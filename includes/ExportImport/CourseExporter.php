<?php
/**
 * Course Exporter - Exports Course Tables data to JSON
 * 
 * Simplified version with direct export/import buttons
 */

namespace CourseBoxManager\ExportImport;

class CourseExporter {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Prevent duplicate initialization
        if (self::$instance !== null) {
            return;
        }
        
        self::$instance = $this;
        
        // AJAX handlers for export
        add_action('wp_ajax_cbm_export_all_data', [$this, 'ajax_export_all_data']);
        
        // Add export/import buttons to admin pages
        add_action('admin_footer', [$this, 'add_export_import_interface']);
    }
    
    /**
     * Add export/import interface to admin footer
     */
    public function add_export_import_interface() {
        // Only show on course-related admin pages
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, ['toplevel_page_course-box-tables', 'course', 'edit-course'])) {
            return;
        }
        ?>
        <style>
        #cbm-export-import-bar {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #fff;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 9999;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        #cbm-export-import-bar.hidden {
            display: none;
        }
        #cbm-import-file {
            display: none;
        }
        .cbm-toggle-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #2271b1;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            z-index: 9998;
        }
        </style>
        
        <!-- Toggle Button -->
        <button class="cbm-toggle-button" onclick="document.getElementById('cbm-export-import-bar').classList.toggle('hidden'); this.style.display='none';">
            📦 Export/Import
        </button>
        
        <!-- Export/Import Bar -->
        <div id="cbm-export-import-bar" class="hidden">
            <strong>Course Data:</strong>
            
            <!-- Export Button -->
            <button class="button button-primary" id="cbm-export-btn">
                <span class="dashicons dashicons-download"></span>
                Export JSON
            </button>
            
            <!-- Import Button -->
            <input type="file" id="cbm-import-file" accept=".json">
            <button class="button" onclick="document.getElementById('cbm-import-file').click();">
                <span class="dashicons dashicons-upload"></span>
                Import JSON
            </button>
            
            <!-- Close Button -->
            <button class="button" onclick="document.getElementById('cbm-export-import-bar').classList.add('hidden'); document.querySelector('.cbm-toggle-button').style.display='block';">
                ✕
            </button>
            
            <div id="cbm-status-message" style="margin-left: 10px;"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Export handler
            $('#cbm-export-btn').on('click', function() {
                const $btn = $(this);
                const $status = $('#cbm-status-message');
                
                $btn.prop('disabled', true);
                $status.html('<span style="color: #666;">Generating export...</span>');
                
                $.post(ajaxurl, {
                    action: 'cbm_export_all_data',
                    nonce: '<?php echo wp_create_nonce('cbm_export'); ?>'
                }, function(response) {
                    if (response.success) {
                        // Create download link
                        const blob = new Blob([JSON.stringify(response.data, null, 2)], {type: 'application/json'});
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'course-data-export-' + new Date().toISOString().split('T')[0] + '.json';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);
                        
                        $status.html('<span style="color: green;">✓ Export completed!</span>');
                        setTimeout(() => $status.html(''), 3000);
                    } else {
                        $status.html('<span style="color: red;">Export failed: ' + response.data + '</span>');
                    }
                }).fail(function() {
                    $status.html('<span style="color: red;">Export failed!</span>');
                }).always(function() {
                    $btn.prop('disabled', false);
                });
            });
            
            // Import handler
            $('#cbm-import-file').on('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;
                
                const $status = $('#cbm-status-message');
                $status.html('<span style="color: #666;">Reading file...</span>');
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        const data = JSON.parse(e.target.result);
                        
                        if (confirm('Import this data? This will add/update courses, groups, and settings.')) {
                            $.post(ajaxurl, {
                                action: 'cbm_import_data',
                                import_data: JSON.stringify(data),
                                mode: 'merge',
                                nonce: '<?php echo wp_create_nonce('cbm_import'); ?>'
                            }, function(response) {
                                if (response.success) {
                                    $status.html('<span style="color: green;">✓ Import successful!</span>');
                                    setTimeout(() => {
                                        if (confirm('Import complete. Reload page to see changes?')) {
                                            location.reload();
                                        }
                                    }, 1000);
                                } else {
                                    $status.html('<span style="color: red;">Import failed: ' + response.data + '</span>');
                                }
                            }).fail(function() {
                                $status.html('<span style="color: red;">Import failed!</span>');
                            });
                        } else {
                            $status.html('');
                        }
                    } catch(err) {
                        $status.html('<span style="color: red;">Invalid JSON file!</span>');
                    }
                };
                reader.readAsText(file);
                
                // Reset input
                $(this).val('');
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Export all data
     */
    public function ajax_export_all_data() {
        check_ajax_referer('cbm_export', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        $export_data = [
            'version' => CBM_VERSION,
            'export_date' => current_time('mysql'),
            'site_url' => home_url(),
            'data' => [
                'groups' => $this->export_groups(),
                'courses' => $this->export_courses(),
                'products' => $this->export_products(),
                'settings' => $this->export_settings()
            ]
        ];
        
        wp_send_json_success($export_data);
    }
    
    /**
     * Export course groups
     */
    private function export_groups() {
        $groups = get_terms([
            'taxonomy' => 'course_group',
            'hide_empty' => false
        ]);
        
        $export_groups = [];
        
        foreach ($groups as $group) {
            $group_data = [
                'name' => $group->name,
                'slug' => $group->slug,
                'description' => $group->description,
                'meta' => []
            ];
            
            // Get group metadata
            $meta_keys = ['group_settings', 'default_instructor', 'default_price', 'enrollment_limit'];
            foreach ($meta_keys as $key) {
                $value = get_term_meta($group->term_id, $key, true);
                if ($value) {
                    $group_data['meta'][$key] = $value;
                }
            }
            
            $export_groups[] = $group_data;
        }
        
        return $export_groups;
    }
    
    /**
     * Export courses
     */
    private function export_courses() {
        $courses = get_posts([
            'post_type' => 'course',
            'post_status' => 'any',
            'posts_per_page' => -1
        ]);
        
        $export_courses = [];
        
        foreach ($courses as $course) {
            $course_data = [
                'title' => $course->post_title,
                'content' => $course->post_content,
                'excerpt' => $course->post_excerpt,
                'status' => $course->post_status,
                'slug' => $course->post_name,
                'meta' => []
            ];
            
            // Get all post meta
            $all_meta = get_post_meta($course->ID);
            foreach ($all_meta as $key => $values) {
                // Skip private meta
                if (strpos($key, '_') !== 0 || strpos($key, '_cbm') === 0) {
                    $course_data['meta'][$key] = maybe_unserialize($values[0]);
                }
            }
            
            // Get taxonomies
            $course_data['groups'] = wp_get_object_terms($course->ID, 'course_group', ['fields' => 'names']);
            
            // Get featured image
            $thumbnail_id = get_post_thumbnail_id($course->ID);
            if ($thumbnail_id) {
                $course_data['featured_image'] = wp_get_attachment_url($thumbnail_id);
            }
            
            $export_courses[] = $course_data;
        }
        
        return $export_courses;
    }
    
    /**
     * Export WooCommerce products linked to courses
     */
    private function export_products() {
        if (!function_exists('wc_get_products')) {
            return [];
        }
        
        $products = wc_get_products(['limit' => -1]);
        $export_products = [];
        
        foreach ($products as $product) {
            // Only export products linked to courses
            $linked_course = get_post_meta($product->get_id(), '_cbm_linked_stm_course', true) ?: 
                           get_post_meta($product->get_id(), '_cbm_course_id', true);
            
            if (!$linked_course) {
                continue;
            }
            
            $export_products[] = [
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'regular_price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price(),
                'description' => $product->get_description(),
                'linked_course' => $linked_course
            ];
        }
        
        return $export_products;
    }
    
    /**
     * Export plugin settings
     */
    private function export_settings() {
        $settings_keys = [
            'cbm_integration_mode',
            'cbm_auto_integrate_stm',
            'cbm_auto_create_hybrid',
            'cbm_bidirectional_sync',
            'cbm_sync_content_to_stm',
            'cbm_delete_linked_on_stm_delete',
            'cbm_override_shortcodes',
            'cbm_inject_position'
        ];
        
        $settings = [];
        foreach ($settings_keys as $key) {
            $value = get_option($key);
            if ($value !== false) {
                $settings[$key] = $value;
            }
        }
        
        return $settings;
    }
}

// Initialize only if not already initialized
if (!isset($GLOBALS['cbm_exporter_initialized'])) {
    new CourseExporter();
    $GLOBALS['cbm_exporter_initialized'] = true;
}