<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'polaris_core_register_addons', 'register_polaris_post_grid_tabbed_menu' );
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

function cpg_tabbed_settings_page() {
    $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';
    ?>
    <div class="wrap">
        <h1>Polaris Post Grids</h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=polaris-post-grid-settings&tab=settings" class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
            <a href="?page=polaris-post-grid-settings&tab=post_icons" class="nav-tab <?php echo $current_tab === 'post_icons' ? 'nav-tab-active' : ''; ?>">Post Icons</a>
            <a href="?page=polaris-post-grid-settings&tab=cache" class="nav-tab <?php echo $current_tab === 'cache' ? 'nav-tab-active' : ''; ?>">Cache</a>
            <a href="?page=polaris-post-grid-settings&tab=templates" class="nav-tab <?php echo $current_tab === 'templates' ? 'nav-tab-active' : ''; ?>">Templates</a>
            <a href="?page=polaris-post-grid-settings&tab=generator" class="nav-tab <?php echo $current_tab === 'generator' ? 'nav-tab-active' : ''; ?>">Generator</a>
        </h2>
        <?php
        if ( $current_tab === 'settings' ) {
            cpg_settings_page();
        } elseif ( $current_tab === 'post_icons' ) {
            cpg_post_icons_page();
        } elseif ( $current_tab === 'cache' ) {
            cpg_cache_settings_page();
        } elseif ( $current_tab === 'templates' ) {
            cpg_templates_page();
        } elseif ( $current_tab === 'generator' ) {
            cpg_shortcode_generator_page();
        }
        ?>
    </div>
    <?php
}

add_action( 'admin_enqueue_scripts', 'cpg_enqueue_admin_scripts' );
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
                if ( customUploader ) {
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
            if ( e.target && e.target.classList.contains('remove_image_button') ) {
                e.preventDefault();
                e.target.closest('.cpg-image-item').remove();
            }
        });

        // --- Default Thumbnail Uploader (Settings Tab) ---
        var uploadDefaultButton = document.getElementById('upload_default_thumbnail_button');
        if ( uploadDefaultButton ) {
            var defaultUploader;
            uploadDefaultButton.addEventListener('click', function(e) {
                e.preventDefault();
                if ( defaultUploader ) {
                    defaultUploader.open();
                    return;
                }
                defaultUploader = wp.media({
                    title: 'Choose Default Thumbnail',
                    button: {
                        text: 'Choose Image'
                    },
                    multiple: false
                });
                defaultUploader.on('select', function() {
                    var attachment = defaultUploader.state().get('selection').first().toJSON();
                    document.getElementById('cpg_default_thumbnail').value = attachment.url;
                    // Update preview image in the container.
                    var container = document.getElementById('cpg_default_thumbnail_container');
                    var img = container.querySelector('img');
                    if ( img ) {
                        img.src = attachment.url;
                    } else {
                        img = document.createElement('img');
                        img.src = attachment.url;
                        img.style.maxWidth = '150px';
                        img.style.height = 'auto';
                        img.style.display = 'block';
                        img.style.marginBottom = '10px';
                        container.insertBefore(img, uploadDefaultButton);
                    }
                });
                defaultUploader.open();
            });
        }

        // --- Live Preview in Templates Tab ---
        // samplePosts contains data for the latest 4 posts (generated server side).
        var samplePosts = <?php
            $sample_posts = [];
            $preview_query = new WP_Query([
                'posts_per_page' => 4,
                'post_status'    => 'publish'
            ]);
            if ( $preview_query->have_posts() ) {
                while ( $preview_query->have_posts() ) {
                    $preview_query->the_post();
                    $sample_posts[] = [
                        'permalink'  => get_permalink(),
                        'thumbnail'  => ( has_post_thumbnail() ? get_the_post_thumbnail( get_the_ID(), 'cpg-grid-thumb', [ 'class' => 'featured', 'loading' => 'lazy' ] ) : '<img src="' . esc_url( get_option("cpg_default_thumbnail", "/wp-content/uploads/2024/09/default-thumbnail.png") ) . '" class="featured" alt="Fallback Thumbnail" width="240" height="200" loading="lazy" />' ),
                        'post_icon'  => ( get_post_meta( get_the_ID(), '_cpg_post_icon', true ) ? '<img src="' . esc_url( get_post_meta( get_the_ID(), '_cpg_post_icon', true ) ) . '" class="icon-overlay" alt="Post Icon">' : '' ),
                        'title'      => get_the_title(),
                        'excerpt'    => ( ! empty( get_the_excerpt() ) ? '<p>' . wp_trim_words( get_the_excerpt(), 15, '...' ) . '</p>' : '' )
                    ];
                }
                wp_reset_postdata();
            }
            echo json_encode( $sample_posts );
        ?>;

        function renderPreview( template, postData ) {
            var html = template;
            html = html.replace( /{{permalink}}/g, postData.permalink );
            html = html.replace( /{{thumbnail}}/g, postData.thumbnail );
            html = html.replace( /{{post_icon}}/g, postData.post_icon );
            html = html.replace( /{{title}}/g, postData.title );
            html = html.replace( /{{excerpt}}/g, postData.excerpt );
            return html;
        }

        function updatePreviews() {
            var gridTemplate = document.getElementById('cpg_post_grid_item_template').value;
            var listTemplate = document.getElementById('cpg_post_list_item_template').value;
            var gridPreviewContainer = document.getElementById('cpg-grid-preview');
            var listPreviewContainer = document.getElementById('cpg-list-preview');
            var gridHTML = '<div class="cpg-grid">';
            var listHTML = '<div class="cpg-list">';
            samplePosts.forEach(function(post) {
                gridHTML += renderPreview( gridTemplate, post );
                listHTML += renderPreview( listTemplate, post );
            });
            gridHTML += '</div>';
            listHTML += '</div>';
            gridPreviewContainer.innerHTML = gridHTML;
            listPreviewContainer.innerHTML = listHTML;
        }

        // Update previews on keyup in either textarea.
        var gridTextarea = document.getElementById('cpg_post_grid_item_template');
        var listTextarea = document.getElementById('cpg_post_list_item_template');
        if ( gridTextarea && listTextarea ) {
            gridTextarea.addEventListener('keyup', updatePreviews);
            listTextarea.addEventListener('keyup', updatePreviews);
        }
    });
    </script>
    <?php
}
add_action( 'admin_enqueue_scripts', 'cpg_enqueue_admin_scripts' );

// ============================================
//  Tabs
// ============================================

function cpg_settings_page() {
    if ( $_SERVER['REQUEST_METHOD'] == 'POST' && isset( $_POST['cpg_default_thumbnail'] ) ) {
        $default_thumbnail = esc_url_raw( $_POST['cpg_default_thumbnail'] );
        update_option( 'cpg_default_thumbnail', $default_thumbnail );
        echo '<div class="notice notice-success"><p>Settings updated successfully.</p></div>';
    }
    $default_thumbnail = get_option( 'cpg_default_thumbnail', '/wp-content/uploads/2024/09/default-thumbnail.png' );
    ?>
    <div class="wrap">
        <h1>Settings</h1>
        <form method="post">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Default Fallback Thumbnail</th>
                    <td>
                        <div id="cpg_default_thumbnail_container">
                            <?php if ( $default_thumbnail ) : ?>
                                <img src="<?php echo esc_url( $default_thumbnail ); ?>" style="max-width: 150px; height: auto; display:block; margin-bottom:10px;">
                            <?php endif; ?>
                            <input type="hidden" name="cpg_default_thumbnail" id="cpg_default_thumbnail" value="<?php echo esc_attr( $default_thumbnail ); ?>">
                            <button type="button" id="upload_default_thumbnail_button" class="button">Upload Default Thumbnail</button>
                        </div>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Save Settings' ); ?>
        </form>
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

function cpg_templates_page() {
    // Define default templates.
    $default_grid_template = '<a href="{{permalink}}" class="cpg-item">
        <div class="cpg-image-wrapper">
            {{thumbnail}}
        </div>
        {{post_icon}}
        <div class="cpg-content">
            <h3>{{title}}</h3>
            {{excerpt}}
        </div>
    </a>';

    $default_list_template = '<a href="{{permalink}}" class="cpg-item">
        <div class="cpg-image-wrapper">
            {{thumbnail}}
        </div>
        {{post_icon}}
        <div class="cpg-content">
            <h3>{{title}}</h3>
            {{excerpt}}
        </div>
    </a>';

    $default_container_template = '<div class="{{container_class}}" data-posts-per-line="{{posts_per_line}}" style="--posts-per-line:{{posts_per_line}}; --max-image-height:{{max_image_height}};">
        {{post_items}}
    </div>';

    // Process form submission for all fields.
    if ( $_SERVER['REQUEST_METHOD'] == 'POST' && isset( $_POST['cpg_post_grid_item_template'] ) ) {
        $grid_template      = wp_unslash( $_POST['cpg_post_grid_item_template'] );
        $list_template      = wp_unslash( $_POST['cpg_post_list_item_template'] );
        $container_template = wp_unslash( $_POST['cpg_post_container_template'] );
        $custom_css         = wp_unslash( $_POST['cpg_custom_css'] );

        update_option( 'cpg_post_grid_item_template', $grid_template );
        update_option( 'cpg_post_list_item_template', $list_template );
        update_option( 'cpg_post_container_template', $container_template );

        // Update CSS file.
        $css_file_path = plugin_dir_path(__FILE__) . 'style.css';
        if ( is_writable( $css_file_path ) ) {
            file_put_contents( $css_file_path, $custom_css );
            $css_update_message = '<div class="notice notice-success"><p>CSS file updated successfully.</p></div>';
        } else {
            $css_update_message = '<div class="notice notice-error"><p>Unable to write to the CSS file. Please check file permissions.</p></div>';
        }
        echo '<div class="notice notice-success"><p>Templates and CSS updated successfully.</p></div>';
        echo $css_update_message;
    }

    // Get saved templates or fall back to defaults.
    $grid_template      = get_option( 'cpg_post_grid_item_template', $default_grid_template );
    $list_template      = get_option( 'cpg_post_list_item_template', $default_list_template );
    $container_template = get_option( 'cpg_post_container_template', $default_container_template );

    // Get CSS file content for the CSS editor.
    $css_file_path = plugin_dir_path(__FILE__) . 'style.css';
    $custom_css    = '';
    if ( file_exists( $css_file_path ) ) {
        $custom_css = file_get_contents( $css_file_path );
    }
    ?>
    <style>
        .cpg-admin-container {
            display: flex;
            gap: 20px;
            align-items: flex-start;
            margin-bottom: 40px;
        }
        .cpg-editor {
            width: 60%;
            box-sizing: border-box;
        }
        .cpg-preview {
            width: 38%;
            box-sizing: border-box;
        }
        .cpg-preview .preview-section {
            margin-bottom: 20px;
            padding: 10px;
            border: 1px solid #ccc;
            background: #fff;
        }
        .cpg-grid-preview {
            margin-top: 40px;
            padding: 10px;
            border: 1px solid #ccc;
            background: #fff;
        }
        textarea {
            width: 100%;
            font-family: monospace;
        }
        .template-placeholders {
            font-size: 0.9em;
            color: #555;
            margin-bottom: 10px;
        }
    </style>
    <div class="wrap">
        <h1>HTML Templates & Custom CSS</h1>
        <p>
            Use the editors below to customize your templates. For each template the available placeholders are shown.
        </p>
        <div class="cpg-admin-container">
            <!-- Editors Column -->
            <div class="cpg-editor">
                <form method="post">
                    <h2>Post Grid Item Template</h2>
                    <p class="template-placeholders">
                        Placeholders: <code>{{permalink}}</code>, <code>{{thumbnail}}</code>, <code>{{post_icon}}</code>, <code>{{title}}</code>, <code>{{excerpt}}</code>
                    </p>
                    <textarea id="cpg_post_grid_item_template" name="cpg_post_grid_item_template" rows="10"><?php echo esc_textarea( $grid_template ); ?></textarea>
                    
                    <h2>Post List Item Template</h2>
                    <p class="template-placeholders">
                        Placeholders: <code>{{permalink}}</code>, <code>{{thumbnail}}</code>, <code>{{post_icon}}</code>, <code>{{title}}</code>, <code>{{excerpt}}</code>
                    </p>
                    <textarea id="cpg_post_list_item_template" name="cpg_post_list_item_template" rows="10"><?php echo esc_textarea( $list_template ); ?></textarea>
                    
                    <h2>Post Container Template</h2>
                    <p class="template-placeholders">
                        Placeholders: <code>{{container_class}}</code>, <code>{{posts_per_line}}</code>, <code>{{max_image_height}}</code>, <code>{{post_items}}</code>
                    </p>
                    <textarea id="cpg_post_container_template" name="cpg_post_container_template" rows="5"><?php echo esc_textarea( $container_template ); ?></textarea>
                    
                    <h2>Custom CSS</h2>
                    <textarea name="cpg_custom_css" rows="20"><?php echo esc_textarea( $custom_css ); ?></textarea>
                    
                    <?php submit_button( 'Save Templates & CSS' ); ?>
                </form>
            </div>
            <!-- Preview Column: Only Post List Preview -->
            <div class="cpg-preview">
                <div class="preview-section">
                    <h2>Post List Preview</h2>
                    <?php echo do_shortcode('[category_post_grid category="nieuws" posts_per_line="5" view="list" max_image_height="200px"]'); ?>
                </div>
            </div>
        </div>
        <!-- Full-width Post Grid Preview below the editors -->
        <div class="cpg-grid-preview">
            <h2>Post Grid Preview</h2>
            <?php echo do_shortcode('[category_post_grid category="nieuws" posts_per_line="5" view="grid" max_image_height="200px"]'); ?>
        </div>
    </div>
    <?php
}


