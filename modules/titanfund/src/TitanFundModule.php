<?php

namespace modules\titanfund;

use craft\events\RegisterUrlRulesEvent;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use modules\titanfund\services\Checkout;
use modules\titanfund\services\Donations;
use yii\base\Event;
use yii\base\Module;

class TitanFundModule extends Module
{
    public function init()
    {
        parent::init();

        \Craft::setAlias('@modules/titanfund', $this->getBasePath());

        $this->controllerNamespace = 'modules\titanfund\controllers';

        $this->setComponents([
            'checkout' => Checkout::class,
            'donations' => Donations::class,
        ]);

        // Athletics posts through actionInput() and needs no rules; these exist
        // because Stripe posts to a fixed URL it is given once, and the progress
        // fetch reads a clean path.
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['titan-fund/checkout'] = 'titan-fund/donations/checkout';
                $event->rules['titan-fund/confirm'] = 'titan-fund/donations/confirm';
                $event->rules['titan-fund/webhook'] = 'titan-fund/webhooks/stripe';
            }
        );

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $variable = $event->sender;
                $variable->set('checkout', $this->checkout);
                $variable->set('donations', $this->donations);
            }
        );
    }
}
