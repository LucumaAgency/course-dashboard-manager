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
    private $enroll_dates;
    
    public function __construct($course_id) {
        parent::__construct($course_id);
        
        // Get the separate product IDs for buy and enroll
        $this->buy_product_id = get_post_meta($this->course_id, 'buy_product_id', true);
        $this->enroll_product_id = get_post_meta($this->course_id, 'enroll_product_id', true);
        
        // If we don't have separate products, fall back to linked_product_id
        if (!$this->buy_product_id && !$this->enroll_product_id) {
            $this->buy_product_id = $this->course_product_id;
            $this->enroll_product_id = $this->course_product_id;
        }
        
        // Get separate prices if available
        $this->buy_price = get_post_meta($this->course_id, 'buy_price', true) ?: $this->course_price;
        
        // Get enroll dates
        $this->enroll_dates = cbm_get_field('enroll_dates', $this->course_id) ?: $this->available_dates_full;
        
        // Create instances of both boxes with custom configurations
        $this->buyBox = new BuyCourseBox($course_id);
        $this->enrollBox = new EnrollCourseBox($course_id);
        
        // Override their properties to use separate products
        $this->buyBox->box_state = 'buy-course';
        $this->buyBox->course_product_id = $this->buy_product_id;
        $this->buyBox->course_price = $this->buy_price;
        
        $this->enrollBox->box_state = 'enroll-course';
        $this->enrollBox->course_product_id = $this->enroll_product_id;
        $this->enrollBox->available_dates_full = $this->enroll_dates ?: $this->available_dates_full;
    }
    
    public function should_display() {
        // Display when box_state is enroll-buy
        return $this->box_state === 'enroll-buy';
    }
    
    protected function get_box_classes() {
        return parent::get_box_classes() . ' enroll-buy-combo';
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
                        <?php echo $this->buyBox->render(); ?>
                    </div>
                    <div class="cbm-tab-pane" data-tab="enroll">
                        <?php echo $this->enrollBox->render(); ?>
                    </div>
                </div>
            </div>
            
            <!-- Desktop: Buy box above Enroll box -->
            <div class="boxes-container desktop-layout desktop-only">
                <div class="buy-box-wrapper">
                    <?php echo $this->buyBox->render(); ?>
                </div>
                <div class="enroll-box-wrapper" style="margin-top: 20px;">
                    <?php echo $this->enrollBox->render(); ?>
                </div>
            </div>
            
            <script>
            // Tab switching logic
            (function() {
                const container = document.querySelector('.enroll-buy-combo[data-course-id="<?php echo esc_js($this->course_id); ?>"]');
                if (container) {
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