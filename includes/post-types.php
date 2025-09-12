<?php
/**
 * Course Dashboard Manager - Post Types & Taxonomies
 * 
 * Registers custom post types and taxonomies
 */

namespace CourseBoxManager;

// Register Course post type
add_action('init', __NAMESPACE__ . '\\register_course_post_type');
function register_course_post_type() {
    $labels = array(
        'name' => 'Courses',
        'singular_name' => 'Course',
        'menu_name' => 'Courses',
        'add_new' => 'Add New',
        'add_new_item' => 'Add New Course',
        'edit_item' => 'Edit Course',
        'new_item' => 'New Course',
        'view_item' => 'View Course',
        'search_items' => 'Search Courses',
        'not_found' => 'No courses found',
        'not_found_in_trash' => 'No courses found in Trash',
    );
    
    $args = array(
        'labels' => $labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'course'),
        'capability_type' => 'post',
        'has_archive' => true,
        'hierarchical' => false,
        'menu_position' => null,
        'supports' => array('title', 'editor', 'thumbnail'),
        'menu_icon' => 'dashicons-welcome-learn-more',
    );
    
    register_post_type('course', $args);
}

// Register Course Group taxonomy
add_action('init', __NAMESPACE__ . '\\register_course_group_taxonomy');
function register_course_group_taxonomy() {
    register_taxonomy('course_group', 'course', [
        'labels' => [
            'name' => 'Course Groups',
            'singular_name' => 'Course Group',
            'menu_name' => 'Groups',
        ],
        'hierarchical' => true,
        'public' => true,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => ['slug' => 'course-group'],
    ]);
}

// Register Instructor post type
add_action('init', __NAMESPACE__ . '\\register_instructor_cpt');
function register_instructor_cpt() {
    register_post_type('instructor', [
        'labels' => [
            'name' => 'Instructors',
            'singular_name' => 'Instructor',
        ],
        'public' => true,
        'has_archive' => false,
        'show_in_menu' => false,
        'supports' => ['title', 'editor', 'thumbnail'],
    ]);
}

// Removed FunnelKit filter - no longer needed