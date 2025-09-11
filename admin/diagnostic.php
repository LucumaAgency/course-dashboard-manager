<?php
/**
 * Course Dashboard Manager - Diagnostic Tool
 * 
 * This page helps diagnose issues with the plugin configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

// Only allow admins
if (!current_user_can('manage_options')) {
    wp_die('You do not have permission to access this page.');
}

?>
<div class="wrap">
    <h1>Course Dashboard Manager - Diagnostic Report</h1>
    
    <style>
        .diagnostic-section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .diagnostic-ok { color: green; font-weight: bold; }
        .diagnostic-warning { color: orange; font-weight: bold; }
        .diagnostic-error { color: red; font-weight: bold; }
        .diagnostic-data { 
            background: #f5f5f5; 
            padding: 10px; 
            margin: 10px 0;
            border-left: 4px solid #0073aa;
            font-family: monospace;
            white-space: pre-wrap;
        }
        table.diagnostic-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        table.diagnostic-table th,
        table.diagnostic-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        table.diagnostic-table th {
            background: #f0f0f0;
        }
    </style>
    
    <?php
    // 1. Check Course Post Type
    echo '<div class="diagnostic-section">';
    echo '<h2>1. Course Post Type</h2>';
    
    $courses = get_posts([
        'post_type' => 'course',
        'posts_per_page' => -1,
        'post_status' => 'any'
    ]);
    
    if (!empty($courses)) {
        echo '<p class="diagnostic-ok">✓ Found ' . count($courses) . ' courses</p>';
        echo '<table class="diagnostic-table">';
        echo '<tr><th>ID</th><th>Title</th><th>Box State</th><th>Product ID</th><th>STM Course</th><th>Group</th></tr>';
        
        foreach ($courses as $course) {
            $box_state = get_post_meta($course->ID, 'box_state', true);
            $linked_product = get_post_meta($course->ID, 'linked_product_id', true);
            $buy_product = get_post_meta($course->ID, 'buy_product_id', true);
            $enroll_product = get_post_meta($course->ID, 'enroll_product_id', true);
            $stm_course = get_post_meta($course->ID, 'related_stm_course_id', true);
            $is_selling = get_post_meta($course->ID, 'is_selling_page', true);
            
            $groups = wp_get_post_terms($course->ID, 'course_group');
            $group_names = array_map(function($g) { return $g->name; }, $groups);
            
            $product_display = '';
            if ($linked_product) $product_display .= 'L:' . $linked_product . ' ';
            if ($buy_product) $product_display .= 'B:' . $buy_product . ' ';
            if ($enroll_product) $product_display .= 'E:' . $enroll_product;
            
            echo '<tr>';
            echo '<td>' . $course->ID . '</td>';
            echo '<td>' . $course->post_title . ($is_selling ? ' <span class="diagnostic-ok">[SELLING PAGE]</span>' : '') . '</td>';
            echo '<td>' . ($box_state ?: '<span class="diagnostic-warning">Not set</span>') . '</td>';
            echo '<td>' . ($product_display ?: '<span class="diagnostic-error">None</span>') . '</td>';
            echo '<td>' . ($stm_course ?: '<span class="diagnostic-warning">None</span>') . '</td>';
            echo '<td>' . implode(', ', $group_names) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p class="diagnostic-error">✗ No courses found</p>';
    }
    echo '</div>';
    
    // 2. Check Course Groups
    echo '<div class="diagnostic-section">';
    echo '<h2>2. Course Groups</h2>';
    
    $groups = get_terms([
        'taxonomy' => 'course_group',
        'hide_empty' => false
    ]);
    
    if (!empty($groups) && !is_wp_error($groups)) {
        echo '<p class="diagnostic-ok">✓ Found ' . count($groups) . ' groups</p>';
        echo '<table class="diagnostic-table">';
        echo '<tr><th>ID</th><th>Name</th><th>Courses</th><th>Selling Page</th></tr>';
        
        foreach ($groups as $group) {
            $group_courses = get_posts([
                'post_type' => 'course',
                'posts_per_page' => -1,
                'tax_query' => [
                    [
                        'taxonomy' => 'course_group',
                        'field' => 'term_id',
                        'terms' => $group->term_id
                    ]
                ]
            ]);
            
            $selling_page = null;
            foreach ($group_courses as $gc) {
                if (get_post_meta($gc->ID, 'is_selling_page', true)) {
                    $selling_page = $gc;
                    break;
                }
            }
            
            echo '<tr>';
            echo '<td>' . $group->term_id . '</td>';
            echo '<td>' . $group->name . '</td>';
            echo '<td>' . count($group_courses) . '</td>';
            echo '<td>' . ($selling_page ? $selling_page->post_title : '<span class="diagnostic-warning">None set</span>') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p class="diagnostic-error">✗ No groups found</p>';
    }
    echo '</div>';
    
    // 3. Check WooCommerce Products
    echo '<div class="diagnostic-section">';
    echo '<h2>3. WooCommerce Products</h2>';
    
    if (class_exists('WooCommerce')) {
        echo '<p class="diagnostic-ok">✓ WooCommerce is active</p>';
        
        $products = wc_get_products(['limit' => -1]);
        $linked_products = [];
        
        foreach ($products as $product) {
            $stm_course_id = get_post_meta($product->get_id(), 'stm_lms_course_id', true);
            $is_stm_product = get_post_meta($product->get_id(), 'stm_lms_product', true);
            
            if ($stm_course_id || $is_stm_product) {
                $linked_products[] = [
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'stm_course' => $stm_course_id,
                    'price' => $product->get_price()
                ];
            }
        }
        
        if (!empty($linked_products)) {
            echo '<p>Found ' . count($linked_products) . ' products linked to STM courses:</p>';
            echo '<table class="diagnostic-table">';
            echo '<tr><th>Product ID</th><th>Name</th><th>STM Course ID</th><th>Price</th></tr>';
            foreach ($linked_products as $lp) {
                echo '<tr>';
                echo '<td>' . $lp['id'] . '</td>';
                echo '<td>' . $lp['name'] . '</td>';
                echo '<td>' . ($lp['stm_course'] ?: 'Not set') . '</td>';
                echo '<td>' . wc_price($lp['price']) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p class="diagnostic-warning">⚠ No products linked to STM courses</p>';
        }
    } else {
        echo '<p class="diagnostic-error">✗ WooCommerce is not active</p>';
    }
    echo '</div>';
    
    // 4. Check MasterStudy LMS
    echo '<div class="diagnostic-section">';
    echo '<h2>4. MasterStudy LMS Integration</h2>';
    
    if (defined('STM_LMS_FILE') || function_exists('stm_lms_add_user_course')) {
        echo '<p class="diagnostic-ok">✓ MasterStudy LMS is active</p>';
        
        // Check for STM courses
        $stm_courses = get_posts([
            'post_type' => ['stm-courses', 'stm_lms_courses', 'stm-course', 'stm_course'],
            'posts_per_page' => 10,
            'post_status' => 'publish'
        ]);
        
        if (!empty($stm_courses)) {
            echo '<p class="diagnostic-ok">✓ Found ' . count($stm_courses) . ' STM courses</p>';
            echo '<table class="diagnostic-table">';
            echo '<tr><th>STM Course ID</th><th>Title</th><th>Linked Products</th></tr>';
            
            foreach ($stm_courses as $stm_course) {
                $product_ids = get_post_meta($stm_course->ID, 'stm_lms_product_ids', true);
                $single_product = get_post_meta($stm_course->ID, 'stm_lms_product_id', true);
                
                $products_display = '';
                if (is_array($product_ids)) {
                    $products_display = implode(', ', $product_ids);
                } elseif ($single_product) {
                    $products_display = $single_product;
                }
                
                echo '<tr>';
                echo '<td>' . $stm_course->ID . '</td>';
                echo '<td>' . $stm_course->post_title . '</td>';
                echo '<td>' . ($products_display ?: '<span class="diagnostic-warning">None</span>') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p class="diagnostic-warning">⚠ No STM courses found</p>';
        }
        
        // Check database tables
        global $wpdb;
        $tables_to_check = [
            'stm_lms_user_courses' => 'User enrollments',
            'stm_lms_order_items' => 'Order items',
            'stm_lms_orders' => 'Orders'
        ];
        
        echo '<h3>Database Tables:</h3>';
        echo '<ul>';
        foreach ($tables_to_check as $table => $description) {
            $full_table = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'") == $full_table;
            if ($exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table");
                echo '<li class="diagnostic-ok">✓ ' . $full_table . ' (' . $description . ') - ' . $count . ' records</li>';
            } else {
                echo '<li class="diagnostic-warning">⚠ ' . $full_table . ' (' . $description . ') - Table does not exist</li>';
            }
        }
        echo '</ul>';
        
    } else {
        echo '<p class="diagnostic-error">✗ MasterStudy LMS is not active</p>';
    }
    echo '</div>';
    
    // 5. Check Course Dates and Stock
    echo '<div class="diagnostic-section">';
    echo '<h2>5. Course Dates and Stock</h2>';
    
    foreach ($courses as $course) {
        $dates = get_post_meta($course->ID, 'course_dates', true);
        $stock = get_post_meta($course->ID, 'course_stock', true);
        
        if (!empty($dates) || $stock) {
            echo '<h3>' . $course->post_title . '</h3>';
            
            if ($stock) {
                echo '<p>Total Stock: ' . $stock . '</p>';
            }
            
            if (!empty($dates) && is_array($dates)) {
                echo '<table class="diagnostic-table">';
                echo '<tr><th>Date</th><th>Stock</th><th>Button Text</th><th>STM Course</th></tr>';
                foreach ($dates as $date) {
                    echo '<tr>';
                    echo '<td>' . (isset($date['date']) ? $date['date'] : 'Not set') . '</td>';
                    echo '<td>' . (isset($date['stock']) ? $date['stock'] : 'Not set') . '</td>';
                    echo '<td>' . (isset($date['button_text']) ? $date['button_text'] : 'Not set') . '</td>';
                    echo '<td>' . (isset($date['stm_course_id']) ? $date['stm_course_id'] : 'Not set') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            }
        }
    }
    echo '</div>';
    
    // 6. Check Recent Orders
    echo '<div class="diagnostic-section">';
    echo '<h2>6. Recent Orders (Last 10)</h2>';
    
    if (class_exists('WooCommerce')) {
        $orders = wc_get_orders(['limit' => 10, 'orderby' => 'date', 'order' => 'DESC']);
        
        if (!empty($orders)) {
            echo '<table class="diagnostic-table">';
            echo '<tr><th>Order ID</th><th>Date</th><th>Status</th><th>Products</th><th>Course Date Meta</th></tr>';
            
            foreach ($orders as $order) {
                $items_text = [];
                foreach ($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    $course_date = $item->get_meta('Course Start Date');
                    $items_text[] = $item->get_name() . ' (ID: ' . $product_id . ')' . 
                                   ($course_date ? ' [Date: ' . $course_date . ']' : '');
                }
                
                echo '<tr>';
                echo '<td>#' . $order->get_id() . '</td>';
                echo '<td>' . $order->get_date_created()->format('Y-m-d H:i') . '</td>';
                echo '<td>' . $order->get_status() . '</td>';
                echo '<td>' . implode('<br>', $items_text) . '</td>';
                echo '<td>' . ($course_date ?? 'None') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p>No recent orders found</p>';
        }
    }
    echo '</div>';
    
    // 7. Configuration Issues Summary
    echo '<div class="diagnostic-section">';
    echo '<h2>7. Potential Issues Found</h2>';
    echo '<ul>';
    
    $issues = [];
    
    // Check for courses without products
    foreach ($courses as $course) {
        $has_product = get_post_meta($course->ID, 'linked_product_id', true) ||
                      get_post_meta($course->ID, 'buy_product_id', true) ||
                      get_post_meta($course->ID, 'enroll_product_id', true);
        if (!$has_product) {
            $issues[] = 'Course "' . $course->post_title . '" has no linked WooCommerce products';
        }
        
        $box_state = get_post_meta($course->ID, 'box_state', true);
        if (!$box_state) {
            $issues[] = 'Course "' . $course->post_title . '" has no box_state set';
        }
        
        if ($box_state === 'enroll-course' || $box_state === 'enroll-buy') {
            $stm_course = get_post_meta($course->ID, 'related_stm_course_id', true);
            if (!$stm_course) {
                $issues[] = 'Course "' . $course->post_title . '" needs STM course for enrollment but none is set';
            }
        }
    }
    
    // Check for groups without selling pages
    foreach ($groups as $group) {
        $has_selling_page = false;
        $group_courses = get_posts([
            'post_type' => 'course',
            'posts_per_page' => -1,
            'tax_query' => [
                [
                    'taxonomy' => 'course_group',
                    'field' => 'term_id',
                    'terms' => $group->term_id
                ]
            ]
        ]);
        
        foreach ($group_courses as $gc) {
            if (get_post_meta($gc->ID, 'is_selling_page', true)) {
                $has_selling_page = true;
                break;
            }
        }
        
        if (!$has_selling_page && count($group_courses) > 0) {
            $issues[] = 'Group "' . $group->name . '" has no selling page set';
        }
    }
    
    if (empty($issues)) {
        echo '<li class="diagnostic-ok">✓ No obvious configuration issues found</li>';
    } else {
        foreach ($issues as $issue) {
            echo '<li class="diagnostic-error">✗ ' . $issue . '</li>';
        }
    }
    
    echo '</ul>';
    echo '</div>';
    ?>
    
    <div class="diagnostic-section">
        <h2>Quick Actions</h2>
        <p>
            <a href="<?php echo admin_url('edit.php?post_type=course'); ?>" class="button">View All Courses</a>
            <a href="<?php echo admin_url('admin.php?page=course-box-tables'); ?>" class="button">Course Tables</a>
            <a href="<?php echo admin_url('admin.php?page=course-box-settings'); ?>" class="button">Plugin Settings</a>
            <?php if (class_exists('WooCommerce')): ?>
            <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="button">WooCommerce Products</a>
            <?php endif; ?>
        </p>
        
        <?php if (isset($_GET['repair_links']) && $_GET['repair_links'] == '1'): ?>
            <?php
            // Repair STM course links
            $repair_count = 0;
            $courses_repaired = [];
            
            foreach ($courses as $course) {
                $stm_course_id = get_post_meta($course->ID, 'related_stm_course_id', true);
                if ($stm_course_id) {
                    // Get all products linked to this course
                    $linked_product = get_post_meta($course->ID, 'linked_product_id', true);
                    $buy_product = get_post_meta($course->ID, 'buy_product_id', true);
                    $enroll_product = get_post_meta($course->ID, 'enroll_product_id', true);
                    
                    $products = array_filter([$linked_product, $buy_product, $enroll_product]);
                    
                    foreach ($products as $product_id) {
                        if ($product_id) {
                            // Update product meta to link to STM course
                            update_post_meta($product_id, 'stm_lms_course_id', $stm_course_id);
                            update_post_meta($product_id, '_stm_lms_course_id', $stm_course_id);
                            update_post_meta($product_id, 'stm_lms_product', 'yes');
                            
                            // Add to course IDs array
                            $course_ids = get_post_meta($product_id, 'stm_lms_course_ids', true);
                            if (!is_array($course_ids)) {
                                $course_ids = [];
                            }
                            if (!in_array($stm_course_id, $course_ids)) {
                                $course_ids[] = $stm_course_id;
                                update_post_meta($product_id, 'stm_lms_course_ids', $course_ids);
                            }
                            
                            // Update STM course to link back to product
                            update_post_meta($stm_course_id, 'stm_lms_product_id', $product_id);
                            
                            $product_ids = get_post_meta($stm_course_id, 'stm_lms_product_ids', true);
                            if (!is_array($product_ids)) {
                                $product_ids = [];
                            }
                            if (!in_array($product_id, $product_ids)) {
                                $product_ids[] = $product_id;
                                update_post_meta($stm_course_id, 'stm_lms_product_ids', $product_ids);
                            }
                            
                            $repair_count++;
                            $courses_repaired[] = $course->post_title . ' (Product: ' . $product_id . ' → STM: ' . $stm_course_id . ')';
                        }
                    }
                }
            }
            
            if ($repair_count > 0) {
                echo '<div class="notice notice-success"><p>';
                echo '<strong>✓ Repaired ' . $repair_count . ' product-to-STM-course links:</strong><br>';
                echo implode('<br>', $courses_repaired);
                echo '</p></div>';
            } else {
                echo '<div class="notice notice-info"><p>No links needed repair.</p></div>';
            }
            
            // Force the enrollment sync to update
            delete_option('cbm_products_stm_linked_v2');
            ?>
        <?php endif; ?>
        
        <h3>Repair Tools</h3>
        <p>
            <a href="<?php echo admin_url('admin.php?page=course-box-diagnostic&repair_links=1'); ?>" 
               class="button button-primary" 
               onclick="return confirm('This will repair all STM course to WooCommerce product links. Continue?');">
                Repair STM Course Links
            </a>
            <br><small>This will sync all product-to-STM-course connections based on your current configuration.</small>
        </p>
    </div>
</div>