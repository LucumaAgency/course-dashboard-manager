<?php
/**
 * Enroll Course Box Class
 * 
 * Displays enrollment option for live courses with date selection
 */

namespace CourseBoxManager\Boxes;

class EnrollCourseBox extends AbstractBox {
    
    public $enroll_regular_price;
    public $enroll_sale_price;
    
    public function should_display() {
        // Display when box_state is enroll-course, regardless of stock or countdown
        return $this->box_state === 'enroll-course';
    }
    
    protected function get_box_classes() {
        return parent::get_box_classes() . ' enroll-course';
    }
    
    public function render() {
        // Ensure selectBox function is defined
        $script = '<script type="text/javascript">
            console.log("[CBM] EnrollCourseBox render() called");
            if (typeof window.selectBox === "undefined") {
                console.log("[CBM] Defining selectBox in EnrollCourseBox");
                window.selectBox = function(element, boxType, courseId) {
                    console.log("[CBM] selectBox called from EnrollCourseBox", boxType, courseId);
                    if (typeof jQuery === "undefined") {
                        setTimeout(function() { window.selectBox(element, boxType, courseId); }, 100);
                        return;
                    }
                    var $ = jQuery;
                    var $box = $(element);
                    if ($box.hasClass("selected")) {
                        $box.removeClass("selected");
                        $box.find(".circlecontainer").hide();
                        $box.find(".circle-container").show();
                    } else {
                        $box.siblings(".box").removeClass("selected");
                        $box.siblings(".box").find(".circlecontainer").hide();
                        $box.siblings(".box").find(".circle-container").show();
                        $box.addClass("selected");
                        $box.find(".circlecontainer").show();
                        $box.find(".circle-container").hide();
                    }
                };
            }
        </script>';
        
        // Get actual price and stock status from WooCommerce product if available
        $display_price = $this->enroll_price;
        $regular_price = $this->enroll_regular_price;
        $sale_price = $this->enroll_sale_price;
        $product_in_stock = !$this->is_out_of_stock; // Use the property that might be set by parent
        $product = null;
        
        if ($this->course_product_id && function_exists('wc_get_product')) {
            $product = wc_get_product($this->course_product_id);
            if ($product) {
                $display_price = $product->get_price();
                // Only get prices if not already set by parent
                if ($regular_price === null) {
                    $regular_price = $product->get_regular_price();
                }
                if ($sale_price === null) {
                    $sale_price = $product->get_sale_price();
                }
                // Only check product stock if not already marked as out of stock
                if (!$this->is_out_of_stock) {
                    $product_in_stock = $product->is_in_stock();
                }
                error_log('[EnrollCourseBox] Product ID: ' . $this->course_product_id . ', Price: ' . $display_price . ', WC Stock: ' . ($product_in_stock ? 'in stock' : 'out of stock') . ', is_out_of_stock property: ' . ($this->is_out_of_stock ? 'true' : 'false'));
            }
        }
        
        // Prepare dates HTML and check if all sold out
        $dates_html = '';
        $all_sold_out = true;
        $default_button_text = 'Enroll Now';
        
        if (!empty($this->available_dates_full)) {
            $dates_html .= '<div class="start-dates" style="display: block;">';
            $dates_html .= '<p class="choose-label">Choose a starting date</p>';
            $dates_html .= '<div class="date-options">';
            
            foreach ($this->available_dates_full as $index => $date_info) {
                $date = isset($date_info['date']) ? $date_info['date'] : '';
                $stock = isset($date_info['stock']) ? intval($date_info['stock']) : 0;
                $button_text = isset($date_info['button_text']) ? $date_info['button_text'] : 'Enroll Now';
                
                // Calculate available seats
                $sold = 0;
                if ($this->course_product_id && function_exists('calculate_seats_sold')) {
                    $sold = calculate_seats_sold($this->course_product_id, $date);
                }
                $available = max(0, $stock - $sold);
                $is_sold_out = ($available <= 0);
                
                if (!$is_sold_out) {
                    $all_sold_out = false;
                    if ($index === 0) {
                        $default_button_text = $button_text; // Use first available date's button text as default
                    }
                }
                
                // Get STM Course ID for this date if available
                $stm_course_id = isset($date_info['stm_course_id']) ? $date_info['stm_course_id'] : '';
                
                // Add data attributes for button text, STM course, and sold out status
                $dates_html .= sprintf(
                    '<button class="date-btn%s" data-date="%s" data-button-text="%s" data-stm-course-id="%s" %s>%s%s</button>',
                    $is_sold_out ? ' sold-out' : '',
                    esc_attr($date),
                    esc_attr($button_text),
                    esc_attr($stm_course_id),
                    $is_sold_out ? 'disabled' : '',
                    esc_html($date),  // Display the text exactly as entered
                    $is_sold_out ? ' (Sold Out)' : ($available <= 5 ? ' (' . $available . ' left)' : '')
                );
            }
            $dates_html .= '</div></div>';
        }
        
        // Determine button state and text
        $button_html = '';
        if (!$product_in_stock) {
            // WooCommerce product is out of stock
            $button_html = '<button class="add-to-cart-button sold-out" disabled>
                <span class="button-text">Out of Stock</span>
            </button>';
            error_log('[EnrollCourseBox] Button disabled - WooCommerce product out of stock');
        } elseif ($all_sold_out) {
            // All dates are sold out based on seats calculation
            $button_html = '<button class="add-to-cart-button sold-out" disabled>
                <span class="button-text">Sold Out</span>
            </button>';
            error_log('[EnrollCourseBox] Button disabled - All seats sold out');
        } else {
            $button_html = $this->render_add_to_cart_button($default_button_text);
            error_log('[EnrollCourseBox] Button enabled - Product in stock and seats available');
        }
        
        // Get custom text or use default
        $custom_text = $this->process_custom_text('enroll', [
            'dates' => $dates_html,
            'price' => $this->format_price($display_price),
            'button' => $button_html
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
                        <div class="price-container">
                            <?php if ($sale_price && $regular_price && $sale_price < $regular_price) : ?>
                                <p class="regular-price strikethrough"><?php echo $this->format_price($regular_price); ?> USD</p>
                                <p class="sale-price"><?php echo $this->format_price($sale_price); ?> USD</p>
                            <?php else : ?>
                                <p class="regular-price"><?php echo $this->format_price($display_price); ?> USD</p>
                            <?php endif; ?>
                        </div>
                        <p class="description">Join weekly live sessions with feedback and expert mentorship. Pay Once.</p>
                    </div>
                </div>
                
                <hr class="divider">
                
                <?php echo $dates_html; ?>
                
                <?php echo $button_html; ?>
            </div>
            <?php
            return $script . ob_get_clean();
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
            return $script . ob_get_clean();
        }
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
        <div class="circle-container">
            <div class="circle"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}