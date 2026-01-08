<?php

/**
 * @package WooApp
 */

namespace WooApp;

defined('ABSPATH') || exit;

/**
 * Simple PSR-4 Autoloader
 * No Composer required - lightweight autoloading for the plugin
 */
class Autoloader
{
    /**
     * Namespace prefix
     */
    const NAMESPACE_PREFIX = 'WooApp\\';

    /**
     * Base directory
     */
    private static $baseDir = '';

    /**
     * Initialize autoloader
     * @param string $basePath Base directory path
     */
    public static function init($basePath = '')
    {
        if (empty($basePath)) {
            $basePath = dirname(__FILE__);
        }

        self::$baseDir = $basePath;
        spl_autoload_register(array(__CLASS__, 'load'));
    }

    /**
     * Load class file
     * @param string $class Fully qualified class name
     * @return bool
     */
    public static function load($class)
    {
        // Check if class starts with our namespace
        if (strpos($class, self::NAMESPACE_PREFIX) !== 0) {
            return false;
        }

        // Remove namespace prefix
        $relative_class = substr($class, strlen(self::NAMESPACE_PREFIX));

        // Convert namespace to file path
        $file = self::$baseDir . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';

        // Load file if it exists
        if (file_exists($file)) {
            require_once $file;
            return true;
        }

        return false;
    }
}
