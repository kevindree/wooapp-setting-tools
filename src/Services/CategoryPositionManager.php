<?php

/**
 * @package WooApp\Services
 * Manages product category positions for mobile app display
 */

namespace WooApp\Services;

use WooApp\Common\Constants;

defined('ABSPATH') || exit;

class CategoryPositionManager
{
    /**
     * Get all available positions with their categories
     *
     * @return array
     */
    public static function get_all_positions()
    {
        $positions = get_option(Constants::OPTION_CATEGORY_POSITIONS, array());
        $mapping = get_option(Constants::OPTION_CATEGORY_POSITION_MAPPING, array());

        $result = array();

        foreach ($positions as $position_key => $position_label) {
            $result[$position_key] = array(
                'label'      => $position_label,
                'categories' => isset($mapping[$position_key]) ? $mapping[$position_key] : array(),
            );
        }

        return $result;
    }

    /**
     * Get categories for a specific position
     *
     * @param string $position_key Position key
     * @return array
     */
    public static function get_position_categories($position_key)
    {
        $mapping = get_option(Constants::OPTION_CATEGORY_POSITION_MAPPING, array());
        return isset($mapping[$position_key]) ? $mapping[$position_key] : array();
    }

    /**
     * Get position label
     *
     * @param string $position_key Position key
     * @return string
     */
    public static function get_position_label($position_key)
    {
        $positions = get_option(Constants::OPTION_CATEGORY_POSITIONS, array());
        return isset($positions[$position_key]) ? $positions[$position_key] : '';
    }

    /**
     * Save position configuration
     *
     * @param array $positions Array of position_key => position_label
     * @return bool
     */
    public static function save_positions($positions)
    {
        if (!is_array($positions)) {
            return false;
        }

        // Sanitize positions
        $sanitized = array();
        foreach ($positions as $key => $label) {
            $sanitized[sanitize_key($key)] = sanitize_text_field($label);
        }

        return update_option(Constants::OPTION_CATEGORY_POSITIONS, $sanitized);
    }

    /**
     * Save position to category mapping
     *
     * @param array $mapping Array of position_key => array of category_ids
     * @return bool
     */
    public static function save_position_mapping($mapping)
    {
        if (!is_array($mapping)) {
            return false;
        }

        // Sanitize mapping
        $sanitized = array();
        foreach ($mapping as $position_key => $category_ids) {
            if (!is_array($category_ids)) {
                continue;
            }

            $sanitized[sanitize_key($position_key)] = array_map('intval', $category_ids);
        }

        return update_option(Constants::OPTION_CATEGORY_POSITION_MAPPING, $sanitized);
    }

    /**
     * Add or update a position
     *
     * @param string $position_key Unique position key
     * @param string $position_label Display label for position
     * @param array  $category_ids  Array of category IDs for this position
     * @return bool
     */
    public static function update_position($position_key, $position_label, $category_ids = array())
    {
        $position_key = sanitize_key($position_key);
        $position_label = sanitize_text_field($position_label);
        $category_ids = array_map('intval', (array) $category_ids);

        // Update positions
        $positions = get_option(Constants::OPTION_CATEGORY_POSITIONS, array());
        $positions[$position_key] = $position_label;
        update_option(Constants::OPTION_CATEGORY_POSITIONS, $positions);

        // Update mapping
        $mapping = get_option(Constants::OPTION_CATEGORY_POSITION_MAPPING, array());
        $mapping[$position_key] = $category_ids;
        update_option(Constants::OPTION_CATEGORY_POSITION_MAPPING, $mapping);

        return true;
    }

    /**
     * Delete a position
     *
     * @param string $position_key Position key to delete
     * @return bool
     */
    public static function delete_position($position_key)
    {
        $position_key = sanitize_key($position_key);

        // Update positions
        $positions = get_option(Constants::OPTION_CATEGORY_POSITIONS, array());
        unset($positions[$position_key]);
        update_option(Constants::OPTION_CATEGORY_POSITIONS, $positions);

        // Update mapping
        $mapping = get_option(Constants::OPTION_CATEGORY_POSITION_MAPPING, array());
        unset($mapping[$position_key]);
        update_option(Constants::OPTION_CATEGORY_POSITION_MAPPING, $mapping);

        return true;
    }

    /**
     * Get all product categories with hierarchy information
     * Returns: ['id' => 'name', 'depth' => depth, 'has_children' => bool, 'parent' => parent_id]
     *
     * @return array
     */
    public static function get_product_categories()
    {
        $categories = get_terms(array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'number'     => 0,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ));

        if (is_wp_error($categories)) {
            return array();
        }

        // Build hierarchy structure
        $result = array();
        $by_parent = array();

        // Group categories by parent
        foreach ($categories as $category) {
            $parent_id = $category->parent;
            if (!isset($by_parent[$parent_id])) {
                $by_parent[$parent_id] = array();
            }
            $by_parent[$parent_id][] = $category;
        }

        // Build the tree recursively
        self::build_category_tree($result, 0, $by_parent, 0);

        return $result;
    }

    /**
     * Recursively build category tree with hierarchy information
     * Each entry contains: ['name' => name, 'depth' => depth, 'has_children' => bool, 'parent' => parent_id]
     *
     * @param array $result Reference to result array
     * @param int   $parent_id Parent category ID
     * @param array $by_parent Categories grouped by parent
     * @param int   $depth Current depth level
     */
    private static function build_category_tree(&$result, $parent_id, $by_parent, $depth)
    {
        if (!isset($by_parent[$parent_id])) {
            return;
        }

        foreach ($by_parent[$parent_id] as $category) {
            $has_children = isset($by_parent[$category->term_id]) && !empty($by_parent[$category->term_id]);

            $result[$category->term_id] = array(
                'name'          => $category->name,
                'depth'         => $depth,
                'has_children'  => $has_children,
                'parent'        => $category->parent,
            );

            // Recursively add children
            self::build_category_tree($result, $category->term_id, $by_parent, $depth + 1);
        }
    }
}
