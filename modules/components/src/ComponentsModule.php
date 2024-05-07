<?php

namespace modules\components;

use Craft;
use craft\web\twig\variables\CraftVariable;
use modules\components\web\twig\extensions\ComponentsExtension;
use modules\components\web\twig\variables\ComponentsVariable;
use yii\base\Event;
use yii\base\Module;

class ComponentsModule extends Module
{
    public function init()
    {
        parent::init();

        if (!Craft::$app->getRequest()->getIsSiteRequest()) {
            return;
        }

        Craft::$app->getView()->registerTwigExtension(new ComponentsExtension());

        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function (Event $event) {
            $event->sender->set('components', ComponentsVariable::class);
        });
    }
}
