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
            // Verify class exists before instantiating
            if (!class_exists($box_class)) {
                error_log('[CBM BoxFactory] Class not found: ' . $box_class);
                continue;
            }
            
            try {
                $box = new $box_class($course_id);
                
                // Debug why box might not display
                if (class_exists('CourseBoxManager\\Debug')) {
                    $debug_info = [
                        'box_class' => $box_class,
                        'course_id' => $course_id,
                        'should_display' => $box->should_display()
                    ];
                    
                    // Add specific debug info for each box type
                    if ($box instanceof \CourseBoxManager\Boxes\EnrollCourseBox) {
                        $debug_info['box_state'] = $box->box_state ?? 'unknown';
                        $debug_info['is_group_course'] = $box->is_group_course ?? 'unknown';
                        $debug_info['is_out_of_stock'] = $box->is_out_of_stock ?? 'unknown';
                        $debug_info['show_countdown'] = $box->show_countdown ?? 'unknown';
                        $debug_info['available_dates'] = !empty($box->available_dates) ? count($box->available_dates) . ' dates' : 'no dates';
                    }
                    
                    \CourseBoxManager\Debug::log('Box display check', $debug_info);
                }
                
                if ($box->should_display()) {
                    return $box;
                }
            } catch (\Exception $e) {
                error_log('[CBM BoxFactory] Error creating box: ' . $e->getMessage());
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
        
        // Debug logging
        if (class_exists('CourseBoxManager\\Debug')) {
            \CourseBoxManager\Debug::log('BoxFactory::get_boxes_for_courses called', [
                'course_ids' => $course_ids,
                'count' => count($course_ids)
            ]);
        }
        
        foreach ($course_ids as $course_id) {
            $box = self::get_box($course_id);
            if ($box) {
                $boxes[] = $box;
            } else {
                if (class_exists('CourseBoxManager\\Debug')) {
                    \CourseBoxManager\Debug::log('No box created for course', [
                        'course_id' => $course_id
                    ]);
                }
            }
        }
        
        return $boxes;
    }
}