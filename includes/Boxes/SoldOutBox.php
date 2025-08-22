<?php
/**
 * Sold Out Box Class
 * 
 * Displays when course is sold out
 */

namespace CourseBoxManager\Boxes;

class SoldOutBox extends AbstractBox {
    
    public function should_display() {
        // Display when box_state is soldout OR when the product is actually out of stock
        if ($this->box_state === 'soldout') {
            return true;
        }
        
        // Check actual WooCommerce product stock
        if ($this->course_product_id && function_exists('wc_get_product')) {
            $product = wc_get_product($this->course_product_id);
            if ($product && !$product->is_in_stock()) {
                error_log('[SoldOutBox] Product ' . $this->course_product_id . ' is out of stock, displaying sold out box');
                return true;
            }
        }
        
        return false;
    }
    
    protected function get_box_classes() {
        return parent::get_box_classes() . ' soldout-course';
    }
    
    public function render() {
        // Get custom text or use default
        $custom_text = $this->process_custom_text('soldout', []);
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($this->get_box_classes()); ?>">
            <?php if (empty($custom_text)) : ?>
                <div class="soldout-header">
                    <span>THE COURSE IS SOLD OUT</span>
                </div>
                <h3>Join Waitlist for Free</h3>
                <p class="description">Gain access to live streams, free credits for Arcana, and more.</p>
                <?php echo do_shortcode('[contact-form-7 id="255b390" title="Course Launch"]'); ?>
                <p class="terms">By signing up, you agree to the Terms & Conditions.</p>
            <?php else : ?>
                <?php echo $custom_text; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}