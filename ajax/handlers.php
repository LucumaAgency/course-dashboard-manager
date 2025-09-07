<?php
/**
 * Course Dashboard Manager - AJAX Handlers
 * 
 * All AJAX request handlers for the plugin
 */

namespace CourseBoxManager\Ajax;

// Group management AJAX handlers
add_action('wp_ajax_create_new_course_group', __NAMESPACE__ . '\\create_new_course_group');
add_action('wp_ajax_delete_course_group', __NAMESPACE__ . '\\delete_course_group');
add_action('wp_ajax_assign_course_to_group', __NAMESPACE__ . '\\assign_course_to_group');
add_action('wp_ajax_save_group_settings', __NAMESPACE__ . '\\save_group_settings');
add_action('wp_ajax_apply_group_settings', __NAMESPACE__ . '\\apply_group_settings');

// Course management AJAX handlers
add_action('wp_ajax_save_course_settings', __NAMESPACE__ . '\\save_course_settings');
add_action('wp_ajax_save_inline_dates', __NAMESPACE__ . '\\save_inline_dates');
add_action('wp_ajax_save_table_row_data', __NAMESPACE__ . '\\save_table_row_data');
add_action('wp_ajax_remove_course_from_group', __NAMESPACE__ . '\\remove_course_from_group');
add_action('wp_ajax_delete_course', __NAMESPACE__ . '\\delete_course');
add_action('wp_ajax_delete_table_row', __NAMESPACE__ . '\\delete_table_row');

// Cart AJAX handlers
// Commented out - using the handler in main file instead to avoid conflicts
// add_action('wp_ajax_woocommerce_add_to_cart', __NAMESPACE__ . '\\cbm_ajax_add_to_cart');
// add_action('wp_ajax_nopriv_woocommerce_add_to_cart', __NAMESPACE__ . '\\cbm_ajax_add_to_cart');

/**
 * Add to cart AJAX handler
 */
function cbm_ajax_add_to_cart() {
    check_ajax_referer('woocommerce-add-to-cart', 'nonce');
    
    $product_id = apply_filters('woocommerce_add_to_cart_product_id', absint($_POST['product_id']));
    $quantity = empty($_POST['quantity']) ? 1 : wc_stock_amount($_POST['quantity']);
    $variation_id = absint($_POST['variation_id']);
    $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity);
    $product_status = get_post_status($product_id);
    
    if ($passed_validation && WC()->cart->add_to_cart($product_id, $quantity, $variation_id) && 'publish' === $product_status) {
        do_action('woocommerce_ajax_added_to_cart', $product_id);
        
        // Get mini cart HTML
        ob_start();
        woocommerce_mini_cart();
        $mini_cart = ob_get_clean();
        
        // Get cart count
        $cart_count = WC()->cart->get_cart_contents_count();
        
        // Check if FunnelKit Cart is active
        $use_funnelkit = defined('FKCART_VERSION') || class_exists('FKCart');
        
        $data = array(
            'success' => true,
            'cart_hash' => WC()->cart->get_cart_hash(),
            'cart_count' => $cart_count,
            'fragments' => apply_filters('woocommerce_add_to_cart_fragments', array(
                'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
            )),
            'use_funnelkit' => $use_funnelkit
        );
        
        wp_send_json($data);
    } else {
        $data = array(
            'success' => false,
            'product_url' => apply_filters('woocommerce_cart_redirect_after_error', get_permalink($product_id), $product_id)
        );
        
        wp_send_json($data);
    }
}

/**
 * Create new course group
 */
function create_new_course_group() {
    check_ajax_referer('course_box_nonce', 'nonce');
    $group_name = sanitize_text_field($_POST['group_name']);
    $term = wp_insert_term($group_name, 'course_group');
    if (!is_wp_error($term)) {
        wp_send_json_success();
    } else {
        wp_send_json_error($term->get_error_message());
    }
}

/**
 * Delete course group
 */
function delete_course_group() {
    check_ajax_referer('course_box_nonce', 'nonce');
    $group_id = intval($_POST['group_id']);
    
    // Remove the term from all courses first
    $courses = get_posts([
        'post_type' => 'course',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => [
            [
                'taxonomy' => 'course_group',
                'field' => 'term_id',
                'terms' => $group_id,
            ],
        ],
    ]);
    
    foreach ($courses as $course_id) {
        wp_remove_object_terms($course_id, $group_id, 'course_group');
    }
    
    // Delete the term
    $result = wp_delete_term($group_id, 'course_group');
    if (!is_wp_error($result)) {
        wp_send_json_success();
    } else {
        wp_send_json_error($result->get_error_message());
    }
}

/**
 * Assign course to group
 */
function assign_course_to_group() {
    error_log('[CBM Debug] assign_course_to_group called');
    error_log('[CBM Debug] POST data: ' . print_r($_POST, true));
    
    check_ajax_referer('course_box_nonce', 'nonce');
    $course_id = intval($_POST['course_id']);
    $group_id = intval($_POST['group_id']);
    $instructors = isset($_POST['instructors']) ? json_decode(stripslashes($_POST['instructors']), true) : [];
    
    error_log('[CBM Debug] Course ID: ' . $course_id . ', Group ID: ' . $group_id);
    
    if (!$course_id) {
        error_log('[CBM Debug] No course selected');
        wp_send_json_error('No course selected.');
    }
    
    // Clear existing group terms and set new one
    wp_set_post_terms($course_id, [], 'course_group');
    
    if ($group_id > 0) {
        $result = wp_set_post_terms($course_id, [$group_id], 'course_group');
        error_log('[CBM Debug] wp_set_post_terms result: ' . print_r($result, true));
        if (is_wp_error($result)) {
            error_log('[CBM Debug] Error setting terms: ' . $result->get_error_message());
            wp_send_json_error($result->get_error_message());
        }
    }
    
    // Update instructors for this course
    if (!empty($instructors)) {
        update_post_meta($course_id, 'course_instructors', $instructors);
        \CourseBoxManager\cbm_update_field('course_instructors', $instructors, $course_id); // Update ACF field if exists
        
        // Update instructor meta - clear from all instructors first
        $all_instructors = get_posts(['post_type' => 'instructor', 'posts_per_page' => -1, 'fields' => 'ids']);
        foreach ($all_instructors as $instructor_id) {
            $courses = get_post_meta($instructor_id, 'instructor_courses', true) ?: [];
            $courses = array_filter($courses, function($id) use ($course_id) { return $id != $course_id; });
            update_post_meta($instructor_id, 'instructor_courses', $courses);
        }
        
        // Add course to selected instructors
        foreach ($instructors as $instructor_id) {
            $courses = get_post_meta($instructor_id, 'instructor_courses', true) ?: [];
            if (!in_array($course_id, $courses)) {
                $courses[] = $course_id;
                update_post_meta($instructor_id, 'instructor_courses', $courses);
            }
        }
    }
    
    wp_send_json_success();
}

/**
 * Save group settings
 */
function save_group_settings() {
    check_ajax_referer('course_box_nonce', 'nonce');
    
    $group_id = intval($_POST['group_id']);
    $box_state = sanitize_text_field($_POST['box_state']);
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    
    // Get all courses in the group
    $courses_to_update = [];
    if ($group_id) {
        $courses = get_posts([
            'post_type' => 'course',
            'posts_per_page' => -1,
            'tax_query' => [
                [
                    'taxonomy' => 'course_group',
                    'field' => 'term_id',
                    'terms' => $group_id,
                ],
            ],
            'fields' => 'ids'
        ]);
        
        if (!empty($courses)) {
            $courses_to_update = $courses;
        }
    }
    
    // If we have a specific course_id, use that, otherwise use all courses in group
    if ($course_id && !in_array($course_id, $courses_to_update)) {
        $courses_to_update[] = $course_id;
    }
    
    if (empty($courses_to_update)) {
        wp_send_json_error('No courses found in group');
        return;
    }
    
    // Update all courses in the group
    foreach ($courses_to_update as $course_id) {
        // Update box state
        update_post_meta($course_id, 'box_state', $box_state);
    }
    
    // Use the first course for saving the main configuration
    $course_id = $courses_to_update[0];
    
    if ($box_state === 'enroll-buy') {
        // Save buy product configuration
        if (isset($_POST['buy_product_id'])) {
            update_post_meta($course_id, 'buy_product_id', intval($_POST['buy_product_id']));
        }
        
        if (isset($_POST['buy_button_text'])) {
            update_post_meta($course_id, 'buy_button_text', sanitize_text_field($_POST['buy_button_text']));
        }
        
        // Update buy product prices if provided
        if (isset($_POST['buy_product_id']) && $_POST['buy_product_id']) {
            $buy_product_id = intval($_POST['buy_product_id']);
            
            if (isset($_POST['buy_regular_price']) && $_POST['buy_regular_price'] !== '') {
                update_post_meta($buy_product_id, '_regular_price', sanitize_text_field($_POST['buy_regular_price']));
            }
            
            if (isset($_POST['buy_sale_price']) && $_POST['buy_sale_price'] !== '') {
                update_post_meta($buy_product_id, '_sale_price', sanitize_text_field($_POST['buy_sale_price']));
                update_post_meta($buy_product_id, '_price', sanitize_text_field($_POST['buy_sale_price']));
            } else if (isset($_POST['buy_regular_price'])) {
                update_post_meta($buy_product_id, '_price', sanitize_text_field($_POST['buy_regular_price']));
            }
        }
        
        // Save enroll product configuration
        if (isset($_POST['enroll_product_id'])) {
            update_post_meta($course_id, 'enroll_product_id', intval($_POST['enroll_product_id']));
        }
        
        // Save enroll dates
        if (isset($_POST['enroll_dates'])) {
            $enroll_dates = json_decode(stripslashes($_POST['enroll_dates']), true);
            if (is_array($enroll_dates)) {
                // Format dates for storage
                $formatted_dates = [];
                foreach ($enroll_dates as $date_info) {
                    if (!empty($date_info['date'])) {
                        $formatted_dates[] = [
                            'date' => sanitize_text_field($date_info['date']),
                            'stock' => isset($date_info['stock']) ? intval($date_info['stock']) : 20,
                            'button_text' => isset($date_info['button_text']) ? sanitize_text_field($date_info['button_text']) : 'Enroll Now'
                        ];
                    }
                }
                
                // Save using ACF or post meta
                if (function_exists('update_field')) {
                    update_field('course_dates', $formatted_dates, $course_id);
                } else {
                    update_post_meta($course_id, 'course_dates', $formatted_dates);
                }
            }
        }
    } else {
        // Handle other box states
        if (isset($_POST['linked_product_id'])) {
            update_post_meta($course_id, 'linked_product_id', intval($_POST['linked_product_id']));
        }
        
        if (isset($_POST['dates'])) {
            $dates = json_decode(stripslashes($_POST['dates']), true);
            if (is_array($dates)) {
                $formatted_dates = [];
                foreach ($dates as $date_info) {
                    if (!empty($date_info['date'])) {
                        $formatted_dates[] = [
                            'date' => sanitize_text_field($date_info['date']),
                            'stock' => isset($date_info['stock']) ? intval($date_info['stock']) : 20,
                            'button_text' => isset($date_info['button_text']) ? sanitize_text_field($date_info['button_text']) : ''
                        ];
                    }
                }
                
                // Save using ACF or post meta
                if (function_exists('update_field')) {
                    update_field('course_dates', $formatted_dates, $course_id);
                } else {
                    update_post_meta($course_id, 'course_dates', $formatted_dates);
                }
            }
        }
    }
    
    wp_send_json_success(['message' => 'Settings saved successfully']);
}