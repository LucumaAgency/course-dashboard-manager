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
        
        // Add the selectBox script at the beginning of any box output
        $script = '<!-- CBM BoxRenderer START --><script type="text/javascript">
            console.log("[CBM] BoxRenderer script executing");
            if (typeof window.selectBox === "undefined") {
                console.log("[CBM] Defining selectBox in render_boxes_for_group");
                window.selectBox = function(element, boxType, courseId) {
                    console.log("[CBM] selectBox called from group render", boxType, courseId);
                    
                    if (typeof jQuery === "undefined") {
                        setTimeout(function() {
                            window.selectBox(element, boxType, courseId);
                        }, 100);
                        return;
                    }
                    
                    var $ = jQuery;
                    var $box = $(element);
                    
                    if ($box.hasClass("selected")) {
                        $box.removeClass("selected");
                        $box.find(".circlecontainer").show();
                        $box.find(".circle-container").hide();
                    } else {
                        $box.siblings(".box").removeClass("selected");
                        $box.siblings(".box").find(".circlecontainer").show();
                        $box.siblings(".box").find(".circle-container").hide();
                        
                        $box.addClass("selected");
                        $box.find(".circlecontainer").hide();
                        $box.find(".circle-container").show();
                    }
                };
            }
        </script><!-- CBM BoxRenderer END -->';
        
        // If we have a specific post_id and it's a course, render its box
        if ($post_id && get_post_type($post_id) === 'course') {
            // Note: render_box_for_course already includes the script, but we add it here too for safety
            return $script . self::render_box_for_course($post_id);
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
                return $script . self::render_box_for_course($courses[0]->ID);
            }
        }
        
        return $script . '<div class="course-box-error">No course found for this page.</div>';
    }
    
    /**
     * Render box for a specific course
     */
    public static function render_box_for_course($course_id) {
        error_log('[BoxRenderer] Rendering box for course: ' . $course_id);
        
        // Add inline script to ensure selectBox is available
        $script = '<script type="text/javascript">
            if (typeof window.selectBox === "undefined") {
                console.log("[CBM] Defining selectBox in BoxRenderer");
                window.selectBox = function(element, boxType, courseId) {
                    console.log("[CBM] selectBox called", boxType, courseId);
                    
                    if (typeof jQuery === "undefined") {
                        setTimeout(function() {
                            window.selectBox(element, boxType, courseId);
                        }, 100);
                        return;
                    }
                    
                    var $ = jQuery;
                    var $box = $(element);
                    
                    if ($box.hasClass("selected")) {
                        $box.removeClass("selected");
                        $box.find(".circlecontainer").show();
                        $box.find(".circle-container").hide();
                    } else {
                        $box.siblings(".box").removeClass("selected");
                        $box.siblings(".box").find(".circlecontainer").show();
                        $box.siblings(".box").find(".circle-container").hide();
                        
                        $box.addClass("selected");
                        $box.find(".circlecontainer").hide();
                        $box.find(".circle-container").show();
                    }
                };
            }
        </script><!-- CBM BoxRenderer END -->';
        
        try {
            $box = BoxFactory::create($course_id);
            
            if ($box) {
                $output = $script . $box->render();
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