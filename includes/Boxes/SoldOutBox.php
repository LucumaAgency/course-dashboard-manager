<?php
/**
 * Sold Out Box Class
 * 
 * Displays when course is sold out
 */

namespace CourseBoxManager\Boxes;

class SoldOutBox extends AbstractBox {
    
    public function should_display() {
        // Display when box_state is soldout
        if ($this->box_state === 'soldout') {
            return true;
        }
        
        // Check WooCommerce product stock for enroll-course state
        if ($this->box_state === 'enroll-course' && $this->course_product_id && function_exists('wc_get_product')) {
            $product = wc_get_product($this->course_product_id);
            if ($product && !$product->is_in_stock()) {
                error_log('[SoldOutBox] WooCommerce product out of stock for course ' . $this->course_id . ', displaying sold out box');
                return true;
            }
        }
        
        // Check if all dates are sold out (for enroll-course state)
        if ($this->box_state === 'enroll-course' && !empty($this->available_dates_full)) {
            $all_sold_out = true;
            foreach ($this->available_dates_full as $date_info) {
                $date = isset($date_info['date']) ? $date_info['date'] : '';
                $stock = isset($date_info['stock']) ? intval($date_info['stock']) : 0;
                
                // Calculate available seats
                $sold = 0;
                if ($this->course_product_id && function_exists('calculate_seats_sold')) {
                    $sold = calculate_seats_sold($this->course_product_id, $date);
                }
                $available = max(0, $stock - $sold);
                
                if ($available > 0) {
                    $all_sold_out = false;
                    break;
                }
            }
            
            if ($all_sold_out) {
                error_log('[SoldOutBox] All seats sold out for course ' . $this->course_id . ', displaying sold out box');
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