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
        // Ensure selectBox function is defined
        $script = '<script type="text/javascript">
            console.log("[CBM] BuyCourseBox render() called");
            if (typeof window.selectBox === "undefined") {
                console.log("[CBM] Defining selectBox in BuyCourseBox");
                window.selectBox = function(element, boxType, courseId) {
                    console.log("[CBM] selectBox called from BuyCourseBox", boxType, courseId);
                    if (typeof jQuery === "undefined") {
                        setTimeout(function() { window.selectBox(element, boxType, courseId); }, 100);
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
        </script>';
        
        // Get actual price from WooCommerce product if available
        $display_price = $this->course_price;
        
        if ($this->course_product_id && function_exists('wc_get_product')) {
            $product = wc_get_product($this->course_product_id);
            if ($product) {
                $display_price = $product->get_price();
                error_log('[BuyCourseBox] Using WooCommerce price: ' . $display_price . ' for product ID: ' . $this->course_product_id);
            }
        }
        
        error_log('[BuyCourseBox] Rendering with price: ' . $display_price . ' and product ID: ' . $this->course_product_id);
        
        // For buy course, we don't check seats - it's always available unless explicitly set to soldout
        $button_html = $this->render_add_to_cart_button('Buy Course');
        
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
                <?php echo $this->render_add_to_cart_button('Buy Course'); ?>
            <?php else : ?>
                <?php echo $this->render_selection_indicator(); ?>
                <div class="box-content">
                    <?php echo $custom_text; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return $script . ob_get_clean();
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
        return $script . ob_get_clean();
    }
}