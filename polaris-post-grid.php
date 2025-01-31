<?php
/*
Plugin Name: Polaris Post Grids
Description: Display posts in a grid or list.
Version: 2.7
Author: Casper Molhoek
Author URI: https://www.polarisit.nl
Plugin URI: https://www.polarisit.nl/post-grids


Todo:
- Option to disable jump to top when clicking pagination
- trending option
*/

require_once plugin_dir_path(__FILE__) . 'shortcode.php';

add_action('polaris_core_register_addons', 'register_polaris_post_grid_tabbed_menu');

function register_polaris_post_grid_tabbed_menu() {
    add_submenu_page(
        'polaris-core',
        'Post Grid',
        'Post Grid',
        'manage_options',
        'polaris-post-grid-settings',
        'cpg_tabbed_settings_page'
    );
}

add_action('admin_enqueue_scripts', 'cpg_enqueue_admin_scripts');
add_action('add_meta_boxes', 'cpg_add_icon_meta_box');
add_action('save_post', 'cpg_save_post_icon_meta');
add_action('quick_edit_custom_box', 'cpg_quick_edit_icon_box', 10, 2);
add_action('save_post', 'cpg_save_quick_edit_icon_meta');

function cpg_tabbed_settings_page() {
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'generator';

    ?>
    <div class="wrap">
        <h1>Post Grid Settings</h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=polaris-post-grid-settings&tab=generator" class="nav-tab <?php echo $current_tab === 'generator' ? 'nav-tab-active' : ''; ?>">Shortcode Generator</a>
            <a href="?page=polaris-post-grid-settings&tab=images" class="nav-tab <?php echo $current_tab === 'images' ? 'nav-tab-active' : ''; ?>">Image Library</a>
            <a href="?page=polaris-post-grid-settings&tab=css" class="nav-tab <?php echo $current_tab === 'css' ? 'nav-tab-active' : ''; ?>">Custom CSS</a>
        </h2>

        <?php
        if ($current_tab === 'generator') {
            cpg_shortcode_generator_page();
        } elseif ($current_tab === 'images') {
            cpg_image_library_page();
        } elseif ($current_tab === 'css') {
            cpg_custom_css_page();
        }
        ?>
    </div>
    <?php
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
                    <th scope="row">Mobile List Limit</th>
                    <td><input type="number" name="cpg_list_limit" value="6" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Max Image Height</th>
                    <td><input type="text" name="cpg_max_image_height" value="200px" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Enable Pagination</th>
                    <td><input type="checkbox" name="cpg_pagination" value="false" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Show Excerpt <br>(post text under title)</th>
                    <td><input type="checkbox" name="cpg_show_excerpt" value="false" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Sort Order</th>
                    <td>
                        <select name="cpg_order">
                            <option value="DESC">Newest to Oldest</option>
                            <option value="ASC">Oldest to Newest</option>
                        </select>
                    </td>
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
            $excerpt = isset($_POST['cpg_show_excerpt']) ? 'true' : 'false';
            $order = sanitize_text_field($_POST['cpg_order']);

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

            if ($view === 'list' && !empty($list_limit)) {
                $shortcode .= ' list_limit="' . $list_limit . '"';
            }

            if ($excerpt === 'true') {
                $shortcode .= ' show_post_excerpt="' . $excerpt . '"';
            }

            $shortcode .= ' view="' . $view . '" max_image_height="' . $max_image_height . '" pagination="' . $pagination . '" order="' . $order . '"]';

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

function cpg_custom_css_page() {
    $css_file_path = plugin_dir_path(__FILE__) . 'style.css';

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cpg_custom_css'])) {
        if (is_writable($css_file_path)) {
            file_put_contents($css_file_path, wp_unslash($_POST['cpg_custom_css']));
            echo '<div class="notice notice-success"><p>CSS file updated successfully.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Unable to write to the CSS file. Please check file permissions.</p></div>';
        }
    }

    $custom_css = '';
    if (file_exists($css_file_path)) {
        $custom_css = file_get_contents($css_file_path);
    }

    ?>
    <div class="wrap">
        <h1>Custom CSS</h1>
        <p>Get's injected straight into the shortcode.</p>
        <form method="post">
            <textarea name="cpg_custom_css" rows="20" cols="100" style="width: 100%;"><?php echo esc_textarea($custom_css); ?></textarea>
            <?php submit_button('Save Custom CSS'); ?>
        </form>
    </div>
    <?php
}

function cpg_enqueue_admin_scripts() {
    wp_enqueue_media();
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

function cpg_get_related_posts_relevanssi($post_id, $results_needed = 5, $post_types = ['post', 'video']) {
    if (!function_exists('relevanssi_do_query')) {
        return [];
    }

    $search_terms = [];

    $title = get_the_title($post_id);
    if (!empty($title)) {
        $title_words  = preg_split('/\s+/', $title);
        $search_terms = array_merge($search_terms, $title_words);
    }

    $category_names = wp_get_post_terms($post_id, 'category', ['fields' => 'names']);
    if (!empty($category_names)) {
        $search_terms = array_merge($search_terms, $category_names);
    }

    $tag_names = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'names']);
    if (!empty($tag_names)) {
        $search_terms = array_merge($search_terms, $tag_names);
    }

    $search_string = implode(' ', $search_terms);

    $args = [
        'post_type'      => $post_types,
        'posts_per_page' => $results_needed,
        's'              => $search_string,
        'orderby'        => 'relevance',
        'order'          => 'DESC',
        'post__not_in'   => [$post_id],
    ];

    $query = new WP_Query($args);

    relevanssi_do_query($query);

    $related_ids = [];
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $related_ids[] = get_the_ID();
        }
    }
    wp_reset_postdata();

    return $related_ids;
}