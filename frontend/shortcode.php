<?php
/**
 * Course Dashboard Manager - Frontend Shortcode
 * 
 * Handles the [course_box_manager] shortcode display
 */

namespace CourseBoxManager\Frontend;

use CourseBoxManager\Boxes\BoxFactory;

// Register shortcode
add_shortcode('course_box_manager', __NAMESPACE__ . '\\course_box_manager_shortcode');

/**
 * Main shortcode handler
 */
function course_box_manager_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => get_the_ID(),
    ), $atts);
    
    $course_id = intval($atts['id']);
    
    // Enqueue required assets
    enqueue_frontend_assets();
    
    // Get the box instance and render
    $box = BoxFactory::create($course_id);
    
    if ($box) {
        return $box->render();
    }
    
    return '<div class="course-box-error">Course box configuration not found.</div>';
}

/**
 * Enqueue frontend assets
 */
function enqueue_frontend_assets() {
    // Enqueue styles
    wp_enqueue_style(
        'course-box-frontend',
        CBM_PLUGIN_URL . 'assets/css/frontend.css',
        array(),
        CBM_VERSION
    );
    
    // Enqueue scripts
    wp_enqueue_script(
        'course-box-frontend',
        CBM_PLUGIN_URL . 'assets/js/frontend.js',
        array('jquery'),
        CBM_VERSION,
        true
    );
    
    // Localize script
    wp_localize_script('course-box-frontend', 'cbm_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('woocommerce-add-to-cart'),
        'cart_url' => wc_get_cart_url(),
        'is_funnelkit_active' => defined('FKCART_VERSION') || class_exists('FKCart')
    ));
}

/**
 * Register additional shortcode for selectable boxes
 */
add_shortcode('selectable_boxes', __NAMESPACE__ . '\\selectable_boxes_shortcode');

function selectable_boxes_shortcode($atts) {
    $atts = shortcode_atts(array(
        'course_id' => get_the_ID(),
    ), $atts);
    
    return course_box_manager_shortcode(array('id' => $atts['course_id']));
}