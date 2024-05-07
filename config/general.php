<?php
/**
 * General Configuration
 *
 * All of your system's general configuration settings go in here. You can see a
 * list of the available settings in vendor/craftcms/cms/src/config/GeneralConfig.php.
 *
 * @see \craft\config\GeneralConfig
 */

use craft\config\GeneralConfig;
use craft\helpers\App;

return GeneralConfig::create()
    // Set the default week start day for date pickers (0 = Sunday, 1 = Monday, etc.)
    ->defaultWeekStartDay(1)
    // Prevent generated URLs from including "index.php"
    ->omitScriptNameInUrls()
    // Preload Single entries as Twig variables
    ->preloadSingles()
    // Prevent user enumeration attacks
    ->preventUserEnumeration()
    // Enable Dev Mode (see https://craftcms.com/guides/what-dev-mode-does)
    ->devMode(App::env('CRAFT_DEV_MODE') ?? false)
    // Allow administrative changes
    ->allowAdminChanges(App::env('CRAFT_ALLOW_ADMIN_CHANGES') ?? false)
    // Disallow robots
    ->disallowRobots(App::env('CRAFT_DISALLOW_ROBOTS') ?? false)
    // Set the @webroot alias so the clear-caches command knows where to find CP resources
    ->aliases([
        '@assetBasePath' => App::env('HOME_PATH').'/'.App::env('WEB_DIR').'/'.App::env('ASSETS_DIR'),
        '@assetBaseUrl' => '/'.App::env('ASSETS_DIR'),
        '@webroot' => dirname(__DIR__) . '/public',
    ])
    ->pageTrigger('?page')
;
