<?php
/**
 * WooCommerce AGGRESSIVE Cache Repair Tool
 * 
 * This performs a more aggressive repair of WooCommerce
 */

// Load WordPress - try multiple paths
$possible_paths = [
    $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php',
    dirname(__FILE__) . '/../../../wp-load.php',
    dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php',
    '/var/www/html/wp-load.php'
];

$wp_loaded = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    // Try relative path climbing
    $wp_path = '';
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($wp_path . 'wp-load.php')) {
            require_once($wp_path . 'wp-load.php');
            $wp_loaded = true;
            break;
        }
        $wp_path .= '../';
    }
}

if (!defined('ABSPATH') || !$wp_loaded) {
    die('Error: Could not load WordPress. This file must be in wp-content/plugins/course-dashboard-manager/');
}

// Check permission
if (!current_user_can('manage_options')) {
    die('Access denied - Admin only');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>WooCommerce AGGRESSIVE Repair</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: green; background: #e8f5e9; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #ffebee; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #1976d2; background: #e3f2fd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #f57c00; background: #fff3e0; padding: 10px; border-radius: 5px; margin: 10px 0; }
        button { background: #f44336; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #d32f2f; }
        pre { background: #f4f4f4; padding: 15px; overflow: auto; border-radius: 5px; }
        h2 { color: #333; border-bottom: 2px solid #f44336; padding-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>💣 WooCommerce AGGRESSIVE Repair Tool</h1>
        <div class="warning">
            <strong>⚠️ WARNING:</strong> This tool performs aggressive repairs that may temporarily affect your site. Use with caution!
        </div>
        
        <?php if (isset($_GET['repair'])): ?>
            <h2>🔄 AGGRESSIVE Repair Process</h2>
            <pre><?php
            echo "Starting AGGRESSIVE WooCommerce repair...\n\n";
            global $wpdb;
            
            // Step 1: Delete ALL WooCommerce related transients and options
            echo "Step 1: Deleting ALL WooCommerce cache data...\n";
            $deleted = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%wc_%'");
            echo "Deleted $deleted WooCommerce options/transients\n";
            
            $deleted = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%product%cache%'");
            echo "Deleted product cache entries\n";
            
            // Step 2: Reset WooCommerce tables
            echo "\nStep 2: Resetting WooCommerce lookup tables...\n";
            
            // Clear lookup tables
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wc_product_meta_lookup");
            echo "✅ Cleared product meta lookup table\n";
            
            // Rebuild lookup tables
            $products = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'");
            echo "Found " . count($products) . " products to rebuild\n";
            
            foreach ($products as $product) {
                // Force refresh each product
                clean_post_cache($product->ID);
                $p = wc_get_product($product->ID);
                if ($p) {
                    $p->save(); // This will rebuild lookup data
                    echo ".";
                }
            }
            echo "\n✅ Rebuilt product data\n";
            
            // Step 3: Force WordPress to refresh
            echo "\nStep 3: Forcing WordPress cache refresh...\n";
            wp_cache_flush();
            echo "✅ Flushed WordPress cache\n";
            
            // Delete object cache
            if (function_exists('wp_cache_delete')) {
                wp_cache_delete('all_product_ids', 'woocommerce');
                wp_cache_delete('product-categories', 'product_cat');
                echo "✅ Deleted specific cache keys\n";
            }
            
            // Step 4: Run WooCommerce's own repair functions
            echo "\nStep 4: Running WooCommerce repair functions...\n";
            
            if (class_exists('WC_Install')) {
                WC_Install::create_tables();
                echo "✅ Verified database tables\n";
                
                WC_Install::update_db_version();
                echo "✅ Updated database version\n";
            }
            
            // Step 5: Force regenerate
            echo "\nStep 5: Force regenerating product data...\n";
            if (function_exists('wc_update_product_lookup_tables')) {
                wc_update_product_lookup_tables();
                echo "✅ Updated lookup tables\n";
            }
            
            // Step 6: Clear all transients one more time
            echo "\nStep 6: Final transient clear...\n";
            wc_delete_product_transients();
            delete_transient('wc_products_onsale');
            delete_transient('wc_featured_products');
            delete_transient('wc_outofstock_count');
            delete_transient('wc_low_stock_count');
            echo "✅ Cleared final transients\n";
            
            // Test the result
            echo "\n" . str_repeat("=", 50) . "\n";
            echo "TESTING RESULTS:\n";
            echo str_repeat("=", 50) . "\n";
            
            // Try different query methods
            echo "\nMethod 1 - wc_get_products with no parameters:\n";
            $test1 = wc_get_products();
            echo "Result: " . count($test1) . " products\n";
            
            echo "\nMethod 2 - wc_get_products with limit -1:\n";
            $test2 = wc_get_products(['limit' => -1]);
            echo "Result: " . count($test2) . " products\n";
            
            echo "\nMethod 3 - wc_get_products with status publish:\n";
            $test3 = wc_get_products(['status' => 'publish', 'limit' => -1]);
            echo "Result: " . count($test3) . " products\n";
            
            echo "\nMethod 4 - Direct WP_Query:\n";
            $args = array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => -1
            );
            $query = new WP_Query($args);
            echo "Result: " . $query->found_posts . " products\n";
            
            echo "\nMethod 5 - get_posts:\n";
            $posts = get_posts(['post_type' => 'product', 'numberposts' => -1, 'post_status' => 'publish']);
            echo "Result: " . count($posts) . " products\n";
            
            if (count($test1) > 0 || count($test2) > 0 || count($test3) > 0) {
                echo "\n✅ SUCCESS! WooCommerce has been repaired!\n";
            } else {
                echo "\n❌ STILL BROKEN! You need to:\n";
                echo "1. Deactivate WooCommerce plugin\n";
                echo "2. Wait 10 seconds\n";
                echo "3. Reactivate WooCommerce plugin\n";
                echo "4. Go to WooCommerce → Status → Tools\n";
                echo "5. Run 'Regenerate product lookup tables'\n";
            }
            
            ?></pre>
        <?php elseif (isset($_GET['soft'])): ?>
            <h2>🔧 Soft Repair (Deactivate/Reactivate WooCommerce)</h2>
            <div class="info">
                <?php
                // Check if WooCommerce is active
                $active_plugins = get_option('active_plugins');
                $wc_plugin = 'woocommerce/woocommerce.php';
                
                if (in_array($wc_plugin, $active_plugins)) {
                    // Deactivate
                    deactivate_plugins($wc_plugin);
                    echo "✅ WooCommerce has been deactivated<br>";
                    
                    // Wait and reactivate
                    sleep(2);
                    activate_plugins($wc_plugin);
                    echo "✅ WooCommerce has been reactivated<br><br>";
                    
                    // Test
                    $test = wc_get_products(['limit' => 5]);
                    echo "Testing wc_get_products: " . count($test) . " products found<br>";
                    
                    if (count($test) > 0) {
                        echo '<div class="success">✅ WooCommerce is now working!</div>';
                    } else {
                        echo '<div class="error">❌ Still not working. Try the aggressive repair.</div>';
                    }
                } else {
                    echo '<div class="error">WooCommerce is not active!</div>';
                }
                ?>
            </div>
        <?php else: ?>
            <h2>📊 Current Status</h2>
            <div class="info">
                <?php
                global $wpdb;
                $db_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'");
                echo "Products in database: <strong>$db_count</strong><br>";
                
                if (function_exists('wc_get_products')) {
                    $wc_products = wc_get_products(['limit' => -1]);
                    $wc_count = count($wc_products);
                    echo "Products via wc_get_products(): <strong>$wc_count</strong><br>";
                    
                    if ($wc_count == 0 && $db_count > 0) {
                        echo '<div class="error">❌ WooCommerce cache is SEVERELY broken!</div>';
                    }
                }
                ?>
            </div>
        <?php endif; ?>
        
        <h2>🚀 Repair Options</h2>
        <p>
            <a href="?soft=1"><button style="background: #2196F3;">🔧 Soft Repair (Deactivate/Reactivate)</button></a>
            <a href="?repair=1"><button>💣 AGGRESSIVE Repair</button></a>
            <a href="?"><button style="background: #9E9E9E;">🔄 Refresh</button></a>
        </p>
    </div>
</body>
</html>