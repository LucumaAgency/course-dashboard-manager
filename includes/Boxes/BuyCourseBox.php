<?php
/**
 * Buy Course Box Class
 * 
 * Displays direct purchase option for courses
 */

namespace CourseBoxManager\Boxes;

class BuyCourseBox extends AbstractBox {
    
    public function should_display() {
        // Display when box_state is buy-course, regardless of stock
        return $this->box_state === 'buy-course';
    }
    
    protected function get_box_classes() {
        return parent::get_box_classes() . ' buy-course';
    }
    
    public function render() {
        // Get actual price and stock from WooCommerce product if available
        $display_price = $this->course_price;
        $is_in_stock = true;
        
        if ($this->course_product_id && function_exists('wc_get_product')) {
            $product = wc_get_product($this->course_product_id);
            if ($product) {
                $display_price = $product->get_price();
                $is_in_stock = $product->is_in_stock();
                error_log('[BuyCourseBox] Product ID: ' . $this->course_product_id . ', Price: ' . $display_price . ', In Stock: ' . ($is_in_stock ? 'yes' : 'no'));
            }
        }
        
        error_log('[BuyCourseBox] Rendering with price: ' . $display_price . ' and product ID: ' . $this->course_product_id);
        
        // Render button based on stock status
        $button_html = $is_in_stock 
            ? $this->render_add_to_cart_button('Buy Course')
            : '<button class="add-to-cart-button sold-out" disabled><span class="button-text">Out of Stock</span></button>';
        
        // Get custom text or use default
        $custom_text = $this->process_custom_text('buy', [
            'price' => $this->format_price($display_price),
            'button' => $button_html
        ]);
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($this->get_box_classes()); ?>" 
             data-course-id="<?php echo esc_attr($this->course_id); ?>" 
             onclick="selectBox(this, 'box1', <?php echo esc_attr($this->course_id); ?>)">
            
            <?php if (empty($custom_text)) : ?>
                <div class="statebox">
                    <?php echo $this->render_selection_indicator(); ?>
                    <div>
                        <h3>Buy This Course</h3>
                        <div class="box-price"><?php echo $this->format_price($display_price); ?></div>
                        <p class="description">Pay once, own the course forever.</p>
                    </div>
                </div>
                <?php echo $button_html; ?>
            <?php else : ?>
                <?php echo $this->render_selection_indicator(); ?>
                <div class="box-content">
                    <?php echo $custom_text; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    protected function render_selection_indicator() {
        ob_start();
        ?>
        <div class="circlecontainer" style="display: none;">
            <div class="outer-circle">
                <div class="middle-circle">
                    <div class="inner-circle"></div>
                </div>
            </div>
        </div>
        <div class="circle-container" style="display: flex;">
            <div class="circle"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}