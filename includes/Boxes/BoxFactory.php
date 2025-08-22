<?php
/**
 * Box Factory Class
 * 
 * Creates appropriate box instances based on course configuration
 */

namespace CourseBoxManager\Boxes;

class BoxFactory {
    
    /**
     * Create appropriate box instance based on course state
     * 
     * @param int $course_id
     * @return AbstractBox|null
     */
    public static function create($course_id) {
        if (!$course_id) {
            return null;
        }
        
        // Get box state from course meta
        $box_state = get_post_meta($course_id, 'box_state', true);
        
        // If no specific state is set, check group default
        if (!$box_state) {
            $terms = wp_get_post_terms($course_id, 'course_group');
            if (!empty($terms)) {
                $group_id = $terms[0]->term_id;
                $box_state = get_term_meta($group_id, 'default_box_state', true);
            }
        }
        
        // Default to enroll-course if still no state
        if (!$box_state) {
            $box_state = 'enroll-course';
        }
        
        // Create appropriate box instance
        switch ($box_state) {
            case 'countdown':
                return new CountdownBox($course_id);
                
            case 'waitlist':
                return new WaitlistBox($course_id);
                
            case 'soldout':
                return new SoldOutBox($course_id);
                
            case 'buy-course':
                return new BuyCourseBox($course_id);
                
            case 'enroll-buy':
                return new EnrollBuyBox($course_id);
                
            case 'enroll-course':
            default:
                return new EnrollCourseBox($course_id);
        }
    }
    
    /**
     * Get all available box states
     * 
     * @return array
     */
    public static function getAvailableStates() {
        return [
            'enroll-course' => 'Enroll in Live Course',
            'buy-course' => 'Buy This Course',
            'enroll-buy' => 'Buy Course + Enroll Course',
            'countdown' => 'Countdown Box',
            'waitlist' => 'Waitlist',
            'soldout' => 'Sold Out'
        ];
    }
}