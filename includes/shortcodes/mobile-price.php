<?php
/**
 * Mobile Price Shortcode
 *
 * Displays the lowest price between course and webinar prices
 *
 * Usage: [mobile_price] or [mobile_price course_id="123"]
 * Output: <span style="font-size: 18px;">$749.99 USD</span>
 */

namespace CourseBoxManager\Shortcodes;

add_shortcode('mobile_price', __NAMESPACE__ . '\\mobile_price_shortcode');

function mobile_price_shortcode($atts) {
    $atts = shortcode_atts(array(
        'course_id' => get_the_ID(),
        'class' => 'mobile-price',
        'style' => ''
    ), $atts);

    $course_id = intval($atts['course_id']);

    // Determine box state to know which price to show
    $box_state = get_post_meta($course_id, 'box_state', true) ?: 'enroll-course';

    error_log('[mobile_price] Course ' . $course_id . ', box_state: ' . $box_state);

    $prices_to_compare = [];

    // For enroll-buy state: get both prices and show the minimum
    if ($box_state === 'enroll-buy') {
        // Get buy price with WooCommerce priority
        $buy_product_id = get_post_meta($course_id, 'buy_product_id', true);
        if ($buy_product_id && function_exists('wc_get_product')) {
            $product = wc_get_product($buy_product_id);
            if ($product) {
                $buy_price = $product->get_price();
                if (is_numeric($buy_price) && $buy_price > 0) {
                    $prices_to_compare[] = floatval($buy_price);
                    error_log('[mobile_price] Buy product ' . $buy_product_id . ': ' . $buy_price);
                }
            }
        }

        // Get enroll prices from course_dates (each date can have different product_id)
        $course_dates = cbm_get_field('course_dates', $course_id) ?: [];
        if (!empty($course_dates)) {
            foreach ($course_dates as $date_entry) {
                $date_product_id = isset($date_entry['product_id']) ? $date_entry['product_id'] : null;

                if ($date_product_id && function_exists('wc_get_product')) {
                    $date_product = wc_get_product($date_product_id);
                    if ($date_product) {
                        $date_price = $date_product->get_price();
                        if (is_numeric($date_price) && $date_price > 0) {
                            $prices_to_compare[] = floatval($date_price);
                            error_log('[mobile_price] Enroll date product ' . $date_product_id . ': ' . $date_price);
                        }
                    }
                }
            }
        }

        // Fallback to enroll_product_id if no date-specific products found
        if (empty($prices_to_compare)) {
            $enroll_product_id = get_post_meta($course_id, 'enroll_product_id', true);
            if ($enroll_product_id && function_exists('wc_get_product')) {
                $product = wc_get_product($enroll_product_id);
                if ($product) {
                    $enroll_price = $product->get_price();
                    if (is_numeric($enroll_price) && $enroll_price > 0) {
                        $prices_to_compare[] = floatval($enroll_price);
                        error_log('[mobile_price] Enroll product ' . $enroll_product_id . ': ' . $enroll_price);
                    }
                }
            }
        }

        // Final fallback to ACF if no WooCommerce products
        if (empty($prices_to_compare)) {
            $course_price = cbm_get_field('course_price', $course_id, null);
            $enroll_price = cbm_get_field('enroll_price', $course_id, null);

            if (is_numeric($course_price) && $course_price > 0) {
                $prices_to_compare[] = floatval($course_price);
            }
            if (is_numeric($enroll_price) && $enroll_price > 0) {
                $prices_to_compare[] = floatval($enroll_price);
            }
        }
    }
    // For buy-course state: get buy price only
    elseif ($box_state === 'buy-course') {
        $buy_product_id = get_post_meta($course_id, 'buy_product_id', true);
        if (!$buy_product_id) {
            $buy_product_id = get_post_meta($course_id, 'linked_product_id', true);
        }

        if ($buy_product_id && function_exists('wc_get_product')) {
            $product = wc_get_product($buy_product_id);
            if ($product) {
                $buy_price = $product->get_price();
                if (is_numeric($buy_price) && $buy_price > 0) {
                    $prices_to_compare[] = floatval($buy_price);
                }
            }
        }

        // Fallback to ACF
        if (empty($prices_to_compare)) {
            $course_price = cbm_get_field('course_price', $course_id, 749.99);
            if (is_numeric($course_price) && $course_price > 0) {
                $prices_to_compare[] = floatval($course_price);
            }
        }
    }
    // For enroll-course state: get enroll prices from course_dates
    else {
        // Get prices from course_dates (each date can have different product_id)
        $course_dates = cbm_get_field('course_dates', $course_id) ?: [];
        if (!empty($course_dates)) {
            foreach ($course_dates as $date_entry) {
                $date_product_id = isset($date_entry['product_id']) ? $date_entry['product_id'] : null;

                if ($date_product_id && function_exists('wc_get_product')) {
                    $date_product = wc_get_product($date_product_id);
                    if ($date_product) {
                        $date_price = $date_product->get_price();
                        if (is_numeric($date_price) && $date_price > 0) {
                            $prices_to_compare[] = floatval($date_price);
                            error_log('[mobile_price] Enroll date product ' . $date_product_id . ': ' . $date_price);
                        }
                    }
                }
            }
        }

        // Fallback to enroll_product_id or linked_product_id
        if (empty($prices_to_compare)) {
            $enroll_product_id = get_post_meta($course_id, 'enroll_product_id', true);
            if (!$enroll_product_id) {
                $enroll_product_id = get_post_meta($course_id, 'linked_product_id', true);
            }

            if ($enroll_product_id && function_exists('wc_get_product')) {
                $product = wc_get_product($enroll_product_id);
                if ($product) {
                    $enroll_price = $product->get_price();
                    if (is_numeric($enroll_price) && $enroll_price > 0) {
                        $prices_to_compare[] = floatval($enroll_price);
                    }
                }
            }
        }

        // Final fallback to ACF
        if (empty($prices_to_compare)) {
            $enroll_price = cbm_get_field('enroll_price', $course_id, 1249.99);
            if (is_numeric($enroll_price) && $enroll_price > 0) {
                $prices_to_compare[] = floatval($enroll_price);
            }
        }
    }

    // Get the minimum price from the relevant products
    $price = !empty($prices_to_compare) ? min($prices_to_compare) : 0;

    error_log('[mobile_price] Final price: ' . $price . ' (from ' . count($prices_to_compare) . ' prices)');

    // Format the price
    $formatted_price = number_format($price, 2, '.', ',');

    // Build inline style
    $inline_style = 'font-size: 18px;';
    if (!empty($atts['style'])) {
        $inline_style .= ' ' . esc_attr($atts['style']);
    }

    $class = esc_attr($atts['class']);

    // Get remaining seats if applicable
    $seats_html = '';

    // Check the box state configured in the dashboard
    $box_state = get_post_meta($course_id, 'box_state', true);
    $show_seats = in_array($box_state, ['enroll-course', 'enroll-buy']);

    if ($show_seats) {
        // Get enroll product ID
        $enroll_product_id = get_post_meta($course_id, 'enroll_product_id', true);

        // If not found, try the linked product ID (for backward compatibility)
        if (!$enroll_product_id) {
            $enroll_product_id = get_post_meta($course_id, 'linked_product_id', true);
        }

        if ($enroll_product_id) {
            // Get the first available date from course_dates
            $dates = cbm_get_field('course_dates', $course_id) ?: [];

            if (!empty($dates)) {
                $first_date = null;
                $initial_stock = 10; // Default stock

                foreach ($dates as $date_entry) {
                    if (!empty($date_entry['date'])) {
                        $first_date = sanitize_text_field($date_entry['date']);
                        $initial_stock = isset($date_entry['stock']) ? intval($date_entry['stock']) : 10;
                        break;
                    }
                }

                if ($first_date && function_exists('wc_get_orders')) {
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

                    $seats_remaining = max(0, $initial_stock - $sales_count);

                    if ($seats_remaining > 0) {
                        $seats_html = sprintf(
                            '<span class="mobile-seats" style="display: block; font-size: 14px; margin-top: 5px;">%d Remaining seats</span>',
                            $seats_remaining
                        );
                    }
                }
            }
        }
    }

    $output = sprintf(
        '<span class="%s" style="%s">$%s USD%s</span>',
        $class,
        $inline_style,
        $formatted_price,
        $seats_html
    );

    return $output;
}