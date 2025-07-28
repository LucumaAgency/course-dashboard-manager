<?php
/**
 * Sold Out Box Class
 * 
 * Displays when course is sold out
 */

namespace CourseBoxManager\Boxes;

class SoldOutBox extends AbstractBox {
    
    public function should_display() {
        return $this->box_state === 'soldout' && $this->is_out_of_stock;
    }
    
    protected function get_box_classes() {
        return parent::get_box_classes() . ' soldout-course';
    }
    
    public function render() {
        ob_start();
        ?>
        <div class="<?php echo esc_attr($this->get_box_classes()); ?>">
            <div class="soldout-header">
                <span>THE COURSE IS SOLD OUT</span>
            </div>
            <h3>Join Waitlist for Free</h3>
            <p class="description">Gain access to live streams, free credits for Arcana, and more.</p>
            <?php echo do_shortcode('[contact-form-7 id="c2b4e27" title="Course Sold Out"]'); ?>
            <p class="terms">By signing up, you agree to the Terms & Conditions.</p>
        </div>
        <?php
        return ob_get_clean();
    }
}