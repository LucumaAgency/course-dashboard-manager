<?php
// This file bypasses all caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Find WordPress
$wp_path = '';
for ($i = 0; $i < 10; $i++) {
    if (file_exists($wp_path . 'wp-load.php')) {
        require_once($wp_path . 'wp-load.php');
        break;
    }
    $wp_path .= '../';
}

if (!defined('ABSPATH')) {
    die('WordPress not loaded');
}

// Check permission
if (!current_user_can('manage_options')) {
    die('Access denied - You must be admin');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Products NOW</title>
    <meta http-equiv="cache-control" content="no-cache">
    <meta http-equiv="expires" content="0">
    <meta http-equiv="pragma" content="no-cache">
    <style>
        body { font-family: monospace; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        pre { background: #f0f0f0; padding: 10px; }
    </style>
</head>
<body>
    <h1 style="background: yellow; padding: 10px;">Product Debug - <?php echo date('Y-m-d H:i:s'); ?></h1>
    
    <h2>1. WooCommerce Status:</h2>
    <pre><?php
    echo "WooCommerce class exists: " . (class_exists('WooCommerce') ? '✅ YES' : '❌ NO') . "\n";
    echo "wc_get_products exists: " . (function_exists('wc_get_products') ? '✅ YES' : '❌ NO') . "\n";
    ?></pre>
    
    <h2>2. Products in Database:</h2>
    <pre><?php
    global $wpdb;
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'");
    echo "Total published products: " . $count . "\n\n";
    
    if ($count > 0) {
        echo "First 10 products:\n";
        $products = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' LIMIT 10");
        foreach ($products as $product) {
            echo "  ID: {$product->ID} - {$product->post_title}\n";
        }
    } else {
        echo "❌ NO PRODUCTS FOUND IN DATABASE!\n";
    }
    ?></pre>
    
    <h2>3. Test wc_get_products:</h2>
    <pre><?php
    if (function_exists('wc_get_products')) {
        try {
            $wc_products = wc_get_products(['limit' => 5, 'status' => 'publish']);
            echo "✅ wc_get_products returned: " . count($wc_products) . " products\n";
            foreach ($wc_products as $p) {
                echo "  - ID " . $p->get_id() . ": " . $p->get_name() . " (Price: " . $p->get_price() . ")\n";
            }
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ wc_get_products function not available\n";
    }
    ?></pre>
    
    <h2>4. Build Products Array (like tables-page.php):</h2>
    <pre><?php
    $all_products = [];
    if (function_exists('wc_get_products')) {
        try {
            $products = wc_get_products(['limit' => -1, 'orderby' => 'title', 'order' => 'ASC', 'status' => 'publish']);
            foreach ($products as $product) {
                $all_products[$product->get_id()] = [
                    'name' => $product->get_name(),
                    'regular_price' => $product->get_regular_price(),
                    'sale_price' => $product->get_sale_price()
                ];
            }
            echo "✅ Built array with " . count($all_products) . " products\n";
            echo "Sample: " . json_encode(array_slice($all_products, 0, 2, true), JSON_PRETTY_PRINT);
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage();
        }
    }
    ?></pre>
    
    <p><a href="?refresh=<?php echo time(); ?>" style="background: blue; color: white; padding: 10px; text-decoration: none;">🔄 Refresh</a></p>
</body>
</html>