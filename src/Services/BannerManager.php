<?php

/**
 * @package WooApp\Services
 */

namespace WooApp\Services;

use WooApp\Common\Constants;

defined('ABSPATH') || exit;

/**
 * Banner Manager
 * Manages app banner configuration and operations
 */
class BannerManager
{
    /**
     * Get all banners
     * 
     * @return array Array of banners with their metadata
     */
    public static function get_banners()
    {
        $banners = get_option(Constants::OPTION_APP_BANNERS, array());
        
        // Ensure banners is an array
        if (!is_array($banners)) {
            return array();
        }
        
        // Sort by order
        usort($banners, function($a, $b) {
            $order_a = isset($a['order']) ? (int)$a['order'] : 0;
            $order_b = isset($b['order']) ? (int)$b['order'] : 0;
            return $order_a - $order_b;
        });
        
        return $banners;
    }

    /**
     * Get a single banner by ID
     * 
     * @param string $banner_id Banner ID
     * @return array|null Banner data or null if not found
     */
    public static function get_banner($banner_id)
    {
        $banners = self::get_banners();
        
        foreach ($banners as $banner) {
            if (isset($banner['id']) && $banner['id'] === $banner_id) {
                return $banner;
            }
        }
        
        return null;
    }

    /**
     * Add a new banner
     * 
     * @param array $banner_data Banner data
     * @return string|false Banner ID on success, false on failure
     */
    public static function add_banner($banner_data)
    {
        $banners = get_option(Constants::OPTION_APP_BANNERS, array());
        
        if (!is_array($banners)) {
            $banners = array();
        }
        
        // Use provided ID or generate unique ID
        $banner_id = isset($banner_data['id']) ? $banner_data['id'] : 'banner_' . uniqid();
        
        // Validate and sanitize banner data
        $banner = array(
            'id' => $banner_id,
            'image_id' => isset($banner_data['image_id']) ? (int)$banner_data['image_id'] : 0,
            'image_url' => isset($banner_data['image_url']) ? sanitize_url($banner_data['image_url']) : '',
            'deeplink' => isset($banner_data['deeplink']) ? sanitize_text_field($banner_data['deeplink']) : '',
            'order' => isset($banner_data['order']) ? (int)$banner_data['order'] : count($banners),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        );
        
        // Validate required fields
        if (empty($banner['image_id']) || empty($banner['image_url'])) {
            return false;
        }
        
        $banners[] = $banner;
        
        if (update_option(Constants::OPTION_APP_BANNERS, $banners)) {
            return $banner_id;
        }
        
        return false;
    }

    /**
     * Update a banner
     * 
     * @param string $banner_id Banner ID
     * @param array $banner_data Banner data to update
     * @return bool True on success, false on failure
     */
    public static function update_banner($banner_id, $banner_data)
    {
        $banners = get_option(Constants::OPTION_APP_BANNERS, array());
        
        if (!is_array($banners)) {
            return false;
        }
        
        $found = false;
        foreach ($banners as &$banner) {
            if (isset($banner['id']) && $banner['id'] === $banner_id) {
                // Update allowed fields
                if (isset($banner_data['image_id'])) {
                    $banner['image_id'] = (int)$banner_data['image_id'];
                }
                if (isset($banner_data['image_url'])) {
                    $banner['image_url'] = sanitize_url($banner_data['image_url']);
                }
                if (isset($banner_data['deeplink'])) {
                    $banner['deeplink'] = sanitize_text_field($banner_data['deeplink']);
                }
                if (isset($banner_data['order'])) {
                    $banner['order'] = (int)$banner_data['order'];
                }
                
                $banner['updated_at'] = current_time('mysql');
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            return false;
        }
        
        return update_option(Constants::OPTION_APP_BANNERS, $banners);
    }

    /**
     * Delete a banner
     * 
     * @param string $banner_id Banner ID
     * @return bool True on success, false on failure
     */
    public static function delete_banner($banner_id)
    {
        $banners = get_option(Constants::OPTION_APP_BANNERS, array());
        
        if (!is_array($banners)) {
            return false;
        }
        
        $found = false;
        foreach ($banners as $index => $banner) {
            if (isset($banner['id']) && $banner['id'] === $banner_id) {
                unset($banners[$index]);
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            return false;
        }
        
        // Re-index array
        $banners = array_values($banners);
        
        return update_option(Constants::OPTION_APP_BANNERS, $banners);
    }

    /**
     * Reorder banners
     * 
     * @param array $banner_ids Array of banner IDs in desired order
     * @return bool True on success, false on failure
     */
    public static function reorder_banners($banner_ids)
    {
        $banners = get_option(Constants::OPTION_APP_BANNERS, array());
        
        if (!is_array($banners)) {
            return false;
        }
        
        // Create a mapping of banner IDs to data
        $banner_map = array();
        foreach ($banners as $banner) {
            if (isset($banner['id'])) {
                $banner_map[$banner['id']] = $banner;
            }
        }
        
        // Reorder based on provided array
        $reordered = array();
        $order = 0;
        foreach ($banner_ids as $banner_id) {
            if (isset($banner_map[$banner_id])) {
                $banner = $banner_map[$banner_id];
                $banner['order'] = $order;
                $reordered[] = $banner;
                $order++;
            }
        }
        
        return update_option(Constants::OPTION_APP_BANNERS, $reordered);
    }

    /**
     * Get attachment URL by ID
     * 
     * @param int $attachment_id Attachment ID
     * @return string|false Attachment URL or false if not found
     */
    public static function get_attachment_url($attachment_id)
    {
        return wp_get_attachment_url($attachment_id);
    }

    /**
     * Validate deeplink format
     * 
     * @param string $deeplink Deeplink URL
     * @return bool True if valid, false otherwise
     */
    public static function validate_deeplink($deeplink)
    {
        if (empty($deeplink)) {
            return true; // Empty deeplink is allowed
        }
        
        // Allow custom scheme deeplinks (e.g., wooapp://, scheme://)
        // Also allow http and https URLs
        return preg_match('/^([a-z][a-z0-9+\-.]*:)?\/\//i', $deeplink) || 
               preg_match('/^[a-z][a-z0-9+\-.]*:\/\//i', $deeplink);
    }
}
