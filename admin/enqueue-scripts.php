<?php
/**
 * Course Dashboard Manager - Admin Scripts Enqueue
 *
 * Handles enqueuing of JavaScript and CSS files for admin pages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue scripts and styles for admin pages
 */
function cbm_admin_enqueue_scripts($hook) {
    // Only load on our plugin pages
    if ($hook !== 'toplevel_page_course-box-tables' && $hook !== 'course-tables_page_course-box-tables') {
        return;
    }

    // Check if we're on the group detail view
    $is_group_view = isset($_GET['page']) && $_GET['page'] === 'course-box-tables' && isset($_GET['group_id']);
    $group_id = $is_group_view ? intval($_GET['group_id']) : 0;

    if ($is_group_view && $group_id) {
        // Enqueue Tables Manager JavaScript
        wp_enqueue_script(
            'cbm-tables-manager',
            CBM_PLUGIN_URL . 'admin/assets/js/tables-manager.js',
            array('jquery'),
            CBM_VERSION,
            true
        );

        // Prepare data for JavaScript
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

                // Debug log to see what data we're loading
                if (!empty($dates)) {
                    error_log('[CBM Debug] Loading dates for course ' . $course_id . ': ' . json_encode($dates));
                }

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

        // Get STM Courses
        $stm_courses_list = get_posts([
            'post_type' => 'stm-courses',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish'
        ]);

        $stm_courses = [];
        foreach ($stm_courses_list as $stm_course) {
            $stm_courses[] = [
                'id' => $stm_course->ID,
                'title' => $stm_course->post_title
            ];
        }

        // Localize script with data
        wp_localize_script('cbm-tables-manager', 'cbmTablesData', array(
            'coursesData' => $courses_json,
            'allProducts' => $all_products,
            'groupId' => $group_id,
            'stmCourses' => $stm_courses,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('course_box_nonce')
        ));
    }
}
add_action('admin_enqueue_scripts', 'cbm_admin_enqueue_scripts');
