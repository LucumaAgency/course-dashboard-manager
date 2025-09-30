<?php
/**
 * Buy Course + Enroll Course Box Class
 * 
 * Displays both buy and enroll options with separate products
 * Shows buy box above enroll box on desktop, tabs on mobile
 */

namespace CourseBoxManager\Boxes;

class EnrollBuyBox extends AbstractBox {
    
    private $buyBox;
    private $enrollBox;
    private $buy_product_id;
    private $enroll_product_id;
    private $buy_price;
    private $buy_regular_price;     // Added to fix PHP 8.2 deprecated warning
    private $buy_sale_price;         // Added to fix PHP 8.2 deprecated warning
    private $enroll_regular_price;   // Added to fix PHP 8.2 deprecated warning
    private $enroll_sale_price;      // Added to fix PHP 8.2 deprecated warning
    private $enroll_in_stock;
    private $enroll_dates;
    
    public function __construct($course_id) {
        parent::__construct($course_id);
        
        // Debug log
        error_log('[EnrollBuyBox] Initializing for course ' . $course_id);
        
        // Get the separate product IDs for buy and enroll
        $this->buy_product_id = get_post_meta($this->course_id, 'buy_product_id', true);
        $this->enroll_product_id = get_post_meta($this->course_id, 'enroll_product_id', true);
        
        error_log('[EnrollBuyBox] Buy Product ID: ' . $this->buy_product_id);
        error_log('[EnrollBuyBox] Enroll Product ID: ' . $this->enroll_product_id);
        
        // If we don't have separate products, fall back to linked_product_id
        if (!$this->buy_product_id && !$this->enroll_product_id) {
            $this->buy_product_id = $this->course_product_id;
            $this->enroll_product_id = $this->course_product_id;
        }
        
        // Get buy price with WooCommerce priority
        $buy_price_data = $this->get_price_with_priority(
            $this->buy_product_id,
            'course_price',
            self::DEFAULT_BUY_PRICE
        );

        $this->buy_price = $buy_price_data['price'];
        $this->buy_regular_price = $buy_price_data['regular'];
        $this->buy_sale_price = $buy_price_data['sale'];

        error_log('[EnrollBuyBox] Buy price: ' . $this->buy_price . ' from ' . $buy_price_data['source']);

        // Get enroll price with WooCommerce priority
        $enroll_price_data = $this->get_price_with_priority(
            $this->enroll_product_id,
            'enroll_price',
            self::DEFAULT_ENROLL_PRICE
        );

        $this->enroll_price = $enroll_price_data['price'];
        $this->enroll_regular_price = $enroll_price_data['regular'];
        $this->enroll_sale_price = $enroll_price_data['sale'];

        // Get stock status
        $this->enroll_in_stock = true; // Default
        if ($this->enroll_product_id && function_exists('wc_get_product')) {
            $enroll_product = wc_get_product($this->enroll_product_id);
            if ($enroll_product) {
                $this->enroll_in_stock = $enroll_product->is_in_stock();
            }
        }

        error_log('[EnrollBuyBox] Enroll price: ' . $this->enroll_price . ' from ' . $enroll_price_data['source'] . ', Stock: ' . ($this->enroll_in_stock ? 'in stock' : 'out of stock'));
        
        // Get enroll dates - these are stored as course_dates
        $this->enroll_dates = cbm_get_field('course_dates', $this->course_id) ?: $this->available_dates_full;
        error_log('[EnrollBuyBox] Enroll Dates: ' . print_r($this->enroll_dates, true));
        
        // Create instances of both boxes with custom configurations
        $this->buyBox = new BuyCourseBox($course_id);
        $this->enrollBox = new EnrollCourseBox($course_id);
        
        // Override their properties to use separate products, prices and stock status
        $this->buyBox->box_state = 'buy-course';
        $this->buyBox->course_product_id = $this->buy_product_id;
        $this->buyBox->course_price = $this->buy_price;
        $this->buyBox->buy_regular_price = $this->buy_regular_price;
        $this->buyBox->buy_sale_price = $this->buy_sale_price;
        
        error_log('[EnrollBuyBox] BuyBox configured with product ID: ' . $this->buyBox->course_product_id . ', price: ' . $this->buyBox->course_price);
        
        $this->enrollBox->box_state = 'enroll-course';
        $this->enrollBox->course_product_id = $this->enroll_product_id;
        $this->enrollBox->enroll_price = $this->enroll_price;
        $this->enrollBox->course_price = $this->enroll_price; // EnrollBox uses course_price for display
        $this->enrollBox->enroll_regular_price = $this->enroll_regular_price;
        $this->enrollBox->enroll_sale_price = $this->enroll_sale_price;
        $this->enrollBox->is_out_of_stock = !$this->enroll_in_stock; // Pass WooCommerce stock status
        $this->enrollBox->available_dates_full = $this->enroll_dates ?: $this->available_dates_full;
        $this->enrollBox->available_dates = array_column($this->enroll_dates ?: $this->available_dates_full, 'date');
        
        error_log('[EnrollBuyBox] EnrollBox configured with product ID: ' . $this->enrollBox->course_product_id . ', price: ' . $this->enrollBox->course_price . ', WC stock: ' . ($this->enroll_in_stock ? 'in stock' : 'out of stock'));
    }
    
    public function should_display() {
        // Display when box_state is enroll-buy
        return $this->box_state === 'enroll-buy';
    }
    
    protected function get_box_classes() {
        // Don't include parent classes to avoid 'box' class that triggers selectBox behavior
        return 'enroll-buy-combo';
    }
    
    public function render() {
        // Debug: Log prices before rendering
        error_log('[EnrollBuyBox] RENDER - Buy Product ID: ' . $this->buy_product_id . ', Buy Price: ' . $this->buy_price);
        error_log('[EnrollBuyBox] RENDER - Enroll Product ID: ' . $this->enroll_product_id . ', Enroll Price: ' . $this->enroll_price);
        error_log('[EnrollBuyBox] RENDER - BuyBox product_id: ' . $this->buyBox->course_product_id . ', price: ' . $this->buyBox->course_price);
        error_log('[EnrollBuyBox] RENDER - EnrollBox product_id: ' . $this->enrollBox->course_product_id . ', price: ' . $this->enrollBox->enroll_price);

        ob_start();
        ?>
        <div class="<?php echo esc_attr($this->get_box_classes()); ?>"
             data-course-id="<?php echo esc_attr($this->course_id); ?>"
             data-buy-product-id="<?php echo esc_attr($this->buy_product_id); ?>"
             data-enroll-product-id="<?php echo esc_attr($this->enroll_product_id); ?>"
             data-buy-price="<?php echo esc_attr($this->buy_price); ?>"
             data-enroll-price="<?php echo esc_attr($this->enroll_price); ?>">
            
            <!-- Mobile: Render as tabs -->
            <div class="cbm-tabs-wrapper mobile-only">
                <div class="cbm-tabs-header">
                    <button class="cbm-tab-btn active" data-tab="buy">Buy Course</button>
                    <button class="cbm-tab-btn" data-tab="enroll">Enroll in Live</button>
                </div>
                <div class="cbm-tabs-content">
                    <div class="cbm-tab-pane active" data-tab="buy">
                        <div class="box-wrapper-no-select">
                            <?php echo $this->buyBox->render(); ?>
                        </div>
                    </div>
                    <div class="cbm-tab-pane" data-tab="enroll">
                        <div class="box-wrapper-no-select">
                            <?php echo $this->enrollBox->render(); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Desktop: Buy box above Enroll box -->
            <div class="boxes-container desktop-layout desktop-only">
                <div class="buy-box-wrapper box-wrapper-no-select">
                    <?php echo $this->buyBox->render(); ?>
                </div>
                <div class="enroll-box-wrapper box-wrapper-no-select" style="margin-top: 20px;">
                    <?php echo $this->enrollBox->render(); ?>
                </div>
            </div>
            
            <script>
            // Tab switching logic and box selection behavior
            function initEnrollBuyCombo() {
                const container = document.querySelector('.enroll-buy-combo[data-course-id="<?php echo esc_js($this->course_id); ?>"]');
                if (!container) return;

                // DEBUG: Log price information
                console.group('[CBM EnrollBuyBox] Price Debug - Course <?php echo esc_js($this->course_id); ?>');
                console.log('Container data attributes:', {
                    courseId: container.dataset.courseId,
                    buyProductId: container.dataset.buyProductId,
                    enrollProductId: container.dataset.enrollProductId,
                    buyPrice: container.dataset.buyPrice,
                    enrollPrice: container.dataset.enrollPrice
                });

                // Check actual rendered prices in DOM
                const buyBoxPrices = container.querySelectorAll('.buy-box-wrapper .price-container, [data-tab="buy"] .price-container');
                const enrollBoxPrices = container.querySelectorAll('.enroll-box-wrapper .price-container, [data-tab="enroll"] .price-container');

                console.log('Buy Box Prices in DOM:');
                buyBoxPrices.forEach((priceContainer, index) => {
                    const regularPrice = priceContainer.querySelector('.regular-price');
                    const salePrice = priceContainer.querySelector('.sale-price');
                    console.log(`  Buy Box ${index + 1}:`, {
                        regular: regularPrice ? regularPrice.textContent.trim() : 'none',
                        sale: salePrice ? salePrice.textContent.trim() : 'none'
                    });
                });

                console.log('Enroll Box Prices in DOM:');
                enrollBoxPrices.forEach((priceContainer, index) => {
                    const regularPrice = priceContainer.querySelector('.regular-price');
                    const salePrice = priceContainer.querySelector('.sale-price');
                    console.log(`  Enroll Box ${index + 1}:`, {
                        regular: regularPrice ? regularPrice.textContent.trim() : 'none',
                        sale: salePrice ? salePrice.textContent.trim() : 'none'
                    });
                });

                // Check product IDs in each box
                const buyBox = container.querySelector('.buy-box-wrapper .box, [data-tab="buy"] .box');
                const enrollBox = container.querySelector('.enroll-box-wrapper .box, [data-tab="enroll"] .box');

                if (buyBox) {
                    console.log('Buy Box data:', {
                        productId: buyBox.dataset.productId,
                        courseId: buyBox.dataset.courseId
                    });
                }

                if (enrollBox) {
                    console.log('Enroll Box data:', {
                        productId: enrollBox.dataset.productId,
                        courseId: enrollBox.dataset.courseId
                    });
                }

                console.groupEnd();

                // Setup box selection behavior
                const boxes = container.querySelectorAll('.box');
                
                // More specific selectors for both desktop and mobile views
                const buyBoxDesktop = container.querySelector('.buy-box-wrapper .box');
                const enrollBoxDesktop = container.querySelector('.enroll-box-wrapper .box');
                const buyBoxMobile = container.querySelector('[data-tab="buy"] .box');
                const enrollBoxMobile = container.querySelector('[data-tab="enroll"] .box');
                
                // Function to set box state
                function setBoxState(box, isSelected) {
                    if (!box) return;
                    
                    if (isSelected) {
                        // Active state: show ringed circle (circlecontainer)
                        box.classList.add('selected');
                        box.classList.remove('no-button');
                        const circleContainer = box.querySelector('.circlecontainer');
                        const circle = box.querySelector('.circle-container');
                        if (circleContainer) circleContainer.style.display = 'flex';
                        if (circle) circle.style.display = 'none';
                    } else {
                        // Inactive state: show simple circle (circle-container)
                        box.classList.remove('selected');
                        box.classList.remove('no-button');
                        const circleContainer = box.querySelector('.circlecontainer');
                        const circle = box.querySelector('.circle-container');
                        if (circleContainer) circleContainer.style.display = 'none';
                        if (circle) circle.style.display = 'flex';
                    }
                }
                
                // Force initial state: Buy box selected, Enroll box unselected
                // Apply immediately and after a short delay to ensure it takes effect
                function applyInitialState() {
                    setBoxState(buyBoxDesktop, true);
                    setBoxState(buyBoxMobile, true);
                    setBoxState(enrollBoxDesktop, false);
                    setBoxState(enrollBoxMobile, false);
                }
                
                applyInitialState();
                setTimeout(applyInitialState, 100);
                    
                    // Add click handlers for box selection
                    boxes.forEach(box => {
                        box.style.cursor = 'pointer';
                        box.addEventListener('click', function(e) {
                            // Only process if click is on the box itself, not on buttons
                            if (e.target.closest('.add-to-cart-button') || e.target.closest('.date-btn')) {
                                return;
                            }
                            
                            // Deselect all boxes (show simple circle for inactive)
                            boxes.forEach(b => {
                                b.classList.remove('selected');
                                const circleContainer = b.querySelector('.circlecontainer');
                                const circle = b.querySelector('.circle-container');
                                if (circleContainer) circleContainer.style.display = 'none';
                                if (circle) circle.style.display = 'flex';
                            });
                            
                            // Select clicked box (show ringed circle for active)
                            this.classList.add('selected');
                            const thisCircleContainer = this.querySelector('.circlecontainer');
                            const thisCircle = this.querySelector('.circle-container');
                            if (thisCircleContainer) thisCircleContainer.style.display = 'flex';
                            if (thisCircle) thisCircle.style.display = 'none';
                        });
                    });
                    
                    // Tab switching is handled by cbm-popup-simple.js
                    // No inline handlers needed - they conflict with jQuery event delegation
            }
            
            // Initialize on DOMContentLoaded and also immediately
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initEnrollBuyCombo);
            } else {
                initEnrollBuyCombo();
            }
            // Also run after a short delay to catch any dynamic rendering
            setTimeout(initEnrollBuyCombo, 500);
            </script>
            
        </div>
        
        <style>
        /* Override no-button behavior for enroll-buy combo */
        .enroll-buy-combo .box .add-to-cart-button {
            display: flex !important;
        }
        
        .enroll-buy-combo .box.no-button .add-to-cart-button {
            display: flex !important;
        }
        
        /* Box states in enroll-buy combo - force correct styles */
        .enroll-buy-combo .box.selected,
        .enroll-buy-combo .buy-box-wrapper .box.selected,
        .enroll-buy-combo .enroll-box-wrapper .box.selected,
        .enroll-buy-combo [data-tab="buy"] .box.selected,
        .enroll-buy-combo [data-tab="enroll"] .box.selected {
            background: linear-gradient(116.47deg, rgba(242, 46, 190, 0.24) 17.65%, rgba(170, 0, 212, 0.12) 84.4%) !important;
            border: 2px solid transparent !important;
            opacity: 1 !important;
        }
        
        .enroll-buy-combo .box:not(.selected),
        .enroll-buy-combo .buy-box-wrapper .box:not(.selected),
        .enroll-buy-combo .enroll-box-wrapper .box:not(.selected),
        .enroll-buy-combo [data-tab="buy"] .box:not(.selected),
        .enroll-buy-combo [data-tab="enroll"] .box:not(.selected) {
            background: #0E0D0F !important;
            border: 2px solid rgba(155, 159, 170, 0.24) !important;
            opacity: 1 !important;
        }
        
        /* Correct radio button display based on selection state */
        /* Active box: show ringed circle (circlecontainer) */
        .enroll-buy-combo .box.selected .circlecontainer {
            display: flex !important;
        }
        
        .enroll-buy-combo .box.selected .circle-container {
            display: none !important;
        }
        
        /* Inactive box: show simple circle (circle-container) */
        .enroll-buy-combo .box:not(.selected) .circlecontainer {
            display: none !important;
        }
        
        .enroll-buy-combo .box:not(.selected) .circle-container {
            display: flex !important;
        }
        
        /* Mobile only elements */
        .enroll-buy-combo .mobile-only {
            display: none;
        }
        
        /* Desktop only elements */
        .enroll-buy-combo .desktop-only {
            display: block;
        }
        
        /* Tab styles for mobile */
        @media (max-width: 768px) {
            .enroll-buy-combo .mobile-only {
                display: block;
                width: 100%;
            }
            
            .enroll-buy-combo .desktop-only {
                display: none;
            }
            
            .enroll-buy-combo .cbm-tabs-header {
                display: flex;
                margin-bottom: 20px;
            }
            
            .enroll-buy-combo .cbm-tab-btn {
                flex: 1;
                padding: 12px 16px;
                background: transparent !important;
                border: none;
                cursor: pointer;
                font-size: 16px;
                transition: all 0.3s;
                color: rgba(255, 255, 255, 0.7);
                position: relative;
            }
            
            .enroll-buy-combo .cbm-tab-btn::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                height: 2px;
                background: transparent;
                transition: background 0.3s;
            }
            
            .enroll-buy-combo .cbm-tab-btn:hover {
                background: transparent !important;
                color: rgba(255, 255, 255, 0.9);
            }
            
            .enroll-buy-combo .cbm-tab-btn:active,
            .enroll-buy-combo .cbm-tab-btn:focus {
                background: transparent !important;
                outline: none;
            }
            
            .enroll-buy-combo .cbm-tab-btn.active {
                background: transparent !important;
                color: #DE00A5 !important;
                font-weight: bold;
            }
            
            .enroll-buy-combo .cbm-tab-btn.active::after {
                background: #DE04A4 !important;
            }
            
            .enroll-buy-combo .cbm-tab-pane {
                display: none;
            }
            
            .enroll-buy-combo .cbm-tab-pane.active {
                display: block;
            }
        }
        
        /* Desktop styles */
        @media (min-width: 769px) {
            .enroll-buy-combo .boxes-container.desktop-layout {
                display: block;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
}