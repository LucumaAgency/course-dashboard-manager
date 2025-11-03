<?php
/**
 * Course Dashboard Manager - Tables Page
 *
 * Displays the course groups and tables management interface
 * Version: 1.9.38 - Individual product_id per row support
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Note: This file is included from dashboard.php which is in the CourseBoxManager\Admin namespace
// So we need to use global namespace for WordPress functions with \

// Main function content (no function declaration needed - included inline)
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
                    // Get all groups
                    $groups = get_terms(['taxonomy' => 'course_group', 'hide_empty' => false]);

                    // OPTIMIZATION: Get all courses with their group terms in a single query
                    // This prevents N+1 query problem
                    $all_courses = get_posts([
                        'post_type' => 'course',
                        'posts_per_page' => -1,
                        'fields' => 'ids',
                    ]);

                    // Build a map of group_id => course_count
                    $group_course_counts = array();
                    foreach ($groups as $group) {
                        $group_course_counts[$group->term_id] = 0;
                    }

                    // Count courses per group
                    foreach ($all_courses as $course_id) {
                        $course_groups = wp_get_post_terms($course_id, 'course_group', ['fields' => 'ids']);
                        foreach ($course_groups as $group_id) {
                            if (isset($group_course_counts[$group_id])) {
                                $group_course_counts[$group_id]++;
                            }
                        }
                    }

                    // Now display the groups
                    foreach ($groups as $group) :
                        $courses_count = $group_course_counts[$group->term_id];
                    ?>
                        <tr>
                            <td>
                                <a href="?page=course-box-tables&group_id=<?php echo esc_attr($group->term_id); ?>">
                                    <?php echo esc_html($group->name); ?>
                                </a>
                            </td>
                            <td><?php echo $courses_count; ?></td>
                            <td>
                                <a href="?page=course-box-tables&group_id=<?php echo esc_attr($group->term_id); ?>" class="button">View Courses</a>
                                <?php
                                $delete_message = $courses_count > 0
                                    ? 'This group contains ' . $courses_count . ' course(s). Deleting the group will unassign all courses from it. Are you sure?'
                                    : 'Are you sure you want to delete this group?';
                                ?>
                                <a href="<?php echo wp_nonce_url('?page=course-box-tables&action=delete_group&group_id=' . $group->term_id, 'delete_group_' . $group->term_id); ?>" 
                                   class="button button-link-delete" 
                                   onclick="return confirm('<?php echo esc_js($delete_message); ?>');">
                                    Delete
                                </a>
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
                        <button id="save-all-changes" class="button button-primary" style="font-size: 14px; padding: 8px 20px;">Save All Changes</button>
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
            
            <!-- Hidden data for JavaScript -->
            <script>
                console.log('[CBM Debug] Page loading - checking coursesData...');
                var coursesData = <?php 
                    $courses_json = [];
                    $all_products = [];
                    
                    // Safely get WooCommerce products
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
                        } catch (Exception $e) {
                            error_log('[CBM Debug] Error getting WooCommerce products: ' . $e->getMessage());
                        }
                    }
                    
                    // Process courses safely
                    if (!empty($courses)) {
                        foreach ($courses as $course) {
                            $course_id = $course->ID;
                            $product_id = get_post_meta($course_id, 'linked_product_id', true);
                            $launch_date = $product_id ? get_post_meta($product_id, '_launch_date', true) : '';
                            
                            // Use safe field retrieval
                            $dates = function_exists('get_field') ? get_field('course_dates', $course_id) : get_post_meta($course_id, 'course_dates', true);
                            $stock = function_exists('get_field') ? get_field('course_stock', $course_id) : get_post_meta($course_id, 'course_stock', true);
                            
                            $courses_json[] = [
                                'id' => $course_id,
                                'title' => $course->post_title,
                                'product_id' => $product_id,
                                'buy_product_id' => get_post_meta($course_id, 'buy_product_id', true),
                                'enroll_product_id' => get_post_meta($course_id, 'enroll_product_id', true),
                                'buy_price' => get_post_meta($course_id, 'buy_price', true),
                                'related_stm_course_id' => get_post_meta($course_id, 'related_stm_course_id', true),
                                'launch_date' => $launch_date,
                                'dates' => $dates ?: [],
                                'stock' => $stock ?: 0
                            ];
                        }
                    }
                    echo wp_json_encode($courses_json);
                ?>;
                console.log('[CBM Debug] Loaded coursesData:', coursesData);
                if (coursesData && coursesData.length > 0 && coursesData[0].dates) {
                    console.log('[CBM Debug] First course dates:', coursesData[0].dates);
                }
                var allProducts = <?php echo wp_json_encode($all_products); ?>;
                var groupId = <?php echo intval($group_id); ?>;
            </script>
        
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
                        console.log('[CBM Debug] Adding course:', courseId, 'to group:', groupId);
                        
                        fetch(ajaxurl + '?action=assign_course_to_group', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'course_id=' + courseId + '&group_id=' + groupId + 
                                  '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                        })
                        .then(response => {
                            console.log('[CBM Debug] Response status:', response.status);
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(result => {
                            console.log('[CBM Debug] AJAX result:', result);
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
