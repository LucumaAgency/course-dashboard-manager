<?php
/**
 * Course Dashboard Manager - Tables Page
 * 
 * Displays the course groups and tables management interface
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
            
            // Debug: Check all courses in group for selling page
            error_log('[CBM Debug] Looking for selling page in group ' . $group_id);
            
            // First, let's see all courses in this group
            $all_in_group = get_posts([
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
            error_log('[CBM Debug] Total courses in group ' . $group_id . ': ' . count($all_in_group) . ' - IDs: ' . implode(', ', $all_in_group));
            
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
                error_log('[CBM Debug] Found selling page: ' . $selling_page_id);
            } else {
                error_log('[CBM Debug] No selling page found in group');
                
                // Debug: Check all courses in group and their meta
                $all_group_courses = get_posts([
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
                
                foreach ($all_group_courses as $course) {
                    $is_selling = get_post_meta($course->ID, 'is_selling_page', true);
                    error_log('[CBM Debug] Course ' . $course->ID . ' (' . $course->post_title . ') - is_selling_page: ' . ($is_selling ? $is_selling : 'not set'));
                }
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
                            // Get only courses in this group for selling page selection
                            $group_courses_for_select = get_posts([
                                'post_type' => 'course',
                                'posts_per_page' => -1,
                                'orderby' => 'title',
                                'order' => 'ASC',
                                'tax_query' => [
                                    [
                                        'taxonomy' => 'course_group',
                                        'field' => 'term_id',
                                        'terms' => $group_id,
                                    ],
                                ],
                            ]);
                            
                            error_log('[CBM Debug] Courses available for selling page dropdown: ' . count($group_courses_for_select));
                            
                            foreach ($group_courses_for_select as $course) {
                                $is_selected = ($selling_page_id == $course->ID);
                                error_log('[CBM Debug] Option: ' . $course->ID . ' - ' . $course->post_title . ' - Selected: ' . ($is_selected ? 'YES' : 'NO'));
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
                var coursesData = <?php 
                    $courses_json = [];
                    $all_products = [];
                    
                    // Debug: Log what's happening with product loading
                    error_log('[CBM Debug] Starting product load...');
                    error_log('[CBM Debug] WooCommerce class exists: ' . (class_exists('WooCommerce') ? 'YES' : 'NO'));
                    error_log('[CBM Debug] wc_get_products exists: ' . (function_exists('wc_get_products') ? 'YES' : 'NO'));
                    
                    // Safely get WooCommerce products
                    if (function_exists('wc_get_products')) {
                        try {
                            error_log('[CBM Debug] Calling wc_get_products...');
                            $products = wc_get_products(['limit' => -1, 'orderby' => 'title', 'order' => 'ASC', 'status' => 'publish']);
                            error_log('[CBM Debug] wc_get_products returned ' . count($products) . ' products');
                            
                            foreach ($products as $product) {
                                $all_products[$product->get_id()] = [
                                    'name' => $product->get_name(),
                                    'regular_price' => $product->get_regular_price(),
                                    'sale_price' => $product->get_sale_price()
                                ];
                            }
                            error_log('[CBM Debug] Added ' . count($all_products) . ' products to array');
                        } catch (Exception $e) {
                            error_log('[CBM Debug] Error getting WooCommerce products: ' . $e->getMessage());
                        }
                    } else {
                        error_log('[CBM Debug] wc_get_products function not found! Trying alternative method...');
                        
                        // Alternative method: Direct database query
                        global $wpdb;
                        $product_results = $wpdb->get_results(
                            "SELECT ID, post_title 
                             FROM {$wpdb->posts} 
                             WHERE post_type = 'product' 
                             AND post_status = 'publish' 
                             ORDER BY post_title ASC"
                        );
                        
                        error_log('[CBM Debug] Direct query found ' . count($product_results) . ' products');
                        
                        foreach ($product_results as $product) {
                            $regular_price = get_post_meta($product->ID, '_regular_price', true);
                            $sale_price = get_post_meta($product->ID, '_sale_price', true);
                            
                            $all_products[$product->ID] = [
                                'name' => $product->post_title,
                                'regular_price' => $regular_price ?: '',
                                'sale_price' => $sale_price ?: ''
                            ];
                        }
                        
                        error_log('[CBM Debug] Alternative method added ' . count($all_products) . ' products to array');
                    }
                    
                    // Final debug check
                    error_log('[CBM Debug] FINAL: Total products loaded = ' . count($all_products));
                    if (count($all_products) > 0) {
                        error_log('[CBM Debug] FINAL: First product = ' . print_r(reset($all_products), true));
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
                    echo json_encode($courses_json);
                ?>;
                var allProducts = <?php echo json_encode($all_products); ?>;
                var groupId = <?php echo intval($group_id); ?>;
                
                // Debug immediately after loading
                try {
                    console.log('[CBM Debug IMMEDIATE] allProducts type:', typeof allProducts);
                    console.log('[CBM Debug IMMEDIATE] allProducts is array?', Array.isArray(allProducts));
                    console.log('[CBM Debug IMMEDIATE] allProducts keys:', Object.keys(allProducts));
                    console.log('[CBM Debug IMMEDIATE] allProducts count:', Object.keys(allProducts).length);
                    console.log('[CBM Debug IMMEDIATE] First 3 products:', Object.entries(allProducts).slice(0, 3));
                    
                    if (Object.keys(allProducts).length === 0) {
                        console.error('[CBM Debug IMMEDIATE] ERROR: No products loaded from PHP!');
                        console.log('[CBM Debug IMMEDIATE] Raw allProducts value:', JSON.stringify(allProducts));
                    }
                } catch (e) {
                    console.error('[CBM Debug IMMEDIATE] Error checking products:', e);
                }
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
        
        <script>
            // Version: <?php echo time(); ?> - Force reload
            console.log('[CBM Debug] Script version: <?php echo date('Y-m-d H:i:s'); ?>');
            
            document.addEventListener('DOMContentLoaded', function() {
                console.log('[CBM Debug] DOMContentLoaded - Tables view script starting');
                console.log('[CBM Debug] Group ID:', typeof groupId !== 'undefined' ? groupId : 'NOT DEFINED');
                console.log('[CBM Debug] Courses data available:', typeof coursesData !== 'undefined' ? 'YES' : 'NO');
                console.log('[CBM Debug] All products available:', typeof allProducts !== 'undefined' ? 'YES' : 'NO');
                
                // Debug products array
                console.log('[CBM Debug] Products count:', Object.keys(allProducts).length);
                console.log('[CBM Debug] Products object:', allProducts);
                if (Object.keys(allProducts).length === 0) {
                    console.error('[CBM Debug] WARNING: No products in array! Products not loaded from PHP.');
                } else {
                    console.log('[CBM Debug] First product:', Object.values(allProducts)[0]);
                }
                
                // Debug selling page selection
                console.log('[CBM Debug] About to check for selling page dropdown...');
                try {
                    const sellingPageSelect = document.getElementById('group-selling-page');
                    if (sellingPageSelect) {
                        console.log('[CBM Debug] Selling page dropdown found!');
                        console.log('[CBM Debug] Selling page dropdown value on load:', sellingPageSelect.value);
                        const selectedOption = sellingPageSelect.options[sellingPageSelect.selectedIndex];
                        console.log('[CBM Debug] Selected option:', selectedOption ? {value: selectedOption.value, text: selectedOption.text} : 'none');
                        
                        // Check if PHP set the selection
                        const phpSellingPageId = <?php echo isset($selling_page_id) ? json_encode($selling_page_id) : 'null'; ?>;
                        console.log('[CBM Debug] PHP selling_page_id:', phpSellingPageId);
                    
                        // Force set the value if needed
                        if (phpSellingPageId && sellingPageSelect.value !== phpSellingPageId.toString()) {
                            console.log('[CBM Debug] Forcing selling page selection to:', phpSellingPageId);
                            sellingPageSelect.value = phpSellingPageId;
                        }
                    } else {
                        console.log('[CBM Debug] Selling page dropdown NOT FOUND at initial load!');
                        console.log('[CBM Debug] Available elements with IDs:', Array.from(document.querySelectorAll('[id]')).map(el => el.id));
                    }
                } catch (error) {
                    console.error('[CBM Debug] Error checking selling page dropdown:', error);
                }
                
                let currentBoxState = document.getElementById('group-box-state').value;
                let rowCounter = 0;
                
                console.log('[CBM Debug] Current box state:', currentBoxState);
                console.log('[CBM Debug] Courses data:', typeof coursesData !== 'undefined' ? coursesData : 'NOT DEFINED');
                
                // Debug selling page selection - MOVED HERE
                try {
                    const sellingPageSelectDebug = document.getElementById('group-selling-page');
                    if (sellingPageSelectDebug) {
                        console.log('[CBM Debug] (Second check) Selling page dropdown value on load:', sellingPageSelectDebug.value);
                        const selectedOptionDebug = sellingPageSelectDebug.options[sellingPageSelectDebug.selectedIndex];
                        console.log('[CBM Debug] (Second check) Selected option:', selectedOptionDebug ? {value: selectedOptionDebug.value, text: selectedOptionDebug.text} : 'none');
                        
                        // Check if PHP set the selection
                        const phpSellingPageIdDebug = <?php echo isset($selling_page_id) ? json_encode($selling_page_id) : 'null'; ?>;
                        console.log('[CBM Debug] (Second check) PHP selling_page_id:', phpSellingPageIdDebug);
                    
                        // Force set the value if needed
                        if (phpSellingPageIdDebug && sellingPageSelectDebug.value !== phpSellingPageIdDebug.toString()) {
                            console.log('[CBM Debug] Forcing selling page selection to:', phpSellingPageIdDebug);
                            sellingPageSelectDebug.value = phpSellingPageIdDebug.toString();
                            
                            // Double check it was set
                            setTimeout(() => {
                                console.log('[CBM Debug] After forcing, selling page value is:', sellingPageSelectDebug.value);
                            }, 100);
                        }
                    } else {
                        console.log('[CBM Debug] (Second check) Selling page dropdown not found!');
                    }
                } catch (error) {
                    console.error('[CBM Debug] (Second check) Error:', error);
                }
                
                // Function to render table based on box state
                function renderTable(boxState) {
                    const tableHeader = document.getElementById('table-header');
                    const tableBody = document.getElementById('table-body');
                    const addButton = document.getElementById('add-new-row');
                    const tableContainer = document.getElementById('table-container');
                    const buyTableContainer = document.getElementById('buy-table-container');
                    const enrollTableTitle = document.getElementById('enroll-table-title');
                    const stmCourseSelector = document.getElementById('stm-course-selector');
                    
                    // Clear existing content
                    tableHeader.innerHTML = '';
                    tableBody.innerHTML = '';
                    
                    // Always show table container
                    tableContainer.style.display = 'block';
                    
                    // Show/hide STM course selector based on state
                    if (boxState === 'enroll-course' || boxState === 'enroll-buy') {
                        stmCourseSelector.style.display = 'block';
                        populateSTMCourseSelector();
                        console.log('[CBM Debug] STM selector shown and populated for state:', boxState);
                    } else {
                        stmCourseSelector.style.display = 'none';
                        console.log('[CBM Debug] STM selector hidden for state:', boxState);
                    }
                    
                    // Handle enroll-buy state with two separate tables
                    if (boxState === 'enroll-buy') {
                        // Show both tables
                        buyTableContainer.style.display = 'block';
                        enrollTableTitle.style.display = 'block';
                        addButton.style.display = 'inline-block';
                        addButton.textContent = '+ Add New Enroll Date';
                        
                        // Render Buy Course table
                        renderBuyTable();
                        // Render Enroll Course table (will be handled below)
                    } else {
                        // Hide buy table for other states
                        buyTableContainer.style.display = 'none';
                        enrollTableTitle.style.display = 'none';
                        
                        // Show/hide add button based on state
                        if (boxState === 'enroll-course') {
                            addButton.style.display = 'inline-block';
                            addButton.textContent = '+ Add Course/Date';
                        } else {
                            addButton.style.display = 'none';
                        }
                    }
                    
                    // Build header based on box state
                    let headerHTML = '<tr>';
                    if (boxState === 'enroll-course') {
                        headerHTML += '<th style="width: 100px;">Date</th>';
                        headerHTML += '<th style="width: 150px;">Product</th>';
                        headerHTML += '<th style="width: 150px;">STM Course</th>';
                        headerHTML += '<th style="width: 80px;">Reg. Price</th>';
                        headerHTML += '<th style="width: 80px;">Sale Price</th>';
                        headerHTML += '<th style="width: 60px;">Seats</th>';
                        headerHTML += '<th style="width: 50px;">Sold</th>';
                        headerHTML += '<th style="width: 60px;">Avail.</th>';
                        headerHTML += '<th style="width: 120px;">Button Text</th>';
                        headerHTML += '<th style="width: 100px;">Actions</th>';
                    } else if (boxState === 'buy-course') {
                        headerHTML += '<th style="width: 200px;">Product</th>';
                        headerHTML += '<th style="width: 200px;">STM Course</th>';
                        headerHTML += '<th style="width: 100px;">Regular Price</th>';
                        headerHTML += '<th style="width: 100px;">Sale Price</th>';
                        headerHTML += '<th style="width: 80px;">Total Seats</th>';
                        headerHTML += '<th style="width: 80px;">Available</th>';
                        headerHTML += '<th style="width: 150px;">Button Text</th>';
                        headerHTML += '<th style="width: 120px;">Actions</th>';
                    } else if (boxState === 'countdown') {
                        headerHTML += '<th style="width: 8%;">Date</th>';
                        headerHTML += '<th style="width: 13%;">Associated Product</th>';
                        headerHTML += '<th style="width: 8%;">Regular Price</th>';
                        headerHTML += '<th style="width: 8%;">Sale Price</th>';
                        headerHTML += '<th style="width: 13%;">Launch Date & Time</th>';
                        headerHTML += '<th style="width: 7%;">Total Seats</th>';
                        headerHTML += '<th style="width: 7%;">Sold</th>';
                        headerHTML += '<th style="width: 8%;">Available</th>';
                        headerHTML += '<th style="width: 13%;">Button Text</th>';
                        headerHTML += '<th style="width: 15%;">Actions</th>';
                    } else if (boxState === 'waitlist') {
                        headerHTML += '<th style="width: 20%;">Associated Product</th>';
                        headerHTML += '<th style="width: 15%;">Regular Price</th>';
                        headerHTML += '<th style="width: 15%;">Sale Price</th>';
                        headerHTML += '<th style="width: 20%;">Button Text</th>';
                        headerHTML += '<th style="width: 30%;">Actions</th>';
                    } else if (boxState === 'soldout') {
                        headerHTML += '<th style="width: 10%;">Date</th>';
                        headerHTML += '<th style="width: 15%;">Associated Product</th>';
                        headerHTML += '<th style="width: 8%;">Regular Price</th>';
                        headerHTML += '<th style="width: 8%;">Sale Price</th>';
                        headerHTML += '<th style="width: 8%;">Total Seats</th>';
                        headerHTML += '<th style="width: 7%;">Sold</th>';
                        headerHTML += '<th style="width: 8%;">Available</th>';
                        headerHTML += '<th style="width: 15%;">Button Text</th>';
                        headerHTML += '<th style="width: 21%;">Actions</th>';
                    } else if (boxState === 'enroll-buy') {
                        // For enroll-buy, we'll have separate headers for each table
                        // Enroll table header (similar to enroll-course)
                        headerHTML += '<th style="width: 100px;">Date</th>';
                        headerHTML += '<th style="width: 140px;">Product</th>';
                        headerHTML += '<th style="width: 140px;">STM Course</th>';
                        headerHTML += '<th style="width: 70px;">Reg. Price</th>';
                        headerHTML += '<th style="width: 70px;">Sale Price</th>';
                        headerHTML += '<th style="width: 50px;">Seats</th>';
                        headerHTML += '<th style="width: 40px;">Sold</th>';
                        headerHTML += '<th style="width: 50px;">Avail.</th>';
                        headerHTML += '<th style="width: 100px;">Button Text</th>';
                        headerHTML += '<th style="width: 15%;">Actions</th>';
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
                    } else if (boxState === 'enroll-buy') {
                        // For enroll-buy, only add enroll rows to the enroll table
                        // Buy table is handled separately by renderBuyTable()
                        const firstCourse = coursesData[0] || {
                            id: 0, 
                            product_id: '', 
                            buy_product_id: '',
                            enroll_product_id: '',
                            buy_price: '',
                            stock: 20
                        };
                        
                        // Add Enroll Course rows (can have multiple dates)
                        if (firstCourse.dates && firstCourse.dates.length > 0) {
                            firstCourse.dates.forEach((dateInfo, index) => {
                                addTableRow(firstCourse, {date: dateInfo, index: index}, boxState);
                            });
                        } else {
                            // Add at least one enroll row
                            addTableRow(firstCourse, null, boxState);
                        }
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
                    
                    console.log('[CBM Debug] addTableRow - Course ID:', course.id, 'Date Info:', dateInfo);
                    
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
                        // Get STM Course ID for this specific date
                        const stmCourseId = dateInfo && dateInfo.date.stm_course_id ? dateInfo.date.stm_course_id : course.related_stm_course_id || '';
                        
                        rowHTML += `<td><input type="text" class="inline-edit-date" value="${dateInfo ? dateInfo.date.date : ''}" placeholder="YYYY-MM-DD" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>${buildProductSelect(course.product_id)}</td>`;
                        rowHTML += `<td>${buildSTMCourseSelect(stmCourseId, course.id)}</td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-regular-price" value="${getProductRegularPrice(course.product_id)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-sale-price" value="${getProductSalePrice(course.product_id)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-stock" value="${stock}" min="0" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="sold-count">${sold}</span></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="available-count" style="color: ${available <= 5 ? '#d54e21' : (available <= 10 ? '#f0ad4e' : '#46b450')}; font-weight: bold;">${available}</span></td>`;
                        rowHTML += `<td><input type="text" class="inline-edit-button-text" value="${buttonText}" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>
                            <!-- Individual save button removed - use Save All Changes button above -->
                            <button class="button button-small delete-row" style="background: #d54e21; color: white; margin-left: 5px;">×</button>
                            <span class="save-status" style="margin-left: 5px;"></span>
                        </td>`;
                    } else if (boxState === 'soldout') {
                        rowHTML += `<td><input type="text" class="inline-edit-date" value="${dateInfo ? dateInfo.date.date : ''}" placeholder="YYYY-MM-DD" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>${buildProductSelect(course.product_id)}</td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-regular-price" value="${getProductRegularPrice(course.product_id)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-sale-price" value="${getProductSalePrice(course.product_id)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-stock" value="0" min="0" readonly style="width: 100%; padding: 3px; background: #f0f0f0;"></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="sold-count">${sold}</span></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="available-count" style="color: #d54e21; font-weight: bold;">0</span></td>`;
                        rowHTML += `<td><input type="text" class="inline-edit-button-text" value="Sold Out" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>
                            <!-- Individual save button removed - use Save All Changes button above -->
                            <button class="button button-small delete-row" style="background: #d54e21; color: white; margin-left: 5px;">×</button>
                            <span class="save-status" style="margin-left: 5px;"></span>
                        </td>`;
                    } else if (boxState === 'buy-course') {
                        rowHTML += `<td>${buildProductSelect(course.product_id)}</td>`;
                        rowHTML += `<td>${buildSTMCourseSelect(course.related_stm_course_id || '', course.id)}</td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-regular-price" value="${getProductRegularPrice(course.product_id)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-sale-price" value="${getProductSalePrice(course.product_id)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-stock" value="${stock}" min="0" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="available-count" style="color: ${available <= 5 ? '#d54e21' : (available <= 10 ? '#f0ad4e' : '#46b450')}; font-weight: bold;">${available}</span></td>`;
                        rowHTML += `<td><input type="text" class="inline-edit-button-text" value="Buy Now" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>
                            <!-- Individual save button removed - use Save All Changes button above -->
                            <button class="button button-small delete-row" style="background: #d54e21; color: white; margin-left: 5px;">×</button>
                            <span class="save-status" style="margin-left: 5px;"></span>
                        </td>`;
                    } else if (boxState === 'countdown') {
                        rowHTML += `<td><input type="text" class="inline-edit-date" value="${dateInfo ? dateInfo.date.date : ''}" placeholder="YYYY-MM-DD" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>${buildProductSelect(course.product_id)}</td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-regular-price" value="${getProductRegularPrice(course.product_id)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-sale-price" value="${getProductSalePrice(course.product_id)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="datetime-local" class="inline-edit-launch-date" value="${course.launch_date || ''}" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-stock" value="${stock}" min="0" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="sold-count">${sold}</span></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="available-count" style="color: ${available <= 5 ? '#d54e21' : (available <= 10 ? '#f0ad4e' : '#46b450')}; font-weight: bold;">${available}</span></td>`;
                        rowHTML += `<td><input type="text" class="inline-edit-button-text" value="${buttonText}" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>
                            <!-- Individual save button removed - use Save All Changes button above -->
                            <button class="button button-small delete-row" style="background: #d54e21; color: white; margin-left: 5px;">×</button>
                            <span class="save-status" style="margin-left: 5px;"></span>
                        </td>`;
                    } else if (boxState === 'waitlist') {
                        rowHTML += `<td>${buildProductSelect(course.product_id)}</td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-regular-price" value="${getProductRegularPrice(course.product_id)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-sale-price" value="${getProductSalePrice(course.product_id)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="text" class="inline-edit-button-text" value="Join Waitlist" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>
                            <!-- Individual save button removed - use Save All Changes button above -->
                            <button class="button button-small delete-row" style="background: #d54e21; color: white; margin-left: 5px;">×</button>
                            <span class="save-status" style="margin-left: 5px;"></span>
                        </td>`;
                    } else if (boxState === 'enroll-buy') {
                        // For enroll-buy state, this handles enroll rows only
                        // Similar to enroll-course but for the enroll table
                        const dateValue = dateInfo && dateInfo.date ? (typeof dateInfo.date === 'object' ? dateInfo.date.date : dateInfo.date) : '';
                        rowHTML += `<td><input type="text" class="inline-edit-date" value="${dateValue}" placeholder="Date/Text" style="width: 100%; padding: 3px;"></td>`;
                        
                        const enrollProductId = course.enroll_product_id || course.product_id;
                        console.log('[CBM Debug] Enroll product ID for row:', enrollProductId);
                        rowHTML += `<td>${buildProductSelect(enrollProductId, 'enroll-product-select')}</td>`;
                        
                        // Add STM Course selector for enroll-buy (enroll section)
                        const stmCourseId = dateInfo && dateInfo.date && dateInfo.date.stm_course_id ? dateInfo.date.stm_course_id : course.related_stm_course_id || '';
                        rowHTML += `<td>${buildSTMCourseSelect(stmCourseId, course.id)}</td>`;
                        
                        rowHTML += `<td><input type="number" class="inline-edit-regular-price" value="${getProductRegularPrice(enrollProductId)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-sale-price" value="${getProductSalePrice(enrollProductId)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        
                        const enrollStock = dateInfo && dateInfo.date && dateInfo.date.stock ? dateInfo.date.stock : course.stock || 20;
                        const enrollSold = 0; // Will be calculated server-side
                        const enrollAvailable = Math.max(0, enrollStock - enrollSold);
                        rowHTML += `<td><input type="number" class="inline-edit-stock" value="${enrollStock}" min="0" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="sold-count">${enrollSold}</span></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="available-count" style="color: ${enrollAvailable <= 5 ? '#d54e21' : (enrollAvailable <= 10 ? '#f0ad4e' : '#46b450')}; font-weight: bold;">${enrollAvailable}</span></td>`;
                        
                        const currentButtonText = dateInfo && dateInfo.date && dateInfo.date.button_text ? dateInfo.date.button_text : 'Enroll Now';
                        rowHTML += `<td><input type="text" class="inline-edit-button-text" value="${currentButtonText}" style="width: 100%; padding: 3px;"></td>`;
                        
                        rowHTML += `<td>
                            <!-- Individual save button removed - use Save All Changes button above -->
                            <button class="button button-small delete-row" style="background: #d54e21; color: white; margin-left: 5px;">×</button>
                            <span class="save-status" style="margin-left: 5px;"></span>
                        </td>`;
                    }
                    
                    row.innerHTML = rowHTML;
                    tableBody.appendChild(row);
                    attachRowEventListeners(row);
                }
                
                // Function to render Buy Course table for enroll-buy state
                function renderBuyTable() {
                    const buyTableHeader = document.getElementById('buy-table-header');
                    const buyTableBody = document.getElementById('buy-table-body');
                    
                    // Clear existing content
                    buyTableHeader.innerHTML = '';
                    buyTableBody.innerHTML = '';
                    
                    // Build header for buy table
                    let headerHTML = '<tr>';
                    headerHTML += '<th style="width: 200px;">Product</th>';
                    headerHTML += '<th style="width: 200px;">STM Course</th>';
                    headerHTML += '<th style="width: 100px;">Regular Price</th>';
                    headerHTML += '<th style="width: 100px;">Sale Price</th>';
                    headerHTML += '<th style="width: 150px;">Button Text</th>';
                    headerHTML += '<th style="width: 120px;">Actions</th>';
                    headerHTML += '</tr>';
                    buyTableHeader.innerHTML = headerHTML;
                    
                    // Get course data
                    const firstCourse = coursesData[0] || {
                        id: 0,
                        product_id: '',
                        buy_product_id: '',
                        buy_price: ''
                    };
                    
                    console.log('[CBM Debug] Buy table course data:', firstCourse);
                    
                    // Create buy row
                    const row = document.createElement('tr');
                    row.className = 'course-row editable-row buy-row';
                    row.dataset.courseId = firstCourse.id;
                    
                    const buyProductId = firstCourse.buy_product_id || firstCourse.product_id;
                    console.log('[CBM Debug] Buy product ID for table:', buyProductId);
                    
                    let rowHTML = '';
                    rowHTML += `<td>${buildProductSelect(buyProductId, 'buy-product-select')}</td>`;
                    rowHTML += `<td>${buildSTMCourseSelect(firstCourse.related_stm_course_id || '', firstCourse.id)}</td>`;
                    rowHTML += `<td><input type="number" class="inline-edit-regular-price" value="${getProductRegularPrice(buyProductId)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                    rowHTML += `<td><input type="number" class="inline-edit-sale-price" value="${getProductSalePrice(buyProductId)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                    rowHTML += `<td><input type="text" class="inline-edit-button-text" value="Buy Course" style="width: 100%; padding: 3px;"></td>`;
                    rowHTML += `<td>
                        <!-- Individual save button removed - use Save All Changes button above -->
                        <span class="save-status" style="margin-left: 5px;"></span>
                    </td>`;
                    
                    row.innerHTML = rowHTML;
                    buyTableBody.appendChild(row);
                    
                    // Attach event listeners for buy table
                    attachBuyRowEventListeners(row);
                }
                
                // Attach event listeners for buy table row
                function attachBuyRowEventListeners(row) {
                    const saveBtn = row.querySelector('.save-buy-row');
                    if (saveBtn) {
                        saveBtn.addEventListener('click', function() {
                            const courseId = row.dataset.courseId;
                            const productSelect = row.querySelector('.buy-product-select, select');
                            const regularPriceInput = row.querySelector('.inline-edit-regular-price');
                            const salePriceInput = row.querySelector('.inline-edit-sale-price');
                            const buttonTextInput = row.querySelector('.inline-edit-button-text');
                            
                            // Save buy product configuration
                            const buyProductId = productSelect ? productSelect.value : '';
                            const regularPrice = regularPriceInput ? regularPriceInput.value : '';
                            const salePrice = salePriceInput ? salePriceInput.value : '';
                            const buttonText = buttonTextInput ? buttonTextInput.value : 'Buy Course';
                            
                            // Update product prices if needed
                            if (buyProductId && (regularPrice || salePrice)) {
                                // This would call the save function
                                console.log('Saving buy product config:', {
                                    courseId,
                                    buyProductId,
                                    regularPrice,
                                    salePrice,
                                    buttonText
                                });
                            }
                            
                            // Show save status
                            const statusSpan = row.querySelector('.save-status');
                            if (statusSpan) {
                                statusSpan.innerHTML = '✓ Saved';
                                setTimeout(() => {
                                    statusSpan.innerHTML = '';
                                }, 2000);
                            }
                        });
                    }
                }
                
                // Build product select dropdown
                function buildProductSelect(selectedId, className = '') {
                    const selectClass = className || 'inline-edit-product';
                    let html = `<select class="${selectClass}" style="width: 100%; padding: 3px;" onchange="updateProductPrice(this)"><option value="">None</option>`;
                    for (let id in allProducts) {
                        const productName = allProducts[id].name || allProducts[id]; // Support both old and new format
                        html += `<option value="${id}" ${selectedId == id ? 'selected' : ''}>${productName}</option>`;
                    }
                    html += '</select>';
                    return html;
                }
                
                // Populate global STM course selector
                function populateSTMCourseSelector() {
                    const globalSelector = document.getElementById('global-stm-course');
                    if (!globalSelector) return;
                    
                    // Get current course's STM ID
                    const currentSTMId = coursesData && coursesData[0] ? coursesData[0].related_stm_course_id : '';
                    
                    console.log('[CBM Debug] Populating STM selector, current STM ID:', currentSTMId);
                    console.log('[CBM Debug] Course data for STM:', coursesData && coursesData[0] ? coursesData[0] : 'no course data');
                    
                    // Build options HTML
                    let html = '<option value="">None</option>';
                    
                    <?php
                    $stm_courses_for_global = get_posts([
                        'post_type' => 'stm-courses',
                        'posts_per_page' => -1,
                        'orderby' => 'title',
                        'order' => 'ASC',
                        'post_status' => 'publish'
                    ]);
                    
                    $stm_courses_js_global = [];
                    foreach ($stm_courses_for_global as $stm_course) {
                        $stm_courses_js_global[] = [
                            'id' => $stm_course->ID,
                            'title' => $stm_course->post_title
                        ];
                    }
                    ?>
                    
                    const stmCoursesGlobal = <?php echo json_encode($stm_courses_js_global); ?>;
                    
                    stmCoursesGlobal.forEach(course => {
                        const selected = currentSTMId == course.id ? 'selected' : '';
                        html += `<option value="${course.id}" ${selected}>${course.title} (#${course.id})</option>`;
                    });
                    
                    globalSelector.innerHTML = html;
                }
                
                // Build STM Course select dropdown
                function buildSTMCourseSelect(selectedId, courseId) {
                    let html = `<select class="inline-edit-stm-course" data-course-id="${courseId}" style="width: 100%; padding: 3px;">`;
                    html += '<option value="">None</option>';
                    
                    // Get STM courses from PHP
                    <?php
                    $stm_courses = get_posts([
                        'post_type' => 'stm-courses',
                        'posts_per_page' => -1,
                        'orderby' => 'title',
                        'order' => 'ASC',
                        'post_status' => 'publish'
                    ]);
                    
                    $stm_courses_js = [];
                    foreach ($stm_courses as $stm_course) {
                        $stm_courses_js[] = [
                            'id' => $stm_course->ID,
                            'title' => $stm_course->post_title
                        ];
                    }
                    ?>
                    
                    const stmCourses = <?php echo json_encode($stm_courses_js); ?>;
                    
                    stmCourses.forEach(course => {
                        const selected = selectedId == course.id ? 'selected' : '';
                        html += `<option value="${course.id}" ${selected}>${course.title} (#${course.id})</option>`;
                    });
                    
                    html += '</select>';
                    return html;
                }
                
                // Get product regular price
                function getProductRegularPrice(productId) {
                    if (!productId || !allProducts[productId]) return '';
                    return allProducts[productId].regular_price || '';
                }
                
                // Get product sale price
                function getProductSalePrice(productId) {
                    if (!productId || !allProducts[productId]) return '';
                    return allProducts[productId].sale_price || '';
                }
                
                // Update price fields when product changes
                window.updateProductPrice = function(selectElement) {
                    const productId = selectElement.value;
                    const row = selectElement.closest('tr');
                    const regularPriceInput = row.querySelector('.inline-edit-regular-price');
                    const salePriceInput = row.querySelector('.inline-edit-sale-price');
                    
                    if (regularPriceInput) {
                        regularPriceInput.value = getProductRegularPrice(productId) || '';
                    }
                    if (salePriceInput) {
                        salePriceInput.value = getProductSalePrice(productId) || '';
                    }
                }
                
                // Attach event listeners to row
                function attachRowEventListeners(row) {
                    // Track changes
                    row.querySelectorAll('input, select').forEach(field => {
                        field.addEventListener('change', function() {
                            row.classList.add('has-changes');
                            
                            // Log STM course changes
                            if (this.classList.contains('inline-edit-stm-course')) {
                                console.log('[CBM Debug] STM course changed in row to:', this.value);
                                console.log('[CBM Debug] Row data:', {
                                    courseId: row.dataset.courseId,
                                    dateIndex: row.dataset.dateIndex
                                });
                            }
                            
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
                        related_stm_course_id: row.querySelector('.inline-edit-stm-course')?.value || '',
                        regular_price: row.querySelector('.inline-edit-regular-price')?.value || '',
                        sale_price: row.querySelector('.inline-edit-sale-price')?.value || '',
                        stock: row.querySelector('.inline-edit-stock')?.value || 0,
                        button_text: row.querySelector('.inline-edit-button-text')?.value || 'Enroll Now'
                    };
                    
                    if (boxState === 'enroll-course' || boxState === 'soldout' || boxState === 'countdown') {
                        data.date = row.querySelector('.inline-edit-date')?.value || '';
                        console.log('[CBM Debug] Date input element:', row.querySelector('.inline-edit-date'));
                        console.log('[CBM Debug] Date value:', data.date);
                        if (!data.date) {
                            alert('Please enter a date');
                            return;
                        }
                    }
                    
                    // Add STM Course ID for enroll-course and enroll-buy
                    if (boxState === 'enroll-course' || boxState === 'enroll-buy') {
                        const stmCourseSelect = row.querySelector('.stm-course-select');
                        if (stmCourseSelect) {
                            data.stm_course_id = stmCourseSelect.value || '';
                            console.log('[CBM Debug] STM Course ID:', data.stm_course_id);
                        }
                    }
                    
                    if (boxState === 'countdown') {
                        data.launch_date = row.querySelector('.inline-edit-launch-date')?.value || '';
                    }
                    
                    statusSpan.className = 'save-status saving';
                    statusSpan.textContent = 'Saving...';
                    
                    console.log('[CBM Debug] Sending data to server:', data);
                    console.log('[CBM Debug] Box state:', boxState);
                    
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
                            console.error('[CBM Debug] Save failed:', result);
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
                const addNewRowBtn = document.getElementById('add-new-row');
                console.log('[CBM Debug] Add new row button element:', addNewRowBtn);
                
                if (addNewRowBtn) {
                    addNewRowBtn.addEventListener('click', function() {
                        console.log('[CBM Debug] Add new row button clicked!');
                        console.log('[CBM Debug] coursesData:', coursesData);
                        
                        // If no courses in group, open the add course modal
                        if (!coursesData || coursesData.length === 0) {
                            console.log('[CBM Debug] No courses in group, opening modal...');
                            // Check if we have the add course modal
                            const addCourseModal = document.getElementById('add-course-modal');
                            console.log('[CBM Debug] Modal element:', addCourseModal);
                            
                            if (addCourseModal) {
                                addCourseModal.style.display = 'block';
                            } else {
                                alert('Please add at least one course to the group first using the "Add Course to Group" button.');
                            }
                            return;
                        }
                        
                        // If we have courses, add a new date row for the first course
                        const firstCourse = coursesData[0];
                        console.log('[CBM Debug] Adding new row with course:', firstCourse);
                        
                        // Handle differently based on box state
                        if (currentBoxState === 'enroll-buy') {
                            // For enroll-buy, add a new enroll row to the enroll table
                            addTableRow(firstCourse, null, currentBoxState);
                        } else {
                            // For other states, add normally
                            addTableRow(firstCourse, null, currentBoxState);
                        }
                    });
                } else {
                    console.error('[CBM Debug] Add new row button not found!');
                }
                
                // Box state change handler
                const boxStateElement = document.getElementById('group-box-state');
                if (boxStateElement) {
                    boxStateElement.addEventListener('change', function() {
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
                }
                
                // Selling page change handler - auto-save when changed
                const sellingPageDropdown = document.getElementById('group-selling-page');
                if (sellingPageDropdown) {
                    console.log('[CBM Debug] Adding change handler to selling page dropdown');
                    console.log('[CBM Debug] Current selling page value before handler:', sellingPageDropdown.value);
                    
                    sellingPageDropdown.addEventListener('change', function() {
                        const sellingPageId = this.value;
                        const groupId = <?php echo $group_id; ?>;
                    
                    console.log('[CBM Debug] Selling page changed to:', sellingPageId);
                    console.log('[CBM Debug] Group ID for saving:', groupId);
                    
                    // Update all courses in the group with the selling page
                    if (coursesData && coursesData.length > 0) {
                        // Clear previous selling page flags
                        coursesData.forEach(course => {
                            delete course.is_selling_page;
                        });
                        
                        // Set new selling page flag
                        if (sellingPageId) {
                            const selectedCourse = coursesData.find(c => c.id == sellingPageId);
                            if (selectedCourse) {
                                selectedCourse.is_selling_page = '1';
                            }
                        }
                    }
                    
                    // Prepare request data
                    const requestBody = 'action=update_group_selling_page' +
                          '&group_id=' + groupId + 
                          '&selling_page_id=' + sellingPageId +
                          '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>';
                    
                    console.log('[CBM Debug] Sending AJAX request with:', requestBody);
                    
                    // Save selling page immediately
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: requestBody
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            console.log('[CBM Debug] Selling page updated successfully');
                            // Show brief success message
                            const selectEl = document.getElementById('group-selling-page');
                            const originalBg = selectEl.style.backgroundColor;
                            selectEl.style.backgroundColor = '#d4edda';
                            setTimeout(() => {
                                selectEl.style.backgroundColor = originalBg;
                            }, 1000);
                        } else {
                            console.error('[CBM Debug] Failed to update selling page:', result);
                            alert('Failed to update selling page');
                        }
                    })
                    .catch(error => {
                        console.error('[CBM Debug] Error updating selling page:', error);
                    });
                });
                } else {
                    console.error('[CBM Debug] Selling page dropdown not found!');
                }
                
                // Save all changes button
                const saveAllBtn = document.getElementById('save-all-changes');
                console.log('[CBM Debug] Save all button element:', saveAllBtn);
                
                if (saveAllBtn) {
                    saveAllBtn.addEventListener('click', function() {
                        console.log('[CBM Debug] Save all changes button clicked!');
                        
                        // First save the selling page if it exists
                        const sellingPageId = document.getElementById('group-selling-page').value;
                        
                        // Then save all rows with changes
                        const rowsWithChanges = document.querySelectorAll('tr.has-changes');
                        if (rowsWithChanges.length > 0) {
                            console.log('[CBM Debug] Saving ' + rowsWithChanges.length + ' rows with changes');
                            let savedCount = 0;
                            rowsWithChanges.forEach(row => {
                                saveRow(row);
                                savedCount++;
                            });
                            
                            // Show success message
                            const button = document.getElementById('save-all-changes');
                            const originalText = button.textContent;
                            button.textContent = '✓ Saved ' + savedCount + ' rows!';
                            button.style.backgroundColor = '#46b450';
                            
                            setTimeout(() => {
                                button.textContent = originalText;
                                button.style.backgroundColor = '';
                            }, 2000);
                            
                            return; // Exit early - we've saved individual rows
                        }
                        
                        const boxState = document.getElementById('group-box-state').value;
                        
                        // Collect data based on current state
                        let saveData = {
                            group_id: groupId,
                            box_state: boxState,
                            selling_page_id: sellingPageId,
                            nonce: '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                        };
                        
                        if (boxState === 'enroll-buy') {
                            // Collect buy table data
                            const buyRow = document.querySelector('#buy-table-body tr');
                            if (buyRow) {
                                const buyProductSelect = buyRow.querySelector('.buy-product-select, select');
                                const buyRegularPrice = buyRow.querySelector('.inline-edit-regular-price');
                                const buySalePrice = buyRow.querySelector('.inline-edit-sale-price');
                                const buyButtonText = buyRow.querySelector('.inline-edit-button-text');
                                
                                saveData.buy_product_id = buyProductSelect ? buyProductSelect.value : '';
                                saveData.buy_regular_price = buyRegularPrice ? buyRegularPrice.value : '';
                                saveData.buy_sale_price = buySalePrice ? buySalePrice.value : '';
                                saveData.buy_button_text = buyButtonText ? buyButtonText.value : 'Buy Course';
                            }
                            
                            // Collect enroll table data
                            const enrollRows = document.querySelectorAll('#table-body tr.course-row');
                            const enrollDates = [];
                            
                            enrollRows.forEach(row => {
                                const dateInput = row.querySelector('.inline-edit-date');
                                const productSelect = row.querySelector('.enroll-product-select, select');
                                const stockInput = row.querySelector('.inline-edit-stock');
                                const buttonText = row.querySelector('.inline-edit-button-text');
                                
                                if (dateInput && dateInput.value) {
                                    enrollDates.push({
                                        date: dateInput.value,
                                        product_id: productSelect ? productSelect.value : '',
                                        stock: stockInput ? stockInput.value : 20,
                                        button_text: buttonText ? buttonText.value : 'Enroll Now'
                                    });
                                }
                            });
                            
                            saveData.enroll_product_id = enrollRows.length > 0 && enrollRows[0].querySelector('.enroll-product-select, select') ? 
                                                         enrollRows[0].querySelector('.enroll-product-select, select').value : '';
                            saveData.enroll_dates = JSON.stringify(enrollDates);
                        } else {
                            // Collect data for other states
                            const tableRows = document.querySelectorAll('#table-body tr.course-row');
                            const dates = [];
                            
                            tableRows.forEach(row => {
                                const dateInput = row.querySelector('.inline-edit-date');
                                const stockInput = row.querySelector('.inline-edit-stock');
                                const buttonText = row.querySelector('.inline-edit-button-text');
                                
                                if (dateInput && dateInput.value) {
                                    dates.push({
                                        date: dateInput.value,
                                        stock: stockInput ? stockInput.value : 20,
                                        button_text: buttonText ? buttonText.value : ''
                                    });
                                }
                            });
                            
                            if (dates.length > 0) {
                                saveData.dates = JSON.stringify(dates);
                            }
                            
                            // Get product if visible
                            const productSelect = document.querySelector('#table-body .inline-edit-product');
                            if (productSelect) {
                                saveData.linked_product_id = productSelect.value;
                            }
                        }
                        
                        // Get first course ID if available
                        if (coursesData && coursesData.length > 0) {
                            saveData.course_id = coursesData[0].id;
                        }
                        
                        console.log('[CBM Debug] Save data:', saveData);
                        
                        // Create form data string
                        let formData = Object.keys(saveData).map(key => 
                            encodeURIComponent(key) + '=' + encodeURIComponent(saveData[key])
                        ).join('&');
                        
                        fetch(ajaxurl + '?action=save_group_settings', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: formData
                        })
                        .then(response => response.json())
                        .then(result => {
                            console.log('[CBM Debug] Save result:', result);
                            if (result.success) {
                                // Show success message
                                const button = document.getElementById('save-all-changes');
                                const originalText = button.textContent;
                                button.textContent = '✓ Saved!';
                                button.style.backgroundColor = '#46b450';
                                
                                setTimeout(() => {
                                    button.textContent = originalText;
                                    button.style.backgroundColor = '';
                                }, 2000);
                                
                                // Reload if needed
                                if (result.data && result.data.reload) {
                                    setTimeout(() => {
                                        location.reload();
                                    }, 1000);
                                }
                            } else {
                                alert('Error saving settings: ' + (result.data ? result.data : 'Unknown error'));
                            }
                        })
                        .catch(error => {
                            console.error('[CBM Debug] Save error:', error);
                            alert('Error saving settings');
                        });
                    });
                }
                
                // Global STM Course change handler - auto-save when changed
                const globalSTMCourse = document.getElementById('global-stm-course');
                if (globalSTMCourse) {
                    globalSTMCourse.addEventListener('change', function() {
                        const stmCourseId = this.value;
                        const statusSpan = document.getElementById('stm-save-status');
                        
                        console.log('[CBM Debug] Global STM course changed to:', stmCourseId);
                        
                        if (!coursesData || coursesData.length === 0) {
                            console.error('[CBM Debug] No course data available');
                            return;
                        }
                        
                        const courseId = coursesData[0].id;
                        
                        statusSpan.textContent = 'Saving...';
                        statusSpan.style.color = '#f0ad4e';
                        
                        // Update all rows with the new STM course ID
                        document.querySelectorAll('.inline-edit-stm-course').forEach(select => {
                            select.value = stmCourseId;
                            // Mark row as changed
                            const row = select.closest('tr');
                            if (row) {
                                row.classList.add('has-changes');
                            }
                        });
                        
                        // Save STM course for this course
                        fetch(ajaxurl + '?action=save_course_settings', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'course_id=' + courseId + 
                                  '&related_stm_course_id=' + stmCourseId +
                                  '&group_id=' + groupId +
                                  '&box_state=' + currentBoxState +
                                  '&instructors=' + JSON.stringify([]) +
                                  '&stock=0&dates=[]&selling_page_id=0&linked_product_id=0' +
                                  '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                statusSpan.textContent = '✓ Saved';
                                statusSpan.style.color = '#46b450';
                                
                                // Update course data
                                if (coursesData[0]) {
                                    coursesData[0].related_stm_course_id = stmCourseId;
                                }
                                
                                setTimeout(() => {
                                    statusSpan.textContent = '';
                                }, 3000);
                            } else {
                                statusSpan.textContent = '✗ Error';
                                statusSpan.style.color = '#d54e21';
                            }
                        })
                        .catch(error => {
                            console.error('[CBM Debug] Error saving STM course:', error);
                            statusSpan.textContent = '✗ Error';
                            statusSpan.style.color = '#d54e21';
                        });
                    });
                }
                
                // Save STM Course button (for enroll states) - Keep for manual save if needed
                const saveSTMBtn = document.getElementById('save-stm-course');
                if (saveSTMBtn) {
                    saveSTMBtn.addEventListener('click', function() {
                        const stmCourseId = document.getElementById('global-stm-course').value;
                        const statusSpan = document.getElementById('stm-save-status');
                        
                        if (!coursesData || coursesData.length === 0) {
                            alert('No course data available');
                            return;
                        }
                        
                        const courseId = coursesData[0].id;
                        
                        statusSpan.textContent = 'Saving...';
                        statusSpan.style.color = '#f0ad4e';
                        
                        // Save STM course for this course
                        fetch(ajaxurl + '?action=save_course_settings', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'course_id=' + courseId + 
                                  '&related_stm_course_id=' + stmCourseId +
                                  '&group_id=' + groupId +
                                  '&box_state=' + currentBoxState +
                                  '&instructors=' + JSON.stringify([]) +
                                  '&stock=0&dates=[]&selling_page_id=0&linked_product_id=0' +
                                  '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                statusSpan.textContent = '✓ Saved';
                                statusSpan.style.color = '#46b450';
                                
                                // Update coursesData
                                if (coursesData[0]) {
                                    coursesData[0].related_stm_course_id = stmCourseId;
                                }
                                
                                setTimeout(() => {
                                    statusSpan.textContent = '';
                                }, 3000);
                            } else {
                                statusSpan.textContent = '✗ Error';
                                statusSpan.style.color = '#d54e21';
                            }
                        })
                        .catch(error => {
                            console.error('Error saving STM course:', error);
                            statusSpan.textContent = '✗ Error';
                            statusSpan.style.color = '#d54e21';
                        });
                    });
                }
                
                // Apply group settings button
                const applySettingsBtn = document.getElementById('apply-group-settings');
                console.log('[CBM Debug] Apply settings button element:', applySettingsBtn);
                
                if (applySettingsBtn) {
                    applySettingsBtn.addEventListener('click', function() {
                        console.log('[CBM Debug] Apply settings button clicked!');
                        
                        const boxState = document.getElementById('group-box-state').value;
                        const instructorId = document.getElementById('group-instructor').value;
                        const sellingPageId = document.getElementById('group-selling-page').value;
                        
                        console.log('[CBM Debug] Settings to apply:', {boxState, instructorId, sellingPageId});
                        
                        if (!confirm('Apply these settings to all courses in the group?')) {
                            console.log('[CBM Debug] User cancelled apply settings');
                            return;
                        }
                        
                        console.log('[CBM Debug] Sending apply settings request...');
                        
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
                        console.log('[CBM Debug] Apply settings result:', result);
                        if (result.success) {
                            alert('Settings applied successfully');
                            location.reload();
                        } else {
                            alert('Error applying settings: ' + (result.data || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('[CBM Debug] Apply settings error:', error);
                        alert('Error applying settings. Check console for details.');
                    });
                    });
                } else {
                    console.error('[CBM Debug] Apply settings button not found!');
                }
                
                // Initial render
                renderTable(currentBoxState);
            });
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
