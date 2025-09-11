<?php
/**
 * Import/Export functionality for Course Box Manager
 */

namespace CourseBoxManager;

class ImportExport {
    
    /**
     * Initialize the import/export functionality
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
        add_action('admin_post_cbm_export', [__CLASS__, 'handle_export']);
        add_action('admin_post_cbm_import', [__CLASS__, 'handle_import']);
    }
    
    /**
     * Add menu page
     */
    public static function add_menu_page() {
        add_submenu_page(
            'edit.php?post_type=stm-courses',
            'Import/Export Settings',
            'Import/Export',
            'manage_options',
            'cbm-import-export',
            [__CLASS__, 'render_page']
        );
    }
    
    /**
     * Render the import/export page
     */
    public static function render_page() {
        ?>
        <div class="wrap">
            <h1>Course Box Manager - Import/Export</h1>
            
            <div class="cbm-import-export-container" style="display: flex; gap: 30px; margin-top: 20px;">
                
                <!-- Export Section -->
                <div class="cbm-export-section" style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2>Export Settings</h2>
                    <p>Export all Course Box Manager settings to a JSON file.</p>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <?php wp_nonce_field('cbm_export', 'cbm_export_nonce'); ?>
                        <input type="hidden" name="action" value="cbm_export">
                        <button type="submit" class="button button-primary">Export Settings</button>
                    </form>
                </div>
                
                <!-- Import Section -->
                <div class="cbm-import-section" style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2>Import Settings</h2>
                    <p>Import Course Box Manager settings from a JSON file.</p>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
                        <?php wp_nonce_field('cbm_import', 'cbm_import_nonce'); ?>
                        <input type="hidden" name="action" value="cbm_import">
                        <input type="file" name="cbm_import_file" accept=".json" required>
                        <br><br>
                        <button type="submit" class="button button-primary">Import Settings</button>
                    </form>
                </div>
                
            </div>
            
            <?php if (isset($_GET['message'])): ?>
                <?php if ($_GET['message'] === 'imported'): ?>
                    <div class="notice notice-success is-dismissible">
                        <p>Settings imported successfully!</p>
                    </div>
                <?php elseif ($_GET['message'] === 'error'): ?>
                    <div class="notice notice-error is-dismissible">
                        <p>Error importing settings. Please check the file and try again.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Handle export
     */
    public static function handle_export() {
        // Check nonce
        if (!isset($_POST['cbm_export_nonce']) || !wp_verify_nonce($_POST['cbm_export_nonce'], 'cbm_export')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to export settings');
        }
        
        // Get all course posts
        $courses = get_posts([
            'post_type' => 'stm-courses',
            'posts_per_page' => -1,
            'post_status' => 'any'
        ]);
        
        $export_data = [
            'version' => CBM_VERSION,
            'timestamp' => current_time('timestamp'),
            'courses' => []
        ];
        
        foreach ($courses as $course) {
            $course_data = [
                'id' => $course->ID,
                'title' => $course->post_title,
                'meta' => []
            ];
            
            // Get all meta keys we want to export
            $meta_keys = [
                'box_state',
                'linked_product_id',
                'course_price',
                'enroll_price',
                'course_dates',
                'box_custom_texts',
                'box_date_format',
                'box_price_format',
                'box_button_text',
                'enroll_buy_settings',
                'cbm_selling_page_url'
            ];
            
            foreach ($meta_keys as $key) {
                $value = get_post_meta($course->ID, $key, true);
                if (!empty($value)) {
                    $course_data['meta'][$key] = $value;
                }
            }
            
            // Also get ACF fields if they exist
            if (function_exists('get_field')) {
                $acf_fields = [
                    'course_price',
                    'enroll_price',
                    'course_dates'
                ];
                
                foreach ($acf_fields as $field) {
                    $value = get_field($field, $course->ID);
                    if (!empty($value)) {
                        $course_data['acf'][$field] = $value;
                    }
                }
            }
            
            $export_data['courses'][] = $course_data;
        }
        
        // Set headers for download
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="cbm-settings-' . date('Y-m-d-H-i-s') . '.json"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output JSON
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Handle import
     */
    public static function handle_import() {
        // Check nonce
        if (!isset($_POST['cbm_import_nonce']) || !wp_verify_nonce($_POST['cbm_import_nonce'], 'cbm_import')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to import settings');
        }
        
        // Check file upload
        if (!isset($_FILES['cbm_import_file']) || $_FILES['cbm_import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(admin_url('edit.php?post_type=stm-courses&page=cbm-import-export&message=error'));
            exit;
        }
        
        // Read file contents
        $json_content = file_get_contents($_FILES['cbm_import_file']['tmp_name']);
        $import_data = json_decode($json_content, true);
        
        if (!$import_data || !isset($import_data['courses'])) {
            wp_redirect(admin_url('edit.php?post_type=stm-courses&page=cbm-import-export&message=error'));
            exit;
        }
        
        // Import each course's settings
        foreach ($import_data['courses'] as $course_data) {
            $course_id = $course_data['id'];
            
            // Check if course exists
            if (get_post_type($course_id) !== 'stm-courses') {
                continue;
            }
            
            // Import meta data
            if (isset($course_data['meta'])) {
                foreach ($course_data['meta'] as $key => $value) {
                    update_post_meta($course_id, $key, $value);
                }
            }
            
            // Import ACF fields if available
            if (isset($course_data['acf']) && function_exists('update_field')) {
                foreach ($course_data['acf'] as $field => $value) {
                    update_field($field, $value, $course_id);
                }
            }
        }
        
        wp_redirect(admin_url('edit.php?post_type=stm-courses&page=cbm-import-export&message=imported'));
        exit;
    }
}