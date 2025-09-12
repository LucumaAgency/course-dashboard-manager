<?php
/**
 * Test file to verify if changes are being reflected
 * Created at: 2024-12-13
 */

// Load WordPress
require_once('../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied');
}

echo '<h1>Cache Test - Version: ' . date('Y-m-d H:i:s') . '</h1>';
echo '<p>If this timestamp doesn\'t change on refresh, you have a caching problem.</p>';

echo '<h2>Products Test:</h2>';
echo '<pre>';

// Check products
global $wpdb;
$products = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' LIMIT 10");
echo "Products found: " . count($products) . "\n\n";

foreach ($products as $product) {
    echo "ID: {$product->ID} - {$product->post_title}\n";
}

echo '</pre>';

echo '<h2>Plugin Files:</h2>';
echo '<pre>';
$plugin_dir = dirname(__FILE__);
echo "Plugin directory: $plugin_dir\n";
echo "tables-page.php last modified: " . date('Y-m-d H:i:s', filemtime($plugin_dir . '/admin/tables-page.php')) . "\n";
echo "dashboard.php last modified: " . date('Y-m-d H:i:s', filemtime($plugin_dir . '/admin/dashboard.php')) . "\n";
echo '</pre>';

echo '<p><a href="?' . time() . '">Refresh with new timestamp</a></p>';
?>