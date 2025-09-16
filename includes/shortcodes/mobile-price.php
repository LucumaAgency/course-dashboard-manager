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

    // Get course price (buy price)
    $course_price = cbm_get_field('course_price', $course_id, 0);

    // Get webinar/enroll price
    $webinar_price = cbm_get_field('enroll_price', $course_id, 0);

    // Also check WooCommerce products if linked
    $prices_to_compare = [];

    // Add course price if valid
    if (is_numeric($course_price) && $course_price > 0) {
        $prices_to_compare[] = floatval($course_price);
    }

    // Add webinar price if valid
    if (is_numeric($webinar_price) && $webinar_price > 0) {
        $prices_to_compare[] = floatval($webinar_price);
    }

    // Check buy product price
    $buy_product_id = get_post_meta($course_id, 'linked_product_id', true);
    if ($buy_product_id && function_exists('wc_get_product')) {
        $product = wc_get_product($buy_product_id);
        if ($product) {
            $product_price = $product->get_price();
            if (is_numeric($product_price) && $product_price > 0) {
                $prices_to_compare[] = floatval($product_price);
            }
        }
    }

    // Check enroll product price
    $enroll_product_id = get_post_meta($course_id, 'enroll_product_id', true);
    if ($enroll_product_id && function_exists('wc_get_product')) {
        $product = wc_get_product($enroll_product_id);
        if ($product) {
            $product_price = $product->get_price();
            if (is_numeric($product_price) && $product_price > 0) {
                $prices_to_compare[] = floatval($product_price);
            }
        }
    }

    // Get the minimum price
    $price = !empty($prices_to_compare) ? min($prices_to_compare) : 0;

    // Format the price
    $formatted_price = number_format($price, 2, '.', ',');

    // Build inline style
    $inline_style = 'font-size: 18px;';
    if (!empty($atts['style'])) {
        $inline_style .= ' ' . esc_attr($atts['style']);
    }

    $class = esc_attr($atts['class']);

    $output = sprintf(
        '<span class="%s" style="%s">$%s USD</span>',
        $class,
        $inline_style,
        $formatted_price
    );

    return $output;
}