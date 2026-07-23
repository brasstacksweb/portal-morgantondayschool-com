<?php

namespace modules\athletics;

use craft\events\RegisterUrlRulesEvent;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use modules\athletics\services\Signups;
use yii\base\Event;
use yii\base\Module;

class AthleticsModule extends Module
{
    public function init()
    {
        parent::init();

        \Craft::setAlias('@modules/athletics', $this->getBasePath());

        $this->controllerNamespace = 'modules\athletics\controllers';

        $this->setComponents([
            'signups' => Signups::class,
        ]);

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['athletics/signups/save'] = 'athletics/signups/save';
                $event->rules['athletics/signups/commit'] = 'athletics/signups/commit';
                $event->rules['athletics/signups/withdraw'] = 'athletics/signups/withdraw';
            }
        );

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $variable = $event->sender;
                $variable->set('signups', $this->signups);
            }
        );
    }
}
