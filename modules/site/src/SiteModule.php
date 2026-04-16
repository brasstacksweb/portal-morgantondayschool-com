<?php

namespace modules\site;

use craft\events\RegisterUrlRulesEvent;
use craft\helpers\App;
use craft\mail\Mailer;
use craft\web\UrlManager;
use yii\base\Event;
use yii\base\Module;
use yii\mail\MailEvent;

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

        // Override email address for all messages in development
        Event::on(
            Mailer::class,
            Mailer::EVENT_BEFORE_PREP,
            function (MailEvent $event) {
                if (!App::devMode()) {
                    return;
                }

                $devEmail = App::env('DEV_EMAIL');

                if (!$devEmail) {
                    // If the .env variable isn't set, prevent the email from sending just in case
                    $event->isValid = false;
                    \Craft::dd('DEV_EMAIL environment variable not set. Email sending prevented.');

                    return;
                }

                // For debugging, add the original recipients to the subject
                $to = is_array($event->message->getTo())
                    ? implode(', ', array_keys($event->message->getTo()))
                    : $event->message->getTo();

                $subject = '[TEST to '.$to.'] '.$event->message->getSubject();

                $event->message->setTo($devEmail);
                $event->message->setCc(null);
                $event->message->setBcc(null);
                $event->message->setSubject($subject);
            }
        );
    }
}
