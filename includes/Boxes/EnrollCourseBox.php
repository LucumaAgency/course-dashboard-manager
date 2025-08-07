<?php
/**
 * Enroll Course Box Class
 * 
 * Displays enrollment option for live courses with date selection
 */

namespace CourseBoxManager\Boxes;

class EnrollCourseBox extends AbstractBox {
    
    public function should_display() {
        // Allow display if box_state is enroll-course and not out of stock/countdown
        // Don't require valid dates - any text is acceptable
        return $this->box_state === 'enroll-course' && 
               !$this->is_out_of_stock && 
               !$this->show_countdown;
    }
    
    protected function get_box_classes() {
        return parent::get_box_classes() . ' enroll-course selected';
    }
    
    public function render() {
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
                
                // Add data attributes for button text and sold out status
                $dates_html .= sprintf(
                    '<button class="date-btn%s" data-date="%s" data-button-text="%s" %s>%s%s</button>',
                    $is_sold_out ? ' sold-out' : '',
                    esc_attr($date),
                    esc_attr($button_text),
                    $is_sold_out ? 'disabled' : '',
                    esc_html($date),  // Display the text exactly as entered
                    $is_sold_out ? ' (Sold Out)' : ($available <= 5 ? ' (' . $available . ' left)' : '')
                );
            }
            $dates_html .= '</div></div>';
        }
        
        // Determine button state and text
        $button_html = '';
        if ($all_sold_out) {
            $button_html = '<button class="add-to-cart-button sold-out" disabled>
                <span class="button-text">Sold Out</span>
            </button>';
        } else {
            $button_html = $this->render_add_to_cart_button($default_button_text);
        }
        
        // Get custom text or use default
        $custom_text = $this->process_custom_text('enroll', [
            'dates' => $dates_html,
            'price' => $this->format_price($this->enroll_price),
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
                        <div class="box-price"><?php echo $this->format_price($this->enroll_price); ?></div>
                        <p class="description">Join weekly live sessions with feedback and expert mentorship.</p>
                    </div>
                </div>
                
                <hr class="divider">
                
                <?php echo $dates_html; ?>
                
                <?php echo $button_html; ?>
            </div>
            <?php
            return ob_get_clean();
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
            return ob_get_clean();
        }
    }
    
    protected function render_selection_indicator() {
        ob_start();
        ?>
        <div class="circlecontainer" style="display: flex;">
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