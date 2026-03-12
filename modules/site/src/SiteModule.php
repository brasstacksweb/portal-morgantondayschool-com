<?php

namespace modules\site;

use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use yii\base\Event;
use yii\base\Module;

class SiteModule extends Module
{
    public function init()
    {
        parent::init();

        $this->controllerNamespace = 'modules\site\controllers';

        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_SITE_URL_RULES, function (RegisterUrlRulesEvent $event) {
            $event->rules['sitemap.xml'] = 'site-module/sitemap';
            $event->rules['cmd/site/test'] = 'site-module/sitemap/test';
            $event->rules['site.webmanifest'] = 'site-module/sitemap/manifest';
        });
    }
}
