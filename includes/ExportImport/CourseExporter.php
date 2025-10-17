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

        // Get all course groups
        $groups = get_terms([
            'taxonomy' => 'course_group',
            'hide_empty' => false
        ]);
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
            flex-wrap: wrap;
            max-width: 600px;
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
        #cbm-group-selector {
            min-width: 200px;
        }
        </style>

        <!-- Toggle Button -->
        <button class="cbm-toggle-button" onclick="document.getElementById('cbm-export-import-bar').classList.toggle('hidden'); this.style.display='none';">
            📦 Export/Import
        </button>

        <!-- Export/Import Bar -->
        <div id="cbm-export-import-bar" class="hidden">
            <strong>Export Group:</strong>

            <!-- Group Selector -->
            <select id="cbm-group-selector" class="regular-text">
                <option value="">-- Select Group --</option>
                <?php foreach ($groups as $group): ?>
                    <option value="<?php echo esc_attr($group->term_id); ?>">
                        <?php echo esc_html($group->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

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

            <div id="cbm-status-message" style="margin-left: 10px; width: 100%;"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Export handler
            $('#cbm-export-btn').on('click', function() {
                const $btn = $(this);
                const $status = $('#cbm-status-message');
                const groupId = $('#cbm-group-selector').val();

                if (!groupId) {
                    $status.html('<span style="color: red;">Please select a group to export</span>');
                    setTimeout(() => $status.html(''), 3000);
                    return;
                }

                $btn.prop('disabled', true);
                $status.html('<span style="color: #666;">Generating export...</span>');

                $.post(ajaxurl, {
                    action: 'cbm_export_all_data',
                    group_id: groupId,
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
     * AJAX: Export group table data
     */
    public function ajax_export_all_data() {
        check_ajax_referer('cbm_export', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;

        if (!$group_id) {
            wp_send_json_error('No group selected');
        }

        // Get group info
        $group = get_term($group_id, 'course_group');
        if (is_wp_error($group)) {
            wp_send_json_error('Invalid group');
        }

        // Export group table data
        $export_data = $this->export_group_table_data($group_id, $group);

        wp_send_json_success($export_data);
    }

    /**
     * Export complete group table data
     */
    private function export_group_table_data($group_id, $group) {
        // Get all courses in this group
        $courses = get_posts([
            'post_type' => 'course',
            'posts_per_page' => -1,
            'tax_query' => [
                [
                    'taxonomy' => 'course_group',
                    'field' => 'term_id',
                    'terms' => $group_id,
                ],
            ],
        ]);

        // Get first course for group settings
        $first_course_id = !empty($courses) ? $courses[0]->ID : 0;
        $box_state = $first_course_id ? get_post_meta($first_course_id, 'box_state', true) : 'enroll-course';
        $instructors = $first_course_id ? get_post_meta($first_course_id, 'course_instructors', true) : [];
        $instructor_id = !empty($instructors) ? $instructors[0] : '';

        // Get selling page
        $selling_page_id = 0;
        $selling_courses = get_posts([
            'post_type' => 'course',
            'posts_per_page' => 1,
            'meta_key' => 'is_selling_page',
            'meta_value' => '1',
            'tax_query' => [
                [
                    'taxonomy' => 'course_group',
                    'field' => 'term_id',
                    'terms' => $group_id,
                ],
            ],
        ]);
        if (!empty($selling_courses)) {
            $selling_page_id = $selling_courses[0]->ID;
        }

        // Prepare export structure
        $export_data = [
            'version' => CBM_VERSION,
            'export_date' => current_time('mysql'),
            'group' => [
                'name' => $group->name,
                'slug' => $group->slug,
                'description' => $group->description,
            ],
            'group_settings' => [
                'box_state' => $box_state,
                'instructor_id' => $instructor_id,
                'selling_page_id' => $selling_page_id,
            ],
            'course_ids' => array_map(function($course) {
                return $course->ID;
            }, $courses),
            'table_data' => []
        ];

        // Export table data based on box_state
        if ($box_state === 'enroll-buy') {
            // Buy course configuration
            $buy_product_id = $first_course_id ? get_post_meta($first_course_id, 'buy_product_id', true) : '';
            $buy_button_text = $first_course_id ? get_post_meta($first_course_id, 'buy_button_text', true) : 'Buy Course';

            $export_data['table_data']['buy'] = [
                'product_id' => $buy_product_id,
                'button_text' => $buy_button_text,
            ];

            // Add product prices
            if ($buy_product_id && function_exists('wc_get_product')) {
                $product = wc_get_product($buy_product_id);
                if ($product) {
                    $export_data['table_data']['buy']['regular_price'] = $product->get_regular_price();
                    $export_data['table_data']['buy']['sale_price'] = $product->get_sale_price();
                }
            }

            // Enroll course dates
            $enroll_product_id = $first_course_id ? get_post_meta($first_course_id, 'enroll_product_id', true) : '';
            $dates = $first_course_id ? (function_exists('get_field') ? get_field('course_dates', $first_course_id) : get_post_meta($first_course_id, 'course_dates', true)) : [];

            $export_data['table_data']['enroll'] = [
                'product_id' => $enroll_product_id,
                'dates' => $this->format_dates_for_export($dates ?: []),
            ];
        } else {
            // Other states (enroll-course, soldout, countdown, etc.)
            foreach ($courses as $course) {
                $product_id = get_post_meta($course->ID, 'linked_product_id', true);
                $stm_course_id = get_post_meta($course->ID, 'related_stm_course_id', true);
                $dates = function_exists('get_field') ? get_field('course_dates', $course->ID) : get_post_meta($course->ID, 'course_dates', true);

                if ($dates && is_array($dates)) {
                    foreach ($dates as $date_info) {
                        $row_data = [
                            'date' => $date_info['date'] ?? '',
                            'product_id' => $date_info['product_id'] ?? $product_id,
                            'stm_course_id' => $date_info['stm_course_id'] ?? $stm_course_id,
                            'stock' => $date_info['stock'] ?? 20,
                            'button_text' => $date_info['button_text'] ?? 'Enroll Now',
                        ];

                        // Add product prices
                        $row_product_id = $row_data['product_id'];
                        if ($row_product_id && function_exists('wc_get_product')) {
                            $product = wc_get_product($row_product_id);
                            if ($product) {
                                $row_data['regular_price'] = $product->get_regular_price();
                                $row_data['sale_price'] = $product->get_sale_price();
                            }
                        }

                        $export_data['table_data'][] = $row_data;
                    }
                }
            }
        }

        return $export_data;
    }

    /**
     * Format dates array for export
     */
    private function format_dates_for_export($dates) {
        if (!is_array($dates)) {
            return [];
        }

        $formatted_dates = [];
        foreach ($dates as $date_info) {
            if (is_array($date_info)) {
                $formatted_date = [
                    'date' => $date_info['date'] ?? '',
                    'product_id' => $date_info['product_id'] ?? '',
                    'stm_course_id' => $date_info['stm_course_id'] ?? '',
                    'stock' => $date_info['stock'] ?? 20,
                    'button_text' => $date_info['button_text'] ?? 'Enroll Now',
                ];

                // Add product prices
                $product_id = $formatted_date['product_id'];
                if ($product_id && function_exists('wc_get_product')) {
                    $product = wc_get_product($product_id);
                    if ($product) {
                        $formatted_date['regular_price'] = $product->get_regular_price();
                        $formatted_date['sale_price'] = $product->get_sale_price();
                    }
                }

                $formatted_dates[] = $formatted_date;
            }
        }

        return $formatted_dates;
    }
}

// Initialize only if not already initialized
if (!isset($GLOBALS['cbm_exporter_initialized'])) {
    new CourseExporter();
    $GLOBALS['cbm_exporter_initialized'] = true;
}