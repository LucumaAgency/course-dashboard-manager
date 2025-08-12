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
        $this->course_price = cbm_get_field('course_price', $this->course_id, 749.99);
        $this->enroll_price = cbm_get_field('enroll_price', $this->course_id, 1249.99);
        
        $available_dates_raw = cbm_get_field('course_dates', $this->course_id, []);
        $this->available_dates = array_column($available_dates_raw, 'date');
        $this->available_dates_full = $available_dates_raw; // Keep full date info with stock and button_text
        
        // Debug logging
        error_log('[CBM Debug] Course ' . $this->course_id . ' properties:');
        error_log('[CBM Debug] - box_state: ' . $this->box_state);
        error_log('[CBM Debug] - product_id: ' . $this->course_product_id);
        error_log('[CBM Debug] - available_dates: ' . json_encode($this->available_dates));
        
        // Only check stock if product exists
        $this->is_out_of_stock = false;
        if ($this->course_product_id && function_exists('wc_get_product')) {
            $product = wc_get_product($this->course_product_id);
            $this->is_out_of_stock = $product ? !$product->is_in_stock() : false;
        }
        
        $this->launch_date = $this->course_product_id ? 
                            apply_filters('wc_launch_date_get', '', $this->course_product_id) : '';
        
        $this->show_countdown = !empty($this->launch_date) && 
                               strtotime($this->launch_date) > current_time('timestamp');
        
        error_log('[CBM Debug] - is_out_of_stock: ' . ($this->is_out_of_stock ? 'true' : 'false'));
        error_log('[CBM Debug] - show_countdown: ' . ($this->show_countdown ? 'true' : 'false'));
        
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
        
        // If no product ID, show a placeholder button or link
        if (!$this->course_product_id) {
            return sprintf(
                '<button class="add-to-cart-button no-product" disabled>
                    <span class="button-text">%s (No Product)</span>
                </button>',
                esc_html($text)
            );
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
        // Use webinar_price shortcode if it exists
        if (shortcode_exists('webinar_price')) {
            // Return the HTML output from the shortcode without escaping
            return do_shortcode('[webinar_price]');
        }
        // Fallback to original formatting (this should be escaped when used)
        return sprintf($this->price_format, $price);
    }
    
    /**
     * Format date display - returns text as-is for text dates
     * @param string $date
     * @return string
     */
    protected function format_date($date) {
        // Return the date text as-is, no formatting
        return $date;
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