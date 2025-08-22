<?php

namespace CourseBoxManager\Popup;

use CourseBoxManager\Boxes\BoxFactory;
use CourseBoxManager\BoxRenderer;

class PopupBoxRenderer {
    public function __construct() {
        // We'll use static methods from BoxFactory and BoxRenderer
    }
    
    /**
     * Render boxes based on context and device
     */
    public function render($course_id, $context = 'popup') {
        error_log('[PopupBoxRenderer] Rendering popup for course: ' . $course_id);
        
        // Simply use BoxRenderer to get the boxes
        $box_html = BoxRenderer::render_box_for_course($course_id);
        
        if (empty($box_html)) {
            return '<div class="cbm-no-boxes">No boxes available for this course.</div>';
        }
        
        // Wrap in popup container
        $is_mobile = $this->isMobile();
        $container_class = $is_mobile ? 'cbm-popup-container mobile-view' : 'cbm-popup-container desktop-view';
        
        return '<div class="' . $container_class . '">' . $box_html . '</div>';
    }
    
    /**
     * Get enabled boxes for a course
     */
    private function getEnabledBoxes($course_id) {
        $settings = get_post_meta($course_id, 'cbm_display_settings', true);
        
        if (!$settings || !isset($settings['enabled_boxes'])) {
            // Fallback to default box detection
            return $this->detectDefaultBoxes($course_id);
        }
        
        return array_filter($settings['enabled_boxes']);
    }
    
    /**
     * Detect default boxes based on course state
     */
    private function detectDefaultBoxes($course_id) {
        $boxes = [];
        
        // Get the actual box state
        $box_state = get_post_meta($course_id, 'box_state', true);
        
        error_log('[PopupBoxRenderer] Course ' . $course_id . ' has box_state: ' . $box_state);
        
        // Use the actual box state to determine what to show
        switch($box_state) {
            case 'enroll-buy':
                $boxes[] = 'enroll_course';
                $boxes[] = 'buy_course';
                break;
            case 'enroll-course':
                $boxes[] = 'enroll_course';
                break;
            case 'buy-course':
                $boxes[] = 'buy_course';
                break;
            case 'soldout':
            case 'soldout-course':
                $boxes[] = 'sold_out';
                break;
            case 'countdown':
            case 'countdown-course':
                $boxes[] = 'countdown';
                break;
            case 'waitlist':
            case 'waitlist-course':
                $boxes[] = 'waitlist';
                break;
            default:
                // Fallback - try to detect from available data
                $has_dates = get_post_meta($course_id, 'course_dates', true);
                $has_product = get_post_meta($course_id, 'linked_product_id', true);
                
                if ($has_dates) {
                    $boxes[] = 'enroll_course';
                }
                if ($has_product) {
                    $boxes[] = 'buy_course';
                }
                break;
        }
        
        error_log('[PopupBoxRenderer] Detected boxes: ' . json_encode($boxes));
        
        return $boxes;
    }
    
    /**
     * Check if current request is from mobile device
     */
    private function isMobile() {
        // Simple mobile detection - can be enhanced
        return wp_is_mobile();
    }
    
    /**
     * Render single box
     */
    private function renderSingleBox($box_type, $course_id) {
        $box = $this->createBoxByType($box_type, $course_id);
        
        if (!$box) {
            return '';
        }
        
        return '<div class="cbm-popup-container has-one-box">' . 
               BoxRenderer::render_box($box) . 
               '</div>';
    }
    
    /**
     * Render boxes as tabs (for 2 boxes on mobile)
     */
    private function renderTabbed($box_types, $course_id) {
        $html = '<div class="cbm-popup-container has-two-boxes">';
        $html .= '<div class="cbm-tabs">';
        $html .= '<div class="cbm-tabs-header">';
        
        $tabs_content = '';
        $first = true;
        
        foreach ($box_types as $index => $box_type) {
            $box = $this->createBoxByType($box_type, $course_id);
            if (!$box) continue;
            
            $title = $this->getBoxTitle($box_type);
            $active = $first ? 'active' : '';
            
            // Tab header
            $html .= sprintf(
                '<button class="cbm-tab-btn %s" data-tab="%d">%s</button>',
                $active,
                $index,
                esc_html($title)
            );
            
            // Tab content
            $tabs_content .= sprintf(
                '<div class="cbm-tab-pane %s" data-tab="%d">%s</div>',
                $active,
                $index,
                BoxRenderer::render_box($box)
            );
            
            $first = false;
        }
        
        $html .= '</div>'; // Close tabs-header
        $html .= '<div class="cbm-tabs-content">' . $tabs_content . '</div>';
        $html .= '</div>'; // Close cbm-tabs
        $html .= '</div>'; // Close container
        
        return $html;
    }
    
    /**
     * Render boxes stacked (for 3+ boxes)
     */
    private function renderStacked($box_types, $course_id) {
        $html = '<div class="cbm-popup-container has-multiple-boxes">';
        
        foreach ($box_types as $box_type) {
            $box = $this->createBoxByType($box_type, $course_id);
            if (!$box) continue;
            
            $html .= '<div class="cbm-box-wrapper">';
            $html .= BoxRenderer::render_box($box);
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render boxes normally (desktop)
     */
    private function renderNormal($box_types, $course_id) {
        $html = '<div class="cbm-popup-container desktop-view">';
        
        foreach ($box_types as $box_type) {
            $box = $this->createBoxByType($box_type, $course_id);
            if (!$box) continue;
            
            $html .= BoxRenderer::render_box($box);
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Create box instance by type
     */
    private function createBoxByType($box_type, $course_id) {
        // Map box type strings to class names
        $box_classes = [
            'sold_out' => 'CourseBoxManager\Boxes\SoldOutBox',
            'coming_soon' => 'CourseBoxManager\Boxes\CountdownBox',
            'buy_course' => 'CourseBoxManager\Boxes\BuyCourseBox',
            'enroll_course' => 'CourseBoxManager\Boxes\EnrollCourseBox',
            'enroll_buy' => 'CourseBoxManager\Boxes\EnrollBuyBox',
            'contact' => 'CourseBoxManager\Boxes\ContactBox'
        ];
        
        $class_name = isset($box_classes[$box_type]) ? $box_classes[$box_type] : null;
        
        if (!$class_name || !class_exists($class_name)) {
            return null;
        }
        
        $box = new $class_name($course_id);
        
        // Check if box should display
        if (!$box->should_display()) {
            return null;
        }
        
        return $box;
    }
    
    /**
     * Get user-friendly title for box type
     */
    private function getBoxTitle($box_type) {
        $titles = [
            'buy_course' => 'Buy Course',
            'enroll_course' => 'Enroll',
            'enroll_buy' => 'Enroll + Buy',
            'sold_out' => 'Sold Out',
            'coming_soon' => 'Coming Soon',
            'contact' => 'Contact'
        ];
        
        return $titles[$box_type] ?? ucfirst(str_replace('_', ' ', $box_type));
    }
}