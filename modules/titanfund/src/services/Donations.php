<?php

namespace modules\titanfund\services;

use craft\elements\Entry;
use modules\titanfund\records\Donation as DonationRecord;
use yii\base\Component;
use yii\caching\TagDependency;
use yii\db\IntegrityException;

/**
 * Titan Fund donations service.
 *
 * Query and mutation surface for the two gift sources: the campaign entry's
 * offline gift rows (CMS content) and the online gifts in
 * {{%titanfund_donations}}. Deals in entries, records, and primitives; the
 * HTTP/form layer sits on top and calls in here.
 *
 * Stripe is deliberately absent — that lives in services\Checkout, so totals
 * stay readable without an API key and the two concerns fail independently.
 */
class Donations extends Component
{
    public const STATUS_PAID = 'paid';
    public const STATUS_REFUNDED = 'refunded';

    public const STATE_ACTIVE = 'active';
    public const STATE_GOAL_MET = 'goal-met';

    /**
     * Stripe's standard US card pricing, used to gross up a gift when the donor
     * opts to cover the fee. Confirm against the school's actual rate —
     * nonprofits often negotiate lower, and an over-estimate means donors
     * slightly overpay.
     */
    public const FEE_PERCENT = 0.029;
    public const FEE_FIXED_CENTS = 30;

    /**
     * Stripe declines anything under $0.50. The floor is a cent above that so a
     * covered-fee gross-up can never land below it.
     */
    public const MIN_CENTS = 100;

    /**
     * A guard against a runaway custom amount (a mistyped card number in the
     * amount box), not a policy ceiling.
     */
    public const MAX_CENTS = 99999900;

    private const CACHE_KEY_PREFIX = 'titanfund:progress:';
    private const CACHE_DURATION = 60;

    // --- Campaign -----------------------------------------------------------

    /**
     * The campaign the homepage should show, or null when none is live.
     *
     * "Live" is Craft's own status: the entry is enabled and now falls between
     * its postDate and expiryDate. That is what makes the banner appear and
     * disappear on schedule with no fields of our own, and what implements the
     * decision that a finished campaign simply stops rendering.
     */
    public function getCampaign(): ?Entry
    {
        $campaigns = Entry::find()
            ->section('titanFund')
            ->status('live')
            ->orderBy(['postDate' => SORT_DESC])
            ->limit(2)
            ->all();

        if (count($campaigns) > 1) {
            // A content mistake, not a data hazard: take the newest and say so.
            \Craft::warning(
                'More than one Titan Fund campaign is live; using the most recent. Set an expiryDate on the older one.',
                __METHOD__
            );
        }

        return $campaigns[0] ?? null;
    }

    // --- Reads --------------------------------------------------------------

    /**
     * Everything the banner needs, from one aggregate query plus the campaign's
     * own field data.
     *
     * Cached briefly, and tied to the campaign element so a staff edit in the CP
     * shows up immediately rather than after the TTL.
     *
     * @return array{
     *     goalCents:int,
     *     raisedCents:int,
     *     onlineCents:int,
     *     offlineCents:int,
     *     percent:int,
     *     barPercent:int,
     *     state:string,
     *     familiesGiven:int,
     *     familyCount:?int,
     *     participation:?int,
     * }
     */
    public function getProgress(Entry $campaign): array
    {
        return \Craft::$app->getCache()->getOrSet(
            self::CACHE_KEY_PREFIX.$campaign->id,
            fn () => $this->calculateProgress($campaign),
            self::CACHE_DURATION,
            new TagDependency(['tags' => ["element::{$campaign->id}"]])
        );
    }

    /**
     * Drop the cached figures for a campaign. Called after a gift is recorded so
     * the donor sees their own gift in the bar behind the confetti.
     */
    public function invalidateProgress(int $campaignEntryId): void
    {
        \Craft::$app->getCache()->delete(self::CACHE_KEY_PREFIX.$campaignEntryId);
    }

    // --- Writes -------------------------------------------------------------

    /**
     * Record an online gift. Idempotent by Stripe payment intent id.
     *
     * Two callers race here by design — the webhook and the return-from-checkout
     * confirmation — so "already recorded" is a success, not a failure. The
     * unique index is the only guard needed; there is no transaction because
     * there is nothing else to keep consistent with.
     */
    public function record(array $data): bool
    {
        $record = new DonationRecord();
        $record->campaignEntryId = (int) $data['campaignEntryId'];
        $record->userId = !empty($data['userId']) ? (int) $data['userId'] : null;
        $record->familyKey = $data['familyKey'];
        $record->amountCents = (int) $data['amountCents'];
        $record->currency = strtolower($data['currency'] ?? 'usd');
        $record->coveredFee = !empty($data['coveredFee']);
        $record->stripePaymentIntentId = $data['stripePaymentIntentId'];
        $record->stripeCheckoutSessionId = $data['stripeCheckoutSessionId'] ?? null;
        $record->status = self::STATUS_PAID;

        try {
            $saved = $record->save();
        } catch (IntegrityException) {
            // The unique validator lost the race to the index itself. Same
            // meaning: the other caller got there first.
            return true;
        }

        if (!$saved) {
            if ($record->hasErrors('stripePaymentIntentId')) {
                return true;
            }

            \Craft::error(
                'Could not record Titan Fund donation: '.json_encode($record->getErrors()),
                __METHOD__
            );

            return false;
        }

        $this->invalidateProgress($record->campaignEntryId);

        return true;
    }

    /**
     * Flag a refunded gift so it drops out of the totals. Returns false when the
     * payment was never recorded here, which is not an error worth retrying.
     */
    public function markRefunded(string $paymentIntentId): bool
    {
        $record = DonationRecord::findOne(['stripePaymentIntentId' => $paymentIntentId]);

        if (!$record) {
            return false;
        }

        if ($record->status === self::STATUS_REFUNDED) {
            return true;
        }

        $record->status = self::STATUS_REFUNDED;
        $saved = $record->save(false);

        if ($saved) {
            $this->invalidateProgress((int) $record->campaignEntryId);
        }

        return $saved;
    }

    // --- Helpers ------------------------------------------------------------

    /**
     * Pseudonymous household identity, so participation can count distinct
     * families without this site storing an email address. Mirrors
     * Signups::participantKey().
     */
    public static function familyKey(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    /**
     * What to charge so the campaign nets $netCents after Stripe's cut.
     */
    public static function grossUpForFee(int $netCents): int
    {
        return (int) ceil(($netCents + self::FEE_FIXED_CENTS) / (1 - self::FEE_PERCENT));
    }

    // --- Internals ----------------------------------------------------------

    private function calculateProgress(Entry $campaign): array
    {
        $offline = $this->offlineTotals($campaign);
        $online = $this->onlineTotals((int) $campaign->id);

        $goalCents = (int) (($this->numberOrNull($campaign->goalAmount) ?? 0) * 100);
        $raisedCents = $offline['cents'] + $online['cents'];

        // Uncapped on purpose: "118% of goal" is the number worth showing. Only
        // the bar's fill is clamped.
        $percent = $goalCents > 0 ? (int) floor($raisedCents / $goalCents * 100) : 0;

        $familyCount = $this->numberOrNull($campaign->familyCount);
        $familiesGiven = $offline['families'] + $online['families'];

        return [
            'goalCents' => $goalCents,
            'raisedCents' => $raisedCents,
            'onlineCents' => $online['cents'],
            'offlineCents' => $offline['cents'],
            'percent' => $percent,
            'barPercent' => min($percent, 100),
            'state' => $percent >= 100 ? self::STATE_GOAL_MET : self::STATE_ACTIVE,
            'familiesGiven' => $familiesGiven,
            'familyCount' => $familyCount,
            'participation' => $familyCount > 0
                ? min(100, (int) round($familiesGiven / $familyCount * 100))
                : null,
        ];
    }

    /**
     * Offline gifts live in a Table field on the campaign, so this is an array
     * sum rather than a query. Rows flagged as a repeat gift still count toward
     * dollars but not toward participation — that flag is how staff say "this
     * household is already counted."
     *
     * @return array{cents:int,families:int}
     */
    private function offlineTotals(Entry $campaign): array
    {
        $rows = $campaign->offlineGifts ?? [];
        $cents = 0;
        $families = 0;

        foreach ($rows as $row) {
            $amount = $this->numberOrNull($row['amount'] ?? null);

            if ($amount === null || $amount <= 0) {
                continue;
            }

            $cents += $amount * 100;

            if (empty($row['repeatGift'])) {
                $families++;
            }
        }

        return ['cents' => $cents, 'families' => $families];
    }

    /**
     * @return array{cents:int,families:int}
     */
    private function onlineTotals(int $campaignEntryId): array
    {
        $row = DonationRecord::find()
            ->select([
                'cents' => 'COALESCE(SUM([[amountCents]]), 0)',
                'families' => 'COUNT(DISTINCT [[familyKey]])',
            ])
            ->where([
                'campaignEntryId' => $campaignEntryId,
                'status' => self::STATUS_PAID,
            ])
            ->asArray()
            ->one();

        return [
            'cents' => (int) ($row['cents'] ?? 0),
            'families' => (int) ($row['families'] ?? 0),
        ];
    }

    /**
     * A Number field's value as an int, or null when the field is left empty.
     */
    private function numberOrNull(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }
}
