<?php

namespace modules\notifications;

use craft\events\RegisterUrlRulesEvent;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use modules\notifications\services\Subscriptions;
use yii\base\Event;
use yii\base\Module;

class NotificationsModule extends Module
{
    public function init()
    {
        parent::init();

        \Craft::setAlias('@modules/notifications', $this->getBasePath());

        if (\Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'modules\notifications\console\controllers';
        } else {
            $this->controllerNamespace = 'modules\notifications\controllers';
        }

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['notifications/auth/send-magic-link'] = 'notifications/auth/send-magic-link';
                $event->rules['notifications/auth/verify'] = 'notifications/auth/verify';
                $event->rules['notifications/onboarding/save-subscriptions'] = 'notifications/onboarding/save-subscriptions';
                $event->rules['notifications/notifications/unread-count'] = 'notifications/notifications/unread-count';
                $event->rules['notifications/notifications/mark-read'] = 'notifications/notifications/mark-read';
                $event->rules['notifications/notifications/mark-all-read'] = 'notifications/notifications/mark-all-read';
            }
        );

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $variable = $event->sender;
                $variable->set('subscriptions', Subscriptions::class);
            }
        );
    }
}
