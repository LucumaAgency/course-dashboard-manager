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
add_action('wp_ajax_woocommerce_add_to_cart', __NAMESPACE__ . '\\cbm_ajax_add_to_cart');
add_action('wp_ajax_nopriv_woocommerce_add_to_cart', __NAMESPACE__ . '\\cbm_ajax_add_to_cart');

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

// Note: The actual AJAX handler functions will be moved here from the main file
// This is just the structure setup