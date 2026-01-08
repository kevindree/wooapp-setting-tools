<?php

/**
 * @package WooApp\Common
 */

namespace WooApp\Common;

defined('ABSPATH') || exit;

/**
 * Version Compatibility Checker
 * Ensures WordPress and WooCommerce versions are compatible
 */
class VersionChecker
{
    /**
     * Minimum required WordPress version
     */
    const MIN_WP_VERSION = '6.0';

    /**
     * Minimum required PHP version
     */
    const MIN_PHP_VERSION = '7.4';

    /**
     * Check if environment meets requirements
     * @return array Array with 'success' and 'errors' keys
     */
    public static function check()
    {
        $errors = array();

        // Check WordPress version
        if (!self::checkWordPressVersion()) {
            $errors[] = sprintf(
                'WooApp requires WordPress %s or higher. You are running %s.',
                self::MIN_WP_VERSION,
                get_bloginfo('version')
            );
        }

        // Check PHP version
        if (!self::checkPHPVersion()) {
            $errors[] = sprintf(
                'WooApp requires PHP %s or higher. You are running %s.',
                self::MIN_PHP_VERSION,
                phpversion()
            );
        }

        // Note: WooCommerce version check is not performed during initialization
        // to avoid triggering WooCommerce compatibility checks.
        // It will be verified later when WooCommerce is actually needed.

        return array(
            'success' => empty($errors),
            'errors'  => $errors,
        );
    }

    /**
     * Check WordPress version
     * @return bool
     */
    private static function checkWordPressVersion()
    {
        return version_compare($GLOBALS['wp_version'], self::MIN_WP_VERSION, '>=');
    }

    /**
     * Check PHP version
     * @return bool
     */
    private static function checkPHPVersion()
    {
        return version_compare(phpversion(), self::MIN_PHP_VERSION, '>=');
    }

    /**
     * Display version error admin notice
     */
    public static function displayErrors($errors)
    {
        if (empty($errors) || !is_admin()) {
            return;
        }

        add_action('admin_notices', function () use ($errors) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <strong><?php esc_html_e('WooApp Setting Tools:', WOOAPP_TEXT_DOMAIN); ?></strong>
                    <?php echo wp_kses_post(implode('<br>', $errors)); ?>
                </p>
            </div>
            <?php
        });
    }
}
