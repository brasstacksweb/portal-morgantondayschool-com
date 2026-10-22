<?php

namespace modules\titanfund\controllers;

use craft\helpers\UrlHelper;
use craft\web\Controller;
use craft\web\Response;
use modules\titanfund\services\Donations;
use modules\titanfund\TitanFundModule;

/**
 * Starts a donation and confirms it on the way back.
 *
 * Giving never requires a login, so both actions are anonymous. actionCheckout
 * answers the shared ajax tl-form like every other form: field errors come back
 * through asModelFailure, and success carries the Stripe Checkout URL as the
 * redirect, which tl-form follows.
 */
class DonationsController extends Controller
{
    protected array|bool|int $allowAnonymous = ['checkout', 'confirm'];

    public function actionCheckout(): ?Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $donations = TitanFundModule::getInstance()->donations;
        $checkout = TitanFundModule::getInstance()->checkout;

        $model = Donations::newDonation($this->request->getBodyParams());

        if (!$model->validate()) {
            return $this->asModelFailure($model, 'Please fix the highlighted fields.');
        }

        $campaign = $donations->getCampaign();

        if (!$campaign) {
            \Craft::warning('A donation was posted with no live Titan Fund campaign.', __METHOD__);

            return $this->asFailure('The Titan Fund is not accepting online gifts right now.');
        }

        if (!$checkout->isConfigured()) {
            \Craft::error('A donation was posted but STRIPE_SECRET_KEY is not set.', __METHOD__);

            return $this->asFailure('Online giving is not available right now.');
        }

        // validateHash unhashed returnPath in place.
        $returnPath = $model->returnPath;
        $netCents = $model->getAmountCents();
        $coversFee = $model->coversFee();

        $url = $checkout->createSession(
            $campaign,
            $coversFee ? Donations::grossUpForFee($netCents) : $netCents,
            $coversFee,
            \Craft::$app->getUser()->getIdentity(),
            $this->successUrl($returnPath),
            UrlHelper::siteUrl($returnPath, ['donation' => 'canceled']),
        );

        if (!$url) {
            return $this->asFailure('We could not reach our payment processor. Please try again in a moment.');
        }

        return $this->asSuccess('Continuing to secure checkout.', [], $url);
    }

    /**
     * Confirm the gift the donor has just come back from, record it, and hand
     * back the thank-you markup.
     *
     * A GET with a side effect, which is a deliberate trade: Stripe returns the
     * donor with a GET, and recording here is what makes gifts land before the
     * webhook exists. It is safe because record() is idempotent on the payment
     * intent id, so a refresh, a double-fetch, or a race with the webhook all
     * collapse to one row.
     */
    public function actionConfirm(): Response
    {
        $this->requireAcceptsJson();

        $sessionId = (string) $this->request->getRequiredParam('session_id');
        $donations = TitanFundModule::getInstance()->donations;
        $checkout = TitanFundModule::getInstance()->checkout;

        if (!$checkout->isConfigured()) {
            return $this->asFailure('Online giving is not available right now.');
        }

        $session = $checkout->retrieveSession($sessionId);

        if (!$session || !$session['paid'] || !$session['paymentIntentId']) {
            return $this->asFailure('That donation could not be confirmed.');
        }

        // Taken from the session's own metadata rather than the live campaign:
        // a gift started seconds before a campaign rolled over still belongs to
        // the campaign it was started for. Null means Stripe knows the session
        // but this site did not start it.
        $campaign = $donations->getCampaignForSession($session);

        if (!$campaign) {
            return $this->asFailure('That donation could not be confirmed.');
        }

        if (!$donations->recordCheckout($campaign, $session)) {
            // The donor's money is taken either way, so thank them regardless
            // and let the webhook or a manual reconciliation fix the total.
            \Craft::error('A paid donation could not be recorded: '.$session['paymentIntentId'], __METHOD__);
        }

        $markup = \Craft::$app->getView()->renderTemplate('_components/donate-thanks', [
            'amountCents' => $session['amountCents'],
            'heading' => $campaign->heading ?: 'Titan Fund',
        ]);

        return $this->asSuccess('Thank you for your gift.', ['markup' => $markup]);
    }

    /**
     * Stripe's placeholder has to survive into the redirect literally, so it is
     * appended by hand — http_build_query would percent-encode the braces and
     * Stripe would hand back a session id of "{CHECKOUT_SESSION_ID}".
     */
    private function successUrl(string $returnPath): string
    {
        $url = UrlHelper::siteUrl($returnPath, ['donation' => 'success']);

        return $url.(str_contains($url, '?') ? '&' : '?').'session_id={CHECKOUT_SESSION_ID}';
    }
}
