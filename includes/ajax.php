<?php
if (!defined('ABSPATH')) {
    exit;
}

/*
* Load more posts, requested by infinite scroll or pagination in /js/cpg-scroll.js
*/
function cpg_load_more_posts_ajax() {
    $scenario = isset($_POST['scenario']) ? intval($_POST['scenario']) : 3;
    $page     = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $atts     = isset($_POST['atts']) ? (array) $_POST['atts'] : [];

    $atts = shortcode_atts(
        [
            'category'          => '',
            'tag'               => '',
            'posts_per_line'    => 4,
            'list_limit'        => 40,
            'number_of_lines'   => 1,
            'view'              => 'grid',
            'max_image_height'  => 'none',
            'show_post_excerpt' => false,
            'order'             => 'DESC',
            'allowscroll'       => false,
            'sortby'            => 'date',
            'pagination'        => false,
            'search'            => '',
        ],
        $atts,
        'category_post_grid'
    );

    if ( wp_is_mobile() ) {
        $posts_per_page = (int)$atts['list_limit'];
        $atts['view'] = 'list';
    } else {
        $posts_per_page = (int)$atts['posts_per_line'] * (int)$atts['number_of_lines'];
    }

    ob_start();

    echo '<div class="cpg-wrapper" data-scenario="' . esc_attr($scenario) . '" data-atts="' . esc_attr(json_encode($atts)) . '">';

    // Instantiate the new renderer.
    if ( ! class_exists('CPG_Renderer') ) {
        require_once plugin_dir_path(__FILE__) . 'cpg-renderer.php';
    }
    $renderer = new CPG_Renderer();

    if ( $scenario === 1 ) {
        echo $renderer->render_search_scenario( $atts, $page, $posts_per_page );
    } elseif ( $scenario === 2 ) {
        echo $renderer->render_related_scenario( $atts, $posts_per_page, $page );
    } else {
        echo $renderer->render_normal_scenario( $atts, $page, $posts_per_page );
    }

    echo '</div>';

    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_cpg_load_more_posts', 'cpg_load_more_posts_ajax');
add_action('wp_ajax_nopriv_cpg_load_more_posts', 'cpg_load_more_posts_ajax');
