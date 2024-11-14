<?php
/*
Plugin Name: Polaris Post Grid
Description: Display posts in a grid or list.
Version: 1.4
Author: Casper Molhoek
*/

add_action('admin_menu', 'cpg_add_admin_menu');
add_shortcode('category_post_grid', 'cpg_register_shortcode');
add_action('admin_enqueue_scripts', 'cpg_enqueue_admin_scripts');
add_action('add_meta_boxes', 'cpg_add_icon_meta_box');
add_action('save_post', 'cpg_save_post_icon_meta');
add_action('quick_edit_custom_box', 'cpg_quick_edit_icon_box', 10, 2);
add_action('save_post', 'cpg_save_quick_edit_icon_meta');

// Add custom CSS option page
function cpg_add_admin_menu() {
    add_menu_page(
        'EWTN Post Grids',
        'EWTN Post Grids',
        'manage_options',
        'cpg_settings',
        'cpg_shortcode_generator_page',
        'dashicons-grid-view'
    );

    add_submenu_page(
        'cpg_settings',
        'Image Library',
        'Image Library',
        'manage_options',
        'cpg_image_library',
        'cpg_image_library_page'
    );

    add_submenu_page(
        'cpg_settings',
        'Custom CSS',
        'Custom CSS',
        'manage_options',
        'cpg_custom_css',
        'cpg_custom_css_page'
    );
}

function cpg_shortcode_generator_page() {
    ?>
    <div class="wrap">
        <h1>Shortcode Generator</h1>
        <p>Use this form to generate a shortcode for a custom post grid or list.</p>
        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Category</th>
                    <td>
                        <select name="cpg_category">
                            <option value="">Select a Category (optional)</option>
                            <option value="current">Current Category or Tag (for category/tag pages)</option>
                            <?php 
                            $categories = get_categories();
                            foreach ($categories as $category) {
                                echo '<option value="' . esc_attr($category->slug) . '">' . esc_html($category->name) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Tag</th>
                    <td>
                        <select name="cpg_tag">
                            <option value="">Select a Tag (optional)</option>
                            <?php 
                            $tags = get_tags();
                            foreach ($tags as $tag) {
                                echo '<option value="' . esc_attr($tag->slug) . '">' . esc_html($tag->name) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Posts Per Line</th>
                    <td><input type="number" name="cpg_posts_per_line" value="5" /></td>
                </tr>
                <tr valign="top" id="cpg_number_of_lines_row">
                    <th scope="row">Number of Lines</th>
                    <td><input type="number" name="cpg_number_of_lines" value="1" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Display as List</th>
                    <td><input type="checkbox" name="cpg_view" value="list" id="cpg_view_checkbox" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Mobile List limit</th>
                    <td><input type="number" name="cpg_list_limit" value="6" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Max Image Height (e.g., 200px)</th>
                    <td><input type="text" name="cpg_max_image_height" value="200px" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Enable Pagination</th>
                    <td><input type="checkbox" name="cpg_pagination" value="true" /></td>
                </tr>
            </table>
            <?php submit_button('Generate Shortcode'); ?>
        </form>
        <?php
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $category = sanitize_text_field($_POST['cpg_category']);
            $tag = sanitize_text_field($_POST['cpg_tag']);
            $posts_per_line = intval($_POST['cpg_posts_per_line']);
            $view = isset($_POST['cpg_view']) ? 'list' : 'grid';
            $max_image_height = sanitize_text_field($_POST['cpg_max_image_height']);
            $list_limit = intval($_POST['cpg_list_limit']); // Capture list limit value
            $pagination = isset($_POST['cpg_pagination']) ? 'true' : 'false';
            
            $shortcode = '[category_post_grid posts_per_line="' . $posts_per_line . '"';
            
            // Include the category if specified
            if (!empty($category)) {
                $shortcode .= ' category="' . $category . '"';
            }
            
            // Include the tag if specified
            if (!empty($tag)) {
                $shortcode .= ' tag="' . $tag . '"';
            }

            if ($view === 'grid') {
                $number_of_lines = intval($_POST['cpg_number_of_lines']);
                $shortcode .= ' number_of_lines="' . $number_of_lines . '"';
            }

            // Include the list limit if specified
            if ($view === 'list' && !empty($list_limit)) {
                $shortcode .= ' list_limit="' . $list_limit . '"';
            }
            
            $shortcode .= ' view="' . $view . '" max_image_height="' . $max_image_height . '" pagination="' . $pagination . '"]';
            
            echo '<h2>Your Shortcode</h2>';
            echo '<code>' . $shortcode . '</code>';
        }
        ?>
    </div>
    <?php
}


function cpg_image_library_page() {
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['cpg_image_library']) && is_array($_POST['cpg_image_library'])) {
            $images = array_map('esc_url_raw', $_POST['cpg_image_library']);
            update_option('cpg_image_library', $images);
        } else {
            update_option('cpg_image_library', array()); // Clear the images if none were submitted
        }
    }

    // Retrieve the images from the database
    $images = get_option('cpg_image_library', array());

    ?>
    <div class="wrap">
        <h1>Image Library</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Upload Images</th>
                    <td>
                        <div id="cpg_image_container">
                            <?php
                            if (!empty($images)) {
                                foreach ($images as $image) {
                                    echo '<div class="cpg-image-item">';
                                    echo '<img src="' . esc_url($image) . '" style="max-width: 100px; height: auto; margin-right: 10px;" />';
                                    echo '<input type="hidden" name="cpg_image_library[]" value="' . esc_attr($image) . '" />';
                                    echo '<button type="button" class="remove_image_button button">Remove</button>';
                                    echo '</div>';
                                }
                            }
                            ?>
                        </div>
                        <button type="button" class="upload_image_button button">Upload Image</button>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Custom CSS settings page
function cpg_custom_css_page() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['cpg_custom_css'])) {
            update_option('cpg_custom_css', wp_unslash($_POST['cpg_custom_css']));
        }
    }

    $custom_css = get_option('cpg_custom_css', '');

    ?>
    <div class="wrap">
        <h1>Custom CSS</h1>
        <form method="post" action="">
            <textarea name="cpg_custom_css" rows="20" cols="100" style="width:100%;"><?php echo esc_textarea($custom_css); ?></textarea>
            <?php submit_button('Save Custom CSS'); ?>
        </form>
    </div>
    <?php
}

// Enqueue the custom CSS
function cpg_enqueue_custom_css() {
    $custom_css = get_option('cpg_custom_css', '');
    if (!empty($custom_css)) {
        echo '<style type="text/css">' . $custom_css . '</style>';
    }
}
add_action('wp_head', 'cpg_enqueue_custom_css');

function cpg_enqueue_admin_scripts() {
    wp_enqueue_media();  // Load the necessary media scripts
    ?>
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            var uploadButtons = document.querySelectorAll('.upload_image_button');
            var customUploader;

            uploadButtons.forEach(function(button) {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    var container = document.getElementById('cpg_image_container');

                    if (customUploader) {
                        customUploader.open();
                        return;
                    }

                    customUploader = wp.media({
                        title: 'Choose Image',
                        button: {
                            text: 'Choose Image'
                        },
                        multiple: true
                    });

                    customUploader.on('select', function() {
                        var attachments = customUploader.state().get('selection').toArray();
                        attachments.forEach(function(attachment) {
                            var imageUrl = attachment.attributes.url;
                            var imageHtml = document.createElement('div');
                            imageHtml.className = 'cpg-image-item';
                            imageHtml.innerHTML = '<img src="' + imageUrl + '" style="max-width: 100px; height: auto; margin-right: 10px;" />' +
                                                  '<input type="hidden" name="cpg_image_library[]" value="' + imageUrl + '" />' +
                                                  '<button type="button" class="remove_image_button button">Remove</button>';
                            container.appendChild(imageHtml);
                        });
                    });

                    customUploader.open();
                });
            });

            document.addEventListener('click', function(e) {
                if (e.target && e.target.classList.contains('remove_image_button')) {
                    e.preventDefault();
                    e.target.closest('.cpg-image-item').remove();
                }
            });
        });
    </script>
    <?php
}
add_action('admin_enqueue_scripts', 'cpg_enqueue_admin_scripts');

function cpg_add_icon_meta_box() {
    add_meta_box(
        'cpg_post_icon',
        'Post Icon',
        'cpg_post_icon_meta_box_callback',
        'post',
        'side',
        'default'
    );
}

function cpg_post_icon_meta_box_callback($post) {
    wp_nonce_field('cpg_save_post_icon_meta', 'cpg_post_icon_meta_nonce');
    $current_icon = get_post_meta($post->ID, '_cpg_post_icon', true);
    $images = get_option('cpg_image_library', array());

    ?>
    <label for="cpg_post_icon">Choose an Icon</label>
    <select name="cpg_post_icon" id="cpg_post_icon">
        <option value="">None</option>
        <?php foreach ($images as $image): ?>
            <option value="<?php echo esc_attr($image); ?>" <?php selected($current_icon, $image); ?>><?php echo basename($image); ?></option>
        <?php endforeach; ?>
    </select>
    <?php
}

function cpg_save_post_icon_meta($post_id) {
    if (!isset($_POST['cpg_post_icon_meta_nonce']) || !wp_verify_nonce($_POST['cpg_post_icon_meta_nonce'], 'cpg_save_post_icon_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['cpg_post_icon'])) {
        $icon_url = sanitize_text_field($_POST['cpg_post_icon']);
        update_post_meta($post_id, '_cpg_post_icon', $icon_url);
    }
}

function cpg_quick_edit_icon_box($column_name, $post_type) {
    if ($column_name != 'title' || $post_type != 'post') {
        return;
    }

    $images = get_option('cpg_image_library', array());

    ?>
    <fieldset class="inline-edit-col-left">
        <div class="inline-edit-col">
            <label>
                <span class="title">Post Icon</span>
                <select name="cpg_post_icon">
                    <option value="">None</option>
                    <?php foreach ($images as $image): ?>
                        <option value="<?php echo esc_attr($image); ?>"><?php echo basename($image); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </fieldset>
    <?php
}

function cpg_save_quick_edit_icon_meta($post_id) {
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['cpg_post_icon'])) {
        $icon_url = sanitize_text_field($_POST['cpg_post_icon']);
        update_post_meta($post_id, '_cpg_post_icon', $icon_url);
    }
}

function cpg_register_shortcode($atts) {
    global $paged;
    if (!isset($paged) || !$paged) {
        $paged = 1;
    }

    // Shortcode attributes with default values
    $atts = shortcode_atts(
        array(
            'category' => '',
            'tag' => '',
            'posts_per_line' => 5,
            'number_of_lines' => 1,
            'list_limit' => 6, // For mobile devices
            'view' => 'grid',
            'max_image_height' => 'none',
            'pagination' => false,
        ),
        $atts,
        'category_post_grid'
    );

    // Check if user is on a mobile device
    if (wp_is_mobile()) {
        $posts_per_page = $atts['list_limit'];
        $atts['view'] = 'list'; // Force list view on mobile
    } else {
        $posts_per_page = $atts['posts_per_line'] * $atts['number_of_lines'];
    }

    // Construct query arguments based on provided attributes
    $query_args = array(
        'posts_per_page' => $posts_per_page,
        'paged' => $paged,
        'post_type' => array('video', 'post'),
        'orderby' => 'post_date',
        'order' => 'DESC',
    );

    // If on search page, apply only the search term without additional filters
    if (is_search()) {
        $query_args['s'] = get_search_query(); // Use the search term from the search query
    } else {
        // Additional query filtering for category or tag if specified
        if ($atts['category'] === 'current' && (is_category() || is_tag())) {
            $queried_object = get_queried_object();
            $current_slug = $queried_object->slug;
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => is_category() ? 'category' : 'post_tag',
                    'field' => 'slug',
                    'terms' => $current_slug,
                ),
            );
        } else {
            if (!empty($atts['category'])) {
                $query_args['category_name'] = $atts['category'];
            }
            if (!empty($atts['tag'])) {
                $query_args['tag'] = $atts['tag'];
            }
        }
    }

    $query = new WP_Query($query_args);

    ob_start();

    if ($query->have_posts()) {
        // Open the grid or list container with appropriate class
        $container_class = ($atts['view'] === 'list') ? 'cpg-list' : 'cpg-grid';
        echo '<div class="' . esc_attr($container_class) . '" data-posts-per-line="' . intval($atts['posts_per_line']) . '" style="--posts-per-line: ' . intval($atts['posts_per_line']) . '; --max-image-height: ' . esc_attr($atts['max_image_height']) . ';">';
        
        while ($query->have_posts()) {
            $query->the_post();
            $post_icon = get_post_meta(get_the_ID(), '_cpg_post_icon', true);

            // Make the entire post item clickable and add a hover effect
            echo '<a href="' . get_permalink() . '" class="cpg-item">';

            // Display the post image
            echo '<div class="cpg-image-wrapper">';
            if (has_post_thumbnail()) {
                the_post_thumbnail('medium', ['class' => 'featured']);
            } else {
                echo '<img src="https://ewtn.polarisit.nl/wp-content/uploads/2024/09/default-thumbnail.png" class="featured" alt="Fallback Thumbnail" />';
            }
            echo '</div>';

            // Display the icon as an overlay in the top left
            if ($post_icon) {
                echo '<img src="' . esc_url($post_icon) . '" class="icon-overlay" alt="Post Icon" />';
            }

            // Display the post title and excerpt for list view
            echo '<div class="cpg-content">';
            echo '<h3>' . get_the_title() . '</h3>';
            if ($atts['view'] === 'list') {
                echo '<p>' . wp_trim_words(get_the_excerpt(), 15, '...') . '</p>';
            }
            echo '</div>';

            echo '</a>';
        }

        echo '</div>'; // Close the grid/list container

        // Pagination
        if ($atts['pagination']) {
            echo '<div class="cpg-pagination">';
            echo paginate_links(array(
                'total' => $query->max_num_pages,
                'current' => $paged,
                'prev_text' => __('« Previous'),
                'next_text' => __('Next »'),
                'end_size' => 1,
                'mid_size' => 1,
            ));
            echo '</div>';
        }
    } else {
        echo '<p>No posts found.</p>';
    }

    wp_reset_postdata();

    return ob_get_clean();
}
add_shortcode('category_post_grid', 'cpg_register_shortcode');

