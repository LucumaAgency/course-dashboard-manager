<?php
/*
 * Plugin Name: Course Box Manager
 * Description: A comprehensive plugin to manage and display selectable boxes for course post types with dashboard control, countdowns, start date selection, and WooCommerce integration.
 * Version: 1.8.9
 * Author: Carlos Murillo
 * Author URI: https://lucumaagency.com/
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('CBM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CBM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CBM_VERSION', '1.8.9');

// Autoloader for classes
spl_autoload_register(function ($class) {
    $prefix = 'CourseBoxManager\\';
    $base_dir = CBM_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Load helper functions first (no namespace)
require_once CBM_PLUGIN_DIR . 'includes/helpers.php';

// Load core includes
require_once CBM_PLUGIN_DIR . 'includes/post-types.php';
require_once CBM_PLUGIN_DIR . 'includes/EnrollmentSync.php';

// Load admin functionality
if (is_admin()) {
    require_once CBM_PLUGIN_DIR . 'admin/dashboard.php';
    require_once CBM_PLUGIN_DIR . 'ajax/handlers.php';
}

// Load frontend functionality
if (!is_admin()) {
    require_once CBM_PLUGIN_DIR . 'frontend/shortcode.php';
}

// Initialize cart handler for AJAX requests
add_action('init', function() {
    new CourseBoxManager\CartHandler();
});

// Activation hook
register_activation_hook(__FILE__, 'cbm_activate');
function cbm_activate() {
    // Register post types
    CourseBoxManager\register_course_post_type();
    CourseBoxManager\register_course_group_taxonomy();
    CourseBoxManager\register_instructor_cpt();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'cbm_deactivate');
function cbm_deactivate() {
    flush_rewrite_rules();
}