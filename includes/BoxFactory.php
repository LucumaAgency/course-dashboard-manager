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
        $box_types = [
            SoldOutBox::class,
            CountdownBox::class,
            WaitlistBox::class,
            BuyCourseBox::class,
            EnrollCourseBox::class
        ];
        
        foreach ($box_types as $box_class) {
            $box = new $box_class($course_id);
            if ($box->should_display()) {
                return $box;
            }
        }
        
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