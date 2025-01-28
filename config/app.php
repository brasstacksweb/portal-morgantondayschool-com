<?php
/**
 * Yii Application Config
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

$passwords = ['mds' => 'portal'];
$users = array_keys($passwords);
$user = $_SERVER['PHP_AUTH_USER'] ?? '';
$pass = $_SERVER['PHP_AUTH_PW'] ?? '';
$path = $_SERVER['REDIRECT_URL'] ?? $_SERVER['REQUEST_URI'];
$protected = [];
$exceptions = [];
$bypass = $_GET['bypass'] ?? false;

if (
    php_sapi_name() !== 'cli'
    && (App::env('CRAFT_ENVIRONMENT') === 'staging' || in_array($path, $protected, true))
    && (!in_array($path, $exceptions, true)) // Allow requests in white-listed paths
    && (!in_array($user, $users, true) || $pass !== $passwords[$user]) // Check credentials
    && !((bool) $bypass)
) {
    header('WWW-Authenticate: Basic realm="MDS Portal"');
    header('HTTP/1.0 401 Unauthorized');
    exit('Not authorized');
}

$modules = [
    'components-module' => \modules\components\ComponentsModule::class,
    'forms-module' => \modules\forms\FormsModule::class,
    'site-module' => \modules\site\SiteModule::class,
];

return [
    'id' => App::env('CRAFT_APP_ID') ?: 'CraftCMS',
    'modules' => $modules,
    'bootstrap' => array_keys($modules),
    'components' => [
        'log' => [
            'targets' => [
                [
                    'enabled' => !App::env('CRAFT_DEV_MODE'),
                    'enabled' => false,
                    'class' => \yii\log\EmailTarget::class,
                    'levels' => [\Psr\Log\LogLevel::ERROR],
                    'logVars' => ['_SERVER.REQUEST_URI'],
                    'except' => [\yii\web\HttpException::class.':404'],
                    'message' => [
                        'to' => ['trevor@brasstacksweb.com'],
                        'subject' => 'portal.morgantondayschool.com Error',
                    ],
                ],
            ],
        ],
    ],
];
