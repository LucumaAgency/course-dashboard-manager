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
                                
                                // Try clicking the cart icon
                                var $cartIcon = $('.fkcart-icon-wrap, .fkcart-float-icon, .wcffwc-icon-wrap').first();
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
        
        // Force FunnelKit cart visibility on page load
        console.log('[CBM Debug] Starting FunnelKit cart visibility check...');
        
        // Check immediately
        console.log('[CBM Debug] Checking for FunnelKit elements immediately...');
        console.log('[CBM Debug] #fkcart-floating-toggler exists:', $('#fkcart-floating-toggler').length);
        console.log('[CBM Debug] .fkcart-toggler exists:', $('.fkcart-toggler').length);
        console.log('[CBM Debug] .fkcart-float-icon exists:', $('.fkcart-float-icon').length);
        console.log('[CBM Debug] .fkcart-icon-wrap exists:', $('.fkcart-icon-wrap').length);
        
        // Log current styles
        if ($('#fkcart-floating-toggler').length > 0) {
            const elem = $('#fkcart-floating-toggler')[0];
            console.log('[CBM Debug] Current fkcart-floating-toggler styles:', {
                display: elem.style.display,
                visibility: elem.style.visibility,
                opacity: elem.style.opacity,
                computedDisplay: window.getComputedStyle(elem).display,
                computedVisibility: window.getComputedStyle(elem).visibility,
                computedOpacity: window.getComputedStyle(elem).opacity,
                computedPosition: window.getComputedStyle(elem).position,
                computedZIndex: window.getComputedStyle(elem).zIndex
            });
        }
        
        setTimeout(function() {
            console.log('[CBM Debug] After 1 second delay, checking FunnelKit elements...');
            const $fkcartToggler = $('#fkcart-floating-toggler, .fkcart-toggler, .fkcart-float-icon, .fkcart-icon-wrap');
            console.log('[CBM Debug] Found FunnelKit elements:', $fkcartToggler.length);
            
            if ($fkcartToggler.length > 0) {
                console.log('[CBM Debug] Attempting to force visibility on', $fkcartToggler.length, 'elements');
                
                $fkcartToggler.each(function(index) {
                    const $elem = $(this);
                    console.log('[CBM Debug] Element', index, ':', {
                        tagName: this.tagName,
                        id: this.id,
                        className: this.className,
                        beforeStyles: {
                            display: this.style.display,
                            visibility: this.style.visibility,
                            opacity: this.style.opacity
                        }
                    });
                    
                    $elem.css({
                        'display': 'block',
                        'visibility': 'visible',
                        'opacity': '1',
                        'pointer-events': 'auto',
                        'z-index': '999990'
                    }).show();
                    
                    console.log('[CBM Debug] After forcing styles on element', index, ':', {
                        display: this.style.display,
                        visibility: this.style.visibility,
                        opacity: this.style.opacity
                    });
                });
                
                // Also ensure parent containers are visible
                $fkcartToggler.parents().each(function() {
                    if ($(this).hasClass('fkcart-icon-wrap') || $(this).hasClass('fkcart-float-icon') || $(this).hasClass('fkcart-wrapper')) {
                        console.log('[CBM Debug] Found parent container:', this.className);
                        $(this).css({
                            'display': 'block',
                            'visibility': 'visible',
                            'opacity': '1'
                        });
                    }
                });
                
                // Final check
                setTimeout(function() {
                    console.log('[CBM Debug] Final check after 2 seconds:');
                    if ($('#fkcart-floating-toggler').length > 0) {
                        const elem = $('#fkcart-floating-toggler')[0];
                        const rect = elem.getBoundingClientRect();
                        const styles = window.getComputedStyle(elem);
                        
                        console.log('[CBM Debug] Final fkcart-floating-toggler complete analysis:', {
                            // Visibility
                            display: styles.display,
                            visibility: styles.visibility,
                            opacity: styles.opacity,
                            
                            // Positioning
                            position: styles.position,
                            top: styles.top,
                            right: styles.right,
                            bottom: styles.bottom,
                            left: styles.left,
                            zIndex: styles.zIndex,
                            
                            // Dimensions
                            width: styles.width,
                            height: styles.height,
                            actualWidth: rect.width,
                            actualHeight: rect.height,
                            
                            // Location on screen
                            boundingRect: {
                                top: rect.top,
                                right: rect.right,
                                bottom: rect.bottom,
                                left: rect.left,
                                x: rect.x,
                                y: rect.y
                            },
                            
                            // Colors
                            backgroundColor: styles.backgroundColor,
                            color: styles.color,
                            
                            // Overflow
                            overflow: styles.overflow,
                            
                            // Transform
                            transform: styles.transform,
                            
                            // Children count
                            childrenCount: elem.children.length,
                            innerHTML: elem.innerHTML.substring(0, 200) // First 200 chars
                        });
                        
                        // Check children visibility
                        console.log('[CBM Debug] Checking children elements:');
                        $(elem).find('*').each(function(i) {
                            if (i < 5) { // Check first 5 children
                                const childStyles = window.getComputedStyle(this);
                                console.log('[CBM Debug] Child', i, ':', {
                                    tagName: this.tagName,
                                    className: this.className,
                                    display: childStyles.display,
                                    visibility: childStyles.visibility,
                                    opacity: childStyles.opacity,
                                    width: childStyles.width,
                                    height: childStyles.height
                                });
                            }
                        });
                        
                        // Try to force everything visible one more time
                        console.log('[CBM Debug] Forcing all FunnelKit elements visible with !important...');
                        const forceStyle = 'display: block !important; visibility: visible !important; opacity: 1 !important; width: auto !important; height: auto !important; position: fixed !important; z-index: 999999 !important;';
                        elem.setAttribute('style', forceStyle);
                        
                        // Also force children
                        $(elem).find('*').attr('style', function(i, s) {
                            return (s || '') + '; display: block !important; visibility: visible !important; opacity: 1 !important;';
                        });
                        
                        console.log('[CBM Debug] Forced styles applied. Element should now be visible if nothing else is blocking it.');
                    }
                }, 1000);
            } else {
                console.log('[CBM Debug] No FunnelKit elements found after 1 second');
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