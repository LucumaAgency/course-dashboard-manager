<?php
/**
 * Box Renderer Class
 * 
 * Handles rendering of course boxes for frontend display
 */

namespace CourseBoxManager;

use CourseBoxManager\Boxes\BoxFactory;

class BoxRenderer {
    
    /**
     * Render boxes for a course group
     */
    public static function render_boxes_for_group($group_id, $post_id = 0) {
        error_log('[BoxRenderer] Rendering for group: ' . $group_id . ', post: ' . $post_id);
        
        // If we have a specific post_id and it's a course, render its box
        if ($post_id && get_post_type($post_id) === 'course') {
            return self::render_box_for_course($post_id);
        }
        
        // Otherwise, try to find courses in the group
        if ($group_id) {
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
            
            if (!empty($courses)) {
                // For now, render the first course's box
                return self::render_box_for_course($courses[0]->ID);
            }
        }
        
        return '<div class="course-box-error">No course found for this page.</div>';
    }
    
    /**
     * Render box for a specific course
     */
    public static function render_box_for_course($course_id) {
        error_log('[BoxRenderer] Rendering box for course: ' . $course_id);
        
        try {
            $box = BoxFactory::create($course_id);
            
            if ($box) {
                $output = $box->render();
                error_log('[BoxRenderer] Box rendered successfully, length: ' . strlen($output));
                return $output;
            } else {
                error_log('[BoxRenderer] BoxFactory returned null for course: ' . $course_id);
                return '<div class="course-box-error">Could not create box for course.</div>';
            }
        } catch (\Exception $e) {
            error_log('[BoxRenderer] Exception: ' . $e->getMessage());
            return '<div class="course-box-error">Error rendering box: ' . esc_html($e->getMessage()) . '</div>';
        }
    }
}