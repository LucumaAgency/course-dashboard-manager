<?php
/**
 * Abstract Box Class
 * 
 * Base class for all course box types
 */

namespace CourseBoxManager\Boxes;

abstract class AbstractBox {
    // Price defaults - only used as last resort fallback
    const DEFAULT_BUY_PRICE = 749.99;
    const DEFAULT_ENROLL_PRICE = 1249.99;

    protected $course_id;
    protected $course;
    protected $box_state;
    protected $course_product_id;
    protected $course_price;
    protected $enroll_price;
    protected $available_dates;
    protected $available_dates_full;  // Added to fix PHP 8.2 deprecated warning
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
        // Ensure we have an array before using array_column
        if (!is_array($available_dates_raw)) {
            $available_dates_raw = [];
        }
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
        
        error_log('[AbstractBox] Rendering add to cart button with product ID: ' . $this->course_product_id . ' and text: ' . $text);
        
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
        // Don't use webinar_price shortcode if we have a specific price
        if ($price && is_numeric($price)) {
            // Format the price using WooCommerce if available
            if (function_exists('wc_price')) {
                return wc_price($price);
            }
            // Fallback to original formatting
            return sprintf($this->price_format, $price);
        }
        
        // Use webinar_price shortcode only as last resort
        if (shortcode_exists('webinar_price')) {
            return do_shortcode('[webinar_price]');
        }
        
        // Final fallback
        return sprintf($this->price_format, $price ?: 0);
    }

    /**
     * Get price from WooCommerce product with fallback to ACF
     * PRIORITY: WooCommerce Product > ACF Field > Default Constant
     *
     * @param int $product_id WooCommerce product ID
     * @param string $acf_field_name ACF field name to use as fallback
     * @param float $default_fallback Default value if all else fails
     * @return array ['price' => float, 'regular' => float|null, 'sale' => float|null, 'source' => string]
     */
    protected function get_price_with_priority($product_id, $acf_field_name, $default_fallback) {
        $result = [
            'price' => $default_fallback,
            'regular' => null,
            'sale' => null,
            'source' => 'default'
        ];

        // PRIORITY 1: Try WooCommerce product
        if ($product_id && function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            if ($product) {
                $result['price'] = $product->get_price();
                $result['regular'] = $product->get_regular_price();
                $result['sale'] = $product->get_sale_price();
                $result['source'] = 'woocommerce';

                error_log("[CBM Price] Using WooCommerce price for product {$product_id}: {$result['price']} (regular: {$result['regular']}, sale: {$result['sale']})");
                return $result;
            }
        }

        // PRIORITY 2: Try ACF field
        $acf_price = cbm_get_field($acf_field_name, $this->course_id, null);
        if ($acf_price !== null && is_numeric($acf_price) && $acf_price > 0) {
            $result['price'] = floatval($acf_price);
            $result['source'] = 'acf';

            error_log("[CBM Price] Using ACF field '{$acf_field_name}' for course {$this->course_id}: {$result['price']}");
            return $result;
        }

        // PRIORITY 3: Use default constant
        error_log("[CBM Price] Using default fallback for course {$this->course_id}: {$default_fallback}");
        return $result;
    }

    /**
     * Get WooCommerce currency symbol
     * @return string
     */
    protected function get_currency() {
        if (function_exists('get_woocommerce_currency')) {
            return get_woocommerce_currency();
        }
        return 'USD'; // Fallback
    }

    /**
     * Validate price value
     * @param mixed $price
     * @return float
     */
    protected function validate_price($price) {
        if (!is_numeric($price) || $price < 0) {
            error_log("[CBM Price] Invalid price value: {$price}");
            return 0;
        }
        return floatval($price);
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

    /**
     * Get remaining seats for the current course
     * @return int|false Returns number of remaining seats or false if not applicable
     */
    protected function get_remaining_seats() {
        // Get enroll product ID
        $enroll_product_id = get_post_meta($this->course_id, 'enroll_product_id', true);

        // If not found, try the linked product ID (for backward compatibility)
        if (!$enroll_product_id) {
            $enroll_product_id = get_post_meta($this->course_id, 'linked_product_id', true);
        }

        if (!$enroll_product_id) {
            return false;
        }

        // Get the first available date from course_dates
        $dates = cbm_get_field('course_dates', $this->course_id) ?: [];
        if (empty($dates)) {
            return false;
        }

        $first_date = null;
        $initial_stock = 10; // Default stock

        foreach ($dates as $date_entry) {
            if (!empty($date_entry['date'])) {
                $first_date = sanitize_text_field($date_entry['date']);
                $initial_stock = isset($date_entry['stock']) ? intval($date_entry['stock']) : 10;
                break;
            }
        }

        if (!$first_date) {
            return false;
        }

        // Calculate sold seats for this date
        $args = [
            'status' => ['wc-completed'],
            'limit' => -1,
        ];

        $orders = wc_get_orders($args);
        $sales_count = 0;

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $item_product_id = $item->get_product_id();
                $start_date = $item->get_meta('Start Date');
                $quantity = $item->get_quantity();

                if ($item_product_id == $enroll_product_id &&
                    strcasecmp(trim($start_date), trim($first_date)) === 0) {
                    $sales_count += $quantity;
                }
            }
        }

        $seats_remaining = $initial_stock - $sales_count;
        return max(0, $seats_remaining);
    }
}