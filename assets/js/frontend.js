/**
 * Course Box Manager - Frontend JavaScript (Clean Version)
 * Handles add to cart via URL redirect, date selection, and box interactions
 * No AJAX interference - pure WooCommerce compatibility
 */

// Define selectBox immediately when script loads
console.log('[CBM] Defining selectBox function...');

// Define closePopup function for custom popups
window.closePopup = function() {
    console.log('[CBM] closePopup called');
    const popup = document.getElementById('popup');
    if (popup) {
        popup.style.display = 'none';
    }
    // Also close cbm popup if exists
    const cbmPopup = document.getElementById('cbm-popup-overlay');
    if (cbmPopup) {
        cbmPopup.style.display = 'none';
    }
};

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
    
    // ALWAYS keep the box selected - never allow deselection
    console.log('[CBM] Forcing box to stay selected');
    
    // Check if there are siblings
    const $siblings = $box.siblings('.box');
    
    if ($siblings.length > 0) {
        // Multiple boxes - allow switching
        $siblings.removeClass('selected');
        $siblings.find('.circlecontainer').hide();
        $siblings.find('.circle-container').show();
    }
    
    // Always select this box
    $box.addClass('selected');
    $box.find('.circlecontainer').show();
    $box.find('.circle-container').hide();
    
    // Prevent any deselection
    return false;
};

// jQuery-dependent code
(function($) {
    'use strict';

    console.log('[CBM] Frontend jQuery code initializing...');

    // Date selection handler - only for OUR date buttons
    $('body').on('click', '.box .date-btn:not(.sold-out)', function(e) {
        e.preventDefault();
        
        console.log('[CBM] Date button click event triggered!');
        
        const $btn = $(this);
        let $container = $btn.closest('.box');
        
        // Get the date value
        let dateValue = $btn.data('date') || $btn.attr('data-date') || $btn.text().trim();
        
        console.log('[CBM] Date button clicked:', dateValue);
        
        // Remove selected class from all date buttons in this box
        $container.find('.date-btn').removeClass('selected');
        
        // Add selected class to clicked button
        $btn.addClass('selected');
        
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

    // Add to cart using iframe (no page reload, no AJAX interference)
    $(document).on('click', '.box .add-to-cart-button:not(.sold-out)', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        let $box = $button.closest('.box');
        
        const productId = $button.data('product-id');
        const quantity = $button.data('quantity') || 1;
        
        console.log('[CBM] Add to cart clicked');
        console.log('[CBM] Product ID:', productId);
        
        // Try to get the selected date
        let selectedDate = '';
        
        // Method 1: Find selected date button within this specific box
        const $selectedDateBtn = $box.find('.date-btn.selected');
        if ($selectedDateBtn.length > 0) {
            selectedDate = $selectedDateBtn.data('date') || 
                          $selectedDateBtn.attr('data-date') || 
                          $selectedDateBtn.text().trim();
            console.log('[CBM] Selected date from button:', selectedDate);
        }
        
        // Method 2: Check data attribute on box
        if (!selectedDate) {
            selectedDate = $box.data('selected-date') || $box.attr('data-selected-date');
            console.log('[CBM] Selected date from box data:', selectedDate);
        }
        
        // Method 3: Auto-select if only one date available
        if (!selectedDate) {
            const $allDates = $box.find('.date-btn:not(.sold-out)');
            if ($allDates.length === 1) {
                selectedDate = $allDates.first().data('date') || 
                              $allDates.first().attr('data-date') || 
                              $allDates.first().text().trim();
                $allDates.first().addClass('selected');
                console.log('[CBM] Single date auto-selected:', selectedDate);
            }
        }
        
        console.log('[CBM] Final selected date:', selectedDate);
        
        // Check if date is required
        const $availableDateButtons = $box.find('.date-btn:not(.sold-out)');
        
        if (!productId) {
            console.error('[CBM] No product ID found');
            return;
        }
        
        // Only require date selection if there are available date buttons
        if ($availableDateButtons.length > 0 && !selectedDate) {
            alert('Please select a date');
            return;
        }
        
        // Show loading feedback
        $button.addClass('loading');
        const originalText = $button.find('.button-text').text();
        $button.find('.button-text').text('Adding to cart...');
        
        // Show the loader spinner
        $button.find('.loader').show();
        
        // Build the add to cart URL with parameters
        const siteUrl = window.location.origin;
        let addToCartUrl = `${siteUrl}/?add-to-cart=${productId}&quantity=${quantity}`;
        
        // Add course date as URL parameter if available
        if (selectedDate) {
            sessionStorage.setItem('cbm_course_date_' + productId, selectedDate);
            addToCartUrl += `&course_date=${encodeURIComponent(selectedDate)}`;
        }
        
        console.log('[CBM] Adding to cart via iframe:', addToCartUrl);
        
        // Create invisible iframe to add to cart without page reload
        const iframeId = 'cbm-cart-iframe-' + Date.now();
        const $iframe = $('<iframe>', {
            id: iframeId,
            src: addToCartUrl,
            style: 'display:none;position:absolute;width:1px;height:1px;',
            'aria-hidden': 'true'
        });
        
        // Append iframe to body
        $('body').append($iframe);
        
        // Wait for cart to be added then trigger cart update
        setTimeout(function() {
            // Remove iframe
            $('#' + iframeId).remove();
            
            // Update button state
            $button.removeClass('loading');
            $button.find('.loader').hide();
            $button.find('.button-text').text('Added to cart!');
            
            // Force refresh cart fragments to get updated cart contents
            console.log('[CBM] Refreshing cart fragments...');
            
            // Build the AJAX URL properly
            let ajaxUrl = '/wc-ajax=get_refreshed_fragments';
            if (typeof wc_add_to_cart_params !== 'undefined' && wc_add_to_cart_params.wc_ajax_url) {
                ajaxUrl = wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'get_refreshed_fragments');
            } else if (window.cbm_ajax && window.cbm_ajax.ajax_url) {
                ajaxUrl = window.cbm_ajax.ajax_url + '?wc-ajax=get_refreshed_fragments';
            }
            
            console.log('[CBM] Using AJAX URL:', ajaxUrl);
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                success: function(data) {
                    console.log('[CBM] Cart fragments response:', data);
                    if (data && data.fragments) {
                        $.each(data.fragments, function(key, value) {
                            $(key).replaceWith(value);
                        });
                        
                        // Update cart hash and fragments in session storage
                        if (data.cart_hash) {
                            const fragmentsKey = (typeof wc_cart_fragments_params !== 'undefined' && wc_cart_fragments_params.fragment_name) 
                                ? wc_cart_fragments_params.fragment_name 
                                : 'wc_fragments_' + data.cart_hash;
                            
                            sessionStorage.setItem(fragmentsKey, JSON.stringify(data.fragments));
                            sessionStorage.setItem('wc_cart_hash', data.cart_hash);
                            console.log('[CBM] Updated session storage with cart hash:', data.cart_hash);
                        }
                    }
                    
                    // Trigger cart updated events
                    $(document.body).trigger('wc_fragments_refreshed');
                    $(document.body).trigger('added_to_cart', [data.fragments, data.cart_hash]);
                    
                    // Try to open cart if function exists
                    if (typeof window.open_cart === 'function') {
                        console.log('[CBM] Opening cart after refresh');
                        window.open_cart();
                    }
                }
            });
            
            // Reset button after 2 seconds
            setTimeout(function() {
                $button.find('.button-text').text(originalText);
            }, 2000);
            
        }, 1500); // Wait 1.5 seconds for cart to process
    });

    // Initialize on page load
    $(document).ready(function() {
        console.log('Course Box Manager frontend loaded (Clean Version)');
        
        // Initialize the Enroll-Buy combo box selection
        initEnrollBuySelection();
        
        // Handle custom popup if it exists
        if ($('#popup').length > 0) {
            console.log('[CBM] Custom popup detected, applying styles');
            
            const $popup = $('#popup');
            const $popupBoxes = $popup.find('.box');
            
            // Check if popup has tabs
            const hasTabs = $popup.find('.cbm-tabs').length > 0;
            
            if (hasTabs || $popupBoxes.length > 1) {
                // Multiple boxes or tabs - add background
                $popup.addClass('has-tabs');
                $popup.css({
                    'background': '#0E0D0F',
                    'border-radius': '10px',
                    'padding': '40px'
                });
            } else {
                // Single box - transparent background
                $popup.css({
                    'background': 'transparent',
                    'border-radius': '10px',
                    'padding': '40px'
                });
            }
            
            // Ensure boxes are always selected
            $popupBoxes.each(function() {
                const $box = $(this);
                $box.addClass('selected');
                $box.find('.circlecontainer').show();
                $box.find('.circle-container').hide();
                
                // Override onclick to force selection
                $box.attr('onclick', 'selectBox(this); return false;');
            });
            
            // Fix tab functionality if tabs exist
            if (hasTabs) {
                console.log('[CBM] Fixing tab functionality');
                
                // Bind tab click events
                $(document).off('click.popuptabs').on('click.popuptabs', '#popup .cbm-tab-btn', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const $btn = $(this);
                    const tabId = $btn.data('tab') || $btn.attr('data-tab');
                    
                    console.log('[CBM] Tab clicked:', tabId);
                    
                    // Update button states
                    $('#popup .cbm-tab-btn').removeClass('active');
                    $btn.addClass('active');
                    
                    // Update pane visibility
                    $('#popup .cbm-tab-pane').removeClass('active').hide();
                    $('#popup .cbm-tab-pane[data-tab="' + tabId + '"]').addClass('active').show();
                });
            }
        }
        
        // Check for single boxes and ensure they're selected
        $('.box-container, .course-boxes-container, .selectable-box-container').each(function() {
            const $container = $(this);
            const $boxes = $container.find('.box');
            
            if ($boxes.length === 1) {
                console.log('[CBM] Found single box, ensuring it stays selected');
                const $singleBox = $boxes.first();
                $singleBox.addClass('selected');
                $singleBox.find('.circlecontainer').show();
                $singleBox.find('.circle-container').hide();
                
                // Prevent clicking from deselecting
                $singleBox.off('click.singlebox').on('click.singlebox', function(e) {
                    // Only prevent deselection if not clicking on buttons or dates
                    if (!$(e.target).closest('.add-to-cart-button, .date-btn').length) {
                        e.stopPropagation();
                        $(this).addClass('selected');
                        $(this).find('.circlecontainer').show();
                        $(this).find('.circle-container').hide();
                    }
                });
            }
        });
        
        // Re-bind date button handlers after a short delay
        setTimeout(function() {
            console.log('[CBM] Binding date button handlers');
            
            // Direct binding to existing date buttons
            $('.box .date-btn:not(.sold-out)').off('click.cbm').on('click.cbm', function(e) {
                e.preventDefault();
                
                console.log('[CBM] Date button clicked!');
                
                const $btn = $(this);
                const $container = $btn.closest('.box');
                const dateValue = $btn.data('date') || $btn.attr('data-date') || $btn.text().trim();
                
                // Remove selected from all dates in this box
                $container.find('.date-btn').removeClass('selected');
                
                // Add selected to clicked button
                $btn.addClass('selected');
                
                // Store the date
                $container.data('selected-date', dateValue);
                $container.attr('data-selected-date', dateValue);
                
                console.log('[CBM] Date selection complete:', dateValue);
            });
        }, 500);
    });
    
    // Handle Enroll-Buy combo box selection
    function initEnrollBuySelection() {
        console.log('[CBM] Initializing Enroll-Buy selection');
        
        // Find all enroll-buy combo containers
        $('.enroll-buy-combo').each(function() {
            const $container = $(this);
            const $boxes = $container.find('.box');
            
            // If there's only one box, keep it selected always
            if ($boxes.length === 1) {
                console.log('[CBM] Single box in enroll-buy combo, keeping selected');
                const $singleBox = $boxes.first();
                $singleBox.addClass('selected');
                $singleBox.find('.circlecontainer').show();
                $singleBox.find('.circle-container').hide();
                return; // Skip adding click handlers for single box
            }
            
            // Add click handler to each box (only for multiple boxes)
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
                
                console.log('[CBM] Box selected:', $clickedBox.attr('class'));
            });
            
            // Set initial state: Buy box selected by default
            const $buyBox = $container.find('.box.buy-course');
            const $enrollBox = $container.find('.box.enroll-course');
            
            if ($buyBox.length) {
                $buyBox.addClass('selected');
                $buyBox.find('.circlecontainer').show();
                $buyBox.find('.circle-container').hide();
            }
            
            if ($enrollBox.length) {
                $enrollBox.removeClass('selected');
                $enrollBox.find('.circlecontainer').hide();
                $enrollBox.find('.circle-container').show();
            }
            
            console.log('[CBM] Buy box selected by default');
        });
    }

})(jQuery);