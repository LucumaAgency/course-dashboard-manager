<?php
/**
 * Product Loader - Alternative method to load products
 * Created: 2024-12-13
 * 
 * This file provides a reliable way to load products without depending on wc_get_products
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get all products using direct database query
 * 
 * @return array Products array with id => [name, regular_price, sale_price]
 */
function cbm_get_all_products_direct() {
    global $wpdb;
    
    $products = [];
    
    // Get all published products directly from database
    $product_results = $wpdb->get_results(
        "SELECT ID, post_title 
         FROM {$wpdb->posts} 
         WHERE post_type = 'product' 
         AND post_status = 'publish' 
         ORDER BY post_title ASC"
    );
    
    foreach ($product_results as $product) {
        // Get prices from post meta
        $regular_price = get_post_meta($product->ID, '_regular_price', true);
        $sale_price = get_post_meta($product->ID, '_sale_price', true);
        
        $products[$product->ID] = [
            'name' => $product->post_title,
            'regular_price' => $regular_price ?: '',
            'sale_price' => $sale_price ?: ''
        ];
    }
    
    return $products;
}

/**
 * Get products for dropdown - always uses direct query
 * 
 * @return string JSON encoded products array
 */
function cbm_get_products_for_dropdown() {
    $products = cbm_get_all_products_direct();
    return json_encode($products);
}

/**
 * AJAX handler to get products
 */
add_action('wp_ajax_cbm_get_products', 'cbm_ajax_get_products');
function cbm_ajax_get_products() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $products = cbm_get_all_products_direct();
    
    wp_send_json_success([
        'products' => $products,
        'count' => count($products),
        'method' => 'direct_query',
        'timestamp' => current_time('Y-m-d H:i:s')
    ]);
}

// Add to admin footer to inject products via AJAX if needed
add_action('admin_footer', function() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'course-box-tables') {
        return;
    }
    ?>
    <script>
    // Backup method to load products via AJAX if main method fails
    (function($) {
        $(document).ready(function() {
            // Check if products are loaded
            if (typeof allProducts !== 'undefined' && Object.keys(allProducts).length === 0) {
                console.log('[CBM] No products loaded, trying AJAX method...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cbm_get_products'
                    },
                    success: function(response) {
                        if (response.success && response.data.products) {
                            window.allProducts = response.data.products;
                            console.log('[CBM] Loaded ' + response.data.count + ' products via AJAX');
                            
                            // Show notification
                            $('<div>')
                                .css({
                                    position: 'fixed',
                                    top: '50px',
                                    right: '20px',
                                    background: '#4CAF50',
                                    color: 'white',
                                    padding: '15px',
                                    borderRadius: '5px',
                                    zIndex: 99999
                                })
                                .text('✅ Loaded ' + response.data.count + ' products via backup method')
                                .appendTo('body')
                                .delay(3000)
                                .fadeOut();
                                
                            // Trigger reload of tables if needed
                            if (typeof reloadTables === 'function') {
                                reloadTables();
                            }
                        }
                    },
                    error: function() {
                        console.error('[CBM] Failed to load products via AJAX');
                    }
                });
            } else if (typeof allProducts !== 'undefined') {
                console.log('[CBM] Products already loaded:', Object.keys(allProducts).length);
            }
        });
    })(jQuery);
    </script>
    <?php
});
?>