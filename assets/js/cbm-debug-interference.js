/**
 * Course Box Manager - Interference Debugger
 * Detects which plugins/themes are modifying our elements
 */

(function() {
    'use strict';

    console.log('%c[CBM DEBUG] Interference detector activated', 'background: #ff0000; color: #fff; padding: 5px;');

    // Store original methods - use bind to preserve context
    const originalAddClass = DOMTokenList.prototype.add;
    const originalRemoveClass = DOMTokenList.prototype.remove;
    const originalSetAttribute = Element.prototype.setAttribute;
    const originalStyle = Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'style');

    // Track stack traces for no-button additions
    const interferenceLog = [];

    // Override classList.add to track who adds no-button
    DOMTokenList.prototype.add = function() {
        // Check if any argument is 'no-button'
        for (let i = 0; i < arguments.length; i++) {
            if (arguments[i] === 'no-button') {
                const stack = new Error().stack;
                const caller = extractCaller(stack);

                // Get the element that owns this classList
                const element = this.parentElement || this.parentNode;

                console.warn('%c[CBM INTERFERENCE DETECTED]', 'background: #ff6600; color: #fff; padding: 3px;');
                console.warn('Element:', element);
                console.warn('Attempted to add class:', arguments[i]);
                console.warn('Called from:', caller);
                console.warn('Full stack:', stack);

                interferenceLog.push({
                    time: new Date().toISOString(),
                    element: element ? element.className : 'unknown',
                    action: 'addClass',
                    value: 'no-button',
                    caller: caller,
                    stack: stack
                });

                // If it's in our popup, prevent it
                if (element && element.closest && (element.closest('#cbm-popup-overlay') || element.closest('#cbm-popup-content'))) {
                    console.error('%c[CBM BLOCKED]', 'background: #ff0000; color: #fff;', 'Prevented no-button addition in popup');
                    return this; // Don't add it, return this for chaining
                }
            }
        }
        return originalAddClass.apply(this, arguments);
    };

    // Override style property to track hiding attempts
    Object.defineProperty(HTMLElement.prototype, 'style', {
        get: function() {
            return originalStyle.get.call(this);
        },
        set: function(value) {
            if (this.classList && this.classList.contains('add-to-cart-button')) {
                if (value && (value.includes('display: none') || value.includes('display:none') ||
                             value.includes('visibility: hidden') || value.includes('visibility:hidden'))) {
                    const stack = new Error().stack;
                    const caller = extractCaller(stack);

                    console.warn('%c[CBM STYLE INTERFERENCE]', 'background: #ff00ff; color: #fff; padding: 3px;');
                    console.warn('Button hide attempt via style property');
                    console.warn('Element:', this);
                    console.warn('Attempted style:', value);
                    console.warn('Called from:', caller);

                    interferenceLog.push({
                        time: new Date().toISOString(),
                        element: this.className,
                        action: 'styleChange',
                        value: value,
                        caller: caller,
                        stack: stack
                    });
                }
            }
            return originalStyle.set.call(this, value);
        }
    });

    // Monitor jQuery if it exists
    if (typeof jQuery !== 'undefined') {
        const originalJQueryAddClass = jQuery.fn.addClass;
        const originalJQueryCSS = jQuery.fn.css;
        const originalJQueryHide = jQuery.fn.hide;

        jQuery.fn.addClass = function(className) {
            if (className === 'no-button') {
                const stack = new Error().stack;
                const caller = extractCaller(stack);

                console.warn('%c[CBM JQUERY INTERFERENCE]', 'background: #0066ff; color: #fff; padding: 3px;');
                console.warn('jQuery addClass no-button');
                console.warn('Elements:', this);
                console.warn('Called from:', caller);

                interferenceLog.push({
                    time: new Date().toISOString(),
                    element: this.attr('class'),
                    action: 'jQuery.addClass',
                    value: className,
                    caller: caller,
                    stack: stack
                });
            }
            return originalJQueryAddClass.apply(this, arguments);
        };

        jQuery.fn.css = function(prop, value) {
            if ((prop === 'display' && value === 'none') ||
                (prop === 'visibility' && value === 'hidden')) {
                if (this.hasClass('add-to-cart-button') || this.hasClass('box')) {
                    const stack = new Error().stack;
                    const caller = extractCaller(stack);

                    console.warn('%c[CBM JQUERY CSS INTERFERENCE]', 'background: #9900ff; color: #fff; padding: 3px;');
                    console.warn('jQuery css hiding attempt');
                    console.warn('Elements:', this);
                    console.warn('Property:', prop, 'Value:', value);
                    console.warn('Called from:', caller);

                    interferenceLog.push({
                        time: new Date().toISOString(),
                        element: this.attr('class'),
                        action: 'jQuery.css',
                        value: prop + ': ' + value,
                        caller: caller,
                        stack: stack
                    });
                }
            }
            return originalJQueryCSS.apply(this, arguments);
        };

        jQuery.fn.hide = function() {
            if (this.hasClass('add-to-cart-button') || this.hasClass('box')) {
                const stack = new Error().stack;
                const caller = extractCaller(stack);

                console.warn('%c[CBM JQUERY HIDE INTERFERENCE]', 'background: #ff0099; color: #fff; padding: 3px;');
                console.warn('jQuery hide() called');
                console.warn('Elements:', this);
                console.warn('Called from:', caller);

                interferenceLog.push({
                    time: new Date().toISOString(),
                    element: this.attr('class'),
                    action: 'jQuery.hide',
                    value: 'hide()',
                    caller: caller,
                    stack: stack
                });
            }
            return originalJQueryHide.apply(this, arguments);
        };
    }

    // Extract meaningful caller info from stack trace
    function extractCaller(stack) {
        const lines = stack.split('\n');
        // Skip the first 2-3 lines (Error + our override)
        for (let i = 2; i < lines.length; i++) {
            const line = lines[i];
            // Skip our own debug script
            if (line.includes('cbm-debug-interference.js')) continue;
            if (line.includes('cbm-popup-simple.js')) continue;
            if (line.includes('frontend.js')) continue;

            // Try to extract file info
            const match = line.match(/(?:at\s+)?(?:.*?\s+)?(?:\()?(https?:\/\/[^\s)]+)/);
            if (match) {
                const url = match[1];
                // Extract plugin/theme name from URL
                if (url.includes('/plugins/')) {
                    const pluginMatch = url.match(/\/plugins\/([^\/]+)/);
                    if (pluginMatch) {
                        return 'PLUGIN: ' + pluginMatch[1] + ' - ' + url;
                    }
                }
                if (url.includes('/themes/')) {
                    const themeMatch = url.match(/\/themes\/([^\/]+)/);
                    if (themeMatch) {
                        return 'THEME: ' + themeMatch[1] + ' - ' + url;
                    }
                }
                if (url.includes('/wp-includes/') || url.includes('/wp-admin/')) {
                    return 'WORDPRESS CORE: ' + url;
                }
                return 'UNKNOWN: ' + url;
            }
        }
        return 'Could not determine caller';
    }

    // Global function to view interference log
    window.CBMDebug = {
        showInterferences: function() {
            console.group('%c[CBM DEBUG] Interference Log', 'background: #000; color: #0f0; padding: 5px;');
            if (interferenceLog.length === 0) {
                console.log('No interferences detected yet');
            } else {
                console.table(interferenceLog);
            }
            console.groupEnd();
            return interferenceLog;
        },

        clearLog: function() {
            interferenceLog.length = 0;
            console.log('Interference log cleared');
        },

        startMonitoring: function(selector) {
            const element = document.querySelector(selector);
            if (!element) {
                console.error('Element not found:', selector);
                return;
            }

            console.log('%c[CBM DEBUG] Starting intensive monitoring on:', 'background: #00ff00; color: #000;', element);

            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    console.group('%c[CBM MUTATION]', 'background: #ffff00; color: #000;');
                    console.log('Type:', mutation.type);
                    console.log('Target:', mutation.target);

                    if (mutation.type === 'attributes') {
                        console.log('Attribute changed:', mutation.attributeName);
                        console.log('Old value:', mutation.oldValue);
                        console.log('New value:', mutation.target.getAttribute(mutation.attributeName));
                    }

                    if (mutation.type === 'childList') {
                        console.log('Added nodes:', mutation.addedNodes);
                        console.log('Removed nodes:', mutation.removedNodes);
                    }

                    // Try to get stack trace
                    const stack = new Error().stack;
                    console.log('Stack:', extractCaller(stack));

                    console.groupEnd();
                });
            });

            observer.observe(element, {
                attributes: true,
                attributeOldValue: true,
                childList: true,
                subtree: true,
                characterData: true,
                characterDataOldValue: true
            });

            window.CBMDebug.observer = observer;
            console.log('Monitoring started. Stop with CBMDebug.stopMonitoring()');
        },

        stopMonitoring: function() {
            if (window.CBMDebug.observer) {
                window.CBMDebug.observer.disconnect();
                console.log('Monitoring stopped');
            }
        },

        findScriptsModifyingClass: function(className) {
            console.group('%c[CBM DEBUG] Scripts that might modify .' + className, 'background: #000; color: #ff0;');

            // Check all loaded scripts
            const scripts = document.querySelectorAll('script[src]');
            const suspects = [];

            scripts.forEach(function(script) {
                const src = script.src;
                // Skip our own scripts
                if (src.includes('course-dashboard-manager')) return;

                // Categorize scripts
                if (src.includes('/plugins/')) {
                    const pluginName = src.match(/\/plugins\/([^\/]+)/)?.[1];
                    if (pluginName) {
                        suspects.push({
                            type: 'PLUGIN',
                            name: pluginName,
                            url: src
                        });
                    }
                } else if (src.includes('/themes/')) {
                    const themeName = src.match(/\/themes\/([^\/]+)/)?.[1];
                    if (themeName) {
                        suspects.push({
                            type: 'THEME',
                            name: themeName,
                            url: src
                        });
                    }
                } else if (src.includes('/wp-includes/') || src.includes('/wp-admin/')) {
                    // Skip WordPress core usually
                } else {
                    suspects.push({
                        type: 'EXTERNAL',
                        name: 'Unknown',
                        url: src
                    });
                }
            });

            console.table(suspects);
            console.groupEnd();
            return suspects;
        }
    };

    // Auto-start monitoring if popup exists
    setTimeout(function() {
        if (document.querySelector('#cbm-popup-overlay')) {
            console.log('%c[CBM DEBUG] Popup detected, auto-monitoring enabled', 'background: #00ff00; color: #000;');
            window.CBMDebug.startMonitoring('#cbm-popup-overlay');
        }
    }, 1000);

    console.log('%c[CBM DEBUG] Debugging tools ready. Use:', 'background: #000; color: #0ff;');
    console.log('  CBMDebug.showInterferences() - View interference log');
    console.log('  CBMDebug.startMonitoring(selector) - Monitor specific element');
    console.log('  CBMDebug.findScriptsModifyingClass(className) - Find suspect scripts');
    console.log('  CBMDebug.clearLog() - Clear the log');

})();