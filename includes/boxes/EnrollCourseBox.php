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
            
            <div class="start-dates" style="display: <?php echo !$this->is_group_course ? 'none' : 'block'; ?>;">
                <p class="choose-label">Choose a starting date</p>
                <div class="date-options">
                    <?php foreach ($this->available_dates as $date) : ?>
                        <button class="date-btn" data-date="<?php echo esc_attr($date); ?>">
                            <?php echo esc_html($date); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php echo $this->render_add_to_cart_button('Enroll Now'); ?>
        </div>
        <?php
        return ob_get_clean();
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