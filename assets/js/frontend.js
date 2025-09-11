/**
 * Course Box Manager - Frontend JavaScript
 * Handles add to cart, date selection, and box interactions
 */

// Define selectBox immediately when script loads
console.log('[CBM] Defining selectBox function...');

window.selectBox = function(element, boxType, courseId) {
    console.log('[CBM] selectBox called', boxType, courseId);
    
    // Wait for jQuery if not loaded yet
    if (typeof jQuery === 'undefined') {
        console.error('[CBM] jQuery not loaded, waiting...');
        setTimeout(function() {
            window.selectBox(element, boxType, courseId);
        }, 100);
        return;
    }
    
    const $ = jQuery;
    const $box = $(element);
    
    // Toggle selection
    if ($box.hasClass('selected')) {
        // Deselect: show simple circle (inactive state)
        $box.removeClass('selected');
        $box.find('.circlecontainer').hide();
        $box.find('.circle-container').show();
    } else {
        // Deselect siblings: show simple circle for them
        $box.siblings('.box').removeClass('selected');
        $box.siblings('.box').find('.circlecontainer').hide();
        $box.siblings('.box').find('.circle-container').show();
        
        // Select this box: show ringed circle (active state)
        $box.addClass('selected');
        $box.find('.circlecontainer').show();
        $box.find('.circle-container').hide();
    }
};

// jQuery-dependent code
(function($) {
    'use strict';

    console.log('[CBM] Frontend jQuery code initializing...');

    // Date selection handler
    $(document).on('click', '.date-btn:not(.sold-out)', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $btn = $(this);
        const $container = $btn.closest('.box');
        
        // Remove selected class from all date buttons in this container
        $container.find('.date-btn').removeClass('selected');
        
        // Add selected class to clicked button
        $btn.addClass('selected');
        
        // Force style update to ensure color is applied
        $btn.css('background-color', '#cc3071');
        
        console.log('[CBM] Date selected:', $btn.text(), 'Has selected class:', $btn.hasClass('selected'));
        
        // Update button text if data attribute exists
        const buttonText = $btn.data('button-text');
        if (buttonText) {
            $container.find('.add-to-cart-button .button-text').text(buttonText);
        }
        
        // Store selected date
        $container.data('selected-date', $btn.data('date'));
        
        // Store STM Course ID if available (for enroll courses)
        const stmCourseId = $btn.data('stm-course-id');
        if (stmCourseId) {
            $container.data('stm-course-id', stmCourseId);
            // Update the add to cart button with the STM Course product ID
            $container.find('.add-to-cart-button').attr('data-product-id', stmCourseId);
            console.log('[CBM] Updated product ID to STM Course:', stmCourseId);
        }
        
        // Force re-render to ensure styles are applied
        $btn.hide().show(0);
    });

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
        
        // Add spinner if it doesn't exist
        if (!$button.find('.loading-spinner').length) {
            $button.append('<span class="loading-spinner"></span>');
        }
        
        // Check if cbm_ajax is available (should always be available now)
        if (typeof cbm_ajax === 'undefined' || !cbm_ajax.ajax_url) {
            console.error('[CBM] AJAX configuration not available - this should not happen');
            console.log('[CBM] window.cbm_ajax:', window.cbm_ajax);
            
            // Try to use window.cbm_ajax as fallback
            if (window.cbm_ajax && window.cbm_ajax.ajax_url) {
                console.log('[CBM] Using window.cbm_ajax as fallback');
                cbm_ajax = window.cbm_ajax;
            } else {
                alert('Error: AJAX configuration not found. Please refresh the page.');
                $button.removeClass('loading');
                $button.find('.button-text').text(originalText);
                return;
            }
        }
        
        console.log('[CBM] Using AJAX URL:', cbm_ajax.ajax_url);
        
        // Prepare data
        const data = {
            action: 'woocommerce_add_to_cart',
            product_id: productId,
            quantity: quantity,
            variation_id: 0,
            security: cbm_ajax.nonce || ''
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
            dataType: 'json',
            success: function(response) {
                console.log('Cart response:', response);
                
                // Check for success - handle both response.success and response.fragments
                if (response.success || response.fragments) {
                    // Remove loading state immediately
                    $button.removeClass('loading');
                    
                    // Update button text briefly to show success
                    $button.find('.button-text').text('Added!');
                    
                    // Mark button to prevent WooCommerce from adding "View cart" link
                    $button.addClass('cbm-processed');
                    
                    // Update cart fragments first
                    if (response.fragments) {
                        $.each(response.fragments, function(key, value) {
                            $(key).replaceWith(value);
                        });
                    }
                    
                    // Trigger WooCommerce added_to_cart event
                    $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, $button]);
                    
                    // Check if FunnelKit Cart is active - simplified approach
                    if (response.use_funnelkit || cbm_ajax.is_funnelkit_active) {
                        // Small delay to ensure cart is updated
                        setTimeout(function() {
                            // Simple approach: just call the functions if they exist
                            if (typeof fkcart_show_cart === 'function') {
                                fkcart_show_cart();
                            } else if (typeof FKCart !== 'undefined' && FKCart.show_cart) {
                                FKCart.show_cart();
                            } else {
                                // Trigger FunnelKit events as fallback
                                $(document.body).trigger('fkcart_show_cart');
                                $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash]);
                            }
                        }, 100);
                    }
                    
                    // Reset button text after delay and remove any "View cart" links
                    setTimeout(function() {
                        // Remove any "View cart" link that WooCommerce might have added
                        $button.siblings('a.added_to_cart').remove();
                        $button.parent().find('a.added_to_cart').remove();
                        
                        // Reset button text
                        $button.find('.button-text').text(originalText);
                        $button.removeClass('cbm-processed');
                    }, 1500);
                    
                } else {
                    // Error handling
                    $button.removeClass('loading');
                    $button.find('.button-text').text(originalText);
                    
                    alert('Error adding to cart. Please try again.');
                    if (response.product_url) {
                        window.location.href = response.product_url;
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('[CBM] AJAX error:', error);
                console.error('[CBM] Response status:', xhr.status);
                console.error('[CBM] Response text:', xhr.responseText ? xhr.responseText.substring(0, 500) : 'empty');
                
                // Remove loading state
                $button.removeClass('loading');
                $button.find('.button-text').text(originalText);
                
                // Check if response is HTML instead of JSON
                if (xhr.responseText && xhr.responseText.indexOf('<!DOCTYPE') !== -1) {
                    console.error('[CBM] Received HTML instead of JSON - AJAX endpoint not found');
                    alert('Error: Server configuration issue. The cart functionality is not available.');
                } else {
                    alert('Error adding to cart. Please try again.');
                }
            }
        });
    });

    // Initialize on page load
    $(document).ready(function() {
        console.log('Course Box Manager frontend loaded');
        
        // Remove any "View cart" links that appear
        $(document).on('DOMNodeInserted', function(e) {
            if ($(e.target).hasClass('added_to_cart') || $(e.target).find('.added_to_cart').length > 0) {
                $('.add-to-cart-button').siblings('a.added_to_cart').remove();
                $('.add-to-cart-button').parent().find('a.added_to_cart').remove();
            }
        });
        
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