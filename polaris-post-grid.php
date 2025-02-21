<?php
/*
Plugin Name: Polaris Post Grids
Description: Display posts in a grid or list.
Version: 4.0
Author: Casper Molhoek
Author URI: https://www.polarisit.nl
Plugin URI: https://www.polarisit.nl/post-grids
*/

// Include required files.
require_once plugin_dir_path(__FILE__) . 'includes/shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/helpers.php';
require_once plugin_dir_path(__FILE__) . 'includes/cpg-cache.php';
require_once plugin_dir_path(__FILE__) . 'includes/cpg-renderer.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-menu.php';

add_image_size('cpg-grid-thumb', 240, 200, true);

// ============================================
//  Post Editor -> Post Icon Meta (Gutenberg Integration)
// ============================================

// Register the meta field with REST support.
function cpg_register_post_icon_meta() {
    register_post_meta( 'post', '_cpg_post_icon', array(
        'show_in_rest'  => true,
        'single'       => true,
        'type'         => 'string',
        'auth_callback'=> function() {
            return current_user_can( 'edit_posts' );
        },
    ) );
}
add_action( 'init', 'cpg_register_post_icon_meta' );

// Enqueue the custom Gutenberg panel JavaScript.
function cpg_enqueue_icon_panel_script() {
    $script_path = plugin_dir_path( __FILE__ ) . '/js/cpg-icon-panel.js';
    $script_url  = plugin_dir_url( __FILE__ ) . '/js/cpg-icon-panel.js';
    wp_enqueue_script(
        'cpg-icon-panel',
        $script_url,
        array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n', 'wp-compose' ),
        filemtime( $script_path ),
        true
    );

    // Pass the list of icons (from your saved option) to the script.
    $icons = get_option('cpg_image_library', array());
    wp_localize_script( 'cpg-icon-panel', 'cpgIconPanelSettings', array(
        'icons' => $icons,
    ) );
}
add_action( 'enqueue_block_editor_assets', 'cpg_enqueue_icon_panel_script' );
