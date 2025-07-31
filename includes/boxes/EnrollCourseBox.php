<?php
/**
 * Enroll Course Box Class
 * 
 * Displays enrollment option for live courses with date selection
 */

namespace CourseBoxManager\Boxes;

class EnrollCourseBox extends AbstractBox {
    
    public function should_display() {
        return $this->box_state === 'enroll-course' && 
               !$this->is_out_of_stock && 
               !$this->show_countdown && 
               !empty($this->available_dates) && 
               $this->is_group_course;
    }
    
    protected function get_box_classes() {
        $classes = parent::get_box_classes() . ' enroll-course';
        if ($this->is_group_course) {
            $classes .= ' selected';
        }
        return $classes;
    }
    
    public function render() {
        // Debug course ID
        if (class_exists('CourseBoxManager\\Debug')) {
            \CourseBoxManager\Debug::log('EnrollCourseBox rendering', [
                'course_id' => $this->course_id,
                'has_custom_texts' => !empty($this->custom_texts),
                'custom_texts' => $this->custom_texts
            ]);
        }
        
        // Prepare dates HTML
        $dates_html = '';
        if (!empty($this->available_dates)) {
            $dates_html .= '<div class="start-dates" style="display: ' . (!$this->is_group_course ? 'none' : 'block') . ';">';
            $dates_html .= '<p class="choose-label">Choose a starting date</p>';
            $dates_html .= '<div class="date-options">';
            foreach ($this->available_dates as $date) {
                $dates_html .= sprintf(
                    '<button class="date-btn" data-date="%s">%s</button>',
                    esc_attr($date),
                    esc_html($this->format_date($date))
                );
            }
            $dates_html .= '</div></div>';
        }
        
        // Get custom text or use default
        $custom_text = $this->process_custom_text('enroll', [
            'dates' => $dates_html,
            'price' => $this->format_price($this->enroll_price),
            'button' => $this->render_add_to_cart_button('Enroll Now')
        ]);
        
        if (empty($custom_text)) {
            // Use default layout if no custom text
            ob_start();
            ?>
            <div class="<?php echo esc_attr($this->get_box_classes()); ?>" 
                 data-course-id="<?php echo esc_attr($this->course_id); ?>" 
                 onclick="selectBox(this, 'box2', <?php echo esc_attr($this->course_id); ?>)">
                
                <div class="statebox">
                    <?php echo $this->render_selection_indicator(); ?>
                    <div>
                        <h3>Enroll in the Live Course</h3>
                        <p><?php echo esc_html($this->format_price($this->enroll_price)); ?></p>
                        <p class="description">Join weekly live sessions with feedback and expert mentorship.</p>
                    </div>
                </div>
                
                <hr class="divider">
                
                <?php echo $dates_html; ?>
                
                <?php echo $this->render_add_to_cart_button('Enroll Now'); ?>
            </div>
            <?php
            return ob_get_clean();
        } else {
            // Use custom text layout
            ob_start();
            ?>
            <div class="<?php echo esc_attr($this->get_box_classes()); ?>" 
                 data-course-id="<?php echo esc_attr($this->course_id); ?>" 
                 onclick="selectBox(this, 'box2', <?php echo esc_attr($this->course_id); ?>)">
                
                <?php echo $this->render_selection_indicator(); ?>
                
                <div class="box-content">
                    <?php echo $custom_text; ?>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }
    }
    
    protected function render_selection_indicator() {
        ob_start();
        ?>
        <div class="circlecontainer" style="display: <?php echo !$this->is_group_course ? 'none' : 'flex'; ?>;">
            <div class="outer-circle">
                <div class="middle-circle">
                    <div class="inner-circle"></div>
                </div>
            </div>
        </div>
        <div class="circle-container" style="display: <?php echo !$this->is_group_course ? 'flex' : 'none'; ?>;">
            <div class="circle"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}