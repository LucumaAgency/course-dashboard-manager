<?php
/**
 * Course Dashboard Manager - Global Helper Functions
 *
 * These functions are available in the global namespace for backward compatibility
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper function to safely get ACF field with fallback to post meta
 *
 * @param string $field Field name
 * @param int|false $post_id Post ID or false for current post
 * @param mixed $default Default value if field is empty
 * @return mixed Field value or default
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

/**
 * Helper function to safely update ACF field with fallback to post meta
 *
 * @param string $field Field name
 * @param mixed $value Value to set
 * @param int|false $post_id Post ID or false for current post
 * @return bool Success status
 */
function cbm_update_field($field, $value, $post_id = false) {
    if (function_exists('update_field')) {
        return update_field($field, $value, $post_id);
    }
    // Fallback to post meta
    return update_post_meta($post_id, $field, $value);
}

/**
 * Calculate total seats sold for a product/course
 *
 * @param int $product_id WooCommerce product ID
 * @param string|null $date_text Optional specific date to filter by
 * @return int Number of seats sold
 */
function calculate_seats_sold($product_id, $date_text = null) {
    if (!$product_id || !function_exists('wc_get_orders')) {
        return 0;
    }

    // Query only completed orders for better performance
    $args = [
        'status' => ['wc-completed'],
        'limit' => -1,
        'return' => 'ids',
        'date_query' => [
            'after' => '2020-01-01', // Reasonable cutoff date
        ],
    ];

    $order_ids = wc_get_orders($args);
    $total_sold = 0;

    foreach ($order_ids as $order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            continue;
        }

        foreach ($order->get_items() as $item) {
            // Check if this item is for our product
            if ($item->get_product_id() != $product_id) {
                continue;
            }

            // If filtering by date, check the course_date meta
            if ($date_text) {
                $item_date = $item->get_meta('course_date');
                if ($item_date && $item_date === $date_text) {
                    $total_sold += $item->get_quantity();
                }
            } else {
                // No date filter, count all
                $total_sold += $item->get_quantity();
            }
        }
    }

    return $total_sold;
}

/**
 * Safely decode JSON with validation
 *
 * @param string $json JSON string to decode
 * @param bool $associative Whether to return associative array
 * @param mixed $default Default value if decode fails
 * @return mixed Decoded data or default value
 */
function cbm_json_decode($json, $associative = true, $default = []) {
    if (empty($json)) {
        return $default;
    }

    // Decode JSON
    $decoded = json_decode(stripslashes($json), $associative);

    // Check for JSON errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('[CBM] JSON decode error: ' . json_last_error_msg() . ' - Input: ' . substr($json, 0, 100));
        return $default;
    }

    // Validate result type matches expected
    if ($associative && !is_array($decoded)) {
        error_log('[CBM] JSON decode returned non-array when array expected');
        return $default;
    }

    return $decoded;
}
