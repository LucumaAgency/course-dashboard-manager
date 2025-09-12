<?php
/**
 * WooCommerce Cache Repair Tool
 * 
 * This file repairs WooCommerce product cache after aggressive cache clearing
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
    <title>WooCommerce Repair Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: green; background: #e8f5e9; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #ffebee; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #1976d2; background: #e3f2fd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #f57c00; background: #fff3e0; padding: 10px; border-radius: 5px; margin: 10px 0; }
        button { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #45a049; }
        pre { background: #f4f4f4; padding: 15px; overflow: auto; border-radius: 5px; }
        h2 { color: #333; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 WooCommerce Cache Repair Tool</h1>
        <p>This tool will repair WooCommerce product cache that was damaged by aggressive cache clearing.</p>
        
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
                    echo '<div class="error">❌ WooCommerce cache is broken! Products exist but wc_get_products returns 0.</div>';
                } elseif ($wc_count == $db_count) {
                    echo '<div class="success">✅ WooCommerce cache appears to be working correctly!</div>';
                } else {
                    echo '<div class="warning">⚠️ Partial cache issue: Database has ' . $db_count . ' but WooCommerce sees ' . $wc_count . '</div>';
                }
            } else {
                echo '<div class="error">❌ WooCommerce is not active or wc_get_products is not available!</div>';
            }
            ?>
        </div>
        
        <?php if (isset($_GET['repair'])): ?>
            <h2>🔄 Repair Process</h2>
            <pre><?php
            echo "Starting WooCommerce cache repair...\n\n";
            
            // Step 1: Clear WooCommerce specific caches
            echo "Step 1: Clearing WooCommerce caches...\n";
            if (function_exists('wc_delete_product_transients')) {
                wc_delete_product_transients();
                echo "✅ Deleted product transients\n";
            }
            
            if (class_exists('WC_Cache_Helper')) {
                WC_Cache_Helper::get_transient_version('product', true);
                echo "✅ Reset product transient version\n";
            }
            
            // Step 2: Clear WooCommerce term counts
            echo "\nStep 2: Updating term counts...\n";
            $product_categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
            foreach ($product_categories as $cat) {
                wp_update_term_count_now([$cat->term_id], 'product_cat');
            }
            echo "✅ Updated " . count($product_categories) . " product categories\n";
            
            $product_tags = get_terms(['taxonomy' => 'product_tag', 'hide_empty' => false]);
            foreach ($product_tags as $tag) {
                wp_update_term_count_now([$tag->term_id], 'product_tag');
            }
            echo "✅ Updated " . count($product_tags) . " product tags\n";
            
            // Step 3: Clear all WC transients
            echo "\nStep 3: Clearing WooCommerce transients...\n";
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wc_%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wc_%'");
            echo "✅ Cleared WooCommerce transients\n";
            
            // Step 4: Clear object cache
            echo "\nStep 4: Flushing caches...\n";
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
                echo "✅ Flushed object cache\n";
            }
            
            // Step 5: Regenerate product lookup tables
            echo "\nStep 5: Regenerating product data...\n";
            if (function_exists('wc_update_product_lookup_tables')) {
                wc_update_product_lookup_tables();
                echo "✅ Updated product lookup tables\n";
            }
            
            // Step 6: Force reload a few products to prime the cache
            echo "\nStep 6: Priming cache with products...\n";
            $sample_products = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' LIMIT 5");
            foreach ($sample_products as $product) {
                $p = wc_get_product($product->ID);
                if ($p) {
                    echo "✅ Loaded product ID " . $product->ID . ": " . $p->get_name() . "\n";
                }
            }
            
            // Step 7: Test the result
            echo "\n" . str_repeat("=", 50) . "\n";
            echo "TESTING RESULTS:\n";
            echo str_repeat("=", 50) . "\n";
            
            $test_products = wc_get_products(['limit' => 5]);
            echo "wc_get_products test (limit 5): " . count($test_products) . " products\n";
            
            $all_products = wc_get_products(['limit' => -1]);
            echo "wc_get_products test (all): " . count($all_products) . " products\n";
            
            if (count($all_products) > 0) {
                echo "\n✅ SUCCESS! WooCommerce cache has been repaired!\n";
                echo "Found " . count($all_products) . " products.\n";
            } else {
                echo "\n❌ FAILED! wc_get_products still returns 0.\n";
                echo "This might require deactivating and reactivating WooCommerce.\n";
            }
            
            ?></pre>
        <?php endif; ?>
        
        <h2>🚀 Actions</h2>
        <p>
            <a href="?repair=1"><button>🔧 Repair WooCommerce Cache</button></a>
            <a href="?"><button style="background: #2196F3;">🔄 Refresh Status</button></a>
        </p>
        
        <h2>💡 Alternative Solutions</h2>
        <div class="info">
            If the repair doesn't work, try these steps:
            <ol>
                <li>Go to WooCommerce → Status → Tools</li>
                <li>Run "Clear transients"</li>
                <li>Run "Regenerate product lookup tables"</li>
                <li>If still broken: Deactivate and reactivate WooCommerce plugin</li>
            </ol>
        </div>
    </div>
</body>
</html>