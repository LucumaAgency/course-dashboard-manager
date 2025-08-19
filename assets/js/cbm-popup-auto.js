/**
 * Course Box Manager - Auto Popup System
 * Automatically detects triggers and handles popup display
 */

(function() {
    'use strict';
    
    class CBMPopup {
        constructor(courseId = null) {
            this.courseId = courseId;
            this.overlay = null;
            this.container = null;
            this.isLoading = false;
            this.init();
        }
        
        init() {
            this.createOverlay();
            this.bindEvents();
        }
        
        createOverlay() {
            // Create overlay
            this.overlay = document.createElement('div');
            this.overlay.className = 'cbm-popup-overlay';
            this.overlay.style.display = 'none';
            
            // Create container
            this.container = document.createElement('div');
            this.container.className = 'cbm-popup-container';
            
            // Add close button
            const closeBtn = document.createElement('button');
            closeBtn.className = 'cbm-popup-close';
            closeBtn.innerHTML = '&times;';
            closeBtn.onclick = () => this.hide();
            
            this.container.appendChild(closeBtn);
            this.overlay.appendChild(this.container);
            document.body.appendChild(this.overlay);
        }
        
        bindEvents() {
            // Close on overlay click
            this.overlay.addEventListener('click', (e) => {
                if (e.target === this.overlay) {
                    this.hide();
                }
            });
            
            // Close on ESC key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isVisible()) {
                    this.hide();
                }
            });
        }
        
        async load() {
            if (this.isLoading) return;
            
            this.isLoading = true;
            this.showLoader();
            
            try {
                const formData = new FormData();
                formData.append('action', 'cbm_get_course_boxes');
                formData.append('course_id', this.courseId || this.getCourseIdFromContext());
                formData.append('context', 'popup');
                formData.append('nonce', cbm_ajax.nonce);
                
                const response = await fetch(cbm_ajax.url, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.setContent(data.data.html);
                    this.processBoxes();
                } else {
                    this.setContent('<div class="cbm-error">Error loading boxes</div>');
                }
            } catch (error) {
                console.error('CBM Popup Error:', error);
                this.setContent('<div class="cbm-error">Failed to load boxes</div>');
            } finally {
                this.isLoading = false;
                this.hideLoader();
            }
        }
        
        processBoxes() {
            const boxes = this.container.querySelectorAll('.box');
            const isMobile = window.innerWidth < 768;
            
            // Initialize tabs if needed
            if (isMobile && boxes.length === 2) {
                this.initializeTabs();
            }
            
            // Initialize any box-specific functionality
            this.initializeBoxFunctionality();
        }
        
        initializeTabs() {
            const tabButtons = this.container.querySelectorAll('.cbm-tab-btn');
            const tabPanes = this.container.querySelectorAll('.cbm-tab-pane');
            
            tabButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const tabIndex = btn.dataset.tab;
                    
                    // Update active states
                    tabButtons.forEach(b => b.classList.remove('active'));
                    tabPanes.forEach(p => p.classList.remove('active'));
                    
                    btn.classList.add('active');
                    const targetPane = this.container.querySelector(`.cbm-tab-pane[data-tab="${tabIndex}"]`);
                    if (targetPane) {
                        targetPane.classList.add('active');
                    }
                });
            });
        }
        
        initializeBoxFunctionality() {
            // Initialize date selection for enroll boxes
            const dateButtons = this.container.querySelectorAll('.date-btn');
            dateButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    // Remove selected from all
                    dateButtons.forEach(b => b.classList.remove('selected'));
                    // Add selected to clicked
                    this.classList.add('selected');
                    
                    // Store selected date
                    window.selectedDate = this.dataset.date || this.textContent.trim();
                });
            });
            
            // Initialize box selection
            const selectableBoxes = this.container.querySelectorAll('.box.selectable');
            selectableBoxes.forEach(box => {
                box.addEventListener('click', function() {
                    selectableBoxes.forEach(b => b.classList.remove('selected'));
                    this.classList.add('selected');
                });
            });
        }
        
        getCourseIdFromContext() {
            // Try to get course ID from various sources
            
            // 1. From body class
            const bodyClasses = document.body.className;
            const match = bodyClasses.match(/postid-(\d+)/);
            if (match) return match[1];
            
            // 2. From data attribute
            const courseEl = document.querySelector('[data-course-id]');
            if (courseEl) return courseEl.dataset.courseId;
            
            // 3. From URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            const courseId = urlParams.get('course_id');
            if (courseId) return courseId;
            
            // 4. Default or error
            console.warn('CBM: Could not determine course ID from context');
            return null;
        }
        
        showLoader() {
            const loader = document.createElement('div');
            loader.className = 'cbm-loader';
            loader.innerHTML = '<div class="cbm-spinner"></div>';
            this.container.appendChild(loader);
        }
        
        hideLoader() {
            const loader = this.container.querySelector('.cbm-loader');
            if (loader) {
                loader.remove();
            }
        }
        
        setContent(html) {
            // Keep close button
            const closeBtn = this.container.querySelector('.cbm-popup-close');
            this.container.innerHTML = '';
            if (closeBtn) {
                this.container.appendChild(closeBtn);
            }
            
            // Add new content
            const contentDiv = document.createElement('div');
            contentDiv.className = 'cbm-popup-content';
            contentDiv.innerHTML = html;
            this.container.appendChild(contentDiv);
        }
        
        show() {
            this.overlay.style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Trigger show event
            const event = new CustomEvent('cbm:popup:show', { detail: { popup: this } });
            document.dispatchEvent(event);
        }
        
        hide() {
            this.overlay.style.display = 'none';
            document.body.style.overflow = '';
            
            // Trigger hide event
            const event = new CustomEvent('cbm:popup:hide', { detail: { popup: this } });
            document.dispatchEvent(event);
        }
        
        isVisible() {
            return this.overlay && this.overlay.style.display !== 'none';
        }
    }
    
    // Auto-initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        // Find all triggers
        const triggers = document.querySelectorAll('.cbm-popup-trigger');
        
        if (triggers.length === 0) {
            console.log('CBM: No popup triggers found');
            return;
        }
        
        console.log(`CBM: Found ${triggers.length} popup trigger(s)`);
        
        // Attach click handlers
        triggers.forEach(trigger => {
            trigger.addEventListener('click', async function(e) {
                e.preventDefault();
                
                // Get course ID from trigger or context
                const courseId = this.dataset.courseId || null;
                
                // Create and show popup
                const popup = new CBMPopup(courseId);
                await popup.load();
                popup.show();
            });
        });
    });
    
    // Expose to global scope for programmatic access
    window.CBMPopup = CBMPopup;
})();