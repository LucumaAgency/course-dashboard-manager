<?php
namespace CourseBoxManager;

class Debug {
    private static $debug_log = [];
    private static $debug_enabled = false;
    
    public static function init() {
        self::$debug_enabled = get_option('cbm_debug_mode', false);
        
        if (self::$debug_enabled) {
            add_action('wp_footer', [__CLASS__, 'output_debug_info']);
            add_action('admin_footer', [__CLASS__, 'output_debug_info']);
            add_action('admin_notices', [__CLASS__, 'show_debug_notice']);
        }
        
        // Add debug toggle to admin bar
        add_action('admin_bar_menu', [__CLASS__, 'add_debug_toggle'], 999);
    }
    
    public static function log($message, $context = []) {
        if (!self::$debug_enabled) return;
        
        self::$debug_log[] = [
            'time' => current_time('Y-m-d H:i:s'),
            'message' => $message,
            'context' => $context,
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ];
        
        // Also log to error_log if WP_DEBUG is on
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[CBM Debug] ' . $message . ' | Context: ' . json_encode($context));
        }
    }
    
    public static function check_elementor() {
        self::log('Checking Elementor compatibility');
        
        $checks = [
            'elementor_active' => did_action('elementor/loaded') > 0,
            'is_elementor_page' => false,
            'is_preview_mode' => false,
            'post_type' => get_post_type(),
            'current_filter' => current_filter(),
            'current_action' => current_action()
        ];
        
        if (class_exists('\Elementor\Plugin')) {
            $checks['is_elementor_page'] = \Elementor\Plugin::$instance->db->is_built_with_elementor(get_the_ID());
            $checks['is_preview_mode'] = \Elementor\Plugin::$instance->preview->is_preview_mode();
        }
        
        self::log('Elementor check results', $checks);
        
        return $checks;
    }
    
    public static function check_seats_calculation($course_id) {
        self::log('Checking seats calculation', ['course_id' => $course_id]);
        
        $debug_info = [
            'course_id' => $course_id,
            'post_type' => get_post_type($course_id),
            'product_id' => get_post_meta($course_id, 'linked_product_id', true),
            'dates' => get_field('course_dates', $course_id),
            'stock' => get_field('course_stock', $course_id),
            'is_group_course' => preg_match('/( - G\d+|\(G\d+\))$/', get_the_title($course_id))
        ];
        
        if (function_exists('wc_get_product') && $debug_info['product_id']) {
            $product = wc_get_product($debug_info['product_id']);
            if ($product) {
                $debug_info['wc_stock'] = $product->get_stock_quantity();
                $debug_info['wc_manage_stock'] = $product->get_manage_stock();
            }
        }
        
        self::log('Seats calculation debug', $debug_info);
        
        return $debug_info;
    }
    
    public static function add_debug_toggle($wp_admin_bar) {
        if (!current_user_can('manage_options')) return;
        
        $current_state = self::$debug_enabled;
        $toggle_url = add_query_arg([
            'cbm_toggle_debug' => !$current_state ? '1' : '0',
            'cbm_nonce' => wp_create_nonce('cbm_debug_toggle')
        ]);
        
        $wp_admin_bar->add_node([
            'id' => 'cbm-debug',
            'title' => '🐛 CBM Debug: ' . ($current_state ? 'ON' : 'OFF'),
            'href' => $toggle_url,
            'meta' => [
                'class' => $current_state ? 'cbm-debug-on' : 'cbm-debug-off'
            ]
        ]);
    }
    
    public static function handle_debug_toggle() {
        if (isset($_GET['cbm_toggle_debug']) && wp_verify_nonce($_GET['cbm_nonce'], 'cbm_debug_toggle')) {
            $new_state = $_GET['cbm_toggle_debug'] === '1';
            update_option('cbm_debug_mode', $new_state);
            
            // Redirect to remove query args
            wp_redirect(remove_query_arg(['cbm_toggle_debug', 'cbm_nonce']));
            exit;
        }
    }
    
    public static function show_debug_notice() {
        if (!self::$debug_enabled) return;
        ?>
        <div class="notice notice-warning">
            <p><strong>Course Box Manager Debug Mode is ON</strong> - Debug information will be displayed at the bottom of pages. <a href="?cbm_toggle_debug=0&cbm_nonce=<?php echo wp_create_nonce('cbm_debug_toggle'); ?>">Turn Off</a></p>
        </div>
        <?php
    }
    
    public static function output_debug_info() {
        if (!self::$debug_enabled || !current_user_can('manage_options')) return;
        
        ?>
        <div id="cbm-debug-output" style="position: fixed; bottom: 0; left: 0; right: 0; background: #23282d; color: #fff; padding: 20px; max-height: 300px; overflow-y: auto; z-index: 99999; font-family: monospace; font-size: 12px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="margin: 0; color: #fff;">Course Box Manager Debug Log</h3>
                <button onclick="document.getElementById('cbm-debug-output').style.display='none'" style="background: #dc3232; color: #fff; border: none; padding: 5px 10px; cursor: pointer;">Close</button>
            </div>
            <div style="max-height: 250px; overflow-y: auto;">
                <?php if (empty(self::$debug_log)) : ?>
                    <p>No debug messages logged.</p>
                <?php else : ?>
                    <?php foreach (self::$debug_log as $entry) : ?>
                        <div style="margin-bottom: 10px; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 3px;">
                            <div style="color: #87ceeb;">[<?php echo esc_html($entry['time']); ?>] <?php echo esc_html($entry['message']); ?></div>
                            <?php if (!empty($entry['context'])) : ?>
                                <pre style="margin: 5px 0 0 0; color: #98fb98; font-size: 11px;"><?php echo esc_html(json_encode($entry['context'], JSON_PRETTY_PRINT)); ?></pre>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <style>
            #adminmenu .cbm-debug-on > a { background: #dc3232 !important; }
            #adminmenu .cbm-debug-off > a { background: #46b450 !important; }
        </style>
        <?php
    }
}