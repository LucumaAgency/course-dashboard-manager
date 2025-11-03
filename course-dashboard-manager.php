<?php
/*
 * Plugin Name: Course Box Manager
 * Description: A comprehensive plugin to manage and display selectable boxes for course post types with dashboard control, countdowns, start date selection, and WooCommerce integration.
 * Version: 1.9.46
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
define('CBM_VERSION', '1.9.46');

// Include constants and helpers
require_once CBM_PLUGIN_DIR . 'includes/constants.php';
require_once CBM_PLUGIN_DIR . 'includes/helpers-global.php';

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
        require_once $file;
    }
});

// Include admin enqueue scripts handler
require_once CBM_PLUGIN_DIR . 'admin/enqueue-scripts.php';

// Initialize the seats remaining functionality
add_action('init', function() {
    new CourseBoxManager\SeatsRemaining();

    // Initialize enrollment sync for STM LMS integration
    if (class_exists('WooCommerce')) {
        new CourseBoxManager\EnrollmentSync();
    }

    // Initialize Cart Handler for AJAX add to cart
    if (class_exists('WooCommerce')) {
        new CourseBoxManager\CartHandler();
    }

    // Load frontend shortcode file which includes pre-rendering for popup
    if (file_exists(CBM_PLUGIN_DIR . 'frontend/shortcode.php')) {
        require_once CBM_PLUGIN_DIR . 'frontend/shortcode.php';
    }

    // Load mobile price shortcode
    if (file_exists(CBM_PLUGIN_DIR . 'includes/shortcodes/mobile-price.php')) {
        require_once CBM_PLUGIN_DIR . 'includes/shortcodes/mobile-price.php';
    }
});

// Register course_group taxonomy
add_action('init', 'register_course_group_taxonomy');
function register_course_group_taxonomy() {
    register_taxonomy('course_group', ['course', 'product'], [
        'labels' => [
            'name' => __('Course Groups'),
            'singular_name' => __('Course Group'),
        ],
        'hierarchical' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_admin_column' => true,
        'rewrite' => ['slug' => 'course-group'],
    ]);
}

// Register instructor CPT
add_action('init', 'register_instructor_cpt');
function register_instructor_cpt() {
    register_post_type('instructor', [
        'labels' => [
            'name' => __('Instructors'),
            'singular_name' => __('Instructor'),
        ],
        'public' => true,
        'supports' => ['title', 'editor', 'custom-fields'],
    ]);
}

// Enable FunnelKit Cart for course post type
add_filter('fkcart_disabled_post_types', function ($post_types) {
    $post_types = array_filter($post_types, function ($i) {
        return $i !== 'course';
    });
    return $post_types;
});

// Add admin menu for dashboard
add_action('admin_menu', 'course_box_manager_menu', 15); // Priority 15 to ensure proper loading

// Add diagnostic admin notice to verify STM courses
add_action('admin_notices', 'cbm_stm_diagnostic_notice');
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
function course_box_manager_menu() {
    // Main menu now redirects to Tables view
    add_menu_page(
        'Course Tables',
        'Course Tables',
        'edit_posts', // Instructors (with edit_posts capability) can view
        'course-box-tables',
        'course_box_tables_page',
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
        'course_box_tables_page'
    );
}


// Handle course group creation and deletion
add_action('admin_init', 'handle_course_group_actions', 20); // Priority 20 to ensure ACF is loaded
function handle_course_group_actions() {
    // Only process on admin pages
    if (!is_admin()) {
        return;
    }
    
    // Only process on our specific page
    if (!isset($_GET['page']) || $_GET['page'] !== 'course-box-tables') {
        return;
    }
    
    // Handle group deletion (POST request)
    if (isset($_POST['action']) && $_POST['action'] === 'delete_group' && isset($_POST['group_id']) && isset($_POST['_wpnonce'])) {
        $group_id = intval($_POST['group_id']);

        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'delete_group_' . $group_id)) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_die('You do not have permission to delete course groups');
        }
        
        // Get all courses in the group to unassign them
        $courses = get_posts([
            'post_type' => 'course',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => [
                [
                    'taxonomy' => 'course_group',
                    'field' => 'term_id',
                    'terms' => $group_id,
                ],
            ],
        ]);
        
        // If group has courses, unassign them first
        if (!empty($courses)) {
            foreach ($courses as $course_id) {
                wp_remove_object_terms($course_id, $group_id, 'course_group');
            }
        }
        
        // Delete the term
        $result = wp_delete_term($group_id, 'course_group');
        
        if (is_wp_error($result)) {
            wp_die('Error deleting group: ' . $result->get_error_message());
        }
        
        // Redirect back to tables page
        wp_redirect(admin_url('admin.php?page=course-box-tables&group_deleted=1'));
        exit;
    }
    
    // Handle group creation
    if (isset($_POST['action']) && $_POST['action'] === 'create_course_group') {
        // Verify nonce
        if (!isset($_POST['course_group_nonce']) || !wp_verify_nonce($_POST['course_group_nonce'], 'create_course_group')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_die('You do not have permission to create course groups');
        }
        
        // Get and sanitize input
        $group_name = sanitize_text_field($_POST['group_name']);
        $group_description = sanitize_textarea_field($_POST['group_description'] ?? '');
        
        if (empty($group_name)) {
            wp_die('Group name is required');
        }
        
        // Create the term
        $result = wp_insert_term(
            $group_name,
            'course_group',
            [
                'description' => $group_description,
            ]
        );
        
        if (is_wp_error($result)) {
            wp_die('Error creating group: ' . $result->get_error_message());
        }
        
        // Redirect back to tables page
        wp_redirect(admin_url('admin.php?page=course-box-tables&group_created=1'));
        exit;
    }
}

// Tables page content
function course_box_tables_page() {
    // Show success messages
    if (isset($_GET['group_created'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Course group created successfully!</p></div>';
    }
    if (isset($_GET['group_deleted'])) {
        echo '<div class="notice notice-success is-dismissible"><p>Course group deleted successfully!</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Course Tables</h1>
        
        <?php if (!isset($_GET['group_id'])) : ?>
            <!-- Groups List View -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Course Groups</h2>
                <button id="add-new-group" class="button button-primary">Add New Group</button>
            </div>
            
            <!-- Add New Group Form (hidden by default) -->
            <div id="new-group-form" style="display: none; margin-bottom: 20px; padding: 15px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 5px;">
                <h3>Create New Course Group</h3>
                <form method="post" action="">
                    <?php wp_nonce_field('create_course_group', 'course_group_nonce'); ?>
                    <input type="hidden" name="action" value="create_course_group">
                    <table class="form-table">
                        <tr>
                            <th><label for="group_name">Group Name</label></th>
                            <td><input type="text" id="group_name" name="group_name" required class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th><label for="group_description">Description (optional)</label></th>
                            <td><textarea id="group_description" name="group_description" class="regular-text" rows="3"></textarea></td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="Create Group" />
                        <button type="button" id="cancel-new-group" class="button">Cancel</button>
                    </p>
                </form>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Group Name</th>
                        <th>Number of Courses</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $groups = get_terms(['taxonomy' => 'course_group', 'hide_empty' => false]);
                    foreach ($groups as $group) :
                        $courses_in_group = get_posts([
                            'post_type' => 'course',
                            'posts_per_page' => -1,
                            'fields' => 'ids',
                            'tax_query' => [
                                [
                                    'taxonomy' => 'course_group',
                                    'field' => 'term_id',
                                    'terms' => $group->term_id,
                                ],
                            ],
                        ]);
                    ?>
                        <tr>
                            <td>
                                <a href="?page=course-box-tables&group_id=<?php echo esc_attr($group->term_id); ?>">
                                    <?php echo esc_html($group->name); ?>
                                </a>
                            </td>
                            <td><?php echo count($courses_in_group); ?></td>
                            <td>
                                <a href="?page=course-box-tables&group_id=<?php echo esc_attr($group->term_id); ?>" class="button">View Courses</a>
                                <?php
                                $delete_message = count($courses_in_group) > 0
                                    ? 'This group contains ' . count($courses_in_group) . ' course(s). Deleting the group will unassign all courses from it. Are you sure?'
                                    : 'Are you sure you want to delete this group?';
                                ?>
                                <form method="post" action="" style="display: inline;" onsubmit="return confirm('<?php echo esc_js($delete_message); ?>');">
                                    <?php wp_nonce_field('delete_group_' . $group->term_id, '_wpnonce'); ?>
                                    <input type="hidden" name="action" value="delete_group">
                                    <input type="hidden" name="group_id" value="<?php echo esc_attr($group->term_id); ?>">
                                    <button type="submit" class="button button-link-delete">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        
        <?php else : ?>
            <!-- Group Detail View with Courses Table -->
            <?php
            $group_id = intval($_GET['group_id']);
            $group = get_term($group_id, 'course_group');
            $courses = get_posts([
                'post_type' => 'course',
                'posts_per_page' => -1,
                'tax_query' => [
                    [
                        'taxonomy' => 'course_group',
                        'field' => 'term_id',
                        'terms' => $group_id,
                    ],
                ],
            ]);
            
            // Get first course settings for group defaults
            $first_course_id = !empty($courses) ? $courses[0]->ID : 0;
            $default_box_state = $first_course_id ? get_post_meta($first_course_id, 'box_state', true) : 'enroll-course';
            $default_instructors = $first_course_id ? get_post_meta($first_course_id, 'course_instructors', true) : [];
            $default_instructor = !empty($default_instructors) ? $default_instructors[0] : '';
            
            // Get selling page for the group
            $selling_page_id = 0;
            $group_courses = get_posts([
                'post_type' => 'course',
                'posts_per_page' => 1,
                'meta_key' => 'is_selling_page',
                'meta_value' => '1',
                'tax_query' => [
                    [
                        'taxonomy' => 'course_group',
                        'field' => 'term_id',
                        'terms' => $group_id,
                    ],
                ],
            ]);
            if (!empty($group_courses)) {
                $selling_page_id = $group_courses[0]->ID;
            }
            ?>
            <h2>Group: <?php echo esc_html($group->name); ?></h2>
            <a href="?page=course-box-tables" class="button">← Back to Groups</a>
            
            <!-- Group Settings -->
            <div style="margin: 20px 0; padding: 15px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 5px;">
                <h3 style="margin-top: 0;">Group Settings</h3>
                <div style="display: flex; gap: 25px; align-items: center; flex-wrap: wrap;">
                    <div>
                        <label for="group-box-state"><strong>Box State:</strong></label>
                        <select id="group-box-state" style="margin-left: 10px; padding: 5px; min-width: 150px;">
                            <option value="enroll-course" <?php selected($default_box_state, 'enroll-course'); ?>>Enroll Course</option>
                            <option value="buy-course" <?php selected($default_box_state, 'buy-course'); ?>>Buy Course</option>
                            <option value="enroll-buy" <?php selected($default_box_state, 'enroll-buy'); ?>>Buy Course + Enroll Course</option>
                            <option value="countdown" <?php selected($default_box_state, 'countdown'); ?>>Countdown Box</option>
                            <!-- Waitlist option hidden but code preserved -->
                            <!-- <option value="waitlist" <?php selected($default_box_state, 'waitlist'); ?>>Waitlist</option> -->
                            <option value="soldout" <?php selected($default_box_state, 'soldout'); ?>>Sold Out</option>
                        </select>
                    </div>
                    <div>
                        <label for="group-instructor"><strong>Instructor:</strong></label>
                        <select id="group-instructor" style="margin-left: 10px; padding: 5px; min-width: 150px;">
                            <option value="">None</option>
                            <?php
                            $all_instructors = get_posts(['post_type' => 'instructor', 'posts_per_page' => -1]);
                            foreach ($all_instructors as $instructor) {
                                echo '<option value="' . esc_attr($instructor->ID) . '"' . selected($default_instructor, $instructor->ID, false) . '>' . 
                                     esc_html($instructor->post_title) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label for="group-selling-page"><strong>Selling Page:</strong></label>
                        <select id="group-selling-page" style="margin-left: 10px; padding: 5px; min-width: 200px;">
                            <option value="">None</option>
                            <?php
                            // Get all courses for selling page selection
                            $all_courses = get_posts(['post_type' => 'course', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
                            foreach ($all_courses as $course) {
                                echo '<option value="' . esc_attr($course->ID) . '"' . selected($selling_page_id, $course->ID, false) . '>' . 
                                     esc_html($course->post_title) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button id="save-group-settings" class="button button-primary">Save Group Settings</button>
                        <button id="apply-group-settings" class="button button-secondary">Apply to All Courses</button>
                    </div>
                </div>
            </div>
            
            <!-- Courses Table -->
            <div id="table-container">
                <!-- Buy Course Table (only shown for enroll-buy state) -->
                <div id="buy-table-container" style="display: none;">
                    <h3>Buy Course Configuration</h3>
                    <table class="wp-list-table widefat fixed striped" id="buy-courses-table" style="margin-top: 10px;">
                        <thead id="buy-table-header">
                            <!-- Dynamic header for buy course -->
                        </thead>
                        <tbody id="buy-table-body">
                            <!-- Dynamic content for buy course -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Enroll Course Table -->
                <div id="enroll-table-container">
                    <h3 id="enroll-table-title" style="display: none;">Enroll Course Configuration</h3>
                    
                    <!-- STM Course Selection for Enroll (applies to all dates) -->
                    <div id="stm-course-selector" style="display: none; margin-bottom: 15px; padding: 10px; background: #f0f8ff; border: 1px solid #0073aa; border-radius: 4px;">
                        <label style="font-weight: bold; margin-right: 10px;">
                            STM Course (applies to all dates):
                        </label>
                        <select id="global-stm-course" style="min-width: 300px;">
                            <!-- Will be populated by JavaScript -->
                        </select>
                        <button id="save-stm-course" class="button button-secondary" style="margin-left: 10px;">Save STM Course</button>
                        <span id="stm-save-status" style="margin-left: 10px;"></span>
                    </div>
                    
                    <button id="add-new-row" class="button button-primary" style="margin-bottom: 10px;">+ Add Course/Date</button>
                    <table class="wp-list-table widefat fixed striped" id="courses-table" style="margin-top: 10px;">
                        <thead id="table-header">
                            <!-- Dynamic header based on box state -->
                        </thead>
                        <tbody id="table-body">
                            <!-- Dynamic content based on box state -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- JavaScript data now loaded via wp_localize_script in admin/enqueue-scripts.php -->
        <style>
            .soldout-row {
                background-color: #ffebee !important;
            }
            .low-stock-row {
                background-color: #fff3e0 !important;
            }
            .medium-stock-row {
                background-color: #fffde7 !important;
            }
            .no-dates-row {
                background-color: #f5f5f5 !important;
            }
            #courses-table th {
                font-weight: bold;
                background-color: #f0f0f0;
            }
            #instructor-filter {
                padding: 5px 10px;
                font-size: 14px;
            }
            .editable-row input, .editable-row select {
                border: 1px solid #ddd;
                border-radius: 3px;
                font-size: 13px;
            }
            .editable-row input:focus, .editable-row select:focus {
                border-color: #5b9dd9;
                box-shadow: 0 0 2px rgba(30,140,190,.8);
                outline: none;
            }
            .editable-row.has-changes {
                background-color: #fff8dc !important;
            }
            .save-status.success {
                color: #46b450;
                font-weight: bold;
            }
            .save-status.error {
                color: #d54e21;
                font-weight: bold;
            }
            .save-status.saving {
                color: #f0ad4e;
                font-style: italic;
            }
            /* Modal styles for tables view */
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.4);
            }
            .modal-content {
                background-color: #fff;
                margin: 15% auto;
                padding: 20px;
                border: 1px solid #888;
                width: 80%;
                max-width: 500px;
                border-radius: 5px;
            }
            .modal-close {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }
            .modal-close:hover,
            .modal-close:focus {
                color: #000;
            }
            .modal-content h2 {
                margin-top: 0;
            }
            .modal-content label {
                display: block;
                margin: 15px 0 5px;
            }
            .modal-content select {
                width: 100%;
                padding: 8px;
                margin-bottom: 15px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
        </style>
        
        </script>
        
        <!-- Modal for Adding Course in Tables View -->
        <div id="add-course-modal" class="modal" style="display:none;">
            <div class="modal-content">
                <span class="modal-close">×</span>
                <h2>Add Course to Group</h2>
                <label>Select Course:</label>
                <select id="course-select" style="width: 100%; margin-bottom: 15px;">
                    <option value="">Select a course...</option>
                    <?php
                    // Get all available courses
                    $all_courses_modal = get_posts([
                        'post_type' => 'course',
                        'posts_per_page' => -1,
                        'orderby' => 'title',
                        'order' => 'ASC'
                    ]);
                    
                    // Get courses already in the group
                    $existing_course_ids = [];
                    foreach ($courses as $course) {
                        $existing_course_ids[] = $course->ID;
                    }
                    
                    // Display only courses not already in the group
                    foreach ($all_courses_modal as $course) {
                        if (!in_array($course->ID, $existing_course_ids)) {
                            echo '<option value="' . esc_attr($course->ID) . '">' . esc_html($course->post_title) . '</option>';
                        }
                    }
                    ?>
                </select>
                <button class="button button-primary" id="add-course-to-group">Add to Group</button>
            </div>
        </div>
        
        <script>
            // Modal functionality for tables view
            document.addEventListener('DOMContentLoaded', function() {
                const modal = document.getElementById('add-course-modal');
                const closeBtn = modal?.querySelector('.modal-close');
                const addBtn = document.getElementById('add-course-to-group');
                
                // Close modal when clicking X
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        modal.style.display = 'none';
                    });
                }
                
                // Close modal when clicking outside
                window.addEventListener('click', function(event) {
                    if (event.target === modal) {
                        modal.style.display = 'none';
                    }
                });
                
                // Add course to group
                if (addBtn) {
                    addBtn.addEventListener('click', function() {
                        const courseId = document.getElementById('course-select').value;
                        const groupId = <?php echo intval($group_id); ?>;
                        
                        if (!courseId) {
                            alert('Please select a course');
                            return;
                        }
                        
                        // Send AJAX request to add course to group
                        
                        fetch(ajaxurl + '?action=assign_course_to_group', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'course_id=' + courseId + '&group_id=' + groupId + 
                                  '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(result => {
                            if (result.success) {
                                // Reload the page to show the new course
                                location.reload();
                            } else {
                                alert('Error: ' + (result.data || 'Failed to add course'));
                            }
                        })
                        .catch(error => {
                            console.error('[CBM Debug] Fetch error:', error);
                            alert('Error adding course to group. Please check the console for details.');
                        });
                    });
                }
            });
        </script>
        
        <?php endif; // End of group detail view ?>
        
        <!-- JavaScript for Add New Group form (only on groups list view) -->
        <?php if (!isset($_GET['group_id'])) : ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const addButton = document.getElementById('add-new-group');
                const formContainer = document.getElementById('new-group-form');
                const cancelButton = document.getElementById('cancel-new-group');
                
                if (addButton) {
                    addButton.addEventListener('click', function() {
                        formContainer.style.display = 'block';
                        addButton.style.display = 'none';
                    });
                }
                
                if (cancelButton) {
                    cancelButton.addEventListener('click', function() {
                        formContainer.style.display = 'none';
                        addButton.style.display = 'inline-block';
                        document.getElementById('group_name').value = '';
                        document.getElementById('group_description').value = '';
                    });
                }
            });
        </script>
        <?php endif; ?>
    </div>
    <?php
}

// Dashboard page content
function course_box_manager_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <input type="text" id="course-search" placeholder="Search...">
        <?php if (!isset($_GET['course_id']) && !isset($_GET['group_id'])) : ?>
            <!-- Main View: Course Groups Table -->
            <?php
            $groups = get_terms(['taxonomy' => 'course_group', 'hide_empty' => false]);
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Course Group</th>
                        <th>Number of Courses</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $group) :
                        $courses_in_group = get_posts([
                            'post_type' => 'course',
                            'posts_per_page' => -1,
                            'fields' => 'ids',
                            'tax_query' => [
                                [
                                    'taxonomy' => 'course_group',
                                    'field' => 'term_id',
                                    'terms' => $group->term_id,
                                ],
                            ],
                        ]);
                    ?>
                        <tr>
                            <td><a href="?page=course-box-manager&group_id=<?php echo esc_attr($group->term_id); ?>"><?php echo esc_html($group->name); ?></a></td>
                            <td><?php echo count($courses_in_group); ?></td>
                            <td>
                                <button class="button view-courses" data-group-id="<?php echo esc_attr($group->term_id); ?>">View Courses</button>
                                <button class="button delete-group" data-group-id="<?php echo esc_attr($group->term_id); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button class="button button-primary add-course-group" style="margin-top: 10px;">Add Course Group</button>
            <a href="<?php echo admin_url('edit.php?post_type=course'); ?>" class="button" style="margin-top: 10px; margin-left: 10px;">View All Courses</a>
        <?php elseif (isset($_GET['group_id']) && !isset($_GET['course_id'])) : ?>
            <!-- Group View: Courses in Group -->
            <?php
            $group_id = intval($_GET['group_id']);
            $group = get_term($group_id, 'course_group');
            $courses = get_posts([
                'post_type' => 'course',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'tax_query' => [
                    [
                        'taxonomy' => 'course_group',
                        'field' => 'term_id',
                        'terms' => $group_id,
                    ],
                ],
            ]);
            ?>
            <h2>Course Group: <?php echo esc_html($group->name); ?></h2>
            <a href="?page=course-box-manager" class="button">Back to Groups</a>
            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th style="width: 20%;">Course</th>
                        <th style="width: 15%;">Instructors</th>
                        <th style="width: 15%;">STM Course</th>
                        <th style="width: 12%;">Box State</th>
                        <th style="width: 23%;">Dates (Stock)</th>
                        <th style="width: 15%;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $course_id) :
                        $title = get_the_title($course_id);
                        $instructors = get_post_meta($course_id, 'course_instructors', true) ?: [];
                        $instructor_names = array_map(function($id) { return get_the_title($id); }, $instructors);
                        $box_state = get_post_meta($course_id, 'box_state', true) ?: 'enroll-course';
                        $course_stock = cbm_get_field('course_stock', $course_id) ?: 0;
                        $dates = cbm_get_field('course_dates', $course_id) ?: [];
                        $product_id = get_post_meta($course_id, 'linked_product_id', true);
                        
                        // Calculate seats availability
                        $seats_info = [];
                        $total_seats = 0;
                        $total_available = 0;
                        $dates_with_info = [];
                        
                        if (!empty($dates)) {
                            foreach ($dates as $idx => $date) {
                                if (isset($date['date'])) {
                                    $stock = isset($date['stock']) ? intval($date['stock']) : $course_stock;
                                    $sold = $product_id ? calculate_seats_sold($product_id, $date['date']) : 0;
                                    $available = max(0, $stock - $sold);
                                    $total_seats += $stock;
                                    $total_available += $available;
                                    $dates_with_info[] = [
                                        'date' => $date['date'],
                                        'stock' => $stock,
                                        'sold' => $sold,
                                        'available' => $available,
                                        'index' => $idx
                                    ];
                                }
                            }
                            $seats_display = $total_available . '/' . $total_seats;
                        } elseif ($product_id) {
                            // Single stock for all dates
                            $sold = calculate_seats_sold($product_id);
                            $available = max(0, $course_stock - $sold);
                            $seats_display = $available . '/' . $course_stock;
                        } else {
                            $seats_display = '-';
                        }
                    ?>
                        <tr data-course-id="<?php echo esc_attr($course_id); ?>">
                            <td><a href="?page=course-box-manager&course_id=<?php echo esc_attr($course_id); ?>&group_id=<?php echo esc_attr($group_id); ?>"><?php echo esc_html($title); ?></a></td>
                            <td><?php echo esc_html(implode(', ', $instructor_names)); ?></td>
                            <td>
                                <?php
                                // Get linked STM Course
                                $stm_course_id = get_post_meta($course_id, 'related_stm_course_id', true);
                                if ($stm_course_id) {
                                    $stm_course = get_post($stm_course_id);
                                    if ($stm_course) {
                                        // Check if it's a valid STM course post type
                                        $possible_stm_types = ['stm-courses', 'stm_lms_courses', 'stm-course', 'stm_course'];
                                        if (in_array($stm_course->post_type, $possible_stm_types)) {
                                            echo '<a href="' . get_edit_post_link($stm_course_id) . '" target="_blank" style="color: #0073aa; text-decoration: none;">' . 
                                                 esc_html($stm_course->post_title) . 
                                                 '</a> <span style="color: #666; font-size: 11px;">(#' . $stm_course_id . ')</span>';
                                        } else {
                                            echo '<span style="color: #d54e21;">Invalid Course Type</span>';
                                        }
                                    } else {
                                        echo '<span style="color: #d54e21;">Course Not Found</span>';
                                    }
                                } else {
                                    // Check if any STM post type exists
                                    $stm_exists = false;
                                    foreach (['stm-courses', 'stm_lms_courses', 'stm-course', 'stm_course'] as $type) {
                                        if (post_type_exists($type)) {
                                            $stm_exists = true;
                                            break;
                                        }
                                    }
                                    
                                    if ($stm_exists) {
                                        echo '<span style="color: #f0ad4e; font-style: italic;">⚠ Not linked</span>';
                                    } else {
                                        echo '<span style="color: #999; font-size: 11px;">STM LMS not active</span>';
                                    }
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html(ucfirst(str_replace('-', ' ', $box_state))); ?></td>
                            <td>
                                <?php if (!empty($dates_with_info)) : ?>
                                    <div class="dates-display">
                                        <?php 
                                        foreach ($dates_with_info as $index => $date_info) {
                                            $stock_color = '#333';
                                            if ($date_info['available'] <= 0) {
                                                $stock_color = '#d54e21'; // Red for sold out
                                            } elseif ($date_info['available'] <= 5) {
                                                $stock_color = '#f0ad4e'; // Yellow for low stock
                                            } else {
                                                $stock_color = '#46b450'; // Green for good availability
                                            }
                                            ?>
                                            <span class="date-item" style="display: inline-block; margin-right: 10px; margin-bottom: 3px; font-size: 11px;">
                                                <span style="color: #333;"><?php echo esc_html($date_info['date']); ?></span>
                                                <span style="color: <?php echo $stock_color; ?>; font-weight: bold;">(<?php echo esc_html($date_info['stock']); ?>)</span>
                                            </span>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                <?php else : ?>
                                    <span style="font-size: 11px; color: #aaa; font-style: italic;">No dates configured</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="button button-primary edit-course-settings" data-course-id="<?php echo esc_attr($course_id); ?>">Edit</button>
                                <button class="button remove-from-group" data-course-id="<?php echo esc_attr($course_id); ?>" data-group-id="<?php echo esc_attr($group_id); ?>">Remove from Group</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button class="button button-primary add-course" data-group-id="<?php echo esc_attr($group_id); ?>" style="margin-top: 10px;">Add Course to Group</button>
        <?php else : ?>
            <!-- Detail View: Course Settings -->
            <?php
            $course_id = intval($_GET['course_id']);
            $title = get_the_title($course_id);
            $instructors = get_post_meta($course_id, 'course_instructors', true) ?: [];
            $box_state = get_post_meta($course_id, 'box_state', true) ?: 'enroll-course';
            $course_stock = cbm_get_field('course_stock', $course_id) ?: 0;
            $dates = cbm_get_field('course_dates', $course_id) ?: [];
            $product_id = get_post_meta($course_id, 'linked_product_id', true);
            $terms = wp_get_post_terms($course_id, 'course_group');
            $group_id = !empty($terms) ? $terms[0]->term_id : 0;
            $group_name = $group_id ? get_term($group_id, 'course_group')->name : 'None';
            $selling_page = get_posts([
                'post_type' => 'course',
                'posts_per_page' => 1,
                'tax_query' => [
                    [
                        'taxonomy' => 'course_group',
                        'field' => 'term_id',
                        'terms' => $group_id,
                    ],
                ],
            ]);
            $selling_page_id = !empty($selling_page) ? $selling_page[0]->ID : 0;
            $from_group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
            ?>
            <h2>Course: <?php echo esc_html($title); ?></h2>
            <?php if ($from_group_id) : ?>
                <a href="?page=course-box-manager&group_id=<?php echo esc_attr($from_group_id); ?>" class="button">Back to Group</a>
            <?php else : ?>
                <a href="?page=course-box-manager" class="button">Back to Groups</a>
            <?php endif; ?>
            <div style="margin-top: 20px;">
                <h3>Course Settings</h3>
                <table class="form-table">
                    <tr>
                        <th><label>Course Group</label></th>
                        <td>
                            <select id="course-group" data-course-id="<?php echo esc_attr($course_id); ?>">
                                <option value="0">None</option>
                                <?php
                                $groups = get_terms(['taxonomy' => 'course_group', 'hide_empty' => false]);
                                foreach ($groups as $group) {
                                    echo '<option value="' . esc_attr($group->term_id) . '"' . ($group_id == $group->term_id ? ' selected' : '') . '>' . esc_html($group->name) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Instructors</label></th>
                        <td>
                            <select class="instructor-select" data-course-id="<?php echo esc_attr($course_id); ?>" multiple>
                                <?php
                                $all_instructors = get_posts(['post_type' => 'instructor', 'posts_per_page' => -1]);
                                foreach ($all_instructors as $instructor) {
                                    echo '<option value="' . esc_attr($instructor->ID) . '"' . (in_array($instructor->ID, $instructors) ? ' selected' : '') . '>' . esc_html($instructor->post_title) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Box State</label></th>
                        <td>
                            <select class="box-state-select" data-course-id="<?php echo esc_attr($course_id); ?>">
                                <option value="enroll-course" <?php echo $box_state === 'enroll-course' ? 'selected' : ''; ?>>Enroll in the Live Course</option>
                                <option value="buy-course" <?php echo $box_state === 'buy-course' ? 'selected' : ''; ?>>Buy This Course</option>
                                <option value="enroll-buy" <?php echo $box_state === 'enroll-buy' ? 'selected' : ''; ?>>Buy Course + Enroll Course</option>
                                <!-- Waitlist option hidden but code preserved -->
                                <!-- <option value="waitlist" <?php echo $box_state === 'waitlist' ? 'selected' : ''; ?>>Waitlist</option> -->
                                <option value="soldout" <?php echo $box_state === 'soldout' ? 'selected' : ''; ?>>Sold Out</option>
                            </select>
                        </td>
                    </tr>
                    <tr id="standard-product-row">
                        <th><label>Associated Product</label></th>
                        <td>
                            <select id="linked-product" data-course-id="<?php echo esc_attr($course_id); ?>">
                                <option value="0">None</option>
                                <?php
                                $linked_product_id = get_post_meta($course_id, 'linked_product_id', true);
                                if (function_exists('wc_get_products')) {
                                    $products = wc_get_products(['limit' => -1, 'orderby' => 'title', 'order' => 'ASC', 'status' => 'publish']);
                                    if (!empty($products)) {
                                        foreach ($products as $product) {
                                            $selected = ($linked_product_id == $product->get_id()) ? ' selected' : '';
                                            echo '<option value="' . esc_attr($product->get_id()) . '"' . $selected . '>' . 
                                                 esc_html($product->get_name()) . ' (#' . $product->get_id() . ')' . '</option>';
                                        }
                                    }
                                } else {
                                    echo '<option disabled>WooCommerce not active</option>';
                                }
                                ?>
                            </select>
                            <p style="font-size: 12px; color: #666; margin-top: 5px;">Select the WooCommerce product associated with this course</p>
                        </td>
                    </tr>
                    
                    <!-- STM LMS Course Selection - Always Visible -->
                    <tr id="stm-course-row" style="background-color: #f0f8ff;">
                        <th><label style="color: #0073aa; font-weight: bold;">STM LMS Course</label></th>
                        <td>
                            <?php
                            // Debug: Log that we're rendering the STM course field
                            error_log('[CBM STM Field] Rendering STM Course selector for course ID: ' . $course_id);
                            ?>
                            <select id="stm-course" class="stm-course-select" data-course-id="<?php echo esc_attr($course_id); ?>" style="width: 100%; max-width: 400px;">
                                <option value="0">None</option>
                                <?php
                                $related_stm_course_id = get_post_meta($course_id, 'related_stm_course_id', true);
                                
                                // Force use stm-courses since we know that's the correct post type
                                $stm_post_type = 'stm-courses';
                                error_log('[CBM STM Field] Using post type: ' . $stm_post_type);
                                
                                // Get all STM Courses
                                $stm_courses = get_posts([
                                    'post_type' => $stm_post_type,
                                    'posts_per_page' => -1,
                                    'orderby' => 'title',
                                    'order' => 'ASC',
                                    'post_status' => 'publish'
                                ]);
                                
                                error_log('[CBM STM Field] Query returned ' . count($stm_courses) . ' STM courses');
                                
                                if (!empty($stm_courses)) {
                                    echo '<option disabled style="background: #f0f0f0;">--- Found ' . count($stm_courses) . ' STM Courses ---</option>';
                                    foreach ($stm_courses as $stm_course) {
                                        $selected = ($related_stm_course_id == $stm_course->ID) ? ' selected' : '';
                                        echo '<option value="' . esc_attr($stm_course->ID) . '"' . $selected . '>' . 
                                             esc_html($stm_course->post_title) . ' (#' . $stm_course->ID . ')' . '</option>';
                                    }
                                } else {
                                    echo '<option disabled>No STM Courses found (checked post type: ' . $stm_post_type . ')</option>';
                                }
                                ?>
                            </select>
                            <p style="font-size: 12px; color: #666; margin-top: 5px;">MasterStudy LMS course to grant access when any product is purchased</p>
                            <p style="font-size: 11px; color: #0073aa; margin-top: 5px; padding: 5px; background: #e7f3ff; border-radius: 3px;">
                                ℹ️ STM Courses detected: <?php echo count($stm_courses); ?> | Plugin v<?php echo CBM_VERSION; ?>
                            </p>
                            <script>
                            </script>
                        </td>
                    </tr>
                    
                    <!-- Additional fields for Buy Course + Enroll Course state -->
                    <tr id="buy-product-row" style="display: none;">
                        <th><label>Buy Course Product</label></th>
                        <td>
                            <select id="buy-product" data-course-id="<?php echo esc_attr($course_id); ?>">
                                <option value="0">None</option>
                                <?php
                                $buy_product_id = get_post_meta($course_id, 'buy_product_id', true);
                                if (function_exists('wc_get_products')) {
                                    $products = wc_get_products(['limit' => -1, 'orderby' => 'title', 'order' => 'ASC', 'status' => 'publish']);
                                    if (!empty($products)) {
                                        foreach ($products as $product) {
                                            $selected = ($buy_product_id == $product->get_id()) ? ' selected' : '';
                                            echo '<option value="' . esc_attr($product->get_id()) . '"' . $selected . '>' . 
                                                 esc_html($product->get_name()) . ' (#' . $product->get_id() . ')' . '</option>';
                                        }
                                    }
                                }
                                ?>
                            </select>
                            <p style="font-size: 12px; color: #666; margin-top: 5px;">Product for the Buy Course box</p>
                        </td>
                    </tr>
                    
                    <tr id="enroll-product-row" style="display: none;">
                        <th><label>Enroll Course Product</label></th>
                        <td>
                            <select id="enroll-product" data-course-id="<?php echo esc_attr($course_id); ?>">
                                <option value="0">None</option>
                                <?php
                                $enroll_product_id = get_post_meta($course_id, 'enroll_product_id', true);
                                if (function_exists('wc_get_products')) {
                                    $products = wc_get_products(['limit' => -1, 'orderby' => 'title', 'order' => 'ASC', 'status' => 'publish']);
                                    if (!empty($products)) {
                                        foreach ($products as $product) {
                                            $selected = ($enroll_product_id == $product->get_id()) ? ' selected' : '';
                                            echo '<option value="' . esc_attr($product->get_id()) . '"' . $selected . '>' . 
                                                 esc_html($product->get_name()) . ' (#' . $product->get_id() . ')' . '</option>';
                                        }
                                    }
                                }
                                ?>
                            </select>
                            <p style="font-size: 12px; color: #666; margin-top: 5px;">Product for the Enroll Course box</p>
                        </td>
                    </tr>
                    
                    <tr id="buy-price-row" style="display: none;">
                        <th><label>Buy Course Price</label></th>
                        <td>
                            <input type="text" id="buy-price" data-course-id="<?php echo esc_attr($course_id); ?>" 
                                   value="<?php echo esc_attr(get_post_meta($course_id, 'buy_price', true) ?: ''); ?>" 
                                   placeholder="e.g., 749.99" />
                            <p style="font-size: 12px; color: #666; margin-top: 5px;">Price for the Buy Course option</p>
                        </td>
                    </tr>
                    
                    
                    <tr>
                        <th><label>Dates & Seats Management</label></th>
                        <td>
                                <div class="date-list" data-course-id="<?php echo esc_attr($course_id); ?>">
                                    <div class="date-header" style="display: flex; gap: 10px; margin-bottom: 10px; padding: 8px; background: #f5f5f5; border-radius: 4px; font-weight: bold;">
                                        <span style="width: 120px;">Date</span>
                                        <span style="width: 80px;">Total Seats</span>
                                        <span style="width: 80px;">Sold</span>
                                        <span style="width: 80px;">Available</span>
                                        <span style="width: 150px;">Button Text</span>
                                        <span style="width: 100px;">Actions</span>
                                    </div>
                                    <?php 
                                    foreach ($dates as $index => $date) : 
                                        $date_stock = isset($date['stock']) ? intval($date['stock']) : $course_stock;
                                        $date_button_text = isset($date['button_text']) ? $date['button_text'] : 'Enroll Now';
                                        $date_sold = 0;
                                        $date_available = $date_stock;
                                        
                                        // Calculate sold and available for this specific date
                                        if ($product_id && isset($date['date'])) {
                                            $date_sold = calculate_seats_sold($product_id, $date['date']);
                                            $date_available = max(0, $date_stock - $date_sold);
                                        }
                                        
                                        // Determine row styling based on availability
                                        $row_class = '';
                                        if ($date_stock > 0) {
                                            $percentage = ($date_available / $date_stock) * 100;
                                            if ($percentage <= 20) {
                                                $row_class = 'seat-warning';
                                            } elseif ($percentage <= 50) {
                                                $row_class = 'seat-caution';
                                            }
                                        }
                                    ?>
                                        <div class="date-stock-row <?php echo esc_attr($row_class); ?>" style="display: flex; gap: 10px; margin-bottom: 8px; padding: 8px; background: #fff; border: 1px solid #ddd; border-radius: 4px; align-items: center;">
                                            <input type="text" class="course-date" value="<?php echo esc_attr($date['date']); ?>" data-index="<?php echo esc_attr($index); ?>" placeholder="YYYY-MM-DD" style="width: 120px; padding: 5px;">
                                            
                                            <input type="number" class="course-stock" value="<?php echo esc_attr($date_stock); ?>" data-index="<?php echo esc_attr($index); ?>" placeholder="10" min="0" style="width: 80px; padding: 5px;">
                                            
                                            <span style="width: 80px; text-align: center; color: #666;"><?php echo esc_html($date_sold); ?></span>
                                            
                                            <span style="width: 80px; text-align: center; font-weight: bold; color: <?php echo $date_available <= 5 ? '#d54e21' : ($date_available <= 10 ? '#f0ad4e' : '#46b450'); ?>">
                                                <?php echo esc_html($date_available); ?>
                                            </span>
                                            
                                            <input type="text" class="course-button-text" value="<?php echo esc_attr($date_button_text); ?>" data-index="<?php echo esc_attr($index); ?>" placeholder="Enroll Now" style="width: 150px; padding: 5px;">
                                            
                                            <div style="width: 100px;">
                                                <button class="button button-small edit-seats" data-index="<?php echo esc_attr($index); ?>" style="margin-right: 5px;">Edit</button>
                                                <button class="button button-small remove-date" data-index="<?php echo esc_attr($index); ?>" style="background: #d54e21; color: white;">×</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <button class="button add-date" style="margin-top: 10px;">+ Add New Date</button>
                                    
                                    <!-- Summary Section -->
                                    <?php if (!empty($dates)) : 
                                        $total_stock_sum = 0;
                                        $total_sold_sum = 0;
                                        $total_available_sum = 0;
                                        
                                        foreach ($dates as $date) {
                                            $stock = isset($date['stock']) ? intval($date['stock']) : $course_stock;
                                            $sold = isset($date['date']) ? calculate_seats_sold($product_id, $date['date']) : 0;
                                            $available = max(0, $stock - $sold);
                                            
                                            $total_stock_sum += $stock;
                                            $total_sold_sum += $sold;
                                            $total_available_sum += $available;
                                        }
                                    ?>
                                    <div style="margin-top: 20px; padding: 10px; background: #f1f1f1; border-radius: 4px;">
                                        <strong>Summary:</strong> 
                                        Total Seats: <?php echo $total_stock_sum; ?> | 
                                        Sold: <?php echo $total_sold_sum; ?> | 
                                        Available: <span style="color: <?php echo $total_available_sum <= 10 ? '#d54e21' : '#46b450'; ?>"><?php echo $total_available_sum; ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <tr>
                        <th><label>Selling Page</label></th>
                        <td>
                            <select id="selling-page" data-course-id="<?php echo esc_attr($course_id); ?>">
                                <option value="0">None</option>
                                <?php
                                $selling_pages = get_posts(['post_type' => 'course', 'posts_per_page' => -1]);
                                foreach ($selling_pages as $page) {
                                    echo '<option value="' . esc_attr($page->ID) . '"' . ($selling_page_id == $page->ID ? ' selected' : '') . '>' . esc_html($page->post_title) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <button class="button button-primary save-course-settings" data-course-id="<?php echo esc_attr($course_id); ?>">Save Settings</button>
            </div>
        <?php endif; ?>

        <!-- Modal for Adding Course to Group -->
        <div id="add-course-modal" class="modal" style="display:none;">
            <div class="modal-content">
                <span class="modal-close">×</span>
                <h2><?php echo isset($_GET['group_id']) ? 'Add Course to Group' : 'Assign Course to Group'; ?></h2>
                <label>Select Course:</label>
                <select id="course-select" style="width: 100%; margin-bottom: 15px;">
                    <option value="">Select a course...</option>
                    <?php
                    // Get the current group ID if we're in a group view
                    $current_group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
                    
                    // Get all courses
                    $all_courses = get_posts([
                        'post_type' => 'course',
                        'posts_per_page' => -1,
                        'orderby' => 'title',
                        'order' => 'ASC'
                    ]);
                    
                    // If we're in a group view, get courses already in this group to exclude them
                    $courses_in_group = [];
                    if ($current_group_id) {
                        $courses_in_group = get_posts([
                            'post_type' => 'course',
                            'posts_per_page' => -1,
                            'fields' => 'ids',
                            'tax_query' => [
                                [
                                    'taxonomy' => 'course_group',
                                    'field' => 'term_id',
                                    'terms' => $current_group_id,
                                ],
                            ],
                        ]);
                    }
                    
                    foreach ($all_courses as $course) {
                        // Skip courses already in the current group
                        if (!in_array($course->ID, $courses_in_group)) {
                            echo '<option value="' . esc_attr($course->ID) . '">' . esc_html($course->post_title) . '</option>';
                        }
                    }
                    ?>
                </select>
                
                <label>Select Instructors:</label>
                <select id="course-instructors-select" multiple style="width: 100%; margin-bottom: 15px; height: 120px;">
                    <?php
                    $all_instructors = get_posts([
                        'post_type' => 'instructor',
                        'posts_per_page' => -1,
                        'orderby' => 'title',
                        'order' => 'ASC'
                    ]);
                    foreach ($all_instructors as $instructor) {
                        echo '<option value="' . esc_attr($instructor->ID) . '">' . esc_html($instructor->post_title) . '</option>';
                    }
                    ?>
                </select>
                <p style="font-size: 12px; color: #666; margin-top: -10px;">Hold Ctrl/Cmd to select multiple instructors</p>
                
                <?php if (!isset($_GET['group_id'])) : ?>
                <label>Course Group:</label>
                <select id="course-group" style="margin-bottom: 15px;">
                    <option value="0">None</option>
                    <?php
                    $groups = get_terms(['taxonomy' => 'course_group', 'hide_empty' => false]);
                    foreach ($groups as $group) {
                        echo '<option value="' . esc_attr($group->term_id) . '">' . esc_html($group->name) . '</option>';
                    }
                    ?>
                </select>
                <?php else : ?>
                <input type="hidden" id="course-group" value="<?php echo esc_attr($_GET['group_id']); ?>">
                <?php endif; ?>
                <button class="button button-primary" id="save-course-assignment">Add to Group</button>
            </div>
        </div>

        <!-- Modal for Adding Course Group -->
        <div id="add-course-group-modal" class="modal" style="display:none;">
            <div class="modal-content">
                <span class="modal-close">×</span>
                <h2>Add Course Group</h2>
                <label>Group Name:</label>
                <input type="text" id="course-group-name" placeholder="e.g., How to do AI">
                <button class="button button-primary" id="save-new-course-group">Create Group</button>
            </div>
        </div>

        <style>
            /* Modal styles */
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.4);
            }
            .modal-content {
                background-color: #fff;
                margin: 15% auto;
                padding: 20px;
                border: 1px solid #888;
                width: 80%;
                max-width: 500px;
                border-radius: 5px;
            }
            .modal-close {
                color: #aaa;
                float: right;
                font-size: 28px;
                cursor: pointer;
            }
            .modal-close:hover {
                color: black;
            }
            .box-state-select, .instructor-select, #course-group, #selling-page {
                width: 200px;
                padding: 5px;
                font-size: 14px;
            }
            .date-list {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            .date-header {
                background: #f5f5f5;
                border-bottom: 2px solid #ddd;
            }
            .date-stock-row {
                transition: background 0.2s;
            }
            .date-stock-row:hover {
                background: #e9ecef !important;
            }
            .date-stock-row.seat-warning {
                background: #fff5f5 !important;
                border-color: #ffcccc !important;
            }
            .date-stock-row.seat-caution {
                background: #fffbf0 !important;
                border-color: #ffe4b5 !important;
            }
            .date-stock-row input {
                border: 1px solid #ddd;
                border-radius: 3px;
            }
            .date-stock-row input:focus {
                border-color: #5b9dd9;
                box-shadow: 0 0 2px rgba(30,140,190,.8);
                outline: none;
            }
            .course-date {
                padding: 5px;
                font-size: 14px;
            }
            .course-stock {
                text-align: center;
            }
            .remove-date {
                padding: 2px 8px;
                font-size: 16px;
                border: none;
                border-radius: 3px;
                cursor: pointer;
                background: #d54e21;
                color: white;
                line-height: 1;
            }
            .remove-date:hover {
                background: #cc0000;
            }
            .add-date {
                background: #0073aa;
                color: white;
                border: none;
                cursor: pointer;
            }
            .add-date:hover {
                background: #005a87;
            }
            @keyframes pulse {
                0% { opacity: 1; }
                50% { opacity: 0.6; }
                100% { opacity: 1; }
            }
            .edit-seats {
                padding: 2px 8px;
                font-size: 11px;
            }
            .button-small {
                padding: 0 8px;
                line-height: 26px;
                height: 28px;
                font-size: 11px;
            }
            #course-search, #course-group-name {
                margin-bottom: 10px;
                padding: 5px;
                width: 300px;
            }
            .low-seats {
                color: #d54e21;
                font-weight: bold;
            }
            .medium-seats {
                color: #f0ad4e;
                font-weight: bold;
            }
            /* Modal for editing seats */
            #edit-seats-modal .modal-content {
                max-width: 400px;
            }
            #edit-seats-modal input {
                width: 100%;
                padding: 8px;
                margin: 10px 0;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            #edit-seats-modal .info-row {
                display: flex;
                justify-content: space-between;
                padding: 5px 0;
                border-bottom: 1px solid #eee;
            }
            #edit-seats-modal .info-label {
                font-weight: bold;
                color: #555;
            }
            #edit-seats-modal .info-value {
                color: #333;
            }
            
            /* Additional gray theme for summary sections */
            .wrap h1, .wrap h2, .wrap h3 {
                color: #343a40;
            }
            .form-table th {
                background: #6c757d;
                color: #ffffff;
                padding: 15px 10px;
            }
            .form-table td {
                background: #e9ecef;
                padding: 15px 10px;
            }
            
        </style>

    </div>
    <?php
}

// AJAX Handler for Creating Course Group
add_action('wp_ajax_create_new_course_group', 'create_new_course_group');
function create_new_course_group() {
    check_ajax_referer('course_box_nonce', 'nonce');
    $group_name = sanitize_text_field($_POST['group_name']);
    $term = wp_insert_term($group_name, 'course_group');
    if (!is_wp_error($term)) {
        wp_send_json_success();
    } else {
        wp_send_json_error($term->get_error_message());
    }
}

// AJAX Handler for Deleting Course Group
add_action('wp_ajax_delete_course_group', 'delete_course_group');
function delete_course_group() {
    check_ajax_referer('course_box_nonce', 'nonce');
    $group_id = intval($_POST['group_id']);
    
    // Remove the term from all courses first
    $courses = get_posts([
        'post_type' => 'course',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => [
            [
                'taxonomy' => 'course_group',
                'field' => 'term_id',
                'terms' => $group_id,
            ],
        ],
    ]);
    
    foreach ($courses as $course_id) {
        wp_remove_object_terms($course_id, $group_id, 'course_group');
    }
    
    // Delete the term
    $result = wp_delete_term($group_id, 'course_group');
    if (!is_wp_error($result)) {
        wp_send_json_success();
    } else {
        wp_send_json_error($result->get_error_message());
    }
}

// AJAX Handler for Assigning Course to Group
add_action('wp_ajax_assign_course_to_group', 'assign_course_to_group');
function assign_course_to_group() {
    error_log('[CBM Debug] assign_course_to_group called');
    error_log('[CBM Debug] POST data: ' . print_r($_POST, true));

    check_ajax_referer('course_box_nonce', 'nonce');

    // Check user capabilities
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    $course_id = intval($_POST['course_id']);
    $group_id = intval($_POST['group_id']);
    $instructors = isset($_POST['instructors']) ? cbm_json_decode($_POST['instructors'], true, []) : [];
    
    error_log('[CBM Debug] Course ID: ' . $course_id . ', Group ID: ' . $group_id);
    
    if (!$course_id) {
        error_log('[CBM Debug] No course selected');
        wp_send_json_error('No course selected.');
    }
    
    // Clear existing group terms and set new one
    wp_set_post_terms($course_id, [], 'course_group');
    
    if ($group_id > 0) {
        $result = wp_set_post_terms($course_id, [$group_id], 'course_group');
        error_log('[CBM Debug] wp_set_post_terms result: ' . print_r($result, true));
        if (is_wp_error($result)) {
            error_log('[CBM Debug] Error setting terms: ' . $result->get_error_message());
            wp_send_json_error($result->get_error_message());
        }
    }
    
    // Update instructors for this course
    if (!empty($instructors)) {
        update_post_meta($course_id, 'course_instructors', $instructors);
        cbm_update_field('course_instructors', $instructors, $course_id); // Update ACF field if exists
        
        // Update instructor meta - clear from all instructors first
        $all_instructors = get_posts(['post_type' => 'instructor', 'posts_per_page' => -1, 'fields' => 'ids']);
        foreach ($all_instructors as $instructor_id) {
            $courses = get_post_meta($instructor_id, 'instructor_courses', true) ?: [];
            $courses = array_filter($courses, function($id) use ($course_id) { return $id != $course_id; });
            update_post_meta($instructor_id, 'instructor_courses', $courses);
        }
        
        // Add course to selected instructors
        foreach ($instructors as $instructor_id) {
            $courses = get_post_meta($instructor_id, 'instructor_courses', true) ?: [];
            if (!in_array($course_id, $courses)) {
                $courses[] = $course_id;
                update_post_meta($instructor_id, 'instructor_courses', $courses);
            }
        }
    }
    
    wp_send_json_success();
}

// AJAX handler for adding products to cart (compatible with FunnelKit)
add_action('wp_ajax_woocommerce_add_to_cart', 'cbm_ajax_add_to_cart');
add_action('wp_ajax_nopriv_woocommerce_add_to_cart', 'cbm_ajax_add_to_cart');

function cbm_ajax_add_to_cart() {
    // Verify nonce
    if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'woocommerce-add-to-cart')) {
        wp_send_json_error('Invalid security token');
        return;
    }
    
    $product_id = intval($_POST['product_id']);
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
    
    error_log('[CBM Cart] Add to cart request - Product ID: ' . $product_id . ', Quantity: ' . $quantity . ', Start Date: ' . $start_date);
    
    if (!$product_id) {
        wp_send_json_error('Invalid product ID');
        return;
    }
    
    // Check if product exists
    $product = wc_get_product($product_id);
    if (!$product) {
        error_log('[CBM Cart] Product not found for ID: ' . $product_id);
        wp_send_json_error('Product not found');
        return;
    }
    
    error_log('[CBM Cart] Product found: ' . $product->get_name() . ', Price: ' . $product->get_price() . ', In Stock: ' . ($product->is_in_stock() ? 'yes' : 'no'));
    
    // Initialize WooCommerce cart if needed
    if (!WC()->cart) {
        WC()->cart = new WC_Cart();
    }
    
    // Clear any previous notices
    wc_clear_notices();
    
    // Add custom data if start_date is provided
    $cart_item_data = array();
    if (!empty($start_date)) {
        $cart_item_data['course_start_date'] = $start_date;
    }
    
    // Try to add product to cart
    $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity);
    
    if ($passed_validation) {
        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, 0, array(), $cart_item_data);
        
        if ($cart_item_key) {
            error_log('[CBM Cart] Product added to cart successfully. Cart item key: ' . $cart_item_key);
            
            // Trigger FunnelKit Cart hooks if available
            do_action('fkcart_item_added', $product_id, $quantity);
            do_action('woocommerce_ajax_added_to_cart', $product_id);
            
            // Get cart fragments for updating mini-cart
            ob_start();
            woocommerce_mini_cart();
            $mini_cart = ob_get_clean();
            
            // Check if FunnelKit Cart is active
            $use_funnelkit = defined('FKCART_VERSION') || class_exists('FKCart') || class_exists('FunnelKitCart');
            
            $data = array(
                'success' => true,
                'fragments' => apply_filters('woocommerce_add_to_cart_fragments', array(
                    'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
                    '.cart-contents-count' => '<span class="cart-contents-count">' . WC()->cart->get_cart_contents_count() . '</span>'
                )),
                'cart_hash' => WC()->cart->get_cart_hash(),
                'cart_item_key' => $cart_item_key,
                'product_name' => $product->get_name(),
                'use_funnelkit' => $use_funnelkit
            );
            
            // Add FunnelKit specific data if available
            $data = apply_filters('fkcart_add_to_cart_response', $data, $product_id);
            
            wp_send_json($data);
        } else {
            // Get any error notices
            $notices = wc_get_notices('error');
            $error_message = !empty($notices) ? strip_tags($notices[0]['notice']) : 'Failed to add product to cart';
            
            error_log('[CBM Cart] Failed to add product to cart. Error: ' . $error_message);
            wp_send_json_error($error_message);
        }
    } else {
        error_log('[CBM Cart] Cart validation failed for product ID: ' . $product_id);
        wp_send_json_error('Product validation failed');
    }
}

add_action('wp_ajax_save_group_settings', 'save_group_settings');
function save_group_settings() {
    check_ajax_referer('course_box_nonce', 'nonce');

    // Check user capabilities
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    $group_id = intval($_POST['group_id']);
    $box_state = sanitize_text_field($_POST['box_state']);
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    
    // Get all courses in the group
    $courses_to_update = [];
    if ($group_id) {
        $courses = get_posts([
            'post_type' => 'course',
            'posts_per_page' => -1,
            'tax_query' => [
                [
                    'taxonomy' => 'course_group',
                    'field' => 'term_id',
                    'terms' => $group_id,
                ],
            ],
            'fields' => 'ids'
        ]);
        
        if (!empty($courses)) {
            $courses_to_update = $courses;
        }
    }
    
    // If we have a specific course_id, use that, otherwise use all courses in group
    if ($course_id && !in_array($course_id, $courses_to_update)) {
        $courses_to_update[] = $course_id;
    }
    
    if (empty($courses_to_update)) {
        wp_send_json_error('No courses found in group');
        return;
    }
    
    // Update all courses in the group
    foreach ($courses_to_update as $course_id) {
        // Update box state
        update_post_meta($course_id, 'box_state', $box_state);
    }
    
    // Use the first course for saving the main configuration
    $course_id = $courses_to_update[0];
    
    if ($box_state === 'enroll-buy') {
        // Save buy product configuration
        if (isset($_POST['buy_product_id'])) {
            update_post_meta($course_id, 'buy_product_id', intval($_POST['buy_product_id']));
        }
        
        if (isset($_POST['buy_button_text'])) {
            update_post_meta($course_id, 'buy_button_text', sanitize_text_field($_POST['buy_button_text']));
        }
        
        // Update buy product prices if provided
        if (isset($_POST['buy_product_id']) && $_POST['buy_product_id']) {
            $buy_product_id = intval($_POST['buy_product_id']);
            
            if (isset($_POST['buy_regular_price']) && $_POST['buy_regular_price'] !== '') {
                update_post_meta($buy_product_id, '_regular_price', sanitize_text_field($_POST['buy_regular_price']));
            }
            
            if (isset($_POST['buy_sale_price']) && $_POST['buy_sale_price'] !== '') {
                update_post_meta($buy_product_id, '_sale_price', sanitize_text_field($_POST['buy_sale_price']));
                update_post_meta($buy_product_id, '_price', sanitize_text_field($_POST['buy_sale_price']));
            } else if (isset($_POST['buy_regular_price'])) {
                update_post_meta($buy_product_id, '_price', sanitize_text_field($_POST['buy_regular_price']));
            }
        }
        
        // Save enroll product configuration
        if (isset($_POST['enroll_product_id'])) {
            update_post_meta($course_id, 'enroll_product_id', intval($_POST['enroll_product_id']));
        }
        
        // Save enroll dates
        if (isset($_POST['enroll_dates'])) {
            $enroll_dates = cbm_json_decode($_POST['enroll_dates'], true, []);
            if (is_array($enroll_dates)) {
                // Format dates for storage
                $formatted_dates = [];
                foreach ($enroll_dates as $date_info) {
                    if (!empty($date_info['date'])) {
                        $formatted_dates[] = [
                            'date' => sanitize_text_field($date_info['date']),
                            'stock' => isset($date_info['stock']) ? intval($date_info['stock']) : 20,
                            'button_text' => isset($date_info['button_text']) ? sanitize_text_field($date_info['button_text']) : 'Enroll Now'
                        ];
                    }
                }
                
                // Save using ACF or post meta
                if (function_exists('update_field')) {
                    update_field('course_dates', $formatted_dates, $course_id);
                } else {
                    update_post_meta($course_id, 'course_dates', $formatted_dates);
                }
            }
        }
    } else {
        // Handle other box states
        if (isset($_POST['linked_product_id'])) {
            update_post_meta($course_id, 'linked_product_id', intval($_POST['linked_product_id']));
        }

        if (isset($_POST['dates'])) {
            $dates = cbm_json_decode($_POST['dates'], true, []);
            if (is_array($dates)) {
                $formatted_dates = [];
                foreach ($dates as $date_info) {
                    if (!empty($date_info['date'])) {
                        $date_entry = [
                            'date' => sanitize_text_field($date_info['date']),
                            'stock' => isset($date_info['stock']) ? intval($date_info['stock']) : 20,
                            'button_text' => isset($date_info['button_text']) ? sanitize_text_field($date_info['button_text']) : ''
                        ];

                        // Process product_id if exists for this specific date
                        if (isset($date_info['product_id']) && !empty($date_info['product_id'])) {
                            $product_id = intval($date_info['product_id']);
                            $date_entry['product_id'] = $product_id;

                            // Update prices for this specific product if provided
                            if (function_exists('wc_get_product')) {
                                $product = wc_get_product($product_id);
                                if ($product) {
                                    $price_updated = false;

                                    if (isset($date_info['regular_price']) && $date_info['regular_price'] !== '') {
                                        $product->set_regular_price(floatval($date_info['regular_price']));
                                        $price_updated = true;
                                    }

                                    // Handle sale_price: always update if provided (even if empty to clear)
                                    if (isset($date_info['sale_price'])) {
                                        $sale_price_value = floatval($date_info['sale_price']);
                                        if ($sale_price_value > 0) {
                                            $product->set_sale_price($sale_price_value);
                                        } else {
                                            // Clear sale price if 0 or empty
                                            $product->set_sale_price('');
                                            delete_post_meta($product_id, '_sale_price');
                                        }
                                        $price_updated = true;
                                    }

                                    if ($price_updated) {
                                        $product->save();
                                        error_log('[CBM Debug] Updated prices for product ' . $product_id . ' in date: ' . $date_info['date']);
                                    }
                                }
                            }
                        }

                        // Process STM course_id if exists
                        if (isset($date_info['stm_course_id']) && !empty($date_info['stm_course_id'])) {
                            $date_entry['stm_course_id'] = intval($date_info['stm_course_id']);
                        }

                        $formatted_dates[] = $date_entry;
                    }
                }

                if (function_exists('update_field')) {
                    update_field('course_dates', $formatted_dates, $course_id);
                } else {
                    update_post_meta($course_id, 'course_dates', $formatted_dates);
                }

                error_log('[CBM Debug] Saved ' . count($formatted_dates) . ' dates with individual products for course ' . $course_id);
            }
        }
    }
    
    wp_send_json_success(['message' => 'Settings saved successfully']);
}

add_action('wp_ajax_save_course_settings', 'save_course_settings');
function save_course_settings() {
    check_ajax_referer('course_box_nonce', 'nonce');

    // Check user capabilities
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    $course_id = intval($_POST['course_id']);
    $group_id = intval($_POST['group_id']);
    $box_state = sanitize_text_field($_POST['box_state']);
    $instructors = cbm_json_decode($_POST['instructors'], true, []);
    $stock = sanitize_text_field($_POST['stock']);
    $dates = cbm_json_decode($_POST['dates'], true, []);
    $selling_page_id = intval($_POST['selling_page_id']);
    $linked_product_id = intval($_POST['linked_product_id']);
    
    // Additional fields for enroll-buy state
    $buy_product_id = isset($_POST['buy_product_id']) ? intval($_POST['buy_product_id']) : 0;
    $enroll_product_id = isset($_POST['enroll_product_id']) ? intval($_POST['enroll_product_id']) : 0;
    $buy_price = isset($_POST['buy_price']) ? sanitize_text_field($_POST['buy_price']) : '';
    
    // STM Course ID for enrollment sync
    $related_stm_course_id = isset($_POST['related_stm_course_id']) ? intval($_POST['related_stm_course_id']) : 0;

    // Update course group
    if ($group_id) {
        wp_set_post_terms($course_id, [$group_id], 'course_group');
    } else {
        wp_set_post_terms($course_id, [], 'course_group');
    }

    // Update selling page
    $current_terms = wp_get_post_terms($course_id, 'course_group');
    $current_group_id = !empty($current_terms) ? $current_terms[0]->term_id : 0;
    if ($current_group_id) {
        $existing_page = get_posts([
            'post_type' => 'course',
            'posts_per_page' => 1,
            'tax_query' => [
                [
                    'taxonomy' => 'course_group',
                    'field' => 'term_id',
                    'terms' => $current_group_id,
                ],
            ],
        ]);
        if ($existing_page && $existing_page[0]->ID != $selling_page_id) {
            wp_set_post_terms($existing_page[0]->ID, [], 'course_group');
        }
        if ($selling_page_id) {
            wp_set_post_terms($selling_page_id, [$current_group_id], 'course_group');
        }
    }

    // Process dates first to determine if we need to change state
    $formatted_dates = [];
    if ($dates && is_array($dates)) {
        foreach ($dates as $date_info) {
            if (is_array($date_info) && isset($date_info['date']) && !empty($date_info['date'])) {
                $formatted_dates[] = [
                    'date' => $date_info['date'],
                    'stock' => isset($date_info['stock']) ? intval($date_info['stock']) : $stock,
                    'button_text' => isset($date_info['button_text']) ? sanitize_text_field($date_info['button_text']) : 'Enroll Now'
                ];
            } elseif (is_string($date_info) && !empty($date_info)) {
                // Legacy support for simple date strings
                $formatted_dates[] = ['date' => $date_info, 'stock' => $stock, 'button_text' => 'Enroll Now'];
            }
        }
    }

    update_post_meta($course_id, 'box_state', $box_state);
    update_post_meta($course_id, 'course_instructors', $instructors);
    
    // Update linked product
    update_post_meta($course_id, 'linked_product_id', $linked_product_id);
    
    // Save additional fields for enroll-buy state
    if ($box_state === 'enroll-buy') {
        update_post_meta($course_id, 'buy_product_id', $buy_product_id);
        update_post_meta($course_id, 'enroll_product_id', $enroll_product_id);
        update_post_meta($course_id, 'buy_price', $buy_price);
    } else {
        // Clean up if not using enroll-buy state
        delete_post_meta($course_id, 'buy_product_id');
        delete_post_meta($course_id, 'enroll_product_id');
        delete_post_meta($course_id, 'buy_price');
    }
    
    // Save STM Course relationship
    if ($related_stm_course_id > 0) {
        update_post_meta($course_id, 'related_stm_course_id', $related_stm_course_id);
        
        // Also update the relationship on all associated WooCommerce products
        // This ensures the enrollment sync will work properly
        if ($linked_product_id) {
            update_post_meta($linked_product_id, 'related_stm_course_id', $related_stm_course_id);
        }
        if ($buy_product_id) {
            update_post_meta($buy_product_id, 'related_stm_course_id', $related_stm_course_id);
        }
        if ($enroll_product_id) {
            update_post_meta($enroll_product_id, 'related_stm_course_id', $related_stm_course_id);
        }
    } else {
        delete_post_meta($course_id, 'related_stm_course_id');
        // Clean up product relationships
        if ($linked_product_id) {
            delete_post_meta($linked_product_id, 'related_stm_course_id');
        }
        if ($buy_product_id) {
            delete_post_meta($buy_product_id, 'related_stm_course_id');
        }
        if ($enroll_product_id) {
            delete_post_meta($enroll_product_id, 'related_stm_course_id');
        }
    }
    
    // Update stock on the product
    if ($linked_product_id && $stock !== '') {
        update_post_meta($linked_product_id, '_stock', $stock);
        update_post_meta($linked_product_id, '_manage_stock', 'yes');
        if ($group_id) {
            wp_set_post_terms($linked_product_id, [$group_id], 'course_group');
        } else {
            wp_set_post_terms($linked_product_id, [], 'course_group');
        }
    }
    
    // Update or delete dates field
    if (!empty($formatted_dates)) {
        cbm_update_field('course_dates', $formatted_dates, $course_id);
    } else {
        // Delete dates field when empty array or no dates
        delete_post_meta($course_id, 'course_dates');
    }

    // Update instructor meta
    foreach ($instructors as $instructor_id) {
        $courses = get_post_meta($instructor_id, 'instructor_courses', true) ?: [];
        if (!in_array($course_id, $courses)) {
            $courses[] = $course_id;
            update_post_meta($instructor_id, 'instructor_courses', $courses);
        }
    }

    wp_send_json_success(['updated_box_state' => $box_state]);
}

add_action('wp_ajax_remove_course_from_group', 'remove_course_from_group');
function remove_course_from_group() {
    check_ajax_referer('course_box_nonce', 'nonce');

    // Check user capabilities
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    $course_id = intval($_POST['course_id']);
    $group_id = intval($_POST['group_id']);
    
    // Remove the course from the group
    wp_remove_object_terms($course_id, $group_id, 'course_group');
    
    wp_send_json_success();
}

add_action('wp_ajax_delete_course', 'delete_course');
function delete_course() {
    check_ajax_referer('course_box_nonce', 'nonce');

    // Check user capabilities
    if (!current_user_can('delete_posts')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    $course_id = intval($_POST['course_id']);
    $product_id = get_post_meta($course_id, 'linked_product_id', true);
    if ($product_id) {
        wp_delete_post($product_id, true);
    }
    wp_delete_post($course_id, true);
    wp_send_json_success();
}

// AJAX Handler for inline date/stock editing
add_action('wp_ajax_save_inline_dates', 'save_inline_dates');
function save_inline_dates() {
    check_ajax_referer('course_box_nonce', 'nonce');

    // Check user capabilities
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    $course_id = intval($_POST['course_id']);
    $dates = cbm_json_decode($_POST['dates'], true, []);
    
    if (!$course_id) {
        wp_send_json_error('Invalid course ID');
    }
    
    // Format dates for ACF field
    $formatted_dates = [];
    $course_stock = cbm_get_field('course_stock', $course_id) ?: 0;
    
    if ($dates && !empty($dates)) {
        foreach ($dates as $date_info) {
            if (isset($date_info['date']) && !empty($date_info['date'])) {
                $formatted_dates[] = [
                    'date' => $date_info['date'],
                    'stock' => isset($date_info['stock']) ? intval($date_info['stock']) : $course_stock
                ];
            }
        }
    }
    
    // Update or delete ACF field based on whether we have dates
    if (!empty($formatted_dates)) {
        cbm_update_field('course_dates', $formatted_dates, $course_id);
    } else {
        delete_post_meta($course_id, 'course_dates');
    }
    
    // Calculate new summary
    $product_id = get_post_meta($course_id, 'linked_product_id', true);
    $total_seats = 0;
    $total_available = 0;
    
    if ($product_id && !empty($formatted_dates)) {
        foreach ($formatted_dates as $date) {
            $stock = $date['stock'];
            $sold = calculate_seats_sold($product_id, $date['date']);
            $available = max(0, $stock - $sold);
            $total_seats += $stock;
            $total_available += $available;
        }
    }
    
    $summary = $total_seats > 0 ? $total_available . '/' . $total_seats : '-';
    $percentage = $total_seats > 0 ? ($total_available / $total_seats) * 100 : 100;
    
    wp_send_json_success([
        'summary' => $summary,
        'percentage' => $percentage,
        'total_seats' => $total_seats,
        'total_available' => $total_available
    ]);
}

// AJAX Handler for saving table row data
add_action('wp_ajax_save_table_row_data', 'save_table_row_data');
function save_table_row_data() {
    check_ajax_referer('course_box_nonce', 'nonce');

    // Check user capabilities
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    $course_id = intval($_POST['course_id']);
    $date_index = sanitize_text_field($_POST['date_index']);
    $product_id = intval($_POST['product_id']);
    $regular_price = isset($_POST['regular_price']) ? floatval($_POST['regular_price']) : null;

    // Handle sale_price: empty string should be treated as "clear the sale price"
    $sale_price = null;
    if (isset($_POST['sale_price'])) {
        $sale_price_raw = trim($_POST['sale_price']);
        if ($sale_price_raw === '' || $sale_price_raw === '0') {
            $sale_price = 0; // Clear sale price
        } else {
            $sale_price = floatval($sale_price_raw);
        }
    }
    $instructor_id = intval($_POST['instructor_id']);
    $stock = intval($_POST['stock']);
    $button_text = sanitize_text_field($_POST['button_text']);
    $box_state = sanitize_text_field($_POST['box_state']);
    $launch_date = isset($_POST['launch_date']) ? sanitize_text_field($_POST['launch_date']) : '';
    $related_stm_course_id = isset($_POST['related_stm_course_id']) ? intval($_POST['related_stm_course_id']) : 0;
    $stm_course_id = isset($_POST['stm_course_id']) ? intval($_POST['stm_course_id']) : 0;
    
    // Date is optional for buy-course state
    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
    
    // Debug logging
    error_log('[CBM Debug] save_table_row_data - Course ID: ' . $course_id . ', Box State: ' . $box_state . ', Date: ' . $date . ', Date Index: ' . $date_index);
    error_log('[CBM Debug] Product ID: ' . $product_id . ', Stock: ' . $stock . ', Button Text: ' . $button_text);
    
    if (!$course_id) {
        error_log('[CBM Debug] Error: Invalid course ID');
        wp_send_json_error('Invalid course ID');
    }
    
    // Date is required for states that use dates (enroll-course, soldout, countdown)
    if (in_array($box_state, ['enroll-course', 'soldout', 'countdown']) && empty($date)) {
        error_log('[CBM Debug] Error: Date required for box state ' . $box_state . ' but was empty');
        wp_send_json_error('Date is required for ' . $box_state . ' state');
    }
    
    // Update product association and prices
    // Only update linked_product_id if we're in a state that doesn't use per-date products
    // or if this is a new course without dates
    if ($product_id) {
        // Don't update linked_product_id for individual date rows in states that support multiple dates
        // This preserves the main product association
        if (!in_array($box_state, ['enroll-course', 'soldout', 'countdown', 'enroll-buy']) || $date_index === 'new_course') {
            update_post_meta($course_id, 'linked_product_id', $product_id);
        }

        // Update product prices in WooCommerce if provided
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            if ($product) {
                $price_updated = false;
                
                // Update regular price if provided
                if ($regular_price !== null && $regular_price >= 0) {
                    $product->set_regular_price($regular_price);
                    $price_updated = true;
                    error_log('[CBM Debug] Updated product ' . $product_id . ' regular price to: ' . $regular_price);
                }

                // Update sale price: always update if provided (even if 0 to clear)
                if ($sale_price !== null) {
                    if ($sale_price > 0) {
                        $product->set_sale_price($sale_price);
                        error_log('[CBM Debug] Updated product ' . $product_id . ' sale price to: ' . $sale_price);
                    } else {
                        // Clear sale price if 0 or empty
                        $product->set_sale_price('');
                        delete_post_meta($product_id, '_sale_price');
                        error_log('[CBM Debug] Cleared sale price for product ' . $product_id);
                    }
                    $price_updated = true;
                }
                
                if ($price_updated) {
                    $product->save();
                }
            }
        }
        
        // Update launch date on product if provided
        if ($launch_date && $box_state === 'countdown') {
            update_post_meta($product_id, '_launch_date', $launch_date);
        }
    } else {
        delete_post_meta($course_id, 'linked_product_id');
    }
    
    // Update box state
    update_post_meta($course_id, 'box_state', $box_state);
    
    // Update STM Course association
    if ($related_stm_course_id) {
        update_post_meta($course_id, 'related_stm_course_id', $related_stm_course_id);
        error_log('[CBM Debug] Updated STM course ID ' . $related_stm_course_id . ' for course ' . $course_id);
    } else {
        delete_post_meta($course_id, 'related_stm_course_id');
    }
    
    // Update instructor
    if ($instructor_id) {
        $instructors = [$instructor_id]; // Store as array for consistency
        update_post_meta($course_id, 'course_instructors', $instructors);
        if (function_exists('update_field')) {
            update_field('course_instructors', $instructors, $course_id);
        }
    } else {
        delete_post_meta($course_id, 'course_instructors');
        delete_post_meta($course_id, 'course_instructors');
    }
    
    // Handle dates based on box state
    if ($box_state === 'buy-course' || $box_state === 'waitlist') {
        // These states don't use dates
        delete_post_meta($course_id, 'course_dates');
        // Store stock directly on course
        update_post_meta($course_id, 'course_stock', $stock);
        if (function_exists('update_field')) {
            update_field('course_stock', $stock, $course_id);
        }
    } else {
        // Get existing dates - ensure it's always an array
        $existing_dates = [];
        if (function_exists('get_field')) {
            $existing_dates = get_field('course_dates', $course_id);
        } else {
            $existing_dates = get_post_meta($course_id, 'course_dates', true);
        }

        // Ensure we have an array
        if (!is_array($existing_dates)) {
            $existing_dates = [];
        }

        error_log('[CBM Debug] Existing dates before update: ' . json_encode($existing_dates));
        
        // Update or add the date entry
        if ($date_index === 'new') {
            // Adding a new date
            $date_entry = [
                'date' => $date,
                'stock' => $stock,
                'button_text' => $button_text,
                'product_id' => $product_id  // Store product ID for each date
            ];

            // Add STM Course ID if provided
            if ($stm_course_id && ($box_state === 'enroll-course' || $box_state === 'enroll-buy')) {
                $date_entry['stm_course_id'] = $stm_course_id;
            }

            $existing_dates[] = $date_entry;
        } else {
            // Updating existing date
            $index = intval($date_index);
            if (isset($existing_dates[$index])) {
                $date_entry = [
                    'date' => $date,
                    'stock' => $stock,
                    'button_text' => $button_text,
                    'product_id' => $product_id  // Store product ID for each date
                ];

                // Add STM Course ID if provided
                if ($stm_course_id && ($box_state === 'enroll-course' || $box_state === 'enroll-buy')) {
                    $date_entry['stm_course_id'] = $stm_course_id;
                }

                $existing_dates[$index] = $date_entry;
            }
        }
        
        // Save the updated dates
        error_log('[CBM Debug] Saving dates for course ' . $course_id . ': ' . json_encode($existing_dates));
        update_post_meta($course_id, 'course_dates', $existing_dates);
        if (function_exists('update_field')) {
            update_field('course_dates', $existing_dates, $course_id);
        }

        // Verify the save
        $saved_dates = get_post_meta($course_id, 'course_dates', true);
        error_log('[CBM Debug] Dates after save: ' . json_encode($saved_dates));
    }
    
    // Return detailed success response
    wp_send_json_success([
        'message' => 'Data saved successfully',
        'saved_data' => [
            'course_id' => $course_id,
            'date_index' => $date_index,
            'dates_count' => count($existing_dates),
            'product_id' => $product_id,
            'stock' => $stock
        ]
    ]);
}

// AJAX Handler for deleting table row
add_action('wp_ajax_delete_table_row', 'delete_table_row');
function delete_table_row() {
    check_ajax_referer('course_box_nonce', 'nonce');

    // Check user capabilities
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    $course_id = intval($_POST['course_id']);
    $date_index = intval($_POST['date_index']);
    
    if (!$course_id) {
        wp_send_json_error('Invalid course ID');
    }
    
    // Get existing dates
    $existing_dates = cbm_get_field('course_dates', $course_id) ?: [];
    
    // Remove the date at the specified index
    if (isset($existing_dates[$date_index])) {
        array_splice($existing_dates, $date_index, 1);
        
        // Save the updated dates
        if (!empty($existing_dates)) {
            cbm_update_field('course_dates', $existing_dates, $course_id);
        } else {
            delete_post_meta($course_id, 'course_dates');
        }
        
        wp_send_json_success(['message' => 'Row deleted successfully']);
    } else {
        wp_send_json_error('Invalid date index');
    }
}

// AJAX Handler for applying group settings
add_action('wp_ajax_apply_group_settings', 'apply_group_settings');
function apply_group_settings() {
    check_ajax_referer('course_box_nonce', 'nonce');

    // Check user capabilities
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    $group_id = intval($_POST['group_id']);
    $box_state = sanitize_text_field($_POST['box_state']);
    $instructor_id = intval($_POST['instructor_id']);
    $selling_page_id = intval($_POST['selling_page_id']);
    
    if (!$group_id) {
        wp_send_json_error('Invalid group ID');
    }
    
    // Get all courses in the group
    $courses = get_posts([
        'post_type' => 'course',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => [
            [
                'taxonomy' => 'course_group',
                'field' => 'term_id',
                'terms' => $group_id,
            ],
        ],
    ]);
    
    // First, clear all selling page flags
    foreach ($courses as $course_id) {
        delete_post_meta($course_id, 'is_selling_page');
    }
    
    // Apply settings to each course
    foreach ($courses as $course_id) {
        // Update box state
        update_post_meta($course_id, 'box_state', $box_state);
        
        // Update instructor
        if ($instructor_id) {
            $instructors = [$instructor_id];
            update_post_meta($course_id, 'course_instructors', $instructors);
            cbm_update_field('course_instructors', $instructors, $course_id);
        } else {
            delete_post_meta($course_id, 'course_instructors');
            delete_post_meta($course_id, 'course_instructors');
        }
        
        // Set selling page flag
        if ($selling_page_id && $course_id == $selling_page_id) {
            update_post_meta($course_id, 'is_selling_page', '1');
        }
        
        // If sold out, set all stocks to 0
        if ($box_state === 'soldout') {
            $dates = cbm_get_field('course_dates', $course_id) ?: [];
            foreach ($dates as &$date) {
                $date['stock'] = 0;
            }
            if (!empty($dates)) {
                cbm_update_field('course_dates', $dates, $course_id);
            }
            cbm_update_field('course_stock', 0, $course_id);
        }
    }
    
    wp_send_json_success(['message' => 'Settings applied to all courses']);
}

// AJAX Handler for popup boxes (old)
add_action('wp_ajax_cbm_get_course_boxes', 'cbm_get_course_boxes');
add_action('wp_ajax_nopriv_cbm_get_course_boxes', 'cbm_get_course_boxes');

// AJAX Handler for simple popup boxes
add_action('wp_ajax_cbm_get_popup_boxes', 'cbm_get_popup_boxes_simple');
add_action('wp_ajax_nopriv_cbm_get_popup_boxes', 'cbm_get_popup_boxes_simple');

function cbm_get_popup_boxes_simple() {
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    
    error_log('[CBM Simple Popup] Request for course: ' . $course_id);
    
    if (!$course_id) {
        // Try to get from referer
        $referer = wp_get_referer();
        if ($referer) {
            $post_id = url_to_postid($referer);
            if ($post_id && get_post_type($post_id) === 'course') {
                $course_id = $post_id;
            }
        }
    }
    
    if (!$course_id) {
        wp_send_json_error('No course ID provided');
        return;
    }
    
    // Get the box using BoxFactory to check if it's EnrollBuyBox
    $box = CourseBoxManager\BoxFactory::get_box($course_id);
    
    if (!$box) {
        wp_send_json_success(['html' => '<div class="no-boxes">No boxes configured for this course.</div>']);
        return;
    }
    
    // Check if this is an EnrollBuyBox - if so, use the same rendering approach
    if ($box instanceof CourseBoxManager\Boxes\EnrollBuyBox) {
        error_log('[CBM Simple Popup] EnrollBuyBox detected, extracting individual boxes');
        
        // Render the EnrollBuyBox normally
        ob_start();
        echo $box->render();
        $full_html = ob_get_clean();
        
        // Extract only the desktop layout boxes (avoiding duplicates)
        if (strpos($full_html, 'desktop-layout') !== false) {
            // Extract just the two boxes from desktop layout
            preg_match_all('/<div class="box\s+(?:buy-course|enroll-course)[^"]*"[^>]*>.*?<\/button>\s*<\/div>/s', $full_html, $matches);
            
            if (!empty($matches[0]) && count($matches[0]) >= 2) {
                // Take the first buy box and first enroll box
                $buy_box = '';
                $enroll_box = '';
                
                foreach ($matches[0] as $match) {
                    if (empty($buy_box) && strpos($match, 'buy-course') !== false) {
                        $buy_box = $match;
                    } elseif (empty($enroll_box) && strpos($match, 'enroll-course') !== false) {
                        $enroll_box = $match;
                    }
                    
                    if ($buy_box && $enroll_box) {
                        break;
                    }
                }
                
                $html = $buy_box . "\n" . $enroll_box;
            } else {
                $html = $full_html; // Fallback to full HTML if extraction fails
            }
        } else {
            $html = $full_html;
        }
        
    } else {
        // For other box types, render normally
        ob_start();
        echo $box->render();
        $html = ob_get_clean();
    }
    
    if (empty($html)) {
        $html = '<div class="no-boxes">No boxes configured for this course.</div>';
    }
    
    wp_send_json_success(['html' => $html]);
}

function cbm_get_course_boxes() {
    // Verify nonce (make it optional for debugging)
    if (isset($_POST['nonce']) && !empty($_POST['nonce'])) {
        if (!wp_verify_nonce($_POST['nonce'], 'cbm_popup_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
    }
    
    error_log('[CBM Popup] Request received with course_id: ' . (isset($_POST['course_id']) ? $_POST['course_id'] : 'not set'));
    
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'popup';
    
    if (!$course_id) {
        // Try to get from referer URL
        $referer = wp_get_referer();
        error_log('[CBM Popup] Referer: ' . $referer);
        
        if ($referer) {
            $post_id = url_to_postid($referer);
            error_log('[CBM Popup] Post ID from referer: ' . $post_id);
            
            if ($post_id) {
                // Check if it's a course
                if (get_post_type($post_id) === 'course') {
                    $course_id = $post_id;
                    error_log('[CBM Popup] Found course ID from referer: ' . $course_id);
                } else {
                    // Check if the post has a course group
                    $terms = wp_get_post_terms($post_id, 'course_group');
                    if (!empty($terms)) {
                        $group_id = $terms[0]->term_id;
                        error_log('[CBM Popup] Found group ID from referer: ' . $group_id);
                        
                        // Get first course in the group
                        $courses = get_posts([
                            'post_type' => 'course',
                            'posts_per_page' => 1,
                            'tax_query' => [
                                [
                                    'taxonomy' => 'course_group',
                                    'field' => 'term_id',
                                    'terms' => $group_id,
                                ],
                            ],
                        ]);
                        
                        if (!empty($courses)) {
                            $course_id = $courses[0]->ID;
                            error_log('[CBM Popup] Found course ID from group: ' . $course_id);
                        }
                    }
                }
            }
        }
    }
    
    if (!$course_id) {
        error_log('[CBM Popup] No course ID found');
        wp_send_json_error('Course ID not provided');
    }
    
    // Include the popup renderer
    require_once CBM_PLUGIN_DIR . 'includes/Popup/PopupBoxRenderer.php';
    
    error_log('[CBM Popup] Rendering popup for course ID: ' . $course_id);
    
    $renderer = new \CourseBoxManager\Popup\PopupBoxRenderer();
    $html = $renderer->render($course_id, $context);
    
    error_log('[CBM Popup] HTML generated, length: ' . strlen($html));
    
    wp_send_json_success(['html' => $html, 'course_id' => $course_id]);
}

// AJAX handler for debugging date seats data
add_action('wp_ajax_cbm_debug_date_seats', 'cbm_debug_date_seats');
add_action('wp_ajax_nopriv_cbm_debug_date_seats', 'cbm_debug_date_seats');
function cbm_debug_date_seats() {
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;

    if (!$course_id) {
        wp_send_json_error('No course ID provided');
    }

    error_log('[CBM Debug] Fetching seat data for course: ' . $course_id);

    // Get dates and stock info
    $dates_data = [];
    $dates = cbm_get_field('course_dates', $course_id) ?: [];

    // Get enroll product ID
    $enroll_product_id = get_post_meta($course_id, 'enroll_product_id', true);
    if (!$enroll_product_id) {
        $enroll_product_id = get_post_meta($course_id, 'linked_product_id', true);
    }

    foreach ($dates as $date_entry) {
        if (!empty($date_entry['date'])) {
            $date = sanitize_text_field($date_entry['date']);
            $initial_stock = isset($date_entry['stock']) ? intval($date_entry['stock']) : 10;

            // Calculate sold seats
            $sold = 0;
            if ($enroll_product_id && function_exists('wc_get_orders')) {
                $orders = wc_get_orders([
                    'status' => ['wc-completed'],
                    'limit' => -1,
                ]);

                foreach ($orders as $order) {
                    foreach ($order->get_items() as $item) {
                        $item_product_id = $item->get_product_id();
                        $start_date = $item->get_meta('Start Date');

                        if ($item_product_id == $enroll_product_id &&
                            strcasecmp(trim($start_date), trim($date)) === 0) {
                            $sold += $item->get_quantity();
                        }
                    }
                }
            }

            $remaining = $initial_stock - $sold;

            $dates_data[] = [
                'date' => $date,
                'initial_stock' => $initial_stock,
                'sold' => $sold,
                'remaining' => $remaining
            ];

            error_log('[CBM Debug] Date: ' . $date . ', Stock: ' . $initial_stock . ', Sold: ' . $sold . ', Remaining: ' . $remaining);
        }
    }

    wp_send_json_success([
        'course_id' => $course_id,
        'enroll_product_id' => $enroll_product_id,
        'dates' => $dates_data,
        'box_state' => get_post_meta($course_id, 'box_state', true)
    ]);
}

// Enqueue global scripts and styles
add_action('wp_enqueue_scripts', 'cbm_enqueue_global_assets', 5); // Priority 5 to load early
function cbm_enqueue_global_assets() {
    if (!is_admin()) {
        // Register and enqueue a global configuration script
        wp_register_script(
            'cbm-global-config',
            false, // No file, just inline
            array(),
            CBM_VERSION,
            false // Load in header, not footer
        );
        
        wp_enqueue_script('cbm-global-config');
        
        // Add inline script with global configuration
        wp_add_inline_script('cbm-global-config', '
            // Initialize CBM global configuration
            window.cbm_ajax = {
                ajax_url: "' . admin_url('admin-ajax.php') . '",
                url: "' . admin_url('admin-ajax.php') . '",
                nonce: "' . wp_create_nonce('woocommerce-add-to-cart') . '",
                cart_url: "' . (function_exists('wc_get_cart_url') ? wc_get_cart_url() : '') . '",
                is_funnelkit_active: ' . (defined('FKCART_VERSION') || class_exists('FKCart') ? 'true' : 'false') . '
            };
        ');
    }
}

// Enqueue popup scripts and styles
add_action('wp_enqueue_scripts', 'cbm_enqueue_popup_assets');
function cbm_enqueue_popup_assets() {
    // Register the simple popup script (new)
    wp_register_script(
        'cbm-popup-simple',
        CBM_PLUGIN_URL . 'assets/js/cbm-popup-simple.js',
        array('jquery'),
        CBM_VERSION,
        true
    );
    
    // Register the old popup script (keeping for compatibility)
    wp_register_script(
        'cbm-popup-auto',
        CBM_PLUGIN_URL . 'assets/js/cbm-popup-auto.js',
        array('jquery', 'cbm-global-config'),
        '1.0.0',
        true
    );
    
    // Register the popup styles
    wp_register_style(
        'cbm-popup',
        CBM_PLUGIN_URL . 'assets/css/cbm-popup.css',
        array(),
        CBM_VERSION
    );
    
    // Always enqueue on frontend
    if (!is_admin()) {
        // Use the simple popup by default
        wp_enqueue_script('cbm-popup-simple');
        wp_enqueue_style('cbm-popup');

        // Add debug script only if WP_DEBUG is enabled or query param exists
        if ((defined('WP_DEBUG') && WP_DEBUG) || isset($_GET['cbm_debug'])) {
            wp_enqueue_script(
                'cbm-debug-interference',
                CBM_PLUGIN_URL . 'assets/js/cbm-debug-interference.js',
                array(),
                CBM_VERSION,
                true
            );
        }
    }
}

// Shortcode to render boxes
function course_box_manager_shortcode() {
    global $post;
    $post_id = $post ? $post->ID : 0;
    
    // Enqueue frontend styles and scripts
    wp_enqueue_style(
        'course-box-frontend',
        CBM_PLUGIN_URL . 'assets/css/frontend.css',
        array(),
        CBM_VERSION
    );
    
    wp_enqueue_script(
        'course-box-frontend',
        CBM_PLUGIN_URL . 'assets/js/frontend.js',
        array('jquery', 'cbm-global-config'),
        CBM_VERSION,
        true
    );
    
    // Localize script with AJAX data
    wp_localize_script('course-box-frontend', 'cbm_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('woocommerce-add-to-cart'),
        'cart_url' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : '',
        'is_funnelkit_active' => defined('FKCART_VERSION') || class_exists('FKCart')
    ));
    
    // Add inline script for immediate availability of selectBox function
    wp_add_inline_script('course-box-frontend', '
        // Define selectBox immediately for onclick handlers
        if (typeof window.selectBox === "undefined") {
            window.selectBox = function(element, boxType, courseId) {
                
                // If jQuery not ready, wait and retry
                if (typeof jQuery === "undefined") {
                    setTimeout(function() {
                        window.selectBox(element, boxType, courseId);
                    }, 100);
                    return;
                }
                
                var $ = jQuery;
                var $box = $(element);
                
                // Toggle selection
                if ($box.hasClass("selected")) {
                    $box.removeClass("selected");
                    $box.find(".circlecontainer").show();
                    $box.find(".circle-container").hide();
                } else {
                    // Deselect siblings
                    $box.siblings(".box").removeClass("selected");
                    $box.siblings(".box").find(".circlecontainer").show();
                    $box.siblings(".box").find(".circle-container").hide();
                    
                    // Select this box
                    $box.addClass("selected");
                    $box.find(".circlecontainer").hide();
                    $box.find(".circle-container").show();
                }
            };
        }
    ', 'before');
    
    error_log('[CBM Shortcode Debug] Starting shortcode render for post_id: ' . $post_id);
    error_log('[CBM Shortcode Debug] Post type: ' . get_post_type($post_id));
    
    // Don't render in Elementor editor
    if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->preview->is_preview_mode()) {
        return '<div style="padding: 20px; background: #f0f0f0; text-align: center;">Course Box Manager - Boxes will appear here on the live page</div>';
    }
    
    $terms = wp_get_post_terms($post_id, 'course_group');
    error_log('[CBM Shortcode Debug] Terms found: ' . print_r($terms, true));
    
    $group_id = !empty($terms) ? $terms[0]->term_id : 0;
    error_log('[CBM Shortcode Debug] Group ID: ' . $group_id);
    
    if (!$group_id) {
        error_log('[CBM Shortcode Debug] No group ID found, checking if this is a course');
        // If no group but it's a course, try to get its own data
        if (get_post_type($post_id) === 'course') {
            error_log('[CBM Shortcode Debug] This is a course, creating single box');
            // Maybe create a single box for this course
        }
    }
    
    try {
        // Add inline script to define selectBox immediately before boxes render
        $inline_script = '<script type="text/javascript">
            if (typeof window.selectBox === "undefined") {
                window.selectBox = function(element, boxType, courseId) {
                    
                    // If jQuery not ready, wait and retry
                    if (typeof jQuery === "undefined") {
                        setTimeout(function() {
                            window.selectBox(element, boxType, courseId);
                        }, 100);
                        return;
                    }
                    
                    var $ = jQuery;
                    var $box = $(element);
                    
                    // Toggle selection
                    if ($box.hasClass("selected")) {
                        $box.removeClass("selected");
                        $box.find(".circlecontainer").show();
                        $box.find(".circle-container").hide();
                    } else {
                        // Deselect siblings
                        $box.siblings(".box").removeClass("selected");
                        $box.siblings(".box").find(".circlecontainer").show();
                        $box.siblings(".box").find(".circle-container").hide();
                        
                        // Select this box
                        $box.addClass("selected");
                        $box.find(".circlecontainer").hide();
                        $box.find(".circle-container").show();
                    }
                };
            } else {
            }
        </script>';
        
        $output = $inline_script . CourseBoxManager\BoxRenderer::render_boxes_for_group($group_id, $post_id);
        error_log('[CBM Shortcode Debug] Output length: ' . strlen($output));
        
        // Add debug info for admins
        if (current_user_can('manage_options') && isset($_GET['cbm_debug'])) {
            $debug_info = '<div style="background: #f0f0f0; padding: 20px; margin: 20px 0; border: 2px solid #333;">';
            $debug_info .= '<h3>Course Box Manager Debug Info</h3>';
            $debug_info .= '<p><strong>Post ID:</strong> ' . $post_id . '</p>';
            $debug_info .= '<p><strong>Post Type:</strong> ' . get_post_type($post_id) . '</p>';
            $debug_info .= '<p><strong>Group ID:</strong> ' . $group_id . '</p>';
            $debug_info .= '<p><strong>Box State:</strong> ' . get_post_meta($post_id, 'box_state', true) . '</p>';
            $debug_info .= '<p><strong>Product ID:</strong> ' . get_post_meta($post_id, 'linked_product_id', true) . '</p>';
            $debug_info .= '<p><strong>Dates:</strong> <pre>' . print_r(cbm_get_field('course_dates', $post_id), true) . '</pre></p>';
            $debug_info .= '<p><strong>Output Empty?</strong> ' . (empty($output) ? 'YES' : 'NO') . '</p>';
            $debug_info .= '</div>';
            $output = $debug_info . $output;
        }
        
        return $output;
    } catch (\Exception $e) {
        error_log('[CBM Shortcode Debug] Exception: ' . $e->getMessage());
        if (current_user_can('manage_options')) {
            return '<div class="notice notice-error"><p>Course Box Manager Error: ' . esc_html($e->getMessage()) . '</p></div>';
        }
        
        return '<!-- Course Box Manager Error -->';
    }
}

// Add start date to cart item data
add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id, $variation_id) {
    if (isset($_POST['start_date']) && !empty($_POST['start_date'])) {
        $cart_item_data['start_date'] = sanitize_text_field($_POST['start_date']);
    }
    return $cart_item_data;
}, 10, 3);

// Save start date to order item meta
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values) {
    if (isset($values['start_date']) && !empty($values['start_date'])) {
        $item->add_meta_data('Start Date', $values['start_date'], true);
    }
}, 10, 3);

// Display start date in admin order details
add_action('woocommerce_order_item_meta_end', function ($item_id, $item, $order) {
    $start_date = $item->get_meta('Start Date');
    if ($start_date) {
        echo '<p><strong>Start Date:</strong> ' . esc_html($start_date) . '</p>';
    }
}, 10, 3);

// Add start date to customer order email
add_action('woocommerce_email_order_meta', function ($order) {
    foreach ($order->get_items() as $item_id => $item) {
        $start_date = $item->get_meta('Start Date');
        if ($start_date) {
            echo '<p><strong>Start Date:</strong> ' . esc_html($start_date) . '</p>';
        }
    }
}, 10, 1);

// Shortcode registration now handled in frontend/shortcode.php
// add_shortcode('course_box_manager', 'course_box_manager_shortcode');

// Sync course creation with product only (no selling page)
add_action('save_post_course', 'sync_course_to_product_and_page', 10, 3);
function sync_course_to_product_and_page($post_id, $post, $update) {
    if (wp_is_post_revision($post_id)) return;

    $terms = wp_get_post_terms($post_id, 'course_group');
    $group_id = !empty($terms) ? $terms[0]->term_id : 0;

    // Create WooCommerce product if not exists
    $product_id = get_post_meta($post_id, 'linked_product_id', true);
    if (!$product_id) {
        $product = new WC_Product_Simple();
        $product->set_name($post->post_title);
        $product->set_status('publish');
        $product->set_virtual(true);
        $product->set_price(cbm_get_field('course_price', $post_id) ?: 749.99);
        $product_id = $product->save();
        if ($group_id) {
            wp_set_post_terms($product_id, [$group_id], 'course_group');
        }
        update_post_meta($post_id, 'linked_product_id', $product_id);
    }
}

// Sync instructors bidirectionally
add_action('acf/save_post', 'sync_course_instructors', 20);
function sync_course_instructors($post_id) {
    if (get_post_type($post_id) !== 'course') return;
    $instructors = cbm_get_field('course_instructors', $post_id) ?: [];
    update_post_meta($post_id, 'course_instructors', $instructors);

    // Clear existing instructor courses
    $all_instructors = get_posts(['post_type' => 'instructor', 'posts_per_page' => -1, 'fields' => 'ids']);
    foreach ($all_instructors as $instructor_id) {
        $courses = get_post_meta($instructor_id, 'instructor_courses', true) ?: [];
        $courses = array_filter($courses, function($id) use ($post_id) { return $id != $post_id; });
        update_post_meta($instructor_id, 'instructor_courses', $courses);
    }

    // Update instructor meta
    foreach ($instructors as $instructor_id) {
        $courses = get_post_meta($instructor_id, 'instructor_courses', true) ?: [];
        if (!in_array($post_id, $courses)) {
            $courses[] = $post_id;
            update_post_meta($instructor_id, 'instructor_courses', $courses);
        }
    }
}

// Initialize Export/Import functionality
if (is_admin()) {
    add_action('init', function() {
        // Load Import/Export functionality
        if (file_exists(CBM_PLUGIN_DIR . 'includes/ImportExport.php')) {
            require_once CBM_PLUGIN_DIR . 'includes/ImportExport.php';
            \CourseBoxManager\ImportExport::init();
        }
        
        // Load Export/Import classes if they exist
        if (file_exists(CBM_PLUGIN_DIR . 'includes/ExportImport/CourseExporter.php')) {
            require_once CBM_PLUGIN_DIR . 'includes/ExportImport/CourseExporter.php';
        }
        if (file_exists(CBM_PLUGIN_DIR . 'includes/ExportImport/CourseImporter.php')) {
            require_once CBM_PLUGIN_DIR . 'includes/ExportImport/CourseImporter.php';
        }
    });
}
?>
