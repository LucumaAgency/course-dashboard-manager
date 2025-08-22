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
    private $buy_in_stock;
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
        
        // Get prices and stock from the actual WooCommerce products
        $this->buy_price = $this->course_price; // Default
        $this->buy_in_stock = true; // Default
        
        if ($this->buy_product_id && function_exists('wc_get_product')) {
            $buy_product = wc_get_product($this->buy_product_id);
            if ($buy_product) {
                // Use get_price() which returns the active price (sale or regular)
                $this->buy_price = $buy_product->get_price();
                $this->buy_in_stock = $buy_product->is_in_stock();
                
                // Check product status
                $is_purchasable = $buy_product->is_purchasable();
                $stock_status = $buy_product->get_stock_status();
                $sale_price = $buy_product->get_sale_price();
                $regular_price = $buy_product->get_regular_price();
                
                error_log('[EnrollBuyBox] Buy Product found - Regular: ' . $regular_price . ', Sale: ' . $sale_price . ', Active Price: ' . $this->buy_price);
                error_log('[EnrollBuyBox] Buy Product status - Purchasable: ' . ($is_purchasable ? 'yes' : 'no') . ', In Stock: ' . ($this->buy_in_stock ? 'yes' : 'no') . ', Stock Status: ' . $stock_status);
            } else {
                error_log('[EnrollBuyBox] Buy Product NOT found for ID: ' . $this->buy_product_id);
            }
        } else {
            error_log('[EnrollBuyBox] No buy_product_id set or WooCommerce not available');
        }
        
        // Get enroll price and stock from product
        $this->enroll_price = $this->enroll_price; // Default
        $this->enroll_in_stock = true; // Default
        
        if ($this->enroll_product_id && function_exists('wc_get_product')) {
            $enroll_product = wc_get_product($this->enroll_product_id);
            if ($enroll_product) {
                // Use get_price() which returns the active price (sale or regular)
                $this->enroll_price = $enroll_product->get_price();
                $this->enroll_in_stock = $enroll_product->is_in_stock();
                $sale_price = $enroll_product->get_sale_price();
                $regular_price = $enroll_product->get_regular_price();
                error_log('[EnrollBuyBox] Enroll Product found - Regular: ' . $regular_price . ', Sale: ' . $sale_price . ', Active Price: ' . $this->enroll_price);
                error_log('[EnrollBuyBox] Enroll Product In Stock: ' . ($this->enroll_in_stock ? 'yes' : 'no'));
            } else {
                error_log('[EnrollBuyBox] Enroll Product NOT found for ID: ' . $this->enroll_product_id);
            }
        } else {
            error_log('[EnrollBuyBox] No enroll_product_id set or WooCommerce not available');
        }
        
        // Get enroll dates - these are stored as course_dates
        $this->enroll_dates = cbm_get_field('course_dates', $this->course_id) ?: $this->available_dates_full;
        error_log('[EnrollBuyBox] Enroll Dates: ' . print_r($this->enroll_dates, true));
        
        // Create instances of both boxes with custom configurations
        $this->buyBox = new BuyCourseBox($course_id);
        $this->enrollBox = new EnrollCourseBox($course_id);
        
        // Override their properties to use separate products, prices, and stock
        $this->buyBox->box_state = 'buy-course';
        $this->buyBox->course_product_id = $this->buy_product_id;
        $this->buyBox->course_price = $this->buy_price;
        $this->buyBox->is_out_of_stock = !$this->buy_in_stock;
        
        error_log('[EnrollBuyBox] BuyBox configured with product ID: ' . $this->buyBox->course_product_id . ', price: ' . $this->buyBox->course_price . ', in stock: ' . ($this->buy_in_stock ? 'yes' : 'no'));
        
        $this->enrollBox->box_state = 'enroll-course';
        $this->enrollBox->course_product_id = $this->enroll_product_id;
        $this->enrollBox->enroll_price = $this->enroll_price;
        $this->enrollBox->course_price = $this->enroll_price; // EnrollBox uses course_price for display
        $this->enrollBox->is_out_of_stock = !$this->enroll_in_stock;
        $this->enrollBox->available_dates_full = $this->enroll_dates ?: $this->available_dates_full;
        $this->enrollBox->available_dates = array_column($this->enroll_dates ?: $this->available_dates_full, 'date');
        
        error_log('[EnrollBuyBox] EnrollBox configured with product ID: ' . $this->enrollBox->course_product_id . ', price: ' . $this->enrollBox->course_price . ', in stock: ' . ($this->enroll_in_stock ? 'yes' : 'no'));
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
        ob_start();
        ?>
        <div class="<?php echo esc_attr($this->get_box_classes()); ?>" 
             data-course-id="<?php echo esc_attr($this->course_id); ?>">
            
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
            // Tab switching logic and prevent selectBox behavior
            (function() {
                const container = document.querySelector('.enroll-buy-combo[data-course-id="<?php echo esc_js($this->course_id); ?>"]');
                if (container) {
                    // Prevent selectBox function from affecting these boxes
                    const boxes = container.querySelectorAll('.box');
                    boxes.forEach(box => {
                        // Remove onclick if it exists
                        box.onclick = null;
                        // Ensure boxes are always "selected" to show buttons
                        box.classList.add('selected');
                        box.classList.remove('no-button');
                        
                        // Stop click propagation
                        box.addEventListener('click', function(e) {
                            e.stopPropagation();
                        }, true);
                    });
                    
                    // Tab switching for mobile
                    const tabButtons = container.querySelectorAll('.cbm-tab-btn');
                    const tabPanes = container.querySelectorAll('.cbm-tab-pane');
                    
                    tabButtons.forEach(btn => {
                        btn.addEventListener('click', function() {
                            const targetTab = this.dataset.tab;
                            
                            // Update buttons
                            tabButtons.forEach(b => b.classList.remove('active'));
                            this.classList.add('active');
                            
                            // Update panes
                            tabPanes.forEach(pane => {
                                if (pane.dataset.tab === targetTab) {
                                    pane.classList.add('active');
                                } else {
                                    pane.classList.remove('active');
                                }
                            });
                        });
                    });
                }
            })();
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
        
        /* Ensure boxes in combo don't respond to selectBox */
        .enroll-buy-combo .box {
            cursor: default;
        }
        
        .enroll-buy-combo .box .circlecontainer,
        .enroll-buy-combo .box .circle-container {
            display: none !important;
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
                border-bottom: 2px solid #ddd;
                margin-bottom: 20px;
            }
            
            .enroll-buy-combo .cbm-tab-btn {
                flex: 1;
                padding: 12px 16px;
                background: none;
                border: none;
                border-bottom: 3px solid transparent;
                cursor: pointer;
                font-size: 16px;
                transition: all 0.3s;
            }
            
            .enroll-buy-combo .cbm-tab-btn.active {
                border-bottom-color: #333;
                font-weight: bold;
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