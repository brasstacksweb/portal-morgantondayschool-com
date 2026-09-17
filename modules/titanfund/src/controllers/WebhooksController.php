<?php

namespace modules\titanfund\controllers;

use craft\web\Controller;
use modules\titanfund\TitanFundModule;
use yii\web\Response;

/**
 * Receives Stripe webhooks.
 *
 * Insurance for the return-from-checkout confirmation: it records the gifts of
 * donors who pay and then close the tab, and it is the only path that learns
 * about refunds. Recording is idempotent on the payment intent id, so this and
 * the confirmation can both fire for the same gift.
 *
 * Answers 400 only when the signature cannot be verified. Everything else —
 * including events we ignore and gifts we could not save — gets a 200, so
 * Stripe retries delivery problems rather than our own logic errors.
 *
 * This URL must be listed in craft-basic-auth's exceptedPaths in any
 * environment where basic auth is on, or Stripe gets a 401 and retries for days.
 */
class WebhooksController extends Controller
{
    // Stripe cannot send a CSRF token; the signature check stands in for it.
    public $enableCsrfValidation = false;

    protected array|bool|int $allowAnonymous = true;

    public function actionStripe(): Response
    {
        $this->requirePostRequest();

        $checkout = TitanFundModule::getInstance()->checkout;
        $donations = TitanFundModule::getInstance()->donations;

        $event = $checkout->constructEvent(
            $this->request->getRawBody(),
            (string) $this->request->getHeaders()->get('Stripe-Signature', '')
        );

        if (!$event) {
            return $this->respond(400);
        }

        switch ($event->type) {
            // async_payment_succeeded covers delayed methods (bank debits), where
            // the session completes before the money has actually moved. For
            // cards, completed already arrives paid and the second event never
            // fires.
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                $session = $checkout->normalizeSession($event->data->object);
                $campaign = $session['paid'] ? $donations->getCampaignForSession($session) : null;

                if (!$campaign) {
                    // Unpaid (a delayed method still settling) or started by
                    // another integration on the same Stripe account.
                    break;
                }

                if (!$donations->recordCheckout($campaign, $session)) {
                    \Craft::error('A webhook could not record a paid donation: '.$session['paymentIntentId'], __METHOD__);
                }

                break;

            case 'charge.refunded':
                $charge = $event->data->object;
                $intent = $charge->payment_intent;
                $paymentIntentId = is_string($intent) ? $intent : ($intent->id ?? null);

                // Gross is the recorded amount and there are no partial amounts
                // in v1, so only a full refund removes a gift from the totals.
                if (!$charge->refunded) {
                    // Logged at info: the charge may belong to another integration
                    // on the same account, not to a gift recorded here.
                    \Craft::info("Partial refund left unchanged in the Titan Fund totals: {$paymentIntentId}", __METHOD__);

                    break;
                }

                if ($paymentIntentId) {
                    $donations->markRefunded($paymentIntentId);
                }

                break;

            default:
                \Craft::info("Ignored Stripe webhook event: {$event->type}", __METHOD__);
        }

        return $this->respond(200);
    }

    private function respond(int $status): Response
    {
        $this->response->setStatusCode($status);
        $this->response->format = Response::FORMAT_RAW;
        $this->response->data = '';

        return $this->response;
    }
}
