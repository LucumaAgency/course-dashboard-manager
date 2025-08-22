<?php
/**
 * Course Dashboard Manager - Enrollment Sync
 * 
 * Automatically enrolls users in MasterStudy LMS courses based on WooCommerce product purchases
 */

namespace CourseBoxManager;

class EnrollmentSync {
    
    public function __construct() {
        // Hook into WooCommerce order completion
        add_action('woocommerce_order_status_completed', [$this, 'enroll_user_on_purchase'], 10, 1);
        add_action('woocommerce_payment_complete', [$this, 'enroll_user_on_purchase'], 10, 1);
        
        // Also handle processing status for immediate enrollment
        add_action('woocommerce_order_status_processing', [$this, 'enroll_user_on_purchase'], 10, 1);
    }
    
    /**
     * Enroll user in STM Course when they purchase a related product
     */
    public function enroll_user_on_purchase($order_id) {
        error_log('[CBM Enrollment Sync] Processing order ID: ' . $order_id);
        
        // Load order
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('[CBM Enrollment Sync] Failed to load order ID: ' . $order_id);
            return;
        }
        
        // Get user ID
        $user_id = $order->get_user_id();
        if (!$user_id) {
            error_log('[CBM Enrollment Sync] No user associated with order ID: ' . $order_id);
            return;
        }
        
        error_log('[CBM Enrollment Sync] User ID: ' . $user_id);
        
        // Check if MasterStudy LMS is active
        if (!defined('STM_LMS_FILE') && !function_exists('stm_lms_add_user_course')) {
            error_log('[CBM Enrollment Sync] MasterStudy LMS not active or function not available');
            return;
        }
        
        // Process each order item
        $enrolled_courses = [];
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            error_log('[CBM Enrollment Sync] Checking product ID: ' . $product_id);
            
            // Get related STM Course ID from product meta
            $stm_course_id = get_post_meta($product_id, 'related_stm_course_id', true);
            
            if (!$stm_course_id) {
                error_log('[CBM Enrollment Sync] No STM Course linked to product ID: ' . $product_id);
                continue;
            }
            
            error_log('[CBM Enrollment Sync] Found STM Course ID: ' . $stm_course_id . ' for product ID: ' . $product_id);
            
            // Verify course exists and is published
            $course = get_post($stm_course_id);
            if (!$course || $course->post_status !== 'publish') {
                error_log('[CBM Enrollment Sync] Invalid or unpublished STM Course ID: ' . $stm_course_id);
                continue;
            }
            
            // Check if it's a valid STM course post type
            $possible_stm_types = ['stm-courses', 'stm_lms_courses', 'stm-course', 'stm_course'];
            if (!in_array($course->post_type, $possible_stm_types)) {
                error_log('[CBM Enrollment Sync] Invalid post type for course ID: ' . $stm_course_id . ' (type: ' . $course->post_type . ')');
                continue;
            }
            
            // Check if user is already enrolled
            if ($this->is_user_enrolled($user_id, $stm_course_id)) {
                error_log('[CBM Enrollment Sync] User ' . $user_id . ' already enrolled in course ' . $stm_course_id);
                continue;
            }
            
            // Enroll the user
            if ($this->enroll_user_in_course($user_id, $stm_course_id)) {
                error_log('[CBM Enrollment Sync] Successfully enrolled user ' . $user_id . ' in course ' . $stm_course_id);
                $enrolled_courses[] = $stm_course_id;
            } else {
                error_log('[CBM Enrollment Sync] Failed to enroll user ' . $user_id . ' in course ' . $stm_course_id);
            }
        }
        
        if (!empty($enrolled_courses)) {
            error_log('[CBM Enrollment Sync] Order ' . $order_id . ' - User enrolled in courses: ' . implode(', ', $enrolled_courses));
            
            // Add order note
            $course_titles = array_map(function($id) { return get_the_title($id); }, $enrolled_courses);
            $order->add_order_note('User automatically enrolled in LMS courses: ' . implode(', ', $course_titles));
        } else {
            error_log('[CBM Enrollment Sync] Order ' . $order_id . ' - No courses to enroll');
        }
    }
    
    /**
     * Check if user is already enrolled in a course
     */
    private function is_user_enrolled($user_id, $course_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'stm_lms_user_courses';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            error_log('[CBM Enrollment Sync] Table ' . $table . ' does not exist');
            return false;
        }
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %d AND course_id = %d",
            $user_id,
            $course_id
        ));
        
        return $existing > 0;
    }
    
    /**
     * Enroll user in STM Course
     */
    private function enroll_user_in_course($user_id, $course_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'stm_lms_user_courses';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            error_log('[CBM Enrollment Sync] Cannot enroll - table ' . $table . ' does not exist');
            
            // Try using MasterStudy function if available
            if (function_exists('stm_lms_add_user_course')) {
                stm_lms_add_user_course($user_id, $course_id, 0, 0);
                return true;
            }
            
            return false;
        }
        
        // Prepare enrollment data
        $data = [
            'user_id' => $user_id,
            'course_id' => $course_id,
            'progress_percent' => 0,
            'start_time' => current_time('mysql'),
            'status' => 'enrolled'
        ];
        
        // Insert enrollment record
        $result = $wpdb->insert($table, $data, ['%d', '%d', '%d', '%s', '%s']);
        
        if ($result === false) {
            error_log('[CBM Enrollment Sync] Database error: ' . $wpdb->last_error);
            
            // Try using MasterStudy function as fallback
            if (function_exists('stm_lms_add_user_course')) {
                stm_lms_add_user_course($user_id, $course_id, 0, 0);
                return true;
            }
            
            return false;
        }
        
        // Also update user meta for compatibility
        $user_courses = get_user_meta($user_id, 'stm_lms_user_courses', true);
        if (!is_array($user_courses)) {
            $user_courses = [];
        }
        
        if (!in_array($course_id, $user_courses)) {
            $user_courses[] = $course_id;
            update_user_meta($user_id, 'stm_lms_user_courses', $user_courses);
        }
        
        return true;
    }
}

// Initialize enrollment sync
new EnrollmentSync();