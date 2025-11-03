<?php
/**
 * Course Dashboard Manager - Constants
 *
 * Centralized constants for configuration values
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Default values
define('CBM_DEFAULT_STOCK', 20);
define('CBM_DEFAULT_BUY_PRICE', 749.99);
define('CBM_DEFAULT_ENROLL_PRICE', 1249.99);

// Query defaults
define('CBM_ORDERS_DATE_CUTOFF', '2020-01-01'); // Don't query orders before this date
define('CBM_TRANSIENT_EXPIRATION', 5 * MINUTE_IN_SECONDS); // Cache for 5 minutes

// Box states
define('CBM_BOX_STATE_BUY', 'buy-course');
define('CBM_BOX_STATE_ENROLL', 'enroll-course');
define('CBM_BOX_STATE_ENROLL_BUY', 'enroll-buy');
define('CBM_BOX_STATE_COUNTDOWN', 'countdown');
define('CBM_BOX_STATE_WAITLIST', 'waitlist');
define('CBM_BOX_STATE_SOLDOUT', 'soldout');

// Taxonomy and post types
define('CBM_TAXONOMY_COURSE_GROUP', 'course_group');
define('CBM_POST_TYPE_COURSE', 'course');
define('CBM_POST_TYPE_INSTRUCTOR', 'instructor');

// Capabilities
define('CBM_CAPABILITY_MANAGE', 'edit_posts');
define('CBM_CAPABILITY_DELETE', 'delete_posts');

// Nonce names - Each AJAX action should have its own nonce for better security
define('CBM_NONCE_CREATE_GROUP', 'cbm_create_group_nonce');
define('CBM_NONCE_DELETE_GROUP', 'delete_group_'); // Kept for backward compatibility with dynamic group IDs
define('CBM_NONCE_ASSIGN_COURSE', 'cbm_assign_course_nonce');
define('CBM_NONCE_SAVE_GROUP', 'cbm_save_group_nonce');
define('CBM_NONCE_SAVE_COURSE', 'cbm_save_course_nonce');
define('CBM_NONCE_SAVE_TABLE_ROW', 'cbm_save_table_row_nonce');
define('CBM_NONCE_DELETE_ROW', 'cbm_delete_row_nonce');
define('CBM_NONCE_REMOVE_COURSE', 'cbm_remove_course_nonce');
define('CBM_NONCE_DELETE_COURSE', 'cbm_delete_course_nonce');
define('CBM_NONCE_SAVE_INLINE_DATES', 'cbm_save_inline_dates_nonce');
define('CBM_NONCE_APPLY_GROUP_SETTINGS', 'cbm_apply_group_settings_nonce');
define('CBM_NONCE_POPUP', 'cbm_popup_nonce');

// AJAX actions
define('CBM_AJAX_CREATE_GROUP', 'create_new_course_group');
define('CBM_AJAX_DELETE_GROUP', 'delete_course_group');
define('CBM_AJAX_ASSIGN_COURSE', 'assign_course_to_group');
define('CBM_AJAX_SAVE_GROUP', 'save_group_settings');
define('CBM_AJAX_SAVE_COURSE', 'save_course_settings');
define('CBM_AJAX_SAVE_TABLE_ROW', 'save_table_row_data');
define('CBM_AJAX_DELETE_ROW', 'delete_table_row');
define('CBM_AJAX_ADD_TO_CART', 'cbm_add_to_cart');
