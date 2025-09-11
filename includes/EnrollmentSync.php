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
        
        // Hook to link WooCommerce products with STM courses
        add_action('save_post_course', [$this, 'link_product_to_stm_course'], 10, 1);
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
            
            // First, find the course that has this product linked
            // Check both linked_product_id, buy_product_id, and enroll_product_id
            $args = array(
                'post_type' => 'course',
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => 'linked_product_id',
                        'value' => $product_id,
                        'compare' => '='
                    ),
                    array(
                        'key' => 'buy_product_id',
                        'value' => $product_id,
                        'compare' => '='
                    ),
                    array(
                        'key' => 'enroll_product_id',
                        'value' => $product_id,
                        'compare' => '='
                    )
                ),
                'posts_per_page' => 1
            );
            $courses = get_posts($args);
            
            if (empty($courses)) {
                error_log('[CBM Enrollment Sync] No course found with linked_product_id: ' . $product_id);
                continue;
            }
            
            $course_id = $courses[0]->ID;
            error_log('[CBM Enrollment Sync] Found course ID: ' . $course_id . ' for product ID: ' . $product_id);
            
            // Now get the related STM Course ID from the course meta
            $stm_course_id = get_post_meta($course_id, 'related_stm_course_id', true);
            
            if (!$stm_course_id) {
                error_log('[CBM Enrollment Sync] No STM Course linked to course ID: ' . $course_id);
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
                
                // Create MasterStudy LMS order entry
                $this->create_stm_lms_order($user_id, $stm_course_id, $order_id, $item);
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
    
    /**
     * Create an order entry in MasterStudy LMS
     */
    private function create_stm_lms_order($user_id, $course_id, $wc_order_id, $item) {
        global $wpdb;
        
        // Get WooCommerce order
        $wc_order = wc_get_order($wc_order_id);
        if (!$wc_order) {
            error_log('[CBM Enrollment Sync] Cannot create STM order - WC order not found');
            return;
        }
        
        // Get course price from the order item
        $item_total = $item->get_total();
        
        // Check if STM LMS Orders table exists
        $table = $wpdb->prefix . 'stm_lms_order_items';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            error_log('[CBM Enrollment Sync] STM LMS Order Items table does not exist');
            
            // Try alternative method - store in user meta
            $this->store_order_in_meta($user_id, $course_id, $wc_order_id, $item_total);
            return;
        }
        
        // Prepare order data for STM LMS
        $order_data = [
            'user_id' => $user_id,
            'course_id' => $course_id,
            'order_id' => $wc_order_id, // Reference to WooCommerce order
            'price' => $item_total,
            'date' => current_time('mysql'),
            'status' => 'completed',
            'order_type' => 'woocommerce' // Mark as WooCommerce order
        ];
        
        // Insert into STM LMS order items table
        $result = $wpdb->insert($table, $order_data);
        
        if ($result === false) {
            error_log('[CBM Enrollment Sync] Failed to insert STM order: ' . $wpdb->last_error);
            // Fallback to meta storage
            $this->store_order_in_meta($user_id, $course_id, $wc_order_id, $item_total);
        } else {
            error_log('[CBM Enrollment Sync] Created STM LMS order for user ' . $user_id . ' course ' . $course_id);
        }
        
        // Also try to create entry in main orders table if it exists
        $orders_table = $wpdb->prefix . 'stm_lms_orders';
        if ($wpdb->get_var("SHOW TABLES LIKE '$orders_table'") == $orders_table) {
            $order_hash = md5($user_id . $course_id . $wc_order_id . time());
            
            $main_order_data = [
                'user_id' => $user_id,
                'order_id' => $wc_order_id,
                'hash' => $order_hash,
                'date' => current_time('mysql'),
                'status' => 'completed',
                'payment_type' => 'woocommerce',
                'total' => $item_total,
                'currency' => get_woocommerce_currency()
            ];
            
            $wpdb->insert($orders_table, $main_order_data);
        }
    }
    
    /**
     * Store order information in user meta as fallback
     */
    private function store_order_in_meta($user_id, $course_id, $wc_order_id, $price) {
        // Get existing orders from user meta
        $user_orders = get_user_meta($user_id, 'stm_lms_user_orders', true);
        if (!is_array($user_orders)) {
            $user_orders = [];
        }
        
        // Add new order
        $user_orders[] = [
            'course_id' => $course_id,
            'order_id' => $wc_order_id,
            'date' => current_time('mysql'),
            'price' => $price,
            'status' => 'completed',
            'source' => 'woocommerce'
        ];
        
        update_user_meta($user_id, 'stm_lms_user_orders', $user_orders);
        error_log('[CBM Enrollment Sync] Stored order in user meta for user ' . $user_id);
    }
    
    /**
     * Link WooCommerce product to STM course when course is saved
     */
    public function link_product_to_stm_course($course_id) {
        // Get the related STM course ID
        $stm_course_id = get_post_meta($course_id, 'related_stm_course_id', true);
        if (!$stm_course_id) {
            return;
        }
        
        // Get all product IDs linked to this course
        $linked_product_id = get_post_meta($course_id, 'linked_product_id', true);
        $buy_product_id = get_post_meta($course_id, 'buy_product_id', true);
        $enroll_product_id = get_post_meta($course_id, 'enroll_product_id', true);
        
        $product_ids = array_filter([$linked_product_id, $buy_product_id, $enroll_product_id]);
        
        foreach ($product_ids as $product_id) {
            if ($product_id) {
                // Add STM course ID to the product meta
                update_post_meta($product_id, '_related_stm_course_id', $stm_course_id);
                
                // Also add product ID to STM course meta for reverse lookup
                $stm_products = get_post_meta($stm_course_id, '_woocommerce_product_ids', true);
                if (!is_array($stm_products)) {
                    $stm_products = [];
                }
                if (!in_array($product_id, $stm_products)) {
                    $stm_products[] = $product_id;
                    update_post_meta($stm_course_id, '_woocommerce_product_ids', $stm_products);
                }
                
                error_log('[CBM Enrollment Sync] Linked product ' . $product_id . ' to STM course ' . $stm_course_id);
            }
        }
    }
}

// Initialize enrollment sync
new EnrollmentSync();