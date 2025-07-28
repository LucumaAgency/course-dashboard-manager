<?php
/**
 * Box Renderer Class
 * 
 * Handles rendering of course boxes and related assets
 */

namespace CourseBoxManager;

use CourseBoxManager\BoxFactory;

class BoxRenderer {
    
    /**
     * Render boxes for a course group
     * 
     * @param int $group_id
     * @return string
     */
    public static function render_boxes_for_group($group_id) {
        if (!$group_id) {
            return '';
        }
        
        $courses = get_posts([
            'post_type' => 'stm-courses',
            'posts_per_page' => -1,
            'tax_query' => [
                [
                    'taxonomy' => 'course_group',
                    'field' => 'term_id',
                    'terms' => $group_id,
                ],
            ],
            'fields' => 'ids'
        ]);
        
        if (empty($courses)) {
            return '';
        }
        
        $boxes = BoxFactory::get_boxes_for_courses($courses);
        
        ob_start();
        ?>
        <div class="selectable-box-container">
            <div class="box-container">
                <?php foreach ($boxes as $box) : ?>
                    <?php echo $box->render(); ?>
                <?php endforeach; ?>
            </div>
            <div class="text-outside-box">
                <p style="text-align: center; letter-spacing: 0.9px; margin-top: 30px; font-weight: 200; font-size: 12px;">
                    <span style="font-weight: 500; font-size: 14px;">Missing a Class?</span>
                    <br>No worries! All live courses will be recorded and made available on-demand to all students.
                </p>
            </div>
        </div>
        <?php
        self::render_styles();
        self::render_scripts();
        
        return ob_get_clean();
    }
    
    /**
     * Render CSS styles
     */
    protected static function render_styles() {
        ?>
        <style>
            .selectable-box-container { max-width: 1200px; margin: 0 auto; }
            .box-container {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                justify-content: center;
            }
            .box {
                max-width: 350px;
                width: 100%;
                padding: 15px;
                background: transparent;
                border: 2px solid #9B9FAA7A;
                border-radius: 15px;
                color: white;
                cursor: pointer;
                transition: all 0.3s ease;
                box-sizing: border-box;
            }
            .box.selected {
                background: linear-gradient(180deg, rgba(242, 46, 190, 0.2), rgba(170, 0, 212, 0.2));
                border: none;
                padding: 16px 12px;
            }
            .box:not(.selected) { opacity: 0.7; }
            .box h3 { color: #fff; margin-left: 10px; margin-top: 0; font-size: 1.5em; }
            .box .price { font-family: 'Poppins', sans-serif; font-weight: 500; font-size: 26px; }
            .box .description { font-size: 12px; color: rgba(255, 255, 255, 0.64); margin: 10px 0; }
            .box button {
                width: 100%;
                padding: 5px 12px;
                background-color: rgba(255, 255, 255, 0.08);
                border: none;
                border-radius: 4px;
                color: white;
                font-size: 12px;
                cursor: pointer;
            }
            .box button:hover { background-color: rgba(255, 255, 255, 0.2); }
            .divider { border-top: 1px solid rgba(255, 255, 255, 0.2); margin: 20px 0; }
            .soldout-course, .course-launch { background: #2a2a2a; text-align: center; }
            .soldout-header { background: #ff3e3e; padding: 10px; border-radius: 10px; margin-bottom: 10px; }
            .countdown { background: #800080; padding: 10px; border-radius: 10px; margin-bottom: 10px; display: flex; gap: 15px; justify-content: center; }
            .countdown-timer { display: flex; gap: 15px; }
            .time-unit { display: flex; flex-direction: column; align-items: center; }
            .time-value { font-size: 1.5em; font-weight: bold; }
            .time-label { font-size: 0.9em; color: rgba(255, 255, 255, 0.8); }
            .terms { font-size: 0.7em; color: #aaa; }
            .start-dates { display: none; margin-top: 15px; animation: fadeIn 0.4s ease; }
            .box.selected .start-dates { display: block; }
            .statebox { display: flex; }
            .outer-circle { width: 16px; height: 16px; border-radius: 50%; background-color: #DE04A4; border: 1.45px solid #DE04A4; display: flex; align-items: center; justify-content: center; }
            .middle-circle { width: 11.77px; height: 11.77px; border-radius: 50%; background-color: #050505; display: flex; align-items: center; justify-content: center; }
            .inner-circle { width: 6.16px; height: 6.16px; border-radius: 50%; background-color: #DE04A4; }
            .circlecontainer { margin: 6px 7px; }
            .circle-container { width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; }
            .circle { width: 14px; height: 14px; border-radius: 50%; border: 2px solid rgba(155, 159, 170, 0.24); }
            .box:not(.selected) .circlecontainer { display: none; }
            .box:not(.selected) .circle-container { display: flex; }
            .box.selected .circle-container { display: none; }
            .box.selected .circlecontainer { display: flex; }
            .choose-label { font-size: 0.95em; margin-bottom: 10px; color: #fff; }
            .date-options { display: flex; flex-wrap: wrap; gap: 4px; }
            .date-btn { width: 68px; padding: 5px 8px; border: none; border-radius: 25px; background-color: rgba(255, 255, 255, 0.08); color: white; cursor: pointer; }
            .date-btn:hover, .date-btn.selected { background-color: #cc3071; }
            @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
            @media (max-width: 767px) { .box { padding: 10px; } .box h3 { font-size: 1.2em; } }
            .add-to-cart-button {
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                height: 40px;
                padding: 5px 12px;
                background-color: rgba(255, 255, 255, 0.08);
                border: none;
                border-radius: 4px;
                color: white;
                font-size: 12px;
                cursor: pointer;
            }
            .add-to-cart-button.loading .button-text { visibility: hidden; }
            .add-to-cart-button.loading .loader { display: inline-block; }
            .loader {
                width: 8px;
                height: 8px;
                border: 2px solid transparent;
                border-top-color: #fff;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
            }
            @keyframes spin { to { transform: translate(-50%, -50%) rotate(360deg); } }
        </style>
        <?php
    }
    
    /**
     * Render JavaScript
     */
    protected static function render_scripts() {
        ?>
        <script>
            let selectedDates = {};
            let wasCartOpened = false;
            let wasCartManuallyClosed = false;

            function selectBox(element, boxId, courseId) {
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
                const selectedStartDates = element.querySelector('.start-dates');
                if (selectedCircleContainer) selectedCircleContainer.style.display = 'none';
                if (selectedCirclecontainer) selectedCirclecontainer.style.display = 'flex';
                if (selectedStartDates) selectedStartDates.style.display = 'block';
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });

                if (element.classList.contains('enroll-course')) {
                    const firstDateBtn = selectedStartDates.querySelector('.date-btn');
                    if (firstDateBtn && !selectedDates[courseId]) {
                        firstDateBtn.classList.add('selected');
                        selectedDates[courseId] = firstDateBtn.getAttribute('data-date') || firstDateBtn.textContent.trim();
                    }
                }
            }

            function openFunnelKitCart() {
                return new Promise((resolve) => {
                    jQuery(document.body).trigger('wc_fragment_refresh');
                    jQuery(document).trigger('fkcart_open_cart');
                    const checkVisibility = () => {
                        const sidebar = document.querySelector('#fkcart-sidecart, .fkcart-sidebar, .fk-cart-panel, .fkcart-cart-sidebar, .cart-sidebar, .fkcart-panel');
                        return sidebar && (sidebar.classList.contains('fkcart-active') || sidebar.classList.contains('active') || sidebar.classList.contains('fkcart-open') || window.getComputedStyle(sidebar).display !== 'none');
                    };
                    if (checkVisibility()) {
                        wasCartOpened = true;
                        resolve(true);
                        return;
                    }
                    setTimeout(() => {
                        resolve(checkVisibility());
                    }, 1000);
                });
            }

            document.addEventListener('DOMContentLoaded', function() {
                // Date selection
                document.querySelectorAll('.date-btn').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const courseId = this.closest('.box').getAttribute('data-course-id');
                        document.querySelectorAll('.date-btn').forEach(b => b.classList.remove('selected'));
                        this.classList.add('selected');
                        selectedDates[courseId] = this.getAttribute('data-date') || this.textContent.trim();
                    });
                });

                // Add to cart
                document.querySelectorAll('.add-to-cart-button').forEach(button => {
                    button.addEventListener('click', async function(e) {
                        e.preventDefault();
                        const productId = this.getAttribute('data-product-id');
                        const courseId = this.closest('.box').getAttribute('data-course-id');
                        if (!productId || productId === '0') {
                            alert('Error: Invalid product.');
                            return;
                        }

                        const isEnrollButton = this.closest('.enroll-course') !== null;
                        if (isEnrollButton && !selectedDates[courseId]) {
                            alert('Please select a start date.');
                            return;
                        }

                        this.classList.add('loading');

                        const addToCart = (productId, startDate = null) => {
                            return new Promise((resolve, reject) => {
                                jQuery.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                                    action: 'woocommerce_add_to_cart',
                                    product_id: productId,
                                    quantity: 1,
                                    start_date: startDate,
                                    security: '<?php echo wp_create_nonce('woocommerce_add_to_cart'); ?>'
                                }, function(response) {
                                    if (response && response.fragments && response.cart_hash) {
                                        resolve(response);
                                    } else {
                                        reject(new Error('Failed to add product to cart.'));
                                    }
                                }).fail(function(jqXHR, textStatus) {
                                    reject(new Error('Error: ' + textStatus));
                                });
                            });
                        };

                        try {
                            const response = await addToCart(productId, isEnrollButton ? selectedDates[courseId] : null);
                            jQuery(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash]);
                            jQuery(document.body).trigger('wc_fragment_refresh');
                            setTimeout(() => {
                                jQuery(document.body).trigger('wc_fragment_refresh');
                                jQuery(document).trigger('fkcart_open_cart');
                            }, 1000);
                            const cartOpened = await openFunnelKitCart();
                            if (!cartOpened && !wasCartOpened && !wasCartManuallyClosed) {
                                alert('The cart may not have updated. Please check manually.');
                            }
                        } catch (error) {
                            alert('Error adding to cart: ' + error.message);
                        } finally {
                            this.classList.remove('loading');
                        }
                    });
                });

                // Cart close tracking
                document.querySelectorAll('.fkcart-close, .fkcart-cart-close, .cart-close, .fkcart-close-btn, .fkcart-panel-close, [data-fkcart-close], .close-cart').forEach(close => {
                    close.addEventListener('click', () => wasCartManuallyClosed = true);
                });

                // Countdown timers
                document.querySelectorAll('.countdown-timer').forEach(countdown => {
                    const launchDate = countdown.dataset.launchDate;
                    if (launchDate) {
                        const updateCountdown = () => {
                            const now = new Date().getTime();
                            const timeDiff = new Date(launchDate).getTime() - now;
                            if (timeDiff <= 0) {
                                countdown.innerHTML = '<span class="launch-soon">Launched!</span>';
                                return;
                            }
                            const days = Math.floor(timeDiff / (1000 * 60 * 60 * 24));
                            const hours = Math.floor((timeDiff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                            const minutes = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));
                            const seconds = Math.floor((timeDiff % (1000 * 60)) / 1000);
                            countdown.querySelector('.time-unit[data-unit="days"] .time-value').textContent = String(Math.max(0, days)).padStart(2, '0');
                            countdown.querySelector('.time-unit[data-unit="hours"] .time-value').textContent = String(Math.max(0, hours)).padStart(2, '0');
                            countdown.querySelector('.time-unit[data-unit="minutes"] .time-value').textContent = String(Math.max(0, minutes)).padStart(2, '0');
                            countdown.querySelector('.time-unit[data-unit="seconds"] .time-value').textContent = String(Math.max(0, seconds)).padStart(2, '0');
                        };
                        updateCountdown();
                        setInterval(updateCountdown, 1000);
                    }
                });
            });
        </script>
        <?php
    }
}