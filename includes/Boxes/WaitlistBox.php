<?php
/**
 * Waitlist Box Class
 * 
 * Displays when course is in waitlist mode
 */

namespace CourseBoxManager\Boxes;

class WaitlistBox extends AbstractBox {
    
    public function should_display() {
        // Display when box_state is waitlist, regardless of dates or stock
        return $this->box_state === 'waitlist';
    }
    
    protected function get_box_classes() {
        return parent::get_box_classes() . ' course-launch';
    }
    
    public function render() {
        // Get custom text or use default
        $default_form = do_shortcode('[contact-form-7 id="255b390" title="Course Launch"]');
        $custom_text = $this->process_custom_text('waitlist', [
            'button' => $default_form
        ]);
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($this->get_box_classes()); ?>">
            <?php if (empty($custom_text)) : ?>
                <h3>Join Waitlist for Free</h3>
                <p class="description">Be the first to know when the course launches. No Spam. We Promise!</p>
                <?php echo $default_form; ?>
                <p class="terms">By signing up, you agree to the Terms & Conditions.</p>
            <?php else : ?>
                <?php echo $custom_text; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}