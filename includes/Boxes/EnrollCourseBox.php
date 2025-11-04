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
        
        // Get price with WooCommerce priority
        error_log('[EnrollCourseBox] RENDER START - enroll_price property: ' . $this->enroll_price . ', course_price: ' . $this->course_price . ', product_id: ' . $this->course_product_id);
        error_log('[EnrollCourseBox] RENDER START - enroll_regular_price: ' . var_export($this->enroll_regular_price, true) . ', enroll_sale_price: ' . var_export($this->enroll_sale_price, true));

        $price_data = $this->get_price_with_priority(
            $this->course_product_id,
            'enroll_price',
            self::DEFAULT_ENROLL_PRICE
        );

        $display_price = $price_data['price'];
        $regular_price = $price_data['regular'];
        $sale_price = $price_data['sale'];
        $currency = $this->get_currency();

        // Check stock status
        $product_in_stock = !$this->is_out_of_stock;
        $product = null;

        if ($this->course_product_id && function_exists('wc_get_product')) {
            $product = wc_get_product($this->course_product_id);
            if ($product && !$this->is_out_of_stock) {
                $product_in_stock = $product->is_in_stock();
            }
        }

        error_log('[EnrollCourseBox] RENDER FINAL - display: ' . $display_price . ', regular: ' . var_export($regular_price, true) . ', sale: ' . var_export($sale_price, true) . ', currency: ' . $currency . ', source: ' . $price_data['source'] . ', stock: ' . ($product_in_stock ? 'in stock' : 'out of stock'));
        
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
                if ($this->course_product_id && function_exists('wc_get_orders')) {
                    // Calculate sold seats directly here
                    $orders = wc_get_orders([
                        'status' => ['wc-completed'],
                        'limit' => -1,
                    ]);

                    foreach ($orders as $order) {
                        foreach ($order->get_items() as $item) {
                            if ($item->get_product_id() == $this->course_product_id &&
                                strcasecmp(trim($item->get_meta('Start Date')), trim($date)) === 0) {
                                $sold += $item->get_quantity();
                            }
                        }
                    }
                }

                $available = max(0, $stock - $sold);
                $is_sold_out = ($available <= 0);

                // Debug logging
                error_log('[EnrollCourseBox DEBUG] Date: ' . $date . ', Stock: ' . $stock . ', Sold: ' . $sold . ', Available: ' . $available . ', Sold Out: ' . ($is_sold_out ? 'YES' : 'NO'));
                
                if (!$is_sold_out) {
                    $all_sold_out = false;
                    if ($index === 0) {
                        $default_button_text = $button_text; // Use first available date's button text as default
                    }
                }
                
                // Get STM Course ID for this date if available
                $stm_course_id = isset($date_info['stm_course_id']) ? $date_info['stm_course_id'] : '';

                // Get product ID for this specific date
                $date_product_id = isset($date_info['product_id']) ? $date_info['product_id'] : $this->course_product_id;

                // Get prices for this specific date's product
                $date_regular_price = '';
                $date_sale_price = '';
                if ($date_product_id && function_exists('wc_get_product')) {
                    $date_product = wc_get_product($date_product_id);
                    if ($date_product) {
                        $date_regular_price = $date_product->get_regular_price();
                        $date_sale_price = $date_product->get_sale_price();
                    }
                }

                // Add data attributes for button text, STM course, prices, and sold out status
                $dates_html .= sprintf(
                    '<button class="date-btn%s" data-date="%s" data-button-text="%s" data-stm-course-id="%s" data-product-id="%s" data-regular-price="%s" data-sale-price="%s" %s>%s%s</button>',
                    $is_sold_out ? ' sold-out' : '',
                    esc_attr($date),
                    esc_attr($button_text),
                    esc_attr($stm_course_id),
                    esc_attr($date_product_id),
                    esc_attr($date_regular_price),
                    esc_attr($date_sale_price),
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
            <div class="<?php echo esc_attr($this->get_box_classes()); ?> selected" 
                 data-course-id="<?php echo esc_attr($this->course_id); ?>" 
                 data-product-id="<?php echo esc_attr($this->course_product_id); ?>"
                 onclick="selectBox(this, 'box2', <?php echo esc_attr($this->course_id); ?>)">
                
                <div class="statebox">
                    <?php echo $this->render_selection_indicator(); ?>
                    <div>
                        <h3>Enroll in the Live Course</h3>
                        <div class="price-container">
                            <?php if ($sale_price && $regular_price && $sale_price < $regular_price) : ?>
                                <p class="regular-price strikethrough"><?php echo $this->format_price($regular_price); ?> <?php echo esc_html($currency); ?></p>
                                <p class="sale-price"><?php echo $this->format_price($sale_price); ?> <?php echo esc_html($currency); ?></p>
                            <?php else : ?>
                                <p class="regular-price"><?php echo $this->format_price($display_price); ?> <?php echo esc_html($currency); ?></p>
                            <?php endif; ?>
                        </div>
                        <p class="description">Join weekly live sessions with feedback and expert mentorship.</p>
                    </div>
                </div>
                
                <hr class="divider">
                
                <?php echo $dates_html; ?>

                <?php echo $button_html; ?>

                <?php
                // Display remaining seats
                $seats_remaining = $this->get_remaining_seats();
                if ($seats_remaining !== false && $seats_remaining > 0) :
                ?>
                    <div class="seats-remaining-info">
                        <p><?php echo esc_html($seats_remaining); ?> Remaining seats</p>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            return $script . ob_get_clean();
        } else {
            // Use custom text layout
            ob_start();
            ?>
            <div class="<?php echo esc_attr($this->get_box_classes()); ?> selected" 
                 data-course-id="<?php echo esc_attr($this->course_id); ?>" 
                 data-product-id="<?php echo esc_attr($this->course_product_id); ?>"
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
        <div class="circlecontainer">
            <div class="outer-circle">
                <div class="middle-circle">
                    <div class="inner-circle"></div>
                </div>
            </div>
        </div>
        <div class="circle-container" style="display: none;">
            <div class="circle"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}