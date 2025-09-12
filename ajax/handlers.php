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
        
        $data = array(
            'success' => true,
            'cart_hash' => WC()->cart->get_cart_hash(),
            'cart_count' => $cart_count,
            'fragments' => apply_filters('woocommerce_add_to_cart_fragments', array(
                'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
            ))
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