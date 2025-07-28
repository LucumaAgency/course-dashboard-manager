<?php
/**
 * Waitlist Box Class
 * 
 * Displays when course is in waitlist mode
 */

namespace CourseBoxManager\Boxes;

class WaitlistBox extends AbstractBox {
    
    public function should_display() {
        return $this->box_state === 'waitlist' && 
               empty($this->available_dates) && 
               !$this->is_out_of_stock;
    }
    
    protected function get_box_classes() {
        return parent::get_box_classes() . ' course-launch';
    }
    
    public function render() {
        ob_start();
        ?>
        <div class="<?php echo esc_attr($this->get_box_classes()); ?>">
            <h3>Join Waitlist for Free</h3>
            <p class="description">Be the first to know when the course launches. No Spam. We Promise!</p>
            <?php echo do_shortcode('[contact-form-7 id="255b390" title="Course Launch"]'); ?>
            <p class="terms">By signing up, you agree to the Terms & Conditions.</p>
        </div>
        <?php
        return ob_get_clean();
    }
}