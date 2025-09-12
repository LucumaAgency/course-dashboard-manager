<?php
/**
 * Course Dashboard Manager - Admin Dashboard
 * 
 * Handles the admin dashboard interface for managing course groups and tables
 */

namespace CourseBoxManager\Admin;

// Add admin menu for dashboard
add_action('admin_menu', __NAMESPACE__ . '\\course_box_manager_menu', 15);

/**
 * Register admin menu items
 */
function course_box_manager_menu() {
    // Main menu now redirects to Tables view
    add_menu_page(
        'Course Tables',
        'Course Tables',
        'edit_posts',
        'course-box-tables',
        __NAMESPACE__ . '\\course_box_tables_page',
        'dashicons-list-view',
        20
    );
    
    // Add submenu for Tables view (same as main)
    add_submenu_page(
        'course-box-tables',
        'Course Tables',
        'Tables',
        'edit_posts',
        'course-box-tables',
        __NAMESPACE__ . '\\course_box_tables_page'
    );
}

/**
 * Add diagnostic admin notice to verify STM courses
 */
add_action('admin_notices', __NAMESPACE__ . '\\cbm_stm_diagnostic_notice');
function cbm_stm_diagnostic_notice() {
    // Only show on course tables page
    if (!isset($_GET['page']) || $_GET['page'] !== 'course-box-tables') {
        return;
    }
    
    // Check for cache clear request
    if (isset($_GET['cbm_clear_cache']) && $_GET['cbm_clear_cache'] === '1') {
        // Clear WordPress object cache
        wp_cache_flush();
        
        // Clear transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_%'");
        
        echo '<div class="notice notice-success is-dismissible"><p>Cache cleared successfully! Plugin version: ' . CBM_VERSION . '</p></div>';
    }
    
    // STM diagnostic
    $stm_post_types = ['stm-courses', 'stm_lms_courses', 'stm-course', 'stm_course'];
    $found_type = null;
    $course_count = 0;
    
    foreach ($stm_post_types as $type) {
        $posts = get_posts(['post_type' => $type, 'posts_per_page' => -1, 'post_status' => 'publish']);
        if (!empty($posts)) {
            $found_type = $type;
            $course_count = count($posts);
            break;
        }
    }
    
    if ($found_type) {
        echo '<div class="notice notice-info"><p><strong>STM LMS Diagnostic:</strong> Found ' . $course_count . ' courses with post type: ' . $found_type . ' | Plugin Version: ' . CBM_VERSION . ' | <a href="?page=course-box-tables&cbm_clear_cache=1">Clear Cache</a></p></div>';
    } else {
        echo '<div class="notice notice-warning"><p><strong>STM LMS Diagnostic:</strong> No STM courses found. Checked post types: ' . implode(', ', $stm_post_types) . ' | Plugin Version: ' . CBM_VERSION . ' | <a href="?page=course-box-tables&cbm_clear_cache=1">Clear Cache</a></p></div>';
    }
}

/**
 * Include the main tables page content
 */
function course_box_tables_page() {
    // Handle cache clearing
    if (isset($_GET['cbm_clear_cache']) && $_GET['cbm_clear_cache'] == '1') {
        // Clear any transients
        delete_transient('cbm_products_cache');
        delete_transient('cbm_courses_cache');
        
        // Clear object cache
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Add version to force reload
        wp_redirect(admin_url('admin.php?page=course-box-tables&cache_cleared=1&v=' . time()));
        exit;
    }
    
    if (isset($_GET['cache_cleared'])) {
        echo '<div class="notice notice-success"><p>✅ Cache cleared successfully! Page reloaded with version ' . (isset($_GET['v']) ? $_GET['v'] : 'unknown') . '</p></div>';
    }
    
    // Add a clear cache button
    echo '<div style="margin: 10px 0;">';
    echo '<a href="' . admin_url('admin.php?page=course-box-tables&cbm_clear_cache=1') . '" class="button button-secondary">🔄 Clear Cache & Reload</a>';
    echo ' <small>Use this if products are not showing in dropdowns</small>';
    echo '</div>';
    
    // This will be moved here from the main file
    require_once CBM_PLUGIN_DIR . 'admin/tables-page.php';
}