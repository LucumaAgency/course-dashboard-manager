<?php
/**
 * Abstract Box Class
 * 
 * Base class for all course box types
 */

namespace CourseBoxManager\Boxes;

abstract class AbstractBox {
    protected $course_id;
    protected $course;
    protected $box_state;
    protected $course_product_id;
    protected $course_price;
    protected $enroll_price;
    protected $available_dates;
    protected $is_out_of_stock;
    protected $launch_date;
    protected $show_countdown;
    protected $is_group_course;
    protected $custom_texts;
    protected $date_format;
    protected $price_format;
    protected $button_text;
    
    public function __construct($course_id) {
        $this->course_id = $course_id;
        $this->course = get_post($course_id);
        
        
        $this->initialize_properties();
    }
    
    protected function initialize_properties() {
        $this->box_state = get_post_meta($this->course_id, 'box_state', true) ?: 'enroll-course';
        $this->course_product_id = get_post_meta($this->course_id, 'linked_product_id', true);
        $this->course_price = get_field('course_price', $this->course_id) ?: 749.99;
        $this->enroll_price = get_field('enroll_price', $this->course_id) ?: 1249.99;
        
        $available_dates = get_field('course_dates', $this->course_id) ?: [];
        $this->available_dates = array_column($available_dates, 'date');
        
        $this->is_out_of_stock = $this->course_product_id && 
                                function_exists('wc_get_product') && 
                                !wc_get_product($this->course_product_id)->is_in_stock();
        
        $this->launch_date = $this->course_product_id ? 
                            apply_filters('wc_launch_date_get', '', $this->course_product_id) : '';
        
        $this->show_countdown = !empty($this->launch_date) && 
                               strtotime($this->launch_date) > current_time('timestamp');
        
        $this->is_group_course = preg_match('/( - G\d+|\(G\d+\))$/', get_the_title($this->course_id));
        
        // Load custom texts and formatting
        $this->custom_texts = get_post_meta($this->course_id, 'box_custom_texts', true) ?: [];
        $this->date_format = get_post_meta($this->course_id, 'box_date_format', true) ?: 'F j, Y';
        $this->price_format = get_post_meta($this->course_id, 'box_price_format', true) ?: '$%.2f';
        $this->button_text = get_post_meta($this->course_id, 'box_button_text', true) ?: '';
        
    }
    
    /**
     * Check if this box type should be displayed
     * @return bool
     */
    abstract public function should_display();
    
    /**
     * Render the box HTML
     * @return string
     */
    abstract public function render();
    
    /**
     * Get CSS classes for the box
     * @return string
     */
    protected function get_box_classes() {
        $classes = ['box'];
        return implode(' ', $classes);
    }
    
    /**
     * Render add to cart button
     * @param string $text Button text
     * @return string
     */
    protected function render_add_to_cart_button($text = 'Add to Cart') {
        // Use custom button text if available
        if (!empty($this->button_text)) {
            $text = $this->button_text;
        }
        
        return sprintf(
            '<button class="add-to-cart-button" data-product-id="%s">
                <span class="button-text">%s</span>
                <span class="loader" style="display: none;"></span>
            </button>',
            esc_attr($this->course_product_id),
            esc_html($text)
        );
    }
    
    /**
     * Format price display
     * @param float $price
     * @return string
     */
    protected function format_price($price) {
        return sprintf($this->price_format, $price);
    }
    
    /**
     * Format date display
     * @param string $date
     * @return string
     */
    protected function format_date($date) {
        $timestamp = strtotime($date);
        return $timestamp ? date($this->date_format, $timestamp) : $date;
    }
    
    /**
     * Process custom text with placeholders
     * @param string $state Box state
     * @param array $replacements Array of placeholder => value pairs
     * @return string
     */
    protected function process_custom_text($state, $replacements = []) {
        if (!isset($this->custom_texts[$state]) || empty($this->custom_texts[$state])) {
            return '';
        }
        
        $text = $this->custom_texts[$state];
        
        // Replace placeholders
        foreach ($replacements as $placeholder => $value) {
            $text = str_replace('{' . $placeholder . '}', $value, $text);
        }
        
        // Convert newlines to <br> tags
        $text = nl2br($text);
        
        return $text;
    }
}