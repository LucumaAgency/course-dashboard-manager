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
add_action('admin_menu', 'course_box_manager_menu');
function course_box_manager_menu() {
    add_menu_page(
        'Course Box Manager',
        'Course Boxes',
        'edit_posts', // Instructors (with edit_posts capability) can view
        'course-box-manager',
        'course_box_manager_page',
        'dashicons-list-view',
        20
    );
}

// Helper function to calculate seats sold for a course
function calculate_seats_sold($product_id, $date = null) {
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
                if ($date) {
                    $start_date = $item->get_meta('Start Date');
                    if ($start_date === $date) {
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
                        $course_stock = get_field('course_stock', $course_id) ?: 0;
                        $dates = get_field('course_dates', $course_id) ?: [];
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
                                <button class="button delete-course" data-course-id="<?php echo esc_attr($course_id); ?>">Delete</button>
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
            $course_stock = get_field('course_stock', $course_id) ?: 0;
            $dates = get_field('course_dates', $course_id) ?: [];
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
                <?php 
                // Calculate seats availability for display
                $product_id = get_post_meta($course_id, 'linked_product_id', true);
                if ($product_id) {
                    ?>
                    <div style="background: #f1f1f1; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                        <h3 style="margin-top: 0;">Seats Availability</h3>
                        <?php
                        if (!empty($dates)) {
                            echo '<table style="width: 100%; border-collapse: collapse;">';
                            echo '<thead><tr style="border-bottom: 1px solid #ccc;">';
                            echo '<th style="text-align: left; padding: 5px;">Date</th>';
                            echo '<th style="text-align: center; padding: 5px;">Total Seats</th>';
                            echo '<th style="text-align: center; padding: 5px;">Sold</th>';
                            echo '<th style="text-align: center; padding: 5px;">Available</th>';
                            echo '</tr></thead><tbody>';
                            
                            $total_seats_all = 0;
                            $total_sold_all = 0;
                            $total_available_all = 0;
                            
                            foreach ($dates as $date) {
                                if (isset($date['date'])) {
                                    $stock = isset($date['stock']) ? intval($date['stock']) : $course_stock;
                                    $sold = $product_id ? calculate_seats_sold($product_id, $date['date']) : 0;
                                    $available = max(0, $stock - $sold);
                                    
                                    $total_seats_all += $stock;
                                    $total_sold_all += $sold;
                                    $total_available_all += $available;
                                    
                                    $row_class = '';
                                    if ($stock > 0) {
                                        $percentage = ($available / $stock) * 100;
                                        if ($percentage <= 20) $row_class = 'low-seats';
                                        elseif ($percentage <= 50) $row_class = 'medium-seats';
                                    }
                                    
                                    echo '<tr>';
                                    echo '<td style="padding: 5px;">' . esc_html($date['date']) . '</td>';
                                    echo '<td style="text-align: center; padding: 5px;">' . esc_html($stock) . '</td>';
                                    echo '<td style="text-align: center; padding: 5px;">' . esc_html($sold) . '</td>';
                                    echo '<td style="text-align: center; padding: 5px;" class="' . esc_attr($row_class) . '"><strong>' . esc_html($available) . '</strong></td>';
                                    echo '</tr>';
                                }
                            }
                            
                            echo '<tr style="border-top: 2px solid #333; font-weight: bold;">';
                            echo '<td style="padding: 5px;">TOTAL</td>';
                            echo '<td style="text-align: center; padding: 5px;">' . esc_html($total_seats_all) . '</td>';
                            echo '<td style="text-align: center; padding: 5px;">' . esc_html($total_sold_all) . '</td>';
                            echo '<td style="text-align: center; padding: 5px;">' . esc_html($total_available_all) . '</td>';
                            echo '</tr>';
                            
                            echo '</tbody></table>';
                        } else {
                            // Single stock for all dates
                            $sold = calculate_seats_sold($product_id);
                            $available = max(0, $course_stock - $sold);
                            
                            echo '<p><strong>Total Seats:</strong> ' . esc_html($course_stock) . '</p>';
                            echo '<p><strong>Seats Sold:</strong> ' . esc_html($sold) . '</p>';
                            echo '<p><strong>Seats Available:</strong> <span class="';
                            
                            if ($course_stock > 0) {
                                $percentage = ($available / $course_stock) * 100;
                                if ($percentage <= 20) echo 'low-seats';
                                elseif ($percentage <= 50) echo 'medium-seats';
                            }
                            
                            echo '">' . esc_html($available) . '</span></p>';
                        }
                        ?>
                    </div>
                    <?php
                }
                ?>
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
                        <th><label>Default Stock</label></th>
                        <td>
                            <input type="number" class="course-stock-input" data-course-id="<?php echo esc_attr($course_id); ?>" value="<?php echo esc_attr($course_stock); ?>" min="0">
                            <p style="font-size: 12px; color: #666; margin-top: 5px;">Default stock for new dates</p>
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
                                        <span style="width: 100px;">Actions</span>
                                    </div>
                                    <?php 
                                    foreach ($dates as $index => $date) : 
                                        $date_stock = isset($date['stock']) ? intval($date['stock']) : $webinar_stock;
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
            .course-stock-input {
                width: 100px;
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
                        const stock = document.querySelector(`.course-stock-input[data-course-id="${courseId}"]`)?.value || '';
                        const dateElements = document.querySelectorAll(`.date-list[data-course-id="${courseId}"] .date-stock-row`);
                        const dates = [];
                        dateElements.forEach(row => {
                            const dateInput = row.querySelector('.course-date');
                            const stockInput = row.querySelector('.course-stock');
                            if (dateInput && dateInput.value.trim() !== '') {
                                dates.push({
                                    date: dateInput.value.trim(),
                                    stock: stockInput ? stockInput.value : stock
                                });
                            }
                        });
                        const sellingPageId = document.querySelector(`#selling-page[data-course-id="${courseId}"]`).value;
                        
                        fetch(ajaxurl + '?action=save_course_settings', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'course_id=' + courseId + '&group_id=' + groupId + '&box_state=' + boxState + '&instructors=' + encodeURIComponent(JSON.stringify(instructors)) + '&stock=' + stock + '&dates=' + encodeURIComponent(JSON.stringify(dates)) + '&selling_page_id=' + sellingPageId + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Settings saved successfully!');
                                location.href = '?page=course-box-manager';
                            } else {
                                alert('Error: ' + data.data);
                            }
                        });
                    });
                });

                // Delete Course
                document.querySelectorAll('.delete-course').forEach(button => {
                    button.addEventListener('click', function() {
                        if (!confirm('Are you sure you want to delete this course?')) return;
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
                    const defaultStock = document.querySelector('.course-stock-input').value || 20;
                    
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
        update_field('course_instructors', $instructors, $course_id); // Update ACF field if exists
        
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

    update_post_meta($course_id, 'box_state', $box_state);
    update_post_meta($course_id, 'course_instructors', $instructors);
    $product_id = get_post_meta($course_id, 'linked_product_id', true);
    if ($product_id && $stock !== '') {
        update_post_meta($product_id, '_stock', $stock);
        update_post_meta($product_id, '_manage_stock', 'yes');
        if ($group_id) {
            wp_set_post_terms($product_id, [$group_id], 'course_group');
        } else {
            wp_set_post_terms($product_id, [], 'course_group');
        }
    }
    if ($dates) {
        // Process dates with stock information
        $formatted_dates = [];
        foreach ($dates as $date_info) {
            if (is_array($date_info) && isset($date_info['date'])) {
                $formatted_dates[] = [
                    'date' => $date_info['date'],
                    'stock' => isset($date_info['stock']) ? intval($date_info['stock']) : $stock
                ];
            } elseif (is_string($date_info)) {
                // Legacy support for simple date strings
                $formatted_dates[] = ['date' => $date_info, 'stock' => $stock];
            }
        }
        update_field('course_dates', $formatted_dates, $course_id);
    } else {
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
    $course_stock = get_field('course_stock', $course_id) ?: 0;
    
    foreach ($dates as $date_info) {
        if (isset($date_info['date']) && !empty($date_info['date'])) {
            $formatted_dates[] = [
                'date' => $date_info['date'],
                'stock' => isset($date_info['stock']) ? intval($date_info['stock']) : $course_stock
            ];
        }
    }
    
    // Update ACF field
    update_field('course_dates', $formatted_dates, $course_id);
    
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

// Shortcode to render boxes
function course_box_manager_shortcode() {
    global $post;
    $post_id = $post ? $post->ID : 0;
    
    // Don't render in Elementor editor
    if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->preview->is_preview_mode()) {
        return '<div style="padding: 20px; background: #f0f0f0; text-align: center;">Course Box Manager - Boxes will appear here on the live page</div>';
    }
    
    $terms = wp_get_post_terms($post_id, 'course_group');
    $group_id = !empty($terms) ? $terms[0]->term_id : 0;
    
    try {
        $output = CourseBoxManager\BoxRenderer::render_boxes_for_group($group_id, $post_id);
        return $output;
    } catch (\Exception $e) {
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
        $product->set_price(get_field('course_price', $post_id) ?: 749.99);
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
    $instructors = get_field('course_instructors', $post_id) ?: [];
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
