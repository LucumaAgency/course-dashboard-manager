/**
 * Course Box Manager - Frontend JavaScript
 * Handles add to cart, date selection, and box interactions
 */

(function($) {
    'use strict';

    // Date selection handler
    $(document).on('click', '.date-btn:not(.sold-out)', function() {
        const $btn = $(this);
        const $container = $btn.closest('.box');
        
        // Remove selected class from siblings
        $btn.siblings().removeClass('selected');
        $btn.addClass('selected');
        
        // Update button text if data attribute exists
        const buttonText = $btn.data('button-text');
        if (buttonText) {
            $container.find('.add-to-cart-button .button-text').text(buttonText);
        }
        
        // Store selected date
        $container.data('selected-date', $btn.data('date'));
    });

    // Box selection handler (for selectable boxes)
    window.selectBox = function(element, boxType, courseId) {
        const $box = $(element);
        
        // Toggle selection
        if ($box.hasClass('selected')) {
            $box.removeClass('selected');
            $box.find('.circlecontainer').show();
            $box.find('.circle-container').hide();
        } else {
            // Deselect siblings
            $box.siblings('.box').removeClass('selected');
            $box.siblings('.box').find('.circlecontainer').show();
            $box.siblings('.box').find('.circle-container').hide();
            
            // Select this box
            $box.addClass('selected');
            $box.find('.circlecontainer').hide();
            $box.find('.circle-container').show();
        }
    };

    // Add to cart handler
    $(document).on('click', '.add-to-cart-button:not(.sold-out):not(.loading)', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $box = $button.closest('.box');
        const productId = $button.data('product-id');
        const quantity = $button.data('quantity') || 1;
        const selectedDate = $box.data('selected-date') || $box.find('.date-btn.selected').data('date') || '';
        
        if (!productId) {
            console.error('No product ID found');
            return;
        }
        
        // Check if date selection is required
        if ($box.find('.date-options').length > 0 && !selectedDate) {
            alert('Please select a date');
            return;
        }
        
        // Add loading state
        $button.addClass('loading');
        const originalText = $button.find('.button-text').text();
        $button.find('.button-text').text('Adding...');
        
        // Prepare data
        const data = {
            action: 'woocommerce_add_to_cart',
            product_id: productId,
            quantity: quantity,
            variation_id: 0,
            security: cbm_ajax.nonce
        };
        
        // Add selected date if available
        if (selectedDate) {
            data.start_date = selectedDate;
            data.course_date = selectedDate;
        }
        
        console.log('Adding to cart:', data);
        
        // Make AJAX request
        $.ajax({
            type: 'POST',
            url: cbm_ajax.ajax_url,
            data: data,
            success: function(response) {
                console.log('Cart response:', response);
                
                if (response.success) {
                    // Update button
                    $button.find('.button-text').text('Added!');
                    
                    // Check if FunnelKit Cart is active
                    if (response.use_funnelkit || cbm_ajax.is_funnelkit_active) {
                        // Trigger FunnelKit Cart
                        if (typeof fkcart_show_cart === 'function') {
                            fkcart_show_cart();
                        } else if (typeof FKCart !== 'undefined' && FKCart.show_cart) {
                            FKCart.show_cart();
                        } else {
                            // Try to trigger FunnelKit by event
                            $(document.body).trigger('fkcart_show_cart');
                            $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash]);
                        }
                    } else {
                        // Regular WooCommerce behavior
                        $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash]);
                        
                        // Optionally redirect to cart
                        if (cbm_ajax.cart_url) {
                            setTimeout(function() {
                                window.location.href = cbm_ajax.cart_url;
                            }, 1000);
                        }
                    }
                    
                    // Reset button after delay
                    setTimeout(function() {
                        $button.removeClass('loading');
                        $button.find('.button-text').text(originalText);
                    }, 2000);
                    
                } else {
                    // Error handling
                    alert('Error adding to cart. Please try again.');
                    $button.removeClass('loading');
                    $button.find('.button-text').text(originalText);
                    
                    if (response.product_url) {
                        window.location.href = response.product_url;
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                alert('Error adding to cart. Please try again.');
                $button.removeClass('loading');
                $button.find('.button-text').text(originalText);
            }
        });
    });

    // Initialize on page load
    $(document).ready(function() {
        console.log('Course Box Manager frontend loaded');
        
        // Auto-select first available date if only one option
        $('.box').each(function() {
            const $box = $(this);
            const $dates = $box.find('.date-btn:not(.sold-out)');
            
            if ($dates.length === 1) {
                $dates.first().click();
            }
        });
    });

})(jQuery);