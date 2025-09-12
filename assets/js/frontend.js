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
        
        // Don't auto-select dates - let the user choose
    }
};

// jQuery-dependent code
(function($) {
    'use strict';

    console.log('[CBM] Frontend jQuery code initializing...');

    // Date selection handler - only for OUR date buttons
    // Use more specific selector to avoid interfering with other plugins
    $('body').on('click', '.box .date-btn:not(.sold-out)', function(e) {
        e.preventDefault();
        // Don't stop propagation - let events bubble
        
        console.log('[CBM] Date button click event triggered!');
        
        const $btn = $(this);
        let $container = $btn.closest('.box');
        
        // If we're in an enroll-buy combo, we might have multiple containers
        // Make sure we're working with the right one
        if (!$container.length) {
            $container = $btn.closest('.cbm-tab-pane, .box-wrapper-no-select').find('.box');
        }
        
        // Get the date value - try multiple sources
        let dateValue = $btn.data('date') || $btn.attr('data-date') || $btn.text().trim();
        
        console.log('[CBM] Date button clicked:', dateValue);
        
        // Find ALL date buttons in the current box
        const $allDateBtnsInBox = $container.find('.date-btn');
        
        console.log('[CBM] Deselecting', $allDateBtnsInBox.length, 'date buttons in current box');
        
        // Remove selected class from all date buttons in this box
        $allDateBtnsInBox.removeClass('selected');
        
        // Add selected class to clicked button
        $btn.addClass('selected');
        
        // Force the style to ensure it's visible
        setTimeout(function() {
            if (!$btn.hasClass('selected')) {
                console.log('[CBM] Forcing selected class');
                $btn.addClass('selected');
            }
            // Log final state
            console.log('[CBM] Final button classes:', $btn.attr('class'));
            console.log('[CBM] Selected dates in box:', $container.find('.date-btn.selected').length);
        }, 10);
        
        // Update button text if data attribute exists
        const buttonText = $btn.data('button-text');
        if (buttonText) {
            $container.find('.add-to-cart-button .button-text').text(buttonText);
        }
        
        // Store selected date
        $container.data('selected-date', dateValue);
        $container.attr('data-selected-date', dateValue);
        
        console.log('[CBM] Date stored:', dateValue);
        
        // Store STM Course ID if available (for enroll courses)
        const stmCourseId = $btn.data('stm-course-id');
        if (stmCourseId) {
            $container.data('stm-course-id', stmCourseId);
            $container.find('.add-to-cart-button').attr('data-product-id', stmCourseId);
            console.log('[CBM] Updated product ID to STM Course:', stmCourseId);
        }
        
        // Trigger custom event
        $container.trigger('dateSelected', [dateValue]);
    });

    // Add to cart handler - only for OUR add to cart buttons
    $(document).on('click', '.box .add-to-cart-button:not(.sold-out):not(.loading)', function(e) {
        e.preventDefault();
        // Don't stop propagation - let other handlers work
        
        const $button = $(this);
        let $box = $button.closest('.box');
        
        const productId = $button.data('product-id');
        const quantity = $button.data('quantity') || 1;
        
        // Debug: Log the box we're working with
        console.log('[CBM] Add to cart clicked');
        console.log('[CBM] Box element:', $box[0]);
        console.log('[CBM] Box classes:', $box.attr('class'));
        
        // Try multiple ways to get the selected date
        let selectedDate = '';
        
        // Method 1: Find selected date button within this specific box
        const $selectedDateBtn = $box.find('.date-btn.selected');
        console.log('[CBM] Found selected date buttons in box:', $selectedDateBtn.length);
        if ($selectedDateBtn.length > 0) {
            selectedDate = $selectedDateBtn.data('date') || 
                          $selectedDateBtn.attr('data-date') || 
                          $selectedDateBtn.text().trim();
            console.log('[CBM] Method 1 - Selected date from button:', selectedDate);
        }
        
        // Method 2: Check data attribute on box
        if (!selectedDate) {
            selectedDate = $box.data('selected-date') || $box.attr('data-selected-date');
            console.log('[CBM] Method 2 - Box data selected-date:', selectedDate);
        }
        
        // Method 3: Check parent containers for date selection
        if (!selectedDate) {
            const $parentContainer = $box.closest('.box-wrapper-no-select, .cbm-tab-pane');
            const $selectedDateInParent = $parentContainer.find('.date-btn.selected');
            if ($selectedDateInParent.length > 0) {
                selectedDate = $selectedDateInParent.data('date') || 
                              $selectedDateInParent.attr('data-date') || 
                              $selectedDateInParent.text().trim();
                console.log('[CBM] Method 3 - Date from parent container:', selectedDate);
            }
        }
        
        // Method 4: Check if there's only one date and auto-select it
        if (!selectedDate) {
            const $allDates = $box.find('.date-btn:not(.sold-out)');
            if ($allDates.length === 1) {
                selectedDate = $allDates.first().data('date') || 
                              $allDates.first().attr('data-date') || 
                              $allDates.first().text().trim();
                // Auto-select this date visually too
                $allDates.first().addClass('selected');
                console.log('[CBM] Method 4 - Single date auto-selected:', selectedDate);
            }
        }
        
        console.log('[CBM] Final selected date:', selectedDate);
        
        // Check how many date buttons exist in this box
        const $allDateButtons = $box.find('.date-btn');
        const $availableDateButtons = $box.find('.date-btn:not(.sold-out)');
        
        console.log('[CBM] Total date buttons:', $allDateButtons.length);
        console.log('[CBM] Available date buttons:', $availableDateButtons.length);
        
        if (!productId) {
            console.error('[CBM] No product ID found');
            return;
        }
        
        // Only require date selection if there are available date buttons to choose from
        if ($availableDateButtons.length > 0 && !selectedDate) {
            console.error('[CBM] Date validation failed - available dates exist but none selected');
            console.error('[CBM] Available dates:', $availableDateButtons.map(function() {
                return $(this).data('date') || $(this).text();
            }).get());
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
        
        // Prepare data - use standard WooCommerce action
        // Let FunnelKit handle it if active, otherwise WooCommerce handles it
        const data = {
            action: 'woocommerce_add_to_cart',
            product_id: productId,
            quantity: quantity,
            variation_id: 0,
            nonce: cbm_ajax.nonce || '',
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
                
                // Check if product was added successfully
                // FunnelKit might return just fragments without success field
                const wasSuccessful = response.success || (response.fragments && response.cart_hash);
                
                if (wasSuccessful) {
                    // Update button
                    $button.find('.button-text').text('Added!');
                    
                    // Debug FunnelKit
                    console.log('[CBM] FunnelKit check - response.use_funnelkit:', response.use_funnelkit);
                    console.log('[CBM] FunnelKit check - cbm_ajax.is_funnelkit_active:', cbm_ajax.is_funnelkit_active);
                    console.log('[CBM] FunnelKit check - typeof fkcart_show_cart:', typeof fkcart_show_cart);
                    console.log('[CBM] FunnelKit check - typeof FKCart:', typeof FKCart);
                    console.log('[CBM] FunnelKit check - window.FKCart:', window.FKCart);
                    
                    // Always trigger the WooCommerce added_to_cart event first
                    $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash]);
                    
                    // Check if FunnelKit Cart is active (check multiple ways)
                    const isFunnelKitActive = response.use_funnelkit || 
                                              cbm_ajax.is_funnelkit_active || 
                                              (typeof window.FKCart !== 'undefined') ||
                                              (typeof window.fkcart_show_cart === 'function') ||
                                              $('.fkcart-icon, .fkcart-trigger').length > 0;
                    
                    if (isFunnelKitActive) {
                        console.log('[CBM] FunnelKit detected, attempting to show cart...');
                        
                        // Wait for cart fragments to be processed
                        setTimeout(function() {
                            // Use our global function to open cart
                            const opened = window.cbmOpenFunnelKitCart();
                            if (!opened) {
                                console.log('[CBM] Failed to open FunnelKit cart, waiting longer...');
                                // Try again after a longer delay
                                setTimeout(function() {
                                    window.cbmOpenFunnelKitCart();
                                }, 500);
                            }
                        }, 300); // Initial delay for cart update
                    } else {
                        console.log('[CBM] FunnelKit not detected, using regular WooCommerce behavior');
                        // Regular WooCommerce behavior only if FunnelKit not detected
                        
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
                        $button.find('.loading-spinner').remove(); // Remove spinner from DOM
                    }, 2000);
                    
                } else if (response.error || response.success === false) {
                    // Error handling - only if explicitly an error
                    const errorMessage = response.data || response.error || 'Error adding to cart. Please try again.';
                    alert(errorMessage);
                    $button.removeClass('loading');
                    $button.find('.button-text').text(originalText);
                    $button.find('.loading-spinner').remove(); // Remove spinner from DOM
                    
                    if (response.product_url) {
                        window.location.href = response.product_url;
                    }
                } else {
                    // Unknown response format - assume success if we got here
                    console.log('[CBM] Unknown response format, assuming success');
                    $button.find('.button-text').text('Added!');
                    $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash]);
                    
                    setTimeout(function() {
                        $button.removeClass('loading');
                        $button.find('.button-text').text(originalText);
                        $button.find('.loading-spinner').remove();
                    }, 2000);
                }
            },
            error: function(xhr, status, error) {
                console.error('[CBM] AJAX error:', error);
                console.error('[CBM] Response status:', xhr.status);
                console.error('[CBM] Response text:', xhr.responseText ? xhr.responseText.substring(0, 500) : 'empty');
                
                // Check if response is HTML instead of JSON
                if (xhr.responseText && xhr.responseText.indexOf('<!DOCTYPE') !== -1) {
                    console.error('[CBM] Received HTML instead of JSON - AJAX endpoint not found');
                    alert('Error: Server configuration issue. The cart functionality is not available.');
                } else {
                    alert('Error adding to cart. Please try again.');
                }
                
                $button.removeClass('loading');
                $button.find('.button-text').text(originalText);
                $button.find('.loading-spinner').remove(); // Remove spinner from DOM
            }
        });
    });

    // Initialize on page load
    $(document).ready(function() {
        console.log('Course Box Manager frontend loaded');
        
        try {
            // NO auto-selection of dates - let user choose
            // Only initialize the Enroll-Buy combo box selection
            initEnrollBuySelection();
            
            // Re-bind date button handlers after a short delay to ensure DOM is ready
            setTimeout(function() {
                console.log('[CBM] Re-binding date button handlers');
                
                // Direct binding to existing date buttons
                $('.date-btn:not(.sold-out)').off('click.cbm').on('click.cbm', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('[CBM] Direct date button clicked!');
                
                const $btn = $(this);
                const $container = $btn.closest('.box');
                const dateValue = $btn.data('date') || $btn.attr('data-date') || $btn.text().trim();
                
                console.log('[CBM] Processing date selection:', dateValue);
                
                // Remove selected from all dates in this box
                $container.find('.date-btn').removeClass('selected');
                
                // Add selected to clicked button
                $btn.addClass('selected');
                
                // Store the date
                $container.data('selected-date', dateValue);
                $container.attr('data-selected-date', dateValue);
                
                console.log('[CBM] Date selection complete:', dateValue);
                console.log('[CBM] Button has selected class:', $btn.hasClass('selected'));
            });
        }, 500);
        } catch (error) {
            console.error('[CBM] Error during initialization:', error);
        }
    });
    
    // Handle Enroll-Buy combo box selection
    function initEnrollBuySelection() {
        console.log('[CBM] Initializing Enroll-Buy selection');
        
        // Find all enroll-buy combo containers
        $('.enroll-buy-combo').each(function() {
            const $container = $(this);
            const $boxes = $container.find('.box');
            
            // Add click handler to each box
            $boxes.off('click.boxselect').on('click.boxselect', function(e) {
                // Don't process if clicking on buttons or dates
                if ($(e.target).closest('.add-to-cart-button, .date-btn').length) {
                    return;
                }
                
                const $clickedBox = $(this);
                
                // Deselect all boxes in this container
                $boxes.removeClass('selected');
                $boxes.find('.circlecontainer').hide();
                $boxes.find('.circle-container').show();
                
                // Select the clicked box
                $clickedBox.addClass('selected');
                $clickedBox.find('.circlecontainer').show();
                $clickedBox.find('.circle-container').hide();
                
                // Don't auto-select any dates - let the user choose
                
                console.log('[CBM] Box selected:', $clickedBox.closest('.buy-box-wrapper, .enroll-box-wrapper').attr('class'));
            });
            
            // Set initial state: Buy box selected by default
            // Find buy boxes in both desktop and mobile layouts
            const $buyBoxDesktop = $container.find('.buy-box-wrapper .box.buy-course');
            const $buyBoxMobile = $container.find('[data-tab="buy"] .box.buy-course');
            const $enrollBoxDesktop = $container.find('.enroll-box-wrapper .box.enroll-course');
            const $enrollBoxMobile = $container.find('[data-tab="enroll"] .box.enroll-course');
            
            // Select buy boxes by default
            if ($buyBoxDesktop.length) {
                $buyBoxDesktop.addClass('selected');
                $buyBoxDesktop.find('.circlecontainer').show();
                $buyBoxDesktop.find('.circle-container').hide();
            }
            
            if ($buyBoxMobile.length) {
                $buyBoxMobile.addClass('selected');
                $buyBoxMobile.find('.circlecontainer').show();
                $buyBoxMobile.find('.circle-container').hide();
            }
            
            // Make sure enroll boxes are not selected
            if ($enrollBoxDesktop.length) {
                $enrollBoxDesktop.removeClass('selected');
                $enrollBoxDesktop.find('.circlecontainer').hide();
                $enrollBoxDesktop.find('.circle-container').show();
            }
            
            if ($enrollBoxMobile.length) {
                $enrollBoxMobile.removeClass('selected');
                $enrollBoxMobile.find('.circlecontainer').hide();
                $enrollBoxMobile.find('.circle-container').show();
            }
            
            console.log('[CBM] Buy box selected by default');
        });
    }

    // Function to debug FunnelKit availability
    window.cbmDebugFunnelKit = function() {
        console.log('[CBM] === FunnelKit Debug Info ===');
        console.log('[CBM] window.fkcart:', typeof window.fkcart !== 'undefined' ? window.fkcart : 'Not found');
        console.log('[CBM] window.FKCart:', typeof window.FKCart !== 'undefined' ? window.FKCart : 'Not found');
        console.log('[CBM] window.fkcart_show_cart:', typeof window.fkcart_show_cart);
        
        // Check for Vue or React instances
        console.log('[CBM] Vue detected:', typeof window.Vue !== 'undefined');
        console.log('[CBM] React detected:', typeof window.React !== 'undefined');
        
        // Check for FunnelKit in other locations
        console.log('[CBM] Checking for FunnelKit in other locations:');
        for (let key in window) {
            if (key.toLowerCase().includes('fk') || key.toLowerCase().includes('funnel')) {
                console.log('[CBM] Found:', key, '=', typeof window[key]);
            }
        }
        
        // Check for FunnelKit elements in DOM
        console.log('[CBM] Cart elements found:');
        console.log('[CBM] - .fkcart-modal:', jQuery('.fkcart-modal').length);
        console.log('[CBM] - .fkcart-coupon-button:', jQuery('.fkcart-coupon-button').length);
        console.log('[CBM] - #fkcart-coupon__input:', jQuery('#fkcart-coupon__input').length);
        
        // Check for event listeners using native method
        const couponBtn = jQuery('.fkcart-coupon-button')[0];
        if (couponBtn) {
            // Check jQuery events
            const jqEvents = jQuery._data(couponBtn, 'events');
            console.log('[CBM] jQuery event listeners on coupon button:', jqEvents);
            
            // Check for onclick attribute
            console.log('[CBM] onclick attribute:', couponBtn.onclick);
            
            // Check for addEventListener events (harder to detect)
            console.log('[CBM] Button element:', couponBtn);
        }
        
        // Check for AJAX settings
        if (typeof fkcart_data !== 'undefined') {
            console.log('[CBM] fkcart_data found:', fkcart_data);
        }
        if (typeof fkcart_settings !== 'undefined') {
            console.log('[CBM] fkcart_settings found:', fkcart_settings);
        }
        if (typeof fkcart_ajax !== 'undefined') {
            console.log('[CBM] fkcart_ajax found:', fkcart_ajax);
        }
        if (typeof fkcart_app_data !== 'undefined') {
            console.log('[CBM] fkcart_app_data found:', fkcart_app_data);
        }
        
        // Try to find Vue instance
        console.log('[CBM] Looking for Vue instance...');
        const cartModal = document.querySelector('.fkcart-modal');
        if (cartModal && cartModal.__vue__) {
            console.log('[CBM] Vue instance found on .fkcart-modal!');
            window.fkcartVue = cartModal.__vue__;
            console.log('[CBM] Vue instance methods:', Object.keys(cartModal.__vue__.$options.methods || {}));
            console.log('[CBM] Vue instance data:', cartModal.__vue__.$data);
        }
        
        // Check coupon button for Vue
        if (couponBtn && couponBtn.__vue__) {
            console.log('[CBM] Vue instance found on coupon button!');
            console.log('[CBM] Button Vue methods:', Object.keys(couponBtn.__vue__.$options.methods || {}));
        }
        
        return true;
    };
    
    // Global function to open FunnelKit cart (accessible from console for testing)
    window.cbmOpenFunnelKitCart = function() {
        console.log('[CBM] Attempting to open FunnelKit cart...');
        
        // Method 1: Direct function calls
        if (typeof window.fkcart_show_cart === 'function') {
            console.log('[CBM] Using fkcart_show_cart()');
            return window.fkcart_show_cart();
        }
        
        if (window.FKCart && typeof window.FKCart.show_cart === 'function') {
            console.log('[CBM] Using FKCart.show_cart()');
            return window.FKCart.show_cart();
        }
        
        if (window.fkcart && typeof window.fkcart.open_cart === 'function') {
            console.log('[CBM] Using fkcart.open_cart()');
            return window.fkcart.open_cart();
        }
        
        // Method 2: Find and click cart icon
        const selectors = [
            '.fkcart-icon',
            '.fkcart-trigger',
            '[data-fkcart="trigger"]',
            '.fk-cart-icon',
            '.fkcart-modal-toggle',
            'a[href="#fkcart"]',
            '.fkcart-float-icon'
        ];
        
        for (let selector of selectors) {
            const element = document.querySelector(selector);
            if (element) {
                console.log('[CBM] Found cart element:', selector);
                element.click();
                return true;
            }
        }
        
        // Method 3: Trigger events
        console.log('[CBM] Triggering FunnelKit events...');
        jQuery(document.body).trigger('fkcart_show_cart');
        jQuery(document.body).trigger('fkcart_open');
        
        return false;
    };

    // Debug AJAX requests to see what FunnelKit is doing
    if (window.location.href.includes('debug=fkcart')) {
        jQuery(document).ajaxSend(function(event, xhr, settings) {
            if (settings.url.includes('admin-ajax.php')) {
                console.log('[CBM Debug] AJAX Request:', settings.data);
            }
        });
    }
    
    // Function to manually remove loading state from coupon button (for debugging)
    window.cbmFixCouponButton = function() {
        const couponBtn = jQuery('.fkcart-coupon-button');
        const couponInput = jQuery('#fkcart-coupon__input');
        
        if (couponBtn.length) {
            // Remove loading state
            couponBtn.removeClass('fkcart-loading fkcart-disabled');
            console.log('[CBM] Removed loading class from coupon button');
            
            // Remove any existing click handlers that might be blocking
            couponBtn.off('click.cbm');
            
            // Add a monitor to see when clicked
            couponBtn.on('click.cbm', function(e) {
                console.log('[CBM] Coupon button clicked!');
                const couponCode = couponInput.val();
                console.log('[CBM] Coupon code:', couponCode);
                
                // Don't interfere - let the event bubble
                // FunnelKit should handle it
            });
            
            // Try to trigger FunnelKit's coupon application manually if needed
            window.cbmApplyCoupon = function() {
                const code = couponInput.val() || jQuery('#fkcart-coupon__input').val();
                if (!code) {
                    alert('Please enter a coupon code');
                    return;
                }
                
                console.log('[CBM] Manually applying coupon:', code);
                
                // Try to use Vue instance first
                const cartModal = document.querySelector('.fkcart-modal');
                if (cartModal && cartModal.__vue__) {
                    const vueInstance = cartModal.__vue__;
                    console.log('[CBM] Found Vue instance, attempting to apply coupon through Vue...');
                    
                    // Try to find the apply coupon method
                    if (vueInstance.applyCoupon) {
                        console.log('[CBM] Calling vueInstance.applyCoupon()');
                        vueInstance.applyCoupon(code);
                        return;
                    } else if (vueInstance.$children) {
                        // Look in child components
                        for (let child of vueInstance.$children) {
                            if (child.applyCoupon) {
                                console.log('[CBM] Found applyCoupon in child component');
                                child.applyCoupon(code);
                                return;
                            }
                        }
                    }
                    
                    // Try to trigger through Vue events
                    if (vueInstance.$emit) {
                        console.log('[CBM] Emitting apply-coupon event');
                        vueInstance.$emit('apply-coupon', code);
                    }
                }
                
                // Fallback: Set the input value
                jQuery('#fkcart-coupon__input').val(code);
                
                // Remove loading state
                couponBtn.removeClass('fkcart-loading');
                
                // Try to find the actual nonce
                let nonce = '';
                // Look for nonce in various places
                if (jQuery('[name="fkcart_nonce"]').length) {
                    nonce = jQuery('[name="fkcart_nonce"]').val();
                } else if (jQuery('#fkcart-nonce').length) {
                    nonce = jQuery('#fkcart-nonce').val();
                } else if (typeof fkcart_data !== 'undefined' && fkcart_data.nonce) {
                    nonce = fkcart_data.nonce;
                }
                
                console.log('[CBM] Found nonce:', nonce || 'Using default');
                
                // Show loading state
                couponBtn.addClass('fkcart-loading');
                
                // Make AJAX call using WooCommerce standard action
                jQuery.ajax({
                    type: 'POST',
                    url: cbm_ajax.ajax_url,
                    data: {
                        action: 'woocommerce_apply_coupon',
                        coupon_code: code,
                        security: nonce || cbm_ajax.nonce
                    },
                    success: function(response) {
                        console.log('[CBM] Coupon response:', response);
                        couponBtn.removeClass('fkcart-loading');
                        
                        // Refresh cart display
                        if (response.success || response.fragments) {
                            // Update cart fragments
                            if (response.fragments) {
                                jQuery.each(response.fragments, function(key, value) {
                                    jQuery(key).replaceWith(value);
                                });
                            }
                            
                            // Show success message
                            const errorDiv = jQuery('.fkcart-input-error');
                            errorDiv.removeClass('fkcart-hide').attr('data-content', 'Coupon applied successfully').css('color', 'green');
                            
                            // Clear input
                            couponInput.val('');
                            
                            // Reload cart to show updated prices
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            // Show error
                            const errorDiv = jQuery('.fkcart-input-error');
                            const errorMsg = response.data || 'Invalid coupon code';
                            errorDiv.removeClass('fkcart-hide').attr('data-content', errorMsg);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('[CBM] Coupon error:', error);
                        couponBtn.removeClass('fkcart-loading');
                        
                        // Show error message
                        const errorDiv = jQuery('.fkcart-input-error');
                        errorDiv.removeClass('fkcart-hide').attr('data-content', 'Error applying coupon');
                    }
                });
            };
            
            console.log('[CBM] Added cbmApplyCoupon() function - call it to manually apply coupon');
        }
    };
    
    // Auto-fix coupon button on page load if it's stuck
    jQuery(document).ready(function() {
        setTimeout(function() {
            const couponBtn = jQuery('.fkcart-coupon-button.fkcart-loading');
            if (couponBtn.length) {
                console.log('[CBM] Found stuck coupon button, auto-fixing...');
                window.cbmFixCouponButton();
            }
        }, 2000);
    });
    
    // Function to explore Vue instance
    window.cbmExploreVue = function() {
        console.log('[CBM] Searching for Vue instances...');
        
        // Try different selectors where Vue might be mounted
        const selectors = [
            '.fkcart-modal',
            '#fkcart-modal',
            '.fkcart-container',
            '#fkcart-app',
            '.fkcart-app',
            '[id*="fkcart"]',
            '[class*="fkcart"]'
        ];
        
        let vueFound = false;
        let vueInstance = null;
        
        for (let selector of selectors) {
            const elements = document.querySelectorAll(selector);
            for (let el of elements) {
                if (el.__vue__) {
                    console.log(`[CBM] Vue instance found on: ${selector}`, el);
                    vueFound = true;
                    vueInstance = el.__vue__;
                    break;
                }
            }
            if (vueFound) break;
        }
        
        // Also check if Vue is mounted on document.body or #app
        if (!vueFound) {
            if (document.body.__vue__) {
                console.log('[CBM] Vue instance found on document.body');
                vueInstance = document.body.__vue__;
                vueFound = true;
            } else if (document.getElementById('app') && document.getElementById('app').__vue__) {
                console.log('[CBM] Vue instance found on #app');
                vueInstance = document.getElementById('app').__vue__;
                vueFound = true;
            }
        }
        
        // Check window for Vue apps
        if (!vueFound && window.Vue && window.Vue.apps) {
            console.log('[CBM] Vue apps found in window.Vue.apps');
            console.log('[CBM] Number of Vue apps:', window.Vue.apps.length);
        }
        
        if (!vueFound) {
            console.log('[CBM] No Vue instance found. Checking fkcart_app_data...');
            if (window.fkcart_app_data) {
                console.log('[CBM] fkcart_app_data details:', {
                    ajax_url: fkcart_app_data.ajax_url,
                    ajax_nonce: fkcart_app_data.ajax_nonce,
                    keys: Object.keys(fkcart_app_data)
                });
                
                // Try to apply coupon using AJAX directly with fkcart_app_data
                window.cbmApplyCouponDirect = function(code) {
                    console.log('[CBM] Applying coupon directly via AJAX...');
                    jQuery.ajax({
                        type: 'POST',
                        url: fkcart_app_data.ajax_url,
                        data: {
                            action: 'fkcart_apply_coupon',
                            coupon_code: code,
                            security: fkcart_app_data.ajax_nonce,
                            _security: fkcart_app_data.ajax_nonce,
                            nonce: fkcart_app_data.ajax_nonce
                        },
                        success: function(response) {
                            console.log('[CBM] Direct coupon response:', response);
                            if (response.success) {
                                location.reload();
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('[CBM] Direct coupon error:', error);
                        }
                    });
                };
                console.log('[CBM] Created cbmApplyCouponDirect(code) function');
            }
            return;
        }
        
        const vue = vueInstance;
        console.log('[CBM] === Vue Instance Exploration ===');
        console.log('[CBM] Vue root data:', vue.$data);
        console.log('[CBM] Vue root methods:', vue.$options.methods);
        
        // Explore all components
        function exploreComponent(component, path = 'root') {
            if (component.$options.methods) {
                const methods = Object.keys(component.$options.methods);
                if (methods.length > 0) {
                    console.log(`[CBM] Component ${path} methods:`, methods);
                    
                    // Look for coupon-related methods
                    methods.forEach(method => {
                        if (method.toLowerCase().includes('coupon')) {
                            console.log(`[CBM] *** Found coupon method: ${path}.${method}`);
                            window.lastFoundCouponMethod = {component, method};
                        }
                    });
                }
            }
            
            // Check data for coupon-related properties
            if (component.$data) {
                Object.keys(component.$data).forEach(key => {
                    if (key.toLowerCase().includes('coupon')) {
                        console.log(`[CBM] Found coupon data: ${path}.${key} =`, component.$data[key]);
                    }
                });
            }
            
            // Recursively check children
            if (component.$children && component.$children.length > 0) {
                component.$children.forEach((child, index) => {
                    exploreComponent(child, `${path}.$children[${index}]`);
                });
            }
        }
        
        exploreComponent(vue);
        
        if (window.lastFoundCouponMethod) {
            console.log('[CBM] To apply coupon, you can try:');
            console.log('[CBM] window.lastFoundCouponMethod.component.' + window.lastFoundCouponMethod.method + '("YOUR_CODE")');
        }
    };

})(jQuery);