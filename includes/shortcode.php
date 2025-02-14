<?php
if (!defined('ABSPATH')) {
    exit;
}

function cpg_register_shortcode( $atts ) {
    global $paged;
    if ( empty( $paged ) ) {
        $paged = 1;
    }

    $atts = shortcode_atts( [
        'category'          => '',
        'tag'               => '',
        'posts_per_line'    => 4,
        'list_limit'        => 40,
        'number_of_lines'   => 1,
        'view'              => 'grid',
        'max_image_height'  => '200px',
        'show_post_excerpt' => false,
        'order'             => 'DESC',
        'allowscroll'       => false,
        'sortby'            => 'date',
        'pagination'        => false,
        'search'            => '',
        'prefetch'          => false,
    ], $atts, 'category_post_grid' );

    // If on a search page, attach the search query.
    if ( is_search() ) {
        $atts['search'] = get_search_query();
    }

    // Determine posts per page.
    if ( wp_is_mobile() ) {
        $posts_per_page = (int) $atts['list_limit'];
        $atts['view']   = 'list';
    } else {
        $posts_per_page = (int) $atts['posts_per_line'] * (int) $atts['number_of_lines'];
    }

    // Determine scenario.
    $scenario = 3;
    if ( is_search() ) {
        $scenario = 1;
    } elseif ( $atts['category'] === 'related' && is_single() ) {
        $scenario = 2;
    }

    if ( ! class_exists( 'CPG_Renderer' ) ) {
        require_once plugin_dir_path( __FILE__ ) . 'cpg-renderer.php';
    }
    $renderer = new CPG_Renderer();

    if ( $scenario === 1 ) {
        $inner_html = $renderer->render_search_scenario( $atts, $paged, $posts_per_page );
    } elseif ( $scenario === 2 ) {
        $inner_html = $renderer->render_related_scenario( $atts, $posts_per_page, $paged );
    } else {
        $inner_html = $renderer->render_normal_scenario( $atts, $paged, $posts_per_page );
    }

    // Build the outer wrapper. (You can adjust the data-scenario as needed.)
    $output  = '<div class="cpg-wrapper" data-scenario="' . esc_attr( $scenario ) . '" data-atts="' . esc_attr( wp_json_encode( $atts ) ) . '">';
    $output .= $inner_html;
    $output .= '</div>';

    // Optionally add prefetch script if enabled.
    if ( $atts['prefetch'] === 'true' ) {
        $output .= '
        <script>
        (function() {
            function prefetchLinks(wrapper) {
                var links = wrapper.querySelectorAll("a");
                links.forEach(function(link) {
                    if (!link.dataset.prefetched) {
                        var prefetchEl = document.createElement("link");
                        prefetchEl.rel = "prefetch";
                        prefetchEl.href = link.href;
                        prefetchEl.as = "document";
                        document.head.appendChild(prefetchEl);
                        link.dataset.prefetched = "true";
                    }
                });
            }
            document.addEventListener("DOMContentLoaded", function() {
                var wrappers = document.querySelectorAll(".cpg-wrapper[data-prefetch=\'true\']");
                wrappers.forEach(function(wrapper) {
                    prefetchLinks(wrapper);
                });
            });
        })();
        </script>';
    }

    // Add CSS
    $inline_css = cpg_inject_css_once();

    return $inline_css . $output;
}
add_shortcode( 'category_post_grid', 'cpg_register_shortcode' );

