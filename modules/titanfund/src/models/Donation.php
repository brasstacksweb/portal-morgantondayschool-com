<?php

namespace modules\titanfund\models;

use modules\components\models\Form as BaseForm;
use modules\titanfund\services\Donations;

/**
 * Titan Fund donation form model.
 *
 * Validates the shape of what the donate dialog posts: an amount and whether the
 * donor is covering the fee. Nothing else — the campaign is resolved server-side
 * from the live entry rather than trusted from the form, and the donor's name and
 * email are collected by Stripe Checkout, not by us.
 *
 * Deliberately does not reuse templates/_components/form.twig: preset radio
 * buttons plus an "other amount" box is bespoke markup, not a list of
 * attributeTypes().
 */
class Donation extends BaseForm
{
    /** Preset amount in cents, as posted by the radio buttons. */
    public string $amount = '';

    /** Optional "other amount" in whole or fractional dollars. Wins when set. */
    public string $customAmount = '';

    public string $coverFee = '';

    public string $redirect = '';

    public function scenarios(): array
    {
        return [
            self::SCENARIO_DEFAULT => [
                'amount',
                'customAmount',
                'coverFee',
                'redirect',
            ],
        ];
    }

    /**
     * Deliberately NOT array_merge(parent::rules(), ...) the way the athletics
     * and forms models do: the base rule set requires a reCAPTCHA token, and
     * this form has none by design. Stripe Radar covers abuse, the endpoint only
     * creates a Checkout Session, and requiring a token would mean loading
     * reCAPTCHA on every homepage view.
     */
    public function rules(): array
    {
        return [
            [['amount', 'customAmount'], 'trim'],
            [['amount'], 'integer'],
            [['customAmount'], 'number'],
            [['redirect'], 'validateHash', 'skipOnEmpty' => true],
            // skipOnEmpty is explicit because Yii defaults it to true, which
            // would skip this check in exactly the case that needs it most: the
            // donor cleared the presets and typed their own amount, leaving
            // `amount` empty. Without it, an unbounded customAmount reaches
            // Stripe unchecked.
            [['amount'], 'validateAmount', 'skipOnEmpty' => false],
        ];
    }

    /**
     * The gift in cents: the "other amount" box wins whenever it holds a
     * positive number, otherwise the selected preset.
     */
    public function getAmountCents(): int
    {
        $custom = (float) $this->customAmount;

        if ($custom > 0) {
            return (int) round($custom * 100);
        }

        return (int) $this->amount;
    }

    public function coversFee(): bool
    {
        return $this->coverFee !== '' && $this->coverFee !== '0';
    }

    /**
     * Bounds are technical, not policy: the floor is Stripe's own minimum charge
     * and the ceiling catches a mistyped card number in the amount box.
     */
    public function validateAmount(): void
    {
        $cents = $this->getAmountCents();

        if ($cents <= 0) {
            $this->addError('amount', 'Please choose or enter an amount.');

            return;
        }

        if ($cents < Donations::MIN_CENTS) {
            $this->addError('amount', 'The smallest gift we can process online is $1.');

            return;
        }

        if ($cents > Donations::MAX_CENTS) {
            $this->addError('amount', 'Please contact the school office to make a gift this size.');
        }
    }

    public function getActionPath(): string
    {
        return 'titan-fund/donations/checkout';
    }

    public function getRedirectPath(): string
    {
        return $this->redirect;
    }
}
