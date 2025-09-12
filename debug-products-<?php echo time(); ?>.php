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
    die('Access denied');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Products</title>
    <meta http-equiv="cache-control" content="no-cache">
    <meta http-equiv="expires" content="0">
    <meta http-equiv="pragma" content="no-cache">
</head>
<body>
    <h1>Product Debug - <?php echo date('Y-m-d H:i:s'); ?></h1>
    
    <h2>1. WooCommerce Status:</h2>
    <pre><?php
    echo "WooCommerce class exists: " . (class_exists('WooCommerce') ? 'YES' : 'NO') . "\n";
    echo "wc_get_products exists: " . (function_exists('wc_get_products') ? 'YES' : 'NO') . "\n";
    ?></pre>
    
    <h2>2. Products in Database:</h2>
    <pre><?php
    global $wpdb;
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'");
    echo "Total published products: " . $count . "\n\n";
    
    $products = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' LIMIT 10");
    foreach ($products as $product) {
        echo "ID: {$product->ID} - {$product->post_title}\n";
    }
    ?></pre>
    
    <h2>3. Test wc_get_products:</h2>
    <pre><?php
    if (function_exists('wc_get_products')) {
        $wc_products = wc_get_products(['limit' => 5]);
        echo "wc_get_products returned: " . count($wc_products) . " products\n";
        foreach ($wc_products as $p) {
            echo "- " . $p->get_id() . ": " . $p->get_name() . "\n";
        }
    } else {
        echo "wc_get_products not available\n";
    }
    ?></pre>
    
    <h2>4. JavaScript Test:</h2>
    <script>
    var testProducts = <?php 
        $js_products = [];
        if (function_exists('wc_get_products')) {
            $test_prods = wc_get_products(['limit' => 3]);
            foreach ($test_prods as $tp) {
                $js_products[$tp->get_id()] = $tp->get_name();
            }
        }
        echo json_encode($js_products);
    ?>;
    console.log('Products in JavaScript:', testProducts);
    document.write('<pre>Products in JS: ' + JSON.stringify(testProducts, null, 2) + '</pre>');
    </script>
    
    <p><a href="?refresh=<?php echo time(); ?>">Refresh</a></p>
</body>
</html>