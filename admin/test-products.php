<?php
/**
 * Test file to check if products are loading
 */

// Load WordPress
require_once('../../../../wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('Access denied');
}

echo "<h1>Product Loading Test</h1>";
echo "<pre>";

// Test 1: Check if WooCommerce is active
echo "1. WooCommerce class exists: " . (class_exists('WooCommerce') ? 'YES' : 'NO') . "\n";
echo "2. wc_get_products function exists: " . (function_exists('wc_get_products') ? 'YES' : 'NO') . "\n\n";

// Test 2: Try to load products using wc_get_products
if (function_exists('wc_get_products')) {
    echo "3. Loading products with wc_get_products:\n";
    $products = wc_get_products(['limit' => -1, 'status' => 'publish']);
    echo "   Found " . count($products) . " products\n";
    
    if (count($products) > 0) {
        echo "   First 3 products:\n";
        $count = 0;
        foreach ($products as $product) {
            if ($count >= 3) break;
            echo "   - ID: " . $product->get_id() . " | Name: " . $product->get_name() . " | Price: " . $product->get_price() . "\n";
            $count++;
        }
    }
    echo "\n";
}

// Test 3: Try direct database query
echo "4. Loading products with direct database query:\n";
global $wpdb;
$product_posts = $wpdb->get_results(
    "SELECT ID, post_title, post_status 
     FROM {$wpdb->posts} 
     WHERE post_type = 'product' 
     ORDER BY ID DESC 
     LIMIT 10"
);

echo "   Found " . count($product_posts) . " products in database\n";
if (count($product_posts) > 0) {
    echo "   First products:\n";
    foreach ($product_posts as $product) {
        echo "   - ID: {$product->ID} | Title: {$product->post_title} | Status: {$product->post_status}\n";
    }
}

// Test 4: Check published products only
echo "\n5. Published products only:\n";
$published_products = $wpdb->get_results(
    "SELECT COUNT(*) as count 
     FROM {$wpdb->posts} 
     WHERE post_type = 'product' 
     AND post_status = 'publish'"
);
echo "   Total published products: " . $published_products[0]->count . "\n";

// Test 5: Check for any errors
echo "\n6. PHP Error Log (last errors):\n";
$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    $lines = file($error_log);
    $recent_lines = array_slice($lines, -10);
    foreach ($recent_lines as $line) {
        if (strpos($line, 'CBM') !== false) {
            echo "   " . $line;
        }
    }
}

echo "</pre>";
?>