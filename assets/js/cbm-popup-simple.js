/**
 * Course Box Manager - Simple Popup System
 * A clean, simple popup that loads boxes via AJAX
 */

(function($) {
    'use strict';

    // DIAGNOSTIC: Track button visibility
    (function diagnoseButtons() {
        console.log('[CBM DIAGNOSTIC] Starting button diagnostic');

        // Check every 100ms for 3 seconds
        let checkCount = 0;
        const checker = setInterval(function() {
            checkCount++;

            const popupVisible = $('#cbm-popup-overlay').css('display') !== 'none';
            if (!popupVisible) return;

            const boxes = document.querySelectorAll('#cbm-popup-overlay .box, #cbm-popup-content .box');
            boxes.forEach(function(box) {
                const hasNoButton = box.classList.contains('no-button');
                const button = box.querySelector('.add-to-cart-button');

                if (button) {
                    const computed = getComputedStyle(button);
                    const isHidden = computed.display === 'none';

                    if (isHidden || hasNoButton) {
                        console.error('[CBM DIAGNOSTIC] Check #' + checkCount + ' - Button PROBLEM:');
                        console.error('  Box classes:', box.className);
                        console.error('  Has no-button:', hasNoButton);
                        console.error('  Button display:', computed.display);
                        console.error('  Button visibility:', computed.visibility);

                        // FIX IT IMMEDIATELY
                        box.classList.remove('no-button');
                        button.style.cssText = 'display: flex !important; visibility: visible !important; opacity: 1 !important;';
                        console.warn('[CBM DIAGNOSTIC] Applied fix!');
                    }
                } else {
                    console.error('[CBM DIAGNOSTIC] NO BUTTON FOUND in box!', box.className);
                }
            });

            if (checkCount >= 30) { // 3 seconds
                clearInterval(checker);
                console.log('[CBM DIAGNOSTIC] Diagnostic complete');
            }
        }, 100);
    })();

    // Removed ensureButtonsVisible - functionality moved inline to showPopup

    // OVERRIDE: Block any attempt to add no-button class
    const originalAdd = DOMTokenList.prototype.add;
    DOMTokenList.prototype.add = function() {
        for (let i = 0; i < arguments.length; i++) {
            if (arguments[i] === 'no-button') {
                const elem = this.parentElement || this.parentNode;
                if (elem && (elem.closest('#cbm-popup-overlay') || elem.closest('#cbm-popup-content'))) {
                    console.error('[CBM BLOCK] Prevented adding no-button class to popup element!');
                    return this; // Don't add it
                }
            }
        }
        return originalAdd.apply(this, arguments);
    };

    // IMMEDIATE: Run cleanup as soon as script loads, not waiting for DOM ready
    (function immediateCleanup() {
        // Check every 10ms for sold-out selections in the first second
        let checks = 0;
        const maxChecks = 100;
        const immediateChecker = setInterval(function() {
            checks++;

            // Use native DOM for speed
            const soldOutSelected = document.querySelectorAll('.date-btn.selected.sold-out');
            if (soldOutSelected.length > 0) {
                console.warn('[CBM Popup IMMEDIATE] Found and removing sold-out selections:', soldOutSelected.length);
                soldOutSelected.forEach(function(btn) {
                    btn.classList.remove('selected');
                });

                // Select first available
                const containers = document.querySelectorAll('.date-options');
                containers.forEach(function(container) {
                    const available = container.querySelector('.date-btn:not(.sold-out)');
                    if (available && !container.querySelector('.date-btn.selected')) {
                        available.classList.add('selected');
                        console.log('[CBM Popup IMMEDIATE] Selected available date:', available.textContent);
                    }
                });
            }

            if (checks >= maxChecks) {
                clearInterval(immediateChecker);
                console.log('[CBM Popup IMMEDIATE] Stopped immediate checker after', checks, 'checks');
            }
        }, 10); // Check every 10ms
    })();

    // CRITICAL: Override any inline scripts that try to select dates
    // This runs BEFORE document ready to intercept early selections
    (function interceptEarlySelection() {
        // Store original addEventListener to intercept popup events
        const originalAddEventListener = Element.prototype.addEventListener;
        Element.prototype.addEventListener = function(type, listener, options) {
            // Only intercept clicks on date buttons, NOT tabs or other elements
            if (this.classList && this.classList.contains('date-btn') && type === 'click') {
                const wrappedListener = function(event) {
                    if (this.classList.contains('sold-out')) {
                        console.warn('[CBM Popup INTERCEPTOR] Blocked click on sold-out date:', this.textContent);
                        event.preventDefault();
                        event.stopPropagation();
                        return false;
                    }
                    return listener.call(this, event);
                };
                return originalAddEventListener.call(this, type, wrappedListener, options);
            }
            // For all other elements (including tabs), use original listener
            return originalAddEventListener.call(this, type, listener, options);
        };
    })();

    // Override console.log temporarily to catch and block unwanted messages
    const originalConsoleLog = console.log;
    console.log = function(...args) {
        // Block specific messages about default date selection
        const message = args.join(' ');
        if (message.includes('Default selected date in popup') ||
            message.includes('Popup opened, initializing')) {
            console.warn('[CBM Popup BLOCKED] Intercepted message:', message);
            return; // Don't log it
        }
        originalConsoleLog.apply(console, args);
    };

    // Restore original console.log after 2 seconds
    setTimeout(function() {
        console.log = originalConsoleLog;
        console.info('[CBM Popup] Console.log restored');
    }, 2000);

    // CRITICAL: Override Elementor's showPopup immediately
    // This must happen BEFORE DOM ready
    (function() {
        // Store Elementor's showPopup if it exists
        let elementorShowPopup = null;

        // Check periodically for Elementor's showPopup and override it
        const overrideInterval = setInterval(function() {
            if (window.showPopup && window.showPopup !== cbmShowPopup) {
                console.log('[CBM] Found Elementor showPopup, storing it');
                elementorShowPopup = window.showPopup;

                // Override with our wrapper
                window.showPopup = function(courseId) {
                    // If called with no arguments or looking for id="popup"
                    if (!courseId && document.getElementById('popup')) {
                        console.log('[CBM] Calling Elementor showPopup');
                        if (elementorShowPopup) {
                            return elementorShowPopup.call(this);
                        }
                    } else {
                        // It's for our popup
                        console.log('[CBM] Redirecting to cbmShowPopup');
                        return cbmShowPopup(courseId);
                    }
                };

                clearInterval(overrideInterval);
            }
        }, 10);

        // Stop checking after 5 seconds
        setTimeout(function() {
            clearInterval(overrideInterval);
        }, 5000);
    })();

    // Wait for DOM ready
    $(document).ready(function() {
        // Override jQuery removeClass for popup boxes
        const originalRemoveClass = $.fn.removeClass;
        $.fn.removeClass = function() {
            // Check if this is a popup box trying to remove 'selected'
            if (this.closest('#cbm-popup-overlay, #cbm-popup-content').length > 0 &&
                this.hasClass('box') &&
                arguments[0] === 'selected') {
                console.log('[CBM Popup] Blocked jQuery removeClass("selected") on popup box');
                return this; // Return without removing
            }
            return originalRemoveClass.apply(this, arguments);
        };
        console.log('[CBM Popup] Initializing simple popup system');

        // IMMEDIATELY clean any pre-selected sold-out dates
        $('.date-btn.selected.sold-out').removeClass('selected');

        // Override any global functions that might select dates
        if (typeof window.initializeEnrollCourseSelection !== 'undefined') {
            console.warn('[CBM Popup] Overriding initializeEnrollCourseSelection');
            window.initializeEnrollCourseSelection = function() {
                console.log('[CBM Popup] Blocked initializeEnrollCourseSelection');
            };
        }

        // Debug: Check if pre-rendered popup exists
        const prerenderedPopup = $('#cbm-popup-overlay[data-prerendered="true"]');
        if (prerenderedPopup.length > 0) {
            console.log('[CBM Popup] ✅ Pre-rendered popup found in DOM');
            console.log('[CBM Popup] Has tabs:', prerenderedPopup.attr('data-has-tabs'));

            // DEBUG: Set up MutationObserver to track date selection changes
            if (window.MutationObserver) {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                            const target = mutation.target;
                            if (target.classList.contains('date-btn')) {
                                const wasSelected = mutation.oldValue && mutation.oldValue.includes('selected');
                                const isSelected = target.classList.contains('selected');
                                if (!wasSelected && isSelected) {
                                    console.warn('[CBM Popup OBSERVER] Date SELECTED:', target.textContent, 'Stack trace:');
                                    console.trace();
                                }
                            }
                        }
                    });
                });

                // Start observing the popup content
                const popupContent = document.getElementById('cbm-popup-content');
                if (popupContent) {
                    observer.observe(popupContent, {
                        attributes: true,
                        attributeOldValue: true,
                        subtree: true
                    });
                    console.log('[CBM Popup DEBUG] MutationObserver activated to track date selections');
                }
            }
        } else {
            console.log('[CBM Popup] ⚠️ No pre-rendered popup found - will use AJAX fallback');
        }

        // Initialize popup triggers
        initializePopupTriggers();

        // Fix any inline onclick handlers that call showPopup with course IDs
        $('[onclick*="showPopup"]').each(function() {
            const onclick = $(this).attr('onclick');
            // If it's calling showPopup with a number, it's probably for our popup
            if (onclick && onclick.match(/showPopup\(\s*\d+\s*\)/)) {
                console.log('[CBM] Fixing onclick to use CBMPopup.show:', onclick);
                const newOnclick = onclick.replace(/showPopup\(/, 'CBMPopup.show(');
                $(this).attr('onclick', newOnclick);
            }
        });

        // SAFETY NET: Periodically check and clean sold-out selections
        setInterval(function() {
            const soldOutSelected = $('.date-btn.selected.sold-out');
            if (soldOutSelected.length > 0) {
                console.warn('[CBM Popup CLEANUP] Found and removing sold-out selection:', soldOutSelected.text());
                soldOutSelected.removeClass('selected');

                // Auto-select first available date
                const container = soldOutSelected.closest('.date-options');
                const availableDate = container.find('.date-btn:not(.sold-out)').first();
                if (availableDate.length > 0 && !container.find('.date-btn.selected').length) {
                    availableDate.addClass('selected');
                    console.log('[CBM Popup CLEANUP] Auto-selected available date:', availableDate.text());
                }
            }
        }, 500); // Check every 500ms
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
            cbmShowPopup(courseId);
        });
    }
    
    // Simple and direct tab handling for popup
    function bindMinimalEvents() {

        // SIMPLE TAB SWITCHING
        $(document).on('click', '#cbm-popup-content .cbm-tab-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const tabName = $(this).attr('data-tab');

            // Update tab buttons
            $('#cbm-popup-content .cbm-tab-btn').removeClass('active');
            $(this).addClass('active');

            // Update tab panes
            $('#cbm-popup-content .cbm-tab-pane').hide().removeClass('active');
            $('#cbm-popup-content .cbm-tab-pane[data-tab="' + tabName + '"]').show().addClass('active');
        });

        // Ensure buttons remain visible
        $('#cbm-popup-content .box, #cbm-popup-overlay .box').each(function() {
            const $box = $(this);
            $box.removeClass('no-button');
            $box.off('click');
            $box.css('cursor', 'default');

            // Force button visible with jQuery
            const $button = $box.find('.add-to-cart-button');
            if ($button.length > 0) {
                $button.attr('style', 'display: flex !important; visibility: visible !important; opacity: 1 !important;');
            }
        });

        // Date selection (keep this simple too)
        $(document).on('click', '#cbm-popup-content .date-btn:not(.sold-out):not(:disabled)', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $box = $(this).closest('.box');
            $box.find('.date-btn').removeClass('selected');
            $(this).addClass('selected');
        });
        
        // Add to cart
        $('.add-to-cart-button').off('click').on('click', function(e) {
            e.preventDefault();
            const $button = $(this);
            const productId = $button.data('product-id');
            const selectedDate = $button.closest('.box').find('.date-btn.selected').data('date') || '';
            
            if (productId) {
                addToCart($button, productId, selectedDate);
            }
        });
    }
    
    function cbmShowPopup(courseId) {
        // Add class to body to indicate popup is active
        document.body.classList.add('cbm-popup-active');

        // Use native DOM for maximum speed
        const overlay = document.getElementById('cbm-popup-overlay');

        if (overlay && overlay.getAttribute('data-prerendered') === 'true') {
            // CRITICAL: Override classList.add BEFORE showing popup
            // This prevents Elementor from adding no-button class
            const originalAdd = DOMTokenList.prototype.add;
            DOMTokenList.prototype.add = function() {
                // Block no-button class on any element in our popup
                for (let i = 0; i < arguments.length; i++) {
                    if (arguments[i] === 'no-button') {
                        const elem = this.parentElement || this.parentNode;
                        if (elem && (elem.closest('#cbm-popup-overlay') || elem.closest('#cbm-popup-content'))) {
                            console.warn('[CBM] BLOCKED no-button class in popup');
                            return this; // Don't add it
                        }
                    }
                }
                return originalAdd.apply(this, arguments);
            };

            // OVERRIDE selectBox function for popup - make it do NOTHING
            // selectBox is the function from PHP that handles box selection on main page
            // In popup, we don't want ANY selection changes
            window.originalSelectBox = window.selectBox;
            window.selectBox = function(element, boxType, courseId) {
                // Do nothing - boxes can't change state in popup
                return false;
            };

            // Show popup
            overlay.style.display = 'block';

            // IMMEDIATELY remove no-button class from all boxes
            // Do this multiple times to counteract Elementor's attempts
            function removeNoButtonClass() {
                const allBoxes = overlay.querySelectorAll('.box');
                allBoxes.forEach(function(box) {
                    if (box.classList.contains('no-button')) {
                        console.log('[CBM] Removing no-button class from box');
                        box.classList.remove('no-button');
                        // Force button visible
                        const btn = box.querySelector('.add-to-cart-button');
                        if (btn) {
                            btn.style.display = 'flex';
                            btn.style.visibility = 'visible';
                        }
                    }
                });
            }

            // Run immediately
            removeNoButtonClass();

            // Run again after Elementor's timeout (they use setTimeout)
            setTimeout(removeNoButtonClass, 10);
            setTimeout(removeNoButtonClass, 50);
            setTimeout(removeNoButtonClass, 100);
            setTimeout(removeNoButtonClass, 200);
            setTimeout(removeNoButtonClass, 500);

            // ULTRA CRITICAL: Immediate cleanup with native DOM (faster than jQuery)
            const immediateSoldOut = overlay.querySelectorAll('.date-btn.selected.sold-out');
            if (immediateSoldOut.length > 0) {
                console.warn('[CBM Popup SHOW] Immediately removing', immediateSoldOut.length, 'sold-out selections');
                immediateSoldOut.forEach(btn => btn.classList.remove('selected'));
            }

            // CRITICAL: Clean up any pre-selected sold-out dates BEFORE binding events
            cleanupSoldOutSelections();

            // Setup popup boxes - remove click handlers and ensure buttons visible
            const boxes = overlay.querySelectorAll('.box');
            boxes.forEach(function(box) {
                // Remove click handlers - boxes can't be clicked in popup
                box.onclick = null;
                box.removeAttribute('onclick');
                box.style.cursor = 'default';

                // Remove no-button class if present
                box.classList.remove('no-button');

                // Force button visible immediately
                const btn = box.querySelector('.add-to-cart-button');
                if (btn) {
                    btn.style.display = 'flex';
                    btn.style.visibility = 'visible';
                    btn.style.opacity = '1';
                    btn.setAttribute('style', 'display: flex !important; visibility: visible !important; opacity: 1 !important;');
                }
            });

            // ULTRA-AGGRESSIVE: Monitor for ANY changes that hide the button
            const buttonObserver = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    // Check if no-button class was added
                    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                        const target = mutation.target;
                        if (target.classList && target.classList.contains('box') && target.classList.contains('no-button')) {
                            target.classList.remove('no-button');
                            console.warn('[CBM] Removed no-button class that was added to box');
                        }
                    }

                    // Check if button was hidden via style
                    if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                        const target = mutation.target;
                        if (target.classList && target.classList.contains('add-to-cart-button')) {
                            const computed = window.getComputedStyle(target);
                            if (computed.display === 'none' || computed.visibility === 'hidden' || computed.opacity === '0') {
                                target.setAttribute('style', 'display: flex !important; visibility: visible !important; opacity: 1 !important;');
                                console.warn('[CBM] Forced button visible after style change attempted to hide it');
                            }
                        }
                    }
                });
            });

            // Observe all boxes and their children for changes
            const allBoxes = overlay.querySelectorAll('.box');
            allBoxes.forEach(function(box) {
                buttonObserver.observe(box, {
                    attributes: true,
                    attributeFilter: ['class', 'style'],
                    subtree: true
                });
            });

            // Bind events
            bindMinimalEvents();

            // Initialize tabs - Buy tab active by default
            setTimeout(function() {
                // Ensure Buy tab is active
                $('#cbm-popup-content .cbm-tab-btn[data-tab="buy"]').addClass('active');
                $('#cbm-popup-content .cbm-tab-btn[data-tab="enroll"]').removeClass('active');

                // Show Buy pane, hide Enroll pane
                $('#cbm-popup-content .cbm-tab-pane[data-tab="buy"]').show().addClass('active');
                $('#cbm-popup-content .cbm-tab-pane[data-tab="enroll"]').hide().removeClass('active');

                // Ensure all boxes are selected and buttons visible
                $('#cbm-popup-content .box').each(function() {
                    const $box = $(this);
                    $box.addClass('selected');
                    $box.removeClass('no-button'); // Remove no-button class
                    $box.find('.circlecontainer').show();
                    $box.find('.circle-container').hide();

                    // Force button to be visible
                    const $button = $box.find('.add-to-cart-button');
                    if ($button.length > 0) {
                        $button.attr('style', 'display: flex !important; visibility: visible !important; opacity: 1 !important;');
                    }
                });
            }, 100);

            // Mark as bound but allow re-binding on next open
            overlay.setAttribute('data-events-bound', 'true');

            return; // Exit early - no further processing needed
        }
        
        // Fallback: Only if pre-rendering failed
        console.log('[CBM Popup] Pre-rendering not available, using AJAX fallback');
        
        if ($('#cbm-popup-overlay').length === 0) {
            createPopupStructure();
        }
        
        // Show overlay with loading state
        const $overlay = $('#cbm-popup-overlay');
        const $container = $('#cbm-popup-container');
        const $content = $('#cbm-popup-content');
        
        $overlay.fadeIn(100);
        $content.html('<div class="cbm-loading">Loading...</div>');
        
        // Load boxes via AJAX (fallback)
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
        // For pre-rendered popups, just ensure proper state
        console.log('[CBM Popup] Initializing popup state');

        // Ensure all boxes are always selected in popup
        $('#cbm-popup-content .box').each(function() {
            const $box = $(this);
            $box.addClass('selected');
            $box.removeClass('no-button');
            $box.find('.circlecontainer').show();
            $box.find('.circle-container').hide();

            // Remove onclick handlers - boxes can't be deselected in popup
            $box.removeAttr('onclick');
            $box.css('cursor', 'default');
            $box.off('click');
        });

        // Initialize box interactions
        initializeBoxInteractions();
    }
    
    // Removed bindTabEvents - functionality now in bindMinimalEvents for simplicity
    
    function initializeBoxInteractions() {
        console.log('[CBM Popup] Initializing box interactions');

        // FIRST: Clean up any pre-selected sold-out dates
        cleanupSoldOutSelections();

        // Debug: Map all dates and their states
        debugDateStates();

        // Re-initialize date selection
        $('#cbm-popup-content').find('.date-btn:not(.sold-out)').off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation(); // Prevent event from bubbling
            e.stopImmediatePropagation(); // Stop ALL event handlers
            
            const $btn = $(this);
            const $box = $btn.closest('.box');
            
            // Ensure box stays selected
            $box.addClass('selected');
            $box.find('.circlecontainer').show();
            $box.find('.circle-container').hide();
            
            // Handle date selection
            $btn.siblings('.date-btn').removeClass('selected');
            $btn.addClass('selected');
            
            const buttonText = $btn.data('button-text');
            if (buttonText) {
                $box.find('.add-to-cart-button .button-text').text(buttonText);
            }
            
            $box.data('selected-date', $btn.data('date'));
            $box.attr('data-selected-date', $btn.data('date'));
            
            console.log('[CBM Popup] Date selected:', $btn.data('date'));
        });
        
        // Re-initialize add to cart
        $('#cbm-popup-content').find('.add-to-cart-button').off('click').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation(); // Prevent event from bubbling
            e.stopImmediatePropagation(); // Stop ALL event handlers
            
            const $button = $(this);
            const $box = $button.closest('.box');
            
            // Ensure box stays selected
            $box.addClass('selected');
            $box.find('.circlecontainer').show();
            $box.find('.circle-container').hide();
            
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
        
        // Auto-select dates intelligently
        console.log('[CBM Popup DEBUG] ========== AUTO-SELECT DATES START ==========');
        $('#cbm-popup-content').find('.box, .cbm-tab-pane').each(function() {
            const $container = $(this);
            const containerType = $container.hasClass('box') ? 'box' : 'tab-pane';
            const containerClasses = $container.attr('class');

            console.log('[CBM Popup DEBUG] Processing container:', containerType, 'Classes:', containerClasses);

            // Get ALL date buttons
            const $allDates = $container.find('.date-btn');
            const $availableDates = $container.find('.date-btn:not(.sold-out)');
            const $soldOutDates = $container.find('.date-btn.sold-out');
            const $selectedDate = $container.find('.date-btn.selected');

            console.log('[CBM Popup DEBUG] Total dates found:', $allDates.length);
            console.log('[CBM Popup DEBUG] Available dates:', $availableDates.length);
            console.log('[CBM Popup DEBUG] Sold-out dates:', $soldOutDates.length);
            console.log('[CBM Popup DEBUG] Selected dates:', $selectedDate.length);

            // Log each date details
            $allDates.each(function(index) {
                const $btn = $(this);
                const dateText = $btn.data('date') || $btn.text();
                const classes = $btn.attr('class');
                const isSoldOut = $btn.hasClass('sold-out');
                const isSelected = $btn.hasClass('selected');
                const isDisabled = $btn.prop('disabled');

                console.log(`[CBM Popup DEBUG] Date ${index + 1}:`, {
                    text: dateText,
                    classes: classes,
                    soldOut: isSoldOut,
                    selected: isSelected,
                    disabled: isDisabled,
                    element: $btn[0]
                });
            });

            // If there's already a selected date that is NOT sold out, leave it
            if ($selectedDate.length > 0 && !$selectedDate.hasClass('sold-out')) {
                console.log('[CBM Popup DEBUG] ✓ Date already selected and available:', $selectedDate.data('date'));
                return; // Continue to next container
            }

            // If the selected date IS sold out, remove selection
            if ($selectedDate.length > 0 && $selectedDate.hasClass('sold-out')) {
                console.log('[CBM Popup DEBUG] ⚠️ REMOVING sold-out date selection:', $selectedDate.data('date'));
                $selectedDate.removeClass('selected');
            }

            // Auto-select first available date if there are any
            if ($availableDates.length > 0) {
                const firstAvailable = $availableDates.first();
                console.log('[CBM Popup DEBUG] Auto-selecting first available date:', firstAvailable.data('date') || firstAvailable.text());
                firstAvailable.click();
            } else {
                console.log('[CBM Popup DEBUG] ❌ No available dates to select');
            }
        });
        console.log('[CBM Popup DEBUG] ========== AUTO-SELECT DATES END ==========');
    }
    
    function addToCart($button, productId, selectedDate) {
        const originalText = $button.find('.button-text').text();
        
        // Add loading state
        $button.addClass('loading');
        
        // Add spinner if it doesn't exist
        if (!$button.find('.loading-spinner').length) {
            $button.append('<span class="loading-spinner"></span>');
        }
        
        const data = {
            action: 'woocommerce_add_to_cart',
            product_id: productId,
            quantity: 1,
            security: window.cbm_ajax.nonce || '',
            start_date: selectedDate,
            course_date: selectedDate
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
                        $button.removeClass('loading');
                        $button.find('.button-text').text(originalText);
                        $button.find('.loading-spinner').remove();
                        closePopup(); // Close popup after successful add
                    }, 1500);
                } else {
                    alert(response.data || 'Error adding to cart');
                    $button.removeClass('loading');
                    $button.find('.button-text').text(originalText);
                    $button.find('.loading-spinner').remove();
                }
            },
            error: function(xhr, status, error) {
                console.error('[CBM Popup] Cart error:', error);
                alert('Error adding to cart. Please try again.');
                $button.removeClass('loading');
                $button.find('.button-text').text(originalText);
                $button.find('.loading-spinner').remove();
            }
        });
    }
    
    function cleanupSoldOutSelections() {
        console.log('[CBM Popup] Cleaning up sold-out date selections');

        // Find all selected dates that are sold out and remove selection
        const soldOutSelected = document.querySelectorAll('.date-btn.selected.sold-out');
        soldOutSelected.forEach(function(btn) {
            console.log('[CBM Popup] Removing selection from sold-out date:', btn.dataset.date || btn.textContent);
            btn.classList.remove('selected');
        });

        // Auto-select first available (non sold-out) date if none selected
        const containers = document.querySelectorAll('#cbm-popup-content .date-options');
        containers.forEach(function(container) {
            const selectedDates = container.querySelectorAll('.date-btn.selected:not(.sold-out)');
            if (selectedDates.length === 0) {
                // No valid date selected, find first available
                const availableDate = container.querySelector('.date-btn:not(.sold-out)');
                if (availableDate) {
                    console.log('[CBM Popup] Auto-selecting first available date:', availableDate.dataset.date || availableDate.textContent);
                    availableDate.classList.add('selected');
                }
            }
        });
    }

    function closePopup() {
        // Remove popup active class from body
        document.body.classList.remove('cbm-popup-active');

        const overlay = document.getElementById('cbm-popup-overlay');
        if (overlay) {
            overlay.style.display = 'none'; // Direct DOM for instant close
        }
    }

    function debugDateStates() {
        console.log('[CBM Popup DEBUG] ========== DATE STATE DEBUG ==========');

        // Get course ID from popup content
        const $firstBox = $('#cbm-popup-content').find('.box').first();
        const courseId = $firstBox.data('course-id') || $firstBox.attr('data-course-id');

        if (courseId) {
            console.log('[CBM Popup DEBUG] Course ID:', courseId);

            // Fetch seats data via AJAX
            $.ajax({
                url: window.cbm_ajax.ajax_url || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'cbm_debug_date_seats',
                    course_id: courseId,
                    nonce: window.cbm_ajax.nonce || ''
                },
                success: function(response) {
                    if (response.success && response.data) {
                        console.log('[CBM Popup DEBUG] SEATS DATA:', response.data);

                        // Log each date's seat info
                        if (response.data.dates) {
                            response.data.dates.forEach(function(dateInfo, index) {
                                console.log(`[CBM Popup DEBUG] Date ${index + 1}: "${dateInfo.date}"`, {
                                    initial_stock: dateInfo.initial_stock,
                                    sold: dateInfo.sold,
                                    remaining: dateInfo.remaining,
                                    should_be_sold_out: dateInfo.remaining <= 0
                                });
                            });
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[CBM Popup DEBUG] Failed to fetch seats data:', error);
                }
            });
        }

        // Debug current DOM state
        $('#cbm-popup-content').find('.date-btn').each(function(index) {
            const $btn = $(this);
            console.log(`[CBM Popup DEBUG] DOM Date ${index + 1}:`, {
                text: $btn.text(),
                data_date: $btn.data('date'),
                has_sold_out_class: $btn.hasClass('sold-out'),
                has_selected_class: $btn.hasClass('selected'),
                is_disabled: $btn.prop('disabled'),
                parent_classes: $btn.parent().attr('class'),
                html: $btn[0].outerHTML.substring(0, 100) + '...'
            });
        });

        console.log('[CBM Popup DEBUG] ========== END DATE STATE DEBUG ==========');
    }

    // Expose for external use with CBM namespace
    window.CBMPopup = {
        show: cbmShowPopup,
        close: closePopup,
        debugDates: debugDateStates
    };

    // IMPORTANT: Only set global showPopup if it doesn't exist
    // This prevents conflicts with Elementor's showPopup
    if (typeof window.showPopup === 'undefined') {
        window.showPopup = cbmShowPopup;
    }

})(jQuery);