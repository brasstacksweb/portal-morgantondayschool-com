<?php
/**
 * Yii Application Config.
 *
 * Edit this file at your own risk!
 *
 * The array returned by this file will get merged with
 * vendor/craftcms/cms/src/config/app.php and app.[web|console].php, when
 * Craft's bootstrap script is defining the configuration for the entire
 * application.
 *
 * You can define custom modules and system components, and even override the
 * built-in system components.
 *
 * If you want to modify the application config for *only* web requests or
 * *only* console requests, create an app.web.php or app.console.php file in
 * your config/ folder, alongside this one.
 *
 * Read more about application configuration:
 * https://craftcms.com/docs/4.x/config/app.html
 */

use craft\helpers\App;
use modules\components\ComponentsModule;
use modules\forms\FormsModule;
use modules\notifications\NotificationsModule;
use modules\site\SiteModule;
use Psr\Log\LogLevel;
use yii\log\EmailTarget;
use yii\web\HttpException;

$modules = [
    'components-module' => ComponentsModule::class,
    'forms-module' => FormsModule::class,
    'notifications' => NotificationsModule::class,
    'site-module' => SiteModule::class,
];

return [
    'id' => App::env('CRAFT_APP_ID') ?: 'CraftCMS',
    'modules' => $modules,
    'bootstrap' => array_keys($modules),
    'components' => [
        'log' => [
            'targets' => [
                [
                    // 'enabled' => !App::env('CRAFT_DEV_MODE'),
                    'enabled' => false,
                    'class' => EmailTarget::class,
                    'levels' => [LogLevel::ERROR],
                    'logVars' => ['_SERVER.REQUEST_URI'],
                    'except' => [HttpException::class.':404'],
                    'message' => [
                        'to' => ['trevor@brasstacksweb.com'],
                        'subject' => 'portal.morgantondayschool.com Error',
                    ],
                ],
            ],
        ],
    ],
];
