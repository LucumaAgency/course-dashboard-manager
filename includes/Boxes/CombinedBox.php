<?php
/**
 * Combined Box Class
 * 
 * Displays when a group has both Buy This Course and Enroll Course options
 */

namespace CourseBoxManager\Boxes;

class CombinedBox extends AbstractBox {
    
    protected $buy_course = null;
    protected $enroll_course = null;
    
    /**
     * Constructor
     * 
     * @param object $buy_course - Course with buy-course state
     * @param object $enroll_course - Course with enroll-course state
     */
    public function __construct($buy_course, $enroll_course) {
        // Use the enroll course as the primary course
        parent::__construct($enroll_course);
        
        $this->buy_course = $buy_course;
        $this->enroll_course = $enroll_course;
    }
    
    /**
     * Check if this box should display
     * 
     * @return bool
     */
    public function should_display() {
        // This box is only created when both courses exist
        return true;
    }
    
    /**
     * Render the combined box
     * 
     * @return string
     */
    public function render() {
        // Get data for both options
        $buy_course_id = $this->buy_course;
        $enroll_course_id = $this->enroll_course;
        
        $buy_price = get_field('course_price', $buy_course_id) ?: 749.99;
        $buy_product_id = get_post_meta($buy_course_id, 'linked_product_id', true);
        
        $enroll_price = get_field('course_price', $enroll_course_id) ?: 749.99;
        $enroll_product_id = get_post_meta($enroll_course_id, 'linked_product_id', true);
        $dates = get_field('course_dates', $enroll_course_id) ?: [];
        $course_stock = get_field('course_stock', $enroll_course_id) ?: 0;
        
        // Get instructor names for enroll course
        $instructors = get_post_meta($enroll_course_id, 'course_instructors', true) ?: [];
        $instructor_names = array_map(function($id) { 
            return get_the_title($id); 
        }, $instructors);
        $instructors_text = !empty($instructor_names) ? implode(' and ', $instructor_names) : 'Expert Instructors';
        
        ob_start();
        ?>
        <div class="box combined-course" data-course-id="<?php echo esc_attr($enroll_course_id); ?>" 
             onclick="selectCombinedBox(this, 'combined-<?php echo esc_attr($enroll_course_id); ?>', <?php echo esc_attr($enroll_course_id); ?>)">
            
            <div class="statebox">
                <div class="circle-container">
                    <div class="circle"></div>
                </div>
                <div class="circlecontainer" style="display:none;">
                    <div class="outer-circle">
                        <div class="middle-circle">
                            <div class="inner-circle"></div>
                        </div>
                    </div>
                </div>
                <h3><?php echo esc_html(get_the_title($enroll_course_id)); ?></h3>
            </div>
            
            <!-- Combined Options Section -->
            <div class="combined-options">
                <div class="option-tabs">
                    <button class="option-tab active" data-option="live">Live Course</button>
                    <button class="option-tab" data-option="recorded">Recorded Course</button>
                </div>
                
                <!-- Live Course Option -->
                <div class="option-content live-option active">
                    <div class="price">$<?php echo esc_html(number_format($enroll_price, 2)); ?></div>
                    <div class="description">
                        <?php echo esc_html(get_field('course_subtitle', $enroll_course_id) ?: 'Enroll in the Live Course'); ?>
                        <br>with <?php echo esc_html($instructors_text); ?>
                    </div>
                    
                    <!-- Date Selection -->
                    <?php if (!empty($dates)) : ?>
                        <div class="start-dates" style="display: none;">
                            <p class="choose-label">Choose your start date:</p>
                            <div class="date-options">
                                <?php foreach ($dates as $index => $date) : 
                                    if (isset($date['date'])) :
                                        $stock = isset($date['stock']) ? intval($date['stock']) : $course_stock;
                                        $sold = $enroll_product_id ? calculate_seats_sold($enroll_product_id, $date['date']) : 0;
                                        $available = max(0, $stock - $sold);
                                        $is_sold_out = $available <= 0;
                                ?>
                                    <button class="date-btn <?php echo $is_sold_out ? 'sold-out' : ''; ?>" 
                                            data-date="<?php echo esc_attr($date['date']); ?>"
                                            data-product-id="<?php echo esc_attr($enroll_product_id); ?>"
                                            data-option="live"
                                            <?php echo $is_sold_out ? 'disabled' : ''; ?>>
                                        <?php echo esc_html($date['date']); ?>
                                        <?php if ($is_sold_out) : ?>
                                            <span class="sold-out-label">(Sold Out)</span>
                                        <?php elseif ($available <= 5) : ?>
                                            <span class="low-stock">(<?php echo $available; ?> left)</span>
                                        <?php endif; ?>
                                    </button>
                                <?php 
                                    endif;
                                endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <button class="add-to-cart-button" data-product-id="<?php echo esc_attr($enroll_product_id); ?>" data-option="live">
                        <span class="button-text">Add to Cart - Live Course</span>
                        <span class="loader" style="display: none;"></span>
                    </button>
                </div>
                
                <!-- Recorded Course Option -->
                <div class="option-content recorded-option" style="display: none;">
                    <div class="price">$<?php echo esc_html(number_format($buy_price, 2)); ?></div>
                    <div class="description">
                        <?php echo esc_html(get_field('course_subtitle', $buy_course_id) ?: 'Buy the Recorded Course'); ?>
                        <br>Instant access to all recordings
                    </div>
                    
                    <button class="add-to-cart-button" data-product-id="<?php echo esc_attr($buy_product_id); ?>" data-option="recorded">
                        <span class="button-text">Add to Cart - Recorded Course</span>
                        <span class="loader" style="display: none;"></span>
                    </button>
                </div>
            </div>
            
            <div class="divider"></div>
            
            <!-- Features -->
            <div class="features">
                <?php 
                $features = [
                    'Live Course: Interactive sessions with Q&A',
                    'Recorded Course: Watch at your own pace',
                    'Lifetime access to materials',
                    'Certificate of completion'
                ];
                foreach ($features as $feature) : ?>
                    <div class="feature">✓ <?php echo esc_html($feature); ?></div>
                <?php endforeach; ?>
            </div>
            
            <div class="terms">Terms and conditions apply</div>
        </div>
        
        <style>
            .combined-course .option-tabs {
                display: flex;
                gap: 10px;
                margin: 15px 0;
                background: rgba(255, 255, 255, 0.05);
                border-radius: 25px;
                padding: 3px;
            }
            .combined-course .option-tab {
                flex: 1;
                padding: 8px 12px;
                background: transparent;
                border: none;
                color: rgba(255, 255, 255, 0.6);
                cursor: pointer;
                border-radius: 22px;
                transition: all 0.3s ease;
                font-size: 12px;
            }
            .combined-course .option-tab.active {
                background: linear-gradient(90deg, #DE04A4, #AA00D4);
                color: white;
            }
            .combined-course .option-content {
                transition: opacity 0.3s ease;
            }
            .combined-course .date-btn.sold-out {
                opacity: 0.5;
                cursor: not-allowed;
                background: rgba(255, 59, 59, 0.2);
            }
            .combined-course .sold-out-label,
            .combined-course .low-stock {
                display: block;
                font-size: 9px;
                margin-top: 2px;
            }
            .combined-course .low-stock {
                color: #ffa500;
            }
            .combined-course .features {
                margin: 15px 0;
            }
            .combined-course .feature {
                font-size: 11px;
                color: rgba(255, 255, 255, 0.8);
                margin: 5px 0;
            }
        </style>
        
        <script>
            function selectCombinedBox(element, boxId, courseId) {
                // Handle box selection
                const boxes = element.closest('.box-container').querySelectorAll('.box');
                boxes.forEach(box => {
                    box.classList.remove('selected');
                    const circleContainer = box.querySelector('.circle-container');
                    const circlecontainer = box.querySelector('.circlecontainer');
                    const startDates = box.querySelector('.start-dates');
                    if (circleContainer) circleContainer.style.display = 'flex';
                    if (circlecontainer) circlecontainer.style.display = 'none';
                    if (startDates) startDates.style.display = 'none';
                });
                
                element.classList.add('selected');
                const selectedCircleContainer = element.querySelector('.circle-container');
                const selectedCirclecontainer = element.querySelector('.circlecontainer');
                const selectedStartDates = element.querySelector('.start-dates.active, .live-option .start-dates');
                if (selectedCircleContainer) selectedCircleContainer.style.display = 'none';
                if (selectedCirclecontainer) selectedCirclecontainer.style.display = 'flex';
                if (selectedStartDates) selectedStartDates.style.display = 'block';
                
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            // Handle tab switching
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('.combined-course .option-tab').forEach(tab => {
                    tab.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const box = this.closest('.combined-course');
                        const option = this.dataset.option;
                        
                        // Update tabs
                        box.querySelectorAll('.option-tab').forEach(t => t.classList.remove('active'));
                        this.classList.add('active');
                        
                        // Update content
                        box.querySelectorAll('.option-content').forEach(content => {
                            content.style.display = 'none';
                            content.classList.remove('active');
                        });
                        
                        const targetContent = box.querySelector(`.${option}-option`);
                        if (targetContent) {
                            targetContent.style.display = 'block';
                            targetContent.classList.add('active');
                            
                            // Show/hide date selection for live option
                            const startDates = targetContent.querySelector('.start-dates');
                            if (startDates && box.classList.contains('selected')) {
                                startDates.style.display = 'block';
                            }
                        }
                    });
                });
                
                // Handle date selection for combined box
                document.querySelectorAll('.combined-course .date-btn').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        if (this.classList.contains('sold-out')) return;
                        
                        const box = this.closest('.combined-course');
                        const courseId = box.dataset.courseId;
                        
                        // Clear other selections in this box
                        box.querySelectorAll('.date-btn').forEach(b => b.classList.remove('selected'));
                        this.classList.add('selected');
                        
                        // Store selected date
                        if (typeof selectedDates !== 'undefined') {
                            selectedDates[courseId] = this.dataset.date;
                        }
                    });
                });
                
                // Handle add to cart for combined box
                document.querySelectorAll('.combined-course .add-to-cart-button').forEach(button => {
                    button.addEventListener('click', async function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const option = this.dataset.option;
                        const productId = this.dataset.productId;
                        const box = this.closest('.combined-course');
                        const courseId = box.dataset.courseId;
                        
                        if (!productId || productId === '0') {
                            alert('Error: Invalid product.');
                            return;
                        }
                        
                        // Check for date selection if live option
                        if (option === 'live') {
                            const selectedDate = box.querySelector('.live-option .date-btn.selected');
                            if (!selectedDate) {
                                alert('Please select a start date for the live course.');
                                return;
                            }
                        }
                        
                        this.classList.add('loading');
                        
                        // Add to cart logic (reuse existing function if available)
                        // ... existing add to cart implementation
                        
                        setTimeout(() => {
                            this.classList.remove('loading');
                        }, 2000);
                    });
                });
            });
        </script>
        <?php
        
        return ob_get_clean();
    }
}