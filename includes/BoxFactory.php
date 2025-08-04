<?php
/**
 * Box Factory Class
 * 
 * Creates appropriate box instances based on course state
 */

namespace CourseBoxManager;

use CourseBoxManager\Boxes\SoldOutBox;
use CourseBoxManager\Boxes\WaitlistBox;
use CourseBoxManager\Boxes\CountdownBox;
use CourseBoxManager\Boxes\BuyCourseBox;
use CourseBoxManager\Boxes\EnrollCourseBox;

class BoxFactory {
    
    /**
     * Get the appropriate box for a course
     * 
     * @param int $course_id
     * @return \CourseBoxManager\Boxes\AbstractBox|null
     */
    public static function get_box($course_id) {
        error_log('[CBM Debug] get_box called for course_id: ' . $course_id);
        
        $box_types = [
            SoldOutBox::class,
            CountdownBox::class,
            WaitlistBox::class,
            BuyCourseBox::class,
            EnrollCourseBox::class
        ];
        
        foreach ($box_types as $box_class) {
            // Verify class exists before instantiating
            if (!class_exists($box_class)) {
                error_log('[CBM BoxFactory] Class not found: ' . $box_class);
                continue;
            }
            
            try {
                $box = new $box_class($course_id);
                $class_name = get_class($box);
                
                
                if ($box->should_display()) {
                    error_log('[CBM Debug] Box selected: ' . $class_name . ' for course ' . $course_id);
                    return $box;
                } else {
                    error_log('[CBM Debug] Box ' . $class_name . ' should_display() returned false for course ' . $course_id);
                }
            } catch (\Exception $e) {
                error_log('[CBM BoxFactory] Error creating box: ' . $e->getMessage());
            }
        }
        
        error_log('[CBM Debug] No box matched for course ' . $course_id);
        return null;
    }
    
    /**
     * Get all available boxes for a group of courses
     * 
     * @param array $course_ids
     * @return array
     */
    public static function get_boxes_for_courses($course_ids) {
        $boxes = [];
        
        
        foreach ($course_ids as $course_id) {
            $box = self::get_box($course_id);
            if ($box) {
                $boxes[] = $box;
            }
        }
        
        return $boxes;
    }
}