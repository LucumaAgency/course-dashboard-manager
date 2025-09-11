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
                    
                    // Check if FunnelKit Cart should be triggered
                    // Always try to show cart for enroll-buy combo
                    var isEnrollBuyCombo = $button.closest('.enroll-buy-combo').length > 0;
                    
                    if (isEnrollBuyCombo || response.use_funnelkit || cbm_ajax.is_funnelkit_active || typeof fkcart_show_cart === 'function' || typeof FKCart !== 'undefined') {
                        // Small delay to ensure cart is updated
                        setTimeout(function() {
                            console.log('[CBM] Attempting to show FunnelKit cart', {
                                isEnrollBuyCombo: isEnrollBuyCombo,
                                hasFkcartFunction: typeof fkcart_show_cart === 'function',
                                hasFKCart: typeof FKCart !== 'undefined',
                                hasWindowFKCart: window.FKCart ? true : false
                            });
                            
                            if (typeof fkcart_show_cart === 'function') {
                                console.log('[CBM] Using fkcart_show_cart()');
                                fkcart_show_cart();
                            } else if (typeof FKCart !== 'undefined' && FKCart.show_cart) {
                                console.log('[CBM] Using FKCart.show_cart()');
                                FKCart.show_cart();
                            } else if (window.FKCart && window.FKCart.show_cart) {
                                console.log('[CBM] Using window.FKCart.show_cart()');
                                window.FKCart.show_cart();
                            } else if (window.wcffwc_show_cart && typeof window.wcffwc_show_cart === 'function') {
                                console.log('[CBM] Using wcffwc_show_cart()');
                                window.wcffwc_show_cart();
                            } else {
                                console.log('[CBM] Trying FunnelKit events and icon click');
                                // Try to trigger FunnelKit by event
                                $(document.body).trigger('fkcart_show_cart');
                                $(document.body).trigger('fkcart_open');
                                $(document.body).trigger('wcffwc_show_cart');
                                
                                // Try clicking the cart icon (including our moved cart)
                                var $cartIcon = $('#fkcart-floating-toggler, .fkcart-icon-wrap, .fkcart-float-icon, .wcffwc-icon-wrap').first();
                                if ($cartIcon.length > 0) {
                                    console.log('[CBM] Found cart icon, clicking it');
                                    $cartIcon.trigger('click');
                                }
                            }
                        }, 100);
                        
                        // Try again after a longer delay for enroll-buy combo
                        if (isEnrollBuyCombo) {
                            setTimeout(function() {
                                console.log('[CBM] Second attempt to show cart for enroll-buy combo');
                                if (typeof fkcart_show_cart === 'function') {
                                    fkcart_show_cart();
                                } else if ($('.fkcart-icon-wrap, .fkcart-float-icon').length > 0) {
                                    $('.fkcart-icon-wrap, .fkcart-float-icon').first().trigger('click');
                                }
                            }, 500);
                        }
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
                    
                } else if (response.error) {
                    // Error handling
                    $button.removeClass('loading');
                    $button.find('.button-text').text(originalText);
                    
                    // Don't show alert if product was actually added (check cart count)
                    var currentCount = parseInt($('.hfe-cart-count').text()) || 0;
                    if (currentCount > 0) {
                        // Product was added, just trigger cart
                        $button.find('.button-text').text('Added!');
                        setTimeout(function() {
                            if (typeof fkcart_show_cart === 'function') {
                                fkcart_show_cart();
                            }
                            $button.find('.button-text').text(originalText);
                        }, 500);
                    } else {
                        alert('Error adding to cart. Please try again.');
                        if (response.product_url) {
                            window.location.href = response.product_url;
                        }
                    }
                } else {
                    // Unexpected response format but might still be successful
                    $button.removeClass('loading');
                    
                    // Check if cart was updated by looking at fragments
                    if (response.cart_hash || response.fragments) {
                        $button.find('.button-text').text('Added!');
                        $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, $button]);
                        
                        setTimeout(function() {
                            if (typeof fkcart_show_cart === 'function') {
                                fkcart_show_cart();
                            }
                            $button.find('.button-text').text(originalText);
                        }, 1500);
                    } else {
                        $button.find('.button-text').text(originalText);
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
        
        // CRITICAL FIX: Move FunnelKit cart out of popup to body
        const $fkcartInPopup = $('#cbm-popup-overlay #fkcart-floating-toggler, #cbm-popup-content #fkcart-floating-toggler');
        if ($fkcartInPopup.length > 0) {
            console.log('[CBM CRITICAL] FunnelKit cart found inside popup! Moving to body...');
            
            // Clone with events to preserve functionality
            const $clonedCart = $fkcartInPopup.clone(true, true);
            $fkcartInPopup.remove();
            $clonedCart.appendTo('body');
            
            console.log('[CBM CRITICAL] FunnelKit cart moved to body');
            
            // Re-initialize FunnelKit cart events
            setTimeout(function() {
                // Trigger FunnelKit re-initialization if available
                if (typeof window.FKCart !== 'undefined' && window.FKCart.init) {
                    console.log('[CBM] Re-initializing FunnelKit cart...');
                    window.FKCart.init();
                } else if (typeof window.fkcart_init === 'function') {
                    console.log('[CBM] Re-initializing FunnelKit cart with fkcart_init...');
                    window.fkcart_init();
                }
                
                // Manually bind click event if needed
                $('#fkcart-floating-toggler').off('click.cbm').on('click.cbm', function(e) {
                    e.preventDefault();
                    console.log('[CBM] Cart icon clicked, triggering FunnelKit...');
                    
                    // Try various methods to open the cart
                    if (typeof fkcart_show_cart === 'function') {
                        fkcart_show_cart();
                    } else if (window.FKCart && window.FKCart.show_cart) {
                        window.FKCart.show_cart();
                    } else {
                        // Trigger FunnelKit events
                        $(document.body).trigger('fkcart_show_cart');
                        $(document.body).trigger('fkcart_open');
                    }
                });
            }, 100);
        }
        
        // Check if FunnelKit cart exists and ensure it's visible
        console.log('[CBM] Checking FunnelKit cart status...');
        const $fkcart = $('#fkcart-floating-toggler');
        if ($fkcart.length > 0) {
            console.log('[CBM] FunnelKit cart found, ensuring visibility...');
            // Apply visibility styles
            $fkcart.css({
                'display': 'block',
                'visibility': 'visible',
                'opacity': '1'
            });
        } else {
            console.log('[CBM] FunnelKit cart not found on initial check');
        }
        
        // Check again after delay in case FunnelKit loads late
        setTimeout(function() {
            // Check if cart is still inside popup
            const $fkcartStillInPopup = $('#cbm-popup-overlay #fkcart-floating-toggler, #cbm-popup-content #fkcart-floating-toggler');
            if ($fkcartStillInPopup.length > 0) {
                console.log('[CBM] FunnelKit cart still in popup after delay, moving to body...');
                $fkcartStillInPopup.appendTo('body');
            }
            
            const $fkcartToggler = $('#fkcart-floating-toggler');
            console.log('[CBM] Final FunnelKit cart check, found:', $fkcartToggler.length);
            
            if ($fkcartToggler.length > 0) {
                console.log('[CBM] Ensuring FunnelKit cart visibility...');
                $fkcartToggler.css({
                    'display': 'block',
                    'visibility': 'visible',
                    'opacity': '1',
                    'pointer-events': 'auto',
                    'z-index': '999990'
                });
            } else {
                console.log('[CBM] No FunnelKit cart found after delay');
            }
        }, 1000);
        
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