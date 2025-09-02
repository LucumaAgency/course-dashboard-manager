<?php
/**
 * Course Dashboard Manager - Frontend Shortcode
 * 
 * Handles the [course_box_manager] shortcode display
 */

namespace CourseBoxManager\Frontend;

use CourseBoxManager\Boxes\BoxFactory;

// Register shortcode
add_shortcode('course_box_manager', __NAMESPACE__ . '\\course_box_manager_shortcode');

/**
 * Main shortcode handler
 */
function course_box_manager_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => get_the_ID(),
    ), $atts);
    
    $course_id = intval($atts['id']);
    
    // Enqueue required assets
    enqueue_frontend_assets();
    
    // Get the box instance and render
    $box = BoxFactory::create($course_id);
    
    if ($box) {
        return $box->render();
    }
    
    return '<div class="course-box-error">Course box configuration not found.</div>';
}

/**
 * Enqueue frontend assets
 */
function enqueue_frontend_assets() {
    // Enqueue styles
    wp_enqueue_style(
        'course-box-frontend',
        CBM_PLUGIN_URL . 'assets/css/frontend.css',
        array(),
        CBM_VERSION
    );
    
    // Enqueue scripts
    wp_enqueue_script(
        'course-box-frontend',
        CBM_PLUGIN_URL . 'assets/js/frontend.js',
        array('jquery'),
        CBM_VERSION,
        true
    );
    
    // Localize script
    wp_localize_script('course-box-frontend', 'cbm_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('woocommerce-add-to-cart'),
        'cart_url' => wc_get_cart_url(),
        'is_funnelkit_active' => defined('FKCART_VERSION') || class_exists('FKCart')
    ));
}

/**
 * Register additional shortcode for selectable boxes
 */
add_shortcode('selectable_boxes', __NAMESPACE__ . '\\selectable_boxes_shortcode');

function selectable_boxes_shortcode($atts) {
    $atts = shortcode_atts(array(
        'course_id' => get_the_ID(),
    ), $atts);
    
    return course_box_manager_shortcode(array('id' => $atts['course_id']));
}

/**
 * Pre-render popup content on page load
 * This function adds the popup HTML to the footer so it's instantly available
 */
add_action('wp_footer', __NAMESPACE__ . '\\prerender_popup_content');

function prerender_popup_content() {
    // Only pre-render on course pages
    if (!is_singular('course')) {
        return;
    }
    
    $course_id = get_the_ID();
    if (!$course_id) {
        return;
    }
    
    // Get the box to render
    $box = BoxFactory::create($course_id);
    if (!$box) {
        return;
    }
    
    // Start output buffering for box content
    ob_start();
    $box_html = $box->render();
    
    // Check if we have multiple boxes (EnrollBuyBox scenario)
    $has_tabs = false;
    if (strpos($box_html, 'enroll-course') !== false && strpos($box_html, 'buy-course') !== false) {
        $has_tabs = true;
        
        // Pre-render with tabs already created
        ?>
        <div class="cbm-tabs" data-prerendered-tabs="true">
            <div class="cbm-tabs-header">
                <button class="cbm-tab-btn active" data-tab="0" type="button">Buy Course</button>
                <button class="cbm-tab-btn" data-tab="1" type="button">Enroll in Live Course</button>
            </div>
            <div class="cbm-tabs-content">
                <?php
                // Extract individual boxes from the rendered HTML
                if (preg_match('/<div[^>]*class="[^"]*buy-course[^"]*"[^>]*>.*?<\/div>(?=\s*<div|$)/s', $box_html, $buy_match)) {
                    echo '<div class="cbm-tab-pane active" data-tab="0">' . $buy_match[0] . '</div>';
                }
                if (preg_match('/<div[^>]*class="[^"]*enroll-course[^"]*"[^>]*>.*?<\/div>(?=\s*<div|$)/s', $box_html, $enroll_match)) {
                    echo '<div class="cbm-tab-pane" data-tab="1">' . $enroll_match[0] . '</div>';
                }
                ?>
            </div>
        </div>
        <?php
        $final_html = ob_get_clean();
    } else {
        // Single box, no tabs needed
        $final_html = $box_html;
        ob_end_clean();
    }
    
    // Output the complete pre-rendered popup
    ?>
    <div id="cbm-popup-overlay" class="cbm-popup-overlay" style="display:none;" data-prerendered="true" data-has-tabs="<?php echo $has_tabs ? 'true' : 'false'; ?>">
        <div id="cbm-popup-container" class="cbm-popup-container">
            <button id="cbm-popup-close" class="cbm-popup-close">&times;</button>
            <div id="cbm-popup-content" class="cbm-popup-content">
                <?php echo $final_html; ?>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        console.log('[CBM] Popup HTML pre-rendered on server');
        
        // Simple close handler - no jQuery dependency
        document.addEventListener('DOMContentLoaded', function() {
            var overlay = document.getElementById('cbm-popup-overlay');
            var closeBtn = document.getElementById('cbm-popup-close');
            
            if (overlay && closeBtn) {
                // Close on button click
                closeBtn.addEventListener('click', function() {
                    overlay.style.display = 'none';
                });
                
                // Close on overlay click
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        overlay.style.display = 'none';
                    }
                });
            }
        });
    })();
    </script>
    <?php
}

