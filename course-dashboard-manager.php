<?php
/*
 * Plugin Name: Course Box Manager
 * Description: A comprehensive plugin to manage and display selectable boxes for course post types with dashboard control, countdowns, start date selection, and WooCommerce integration.
 * Version: 1.5.2
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
                                <button class="button view-courses" data-group-id="<?php echo esc_attr($group->term_id); ?>">
                                    View Courses
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

// Shortcode for displaying course boxes
add_shortcode('course_box_manager', 'course_box_manager_shortcode');
function course_box_manager_shortcode($atts) {
    global $post;
    $post_id = $post ? $post->ID : 0;
    
    error_log('[CBM Shortcode Debug] Shortcode called for post ID: ' . $post_id);
    
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
            $debug_info .= '<p><strong>Dates:</strong> <pre>' . print_r(get_field('course_dates', $post_id), true) . '</pre></p>';
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

    // Check if course_dates is empty and set box_state to 'waitlist'
    $dates = get_field('course_dates', $post_id) ?: [];
    if (empty($dates)) {
        update_post_meta($post_id, 'box_state', 'waitlist');
        error_log('No dates available for course ID ' . $post_id . ', setting box_state to waitlist');
    } else {
        // Optionally, revert to 'enroll-course' if dates are present and box_state is 'waitlist'
        $current_box_state = get_post_meta($post_id, 'box_state', true) ?: 'enroll-course';
        if ($current_box_state === 'waitlist') {
            update_post_meta($post_id, 'box_state', 'enroll-course');
            error_log('Dates found for course ID ' . $post_id . ', setting box_state to enroll-course');
        }
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
