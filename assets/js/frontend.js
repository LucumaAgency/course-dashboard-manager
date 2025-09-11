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
    $(document).on('click', '.date-btn:not(.sold-out)', function() {
        const $btn = $(this);
        const $container = $btn.closest('.box');
        
        // Get the date value - try multiple sources
        let dateValue = $btn.data('date') || $btn.attr('data-date') || $btn.text().trim();
        
        console.log('[CBM] Date button clicked:', dateValue);
        console.log('[CBM] Date button element:', $btn[0]);
        
        // Remove selected class from siblings (other date buttons)
        $btn.siblings('.date-btn').removeClass('selected');
        $btn.addClass('selected');
        
        console.log('[CBM] Date button selected state:', $btn.hasClass('selected'));
        
        // Update button text if data attribute exists
        const buttonText = $btn.data('button-text');
        if (buttonText) {
            $container.find('.add-to-cart-button .button-text').text(buttonText);
        }
        
        // Store selected date - ensure we're storing the value
        $container.data('selected-date', dateValue);
        $container.attr('data-selected-date', dateValue); // Also set as attribute for debugging
        
        console.log('[CBM] Date stored on container:', $container.data('selected-date'));
        console.log('[CBM] Container element:', $container[0]);
        
        // Store STM Course ID if available (for enroll courses)
        const stmCourseId = $btn.data('stm-course-id');
        if (stmCourseId) {
            $container.data('stm-course-id', stmCourseId);
            // Update the add to cart button with the STM Course product ID
            $container.find('.add-to-cart-button').attr('data-product-id', stmCourseId);
            console.log('[CBM] Updated product ID to STM Course:', stmCourseId);
        }
    });

    // Add to cart handler
    $(document).on('click', '.add-to-cart-button:not(.sold-out):not(.loading)', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $box = $button.closest('.box');
        const productId = $button.data('product-id');
        const quantity = $button.data('quantity') || 1;
        
        // Try multiple ways to get the selected date
        let selectedDate = '';
        
        // Method 1: Check data attribute on box
        selectedDate = $box.data('selected-date');
        console.log('[CBM] Method 1 - Box data selected-date:', selectedDate);
        
        // Method 2: Find selected date button
        if (!selectedDate) {
            const $selectedDateBtn = $box.find('.date-btn.selected');
            if ($selectedDateBtn.length > 0) {
                selectedDate = $selectedDateBtn.data('date') || $selectedDateBtn.text().trim();
                console.log('[CBM] Method 2 - Selected date button:', selectedDate);
            }
        }
        
        // Method 3: Check if there's only one date and auto-select it
        if (!selectedDate) {
            const $allDates = $box.find('.date-btn:not(.sold-out)');
            if ($allDates.length === 1) {
                selectedDate = $allDates.first().data('date') || $allDates.first().text().trim();
                console.log('[CBM] Method 3 - Single date auto-selected:', selectedDate);
            }
        }
        
        console.log('[CBM] Final selected date:', selectedDate);
        console.log('[CBM] Date options found:', $box.find('.date-options').length);
        
        if (!productId) {
            console.error('No product ID found');
            return;
        }
        
        // Check if date selection is required (only if date options exist and no date selected)
        if ($box.find('.date-options').length > 0 && !selectedDate) {
            console.error('[CBM] Date validation failed - date options exist but no date selected');
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
        
        // Initialize Enroll-Buy combo box selection
        initEnrollBuySelection();
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
                
                console.log('[CBM] Box selected:', $clickedBox.closest('.buy-box-wrapper, .enroll-box-wrapper').attr('class'));
            });
            
            // Set initial state: Buy box selected by default
            const $buyBox = $container.find('.buy-box-wrapper .box, [data-tab="buy"] .box').first();
            if ($buyBox.length) {
                $buyBox.addClass('selected');
                $buyBox.find('.circlecontainer').show();
                $buyBox.find('.circle-container').hide();
            }
        });
    }

})(jQuery);