<?php
if (!defined('ABSPATH')) {
    exit;
}

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
            <a href="?page=polaris-post-grid-settings&tab=post_icons" class="nav-tab <?php echo $current_tab === 'images' ? 'nav-tab-active' : ''; ?>">Post Icons</a>
            <a href="?page=polaris-post-grid-settings&tab=css" class="nav-tab <?php echo $current_tab === 'css' ? 'nav-tab-active' : ''; ?>">Custom CSS</a>
            <a href="?page=polaris-post-grid-settings&tab=cache" class="nav-tab <?php echo $current_tab === 'cache' ? 'nav-tab-active' : ''; ?>">Cache</a>
        </h2>

        <?php
        if ($current_tab === 'generator') {
            cpg_shortcode_generator_page();
        } elseif ($current_tab === 'post_icons') {
            cpg_post_icons_page();
        } elseif ($current_tab === 'css') {
            cpg_custom_css_page();
        } elseif ($current_tab === 'cache') {
            cpg_cache_settings_page();
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
                <!-- Category Field -->
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
                <!-- Tag Field -->
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
                <!-- Posts Per Line -->
                <tr valign="top">
                    <th scope="row">Posts Per Line</th>
                    <td><input type="number" name="cpg_posts_per_line" value="5" /></td>
                </tr>
                <!-- Number of Lines -->
                <tr valign="top" id="cpg_number_of_lines_row">
                    <th scope="row">Number of Lines</th>
                    <td><input type="number" name="cpg_number_of_lines" value="1" /></td>
                </tr>
                <!-- Display as List -->
                <tr valign="top">
                    <th scope="row">Display as List</th>
                    <td><input type="checkbox" name="cpg_view" value="list" id="cpg_view_checkbox" /></td>
                </tr>
                <!-- Mobile List Limit -->
                <tr valign="top">
                    <th scope="row">Mobile List Limit</th>
                    <td><input type="number" name="cpg_list_limit" value="6" /></td>
                </tr>
                <!-- Max Image Height -->
                <tr valign="top">
                    <th scope="row">Max Image Height</th>
                    <td><input type="text" name="cpg_max_image_height" value="200px" /></td>
                </tr>
                <!-- Enable Pagination -->
                <tr valign="top">
                    <th scope="row">Enable Pagination</th>
                    <td><input type="checkbox" name="cpg_pagination" value="true" /></td>
                </tr>
                <!-- Show Excerpt -->
                <tr valign="top">
                    <th scope="row">Show Excerpt <br>(post text under title)</th>
                    <td><input type="checkbox" name="cpg_show_excerpt" value="true" /></td>
                </tr>
                <!-- Sort Order -->
                <tr valign="top">
                    <th scope="row">Sort Order</th>
                    <td>
                        <select name="cpg_order">
                            <option value="DESC">Newest to Oldest</option>
                            <option value="ASC">Oldest to Newest</option>
                        </select>
                    </td>
                </tr>
                <!-- Allowscroll -->
                <tr valign="top">
                    <th scope="row">Enable Scroll Navigation</th>
                    <td><input type="checkbox" name="cpg_allowscroll" value="true" /></td>
                </tr>
                <!-- Sortby -->
                <tr valign="top">
                    <th scope="row">Sort By</th>
                    <td>
                        <select name="cpg_sortby">
                            <option value="date">Date</option>
                            <option value="random">Random</option>
                        </select>
                    </td>
                </tr>
                <!-- Prefetch -->
                <tr valign="top">
                    <th scope="row">Enable Prefetch</th>
                    <td><input type="checkbox" name="cpg_prefetch" value="true" /></td>
                </tr>
            </table>
            <?php submit_button('Generate Shortcode'); ?>
        </form>
        <?php
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Capture and sanitize all the form fields
            $category         = sanitize_text_field($_POST['cpg_category']);
            $tag              = sanitize_text_field($_POST['cpg_tag']);
            $posts_per_line   = intval($_POST['cpg_posts_per_line']);
            $number_of_lines  = intval($_POST['cpg_number_of_lines']);
            $view             = isset($_POST['cpg_view']) ? 'list' : 'grid';
            $list_limit       = intval($_POST['cpg_list_limit']);
            $max_image_height = sanitize_text_field($_POST['cpg_max_image_height']);
            $pagination       = isset($_POST['cpg_pagination']) ? 'true' : 'false';
            $excerpt          = isset($_POST['cpg_show_excerpt']) ? 'true' : 'false';
            $order            = sanitize_text_field($_POST['cpg_order']);
            $allowscroll      = isset($_POST['cpg_allowscroll']) ? 'true' : 'false';
            $sortby           = isset($_POST['cpg_sortby']) ? sanitize_text_field($_POST['cpg_sortby']) : 'date';
            $prefetch         = isset($_POST['cpg_prefetch']) ? 'true' : 'false';

            // Begin building the shortcode
            $shortcode = '[category_post_grid posts_per_line="' . $posts_per_line . '"';

            // Include category if specified
            if (!empty($category)) {
                $shortcode .= ' category="' . $category . '"';
            }

            // Include tag if specified
            if (!empty($tag)) {
                $shortcode .= ' tag="' . $tag . '"';
            }

            // If view is grid, include number_of_lines
            if ($view === 'grid') {
                $shortcode .= ' number_of_lines="' . $number_of_lines . '"';
            }

            // If view is list, include list_limit
            if ($view === 'list' && !empty($list_limit)) {
                $shortcode .= ' list_limit="' . $list_limit . '"';
            }

            // Include show_post_excerpt if enabled
            if ($excerpt === 'true') {
                $shortcode .= ' show_post_excerpt="true"';
            }

            // Append remaining attributes
            $shortcode .= ' view="' . $view . '"';
            $shortcode .= ' max_image_height="' . $max_image_height . '"';
            $shortcode .= ' pagination="' . $pagination . '"';
            $shortcode .= ' order="' . $order . '"';
            $shortcode .= ' allowscroll="' . $allowscroll . '"';
            $shortcode .= ' sortby="' . $sortby . '"';
            $shortcode .= ' prefetch="' . $prefetch . '"';
            $shortcode .= ']';

            echo '<h2>Your Shortcode</h2>';
            echo '<code>' . $shortcode . '</code>';
        }
        ?>
    </div>
    <?php
}

function cpg_post_icons_page() {
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
        <h1>Post Icons</h1>
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
    $css_file_path = plugin_dir_path(__FILE__) . 'includes/style.css';

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

function cpg_cache_settings_page() {
    // Process form submission for cache settings
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Save cache expiration time (in days)
        if (isset($_POST['cpg_cache_expiration'])) {
            $expiration = intval($_POST['cpg_cache_expiration']);
            update_option('cpg_cache_expiration', $expiration);
        }
        // Save auto prebuild option (checkbox; value 1 if checked, 0 otherwise)
        $auto_prebuild = isset($_POST['cpg_auto_prebuild_related']) ? 1 : 0;
        update_option('cpg_auto_prebuild_related', $auto_prebuild);

        // If the "Clear Cache" button was pressed, clear all cache.
        if (isset($_POST['cpg_clear_cache'])) {
            cpg_clear_all_cache();
            echo '<div class="notice notice-success"><p>Cache cleared successfully.</p></div>';
        } else {
            echo '<div class="notice notice-success"><p>Cache settings updated.</p></div>';
        }
    }

    // Retrieve current options (default cache expiration is 7 days)
    $cache_expiration = get_option('cpg_cache_expiration', 7);
    $auto_prebuild    = get_option('cpg_auto_prebuild_related', 0);
    ?>
    <div class="wrap">
        <h1>Cache Settings</h1>
        <p>Configure the cache expiration timeframe (in days) and choose if you want to automatically prebuild related posts cache for the last 50 published posts.</p>
        <form method="post">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Cache Expiration (days)</th>
                    <td>
                        <input type="number" name="cpg_cache_expiration" value="<?php echo esc_attr($cache_expiration); ?>" min="1" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Auto Prebuild Related Cache</th>
                    <td>
                        <input type="checkbox" name="cpg_auto_prebuild_related" value="1" <?php checked(1, $auto_prebuild); ?> />
                        <span>Automatically build related posts cache for the last 50 posts upon new publication.</span>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Cache Settings'); ?>
            <?php submit_button('Clear Entire Cache', 'secondary', 'cpg_clear_cache'); ?>
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