            document.addEventListener('DOMContentLoaded', function() {
                // Handle box state changes to show/hide additional fields
                const boxStateSelect = document.querySelector('.box-state-select');
                const standardProductRow = document.getElementById('standard-product-row');
                const buyProductRow = document.getElementById('buy-product-row');
                const enrollProductRow = document.getElementById('enroll-product-row');
                const buyPriceRow = document.getElementById('buy-price-row');
                
                function toggleProductFields() {
                    const selectedState = boxStateSelect ? boxStateSelect.value : '';
                    
                    if (selectedState === 'enroll-buy') {
                        // Hide standard product row, show buy and enroll rows
                        if (standardProductRow) standardProductRow.style.display = 'none';
                        if (buyProductRow) buyProductRow.style.display = 'table-row';
                        if (enrollProductRow) enrollProductRow.style.display = 'table-row';
                        if (buyPriceRow) buyPriceRow.style.display = 'table-row';
                    } else {
                        // Show standard product row, hide buy and enroll rows
                        if (standardProductRow) standardProductRow.style.display = 'table-row';
                        if (buyProductRow) buyProductRow.style.display = 'none';
                        if (enrollProductRow) enrollProductRow.style.display = 'none';
                        if (buyPriceRow) buyPriceRow.style.display = 'none';
                    }
                }
                
                // Initial toggle
                toggleProductFields();
                
                // Listen for changes
                if (boxStateSelect) {
                    boxStateSelect.addEventListener('change', toggleProductFields);
                }
                
                // Handle STM Course selection changes
                const stmCourseSelect = document.getElementById('stm-course');
                if (stmCourseSelect) {
                    stmCourseSelect.addEventListener('change', function() {
                        const courseId = this.getAttribute('data-course-id');
                        const stmCourseId = this.value;
                        
                        
                        // Save via AJAX
                        const formData = new FormData();
                        formData.append('action', 'save_course_settings');
                        formData.append('course_id', courseId);
                        formData.append('related_stm_course_id', stmCourseId);
                        formData.append('nonce', '<?php echo wp_create_nonce('course_box_nonce'); ?>');
                        
                        // Get other current values to preserve them
                        const groupSelect = document.querySelector('.group-select[data-course-id="' + courseId + '"]');
                        const instructorSelect = document.querySelector('.instructor-select[data-course-id="' + courseId + '"]');
                        const boxStateSelect = document.querySelector('.box-state-select[data-course-id="' + courseId + '"]');
                        const linkedProductSelect = document.querySelector('#linked-product[data-course-id="' + courseId + '"]');
                        
                        if (groupSelect) formData.append('group_id', groupSelect.value);
                        if (instructorSelect) {
                            const selectedInstructors = Array.from(instructorSelect.selectedOptions).map(opt => opt.value);
                            formData.append('instructors', JSON.stringify(selectedInstructors));
                        }
                        if (boxStateSelect) formData.append('box_state', boxStateSelect.value);
                        if (linkedProductSelect) formData.append('linked_product_id', linkedProductSelect.value);
                        
                        // Default values for required fields
                        formData.append('stock', '0');
                        formData.append('dates', '[]');
                        formData.append('selling_page_id', '0');
                        
                        fetch(ajaxurl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Show success feedback
                                const originalBg = stmCourseSelect.style.backgroundColor;
                                stmCourseSelect.style.backgroundColor = '#d4edda';
                                setTimeout(() => {
                                    stmCourseSelect.style.backgroundColor = originalBg;
                                }, 1500);
                            } else {
                                console.error('[CBM] Error saving STM Course:', data);
                                alert('Error saving STM Course: ' + (data.data || 'Unknown error'));
                            }
                        })
                        .catch(error => {
                            console.error('[CBM] Error saving STM Course:', error);
                        });
                    });
                } else {
                }
                
                const addCourseModal = document.getElementById('add-course-modal');
                const addCourseGroupModal = document.getElementById('add-course-group-modal');
                const closeButtons = document.getElementsByClassName('modal-close');

                // Open Add Course Modal
                document.querySelectorAll('.add-course').forEach(button => {
                    button.addEventListener('click', function() {
                        const groupId = this.getAttribute('data-group-id');
                        if (groupId) {
                            document.getElementById('course-group').value = groupId;
                        }
                        addCourseModal.style.display = 'block';
                    });
                });

                // Open Add Course Group Modal
                document.querySelectorAll('.add-course-group').forEach(button => {
                    button.addEventListener('click', function() {
                        addCourseGroupModal.style.display = 'block';
                    });
                });

                // Close Modals
                Array.from(closeButtons).forEach(button => {
                    button.addEventListener('click', function() {
                        addCourseModal.style.display = 'none';
                        addCourseGroupModal.style.display = 'none';
                    });
                });
                window.addEventListener('click', function(event) {
                    if (event.target === addCourseModal || event.target === addCourseGroupModal) {
                        addCourseModal.style.display = 'none';
                        addCourseGroupModal.style.display = 'none';
                    }
                });

                // Save Course Assignment
                document.getElementById('save-course-assignment').addEventListener('click', function() {
                    const courseId = document.getElementById('course-select').value;
                    const groupId = document.getElementById('course-group').value;
                    const instructors = Array.from(document.getElementById('course-instructors-select').selectedOptions).map(option => option.value);
                    
                    if (!courseId) {
                        alert('Please select a course.');
                        return;
                    }
                    
                    fetch(ajaxurl + '?action=assign_course_to_group', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'course_id=' + courseId + '&group_id=' + groupId + '&instructors=' + encodeURIComponent(JSON.stringify(instructors)) + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Course added to group successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + data.data);
                        }
                    });
                });

                // Save New Course Group
                document.getElementById('save-new-course-group').addEventListener('click', function() {
                    const groupName = document.getElementById('course-group-name').value;
                    if (!groupName.trim()) {
                        alert('Please enter a group name.');
                        return;
                    }
                    fetch(ajaxurl + '?action=create_new_course_group', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'group_name=' + encodeURIComponent(groupName) + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Course group created successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + data.data);
                        }
                    });
                });

                // Save Course Settings
                document.querySelectorAll('.save-course-settings').forEach(button => {
                    button.addEventListener('click', function() {
                        const courseId = this.getAttribute('data-course-id');
                        const groupId = document.querySelector(`#course-group[data-course-id="${courseId}"]`).value;
                        const boxState = document.querySelector(`.box-state-select[data-course-id="${courseId}"]`).value;
                        const instructors = Array.from(document.querySelector(`.instructor-select[data-course-id="${courseId}"]`).selectedOptions).map(option => option.value);
                        const stock = '';
                        const linkedProductElement = document.querySelector(`#linked-product[data-course-id="${courseId}"]`);
                        const linkedProductId = linkedProductElement ? linkedProductElement.value : 0;
                        const dateElements = document.querySelectorAll(`.date-list[data-course-id="${courseId}"] .date-stock-row`);
                        const dates = [];
                        dateElements.forEach(row => {
                            const dateInput = row.querySelector('.course-date');
                            const stockInput = row.querySelector('.course-stock');
                            const buttonTextInput = row.querySelector('.course-button-text');
                            if (dateInput && dateInput.value.trim() !== '') {
                                dates.push({
                                    date: dateInput.value.trim(),
                                    stock: stockInput ? stockInput.value : stock,
                                    button_text: buttonTextInput ? buttonTextInput.value.trim() : 'Enroll Now'
                                });
                            }
                        });
                        const sellingPageId = document.querySelector(`#selling-page[data-course-id="${courseId}"]`).value;
                        
                        // Get additional fields for enroll-buy state
                        let additionalParams = '';
                        if (boxState === 'enroll-buy') {
                            const buyProductEl = document.querySelector(`#buy-product[data-course-id="${courseId}"]`);
                            const enrollProductEl = document.querySelector(`#enroll-product[data-course-id="${courseId}"]`);
                            const buyPriceEl = document.querySelector(`#buy-price[data-course-id="${courseId}"]`);
                            
                            if (buyProductEl) {
                                additionalParams += '&buy_product_id=' + buyProductEl.value;
                            }
                            if (enrollProductEl) {
                                additionalParams += '&enroll_product_id=' + enrollProductEl.value;
                            }
                            if (buyPriceEl) {
                                additionalParams += '&buy_price=' + encodeURIComponent(buyPriceEl.value);
                            }
                        }
                        
                        // Get STM Course ID
                        const stmCourseEl = document.querySelector(`#stm-course[data-course-id="${courseId}"]`);
                        if (stmCourseEl) {
                            additionalParams += '&related_stm_course_id=' + stmCourseEl.value;
                        }
                        
                        fetch(ajaxurl + '?action=save_course_settings', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'course_id=' + courseId + '&group_id=' + groupId + '&box_state=' + boxState + '&instructors=' + encodeURIComponent(JSON.stringify(instructors)) + '&stock=' + stock + '&dates=' + encodeURIComponent(JSON.stringify(dates)) + '&selling_page_id=' + sellingPageId + '&linked_product_id=' + linkedProductId + additionalParams + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Update box state dropdown if it changed
                                if (data.data && data.data.updated_box_state) {
                                    const boxStateSelect = document.querySelector(`.box-state-select[data-course-id="${courseId}"]`);
                                    if (boxStateSelect) {
                                        boxStateSelect.value = data.data.updated_box_state;
                                        
                                        // Show notification if state was auto-changed to waitlist
                                        if (data.data.updated_box_state === 'waitlist' && boxState === 'enroll-course' && dates.length === 0) {
                                            const notification = document.createElement('div');
                                            notification.style.cssText = 'background: #f0ad4e; color: #333; padding: 10px; margin: 10px 0; border-radius: 4px;';
                                            notification.textContent = '⚠️ Box state automatically changed to Waitlist because no dates are configured.';
                                            boxStateSelect.parentElement.appendChild(notification);
                                            setTimeout(() => notification.remove(), 5000);
                                        }
                                    }
                                }
                                
                                // Show success message without redirecting
                                const button = document.querySelector(`.save-course-settings[data-course-id="${courseId}"]`);
                                
                                // Reset button appearance
                                button.style.backgroundColor = '';
                                button.style.animation = '';
                                button.textContent = 'Save Settings';
                                
                                const successMsg = document.createElement('span');
                                successMsg.style.cssText = 'color: #46b450; margin-left: 10px; font-weight: bold;';
                                successMsg.textContent = '✓ Settings saved successfully!';
                                button.parentElement.appendChild(successMsg);
                                
                                // Remove the message after 3 seconds
                                setTimeout(() => {
                                    successMsg.remove();
                                }, 3000);
                            } else {
                                alert('Error: ' + data.data);
                            }
                        });
                    });
                });

                // Remove Course from Group
                document.querySelectorAll('.remove-from-group').forEach(button => {
                    button.addEventListener('click', function() {
                        if (!confirm('Are you sure you want to remove this course from the group?')) return;
                        const courseId = this.getAttribute('data-course-id');
                        const groupId = this.getAttribute('data-group-id');
                        fetch(ajaxurl + '?action=remove_course_from_group', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'course_id=' + courseId + '&group_id=' + groupId + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Course removed from group successfully!');
                                location.reload();
                            } else {
                                alert('Error: ' + data.data);
                            }
                        });
                    });
                });
                
                // Delete Course (only used elsewhere, not in group view)
                document.querySelectorAll('.delete-course').forEach(button => {
                    button.addEventListener('click', function() {
                        if (!confirm('Are you sure you want to delete this course permanently?')) return;
                        const courseId = this.getAttribute('data-course-id');
                        fetch(ajaxurl + '?action=delete_course', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'course_id=' + courseId + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Course deleted successfully!');
                                location.reload();
                            } else {
                                alert('Error: ' + data.data);
                            }
                        });
                    });
                });

                // Add/Remove/Edit Dates
                document.querySelectorAll('.date-list').forEach(container => {
                    const courseId = container.getAttribute('data-course-id');
                    const addDateButton = container.querySelector('.add-date');
                    const defaultStock = 20;
                    
                    // Add new date functionality
                    if (addDateButton) {
                        addDateButton.addEventListener('click', function() {
                            const dateList = container;
                            const existingRows = dateList.querySelectorAll('.date-stock-row');
                            const index = existingRows.length;
                            const dateHeader = dateList.querySelector('.date-header');
                            
                            const wrapper = document.createElement('div');
                            wrapper.className = 'date-stock-row';
                            wrapper.style.cssText = 'display: flex; gap: 10px; margin-bottom: 8px; padding: 8px; background: #fff; border: 1px solid #ddd; border-radius: 4px; align-items: center;';
                            
                            // Date input
                            const newDateInput = document.createElement('input');
                            newDateInput.type = 'text';
                            newDateInput.className = 'course-date';
                            newDateInput.setAttribute('data-index', index);
                            newDateInput.placeholder = 'YYYY-MM-DD';
                            newDateInput.style.cssText = 'width: 120px; padding: 5px;';
                            
                            // Stock input
                            const newStockInput = document.createElement('input');
                            newStockInput.type = 'number';
                            newStockInput.className = 'course-stock';
                            newStockInput.setAttribute('data-index', index);
                            newStockInput.value = defaultStock;
                            newStockInput.min = '0';
                            newStockInput.style.cssText = 'width: 80px; padding: 5px;';
                            
                            // Sold span
                            const soldSpan = document.createElement('span');
                            soldSpan.style.cssText = 'width: 80px; text-align: center; color: #666;';
                            soldSpan.textContent = '0';
                            
                            // Available span
                            const availableSpan = document.createElement('span');
                            availableSpan.style.cssText = 'width: 80px; text-align: center; font-weight: bold; color: #46b450;';
                            availableSpan.textContent = defaultStock;
                            
                            // Button text input
                            const buttonTextInput = document.createElement('input');
                            buttonTextInput.type = 'text';
                            buttonTextInput.className = 'course-button-text';
                            buttonTextInput.setAttribute('data-index', index);
                            buttonTextInput.placeholder = 'Enroll Now';
                            buttonTextInput.value = 'Enroll Now';
                            buttonTextInput.style.cssText = 'width: 150px; padding: 5px;';
                            
                            // Actions div
                            const actionsDiv = document.createElement('div');
                            actionsDiv.style.width = '100px';
                            
                            const editButton = document.createElement('button');
                            editButton.className = 'button button-small edit-seats';
                            editButton.setAttribute('data-index', index);
                            editButton.textContent = 'Edit';
                            editButton.style.marginRight = '5px';
                            
                            const removeButton = document.createElement('button');
                            removeButton.className = 'button button-small remove-date';
                            removeButton.setAttribute('data-index', index);
                            removeButton.textContent = '×';
                            removeButton.style.cssText = 'background: #d54e21; color: white;';
                            removeButton.addEventListener('click', function() {
                                wrapper.remove();
                                updateSummary();
                                
                                // Show save reminder
                                const saveButton = document.querySelector(`.save-course-settings[data-course-id="${courseId}"]`);
                                if (saveButton) {
                                    saveButton.style.backgroundColor = '#f0ad4e';
                                    saveButton.textContent = 'Save Settings (Changes Pending)';
                                    saveButton.style.animation = 'pulse 1s infinite';
                                }
                            });
                            
                            // Listen for stock changes to update available seats
                            newStockInput.addEventListener('input', function() {
                                availableSpan.textContent = this.value;
                                updateAvailableColor(availableSpan, parseInt(this.value));
                                updateSummary();
                            });
                            
                            actionsDiv.appendChild(editButton);
                            actionsDiv.appendChild(removeButton);
                            
                            wrapper.appendChild(newDateInput);
                            wrapper.appendChild(newStockInput);
                            wrapper.appendChild(soldSpan);
                            wrapper.appendChild(availableSpan);
                            wrapper.appendChild(buttonTextInput);
                            wrapper.appendChild(actionsDiv);
                            
                            // Insert before the add button
                            const summaryDiv = dateList.querySelector('div[style*="Summary"]');
                            if (summaryDiv) {
                                dateList.insertBefore(wrapper, summaryDiv.previousElementSibling);
                            } else {
                                dateList.insertBefore(wrapper, addDateButton);
                            }
                            
                            // Focus on the new date input
                            newDateInput.focus();
                        });
                    }
                    
                    // Remove date functionality
                    container.querySelectorAll('.remove-date').forEach(button => {
                        button.addEventListener('click', function() {
                            if (confirm('Are you sure you want to remove this date?')) {
                                button.closest('.date-stock-row').remove();
                                updateSummary();
                                
                                // Show save reminder
                                const saveButton = document.querySelector(`.save-course-settings[data-course-id="${courseId}"]`);
                                if (saveButton) {
                                    // Add visual indicator that changes need to be saved
                                    saveButton.style.backgroundColor = '#f0ad4e';
                                    saveButton.textContent = 'Save Settings (Changes Pending)';
                                    
                                    // Add pulsing animation
                                    saveButton.style.animation = 'pulse 1s infinite';
                                }
                            }
                        });
                    });
                    
                    // Edit seats functionality (placeholder for future modal)
                    container.querySelectorAll('.edit-seats').forEach(button => {
                        button.addEventListener('click', function() {
                            const row = button.closest('.date-stock-row');
                            const dateInput = row.querySelector('.course-date');
                            const stockInput = row.querySelector('.course-stock');
                            
                            // For now, just focus on the stock input for quick editing
                            stockInput.focus();
                            stockInput.select();
                        });
                    });
                    
                    // Listen for stock input changes to update UI
                    container.querySelectorAll('.course-stock').forEach(input => {
                        input.addEventListener('input', function() {
                            const row = this.closest('.date-stock-row');
                            const availableSpan = row.querySelectorAll('span')[1]; // Second span is available
                            const soldSpan = row.querySelectorAll('span')[0]; // First span is sold
                            const sold = parseInt(soldSpan.textContent) || 0;
                            const newStock = parseInt(this.value) || 0;
                            const available = Math.max(0, newStock - sold);
                            
                            availableSpan.textContent = available;
                            updateAvailableColor(availableSpan, available);
                            updateRowClass(row, newStock, available);
                            updateSummary();
                        });
                    });
                    
                    // Helper function to update available seats color
                    function updateAvailableColor(element, available) {
                        if (available <= 5) {
                            element.style.color = '#d54e21';
                        } else if (available <= 10) {
                            element.style.color = '#f0ad4e';
                        } else {
                            element.style.color = '#46b450';
                        }
                    }
                    
                    // Helper function to update row class based on availability
                    function updateRowClass(row, stock, available) {
                        row.classList.remove('seat-warning', 'seat-caution');
                        if (stock > 0) {
                            const percentage = (available / stock) * 100;
                            if (percentage <= 20) {
                                row.classList.add('seat-warning');
                            } else if (percentage <= 50) {
                                row.classList.add('seat-caution');
                            }
                        }
                    }
                    
                    // Helper function to update summary
                    function updateSummary() {
                        const summaryDiv = container.querySelector('div[style*="Summary"]');
                        if (!summaryDiv) return;
                        
                        let totalStock = 0;
                        let totalSold = 0;
                        let totalAvailable = 0;
                        
                        container.querySelectorAll('.date-stock-row').forEach(row => {
                            const stockInput = row.querySelector('.course-stock');
                            const spans = row.querySelectorAll('span');
                            
                            if (stockInput && spans.length >= 2) {
                                const stock = parseInt(stockInput.value) || 0;
                                const sold = parseInt(spans[0].textContent) || 0;
                                const available = parseInt(spans[1].textContent) || 0;
                                
                                totalStock += stock;
                                totalSold += sold;
                                totalAvailable += available;
                            }
                        });
                        
                        summaryDiv.innerHTML = `
                            <strong>Summary:</strong> 
                            Total Seats: ${totalStock} | 
                            Sold: ${totalSold} | 
                            Available: <span style="color: ${totalAvailable <= 10 ? '#d54e21' : '#46b450'}">${totalAvailable}</span>
                        `;
                    }
                });

                // Search functionality
                document.getElementById('course-search').addEventListener('input', function() {
                    const search = this.value.toLowerCase();
                    document.querySelectorAll('.wp-list-table tbody tr').forEach(row => {
                        const searchText = row.cells[0].textContent.toLowerCase();
                        row.style.display = searchText.includes(search) ? '' : 'none';
                    });
                });

                // View Courses button
                document.querySelectorAll('.view-courses').forEach(button => {
                    button.addEventListener('click', function() {
                        const groupId = this.getAttribute('data-group-id');
                        window.location.href = '?page=course-box-manager&group_id=' + groupId;
                    });
                });

                // Delete Group button
                document.querySelectorAll('.delete-group').forEach(button => {
                    button.addEventListener('click', function() {
                        if (!confirm('Are you sure you want to delete this course group? This will not delete the courses.')) return;
                        const groupId = this.getAttribute('data-group-id');
                        fetch(ajaxurl + '?action=delete_course_group', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'group_id=' + groupId + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Course group deleted successfully!');
                                location.reload();
                            } else {
                                alert('Error: ' + data.data);
                            }
                        });
                    });
                });

                // Edit Course Settings
                document.querySelectorAll('.edit-course-settings').forEach(button => {
                    button.addEventListener('click', function() {
                        const courseId = this.getAttribute('data-course-id');
                        const urlParams = new URLSearchParams(window.location.search);
                        const groupId = urlParams.get('group_id');
                        let redirectUrl = '?page=course-box-manager&course_id=' + courseId;
                        if (groupId) {
                            redirectUrl += '&group_id=' + groupId;
                        }
                        window.location.href = redirectUrl;
                    });
                });

                // Inline date and stock editing in course list view (only for non-group views)
                // Skip if we're in a group view
                const urlParams = new URLSearchParams(window.location.search);
                const isGroupView = urlParams.has('group_id') && !urlParams.has('course_id');
                
                if (!isGroupView) {
                    document.querySelectorAll('.inline-dates-editor').forEach(editor => {
                    const courseId = editor.getAttribute('data-course-id');
                    let hasChanges = false;
                    
                    // Track changes
                    editor.addEventListener('input', function(e) {
                        if (e.target.classList.contains('inline-date-input') || e.target.classList.contains('inline-stock-input')) {
                            hasChanges = true;
                            const saveBtn = editor.querySelector('.inline-save-dates');
                            if (saveBtn) saveBtn.style.display = 'inline-block';
                        }
                    });
                    
                    // Add date functionality
                    const addBtn = editor.querySelector('.inline-add-date');
                    if (addBtn) {
                        addBtn.addEventListener('click', function() {
                            const existingRows = editor.querySelectorAll('.inline-date-row');
                            const newIndex = existingRows.length;
                            
                            const newRow = document.createElement('div');
                            newRow.className = 'inline-date-row';
                            newRow.style.cssText = 'display: flex; gap: 5px; margin-bottom: 3px; align-items: center;';
                            
                            // Get today's date in YYYY-MM-DD format
                            const today = new Date().toISOString().split('T')[0];
                            
                            newRow.innerHTML = `
                                <input type="text" 
                                       class="inline-date-input" 
                                       value="${today}"
                                       data-course-id="${courseId}"
                                       data-index="${newIndex}"
                                       style="width: 110px; padding: 2px 4px; font-size: 11px; background: #fff; color: #333;">
                                <input type="number" 
                                       class="inline-stock-input" 
                                       value="20"
                                       data-course-id="${courseId}"
                                       data-index="${newIndex}"
                                       min="0"
                                       style="width: 45px; padding: 2px 4px; font-size: 11px; background: #fff; color: #333;">
                                <span style="font-size: 11px; color: #666;">
                                    (0 sold, <span style="color: #4CAF50; font-weight: bold;">20 avail</span>)
                                </span>
                                <button class="inline-remove-date" 
                                        data-course-id="${courseId}"
                                        data-index="${newIndex}"
                                        style="padding: 1px 4px; font-size: 10px; background: #d54e21; color: white; border: none; cursor: pointer; border-radius: 2px;">
                                    ×
                                </button>
                            `;
                            
                            editor.insertBefore(newRow, addBtn);
                            
                            // Add remove functionality
                            newRow.querySelector('.inline-remove-date').addEventListener('click', function() {
                                newRow.remove();
                                hasChanges = true;
                                const saveBtn = editor.querySelector('.inline-save-dates');
                                if (saveBtn) saveBtn.style.display = 'inline-block';
                            });
                            
                            hasChanges = true;
                            const saveBtn = editor.querySelector('.inline-save-dates');
                            if (saveBtn) saveBtn.style.display = 'inline-block';
                        });
                    }
                    
                    // Remove date functionality
                    editor.querySelectorAll('.inline-remove-date').forEach(removeBtn => {
                        removeBtn.addEventListener('click', function() {
                            const row = this.closest('.inline-date-row');
                            row.remove();
                            hasChanges = true;
                            const saveBtn = editor.querySelector('.inline-save-dates');
                            if (saveBtn) saveBtn.style.display = 'inline-block';
                        });
                    });
                    
                    // Save functionality
                    const saveBtn = editor.querySelector('.inline-save-dates');
                    if (saveBtn) {
                        saveBtn.addEventListener('click', function() {
                            const dates = [];
                            editor.querySelectorAll('.inline-date-row').forEach(row => {
                                const dateInput = row.querySelector('.inline-date-input');
                                const stockInput = row.querySelector('.inline-stock-input');
                                if (dateInput && stockInput && dateInput.value) {
                                    dates.push({
                                        date: dateInput.value,
                                        stock: stockInput.value
                                    });
                                }
                            });
                            
                            // Save via AJAX
                            fetch(ajaxurl + '?action=save_inline_dates', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                body: 'course_id=' + courseId + 
                                      '&dates=' + encodeURIComponent(JSON.stringify(dates)) + 
                                      '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    saveBtn.style.display = 'none';
                                    hasChanges = false;
                                    
                                    // Update seats summary
                                    const summarySpan = document.querySelector(`.seats-summary[data-course-id="${courseId}"]`);
                                    if (summarySpan && data.data.summary) {
                                        summarySpan.textContent = data.data.summary;
                                        
                                        // Update class based on availability
                                        summarySpan.className = 'seats-summary';
                                        if (data.data.percentage <= 20) {
                                            summarySpan.classList.add('low-seats');
                                        } else if (data.data.percentage <= 50) {
                                            summarySpan.classList.add('medium-seats');
                                        }
                                    }
                                    
                                    // Show success message
                                    const successMsg = document.createElement('span');
                                    successMsg.style.cssText = 'color: #46b450; font-size: 11px; margin-left: 5px;';
                                    successMsg.textContent = '✓ Saved';
                                    saveBtn.parentElement.appendChild(successMsg);
                                    setTimeout(() => successMsg.remove(), 2000);
                                } else {
                                    alert('Error saving: ' + (data.data || 'Unknown error'));
                                }
                            })
                            .catch(error => {
                                alert('Error saving dates: ' + error.message);
                            });
                        });
                    }
                });
                }
            });
