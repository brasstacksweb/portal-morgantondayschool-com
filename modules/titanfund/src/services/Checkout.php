<?php

namespace modules\titanfund\services;

use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\App;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use yii\base\Component;

/**
 * Stripe Checkout service.
 *
 * The only Stripe-aware code in the module. Everything it returns is a plain
 * array or a string, so services\Donations can total gifts without an API key
 * and the two concerns fail independently.
 *
 * Checkout is hosted: donors are redirected to Stripe, which collects the email
 * and family name, takes the payment, and emails the receipt. No card data and
 * no donor PII ever reaches this site, and no publishable key is needed because
 * the browser never talks to Stripe directly.
 */
class Checkout extends Component
{
    public const CURRENCY = 'usd';

    private ?StripeClient $client = null;

    /**
     * Whether online giving can be offered at all. The donate button is hidden
     * until the school's secret key is in place, so the banner degrades to a
     * progress bar driven by offline gifts rather than showing a dead control.
     */
    public function isConfigured(): bool
    {
        return (bool) App::env('STRIPE_SECRET_KEY');
    }

    /**
     * Create a Checkout Session and return the URL to send the donor to, or null
     * if Stripe refused.
     */
    public function createSession(
        Entry $campaign,
        int $grossCents,
        bool $coveredFee,
        ?User $user,
        string $successUrl,
        string $cancelUrl,
    ): ?string {
        // Mirrored onto the payment intent so the refund webhook, which only
        // ever sees a charge, can still tell which campaign to adjust.
        $metadata = [
            'campaignEntryId' => (string) $campaign->id,
            'userId' => $user ? (string) $user->id : '',
            'coveredFee' => $coveredFee ? '1' : '0',
        ];

        $params = [
            'mode' => 'payment',
            // Relabels the Stripe button "Donate".
            'submit_type' => 'donate',
            'line_items' => [
                [
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => self::CURRENCY,
                        'unit_amount' => $grossCents,
                        'product_data' => [
                            'name' => (string) $campaign->title,
                        ],
                    ],
                ],
            ],
            'custom_fields' => [
                [
                    'key' => 'family_name',
                    'label' => ['type' => 'custom', 'custom' => 'Family name'],
                    'type' => 'text',
                    'optional' => false,
                ],
            ],
            'metadata' => $metadata,
            'payment_intent_data' => [
                'description' => (string) $campaign->title,
                'metadata' => $metadata,
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ];

        // Only prefilled for signed-in parents; guests type it into Checkout.
        if ($user && $user->email) {
            $params['customer_email'] = $user->email;
        }

        try {
            $session = $this->client()->checkout->sessions->create($params);
        } catch (ApiErrorException $e) {
            \Craft::error('Stripe checkout session could not be created: '.$e->getMessage(), __METHOD__);

            return null;
        }

        return $session->url;
    }

    /**
     * Look up a finished session, normalized to the fields the recording path
     * needs. Null when Stripe cannot be reached or the id is unknown.
     */
    public function retrieveSession(string $sessionId): ?array
    {
        try {
            $session = $this->client()->checkout->sessions->retrieve($sessionId);
        } catch (ApiErrorException $e) {
            \Craft::warning('Stripe checkout session could not be retrieved: '.$e->getMessage(), __METHOD__);

            return null;
        }

        return $this->normalizeSession($session);
    }

    /**
     * @return array{
     *     paid:bool,
     *     sessionId:string,
     *     paymentIntentId:?string,
     *     amountCents:int,
     *     currency:string,
     *     email:?string,
     *     metadata:array,
     * }
     */
    public function normalizeSession(Session $session): array
    {
        $intent = $session->payment_intent;

        return [
            'paid' => $session->payment_status === 'paid',
            'sessionId' => (string) $session->id,
            'paymentIntentId' => is_string($intent) ? $intent : ($intent->id ?? null),
            'amountCents' => (int) $session->amount_total,
            'currency' => (string) $session->currency,
            'email' => $session->customer_details->email ?? null,
            'metadata' => $session->metadata ? $session->metadata->toArray() : [],
        ];
    }

    /**
     * Verify a webhook payload's signature and return the event, or null when it
     * cannot be trusted. Callers answer 400 on null and nothing else, so Stripe
     * retries only genuine delivery problems.
     */
    public function constructEvent(string $payload, string $signature): ?Event
    {
        $secret = App::env('STRIPE_WEBHOOK_SECRET');

        if (!$secret) {
            \Craft::error('A Stripe webhook arrived but STRIPE_WEBHOOK_SECRET is not set.', __METHOD__);

            return null;
        }

        try {
            return Webhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            \Craft::warning('Rejected a Stripe webhook: '.$e->getMessage(), __METHOD__);

            return null;
        }
    }

    private function client(): StripeClient
    {
        return $this->client ??= new StripeClient((string) App::env('STRIPE_SECRET_KEY'));
    }
}
