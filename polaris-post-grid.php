<?php
/*
Plugin Name: Polaris Post Grids
Description: Display posts in a grid or list.
Version: 1.4
Author: Casper Molhoek
Author URI: https://www.polarisit.nl
Plugin URI: https://www.polarisit.nl/post-grids
*/

// Hook into Polaris Core's `polaris_core_register_addons` action
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

add_shortcode('category_post_grid', 'cpg_register_shortcode');
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

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cpg_custom_css'])) {
        if (is_writable($css_file_path)) {
            file_put_contents($css_file_path, wp_unslash($_POST['cpg_custom_css']));
            echo '<div class="notice notice-success"><p>CSS file updated successfully.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Unable to write to the CSS file. Please check file permissions.</p></div>';
        }
    }

    // Load the CSS file content
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

    static $css_injected = false;

    // Inject CSS only once per page
    if (!$css_injected) {
        $css_file_path = plugin_dir_path(__FILE__) . 'style.css';
        if (file_exists($css_file_path)) {
            echo '<style type="text/css">' . file_get_contents($css_file_path) . '</style>';
        }
        $css_injected = true;
    }

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
            'show_post_excerpt' => false,
            'order' => 'DESC',
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

    // Related posts
    if ($atts['category'] === 'related' && is_single()) {
        $combined_results = array();
        $results_needed = $atts['posts_per_line'] * $atts['number_of_lines'];
        $post_id = get_the_ID();
        $post_categories = wp_get_post_terms($post_id, 'category', array('fields' => 'ids'));

        // Load blacklist words from external file
        $blacklist_file = plugin_dir_path(__FILE__) . 'search_blacklist.php';
        $blacklist_words = file_exists($blacklist_file) ? include($blacklist_file) : [];

        // Search query on capitalized title tokens
        $current_post_title = get_the_title($post_id);
        if (!empty($current_post_title)) {
            $tokens = preg_split('/\s+/', $current_post_title);
            $capitalized_tokens = array_filter($tokens, function($word) use ($blacklist_words) {
                return ctype_upper(mb_substr($word, 0, 1)) && !in_array(mb_strtolower($word), array_map('mb_strtolower', $blacklist_words));
            });
            if (!empty($capitalized_tokens)) {
                $query_args = array(
                    'posts_per_page' => $results_needed,
                    'paged' => $paged,
                    'post_type' => array('video', 'post'),
                    'orderby' => 'post_date',
                    'order' => in_array(strtoupper($atts['order']), ['ASC', 'DESC']) ? strtoupper($atts['order']) : 'DESC',
                    's' => implode(' ', array_slice($capitalized_tokens, 0, 3)),
                    'post__not_in' => array($post_id),
                );

                $query = new WP_Query($query_args);
                if ($query->have_posts()) {
                    while ($query->have_posts()) {
                        $query->the_post();
                        $combined_results[] = get_the_ID();
                    }
                }
                wp_reset_postdata();
            }
        }

        // Search query on first category if not enough results are found
        if (count($combined_results) < $results_needed && !empty($post_categories)) {
            $first_category = $post_categories[0];
            $query_args = array(
                'posts_per_page' => $results_needed - count($combined_results),
                'paged' => $paged,
                'post_type' => array('video', 'post'),
                'orderby' => 'post_date',
                'order' => 'DESC',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'category',
                        'field' => 'id',
                        'terms' => $first_category,
                        'operator' => 'IN',
                    ),
                ),
                'post__not_in' => array_merge($combined_results, array($post_id)),
            );

            $query = new WP_Query($query_args);
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $combined_results[] = get_the_ID();
                }
            }
            wp_reset_postdata();
        }

        // Search query on any of the categories if still not enough results are found
        if (count($combined_results) < $results_needed && !empty($post_categories)) {
            $query_args = array(
                'posts_per_page' => $results_needed - count($combined_results),
                'paged' => $paged,
                'post_type' => array('video', 'post'),
                'orderby' => 'post_date',
                'order' => 'DESC',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'category',
                        'field' => 'id',
                        'terms' => $post_categories,
                        'operator' => 'IN',
                    ),
                ),
                'post__not_in' => array_merge($combined_results, array($post_id)),
            );

            $query = new WP_Query($query_args);
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $combined_results[] = get_the_ID();
                }
            }
            wp_reset_postdata();
        }

        // Display combined results
        ob_start();
        if (!empty($combined_results)) {
            $container_class = ($atts['view'] === 'list') ? 'cpg-list' : 'cpg-grid';
            echo '<div class="' . esc_attr($container_class) . '" data-posts-per-line="' . intval($atts['posts_per_line']) . '" style="--posts-per-line: ' . intval($atts['posts_per_line']) . '; --max-image-height: ' . esc_attr($atts['max_image_height']) . ';">';

            foreach ($combined_results as $post_id) {
                setup_postdata(get_post($post_id));
                $post_icon = get_post_meta($post_id, '_cpg_post_icon', true);

                // Make the entire post item clickable and add a hover effect
                echo '<a href="' . get_permalink($post_id) . '" class="cpg-item">';

                // Display the post image
                echo '<div class="cpg-image-wrapper">';
                if (has_post_thumbnail($post_id)) {
                    echo get_the_post_thumbnail($post_id, 'large', ['class' => 'featured']);
                } else {
                    echo '<img src="/wp-content/uploads/2024/09/default-thumbnail.png" class="featured" alt="Fallback Thumbnail" />';
                }
                echo '</div>';

                // Display the icon as an overlay in the top left
                if ($post_icon) {
                    echo '<img src="' . esc_url($post_icon) . '" class="icon-overlay" alt="Post Icon" />';
                }

                // Display the post title and optionally the excerpt
                echo '<div class="cpg-content">';
                echo '<h3>' . get_the_title($post_id) . '</h3>';
                if (!empty($atts['show_post_excerpt']) && $atts['show_post_excerpt'] === 'true') {
                    echo '<p>' . wp_trim_words(get_the_excerpt($post_id), 15, '...') . '</p>';
                }
                echo '</div>';

                echo '</a>';
            }

            echo '</div>';
            wp_reset_postdata();
        } else {
            echo '<p>No posts found.</p>';
        }

        return ob_get_clean();
    }

    // Construct query arguments based on provided attributes
    $query_args = array(
        'posts_per_page' => $posts_per_page,
        'paged' => $paged,
        'post_type' => array('video', 'post'),
        'orderby' => 'post_date',
        'order' => in_array(strtoupper($atts['order']), ['ASC', 'DESC']) ? strtoupper($atts['order']) : 'DESC', // Validate order attribute
    );

    // Check for special category values
    if ($atts['category'] === 'current') {
        if (is_search()) {
            // Display search results if on search page
            $query_args['s'] = get_search_query();
        } elseif (is_category() || is_tag()) {
            // Get current category or tag and filter posts accordingly
            $queried_object = get_queried_object();
            $current_slug = $queried_object->slug;
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => is_category() ? 'category' : 'post_tag',
                    'field' => 'slug',
                    'terms' => $current_slug,
                ),
            );
        }
    } else {
        // Construct a tax_query to handle category and tag with an OR relation
        $query_args['tax_query'] = array(
            'relation' => 'OR',
        );

        if (!empty($atts['category'])) {
            $query_args['tax_query'][] = array(
                'taxonomy' => 'category',
                'field' => 'slug',
                'terms' => $atts['category'],
            );
        }

        if (!empty($atts['tag'])) {
            $query_args['tax_query'][] = array(
                'taxonomy' => 'post_tag',
                'field' => 'slug',
                'terms' => $atts['tag'],
            );
        }
    }

    $query = new WP_Query($query_args);

    ob_start();

    if ($query->have_posts()) {
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
                the_post_thumbnail('large', ['class' => 'featured']);
            } else {
                echo '<img src="/wp-content/uploads/2024/09/default-thumbnail.png" class="featured" alt="Fallback Thumbnail" />';
            }
            echo '</div>';

            // Display the icon as an overlay in the top left
            if ($post_icon) {
                echo '<img src="' . esc_url($post_icon) . '" class="icon-overlay" alt="Post Icon" />';
            }

            // Display the post title and optionally the excerpt
            echo '<div class="cpg-content">';
            echo '<h3>' . get_the_title() . '</h3>';
            if (!empty($atts['show_post_excerpt']) && $atts['show_post_excerpt'] === 'true') {
                echo '<p>' . wp_trim_words(get_the_excerpt(), 15, '...') . '</p>';
            }
            echo '</div>';

            echo '</a>';
        }

        echo '</div>';

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


