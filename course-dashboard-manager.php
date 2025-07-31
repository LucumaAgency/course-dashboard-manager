<?php
/*
 * Plugin Name: Course Box Manager
 * Description: A comprehensive plugin to manage and display selectable boxes for course post types with dashboard control, countdowns, start date selection, and WooCommerce integration.
 * Version: 1.5.0
 * Author: Carlos Murillo
 * Author URI: https://lucumaagency.com/
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('CBM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CBM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader for classes
spl_autoload_register(function ($class) {
    $prefix = 'CourseBoxManager\\';
    $base_dir = CBM_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Register course_group taxonomy
add_action('init', 'register_course_group_taxonomy');
function register_course_group_taxonomy() {
    register_taxonomy('course_group', ['stm-courses', 'product', 'course'], [
        'labels' => [
            'name' => __('Course Groups'),
            'singular_name' => __('Course Group'),
        ],
        'hierarchical' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_admin_column' => true,
        'rewrite' => ['slug' => 'course-group'],
    ]);
}

// Register instructor CPT
add_action('init', 'register_instructor_cpt');
function register_instructor_cpt() {
    register_post_type('instructor', [
        'labels' => [
            'name' => __('Instructors'),
            'singular_name' => __('Instructor'),
        ],
        'public' => true,
        'supports' => ['title', 'editor', 'custom-fields'],
    ]);
}

// Enable FunnelKit Cart for course post type
add_filter('fkcart_disabled_post_types', function ($post_types) {
    $post_types = array_filter($post_types, function ($i) {
        return $i !== 'course';
    });
    return $post_types;
});

// Add admin menu for dashboard
add_action('admin_menu', 'course_box_manager_menu');
function course_box_manager_menu() {
    add_menu_page(
        'Course Box Manager',
        'Course Boxes',
        'edit_posts', // Instructors (with edit_posts capability) can view
        'course-box-manager',
        'course_box_manager_page',
        'dashicons-list-view',
        20
    );
}

// Dashboard page content
function course_box_manager_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <input type="text" id="course-search" placeholder="Search...">
        <?php if (!isset($_GET['course_id']) && !isset($_GET['group_id'])) : ?>
            <!-- Main View: Course Groups Table -->
            <?php
            $groups = get_terms(['taxonomy' => 'course_group', 'hide_empty' => false]);
            ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Course Group</th>
                        <th>Number of Courses</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $group) :
                        $courses_in_group = get_posts([
                            'post_type' => 'stm-courses',
                            'posts_per_page' => -1,
                            'fields' => 'ids',
                            'tax_query' => [
                                [
                                    'taxonomy' => 'course_group',
                                    'field' => 'term_id',
                                    'terms' => $group->term_id,
                                ],
                            ],
                        ]);
                    ?>
                        <tr>
                            <td><a href="?page=course-box-manager&group_id=<?php echo esc_attr($group->term_id); ?>"><?php echo esc_html($group->name); ?></a></td>
                            <td><?php echo count($courses_in_group); ?></td>
                            <td>
                                <button class="button view-courses" data-group-id="<?php echo esc_attr($group->term_id); ?>">View Courses</button>
                                <button class="button delete-group" data-group-id="<?php echo esc_attr($group->term_id); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button class="button button-primary add-course-group" style="margin-top: 10px;">Add Course Group</button>
        <?php elseif (isset($_GET['group_id']) && !isset($_GET['course_id'])) : ?>
            <!-- Group View: Courses in Group -->
            <?php
            $group_id = intval($_GET['group_id']);
            $group = get_term($group_id, 'course_group');
            $courses = get_posts([
                'post_type' => 'stm-courses',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'tax_query' => [
                    [
                        'taxonomy' => 'course_group',
                        'field' => 'term_id',
                        'terms' => $group_id,
                    ],
                ],
            ]);
            ?>
            <h2>Course Group: <?php echo esc_html($group->name); ?></h2>
            <a href="?page=course-box-manager" class="button">Back to Groups</a>
            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th>Course</th>
                        <th>Instructors</th>
                        <th>Box State</th>
                        <th>Stock</th>
                        <th>Dates</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $course_id) :
                        $title = get_the_title($course_id);
                        $instructors = get_post_meta($course_id, 'course_instructors', true) ?: [];
                        $instructor_names = array_map(function($id) { return get_the_title($id); }, $instructors);
                        $box_state = get_post_meta($course_id, 'box_state', true) ?: 'enroll-course';
                        $webinar_stock = get_field('course_stock', $course_id) ?: 0;
                        $dates = get_field('course_dates', $course_id) ?: [];
                        $is_group_course = preg_match('/( - G\d+|\(G\d+\))$/', $title);
                    ?>
                        <tr>
                            <td><a href="?page=course-box-manager&course_id=<?php echo esc_attr($course_id); ?>&group_id=<?php echo esc_attr($group_id); ?>"><?php echo esc_html($title); ?></a></td>
                            <td><?php echo esc_html(implode(', ', $instructor_names)); ?></td>
                            <td><?php echo esc_html(ucfirst(str_replace('-', ' ', $box_state))); ?></td>
                            <td><?php echo $is_group_course ? esc_html($webinar_stock) : '-'; ?></td>
                            <td><?php echo $is_group_course && !empty($dates) ? esc_html(implode(', ', array_column($dates, 'date'))) : '-'; ?></td>
                            <td>
                                <button class="button button-primary edit-course-settings" data-course-id="<?php echo esc_attr($course_id); ?>">Edit</button>
                                <button class="button delete-course" data-course-id="<?php echo esc_attr($course_id); ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button class="button button-primary add-course" data-group-id="<?php echo esc_attr($group_id); ?>" style="margin-top: 10px;">Add Course to Group</button>
        <?php else : ?>
            <!-- Detail View: Course Settings -->
            <?php
            $course_id = intval($_GET['course_id']);
            $title = get_the_title($course_id);
            $instructors = get_post_meta($course_id, 'course_instructors', true) ?: [];
            $box_state = get_post_meta($course_id, 'box_state', true) ?: 'enroll-course';
            $webinar_stock = get_field('course_stock', $course_id) ?: 0;
            $dates = get_field('course_dates', $course_id) ?: [];
            $is_group_course = preg_match('/( - G\d+|\(G\d+\))$/', $title);
            $terms = wp_get_post_terms($course_id, 'course_group');
            $group_id = !empty($terms) ? $terms[0]->term_id : 0;
            $group_name = $group_id ? get_term($group_id, 'course_group')->name : 'None';
            $selling_page = get_posts([
                'post_type' => 'course',
                'posts_per_page' => 1,
                'tax_query' => [
                    [
                        'taxonomy' => 'course_group',
                        'field' => 'term_id',
                        'terms' => $group_id,
                    ],
                ],
            ]);
            $selling_page_id = !empty($selling_page) ? $selling_page[0]->ID : 0;
            $from_group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
            ?>
            <h2>Course: <?php echo esc_html($title); ?></h2>
            <?php if ($from_group_id) : ?>
                <a href="?page=course-box-manager&group_id=<?php echo esc_attr($from_group_id); ?>" class="button">Back to Group</a>
            <?php else : ?>
                <a href="?page=course-box-manager" class="button">Back to Groups</a>
            <?php endif; ?>
            <div style="margin-top: 20px;">
                <h3>Course Settings</h3>
                <table class="form-table">
                    <tr>
                        <th><label>Course Group</label></th>
                        <td>
                            <select id="course-group" data-course-id="<?php echo esc_attr($course_id); ?>">
                                <option value="0">None</option>
                                <?php
                                $groups = get_terms(['taxonomy' => 'course_group', 'hide_empty' => false]);
                                foreach ($groups as $group) {
                                    echo '<option value="' . esc_attr($group->term_id) . '"' . ($group_id == $group->term_id ? ' selected' : '') . '>' . esc_html($group->name) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Instructors</label></th>
                        <td>
                            <select class="instructor-select" data-course-id="<?php echo esc_attr($course_id); ?>" multiple>
                                <?php
                                $all_instructors = get_posts(['post_type' => 'instructor', 'posts_per_page' => -1]);
                                foreach ($all_instructors as $instructor) {
                                    echo '<option value="' . esc_attr($instructor->ID) . '"' . (in_array($instructor->ID, $instructors) ? ' selected' : '') . '>' . esc_html($instructor->post_title) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Box State</label></th>
                        <td>
                            <select class="box-state-select" data-course-id="<?php echo esc_attr($course_id); ?>">
                                <option value="enroll-course" <?php echo $box_state === 'enroll-course' ? 'selected' : ''; ?>>Enroll in the Live Course</option>
                                <option value="buy-course" <?php echo $box_state === 'buy-course' ? 'selected' : ''; ?>>Buy This Course</option>
                                <option value="waitlist" <?php echo $box_state === 'waitlist' ? 'selected' : ''; ?>>Waitlist</option>
                                <option value="soldout" <?php echo $box_state === 'soldout' ? 'selected' : ''; ?>>Sold Out</option>
                            </select>
                        </td>
                    </tr>
                    <?php if ($is_group_course) : ?>
                        <tr>
                            <th><label>Stock</label></th>
                            <td>
                                <input type="number" class="webinar-stock" data-course-id="<?php echo esc_attr($course_id); ?>" value="<?php echo esc_attr($webinar_stock); ?>" min="0">
                            </td>
                        </tr>
                        <tr>
                            <th><label>Dates</label></th>
                            <td>
                                <div class="date-list" data-course-id="<?php echo esc_attr($course_id); ?>">
                                    <?php foreach ($dates as $index => $date) : ?>
                                        <div>
                                            <input type="text" class="course-date" value="<?php echo esc_attr($date['date']); ?>" data-index="<?php echo esc_attr($index); ?>" placeholder="Enter date (e.g., 2025-08-01)">
                                            <button class="remove-date" data-index="<?php echo esc_attr($index); ?>">Remove</button>
                                        </div>
                                    <?php endforeach; ?>
                                    <button class="add-date">Add Date</button>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <th><label>Selling Page</label></th>
                        <td>
                            <select id="selling-page" data-course-id="<?php echo esc_attr($course_id); ?>">
                                <option value="0">None</option>
                                <?php
                                $selling_pages = get_posts(['post_type' => 'course', 'posts_per_page' => -1]);
                                foreach ($selling_pages as $page) {
                                    echo '<option value="' . esc_attr($page->ID) . '"' . ($selling_page_id == $page->ID ? ' selected' : '') . '>' . esc_html($page->post_title) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <button class="button button-primary save-course-settings" data-course-id="<?php echo esc_attr($course_id); ?>">Save Settings</button>
            </div>
        <?php endif; ?>

        <!-- Modal for Adding Course -->
        <div id="add-course-modal" class="modal" style="display:none;">
            <div class="modal-content">
                <span class="modal-close">×</span>
                <h2>Add Course</h2>
                <label>Course Title (include suffix, e.g., VOD, G1, (G1)):</label>
                <input type="text" id="course-title" placeholder="e.g., How to do AI - VOD or How to do AI (G1)">
                <?php if (!isset($_GET['group_id'])) : ?>
                <label>Course Group:</label>
                <select id="course-group">
                    <option value="0">None</option>
                    <?php
                    $groups = get_terms(['taxonomy' => 'course_group', 'hide_empty' => false]);
                    foreach ($groups as $group) {
                        echo '<option value="' . esc_attr($group->term_id) . '">' . esc_html($group->name) . '</option>';
                    }
                    ?>
                </select>
                <?php else : ?>
                <input type="hidden" id="course-group" value="<?php echo esc_attr($_GET['group_id']); ?>">
                <?php endif; ?>
                <label>Instructors:</label>
                <select id="course-instructors" multiple>
                    <?php
                    $all_instructors = get_posts(['post_type' => 'instructor', 'posts_per_page' => -1]);
                    foreach ($all_instructors as $instructor) {
                        echo '<option value="' . esc_attr($instructor->ID) . '">' . esc_html($instructor->post_title) . '</option>';
                    }
                    ?>
                </select>
                <button class="button button-primary" id="save-new-course">Create Course</button>
            </div>
        </div>

        <!-- Modal for Adding Course Group -->
        <div id="add-course-group-modal" class="modal" style="display:none;">
            <div class="modal-content">
                <span class="modal-close">×</span>
                <h2>Add Course Group</h2>
                <label>Group Name:</label>
                <input type="text" id="course-group-name" placeholder="e.g., How to do AI">
                <button class="button button-primary" id="save-new-course-group">Create Group</button>
            </div>
        </div>

        <style>
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.4);
            }
            .modal-content {
                background-color: #fff;
                margin: 15% auto;
                padding: 20px;
                border: 1px solid #888;
                width: 80%;
                max-width: 500px;
                border-radius: 5px;
            }
            .modal-close {
                color: #aaa;
                float: right;
                font-size: 28px;
                cursor: pointer;
            }
            .modal-close:hover {
                color: black;
            }
            .box-state-select, .instructor-select, #course-group, #selling-page {
                width: 200px;
                padding: 5px;
                font-size: 14px;
            }
            .webinar-stock {
                width: 100px;
                padding: 5px;
                font-size: 14px;
            }
            .date-list {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            .course-date {
                padding: 5px;
                font-size: 14px;
                width: 150px;
            }
            .remove-date, .add-date {
                padding: 5px 10px;
                font-size: 12px;
                border: none;
                border-radius: 3px;
                cursor: pointer;
            }
            .remove-date {
                background: #ff4444;
                color: white;
            }
            .remove-date:hover {
                background: #cc0000;
            }
            .add-date {
                background: #4CAF50;
                color: white;
            }
            .add-date:hover {
                background: #45a049;
            }
            #course-search, #course-group-name {
                margin-bottom: 10px;
                padding: 5px;
                width: 300px;
            }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const addCourseModal = document.getElementById('add-course-modal');
                const addCourseGroupModal = document.getElementById('add-course-group-modal');
                const closeButtons = document.getElementsByClassName('modal-close');

                // Open Add Course Modal
                document.querySelectorAll('.add-course').forEach(button => {
                    button.addEventListener('click', function() {
                        const groupId = this.getAttribute('data-group-id');
                        if (groupId) {
                            document.getElementById('course-group').value = groupId;
                        }
                        addCourseModal.style.display = 'block';
                    });
                });

                // Open Add Course Group Modal
                document.querySelectorAll('.add-course-group').forEach(button => {
                    button.addEventListener('click', function() {
                        addCourseGroupModal.style.display = 'block';
                    });
                });

                // Close Modals
                Array.from(closeButtons).forEach(button => {
                    button.addEventListener('click', function() {
                        addCourseModal.style.display = 'none';
                        addCourseGroupModal.style.display = 'none';
                    });
                });
                window.addEventListener('click', function(event) {
                    if (event.target === addCourseModal || event.target === addCourseGroupModal) {
                        addCourseModal.style.display = 'none';
                        addCourseGroupModal.style.display = 'none';
                    }
                });

                // Save New Course
                document.getElementById('save-new-course').addEventListener('click', function() {
                    const groupId = document.getElementById('course-group').value;
                    const title = document.getElementById('course-title').value;
                    const instructors = Array.from(document.getElementById('course-instructors').selectedOptions).map(option => option.value);
                    fetch(ajaxurl + '?action=create_new_course', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'group_id=' + groupId + '&title=' + encodeURIComponent(title) + '&instructors=' + encodeURIComponent(JSON.stringify(instructors)) + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Course created successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + data.data);
                        }
                    });
                });

                // Save New Course Group
                document.getElementById('save-new-course-group').addEventListener('click', function() {
                    const groupName = document.getElementById('course-group-name').value;
                    if (!groupName.trim()) {
                        alert('Please enter a group name.');
                        return;
                    }
                    fetch(ajaxurl + '?action=create_new_course_group', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'group_name=' + encodeURIComponent(groupName) + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Course group created successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + data.data);
                        }
                    });
                });

                // Save Course Settings
                document.querySelectorAll('.save-course-settings').forEach(button => {
                    button.addEventListener('click', function() {
                        const courseId = this.getAttribute('data-course-id');
                        const groupId = document.querySelector(`#course-group[data-course-id="${courseId}"]`).value;
                        const boxState = document.querySelector(`.box-state-select[data-course-id="${courseId}"]`).value;
                        const instructors = Array.from(document.querySelector(`.instructor-select[data-course-id="${courseId}"]`).selectedOptions).map(option => option.value);
                        const stock = document.querySelector(`.webinar-stock[data-course-id="${courseId}"]`)?.value || '';
                        const dates = Array.from(document.querySelectorAll(`.date-list[data-course-id="${courseId}"] .course-date`)).map(input => input.value).filter(date => date.trim() !== '');
                        const sellingPageId = document.querySelector(`#selling-page[data-course-id="${courseId}"]`).value;
                        fetch(ajaxurl + '?action=save_course_settings', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'course_id=' + courseId + '&group_id=' + groupId + '&box_state=' + boxState + '&instructors=' + encodeURIComponent(JSON.stringify(instructors)) + '&stock=' + stock + '&dates=' + encodeURIComponent(JSON.stringify(dates)) + '&selling_page_id=' + sellingPageId + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Settings saved successfully!');
                                location.href = '?page=course-box-manager';
                            } else {
                                alert('Error: ' + data.data);
                            }
                        });
                    });
                });

                // Delete Course
                document.querySelectorAll('.delete-course').forEach(button => {
                    button.addEventListener('click', function() {
                        if (!confirm('Are you sure you want to delete this course?')) return;
                        const courseId = this.getAttribute('data-course-id');
                        fetch(ajaxurl + '?action=delete_course', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'course_id=' + courseId + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Course deleted successfully!');
                                location.reload();
                            } else {
                                alert('Error: ' + data.data);
                            }
                        });
                    });
                });

                // Add/Remove Dates
                document.querySelectorAll('.date-list').forEach(container => {
                    const courseId = container.getAttribute('data-course-id');
                    const addDateButton = container.querySelector('.add-date');
                    addDateButton.addEventListener('click', function() {
                        const dateList = container;
                        const index = dateList.querySelectorAll('.course-date').length;
                        const wrapper = document.createElement('div');
                        const newDateInput = document.createElement('input');
                        newDateInput.type = 'text';
                        newDateInput.className = 'course-date';
                        newDateInput.setAttribute('data-index', index);
                        newDateInput.placeholder = 'Enter date (e.g., 2025-08-01)';
                        const removeButton = document.createElement('button');
                        removeButton.className = 'remove-date';
                        removeButton.setAttribute('data-index', index);
                        removeButton.textContent = 'Remove';
                        removeButton.addEventListener('click', function() {
                            wrapper.remove();
                        });
                        wrapper.appendChild(newDateInput);
                        wrapper.appendChild(removeButton);
                        dateList.insertBefore(wrapper, addDateButton);
                    });

                    container.querySelectorAll('.remove-date').forEach(button => {
                        button.addEventListener('click', function() {
                            button.parentElement.remove();
                        });
                    });
                });

                // Search functionality
                document.getElementById('course-search').addEventListener('input', function() {
                    const search = this.value.toLowerCase();
                    document.querySelectorAll('.wp-list-table tbody tr').forEach(row => {
                        const searchText = row.cells[0].textContent.toLowerCase();
                        row.style.display = searchText.includes(search) ? '' : 'none';
                    });
                });

                // View Courses button
                document.querySelectorAll('.view-courses').forEach(button => {
                    button.addEventListener('click', function() {
                        const groupId = this.getAttribute('data-group-id');
                        window.location.href = '?page=course-box-manager&group_id=' + groupId;
                    });
                });

                // Delete Group button
                document.querySelectorAll('.delete-group').forEach(button => {
                    button.addEventListener('click', function() {
                        if (!confirm('Are you sure you want to delete this course group? This will not delete the courses.')) return;
                        const groupId = this.getAttribute('data-group-id');
                        fetch(ajaxurl + '?action=delete_course_group', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'group_id=' + groupId + '&nonce=' + '<?php echo wp_create_nonce('course_box_nonce'); ?>'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Course group deleted successfully!');
                                location.reload();
                            } else {
                                alert('Error: ' + data.data);
                            }
                        });
                    });
                });

                // Edit Course Settings
                document.querySelectorAll('.edit-course-settings').forEach(button => {
                    button.addEventListener('click', function() {
                        const courseId = this.getAttribute('data-course-id');
                        const urlParams = new URLSearchParams(window.location.search);
                        const groupId = urlParams.get('group_id');
                        let redirectUrl = '?page=course-box-manager&course_id=' + courseId;
                        if (groupId) {
                            redirectUrl += '&group_id=' + groupId;
                        }
                        window.location.href = redirectUrl;
                    });
                });
            });
        </script>
    </div>
    <?php
}

// AJAX Handler for Creating Course Group
add_action('wp_ajax_create_new_course_group', 'create_new_course_group');
function create_new_course_group() {
    check_ajax_referer('course_box_nonce', 'nonce');
    $group_name = sanitize_text_field($_POST['group_name']);
    $term = wp_insert_term($group_name, 'course_group');
    if (!is_wp_error($term)) {
        wp_send_json_success();
    } else {
        wp_send_json_error($term->get_error_message());
    }
}

// AJAX Handler for Deleting Course Group
add_action('wp_ajax_delete_course_group', 'delete_course_group');
function delete_course_group() {
    check_ajax_referer('course_box_nonce', 'nonce');
    $group_id = intval($_POST['group_id']);
    
    // Remove the term from all courses first
    $courses = get_posts([
        'post_type' => 'stm-courses',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => [
            [
                'taxonomy' => 'course_group',
                'field' => 'term_id',
                'terms' => $group_id,
            ],
        ],
    ]);
    
    foreach ($courses as $course_id) {
        wp_remove_object_terms($course_id, $group_id, 'course_group');
    }
    
    // Delete the term
    $result = wp_delete_term($group_id, 'course_group');
    if (!is_wp_error($result)) {
        wp_send_json_success();
    } else {
        wp_send_json_error($result->get_error_message());
    }
}

// AJAX Handlers
add_action('wp_ajax_create_new_course', 'create_new_course');
function create_new_course() {
    check_ajax_referer('course_box_nonce', 'nonce');
    $group_id = intval($_POST['group_id']);
    $title = sanitize_text_field($_POST['title']);
    $instructors = json_decode(stripslashes($_POST['instructors']), true);

    $course_id = wp_insert_post([
        'post_title' => $title,
        'post_type' => 'stm-courses',
        'post_status' => 'publish',
    ]);

    if ($course_id) {
        if ($group_id) {
            wp_set_post_terms($course_id, [$group_id], 'course_group');
        }
        update_post_meta($course_id, 'course_instructors', $instructors);

        // Create WooCommerce product
        $product = new WC_Product_Simple();
        $product->set_name($title);
        $product->set_status('publish');
        $product->set_virtual(true);
        $product->set_price(get_field('course_price', $course_id) ?: 749.99);
        $product_id = $product->save();
        if ($group_id) {
            wp_set_post_terms($product_id, [$group_id], 'course_group');
        }
        update_post_meta($course_id, 'linked_product_id', $product_id);

        // Update instructor meta
        foreach ($instructors as $instructor_id) {
            $courses = get_post_meta($instructor_id, 'instructor_courses', true) ?: [];
            if (!in_array($course_id, $courses)) {
                $courses[] = $course_id;
                update_post_meta($instructor_id, 'instructor_courses', $courses);
            }
        }

        wp_send_json_success();
    } else {
        wp_send_json_error('Could not create course.');
    }
}

add_action('wp_ajax_save_course_settings', 'save_course_settings');
function save_course_settings() {
    check_ajax_referer('course_box_nonce', 'nonce');
    $course_id = intval($_POST['course_id']);
    $group_id = intval($_POST['group_id']);
    $box_state = sanitize_text_field($_POST['box_state']);
    $instructors = json_decode(stripslashes($_POST['instructors']), true);
    $stock = sanitize_text_field($_POST['stock']);
    $dates = json_decode(stripslashes($_POST['dates']), true);
    $selling_page_id = intval($_POST['selling_page_id']);

    // Update course group
    if ($group_id) {
        wp_set_post_terms($course_id, [$group_id], 'course_group');
    } else {
        wp_set_post_terms($course_id, [], 'course_group');
    }

    // Update selling page
    $current_terms = wp_get_post_terms($course_id, 'course_group');
    $current_group_id = !empty($current_terms) ? $current_terms[0]->term_id : 0;
    if ($current_group_id) {
        $existing_page = get_posts([
            'post_type' => 'course',
            'posts_per_page' => 1,
            'tax_query' => [
                [
                    'taxonomy' => 'course_group',
                    'field' => 'term_id',
                    'terms' => $current_group_id,
                ],
            ],
        ]);
        if ($existing_page && $existing_page[0]->ID != $selling_page_id) {
            wp_set_post_terms($existing_page[0]->ID, [], 'course_group');
        }
        if ($selling_page_id) {
            wp_set_post_terms($selling_page_id, [$current_group_id], 'course_group');
        }
    }

    update_post_meta($course_id, 'box_state', $box_state);
    update_post_meta($course_id, 'course_instructors', $instructors);
    $product_id = get_post_meta($course_id, 'linked_product_id', true);
    if ($product_id && $stock !== '') {
        update_post_meta($product_id, '_stock', $stock);
        update_post_meta($product_id, '_manage_stock', 'yes');
        if ($group_id) {
            wp_set_post_terms($product_id, [$group_id], 'course_group');
        } else {
            wp_set_post_terms($product_id, [], 'course_group');
        }
    }
    if ($dates) {
        update_field('course_dates', array_map(function($date) { return ['date' => $date]; }, $dates), $course_id);
    } else {
        delete_field('course_dates', $course_id);
    }

    // Update instructor meta
    foreach ($instructors as $instructor_id) {
        $courses = get_post_meta($instructor_id, 'instructor_courses', true) ?: [];
        if (!in_array($course_id, $courses)) {
            $courses[] = $course_id;
            update_post_meta($instructor_id, 'instructor_courses', $courses);
        }
    }

    wp_send_json_success();
}

add_action('wp_ajax_delete_course', 'delete_course');
function delete_course() {
    check_ajax_referer('course_box_nonce', 'nonce');
    $course_id = intval($_POST['course_id']);
    $product_id = get_post_meta($course_id, 'linked_product_id', true);
    if ($product_id) {
        wp_delete_post($product_id, true);
    }
    wp_delete_post($course_id, true);
    wp_send_json_success();
}

// Shortcode to render boxes
function course_box_manager_shortcode() {
    global $post;
    $post_id = $post ? $post->ID : 0;
    $terms = wp_get_post_terms($post_id, 'course_group');
    $group_id = !empty($terms) ? $terms[0]->term_id : 0;
    
    return CourseBoxManager\BoxRenderer::render_boxes_for_group($group_id);
}

// Add start date to cart item data
add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id, $variation_id) {
    if (isset($_POST['start_date']) && !empty($_POST['start_date'])) {
        $cart_item_data['start_date'] = sanitize_text_field($_POST['start_date']);
    }
    return $cart_item_data;
}, 10, 3);

// Save start date to order item meta
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values) {
    if (isset($values['start_date']) && !empty($values['start_date'])) {
        $item->add_meta_data('Start Date', $values['start_date'], true);
    }
}, 10, 3);

// Display start date in admin order details
add_action('woocommerce_order_item_meta_end', function ($item_id, $item, $order) {
    $start_date = $item->get_meta('Start Date');
    if ($start_date) {
        echo '<p><strong>Start Date:</strong> ' . esc_html($start_date) . '</p>';
    }
}, 10, 3);

// Add start date to customer order email
add_action('woocommerce_email_order_meta', function ($order) {
    foreach ($order->get_items() as $item_id => $item) {
        $start_date = $item->get_meta('Start Date');
        if ($start_date) {
            echo '<p><strong>Start Date:</strong> ' . esc_html($start_date) . '</p>';
        }
    }
}, 10, 1);

add_shortcode('course_box_manager', 'course_box_manager_shortcode');
add_filter('the_content', 'inject_course_box_manager');
function inject_course_box_manager($content) {
    if (is_singular('course')) {
        $output = do_shortcode('[course_box_manager]');
        return $content . $output;
    }
    return $content;
}

// Sync course creation with product only (no selling page)
add_action('save_post_stm-courses', 'sync_course_to_product_and_page', 10, 3);
function sync_course_to_product_and_page($post_id, $post, $update) {
    if (wp_is_post_revision($post_id)) return;

    $terms = wp_get_post_terms($post_id, 'course_group');
    $group_id = !empty($terms) ? $terms[0]->term_id : 0;

    // Create WooCommerce product if not exists
    $product_id = get_post_meta($post_id, 'linked_product_id', true);
    if (!$product_id) {
        $product = new WC_Product_Simple();
        $product->set_name($post->post_title);
        $product->set_status('publish');
        $product->set_virtual(true);
        $product->set_price(get_field('course_price', $post_id) ?: 749.99);
        $product_id = $product->save();
        if ($group_id) {
            wp_set_post_terms($product_id, [$group_id], 'course_group');
        }
        update_post_meta($post_id, 'linked_product_id', $product_id);
    }
}

// Sync instructors bidirectionally
add_action('acf/save_post', 'sync_course_instructors', 20);
function sync_course_instructors($post_id) {
    if (get_post_type($post_id) !== 'stm-courses') return;
    $instructors = get_field('course_instructors', $post_id) ?: [];
    update_post_meta($post_id, 'course_instructors', $instructors);

    // Clear existing instructor courses
    $all_instructors = get_posts(['post_type' => 'instructor', 'posts_per_page' => -1, 'fields' => 'ids']);
    foreach ($all_instructors as $instructor_id) {
        $courses = get_post_meta($instructor_id, 'instructor_courses', true) ?: [];
        $courses = array_filter($courses, function($id) use ($post_id) { return $id != $post_id; });
        update_post_meta($instructor_id, 'instructor_courses', $courses);
    }

    // Update instructor meta
    foreach ($instructors as $instructor_id) {
        $courses = get_post_meta($instructor_id, 'instructor_courses', true) ?: [];
        if (!in_array($post_id, $courses)) {
            $courses[] = $post_id;
            update_post_meta($instructor_id, 'instructor_courses', $courses);
        }
    }
}
?>
