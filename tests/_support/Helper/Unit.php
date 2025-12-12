<?php

namespace Tests\Support\Helper;

use Codeception\Module;
use Craft;
use craft\test\TestCase;

class Unit extends Module
{
    public function _before($test)
    {
        // Mock Craft application for testing
        if (!Craft::$app) {
            $this->setupMockCraftApp();
        }
    }

    private function setupMockCraftApp()
    {
        // Initialize minimal Craft app for testing
        // This is where we implement Option B - environment-based mocking
        
        $configPath = dirname(__DIR__, 3) . '/config';
        $envPath = dirname(__DIR__, 3);
        
        // Basic Craft app setup for testing
        define('CRAFT_BASE_PATH', dirname(__DIR__, 3));
        define('CRAFT_VENDOR_PATH', CRAFT_BASE_PATH . '/vendor');
        
        $app = \craft\web\Application::createApplication(
            'web',
            [
                'vendorPath' => CRAFT_VENDOR_PATH,
                'basePath' => CRAFT_BASE_PATH,
                'configPath' => $configPath,
            ]
        );
        
        Craft::$app = $app;
    }

    public function _after($test)
    {
        // Clean up after tests
    }
}