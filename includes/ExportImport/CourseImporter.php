<?php
/**
 * Course Importer - Imports Course Tables data from JSON
 * 
 * Simplified version that works with the new button-based interface
 */

namespace CourseBoxManager\ExportImport;

class CourseImporter {
    
    private static $instance = null;
    private $import_log = [];
    private $group_map = [];
    private $course_map = [];
    private $product_map = [];
    
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
        
        // AJAX handler for import
        add_action('wp_ajax_cbm_import_data', [$this, 'ajax_import_data']);
    }
    
    /**
     * AJAX: Import data
     */
    public function ajax_import_data() {
        check_ajax_referer('cbm_import', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }
        
        // Get import data
        $import_data = json_decode(stripslashes($_POST['import_data']), true);
        
        if (!$import_data || !isset($import_data['data'])) {
            wp_send_json_error('Invalid import data');
        }
        
        $mode = sanitize_text_field($_POST['mode'] ?? 'merge');
        
        $this->import_log = [];
        $results = [
            'groups' => 0,
            'courses' => 0,
            'products' => 0,
            'settings' => 0,
            'errors' => []
        ];
        
        // Import in order
        try {
            // 1. Import settings
            if (isset($import_data['data']['settings'])) {
                $results['settings'] = $this->import_settings($import_data['data']['settings'], $mode);
            }
            
            // 2. Import groups
            if (isset($import_data['data']['groups'])) {
                $results['groups'] = $this->import_groups($import_data['data']['groups'], $mode);
            }
            
            // 3. Import products
            if (isset($import_data['data']['products'])) {
                $results['products'] = $this->import_products($import_data['data']['products'], $mode);
            }
            
            // 4. Import courses
            if (isset($import_data['data']['courses'])) {
                $results['courses'] = $this->import_courses($import_data['data']['courses'], $mode);
            }
            
        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
        }
        
        wp_send_json_success([
            'imported' => $results,
            'log' => $this->import_log
        ]);
    }
    
    /**
     * Import settings
     */
    private function import_settings($settings, $mode) {
        $imported = 0;
        
        foreach ($settings as $key => $value) {
            if ($mode === 'replace' || !get_option($key)) {
                update_option($key, $value);
                $imported++;
                $this->import_log[] = "Setting '$key' imported";
            }
        }
        
        return $imported;
    }
    
    /**
     * Import groups
     */
    private function import_groups($groups, $mode) {
        $imported = 0;
        
        foreach ($groups as $group_data) {
            $existing_term = get_term_by('name', $group_data['name'], 'course_group');
            
            if ($existing_term) {
                if ($mode === 'skip') {
                    $this->group_map[$group_data['name']] = $existing_term->term_id;
                    continue;
                }
                
                // Update existing
                wp_update_term($existing_term->term_id, 'course_group', [
                    'description' => $group_data['description']
                ]);
                $term_id = $existing_term->term_id;
            } else {
                // Create new
                $result = wp_insert_term($group_data['name'], 'course_group', [
                    'description' => $group_data['description'],
                    'slug' => $group_data['slug']
                ]);
                
                if (!is_wp_error($result)) {
                    $term_id = $result['term_id'];
                } else {
                    $this->import_log[] = "Error creating group '{$group_data['name']}': " . $result->get_error_message();
                    continue;
                }
            }
            
            // Store mapping
            $this->group_map[$group_data['name']] = $term_id;
            
            // Import metadata
            if (isset($group_data['meta']) && is_array($group_data['meta'])) {
                foreach ($group_data['meta'] as $meta_key => $meta_value) {
                    update_term_meta($term_id, $meta_key, $meta_value);
                }
            }
            
            $imported++;
            $this->import_log[] = "Group '{$group_data['name']}' imported";
        }
        
        return $imported;
    }
    
    /**
     * Import products
     */
    private function import_products($products, $mode) {
        if (!function_exists('wc_get_products')) {
            $this->import_log[] = "WooCommerce not active, skipping products";
            return 0;
        }
        
        $imported = 0;
        
        foreach ($products as $product_data) {
            $existing_product = null;
            
            // Try to find by SKU
            if (!empty($product_data['sku'])) {
                $existing_product = wc_get_product_id_by_sku($product_data['sku']);
            }
            
            // Try to find by name if no SKU match
            if (!$existing_product) {
                $existing_products = wc_get_products([
                    'name' => $product_data['name'],
                    'limit' => 1
                ]);
                
                if (!empty($existing_products)) {
                    $existing_product = $existing_products[0]->get_id();
                }
            }
            
            if ($existing_product) {
                $this->product_map[$product_data['name']] = $existing_product;
                
                if ($mode === 'merge' || $mode === 'replace') {
                    // Update existing product
                    $product = wc_get_product($existing_product);
                    if ($product) {
                        $product->set_regular_price($product_data['regular_price']);
                        if ($product_data['sale_price']) {
                            $product->set_sale_price($product_data['sale_price']);
                        }
                        $product->save();
                    }
                }
            } elseif ($mode !== 'skip') {
                // Create new product
                $product = new \WC_Product_Simple();
                $product->set_name($product_data['name']);
                if ($product_data['sku']) {
                    $product->set_sku($product_data['sku']);
                }
                $product->set_regular_price($product_data['regular_price']);
                if ($product_data['sale_price']) {
                    $product->set_sale_price($product_data['sale_price']);
                }
                $product->set_description($product_data['description']);
                
                $product_id = $product->save();
                
                if ($product_id) {
                    $this->product_map[$product_data['name']] = $product_id;
                    $imported++;
                    $this->import_log[] = "Product '{$product_data['name']}' created";
                }
            }
        }
        
        return $imported;
    }
    
    /**
     * Import courses
     */
    private function import_courses($courses, $mode) {
        $imported = 0;
        
        foreach ($courses as $course_data) {
            $existing_course = get_page_by_title($course_data['title'], OBJECT, 'course');
            
            if ($existing_course) {
                if ($mode === 'skip') {
                    $this->course_map[$course_data['title']] = $existing_course->ID;
                    continue;
                }
                
                // Update existing
                $course_id = $existing_course->ID;
                wp_update_post([
                    'ID' => $course_id,
                    'post_content' => $course_data['content'],
                    'post_excerpt' => $course_data['excerpt'],
                    'post_status' => $course_data['status']
                ]);
            } else {
                // Create new
                $course_id = wp_insert_post([
                    'post_title' => $course_data['title'],
                    'post_content' => $course_data['content'],
                    'post_excerpt' => $course_data['excerpt'],
                    'post_status' => $course_data['status'],
                    'post_name' => $course_data['slug'],
                    'post_type' => 'course'
                ]);
                
                if (is_wp_error($course_id)) {
                    $this->import_log[] = "Error creating course '{$course_data['title']}'";
                    continue;
                }
            }
            
            // Store mapping
            $this->course_map[$course_data['title']] = $course_id;
            
            // Import metadata
            if (isset($course_data['meta']) && is_array($course_data['meta'])) {
                foreach ($course_data['meta'] as $meta_key => $meta_value) {
                    update_post_meta($course_id, $meta_key, $meta_value);
                }
            }
            
            // Assign to groups
            if (isset($course_data['groups']) && is_array($course_data['groups'])) {
                $group_ids = [];
                foreach ($course_data['groups'] as $group_name) {
                    if (isset($this->group_map[$group_name])) {
                        $group_ids[] = $this->group_map[$group_name];
                    }
                }
                
                if (!empty($group_ids)) {
                    wp_set_object_terms($course_id, $group_ids, 'course_group');
                }
            }
            
            // Import featured image
            if (isset($course_data['featured_image'])) {
                $this->import_featured_image($course_id, $course_data['featured_image']);
            }
            
            $imported++;
            $this->import_log[] = "Course '{$course_data['title']}' imported";
        }
        
        return $imported;
    }
    
    /**
     * Import featured image
     */
    private function import_featured_image($post_id, $image_url) {
        if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
            return;
        }
        
        // Download image
        $tmp = download_url($image_url);
        
        if (is_wp_error($tmp)) {
            return;
        }
        
        $file_array = [
            'name' => basename($image_url),
            'tmp_name' => $tmp
        ];
        
        // Upload
        $attachment_id = media_handle_sideload($file_array, $post_id);
        
        if (!is_wp_error($attachment_id)) {
            set_post_thumbnail($post_id, $attachment_id);
        }
        
        @unlink($tmp);
    }
}

// Initialize only if not already initialized
if (!isset($GLOBALS['cbm_importer_initialized'])) {
    new CourseImporter();
    $GLOBALS['cbm_importer_initialized'] = true;
}