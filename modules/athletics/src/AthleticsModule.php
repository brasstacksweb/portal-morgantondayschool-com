<?php

namespace modules\athletics;

use craft\web\twig\variables\CraftVariable;
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
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $variable = $event->sender;
                $variable->set('signups', $this->signups);
            }
        );
    }
}
