<?php
/*
Plugin Name: Polaris Post Grids
Description: Display posts in a grid or list.
Version: 4.0
Author: Casper Molhoek
Author URI: https://www.polarisit.nl
Plugin URI: https://www.polarisit.nl/post-grids
*/

require_once plugin_dir_path(__FILE__) . 'includes/shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/helpers.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-menu.php';
add_image_size('cpg-grid-thumb', 240, 200, true);

// ============================================
//  Post Editor -> Post Icon Meta
// ============================================

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
add_action( 'add_meta_boxes', 'cpg_add_icon_meta_box' );

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
add_action( 'save_post', 'cpg_save_post_icon_meta' );

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
add_action( 'quick_edit_custom_box', 'cpg_quick_edit_icon_box', 10, 2 );

function cpg_save_quick_edit_icon_meta($post_id) {
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['cpg_post_icon'])) {
        $icon_url = sanitize_text_field($_POST['cpg_post_icon']);
        update_post_meta($post_id, '_cpg_post_icon', $icon_url);
    }
}
add_action( 'save_post', 'cpg_save_quick_edit_icon_meta' );

// ============================================
//  Caching
// ============================================

/**
 * Delete all transients whose keys start with our plugin prefix.
 */
function cpg_clear_all_cache() {
    global $wpdb;
    $like = '_transient_cpg_%';
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $like
    ) );
}

/**
 * Hook into post publishing to clear cache.
 */
function cpg_invalidate_cache_on_new_post($post_id, $post) {
    if ( wp_is_post_revision($post_id) || ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) ) {
        return;
    }

    if ( 'publish' !== $post->post_status ) {
        return;
    }

    // (Optional) If you want more granularity you could check whether the post belongs
    // to categories used by the grid. For simplicity, we clear all plugin cache here.
    cpg_clear_all_cache();
}
add_action('save_post', 'cpg_invalidate_cache_on_new_post', 10, 2);

