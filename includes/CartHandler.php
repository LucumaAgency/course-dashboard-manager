<?php
/**
 * Cart Handler for Course Box Manager
 * 
 * Handles AJAX add to cart functionality compatible with FunnelKit
 */

namespace CourseBoxManager;

class CartHandler {
    
    public function __construct() {
        // TEMPORARILY DISABLED - Using main handler in course-dashboard-manager.php
        // Hook into WordPress AJAX actions with our own action name
        // add_action('wp_ajax_cbm_add_to_cart', [$this, 'handle_add_to_cart']);
        // add_action('wp_ajax_nopriv_cbm_add_to_cart', [$this, 'handle_add_to_cart']);
        
        // Hook into woocommerce_add_to_cart with high priority to override others
        // add_action('wp_ajax_woocommerce_add_to_cart', [$this, 'handle_add_to_cart'], 5);
        // add_action('wp_ajax_nopriv_woocommerce_add_to_cart', [$this, 'handle_add_to_cart'], 5);
        
        // Log initialization
        error_log('[CBM CartHandler] Temporarily disabled - using main handler');
    }
    
    /**
     * Handle AJAX add to cart request
     */
    public function handle_add_to_cart() {
        error_log('[CBM CartHandler] handle_add_to_cart called');
        error_log('[CBM CartHandler] POST data: ' . json_encode($_POST));
        
        // Verify nonce
        $nonce_valid = false;
        if (isset($_POST['security']) && wp_verify_nonce($_POST['security'], 'woocommerce-add-to-cart')) {
            $nonce_valid = true;
            error_log('[CBM CartHandler] Nonce valid via security parameter');
        } elseif (isset($_POST['nonce']) && wp_verify_nonce($_POST['nonce'], 'woocommerce-add-to-cart')) {
            $nonce_valid = true;
            error_log('[CBM CartHandler] Nonce valid via nonce parameter');
        }
        
        if (!$nonce_valid) {
            error_log('[CBM CartHandler] Invalid nonce');
            wp_send_json_error(['message' => 'Invalid security token']);
            return;
        }
        
        // Get product details
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;
        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
        
        if (!$product_id) {
            wp_send_json_error(['message' => 'No product ID provided']);
            return;
        }
        
        // Prepare cart item data for course dates
        $cart_item_data = array();
        if (!empty($_POST['course_date'])) {
            $cart_item_data['course_date'] = sanitize_text_field($_POST['course_date']);
        }
        if (!empty($_POST['start_date'])) {
            $cart_item_data['start_date'] = sanitize_text_field($_POST['start_date']);
        }
        
        // Clear any previous errors
        wc_clear_notices();
        
        // Add to cart
        $cart_item_key = WC()->cart->add_to_cart(
            $product_id,
            $quantity,
            $variation_id,
            array(),
            $cart_item_data
        );
        
        if ($cart_item_key) {
            // Product added successfully
            do_action('woocommerce_ajax_added_to_cart', $product_id);
            
            // Get mini cart HTML
            ob_start();
            woocommerce_mini_cart();
            $mini_cart = ob_get_clean();
            
            // Get cart fragments
            $fragments = apply_filters('woocommerce_add_to_cart_fragments', array(
                'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
            ));
            
            // Add cart count fragments
            $cart_count = WC()->cart->get_cart_contents_count();
            $fragments['.cart-count'] = '<span class="cart-count">' . $cart_count . '</span>';
            $fragments['.hfe-cart-count'] = '<span class="hfe-cart-count">' . $cart_count . '</span>';
            
            // Check if FunnelKit is active
            $use_funnelkit = class_exists('FKCart') || class_exists('\\FKCart') || defined('FKCART_VERSION');
            
            // Send success response
            $response = array(
                'success' => true,
                'fragments' => $fragments,
                'cart_hash' => WC()->cart->get_cart_hash(),
                'cart_count' => $cart_count,
                'use_funnelkit' => $use_funnelkit
            );
            
            // Allow other plugins to modify the response
            $response = apply_filters('cbm_add_to_cart_response', $response, $product_id, $cart_item_key);
            
            wp_send_json($response);
            
        } else {
            // Failed to add to cart
            $notices = wc_get_notices('error');
            $message = 'Could not add product to cart';
            
            if (!empty($notices)) {
                $message = strip_tags($notices[0]['notice']);
            }
            
            wp_send_json_error(['message' => $message]);
        }
    }
}