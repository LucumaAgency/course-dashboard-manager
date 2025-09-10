<?php
/**
 * Course Dashboard Manager - Helper Functions
 * 
 * Common utility functions used throughout the plugin
 */

namespace CourseBoxManager;

/**
 * Helper function to calculate seats sold for a course
 */
function calculate_seats_sold($product_id, $date_text = null) {
    if (!$product_id) {
        return 0;
    }
    
    $args = [
        'status' => ['wc-completed'],
        'limit' => -1,
        'return' => 'ids'
    ];
    
    $orders = wc_get_orders($args);
    $total_sold = 0;
    
    foreach ($orders as $order_id) {
        $order = wc_get_order($order_id);
        if (!$order) continue;
        
        foreach ($order->get_items() as $item) {
            if ($item->get_product_id() != $product_id) continue;
            
            if ($date_text) {
                $item_date = $item->get_meta('course_date');
                if ($item_date && $item_date === $date_text) {
                    $total_sold += $item->get_quantity();
                }
            } else {
                $total_sold += $item->get_quantity();
            }
        }
    }
    
    return $total_sold;
}

/**
 * Get all course groups
 */
function get_course_groups() {
    return get_terms([
        'taxonomy' => 'course_group',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ]);
}

/**
 * Get courses in a group
 */
function get_courses_in_group($group_id) {
    return get_posts([
        'post_type' => 'course',
        'posts_per_page' => -1,
        'tax_query' => [
            [
                'taxonomy' => 'course_group',
                'field' => 'term_id',
                'terms' => $group_id,
            ],
        ],
        'orderby' => 'menu_order',
        'order' => 'ASC'
    ]);
}

// Define global helper functions that need to be accessible everywhere
// These are defined in the global namespace after the file is included

}

// Global namespace functions
namespace {
    
    if (!function_exists('cbm_get_field')) {
        /**
         * Helper function to safely get ACF field
         */
        function cbm_get_field($field, $post_id = false, $default = null) {
            if (function_exists('get_field')) {
                $value = get_field($field, $post_id);
                return $value !== false ? $value : $default;
            }
            // Fallback to post meta
            $value = get_post_meta($post_id, $field, true);
            return $value !== '' ? $value : $default;
        }
    }

    if (!function_exists('cbm_update_field')) {
        /**
         * Helper function to safely update ACF field
         */
        function cbm_update_field($field, $value, $post_id = false) {
            if (function_exists('update_field')) {
                return update_field($field, $value, $post_id);
            }
            // Fallback to post meta
            return update_post_meta($post_id, $field, $value);
        }
    }
}