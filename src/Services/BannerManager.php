<?php

/**
 * @package WooApp\Services
 */

namespace WooApp\Services;

use WooApp\Common\Constants;

defined('ABSPATH') || exit;

/**
 * Banner Manager
 * Manages app banner configuration and operations including groups
 */
class BannerManager
{
    const OPTION_BANNER_GROUPS = 'wooapp_banner_groups';
    /**
     * Get all banners
     * 
     * @param string|null $group Filter by group, null to get all
     * @return array Array of banners with their metadata
     */
    public static function get_banners($group = null)
    {
        $banners = get_option(Constants::OPTION_APP_BANNERS, array());
        
        // Ensure banners is an array
        if (!is_array($banners)) {
            return array();
        }
        
        // Filter by group if specified
        if ($group !== null) {
            $banners = array_filter($banners, function($banner) use ($group) {
                $banner_group = isset($banner['group']) ? $banner['group'] : 'default';
                return $banner_group === $group;
            });
        }
        
        // Sort by group then by order
        usort($banners, function($a, $b) {
            $group_a = isset($a['group']) ? $a['group'] : 'default';
            $group_b = isset($b['group']) ? $b['group'] : 'default';
            
            // First sort by group
            if ($group_a !== $group_b) {
                return strcmp($group_a, $group_b);
            }
            
            // Then sort by order within the same group
            $order_a = isset($a['order']) ? (int)$a['order'] : 0;
            $order_b = isset($b['order']) ? (int)$b['order'] : 0;
            return $order_a - $order_b;
        });
        
        // Ensure order values are sequential (0, 1, 2, ...)
        if ($group === null) {
            // For all banners, reindex order by group
            $grouped = array();
            foreach ($banners as &$banner) {
                $banner_group = isset($banner['group']) ? $banner['group'] : 'default';
                if (!isset($grouped[$banner_group])) {
                    $grouped[$banner_group] = 0;
                }
                $banner['order'] = $grouped[$banner_group];
                $grouped[$banner_group]++;
            }
            unset($banner);
        } else {
            // For single group, simple reindex
            foreach ($banners as $index => &$banner) {
                $banner['order'] = $index;
            }
            unset($banner);
        }
        
        return array_values($banners);
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
        
        // Get group for this banner
        $group = isset($banner_data['group']) ? sanitize_text_field($banner_data['group']) : 'default';
        
        // Calculate correct order - count banners in same group
        $group_count = 0;
        foreach ($banners as $banner) {
            $banner_group = isset($banner['group']) ? $banner['group'] : 'default';
            if ($banner_group === $group) {
                $group_count++;
            }
        }
        $new_order = $group_count;
        
        // Validate and sanitize banner data
        $banner = array(
            'id' => $banner_id,
            'group' => $group,
            'image_id' => isset($banner_data['image_id']) ? (int)$banner_data['image_id'] : 0,
            'image_url' => isset($banner_data['image_url']) ? sanitize_url($banner_data['image_url']) : '',
            'deeplink' => isset($banner_data['deeplink']) ? sanitize_text_field($banner_data['deeplink']) : '',
            'order' => isset($banner_data['order']) ? (int)$banner_data['order'] : $new_order,
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
                if (isset($banner_data['group'])) {
                    $banner['group'] = sanitize_text_field($banner_data['group']);
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

    // ============================================
    // Banner Group Management Methods
    // ============================================

    /**
     * Generate a unique group key from a label.
     * Uses sanitize_title for slug generation, appends a numeric suffix if key already exists.
     *
     * @param string $label Group label
     * @param array  $existing_keys Existing group keys to check against
     * @return string Generated unique key
     */
    public static function generate_group_key($label, $existing_keys = array())
    {
        $base_key = sanitize_title($label);
        if (empty($base_key)) {
            $base_key = 'group';
        }

        $key = $base_key;
        $counter = 1;
        while (in_array($key, $existing_keys, true)) {
            $key = $base_key . '-' . $counter;
            $counter++;
        }

        return $key;
    }

    /**
     * Get all banner groups
     * 
     * @return array Associative array of group_key => group_label
     */
    public static function get_groups()
    {
        $groups = get_option(self::OPTION_BANNER_GROUPS, array());
        
        // Ensure groups is an array
        if (!is_array($groups)) {
            $groups = array();
        }
        
        // Migrate from old flat array format ['default', 'home'] to key => label format
        if (!empty($groups) && array_values($groups) === $groups) {
            $migrated = array();
            foreach ($groups as $name) {
                $migrated[sanitize_key($name)] = $name;
            }
            $groups = $migrated;
            update_option(self::OPTION_BANNER_GROUPS, $groups);
        }

        // Always include default group
        if (!isset($groups['default'])) {
            $groups = array_merge(array('default' => __('Default', WOOAPP_TEXT_DOMAIN)), $groups);
        }
        
        return $groups;
    }

    /**
     * Get group label by key
     *
     * @param string $group_key Group key
     * @return string Group label, or empty string if not found
     */
    public static function get_group_label($group_key)
    {
        $groups = self::get_groups();
        return isset($groups[$group_key]) ? $groups[$group_key] : '';
    }

    /**
     * Add a new banner group
     * 
     * @param string $group_label Display label for the group
     * @return array|false Array with 'key' and 'label' on success, false on failure
     */
    public static function add_group($group_label)
    {
        $group_label = sanitize_text_field($group_label);
        
        // Validate group label
        if (empty($group_label)) {
            return false;
        }
        
        $groups = self::get_groups();
        
        // Generate unique key from label
        $group_key = self::generate_group_key($group_label, array_keys($groups));
        
        // Key collision should not happen after generate_group_key, but guard anyway
        if (isset($groups[$group_key])) {
            return false;
        }
        
        $groups[$group_key] = $group_label;
        
        if (update_option(self::OPTION_BANNER_GROUPS, $groups)) {
            return array('key' => $group_key, 'label' => $group_label);
        }

        return false;
    }

    /**
     * Delete a banner group and all its banners
     * 
     * @param string $group_key Group key
     * @return bool True on success, false on failure
     */
    public static function delete_group($group_key)
    {
        // Don't allow deleting default group
        if ($group_key === 'default') {
            return false;
        }
        
        $group_key = sanitize_key($group_key);
        
        // Delete all banners in this group
        $banners = self::get_banners();
        foreach ($banners as $banner) {
            $banner_group = isset($banner['group']) ? $banner['group'] : 'default';
            if ($banner_group === $group_key) {
                self::delete_banner($banner['id']);
            }
        }
        
        // Remove from groups list
        $groups = self::get_groups();
        unset($groups[$group_key]);
        
        return update_option(self::OPTION_BANNER_GROUPS, $groups);
    }

    /**
     * Update a banner group label (key stays immutable)
     * 
     * @param string $group_key Group key
     * @param string $new_label New display label
     * @return bool True on success, false on failure
     */
    public static function update_group_label($group_key, $new_label)
    {
        $group_key = sanitize_key($group_key);
        $new_label = sanitize_text_field($new_label);
        
        if (empty($new_label)) {
            return false;
        }
        
        $groups = self::get_groups();
        
        if (!isset($groups[$group_key])) {
            return false;
        }
        
        $groups[$group_key] = $new_label;
        
        return update_option(self::OPTION_BANNER_GROUPS, $groups);
    }

    /**
     * Check if a group exists by key
     * 
     * @param string $group_key Group key
     * @return bool True if group exists, false otherwise
     */
    public static function group_exists($group_key)
    {
        $groups = self::get_groups();
        return isset($groups[$group_key]);
    }
}
