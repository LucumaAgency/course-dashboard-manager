// Course Dashboard Manager - Tables Manager
// Extracted from inline script - now uses localized data

document.addEventListener('DOMContentLoaded', function() {
    // Extract data from localized object
    const coursesData = cbmTablesData.coursesData || [];
    const allProducts = cbmTablesData.allProducts || {};
    const groupId = cbmTablesData.groupId || 0;
    const stmCourses = cbmTablesData.stmCourses || [];
    const ajaxurl = cbmTablesData.ajaxUrl;
    const nonce = cbmTablesData.nonce;
    
    console.log('[CBM Debug] DOMContentLoaded - Tables view script starting');
    console.log('[CBM Debug] Group ID:', groupId);
    console.log('[CBM Debug] Courses data available:', coursesData.length > 0 ? 'YES' : 'NO');
    console.log('[CBM Debug] All products available:', Object.keys(allProducts).length > 0 ? 'YES' : 'NO');
    console.log('[CBM Debug] STM Courses available:', stmCourses.length);
    
                let currentBoxState = document.getElementById('group-box-state').value;
                let rowCounter = 0;
                
                console.log('[CBM Debug] Current box state:', currentBoxState);
                console.log('[CBM Debug] Courses data:', coursesData);
                
                // Function to render table based on box state
                function renderTable(boxState) {
                    const tableHeader = document.getElementById('table-header');
                    const tableBody = document.getElementById('table-body');
                    const addButton = document.getElementById('add-new-row');
                    const tableContainer = document.getElementById('table-container');
                    const buyTableContainer = document.getElementById('buy-table-container');
                    const enrollTableTitle = document.getElementById('enroll-table-title');
                    const stmCourseSelector = document.getElementById('stm-course-selector');
                    
                    // Clear existing content
                    tableHeader.innerHTML = '';
                    tableBody.innerHTML = '';
                    
                    // Always show table container
                    tableContainer.style.display = 'block';
                    
                    // Show/hide STM course selector based on state
                    if (boxState === 'enroll-course' || boxState === 'enroll-buy') {
                        stmCourseSelector.style.display = 'block';
                        populateSTMCourseSelector();
                    } else {
                        stmCourseSelector.style.display = 'none';
                    }
                    
                    // Handle enroll-buy state with two separate tables
                    if (boxState === 'enroll-buy') {
                        // Show both tables
                        buyTableContainer.style.display = 'block';
                        enrollTableTitle.style.display = 'block';
                        addButton.style.display = 'inline-block';
                        addButton.textContent = '+ Add New Enroll Date';
                        
                        // Render Buy Course table
                        renderBuyTable();
                        // Render Enroll Course table (will be handled below)
                    } else {
                        // Hide buy table for other states
                        buyTableContainer.style.display = 'none';
                        enrollTableTitle.style.display = 'none';
                        
                        // Show/hide add button based on state
                        if (boxState === 'enroll-course') {
                            addButton.style.display = 'inline-block';
                            addButton.textContent = '+ Add Course/Date';
                        } else {
                            addButton.style.display = 'none';
                        }
                    }
                    
                    // Build header based on box state
                    let headerHTML = '<tr>';
                    if (boxState === 'enroll-course') {
                        headerHTML += '<th style="width: 100px;">Date</th>';
                        headerHTML += '<th style="width: 150px;">Product</th>';
                        headerHTML += '<th style="width: 150px;">STM Course</th>';
                        headerHTML += '<th style="width: 80px;">Reg. Price</th>';
                        headerHTML += '<th style="width: 80px;">Sale Price</th>';
                        headerHTML += '<th style="width: 60px;">Seats</th>';
                        headerHTML += '<th style="width: 50px;">Sold</th>';
                        headerHTML += '<th style="width: 60px;">Avail.</th>';
                        headerHTML += '<th style="width: 120px;">Button Text</th>';
                        headerHTML += '<th style="width: 100px;">Actions</th>';
                    } else if (boxState === 'buy-course') {
                        headerHTML += '<th style="width: 200px;">Product</th>';
                        headerHTML += '<th style="width: 200px;">STM Course</th>';
                        headerHTML += '<th style="width: 100px;">Regular Price</th>';
                        headerHTML += '<th style="width: 100px;">Sale Price</th>';
                        headerHTML += '<th style="width: 80px;">Total Seats</th>';
                        headerHTML += '<th style="width: 80px;">Available</th>';
                        headerHTML += '<th style="width: 150px;">Button Text</th>';
                        headerHTML += '<th style="width: 120px;">Actions</th>';
                    } else if (boxState === 'countdown') {
                        headerHTML += '<th style="width: 8%;">Date</th>';
                        headerHTML += '<th style="width: 13%;">Associated Product</th>';
                        headerHTML += '<th style="width: 8%;">Regular Price</th>';
                        headerHTML += '<th style="width: 8%;">Sale Price</th>';
                        headerHTML += '<th style="width: 13%;">Launch Date & Time</th>';
                        headerHTML += '<th style="width: 7%;">Total Seats</th>';
                        headerHTML += '<th style="width: 7%;">Sold</th>';
                        headerHTML += '<th style="width: 8%;">Available</th>';
                        headerHTML += '<th style="width: 13%;">Button Text</th>';
                        headerHTML += '<th style="width: 15%;">Actions</th>';
                    } else if (boxState === 'waitlist') {
                        headerHTML += '<th style="width: 20%;">Associated Product</th>';
                        headerHTML += '<th style="width: 15%;">Regular Price</th>';
                        headerHTML += '<th style="width: 15%;">Sale Price</th>';
                        headerHTML += '<th style="width: 20%;">Button Text</th>';
                        headerHTML += '<th style="width: 30%;">Actions</th>';
                    } else if (boxState === 'soldout') {
                        headerHTML += '<th style="width: 10%;">Date</th>';
                        headerHTML += '<th style="width: 15%;">Associated Product</th>';
                        headerHTML += '<th style="width: 8%;">Regular Price</th>';
                        headerHTML += '<th style="width: 8%;">Sale Price</th>';
                        headerHTML += '<th style="width: 8%;">Total Seats</th>';
                        headerHTML += '<th style="width: 7%;">Sold</th>';
                        headerHTML += '<th style="width: 8%;">Available</th>';
                        headerHTML += '<th style="width: 15%;">Button Text</th>';
                        headerHTML += '<th style="width: 21%;">Actions</th>';
                    } else if (boxState === 'enroll-buy') {
                        // For enroll-buy, we'll have separate headers for each table
                        // Enroll table header (similar to enroll-course)
                        headerHTML += '<th style="width: 100px;">Date</th>';
                        headerHTML += '<th style="width: 140px;">Product</th>';
                        headerHTML += '<th style="width: 140px;">STM Course</th>';
                        headerHTML += '<th style="width: 70px;">Reg. Price</th>';
                        headerHTML += '<th style="width: 70px;">Sale Price</th>';
                        headerHTML += '<th style="width: 50px;">Seats</th>';
                        headerHTML += '<th style="width: 40px;">Sold</th>';
                        headerHTML += '<th style="width: 50px;">Avail.</th>';
                        headerHTML += '<th style="width: 100px;">Button Text</th>';
                        headerHTML += '<th style="width: 15%;">Actions</th>';
                    }
                    headerHTML += '</tr>';
                    tableHeader.innerHTML = headerHTML;
                    
                    // Build table rows based on box state
                    if (boxState === 'enroll-course') {
                        // Multiple rows allowed for enroll-course
                        coursesData.forEach(course => {
                            console.log('[CBM Debug] Loading course data:', course);
                            if (course.dates && course.dates.length > 0) {
                                console.log('[CBM Debug] Course has dates:', course.dates);
                                course.dates.forEach((dateInfo, index) => {
                                    console.log('[CBM Debug] Processing date:', dateInfo, 'at index:', index);
                                    addTableRow(course, {date: dateInfo, index: index}, boxState);
                                });
                            } else {
                                console.log('[CBM Debug] Course has no dates, adding empty row');
                                addTableRow(course, null, boxState);
                            }
                        });
                    } else if (boxState === 'enroll-buy') {
                        // For enroll-buy, only add enroll rows to the enroll table
                        // Buy table is handled separately by renderBuyTable()
                        const firstCourse = coursesData[0] || {
                            id: 0, 
                            product_id: '', 
                            buy_product_id: '',
                            enroll_product_id: '',
                            buy_price: '',
                            stock: 20
                        };
                        
                        // Add Enroll Course rows (can have multiple dates)
                        if (firstCourse.dates && firstCourse.dates.length > 0) {
                            firstCourse.dates.forEach((dateInfo, index) => {
                                addTableRow(firstCourse, {date: dateInfo, index: index}, boxState);
                            });
                        } else {
                            // Add at least one enroll row
                            addTableRow(firstCourse, null, boxState);
                        }
                    } else {
                        // Single row for all other states
                        const firstCourse = coursesData[0] || {id: 0, product_id: '', stock: 20};
                        const firstDate = firstCourse.dates && firstCourse.dates.length > 0 ? 
                                         {date: firstCourse.dates[0], index: 0} : null;
                        addTableRow(firstCourse, firstDate, boxState);
                    }
                }
                
                // Function to add a table row
                function addTableRow(course, dateInfo, boxState) {
                    const tableBody = document.getElementById('table-body');
                    const row = document.createElement('tr');
                    row.className = 'course-row editable-row';
                    row.dataset.courseId = course.id;
                    
                    console.log('[CBM Debug] addTableRow - Course ID:', course.id, 'Date Info:', dateInfo);
                    
                    if (dateInfo) {
                        row.dataset.dateIndex = dateInfo.index;
                    } else {
                        row.dataset.dateIndex = 'new';
                    }
                    
                    let rowHTML = '';
                    const stock = boxState === 'soldout' ? 0 : (dateInfo && dateInfo.date && dateInfo.date.stock !== undefined ? dateInfo.date.stock : course.stock || 20);
                    console.log('[CBM Debug] Stock for row:', stock, 'DateInfo:', dateInfo, 'Course stock:', course.stock);
                    const sold = 0; // Will be calculated server-side
                    const available = Math.max(0, stock - sold);
                    const buttonText = dateInfo && dateInfo.date && dateInfo.date.button_text ? dateInfo.date.button_text :
                                      (boxState === 'waitlist' ? 'Join Waitlist' : 'Enroll Now');

                    if (boxState === 'enroll-course') {
                        // Get STM Course ID for this specific date
                        const stmCourseId = dateInfo && dateInfo.date && dateInfo.date.stm_course_id ? dateInfo.date.stm_course_id : course.related_stm_course_id || '';
                        // Get Product ID for this specific date, fallback to course product
                        const productId = dateInfo && dateInfo.date && dateInfo.date.product_id ? dateInfo.date.product_id : course.product_id;
                        console.log('[CBM Debug] Product ID for row:', productId, 'from dateInfo:', dateInfo?.date?.product_id, 'fallback:', course.product_id);

                        rowHTML += `<td><input type="text" class="inline-edit-date" value="${dateInfo && dateInfo.date ? dateInfo.date.date : ''}" placeholder="YYYY-MM-DD" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>${buildProductSelect(productId)}</td>`;
                        rowHTML += `<td>${buildSTMCourseSelect(stmCourseId, course.id)}</td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-regular-price" value="${getProductRegularPrice(productId)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-sale-price" value="${getProductSalePrice(productId)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-stock" value="${stock}" min="0" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="sold-count">${sold}</span></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="available-count" style="color: ${available <= 5 ? '#d54e21' : (available <= 10 ? '#f0ad4e' : '#46b450')}; font-weight: bold;">${available}</span></td>`;
                        rowHTML += `<td><input type="text" class="inline-edit-button-text" value="${buttonText}" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>
                            <button class="button button-small button-primary save-row">Save</button>
                            <button class="button button-small delete-row" style="background: #d54e21; color: white; margin-left: 5px;">×</button>
                            <span class="save-status" style="margin-left: 5px;"></span>
                        </td>`;
                    } else if (boxState === 'soldout') {
                        const productId = dateInfo && dateInfo.date && dateInfo.date.product_id ? dateInfo.date.product_id : course.product_id;
                        rowHTML += `<td><input type="text" class="inline-edit-date" value="${dateInfo && dateInfo.date ? dateInfo.date.date : ''}" placeholder="YYYY-MM-DD" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>${buildProductSelect(productId)}</td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-regular-price" value="${getProductRegularPrice(productId)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-sale-price" value="${getProductSalePrice(productId)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-stock" value="0" min="0" readonly style="width: 100%; padding: 3px; background: #f0f0f0;"></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="sold-count">${sold}</span></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="available-count" style="color: #d54e21; font-weight: bold;">0</span></td>`;
                        rowHTML += `<td><input type="text" class="inline-edit-button-text" value="Sold Out" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>
                            <button class="button button-small button-primary save-row">Save</button>
                            <button class="button button-small delete-row" style="background: #d54e21; color: white; margin-left: 5px;">×</button>
                            <span class="save-status" style="margin-left: 5px;"></span>
                        </td>`;
                    } else if (boxState === 'buy-course') {
                        rowHTML += `<td>${buildProductSelect(course.product_id)}</td>`;
                        rowHTML += `<td>${buildSTMCourseSelect(course.related_stm_course_id || '', course.id)}</td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-regular-price" value="${getProductRegularPrice(course.product_id)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-sale-price" value="${getProductSalePrice(course.product_id)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-stock" value="${stock}" min="0" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="available-count" style="color: ${available <= 5 ? '#d54e21' : (available <= 10 ? '#f0ad4e' : '#46b450')}; font-weight: bold;">${available}</span></td>`;
                        rowHTML += `<td><input type="text" class="inline-edit-button-text" value="Buy Now" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>
                            <button class="button button-small button-primary save-row">Save</button>
                            <button class="button button-small delete-row" style="background: #d54e21; color: white; margin-left: 5px;">×</button>
                            <span class="save-status" style="margin-left: 5px;"></span>
                        </td>`;
                    } else if (boxState === 'countdown') {
                        const productId = dateInfo && dateInfo.date && dateInfo.date.product_id ? dateInfo.date.product_id : course.product_id;
                        rowHTML += `<td><input type="text" class="inline-edit-date" value="${dateInfo && dateInfo.date ? dateInfo.date.date : ''}" placeholder="YYYY-MM-DD" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>${buildProductSelect(productId)}</td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-regular-price" value="${getProductRegularPrice(productId)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-sale-price" value="${getProductSalePrice(productId)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="datetime-local" class="inline-edit-launch-date" value="${course.launch_date || ''}" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-stock" value="${stock}" min="0" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="sold-count">${sold}</span></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="available-count" style="color: ${available <= 5 ? '#d54e21' : (available <= 10 ? '#f0ad4e' : '#46b450')}; font-weight: bold;">${available}</span></td>`;
                        rowHTML += `<td><input type="text" class="inline-edit-button-text" value="${buttonText}" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>
                            <button class="button button-small button-primary save-row">Save</button>
                            <button class="button button-small delete-row" style="background: #d54e21; color: white; margin-left: 5px;">×</button>
                            <span class="save-status" style="margin-left: 5px;"></span>
                        </td>`;
                    } else if (boxState === 'waitlist') {
                        rowHTML += `<td>${buildProductSelect(course.product_id)}</td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-regular-price" value="${getProductRegularPrice(course.product_id)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-sale-price" value="${getProductSalePrice(course.product_id)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="text" class="inline-edit-button-text" value="Join Waitlist" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td>
                            <button class="button button-small button-primary save-row">Save</button>
                            <button class="button button-small delete-row" style="background: #d54e21; color: white; margin-left: 5px;">×</button>
                            <span class="save-status" style="margin-left: 5px;"></span>
                        </td>`;
                    } else if (boxState === 'enroll-buy') {
                        // For enroll-buy state, this handles enroll rows only
                        // Similar to enroll-course but for the enroll table
                        const dateValue = dateInfo && dateInfo.date ? (typeof dateInfo.date === 'object' ? dateInfo.date.date : dateInfo.date) : '';
                        rowHTML += `<td><input type="text" class="inline-edit-date" value="${dateValue}" placeholder="Date/Text" style="width: 100%; padding: 3px;"></td>`;

                        const enrollProductId = (dateInfo && dateInfo.date && dateInfo.date.product_id) ? dateInfo.date.product_id : (course.enroll_product_id || course.product_id);
                        console.log('[CBM Debug] Enroll product ID for row:', enrollProductId);
                        rowHTML += `<td>${buildProductSelect(enrollProductId, 'enroll-product-select')}</td>`;
                        
                        // Add STM Course selector for enroll-buy (enroll section)
                        const stmCourseId = dateInfo && dateInfo.date && dateInfo.date.stm_course_id ? dateInfo.date.stm_course_id : course.related_stm_course_id || '';
                        rowHTML += `<td>${buildSTMCourseSelect(stmCourseId, course.id)}</td>`;
                        
                        rowHTML += `<td><input type="number" class="inline-edit-regular-price" value="${getProductRegularPrice(enrollProductId)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td><input type="number" class="inline-edit-sale-price" value="${getProductSalePrice(enrollProductId)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                        
                        const enrollStock = dateInfo && dateInfo.date && dateInfo.date.stock ? dateInfo.date.stock : course.stock || 20;
                        const enrollSold = 0; // Will be calculated server-side
                        const enrollAvailable = Math.max(0, enrollStock - enrollSold);
                        rowHTML += `<td><input type="number" class="inline-edit-stock" value="${enrollStock}" min="0" style="width: 100%; padding: 3px;"></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="sold-count">${enrollSold}</span></td>`;
                        rowHTML += `<td style="text-align: center;"><span class="available-count" style="color: ${enrollAvailable <= 5 ? '#d54e21' : (enrollAvailable <= 10 ? '#f0ad4e' : '#46b450')}; font-weight: bold;">${enrollAvailable}</span></td>`;
                        
                        const currentButtonText = dateInfo && dateInfo.date && dateInfo.date.button_text ? dateInfo.date.button_text : 'Enroll Now';
                        rowHTML += `<td><input type="text" class="inline-edit-button-text" value="${currentButtonText}" style="width: 100%; padding: 3px;"></td>`;
                        
                        rowHTML += `<td>
                            <button class="button button-small button-primary save-row">Save</button>
                            <button class="button button-small delete-row" style="background: #d54e21; color: white; margin-left: 5px;">×</button>
                            <span class="save-status" style="margin-left: 5px;"></span>
                        </td>`;
                    }
                    
                    row.innerHTML = rowHTML;
                    tableBody.appendChild(row);
                    attachRowEventListeners(row);
                }
                
                // Function to render Buy Course table for enroll-buy state
                function renderBuyTable() {
                    const buyTableHeader = document.getElementById('buy-table-header');
                    const buyTableBody = document.getElementById('buy-table-body');
                    
                    // Clear existing content
                    buyTableHeader.innerHTML = '';
                    buyTableBody.innerHTML = '';
                    
                    // Build header for buy table
                    let headerHTML = '<tr>';
                    headerHTML += '<th style="width: 200px;">Product</th>';
                    headerHTML += '<th style="width: 200px;">STM Course</th>';
                    headerHTML += '<th style="width: 100px;">Regular Price</th>';
                    headerHTML += '<th style="width: 100px;">Sale Price</th>';
                    headerHTML += '<th style="width: 150px;">Button Text</th>';
                    headerHTML += '<th style="width: 120px;">Actions</th>';
                    headerHTML += '</tr>';
                    buyTableHeader.innerHTML = headerHTML;
                    
                    // Get course data
                    const firstCourse = coursesData[0] || {
                        id: 0,
                        product_id: '',
                        buy_product_id: '',
                        buy_price: ''
                    };
                    
                    console.log('[CBM Debug] Buy table course data:', firstCourse);
                    
                    // Create buy row
                    const row = document.createElement('tr');
                    row.className = 'course-row editable-row buy-row';
                    row.dataset.courseId = firstCourse.id;
                    
                    const buyProductId = firstCourse.buy_product_id || firstCourse.product_id;
                    console.log('[CBM Debug] Buy product ID for table:', buyProductId);
                    
                    let rowHTML = '';
                    rowHTML += `<td>${buildProductSelect(buyProductId, 'buy-product-select')}</td>`;
                    rowHTML += `<td>${buildSTMCourseSelect(firstCourse.related_stm_course_id || '', firstCourse.id)}</td>`;
                    rowHTML += `<td><input type="number" class="inline-edit-regular-price" value="${getProductRegularPrice(buyProductId)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                    rowHTML += `<td><input type="number" class="inline-edit-sale-price" value="${getProductSalePrice(buyProductId)}" min="0" step="0.01" style="width: 100%; padding: 3px;"></td>`;
                    rowHTML += `<td><input type="text" class="inline-edit-button-text" value="Buy Course" style="width: 100%; padding: 3px;"></td>`;
                    rowHTML += `<td>
                        <button class="button button-small button-primary save-buy-row">Save</button>
                        <span class="save-status" style="margin-left: 5px;"></span>
                    </td>`;
                    
                    row.innerHTML = rowHTML;
                    buyTableBody.appendChild(row);
                    
                    // Attach event listeners for buy table
                    attachBuyRowEventListeners(row);
                }
                
                // Attach event listeners for buy table row
                function attachBuyRowEventListeners(row) {
                    const saveBtn = row.querySelector('.save-buy-row');
                    if (saveBtn) {
                        saveBtn.addEventListener('click', function() {
                            const courseId = row.dataset.courseId;
                            const productSelect = row.querySelector('.buy-product-select, select');
                            const regularPriceInput = row.querySelector('.inline-edit-regular-price');
                            const salePriceInput = row.querySelector('.inline-edit-sale-price');
                            const buttonTextInput = row.querySelector('.inline-edit-button-text');
                            
                            // Save buy product configuration
                            const buyProductId = productSelect ? productSelect.value : '';
                            const regularPrice = regularPriceInput ? regularPriceInput.value : '';
                            const salePrice = salePriceInput ? salePriceInput.value : '';
                            const buttonText = buttonTextInput ? buttonTextInput.value : 'Buy Course';
                            
                            // Update product prices if needed
                            if (buyProductId && (regularPrice || salePrice)) {
                                // This would call the save function
                                console.log('Saving buy product config:', {
                                    courseId,
                                    buyProductId,
                                    regularPrice,
                                    salePrice,
                                    buttonText
                                });
                            }
                            
                            // Show save status
                            const statusSpan = row.querySelector('.save-status');
                            if (statusSpan) {
                                statusSpan.innerHTML = '✓ Saved';
                                setTimeout(() => {
                                    statusSpan.innerHTML = '';
                                }, 2000);
                            }
                        });
                    }
                }
                
                // Build product select dropdown
                function buildProductSelect(selectedId, className = '') {
                    const selectClass = className || 'inline-edit-product';
                    // Convert selectedId to string for comparison
                    const selectedIdStr = selectedId ? String(selectedId) : '';
                    console.log('[CBM Debug] Building product select with selectedId:', selectedIdStr);
                    console.log('[CBM Debug] Available products:', Object.keys(allProducts));

                    let html = `<select class="${selectClass}" style="width: 100%; padding: 3px;" onchange="updateProductPrice(this)"><option value="">None</option>`;
                    for (let id in allProducts) {
                        const productName = allProducts[id].name || allProducts[id]; // Support both old and new format
                        const isSelected = selectedIdStr === String(id);
                        if (isSelected) {
                            console.log('[CBM Debug] Found match! Product', id, 'is selected');
                        }
                        html += `<option value="${id}" ${isSelected ? 'selected' : ''}>${productName}</option>`;
                    }
                    html += '</select>';
                    return html;
                }
                
                // Populate global STM course selector
                function populateSTMCourseSelector() {
                    const globalSelector = document.getElementById('global-stm-course');
                    if (!globalSelector) return;
                    
                    // Get current course's STM ID
                    const currentSTMId = coursesData && coursesData[0] ? coursesData[0].related_stm_course_id : '';
                    
                    // Build options HTML
                    let html = '<option value="">None</option>';
                    
                    const stmCoursesGlobal = stmCourses;
                    
                    stmCoursesGlobal.forEach(course => {
                        const selected = currentSTMId == course.id ? 'selected' : '';
                        html += `<option value="${course.id}" ${selected}>${course.title} (#${course.id})</option>`;
                    });
                    
                    globalSelector.innerHTML = html;
                }
                
                // Build STM Course select dropdown
                function buildSTMCourseSelect(selectedId, courseId) {
                    // Convert selectedId to string for comparison
                    const selectedIdStr = selectedId ? String(selectedId) : '';
                    console.log('[CBM Debug] Building STM course select with selectedId:', selectedIdStr);

                    let html = `<select class="inline-edit-stm-course" data-course-id="${courseId}" style="width: 100%; padding: 3px;">`;
                    html += '<option value="">None</option>';
                    
                    // Get STM courses from PHP
