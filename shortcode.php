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
            '6.7',
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

    if (wp_is_mobile()) {
        $posts_per_page = (int) $atts['list_limit'];
        $atts['view'] = 'list';
    } else {
        $posts_per_page = (int) $atts['posts_per_line'] * (int)$atts['number_of_lines'];
    }

    ob_start();

    echo '<div class="cpg-wrapper" data-scenario="' . esc_attr($scenario) . '" data-atts="' . esc_attr(json_encode($atts)) . '">';

    if ($scenario === 1) {
        echo cpg_render_search_scenario($atts, $page, $posts_per_page);
    } elseif ($scenario === 2) {
        echo cpg_render_related_scenario($atts, $posts_per_page, $page);
    } else {
        echo cpg_render_normal_scenario($atts, $page, $posts_per_page);
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

function cpg_build_pagination_html($current_page, $max_num_pages) {
    if ($max_num_pages <= 1) {
        return '';
    }

    $html = '<div class="cpg-pagination">';

    if ($current_page > 1) {
        $html .= '<a href="#" data-page="' . ($current_page - 1) . '">« Prev&nbsp;</a>';
    }

    $max_shown = 5;

    $start = $current_page - floor($max_shown / 2);
    $end   = $current_page + floor($max_shown / 2);

    if ($start < 1) {
        $end += (1 - $start);
        $start = 1;
    }

    if ($end > $max_num_pages) {
        $start -= ($end - $max_num_pages);
        $end = $max_num_pages;
    }

    if ($start < 1) $start = 1;

    $actual_count = $end - $start + 1;
    if ($actual_count < $max_shown && $max_num_pages >= $max_shown) {
        $missing = $max_shown - $actual_count;
        $end = $end + $missing;
        if ($end > $max_num_pages) {
            $end = $max_num_pages;
        }
    }

    if ($start > 1) {
        $html .= '<a href="#" data-page="1">1</a>';
        if ($start > 2) {
            $html .= '&nbsp;<span class="cpg-dots">…</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        if ($i == $current_page) {
            $html .= '&nbsp;<span class="cpg-active-page">' . $i . '</span>';
        } else {
            $html .= '&nbsp;<a href="#" data-page="' . $i . '">' . $i . '</a>';
        }
    }

    if ($end < $max_num_pages) {
        if ($end < ($max_num_pages - 1)) {
            $html .= '<span class="cpg-dots">…</span>';
        }
        $html .= '&nbsp;<a href="#" data-page="' . $max_num_pages . '">' . $max_num_pages . '</a>';
    }

    if ($current_page < $max_num_pages) {
        $html .= '&nbsp;<a href="#" data-page="' . ($current_page + 1) . '">Next »</a>';
    }

    $html .= '</div>';
    return $html;
}

function cpg_render_search_scenario($atts, $paged, $posts_per_page) {
    $search_query = isset($atts['search']) ? trim($atts['search']) : '';

    if (empty($search_query)) {
        return '<p>No search term provided.</p>';
    }

    $args = [
        'post_type'      => ['post', 'video', 'page'],
        'posts_per_page' => $posts_per_page,
        's'              => $search_query,
        'orderby'        => 'relevance',
        'order'          => 'DESC',
        'paged'          => $paged,
    ];

    $query = new WP_Query($args);

    // Use Relevanssi if available
    if (function_exists('relevanssi_do_query')) {
        relevanssi_do_query($query);
    }

    ob_start();
    if ($query->have_posts()) {
        $container_class  = ($atts['view'] === 'list') ? 'cpg-list' : 'cpg-grid';
        $scrollable_class = ($atts['allowscroll'] === 'true') 
                            ? (wp_is_mobile() ? ' cpg-scrollable-mobile' : ' cpg-scrollable-desktop') 
                            : '';

        echo '<div class="' . esc_attr($container_class . $scrollable_class) . '"
             data-posts-per-line="' . intval($atts['posts_per_line']) . '"
             data-cached="false"
             style="--posts-per-line: ' . intval($atts['posts_per_line']) . ';
                    --max-image-height: ' . esc_attr($atts['max_image_height']) . ';">';

        while ($query->have_posts()) {
            $query->the_post();
            echo cpg_render_post_item(get_the_ID(), $atts);
        }
        echo '</div>';

        if (!empty($atts['pagination']) && $atts['pagination'] !== 'false') {
            echo cpg_build_pagination_html($paged, $query->max_num_pages);
        }

        wp_reset_postdata();
    } else {
        echo '<p>No search results found.</p>';
    }

    return ob_get_clean();
}

function cpg_render_related_scenario($atts, $posts_per_page, $paged = 1) {
    $post_id   = get_the_ID();
    $cache_key = 'cpg_related_posts_' . $post_id . '_' . $posts_per_page;
    $related_posts = get_transient($cache_key);

    $is_cached = false;
    if ( false !== $related_posts ) {
        $is_cached = true;
    } else {
        $related_posts = cpg_get_related_posts_relevanssi($post_id, $posts_per_page, ['post', 'video']);
        // Use the admin setting for expiration, defaulting to 7 days
        $expiration_days = intval(get_option('cpg_cache_expiration', 7));
        $expiration = $expiration_days * DAY_IN_SECONDS;
        set_transient($cache_key, $related_posts, $expiration);
    }

    ob_start();

    if (!empty($related_posts)) {
        $container_class  = ($atts['view'] === 'list') ? 'cpg-list' : 'cpg-grid';
        $scrollable_class = ($atts['allowscroll'] === 'true')
                            ? (wp_is_mobile() ? ' cpg-scrollable-mobile' : ' cpg-scrollable-desktop')
                            : '';

        echo '<div class="' . esc_attr($container_class . $scrollable_class) . '"
             data-posts-per-line="' . intval($atts['posts_per_line']) . '"
             data-cached="' . ($is_cached ? 'true' : 'false') . '"
             style="--posts-per-line: ' . intval($atts['posts_per_line']) . ';
                    --max-image-height: ' . esc_attr($atts['max_image_height']) . ';">';

        foreach ($related_posts as $related_id) {
            echo cpg_render_post_item($related_id, $atts);
        }

        echo '</div>';
    } else {
        echo '<p>No related posts found.</p>';
    }

    return ob_get_clean();
}

function cpg_render_normal_scenario($atts, $paged, $posts_per_page) {
    $orderby = in_array($atts['sortby'], ['date', 'random']) ? $atts['sortby'] : 'date';
    $order   = in_array(strtoupper($atts['order']), ['ASC','DESC']) ? strtoupper($atts['order']) : 'DESC';

    $query_args = [
        'post_type'      => ['post', 'video'],
        'posts_per_page' => $posts_per_page,
        'paged'          => $paged,
        'orderby'        => ($orderby === 'random') ? 'rand' : 'date',
        'order'          => $order,
    ];

    // **Handling category="current" correctly**
    if ($atts['category'] === 'current') {
        if (is_category() || is_tag()) {
            // Get the category or tag slug from the current archive page
            $queried_object = get_queried_object();
            if (!empty($queried_object->slug)) {
                $tax = is_category() ? 'category' : 'post_tag';
                $query_args['tax_query'] = [
                    [
                        'taxonomy' => $tax,
                        'field'    => 'slug',
                        'terms'    => $queried_object->slug,
                    ],
                ];
            }
        } elseif (is_single()) {
            // Get categories of the current post
            $categories = get_the_category();
            if (!empty($categories)) {
                $query_args['cat'] = $categories[0]->term_id; // Get first category
            }
        }
    } else {
        // Normal category/tag filtering
        if (!empty($atts['category'])) {
            $query_args['category_name'] = sanitize_text_field($atts['category']);
        }
        if (!empty($atts['tag'])) {
            $query_args['tag'] = sanitize_text_field($atts['tag']);
        }
    }

    $is_cached = false;

    // If sortby is random, skip caching entirely
    if ($orderby === 'random') {
        $query = new WP_Query($query_args);
    } else {
        // Generate a unique cache key for this query
        $cache_key = 'cpg_category_posts_' . md5(json_encode($query_args));
        $cached_posts = get_transient($cache_key);
        $expiration_days = intval(get_option('cpg_cache_expiration', 7));
        $expiration = $expiration_days * DAY_IN_SECONDS;

        if ($cached_posts !== false) {
            $query = (object) ['posts' => $cached_posts, 'max_num_pages' => 1];
            $is_cached = true;
        } else {
            $query = new WP_Query($query_args);
            set_transient($cache_key, $query->posts, $expiration);
        }
    }
    
    ob_start();
    if (!empty($query->posts)) {
        // If allowscroll is true, render the arrow-enabled layout.
        if ($atts['allowscroll'] === 'true') {
            echo '<div class="cpg-scroll-container">';
            
            // Left arrow button
            echo '<button class="cpg-arrow-prev">&lt;</button>';
            
            // Inner container for scrolling content.
            echo '<div class="cpg-scrollable-inner">';
            
            // Use the appropriate grid class and add the scrollable class for desktop (or mobile).
            $container_class  = ($atts['view'] === 'list') ? 'cpg-list' : 'cpg-grid';
            $scrollable_class = (wp_is_mobile() ? ' cpg-scrollable-mobile' : ' cpg-scrollable-desktop');
            echo '<div class="' . esc_attr($container_class . $scrollable_class) . '" 
                       data-posts-per-line="' . intval($atts['posts_per_line']) . '" 
                       data-cached="' . ($is_cached ? 'true' : 'false') . '"
                       style="--posts-per-line: ' . intval($atts['posts_per_line']) . ';
                              --max-image-height: ' . esc_attr($atts['max_image_height']) . ';">';
            
            foreach ($query->posts as $post) {
                setup_postdata($post);
                echo cpg_render_post_item($post->ID, $atts);
            }
            echo '</div>'; // End of grid container
            
            echo '</div>'; // End of .cpg-scrollable-inner
            
            // Right arrow button
            echo '<button class="cpg-arrow-next">&gt;</button>';
            
            echo '</div>'; // End of .cpg-scroll-container

        } else {
            // If allowscroll is not true, output the grid normally.
            $container_class  = ($atts['view'] === 'list') ? 'cpg-list' : 'cpg-grid';
            echo '<div class="' . esc_attr($container_class) . '" 
                       data-posts-per-line="' . intval($atts['posts_per_line']) . '" 
                       data-cached="' . ($is_cached ? 'true' : 'false') . '"
                       style="--posts-per-line: ' . intval($atts['posts_per_line']) . ';
                              --max-image-height: ' . esc_attr($atts['max_image_height']) . ';">';
            
            foreach ($query->posts as $post) {
                setup_postdata($post);
                echo cpg_render_post_item($post->ID, $atts);
            }
            echo '</div>'; // End of grid container
        }
        
        // Optionally output pagination if needed.
        if (!empty($atts['pagination']) && $atts['pagination'] !== 'false') {
            echo cpg_build_pagination_html($paged, $query->max_num_pages);
        }
        wp_reset_postdata();
    } else {
        echo '<p>No posts found.</p>';
    }
    return ob_get_clean();
}

function cpg_register_shortcode($atts) {
    global $paged;
    if (empty($paged)) {
        $paged = 1;
    }

    $inline_css = cpg_inject_css_once();

    // Add the "prefetch" attribute with a default of false.
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
            'prefetch'          => false,  // New attribute
        ],
        $atts,
        'category_post_grid'
    );

    // Attach the search term if this is a search
    if (is_search()) {
        $atts['search'] = get_search_query(); // store the user's search
    }

    if (wp_is_mobile()) {
        $posts_per_page = (int) $atts['list_limit'];
        $atts['view']   = 'list';
    } else {
        $posts_per_page = (int) $atts['posts_per_line'] * (int)$atts['number_of_lines'];
    }

    $scenario = 3;
    if (is_search()) {
        $scenario = 1;
    } elseif ($atts['category'] === 'related' && is_single()) {
        $scenario = 2;
    }

    if ($scenario === 1) {
        // The search term is now in $atts['search']
        $inner_html = cpg_render_search_scenario($atts, $paged, $posts_per_page);
    } elseif ($scenario === 2) {
        $inner_html = cpg_render_related_scenario($atts, $posts_per_page, $paged);
    } else {
        $inner_html = cpg_render_normal_scenario($atts, $paged, $posts_per_page);
    }

    // If prefetching is enabled, mark the wrapper with a data attribute.
    $prefetch_attr = ($atts['prefetch'] === 'true') ? ' data-prefetch="true"' : '';

    $output  = '<div class="cpg-wrapper" data-scenario="' . esc_attr($scenario) . '" data-atts="' . esc_attr(json_encode($atts)) . '"' . $prefetch_attr . '>';
    $output .= $inner_html;
    $output .= '</div>';

    // If prefetch=true, add a script to prefetch all links inside the wrapper.
    if ($atts['prefetch'] === 'true') {
        $output .= '
        <script>
        (function() {
            function prefetchLinks(wrapper) {
                var links = wrapper.querySelectorAll("a");
                links.forEach(function(link) {
                    // Avoid prefetching the same link more than once
                    if (!link.dataset.prefetched) {
                        var prefetchEl = document.createElement("link");
                        prefetchEl.rel = "prefetch";
                        prefetchEl.href = link.href;
                        prefetchEl.as = "document";
                        document.head.appendChild(prefetchEl);
                        link.dataset.prefetched = "true";
                        console.log("Prefetching: " + link.href);
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
        </script>
        ';
    }

    return $inline_css . $output;
}
add_shortcode('category_post_grid', 'cpg_register_shortcode');
