/**
 * Course Box Manager - Simple Popup System
 * A clean, simple popup that loads boxes via AJAX
 */

(function($) {
    'use strict';
    
    // Wait for DOM ready
    $(document).ready(function() {
        console.log('[CBM Popup] Initializing simple popup system');
        
        // Initialize popup triggers
        initializePopupTriggers();
    });
    
    function initializePopupTriggers() {
        // Find all popup triggers
        $(document).on('click', '.cbm-popup-trigger', function(e) {
            e.preventDefault();
            console.log('[CBM Popup] Trigger clicked');
            
            // Get course ID from data attribute or detect from context
            let courseId = $(this).data('course-id');
            
            if (!courseId) {
                // Try to get from body class
                const bodyClass = $('body').attr('class');
                const match = bodyClass ? bodyClass.match(/postid-(\d+)/) : null;
                if (match) {
                    courseId = match[1];
                }
            }
            
            console.log('[CBM Popup] Course ID:', courseId);
            
            // Show popup with course boxes
            showPopup(courseId);
        });
    }
    
    function showPopup(courseId) {
        // Create overlay if it doesn't exist
        if ($('#cbm-popup-overlay').length === 0) {
            createPopupStructure();
        }
        
        // Show overlay with loading state
        const $overlay = $('#cbm-popup-overlay');
        const $container = $('#cbm-popup-container');
        const $content = $('#cbm-popup-content');
        
        $overlay.fadeIn(300);
        $content.html('<div class="cbm-loading">Loading...</div>');
        
        // Load boxes via AJAX
        loadBoxes(courseId, function(html) {
            $content.html(html);
            
            // Re-initialize any JavaScript for the boxes
            initializeBoxScripts();
        });
    }
    
    function createPopupStructure() {
        const html = `
            <div id="cbm-popup-overlay" style="display:none;">
                <div id="cbm-popup-container">
                    <button id="cbm-popup-close">&times;</button>
                    <div id="cbm-popup-content"></div>
                </div>
            </div>
        `;
        
        $('body').append(html);
        
        // Add styles
        addPopupStyles();
        
        // Bind close events
        $('#cbm-popup-close, #cbm-popup-overlay').on('click', function(e) {
            if (e.target.id === 'cbm-popup-overlay' || e.target.id === 'cbm-popup-close') {
                closePopup();
            }
        });
        
        // Close on ESC key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                closePopup();
            }
        });
    }
    
    function addPopupStyles() {
        if ($('#cbm-popup-styles').length === 0) {
            const styles = `
                <style id="cbm-popup-styles">
                    #cbm-popup-overlay {
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0, 0, 0, 0.7);
                        z-index: 99998;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    
                    #cbm-popup-container {
                        position: relative;
                        background: white;
                        border-radius: 10px;
                        width: 90%;
                        max-width: 1200px;
                        max-height: 90vh;
                        overflow-y: auto;
                        padding: 40px;
                        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                    }
                    
                    #cbm-popup-close {
                        position: absolute;
                        top: 15px;
                        right: 15px;
                        background: none;
                        border: none;
                        font-size: 30px;
                        cursor: pointer;
                        color: #666;
                        z-index: 100;
                        line-height: 1;
                        padding: 0;
                        width: 30px;
                        height: 30px;
                    }
                    
                    #cbm-popup-close:hover {
                        color: #000;
                    }
                    
                    #cbm-popup-content {
                        position: relative;
                    }
                    
                    .cbm-loading {
                        text-align: center;
                        padding: 40px;
                        color: #666;
                        font-size: 18px;
                    }
                    
                    /* Ensure boxes display properly in popup */
                    #cbm-popup-content .course-boxes-container {
                        display: flex;
                        gap: 20px;
                        flex-wrap: wrap;
                    }
                    
                    #cbm-popup-content .box {
                        flex: 1;
                        min-width: 350px;
                    }
                    
                    /* Tabs styles */
                    .cbm-tabs {
                        width: 100%;
                    }
                    
                    .cbm-tabs-header {
                        display: flex;
                        border-bottom: 2px solid #e0e0e0;
                        margin-bottom: 20px;
                    }
                    
                    .cbm-tab-btn {
                        flex: 1;
                        padding: 12px 20px;
                        background: transparent;
                        border: none;
                        font-size: 16px;
                        font-weight: 500;
                        color: #666;
                        cursor: pointer;
                        transition: all 0.3s ease;
                        position: relative;
                        border-bottom: 3px solid transparent;
                    }
                    
                    .cbm-tab-btn:hover {
                        color: #333;
                        background: #f5f5f5;
                    }
                    
                    .cbm-tab-btn.active {
                        color: #0073aa;
                        font-weight: 600;
                        border-bottom-color: #0073aa;
                        background: #f9f9f9;
                    }
                    
                    .cbm-tabs-content {
                        padding: 10px 0;
                    }
                    
                    .cbm-tab-pane {
                        display: none;
                        animation: fadeIn 0.3s ease;
                    }
                    
                    .cbm-tab-pane.active {
                        display: block;
                    }
                    
                    @keyframes fadeIn {
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                    
                    /* Mobile styles */
                    @media (max-width: 768px) {
                        #cbm-popup-container {
                            width: 95%;
                            padding: 20px;
                            margin: 10px;
                        }
                        
                        #cbm-popup-content .course-boxes-container {
                            flex-direction: column;
                        }
                        
                        #cbm-popup-content .box {
                            width: 100%;
                            min-width: unset;
                        }
                        
                        .cbm-tab-btn {
                            font-size: 14px;
                            padding: 10px 15px;
                        }
                    }
                </style>
            `;
            
            $('head').append(styles);
        }
    }
    
    function loadBoxes(courseId, callback) {
        console.log('[CBM Popup] Loading boxes for course:', courseId);
        
        // Ensure we have AJAX configuration
        if (typeof window.cbm_ajax === 'undefined') {
            console.error('[CBM Popup] cbm_ajax not defined, using defaults');
            window.cbm_ajax = {
                ajax_url: '/wp-admin/admin-ajax.php',
                nonce: ''
            };
        }
        
        $.ajax({
            url: window.cbm_ajax.ajax_url || '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'cbm_get_popup_boxes',  // Correct action name
                course_id: courseId,
                nonce: window.cbm_ajax.nonce || ''
            },
            success: function(response) {
                console.log('[CBM Popup] Response received:', response);
                
                if (response.success && response.data) {
                    const html = response.data.html || response.data;
                    console.log('[CBM Popup] HTML content length:', html.length);
                    callback(html);
                } else if (response && typeof response === 'string') {
                    // Direct HTML response
                    console.log('[CBM Popup] Direct HTML response');
                    callback(response);
                } else {
                    callback('<div class="error">Error loading boxes. Please try again.</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('[CBM Popup] AJAX error:', error);
                console.error('[CBM Popup] Response text:', xhr.responseText);
                callback('<div class="error">Error loading boxes. Please try again.</div>');
            }
        });
    }
    
    function createTabs($boxes) {
        console.log('[CBM Popup] Creating tabs for', $boxes.length, 'boxes');
        
        // Detect box types from classes and content
        const boxTypes = [];
        $boxes.each(function() {
            const $box = $(this);
            const classes = $box.attr('class') || '';
            const content = $box.html() || '';
            
            console.log('[CBM Popup] Box classes:', classes);
            
            // Check by class names
            if (classes.includes('enroll-course') || classes.includes('enroll_course')) {
                boxTypes.push('Enroll in Live Course');
            } else if (classes.includes('buy-course') || classes.includes('buy_course')) {
                boxTypes.push('Buy Course');
            } else if (classes.includes('waitlist')) {
                boxTypes.push('Join Waitlist');
            } else if (classes.includes('soldout') || classes.includes('sold-out')) {
                boxTypes.push('Sold Out');
            } 
            // Check by content
            else if (content.includes('Enroll') || content.includes('enroll')) {
                boxTypes.push('Enroll in Course');
            } else if (content.includes('Buy') || content.includes('buy')) {
                boxTypes.push('Buy Course');
            } else {
                // Default naming
                boxTypes.push('Option ' + (boxTypes.length + 1));
            }
        });
        
        console.log('[CBM Popup] Box types detected:', boxTypes);
        
        // Create tab structure
        let tabsHtml = '<div class="cbm-tabs">';
        tabsHtml += '<div class="cbm-tabs-header">';
        
        // Create tab buttons
        boxTypes.forEach((type, index) => {
            const activeClass = index === 0 ? 'active' : '';
            tabsHtml += `<button class="cbm-tab-btn ${activeClass}" data-tab="${index}" type="button">${type}</button>`;
        });
        
        tabsHtml += '</div>'; // Close tabs-header
        tabsHtml += '<div class="cbm-tabs-content">';
        
        // Wrap each box in a tab pane
        $boxes.each(function(index) {
            const activeClass = index === 0 ? 'active' : '';
            const $box = $(this);
            
            tabsHtml += `<div class="cbm-tab-pane ${activeClass}" data-tab="${index}">`;
            tabsHtml += $box.prop('outerHTML');
            tabsHtml += '</div>';
        });
        
        tabsHtml += '</div>'; // Close tabs-content
        tabsHtml += '</div>'; // Close tabs
        
        // Replace content with tabbed version
        $('#cbm-popup-content').html(tabsHtml);
        
        console.log('[CBM Popup] Tabs HTML created and inserted');
    }
    
    function initializeBoxScripts() {
        console.log('[CBM Popup] Initializing box scripts');
        
        // Check if we have raw HTML with multiple boxes
        const $content = $('#cbm-popup-content');
        const htmlContent = $content.html();
        
        // Look for multiple boxes in the content
        const $boxes = $content.find('.box');
        console.log('[CBM Popup] Found boxes:', $boxes.length);
        
        // If we have multiple boxes, create tabs
        if ($boxes.length > 1) {
            console.log('[CBM Popup] Multiple boxes detected, creating tabs');
            createTabs($boxes);
            
            // After creating tabs, re-bind tab switching events
            setTimeout(function() {
                bindTabEvents();
                
                // Auto-select the first box
                const $firstPane = $('#cbm-popup-content').find('.cbm-tab-pane.active');
                const $firstBox = $firstPane.find('.box');
                if ($firstBox.length > 0) {
                    $firstBox.addClass('selected');
                    $firstBox.removeClass('no-button');
                    $firstBox.find('.circlecontainer').show();
                    $firstBox.find('.circle-container').hide();
                    console.log('[CBM Popup] Auto-selected first box');
                }
            }, 100);
        } else if ($boxes.length === 1) {
            console.log('[CBM Popup] Single box detected, no tabs needed');
            // Auto-select single box
            $boxes.addClass('selected');
            $boxes.removeClass('no-button');
            $boxes.find('.circlecontainer').show();
            $boxes.find('.circle-container').hide();
        } else {
            console.log('[CBM Popup] No boxes found in content');
        }
        
        // Initialize other box scripts
        initializeBoxInteractions();
    }
    
    function bindTabEvents() {
        console.log('[CBM Popup] Binding tab events');
        $('#cbm-popup-content').find('.cbm-tab-btn').off('click').on('click', function() {
            const $btn = $(this);
            const tabIndex = $btn.data('tab');
            
            console.log('[CBM Popup] Tab clicked:', tabIndex);
            
            // Update active states
            $btn.siblings().removeClass('active');
            $btn.addClass('active');
            
            // Show corresponding content
            $('#cbm-popup-content').find('.cbm-tab-pane').removeClass('active');
            const $activePane = $('#cbm-popup-content').find('.cbm-tab-pane[data-tab="' + tabIndex + '"]');
            $activePane.addClass('active');
            
            // Auto-select the box in the active tab
            const $box = $activePane.find('.box');
            if ($box.length > 0) {
                // Add selected class to show the box as selected
                $box.addClass('selected');
                
                // Ensure the button is visible
                $box.removeClass('no-button');
                
                // Update radio button indicators
                $box.find('.circlecontainer').show();
                $box.find('.circle-container').hide();
                
                console.log('[CBM Popup] Auto-selected box in tab:', tabIndex);
            }
            
            console.log('[CBM Popup] Switched to tab:', tabIndex);
        });
    }
    
    function initializeBoxInteractions() {
        console.log('[CBM Popup] Initializing box interactions');
        
        // Re-initialize date selection
        $('#cbm-popup-content').find('.date-btn:not(.sold-out)').off('click').on('click', function() {
            const $btn = $(this);
            const $container = $btn.closest('.box, .cbm-tab-pane');
            
            $btn.siblings().removeClass('selected');
            $btn.addClass('selected');
            
            const buttonText = $btn.data('button-text');
            if (buttonText) {
                $container.find('.add-to-cart-button .button-text').text(buttonText);
            }
            
            $container.data('selected-date', $btn.data('date'));
            console.log('[CBM Popup] Date selected:', $btn.data('date'));
        });
        
        // Re-initialize add to cart
        $('#cbm-popup-content').find('.add-to-cart-button').off('click').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const $box = $button.closest('.box, .cbm-tab-pane');
            const productId = $button.data('product-id');
            const selectedDate = $box.data('selected-date') || $box.find('.date-btn.selected').data('date') || '';
            
            console.log('[CBM Popup] Add to cart clicked - Product ID:', productId, 'Date:', selectedDate);
            
            if (!productId) {
                console.error('[CBM Popup] No product ID');
                return;
            }
            
            if ($box.find('.date-options').length > 0 && !selectedDate) {
                alert('Please select a date');
                return;
            }
            
            // Add to cart
            addToCart($button, productId, selectedDate);
        });
        
        // Auto-select first date if only one
        $('#cbm-popup-content').find('.box, .cbm-tab-pane').each(function() {
            const $dates = $(this).find('.date-btn:not(.sold-out)');
            if ($dates.length === 1) {
                $dates.first().click();
            }
        });
    }
    
    function addToCart($button, productId, selectedDate) {
        const originalText = $button.find('.button-text').text();
        $button.addClass('loading').find('.button-text').text('Adding...');
        
        const data = {
            action: 'woocommerce_add_to_cart',
            product_id: productId,
            quantity: 1,
            security: window.cbm_ajax.nonce || '',
            start_date: selectedDate
        };
        
        console.log('[CBM Popup] Adding to cart:', data);
        
        $.ajax({
            url: window.cbm_ajax.ajax_url || '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                console.log('[CBM Popup] Cart response:', response);
                
                if (response.success) {
                    $button.find('.button-text').text('Added!');
                    
                    // Trigger FunnelKit Cart if available
                    if (window.cbm_ajax.is_funnelkit_active || response.use_funnelkit) {
                        if (typeof fkcart_show_cart === 'function') {
                            fkcart_show_cart();
                        } else if (typeof FKCart !== 'undefined' && FKCart.show_cart) {
                            FKCart.show_cart();
                        } else {
                            $(document.body).trigger('fkcart_show_cart');
                        }
                    }
                    
                    // Trigger WooCommerce events
                    $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash]);
                    
                    setTimeout(function() {
                        $button.removeClass('loading').find('.button-text').text(originalText);
                        closePopup(); // Close popup after successful add
                    }, 1500);
                } else {
                    alert(response.data || 'Error adding to cart');
                    $button.removeClass('loading').find('.button-text').text(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('[CBM Popup] Cart error:', error);
                alert('Error adding to cart. Please try again.');
                $button.removeClass('loading').find('.button-text').text(originalText);
            }
        });
    }
    
    function closePopup() {
        $('#cbm-popup-overlay').fadeOut(300, function() {
            $('#cbm-popup-content').empty();
        });
    }
    
    // Expose for external use
    window.CBMPopup = {
        show: showPopup,
        close: closePopup
    };
    
})(jQuery);