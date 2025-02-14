<?php
if (!defined('ABSPATH')) {
    exit;
}

function cpg_enqueue_scroll_scripts() {
    if (!is_admin()) {
        wp_register_script(
            'cpg-scroll-script',
            plugins_url('js/cpg-scroll.js', __FILE__),
            ['jquery'],
            '6.8',
            true
        );
        wp_enqueue_script('cpg-scroll-script');

        wp_localize_script('cpg-scroll-script', 'cpgPaginationData', [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'enableCache' => true,
        ]);
    }
}
add_action('wp_enqueue_scripts', 'cpg_enqueue_scroll_scripts');

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


function cpg_inject_css_once() {
    static $css_injected = false;
    $inline_css = '';

    if (!$css_injected) {
        $css_file_path = plugin_dir_path(__FILE__) . 'style.css';
        if (file_exists($css_file_path)) {
            $inline_css = '<style type="text/css">'
                        . file_get_contents($css_file_path)
                        . '</style>';
        }
        $css_injected = true;
    }
    return $inline_css;
}

function cpg_render_post_item($post_id, $atts) {
    $post_icon = get_post_meta($post_id, '_cpg_post_icon', true);

    ob_start(); ?>
    <a href="<?php echo get_permalink($post_id); ?>" class="cpg-item">
        <div class="cpg-image-wrapper">
        <?php if (has_post_thumbnail($post_id)) : ?>
            <?php echo get_the_post_thumbnail(
                $post_id,
                'cpg-grid-thumb',
                ['class' => 'featured', 'loading' => 'lazy']
            ); ?>
        <?php else : ?>
            <img src="/wp-content/uploads/2024/09/default-thumbnail.png"
                class="featured"
                alt="Fallback Thumbnail"
                width="240"
                height="200"
                loading="lazy" />
        <?php endif; ?>
        </div>

        <?php if ($post_icon) : ?>
            <img src="<?php echo esc_url($post_icon); ?>" class="icon-overlay" alt="Post Icon" />
        <?php endif; ?>

        <div class="cpg-content">
            <h3><?php echo get_the_title($post_id); ?></h3>
            <?php if (!empty($atts['show_post_excerpt']) && $atts['show_post_excerpt'] === 'true') : ?>
                <p><?php echo wp_trim_words(get_the_excerpt($post_id), 15, '...'); ?></p>
            <?php endif; ?>
        </div>
    </a>
    <?php
    return ob_get_clean();
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

    // Instantiate our renderer.
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

    // Optionally, include inline CSS.
    $inline_css = cpg_inject_css_once();

    return $inline_css . $output;
}
add_shortcode( 'category_post_grid', 'cpg_register_shortcode' );

