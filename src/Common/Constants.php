<?php

/**
 * @package WooApp\Common
 */

namespace WooApp\Common;

defined('ABSPATH') || exit;

/**
 * Plugin Constants
 */
class Constants
{
    /**
     * Plugin information
     */
    const PLUGIN_NAME = 'WooApp Setting Tools';
    const PLUGIN_DESCRIPTION = 'WooCommerce APP Support Tools';
    const PLUGIN_VERSION = '1.0.0';
    const PLUGIN_AUTHOR = 'Geehootek';
    const PLUGIN_TEXT_DOMAIN = 'wooapp-setting-tools';

    /**
     * REST API namespace and settings
     */
    const REST_NAMESPACE = 'wooapp/v1';
    const OAUTH_TIMESTAMP_WINDOW = 900; // 15 minutes
    const ALLOW_BASIC_AUTH = true;
    const ALLOW_OAUTH = true;
    const API_VERSION = 'v1';

    /**
     * Category grouping settings
     */
    const CATEGORY_GROUPS_ENABLED = true;
    const CATEGORY_GROUPS_DEFAULT = 'normal';

    /**
     * Debug settings
     */
    const DEBUG_LOG_REQUESTS = false;
    const DEBUG_LOG_ERRORS = true;
    const DEBUG_REQUEST_LOG_LIMIT = 1000;

    /**
     * Option names
     */
    const OPTION_SETTINGS = 'wooapp_settings';
    const OPTION_CATEGORY_GROUPS = 'wooapp_category_groups';
    const OPTION_CATEGORY_POSITIONS = 'wooapp_category_positions';
    const OPTION_CATEGORY_POSITION_MAPPING = 'wooapp_category_position_mapping';
    const OPTION_APP_BANNERS = 'wooapp_app_banners';

    /**
     * Meta keys
     */
    const META_CATEGORY_GROUPS = 'wooapp_category_groups';
    const META_API_NONCE = 'wooapp_api_nonce';

    /**
     * Error codes
     */
    const ERROR_INVALID_AUTH = 'wooapp_invalid_auth';
    const ERROR_MISSING_PARAM = 'wooapp_missing_param';
    const ERROR_PERMISSION = 'wooapp_permission_denied';
    const ERROR_NOT_FOUND = 'wooapp_not_found';
}
