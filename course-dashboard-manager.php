<?php
/*
 * Plugin Name: Course Box Manager
 * Description: A comprehensive plugin to manage and display selectable boxes for course post types with dashboard control, countdowns, start date selection, and WooCommerce integration.
 * Version: 1.5.1
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

// Helper function to safely get ACF field
function cbm_cbm_get_field($field, $post_id = false, $default = null) {
    if (function_exists('get_field')) {
        $value = cbm_get_field($field, $post_id);
        return $value !== false ? $value : $default;
    }
    return $default;
}

// Helper function to safely update ACF field
function cbm_cbm_update_field($field, $value, $post_id = false) {
    if (function_exists('update_field')) {
        return cbm_update_field($field, $value, $post_id);
    }
    return false;
}

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

// Initialize the seats remaining functionality
add_action('init', function() {
    new CourseBoxManager\SeatsRemaining();
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
    
    // Course Boxes submenu - disabled (commented out)
    /*
    add_submenu_page(
        'course-box-tables',
        'Course Boxes',
        'Course Boxes',
        'edit_posts',
        'course-box-manager',
        'course_box_manager_page'
    );
    */
}

// Helper function to calculate seats sold for a course
function calculate_seats_sold($product_id, $date_text = null) {
    if (!$product_id) {
        return 0;
    }
    
    $args = [
        'status' => ['wc-completed'],
        'limit' => -1,
        'date_query' => ['after' => '2020-01-01'],
    ];
    
    // Check if WooCommerce is available
    if (!function_exists('wc_get_orders')) {
        return 0;
    }
    
    $orders = wc_get_orders($args);
    $sales_count = 0;
    $matching_orders = [];
    
    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            if ($item->get_product_id() == $product_id) {
                if ($date_text) {
                    $start_date = $item->get_meta('Start Date');
                    // Compare as text strings, case-insensitive
                    if (strcasecmp(trim($start_date), trim($date_text)) === 0) {
                        $sales_count += $item->get_quantity();
                        $matching_orders[] = $order->get_id();
                    }
                } else {
                    $sales_count += $item->get_quantity();
                    $matching_orders[] = $order->get_id();
                }
            }
        }
    }
    
    return $sales_count;
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
    
    // Handle group deletion
    if (isset($_GET['action']) && $_GET['action'] === 'delete_group' && isset($_GET['group_id']) && isset($_GET['_wpnonce'])) {
        $group_id = intval($_GET['group_id']);
        
        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_group_' . $group_id)) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_die('You do not have permission to delete course groups');
        }
        
        // Check if group has courses
        $courses = get_posts([
            'post_type' => 'course',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'tax_query' => [
                [
                    'taxonomy' => 'course_group',
                    'field' => 'term_id',
                    'terms' => $group_id,
                ],
            ],
        ]);
        
        if (!empty($courses)) {
            wp_die('Cannot delete a group that contains courses');
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
                                <?php if (count($courses_in_group) === 0) : ?>
                                    <a href="<?php echo wp_nonce_url('?page=course-box-tables&action=delete_group&group_id=' . $group->term_id, 'delete_group_' . $group->term_id); ?>" 
                                       class="button button-link-delete" 
                                       onclick="return confirm('Are you sure you want to delete this group?');">
                                        Delete
                                    </a>
                                <?php endif; ?>
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
                            <option value="countdown" <?php selected($default_box_state, 'countdown'); ?>>Countdown Box</option>
                            <option value="waitlist" <?php selected($default_box_state, 'waitlist'); ?>>Waitlist</option>
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
                            // Get all courses in the group for selling page selection
                            foreach ($courses as $course) {
                                echo '<option value="' . esc_attr($course->ID) . '"' . selected($selling_page_id, $course->ID, false) . '>' . 
                                     esc_html($course->post_title) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <button id="apply-group-settings" class="button button-primary">Apply to All Courses</button>
                    </div>
                </div>
            </div>
            
            <!-- Courses Table -->
            <div id="table-container">
                <button id="add-new-row" class="button button-primary" style="margin-bottom: 10px;">+ Add New Row</button>
                <table class="wp-list-table widefat fixed striped" id="courses-table" style="margin-top: 10px;">
                    <thead id="table-header">
                        <!-- Dynamic header based on box state -->
                    </thead>
                    <tbody id="table-body">
                        <!-- Dynamic content based on box state -->
                    </tbody>
                </table>
            </div>
            
            <!-- Hidden data for JavaScript -->
            <script>
                var coursesData = <?php 
                    $courses_json = [];
                    $all_products = [];
                    if (function_exists('wc_get_products')) {
                        $products = wc_get_products(['limit' => -1, 'orderby' => 'title', 'order' => 'ASC', 'status' => 'publish']);
                        foreach ($products as $product) {
                            $all_products[$product->get_id()] = $product->get_name();
                        }
                    }
                    
                    foreach ($courses as $course) {
                        $course_id = $course->ID;
                        $product_id = get_post_meta($course_id, 'linked_product_id', true);
                        $launch_date = $product_id ? get_post_meta($product_id, '_launch_date', true) : '';
                        $courses_json[] = [
                            'id' => $course_id,
                            'title' => $course->post_title,
                            'product_id' => $product_id,
                            'launch_date' => $launch_date,
                            'dates' => cbm_get_field('course_dates', $course_id) ?: [],
                            'stock' => cbm_get_field('course_stock', $course_id) ?: 0
                        ];
                    }
                    echo json_encode($courses_json);
                ?>;
                var allProducts = <?php echo json_encode($all_products); ?>;
                var groupId = <?php echo $group_id; ?>;
            </script>
        <?php endif; ?>
        
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
        </style>
        
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                let currentBoxState = document.getElementById('group-box-state').value;
                let rowCounter = 0;
                
                // Function to render table based on box state
                function renderTable(boxState) {
                    const tableHeader = document.getElementById('table-header');
                    const tableBody = document.getElementById('table-body');
                    const addButton = document.getElementById('add-new-row');
                    const tableContainer = document.getElementById('table-container');
                    
                    // Clear existing content
                    tableHeader.innerHTML = '';
                    tableBody.innerHTML = '';
                    
                    // Always show table container
                    tableContainer.style.display = 'block';
                    
                    // Show/hide add button based on state (only for enroll-course)
                    if (boxState === 'enroll-course') {
                        addButton.style.display = 'inline-block';
                    } else {
                        addButton.style.display = 'none';
                    }
                    
                    // Build header based on box state
                    let headerHTML = '<tr>';
                    if (boxState === 'enroll-course') {
                        headerHTML += '<th style="width: 15%;">Date</th>';
                        headerHTML += '<th style="width: 20%;">Associated Product</th>';
                        headerHTML += '<th style="width: 12%;">Total Seats</th>';
                        headerHTML += '<th style="width: 10%;">Sold</th>';
                        headerHTML += '<th style="width: 12%;">Available</th>';
                        headerHTML += '<th style="width: 18%;">Button Text</th>';
                        headerHTML += '<th style="width: 13%;">Actions</th>';
                    } else if (boxState === 'buy-course') {
                        headerHTML += '<th style="width: 30%;">Associated Product</th>';
                        headerHTML += '<th style="width: 15%;">Total Seats</th>';
                        headerHTML += '<th style="width: 15%;">Available</th>';
                        headerHTML += '<th style="width: 25%;">Button Text</th>';
                        headerHTML += '<th style="width: 15%;">Actions</th>';
                    } else if (boxState === 'countdown') {
                        headerHTML += '<th style="width: 12%;">Date</th>';
                        headerHTML += '<th style="width: 18%;">Associated Product</th>';
                        headerHTML += '<th style="width: 18%;">Launch Date & Time</th>';
                        headerHTML += '<th style="width: 10%;">Total Seats</th>';
                        headerHTML += '<th style="width: 8%;">Sold</th>';
                        headerHTML += '<th style="width: 10%;">Available</th>';
                        headerHTML += '<th style="width: 14%;">Button Text</th>';
                        headerHTML += '<th style="width: 10%;">Actions</th>';
                    } else if (boxState === 'waitlist') {
                        headerHTML += '<th style="width: 30%;">Associated Product</th>';
                        headerHTML += '<th style="width: 30%;">Button Text</th>';
                        headerHTML += '<th style="width: 40%;">Actions</th>';
                    } else if (boxState === 'soldout') {
                        headerHTML += '<th style="width: 15%;">Date</th>';
                        headerHTML += '<th style="width: 20%;">Associated Product</th>';
                        headerHTML += '<th style="width: 12%;">Total Seats</th>';
                        headerHTML += '<th style="width: 10%;">Sold</th>';
                        headerHTML += '<th style="width: 12%;">Available</th>';
                        headerHTML += '<th style="width: 18%;">Button Text</th>';
                        headerHTML += '<th style="width: 13%;">Actions</th>';
                    }
                    headerHTML += '</tr>';
                    tableHeader.innerHTML = headerHTML;
                    
                    // Build table rows based on box state
                    if (boxState === 'enroll-course') {
                        // Multiple rows allowed for enroll-course
                        coursesData.forEach(course => {
                            if (course.dates && course.dates.length > 0) {
                                course.dates.forEach((dateInfo, index) => {
                                    addTableRow(course, {date: dateInfo, index: index}, boxState);
                                });
                            } else {
                                addTableRow(course, null, boxState);
                            }
                        });
                    } else {
                        // Single row for all other states
                        const firstCourse = coursesData[0] || {id: 0, product_id: '', stock: 20};
                        const firstDate = firstCourse.dates && firstCourse.dates.length > 0 ? 
                                         {date: firstCourse.dates[0], index: 0} : null;
                        addTableRow(firstCourse, firstDate, boxState);
                    }
                }
                
                // Function to add a table row
                function addTableRow(course, dateInfo, boxState) {
                    const tableBody = document.getElementById('table-body');
                    const row = document.createElement('tr');
                    row.className = 'course-row editable-row';
                    row.dataset.courseId = course.id;
                    
                    if (dateInfo) {
                        row.dataset.dateIndex = dateInfo.index;
                    } else {
                        row.dataset.dateIndex = 'new';
                    }
                    
                    let rowHTML = '';
                    const stock = boxState === 'soldout' ? 0 : (dateInfo && dateInfo.date.stock ? dateInfo.date.stock : course.stock || 20);
                    const sold = 0; // Will be calculated server-side
                    const available = Math.max(0, stock - sold);
                    const buttonText = dateInfo && dateInfo.date.button_text ? dateInfo.date.button_text : 
                                      (boxState === 'waitlist' ? 'Join Waitlist' : 'Enroll Now');
                    
                    if (boxState === 'enroll-course') {
                        rowHTML += `<td><input type="text" class="inline-edit-date" value="${dateInfo ? dateInfo.date.date : ''}" placeholder="YYYY-MM-DD" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>${buildProductSelect(course.product_id)}</td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-stock" value="${stock}" min="0" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="sold-count">${sold}</span></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="available-count" style="color: ${available <= 5 ? '#d54e21' : (available <= 10 ? '#f0ad4e' : '#46b450')}; font-weight: bold;">${available}</span></td>`;
                        rowHTML += `<td><input type="text" class="inline-edit-button-text" value="${buttonText}" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>
                            <button class="button button-small button-primary save-row">Save</button>
                            <button class="button button-small delete-row" style="background: #d54e21; color: white; margin-left: 5px;">×</button>
                            <span class="save-status" style="margin-left: 5px;"></span>
                        </td>`;
                    } else if (boxState === 'soldout') {
                        rowHTML += `<td><input type="text" class="inline-edit-date" value="${dateInfo ? dateInfo.date.date : ''}" placeholder="YYYY-MM-DD" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>${buildProductSelect(course.product_id)}</td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-stock" value="0" min="0" readonly style="width: 100%; padding: 3px; background: #f0f0f0;"></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="sold-count">${sold}</span></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="available-count" style="color: #d54e21; font-weight: bold;">0</span></td>`;
                        rowHTML += `<td><input type="text" class="inline-edit-button-text" value="Sold Out" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>
                            <button class="button button-small button-primary save-row">Save</button>
                            <button class="button button-small delete-row" style="background: #d54e21; color: white; margin-left: 5px;">×</button>
                            <span class="save-status" style="margin-left: 5px;"></span>
                        </td>`;
                    } else if (boxState === 'buy-course') {
                        rowHTML += `<td>${buildProductSelect(course.product_id)}</td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-stock" value="${stock}" min="0" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="available-count" style="color: ${available <= 5 ? '#d54e21' : (available <= 10 ? '#f0ad4e' : '#46b450')}; font-weight: bold;">${available}</span></td>`;
                        rowHTML += `<td><input type="text" class="inline-edit-button-text" value="Buy Now" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>
                            <button class="button button-small button-primary save-row">Save</button>
                            <button class="button button-small delete-row" style="background: #d54e21; color: white; margin-left: 5px;">×</button>
                            <span class="save-status" style="margin-left: 5px;"></span>
                        </td>`;
                    } else if (boxState === 'countdown') {
                        rowHTML += `<td><input type="text" class="inline-edit-date" value="${dateInfo ? dateInfo.date.date : ''}" placeholder="YYYY-MM-DD" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>${buildProductSelect(course.product_id)}</td>`;
                        rowHTML += `<td><input type="datetime-local" class="inline-edit-launch-date" value="${course.launch_date || ''}" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-stock" value="${stock}" min="0" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="sold-count">${sold}</span></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="available-count" style="color: ${available <= 5 ? '#d54e21' : (available <= 10 ? '#f0ad4e' : '#46b450')}; font-weight: bold;">${available}</span></td>`;
                        rowHTML += `<td><input type="text" class="inline-edit-button-text" value="${buttonText}" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>
                            <button class="button button-small button-primary save-row">Save</button>
                            <button class="button button-small delete-row" style="background: #d54e21; color: white; margin-left: 5px;">×</button>
                            <span class="save-status" style="margin-left: 5px;"></span>
                        </td>`;
                    } else if (boxState === 'waitlist') {
                        rowHTML += `<td>${buildProductSelect(course.product_id)}</td>`;
                        rowHTML += `<td><input type="text" class="inline-edit-button-text" value="Join Waitlist" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>
                            <button class="button button-small button-primary save-row">Save</button>
                            <button class="button button-small delete-row" style="background: #d54e21; color: white; margin-left: 5px;">×</button>
                            <span class="save-status" style="margin-left: 5px;"></span>
                        </td>`;
                    }
                    
                    row.innerHTML = rowHTML;
                    tableBody.appendChild(row);
                    attachRowEventListeners(row);
                }
                
                // Build product select dropdown
                function buildProductSelect(selectedId) {
                    let html = '<select class="inline-edit-product" style="width: 100%; padding: 3px;"><option value="">None</option>';
                    for (let id in allProducts) {
                        html += `<option value="${id}" ${selectedId == id ? 'selected' : ''}>${allProducts[id]}</option>`;
                    }
                    html += '</select>';
                    return html;
                }
                
                // Attach event listeners to row
                function attachRowEventListeners(row) {
                    // Track changes
                    row.querySelectorAll('input, select').forEach(field => {
                        field.addEventListener('change', function() {
                            row.classList.add('has-changes');
                            
                            // Update available when stock changes
                            if (this.classList.contains('inline-edit-stock')) {
                                const soldCount = parseInt(row.querySelector('.sold-count')?.textContent) || 0;
                                const newStock = parseInt(this.value) || 0;
                                const available = Math.max(0, newStock - soldCount);
                                const availableSpan = row.querySelector('.available-count');
                                if (availableSpan) {
                                    availableSpan.textContent = available;
                                    availableSpan.style.color = available <= 5 ? '#d54e21' : (available <= 10 ? '#f0ad4e' : '#46b450');
                                }
                            }
                        });
                    });
                    
                    // Save button
                    const saveBtn = row.querySelector('.save-row');
                    if (saveBtn) {
                        saveBtn.addEventListener('click', function() {
                            saveRow(row);
                        });
                    }
                    
                    // Delete button
                    const deleteBtn = row.querySelector('.delete-row');
                    if (deleteBtn) {
                        deleteBtn.addEventListener('click', function() {
                            if (confirm('Delete this row?')) {
                                deleteRow(row);
                            }
                        });
                    }
                }
                
                // Save row function
                function saveRow(row) {
                    const courseId = row.dataset.courseId;
                    const dateIndex = row.dataset.dateIndex;
                    const statusSpan = row.querySelector('.save-status');
                    const boxState = document.getElementById('group-box-state').value;
                    const instructorId = document.getElementById('group-instructor').value;
                    
                    let data = {
                        course_id: courseId,
                        date_index: dateIndex,
                        box_state: boxState,
                        instructor_id: instructorId,
                        product_id: row.querySelector('.inline-edit-product')?.value || '',
                        stock: row.querySelector('.inline-edit-stock')?.value || 0,
                        button_text: row.querySelector('.inline-edit-button-text')?.value || 'Enroll Now'
                    };
                    
                    if (boxState === 'enroll-course' || boxState === 'soldout' || boxState === 'countdown') {
                        data.date = row.querySelector('.inline-edit-date')?.value || '';
                        if (!data.date) {
                            alert('Please enter a date');
                            return;
                        }
                    }
                    
                    if (boxState === 'countdown') {
                        data.launch_date = row.querySelector('.inline-edit-launch-date')?.value || '';
                    }
                    
                    statusSpan.className = 'save-status saving';
                    statusSpan.textContent = 'Saving...';
                    
                    fetch(ajaxurl + '?action=save_table_row_data', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: Object.keys(data).map(key => `${key}=${encodeURIComponent(data[key])}`).join('&') + 
                              '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            statusSpan.className = 'save-status success';
                            statusSpan.textContent = '✓';
                            row.classList.remove('has-changes');
                            setTimeout(() => { statusSpan.textContent = ''; }, 3000);
                            
                            if (dateIndex === 'new') {
                                setTimeout(() => location.reload(), 1000);
                            }
                        } else {
                            statusSpan.className = 'save-status error';
                            statusSpan.textContent = '✗ ' + (result.data || 'Error');
                        }
                    });
                }
                
                // Delete row function
                function deleteRow(row) {
                    const courseId = row.dataset.courseId;
                    const dateIndex = row.dataset.dateIndex;
                    
                    if (dateIndex === 'new') {
                        row.remove();
                        return;
                    }
                    
                    fetch(ajaxurl + '?action=delete_table_row', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'course_id=' + courseId + '&date_index=' + dateIndex +
                              '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            row.remove();
                        } else {
                            alert('Error deleting row');
                        }
                    });
                }
                
                // Add new row button
                document.getElementById('add-new-row').addEventListener('click', function() {
                    const firstCourse = coursesData[0] || {id: 0, product_id: '', stock: 20};
                    addTableRow(firstCourse, null, currentBoxState);
                });
                
                // Box state change handler
                document.getElementById('group-box-state').addEventListener('change', function() {
                    currentBoxState = this.value;
                    renderTable(currentBoxState);
                    
                    // Auto-set stock to 0 for sold out
                    if (currentBoxState === 'soldout') {
                        document.querySelectorAll('.inline-edit-stock').forEach(input => {
                            input.value = 0;
                            input.readOnly = true;
                            input.dispatchEvent(new Event('change'));
                        });
                    }
                });
                
                // Apply group settings button
                document.getElementById('apply-group-settings').addEventListener('click', function() {
                    const boxState = document.getElementById('group-box-state').value;
                    const instructorId = document.getElementById('group-instructor').value;
                    const sellingPageId = document.getElementById('group-selling-page').value;
                    
                    if (!confirm('Apply these settings to all courses in the group?')) return;
                    
                    fetch(ajaxurl + '?action=apply_group_settings', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'group_id=' + groupId + 
                              '&box_state=' + boxState +
                              '&instructor_id=' + instructorId +
                              '&selling_page_id=' + sellingPageId +
                              '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            alert('Settings applied successfully');
                            location.reload();
                        } else {
                            alert('Error applying settings');
                        }
                    });
                });
                
                // Initial render
                renderTable(currentBoxState);
            });
        </script>
        
        <?php endif; // End of group detail view ?>
        
        <?php if (!isset($_GET['group_id'])) : ?>
        <!-- JavaScript for Add New Group form -->
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
                        <th style="width: 25%;">Course</th>
                        <th style="width: 20%;">Instructors</th>
                        <th style="width: 15%;">Box State</th>
                        <th style="width: 25%;">Dates (Stock)</th>
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
                                <option value="waitlist" <?php echo $box_state === 'waitlist' ? 'selected' : ''; ?>>Waitlist</option>
                                <option value="soldout" <?php echo $box_state === 'soldout' ? 'selected' : ''; ?>>Sold Out</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
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

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const addCourseModal = document.getElementById('add-course-modal');
                const addCourseGroupModal = document.getElementById('add-course-group-modal');
                const closeButtons = document.getElementsByClassName('modal-close');

                // Open Add Course Modal
                document.querySelectorAll('.add-course').forEach(button => {
                    button.addEventListener('click', function() {
                        const groupId = this.getAttribute('data-group-id');
                        if (groupId) {
                            document.getElementById('course-group').value = groupId;
                        }
                        addCourseModal.style.display = 'block';
                    });
                });

                // Open Add Course Group Modal
                document.querySelectorAll('.add-course-group').forEach(button => {
                    button.addEventListener('click', function() {
                        addCourseGroupModal.style.display = 'block';
                    });
                });

                // Close Modals
                Array.from(closeButtons).forEach(button => {
                    button.addEventListener('click', function() {
                        addCourseModal.style.display = 'none';
                        addCourseGroupModal.style.display = 'none';
                    });
                });
                window.addEventListener('click', function(event) {
                    if (event.target === addCourseModal || event.target === addCourseGroupModal) {
                        addCourseModal.style.display = 'none';
                        addCourseGroupModal.style.display = 'none';
                    }
                });

                // Save Course Assignment
                document.getElementById('save-course-assignment').addEventListener('click', function() {
                    const courseId = document.getElementById('course-select').value;
                    const groupId = document.getElementById('course-group').value;
                    const instructors = Array.from(document.getElementById('course-instructors-select').selectedOptions).map(option => option.value);
                    
                    if (!courseId) {
                        alert('Please select a course.');
                        return;
                    }
                    
                    fetch(ajaxurl + '?action=assign_course_to_group', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'course_id=' + courseId + '&group_id=' + groupId + '&instructors=' + encodeURIComponent(JSON.stringify(instructors)) + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Course added to group successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + data.data);
                        }
                    });
                });

                // Save New Course Group
                document.getElementById('save-new-course-group').addEventListener('click', function() {
                    const groupName = document.getElementById('course-group-name').value;
                    if (!groupName.trim()) {
                        alert('Please enter a group name.');
                        return;
                    }
                    fetch(ajaxurl + '?action=create_new_course_group', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'group_name=' + encodeURIComponent(groupName) + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Course group created successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + data.data);
                        }
                    });
                });

                // Save Course Settings
                document.querySelectorAll('.save-course-settings').forEach(button => {
                    button.addEventListener('click', function() {
                        const courseId = this.getAttribute('data-course-id');
                        const groupId = document.querySelector(`#course-group[data-course-id="${courseId}"]`).value;
                        const boxState = document.querySelector(`.box-state-select[data-course-id="${courseId}"]`).value;
                        const instructors = Array.from(document.querySelector(`.instructor-select[data-course-id="${courseId}"]`).selectedOptions).map(option => option.value);
                        const stock = '';
                        const linkedProductElement = document.querySelector(`#linked-product[data-course-id="${courseId}"]`);
                        const linkedProductId = linkedProductElement ? linkedProductElement.value : 0;
                        const dateElements = document.querySelectorAll(`.date-list[data-course-id="${courseId}"] .date-stock-row`);
                        const dates = [];
                        dateElements.forEach(row => {
                            const dateInput = row.querySelector('.course-date');
                            const stockInput = row.querySelector('.course-stock');
                            const buttonTextInput = row.querySelector('.course-button-text');
                            if (dateInput && dateInput.value.trim() !== '') {
                                dates.push({
                                    date: dateInput.value.trim(),
                                    stock: stockInput ? stockInput.value : stock,
                                    button_text: buttonTextInput ? buttonTextInput.value.trim() : 'Enroll Now'
                                });
                            }
                        });
                        const sellingPageId = document.querySelector(`#selling-page[data-course-id="${courseId}"]`).value;
                        
                        fetch(ajaxurl + '?action=save_course_settings', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'course_id=' + courseId + '&group_id=' + groupId + '&box_state=' + boxState + '&instructors=' + encodeURIComponent(JSON.stringify(instructors)) + '&stock=' + stock + '&dates=' + encodeURIComponent(JSON.stringify(dates)) + '&selling_page_id=' + sellingPageId + '&linked_product_id=' + linkedProductId + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Update box state dropdown if it changed
                                if (data.data && data.data.updated_box_state) {
                                    const boxStateSelect = document.querySelector(`.box-state-select[data-course-id="${courseId}"]`);
                                    if (boxStateSelect) {
                                        boxStateSelect.value = data.data.updated_box_state;
                                        
                                        // Show notification if state was auto-changed to waitlist
                                        if (data.data.updated_box_state === 'waitlist' && boxState === 'enroll-course' && dates.length === 0) {
                                            const notification = document.createElement('div');
                                            notification.style.cssText = 'background: #f0ad4e; color: #333; padding: 10px; margin: 10px 0; border-radius: 4px;';
                                            notification.textContent = '⚠️ Box state automatically changed to Waitlist because no dates are configured.';
                                            boxStateSelect.parentElement.appendChild(notification);
                                            setTimeout(() => notification.remove(), 5000);
                                        }
                                    }
                                }
                                
                                // Show success message without redirecting
                                const button = document.querySelector(`.save-course-settings[data-course-id="${courseId}"]`);
                                
                                // Reset button appearance
                                button.style.backgroundColor = '';
                                button.style.animation = '';
                                button.textContent = 'Save Settings';
                                
                                const successMsg = document.createElement('span');
                                successMsg.style.cssText = 'color: #46b450; margin-left: 10px; font-weight: bold;';
                                successMsg.textContent = '✓ Settings saved successfully!';
                                button.parentElement.appendChild(successMsg);
                                
                                // Remove the message after 3 seconds
                                setTimeout(() => {
                                    successMsg.remove();
                                }, 3000);
                            } else {
                                alert('Error: ' + data.data);
                            }
                        });
                    });
                });

                // Remove Course from Group
                document.querySelectorAll('.remove-from-group').forEach(button => {
                    button.addEventListener('click', function() {
                        if (!confirm('Are you sure you want to remove this course from the group?')) return;
                        const courseId = this.getAttribute('data-course-id');
                        const groupId = this.getAttribute('data-group-id');
                        fetch(ajaxurl + '?action=remove_course_from_group', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'course_id=' + courseId + '&group_id=' + groupId + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Course removed from group successfully!');
                                location.reload();
                            } else {
                                alert('Error: ' + data.data);
                            }
                        });
                    });
                });
                
                // Delete Course (only used elsewhere, not in group view)
                document.querySelectorAll('.delete-course').forEach(button => {
                    button.addEventListener('click', function() {
                        if (!confirm('Are you sure you want to delete this course permanently?')) return;
                        const courseId = this.getAttribute('data-course-id');
                        fetch(ajaxurl + '?action=delete_course', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'course_id=' + courseId + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Course deleted successfully!');
                                location.reload();
                            } else {
                                alert('Error: ' + data.data);
                            }
                        });
                    });
                });

                // Add/Remove/Edit Dates
                document.querySelectorAll('.date-list').forEach(container => {
                    const courseId = container.getAttribute('data-course-id');
                    const addDateButton = container.querySelector('.add-date');
                    const defaultStock = 20;
                    
                    // Add new date functionality
                    if (addDateButton) {
                        addDateButton.addEventListener('click', function() {
                            const dateList = container;
                            const existingRows = dateList.querySelectorAll('.date-stock-row');
                            const index = existingRows.length;
                            const dateHeader = dateList.querySelector('.date-header');
                            
                            const wrapper = document.createElement('div');
                            wrapper.className = 'date-stock-row';
                            wrapper.style.cssText = 'display: flex; gap: 10px; margin-bottom: 8px; padding: 8px; background: #fff; border: 1px solid #ddd; border-radius: 4px; align-items: center;';
                            
                            // Date input
                            const newDateInput = document.createElement('input');
                            newDateInput.type = 'text';
                            newDateInput.className = 'course-date';
                            newDateInput.setAttribute('data-index', index);
                            newDateInput.placeholder = 'YYYY-MM-DD';
                            newDateInput.style.cssText = 'width: 120px; padding: 5px;';
                            
                            // Stock input
                            const newStockInput = document.createElement('input');
                            newStockInput.type = 'number';
                            newStockInput.className = 'course-stock';
                            newStockInput.setAttribute('data-index', index);
                            newStockInput.value = defaultStock;
                            newStockInput.min = '0';
                            newStockInput.style.cssText = 'width: 80px; padding: 5px;';
                            
                            // Sold span
                            const soldSpan = document.createElement('span');
                            soldSpan.style.cssText = 'width: 80px; text-align: center; color: #666;';
                            soldSpan.textContent = '0';
                            
                            // Available span
                            const availableSpan = document.createElement('span');
                            availableSpan.style.cssText = 'width: 80px; text-align: center; font-weight: bold; color: #46b450;';
                            availableSpan.textContent = defaultStock;
                            
                            // Button text input
                            const buttonTextInput = document.createElement('input');
                            buttonTextInput.type = 'text';
                            buttonTextInput.className = 'course-button-text';
                            buttonTextInput.setAttribute('data-index', index);
                            buttonTextInput.placeholder = 'Enroll Now';
                            buttonTextInput.value = 'Enroll Now';
                            buttonTextInput.style.cssText = 'width: 150px; padding: 5px;';
                            
                            // Actions div
                            const actionsDiv = document.createElement('div');
                            actionsDiv.style.width = '100px';
                            
                            const editButton = document.createElement('button');
                            editButton.className = 'button button-small edit-seats';
                            editButton.setAttribute('data-index', index);
                            editButton.textContent = 'Edit';
                            editButton.style.marginRight = '5px';
                            
                            const removeButton = document.createElement('button');
                            removeButton.className = 'button button-small remove-date';
                            removeButton.setAttribute('data-index', index);
                            removeButton.textContent = '×';
                            removeButton.style.cssText = 'background: #d54e21; color: white;';
                            removeButton.addEventListener('click', function() {
                                wrapper.remove();
                                updateSummary();
                                
                                // Show save reminder
                                const saveButton = document.querySelector(`.save-course-settings[data-course-id="${courseId}"]`);
                                if (saveButton) {
                                    saveButton.style.backgroundColor = '#f0ad4e';
                                    saveButton.textContent = 'Save Settings (Changes Pending)';
                                    saveButton.style.animation = 'pulse 1s infinite';
                                }
                            });
                            
                            // Listen for stock changes to update available seats
                            newStockInput.addEventListener('input', function() {
                                availableSpan.textContent = this.value;
                                updateAvailableColor(availableSpan, parseInt(this.value));
                                updateSummary();
                            });
                            
                            actionsDiv.appendChild(editButton);
                            actionsDiv.appendChild(removeButton);
                            
                            wrapper.appendChild(newDateInput);
                            wrapper.appendChild(newStockInput);
                            wrapper.appendChild(soldSpan);
                            wrapper.appendChild(availableSpan);
                            wrapper.appendChild(buttonTextInput);
                            wrapper.appendChild(actionsDiv);
                            
                            // Insert before the add button
                            const summaryDiv = dateList.querySelector('div[style*="Summary"]');
                            if (summaryDiv) {
                                dateList.insertBefore(wrapper, summaryDiv.previousElementSibling);
                            } else {
                                dateList.insertBefore(wrapper, addDateButton);
                            }
                            
                            // Focus on the new date input
                            newDateInput.focus();
                        });
                    }
                    
                    // Remove date functionality
                    container.querySelectorAll('.remove-date').forEach(button => {
                        button.addEventListener('click', function() {
                            if (confirm('Are you sure you want to remove this date?')) {
                                button.closest('.date-stock-row').remove();
                                updateSummary();
                                
                                // Show save reminder
                                const saveButton = document.querySelector(`.save-course-settings[data-course-id="${courseId}"]`);
                                if (saveButton) {
                                    // Add visual indicator that changes need to be saved
                                    saveButton.style.backgroundColor = '#f0ad4e';
                                    saveButton.textContent = 'Save Settings (Changes Pending)';
                                    
                                    // Add pulsing animation
                                    saveButton.style.animation = 'pulse 1s infinite';
                                }
                            }
                        });
                    });
                    
                    // Edit seats functionality (placeholder for future modal)
                    container.querySelectorAll('.edit-seats').forEach(button => {
                        button.addEventListener('click', function() {
                            const row = button.closest('.date-stock-row');
                            const dateInput = row.querySelector('.course-date');
                            const stockInput = row.querySelector('.course-stock');
                            
                            // For now, just focus on the stock input for quick editing
                            stockInput.focus();
                            stockInput.select();
                        });
                    });
                    
                    // Listen for stock input changes to update UI
                    container.querySelectorAll('.course-stock').forEach(input => {
                        input.addEventListener('input', function() {
                            const row = this.closest('.date-stock-row');
                            const availableSpan = row.querySelectorAll('span')[1]; // Second span is available
                            const soldSpan = row.querySelectorAll('span')[0]; // First span is sold
                            const sold = parseInt(soldSpan.textContent) || 0;
                            const newStock = parseInt(this.value) || 0;
                            const available = Math.max(0, newStock - sold);
                            
                            availableSpan.textContent = available;
                            updateAvailableColor(availableSpan, available);
                            updateRowClass(row, newStock, available);
                            updateSummary();
                        });
                    });
                    
                    // Helper function to update available seats color
                    function updateAvailableColor(element, available) {
                        if (available <= 5) {
                            element.style.color = '#d54e21';
                        } else if (available <= 10) {
                            element.style.color = '#f0ad4e';
                        } else {
                            element.style.color = '#46b450';
                        }
                    }
                    
                    // Helper function to update row class based on availability
                    function updateRowClass(row, stock, available) {
                        row.classList.remove('seat-warning', 'seat-caution');
                        if (stock > 0) {
                            const percentage = (available / stock) * 100;
                            if (percentage <= 20) {
                                row.classList.add('seat-warning');
                            } else if (percentage <= 50) {
                                row.classList.add('seat-caution');
                            }
                        }
                    }
                    
                    // Helper function to update summary
                    function updateSummary() {
                        const summaryDiv = container.querySelector('div[style*="Summary"]');
                        if (!summaryDiv) return;
                        
                        let totalStock = 0;
                        let totalSold = 0;
                        let totalAvailable = 0;
                        
                        container.querySelectorAll('.date-stock-row').forEach(row => {
                            const stockInput = row.querySelector('.course-stock');
                            const spans = row.querySelectorAll('span');
                            
                            if (stockInput && spans.length >= 2) {
                                const stock = parseInt(stockInput.value) || 0;
                                const sold = parseInt(spans[0].textContent) || 0;
                                const available = parseInt(spans[1].textContent) || 0;
                                
                                totalStock += stock;
                                totalSold += sold;
                                totalAvailable += available;
                            }
                        });
                        
                        summaryDiv.innerHTML = `
                            <strong>Summary:</strong> 
                            Total Seats: ${totalStock} | 
                            Sold: ${totalSold} | 
                            Available: <span style="color: ${totalAvailable <= 10 ? '#d54e21' : '#46b450'}">${totalAvailable}</span>
                        `;
                    }
                });

                // Search functionality
                document.getElementById('course-search').addEventListener('input', function() {
                    const search = this.value.toLowerCase();
                    document.querySelectorAll('.wp-list-table tbody tr').forEach(row => {
                        const searchText = row.cells[0].textContent.toLowerCase();
                        row.style.display = searchText.includes(search) ? '' : 'none';
                    });
                });

                // View Courses button
                document.querySelectorAll('.view-courses').forEach(button => {
                    button.addEventListener('click', function() {
                        const groupId = this.getAttribute('data-group-id');
                        window.location.href = '?page=course-box-manager&group_id=' + groupId;
                    });
                });

                // Delete Group button
                document.querySelectorAll('.delete-group').forEach(button => {
                    button.addEventListener('click', function() {
                        if (!confirm('Are you sure you want to delete this course group? This will not delete the courses.')) return;
                        const groupId = this.getAttribute('data-group-id');
                        fetch(ajaxurl + '?action=delete_course_group', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'group_id=' + groupId + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Course group deleted successfully!');
                                location.reload();
                            } else {
                                alert('Error: ' + data.data);
                            }
                        });
                    });
                });

                // Edit Course Settings
                document.querySelectorAll('.edit-course-settings').forEach(button => {
                    button.addEventListener('click', function() {
                        const courseId = this.getAttribute('data-course-id');
                        const urlParams = new URLSearchParams(window.location.search);
                        const groupId = urlParams.get('group_id');
                        let redirectUrl = '?page=course-box-manager&course_id=' + courseId;
                        if (groupId) {
                            redirectUrl += '&group_id=' + groupId;
                        }
                        window.location.href = redirectUrl;
                    });
                });

                // Inline date and stock editing in course list view (only for non-group views)
                // Skip if we're in a group view
                const urlParams = new URLSearchParams(window.location.search);
                const isGroupView = urlParams.has('group_id') && !urlParams.has('course_id');
                
                if (!isGroupView) {
                    document.querySelectorAll('.inline-dates-editor').forEach(editor => {
                    const courseId = editor.getAttribute('data-course-id');
                    let hasChanges = false;
                    
                    // Track changes
                    editor.addEventListener('input', function(e) {
                        if (e.target.classList.contains('inline-date-input') || e.target.classList.contains('inline-stock-input')) {
                            hasChanges = true;
                            const saveBtn = editor.querySelector('.inline-save-dates');
                            if (saveBtn) saveBtn.style.display = 'inline-block';
                        }
                    });
                    
                    // Add date functionality
                    const addBtn = editor.querySelector('.inline-add-date');
                    if (addBtn) {
                        addBtn.addEventListener('click', function() {
                            const existingRows = editor.querySelectorAll('.inline-date-row');
                            const newIndex = existingRows.length;
                            
                            const newRow = document.createElement('div');
                            newRow.className = 'inline-date-row';
                            newRow.style.cssText = 'display: flex; gap: 5px; margin-bottom: 3px; align-items: center;';
                            
                            // Get today's date in YYYY-MM-DD format
                            const today = new Date().toISOString().split('T')[0];
                            
                            newRow.innerHTML = `
                                <input type="text" 
                                       class="inline-date-input" 
                                       value="${today}"
                                       data-course-id="${courseId}"
                                       data-index="${newIndex}"
                                       style="width: 110px; padding: 2px 4px; font-size: 11px; background: #fff; color: #333;">
                                <input type="number" 
                                       class="inline-stock-input" 
                                       value="20"
                                       data-course-id="${courseId}"
                                       data-index="${newIndex}"
                                       min="0"
                                       style="width: 45px; padding: 2px 4px; font-size: 11px; background: #fff; color: #333;">
                                <span style="font-size: 11px; color: #666;">
                                    (0 sold, <span style="color: #4CAF50; font-weight: bold;">20 avail</span>)
                                </span>
                                <button class="inline-remove-date" 
                                        data-course-id="${courseId}"
                                        data-index="${newIndex}"
                                        style="padding: 1px 4px; font-size: 10px; background: #d54e21; color: white; border: none; cursor: pointer; border-radius: 2px;">
                                    ×
                                </button>
                            `;
                            
                            editor.insertBefore(newRow, addBtn);
                            
                            // Add remove functionality
                            newRow.querySelector('.inline-remove-date').addEventListener('click', function() {
                                newRow.remove();
                                hasChanges = true;
                                const saveBtn = editor.querySelector('.inline-save-dates');
                                if (saveBtn) saveBtn.style.display = 'inline-block';
                            });
                            
                            hasChanges = true;
                            const saveBtn = editor.querySelector('.inline-save-dates');
                            if (saveBtn) saveBtn.style.display = 'inline-block';
                        });
                    }
                    
                    // Remove date functionality
                    editor.querySelectorAll('.inline-remove-date').forEach(removeBtn => {
                        removeBtn.addEventListener('click', function() {
                            const row = this.closest('.inline-date-row');
                            row.remove();
                            hasChanges = true;
                            const saveBtn = editor.querySelector('.inline-save-dates');
                            if (saveBtn) saveBtn.style.display = 'inline-block';
                        });
                    });
                    
                    // Save functionality
                    const saveBtn = editor.querySelector('.inline-save-dates');
                    if (saveBtn) {
                        saveBtn.addEventListener('click', function() {
                            const dates = [];
                            editor.querySelectorAll('.inline-date-row').forEach(row => {
                                const dateInput = row.querySelector('.inline-date-input');
                                const stockInput = row.querySelector('.inline-stock-input');
                                if (dateInput && stockInput && dateInput.value) {
                                    dates.push({
                                        date: dateInput.value,
                                        stock: stockInput.value
                                    });
                                }
                            });
                            
                            // Save via AJAX
                            fetch(ajaxurl + '?action=save_inline_dates', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                body: 'course_id=' + courseId + 
                                      '&dates=' + encodeURIComponent(JSON.stringify(dates)) + 
                                      '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    saveBtn.style.display = 'none';
                                    hasChanges = false;
                                    
                                    // Update seats summary
                                    const summarySpan = document.querySelector(`.seats-summary[data-course-id="${courseId}"]`);
                                    if (summarySpan && data.data.summary) {
                                        summarySpan.textContent = data.data.summary;
                                        
                                        // Update class based on availability
                                        summarySpan.className = 'seats-summary';
                                        if (data.data.percentage <= 20) {
                                            summarySpan.classList.add('low-seats');
                                        } else if (data.data.percentage <= 50) {
                                            summarySpan.classList.add('medium-seats');
                                        }
                                    }
                                    
                                    // Show success message
                                    const successMsg = document.createElement('span');
                                    successMsg.style.cssText = 'color: #46b450; font-size: 11px; margin-left: 5px;';
                                    successMsg.textContent = '✓ Saved';
                                    saveBtn.parentElement.appendChild(successMsg);
                                    setTimeout(() => successMsg.remove(), 2000);
                                } else {
                                    alert('Error saving: ' + (data.data || 'Unknown error'));
                                }
                            })
                            .catch(error => {
                                alert('Error saving dates: ' + error.message);
                            });
                        });
                    }
                });
                }
            });
        </script>
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
    check_ajax_referer('course_box_nonce', 'nonce');
    $course_id = intval($_POST['course_id']);
    $group_id = intval($_POST['group_id']);
    $instructors = isset($_POST['instructors']) ? json_decode(stripslashes($_POST['instructors']), true) : [];
    
    if (!$course_id) {
        wp_send_json_error('No course selected.');
    }
    
    // Clear existing group terms and set new one
    wp_set_post_terms($course_id, [], 'course_group');
    
    if ($group_id > 0) {
        $result = wp_set_post_terms($course_id, [$group_id], 'course_group');
        if (is_wp_error($result)) {
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

add_action('wp_ajax_save_course_settings', 'save_course_settings');
function save_course_settings() {
    check_ajax_referer('course_box_nonce', 'nonce');
    $course_id = intval($_POST['course_id']);
    $group_id = intval($_POST['group_id']);
    $box_state = sanitize_text_field($_POST['box_state']);
    $instructors = json_decode(stripslashes($_POST['instructors']), true);
    $stock = sanitize_text_field($_POST['stock']);
    $dates = json_decode(stripslashes($_POST['dates']), true);
    $selling_page_id = intval($_POST['selling_page_id']);
    $linked_product_id = intval($_POST['linked_product_id']);

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
    
    // Auto-change to waitlist if enroll-course has no dates
    if ($box_state === 'enroll-course' && empty($formatted_dates)) {
        $box_state = 'waitlist';
    }
    
    update_post_meta($course_id, 'box_state', $box_state);
    update_post_meta($course_id, 'course_instructors', $instructors);
    
    // Update linked product
    update_post_meta($course_id, 'linked_product_id', $linked_product_id);
    
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
        delete_field('course_dates', $course_id);
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
    $course_id = intval($_POST['course_id']);
    $group_id = intval($_POST['group_id']);
    
    // Remove the course from the group
    wp_remove_object_terms($course_id, $group_id, 'course_group');
    
    wp_send_json_success();
}

add_action('wp_ajax_delete_course', 'delete_course');
function delete_course() {
    check_ajax_referer('course_box_nonce', 'nonce');
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
    $course_id = intval($_POST['course_id']);
    $dates = json_decode(stripslashes($_POST['dates']), true);
    
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
        delete_field('course_dates', $course_id);
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
    
    $course_id = intval($_POST['course_id']);
    $date_index = sanitize_text_field($_POST['date_index']);
    $product_id = intval($_POST['product_id']);
    $instructor_id = intval($_POST['instructor_id']);
    $stock = intval($_POST['stock']);
    $button_text = sanitize_text_field($_POST['button_text']);
    $box_state = sanitize_text_field($_POST['box_state']);
    $launch_date = isset($_POST['launch_date']) ? sanitize_text_field($_POST['launch_date']) : '';
    
    // Date is optional for buy-course state
    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
    if (!$course_id || ($box_state !== 'buy-course' && !$date)) {
        wp_send_json_error('Invalid course ID or date');
    }
    
    // Update product association
    if ($product_id) {
        update_post_meta($course_id, 'linked_product_id', $product_id);
        
        // Update launch date on product if provided
        if ($launch_date && $box_state === 'countdown') {
            update_post_meta($product_id, '_launch_date', $launch_date);
        }
    } else {
        delete_post_meta($course_id, 'linked_product_id');
    }
    
    // Update box state
    update_post_meta($course_id, 'box_state', $box_state);
    
    // Update instructor
    if ($instructor_id) {
        $instructors = [$instructor_id]; // Store as array for consistency
        update_post_meta($course_id, 'course_instructors', $instructors);
        cbm_update_field('course_instructors', $instructors, $course_id);
    } else {
        delete_post_meta($course_id, 'course_instructors');
        delete_field('course_instructors', $course_id);
    }
    
    // Handle dates based on box state
    if ($box_state === 'buy-course' || $box_state === 'waitlist') {
        // These states don't use dates
        delete_field('course_dates', $course_id);
        // Store stock directly on course
        cbm_update_field('course_stock', $stock, $course_id);
    } else {
        // Get existing dates
        $existing_dates = cbm_get_field('course_dates', $course_id) ?: [];
        
        // Update or add the date entry
        if ($date_index === 'new') {
            // Adding a new date
            $existing_dates[] = [
                'date' => $date,
                'stock' => $stock,
                'button_text' => $button_text
            ];
        } else {
            // Updating existing date
            $index = intval($date_index);
            if (isset($existing_dates[$index])) {
                $existing_dates[$index] = [
                    'date' => $date,
                    'stock' => $stock,
                    'button_text' => $button_text
                ];
            }
        }
        
        // Save the updated dates
        cbm_update_field('course_dates', $existing_dates, $course_id);
    }
    
    wp_send_json_success(['message' => 'Data saved successfully']);
}

// AJAX Handler for deleting table row
add_action('wp_ajax_delete_table_row', 'delete_table_row');
function delete_table_row() {
    check_ajax_referer('course_box_nonce', 'nonce');
    
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
            delete_field('course_dates', $course_id);
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
            delete_field('course_instructors', $course_id);
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

// Shortcode to render boxes
function course_box_manager_shortcode() {
    global $post;
    $post_id = $post ? $post->ID : 0;
    
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
        $output = CourseBoxManager\BoxRenderer::render_boxes_for_group($group_id, $post_id);
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

add_shortcode('course_box_manager', 'course_box_manager_shortcode');

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
?>
