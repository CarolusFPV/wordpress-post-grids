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
            '2.9',
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
                <?php echo get_the_post_thumbnail($post_id, 'large', ['class' => 'featured']); ?>
            <?php else : ?>
                <img src="/wp-content/uploads/2024/09/default-thumbnail.png"
                     class="featured" alt="Fallback Thumbnail" />
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
    $search_query = isset($atts['search']) ? $atts['search'] : '';

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

    if (function_exists('relevanssi_do_query')) {
        $query = new WP_Query($args);
        relevanssi_do_query($query);
    } else {
        $query = new WP_Query($args);
    }

    ob_start();
    if ($query->have_posts()) {
        $container_class   = ($atts['view'] === 'list') ? 'cpg-list' : 'cpg-grid';
        $scrollable_class = '';
        if ($atts['allowscroll'] === 'true') {
            if (wp_is_mobile()) {
                $scrollable_class = ' cpg-scrollable-mobile';
            } else {
                $scrollable_class = ' cpg-scrollable-desktop';
            }
        }

        echo '<div class="' . esc_attr($container_class . $scrollable_class) . '"
             data-posts-per-line="' . intval($atts['posts_per_line']) . '"
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
    $post_id = get_the_ID();
    $all_related_ids = cpg_get_related_posts_relevanssi($post_id, 50, ['post','video']);
    $total_related   = count($all_related_ids);

    $max_num_pages = ceil($total_related / $posts_per_page);
    $offset        = ($paged - 1) * $posts_per_page;
    $page_ids      = array_slice($all_related_ids, $offset, $posts_per_page);

    ob_start();

    if (!empty($page_ids)) {
        $container_class   = ($atts['view'] === 'list') ? 'cpg-list' : 'cpg-grid';
        $scrollable_class = '';
        if ($atts['allowscroll'] === 'true') {
            if (wp_is_mobile()) {
                $scrollable_class = ' cpg-scrollable-mobile';
            } else {
                $scrollable_class = ' cpg-scrollable-desktop';
            }
        }

        echo '<div class="' . esc_attr($container_class . $scrollable_class) . '"
             data-posts-per-line="' . intval($atts['posts_per_line']) . '"
             style="--posts-per-line: ' . intval($atts['posts_per_line']) . ';
                    --max-image-height: ' . esc_attr($atts['max_image_height']) . ';">';

        foreach ($page_ids as $rid) {
            setup_postdata(get_post($rid));
            echo cpg_render_post_item($rid, $atts);
        }
        wp_reset_postdata();

        echo '</div>';

        if (!empty($atts['pagination']) && $atts['pagination'] !== 'false') {
            echo cpg_build_pagination_html($paged, $max_num_pages);
        }
    } else {
        echo '<p>No related posts found.</p>';
    }

    return ob_get_clean();
}

function cpg_render_normal_scenario($atts, $paged, $posts_per_page) {
    $orderby = 'post_date';
    $order   = in_array(strtoupper($atts['order']), ['ASC','DESC']) ? strtoupper($atts['order']) : 'DESC';

    if ($atts['sortby'] === 'random') {
        $orderby = 'rand';
    }

    $query_args = [
        'post_type'      => ['post', 'video'],
        'posts_per_page' => $posts_per_page,
        'paged'          => $paged,
        'orderby'        => $orderby,
        'order'          => $order,
    ];

    if ($atts['category'] === 'current') {
        if (is_category() || is_tag()) {
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
        }
    } else {
        if (!empty($atts['category']) && $atts['category'] !== 'related') {
            $query_args['category_name'] = sanitize_text_field($atts['category']);
        }
        if (!empty($atts['tag'])) {
            $query_args['tag'] = sanitize_text_field($atts['tag']);
        }
    }

    $query = new WP_Query($query_args);

    ob_start();
    if ($query->have_posts()) {
        $container_class  = ($atts['view'] === 'list') ? 'cpg-list' : 'cpg-grid';
        $scrollable_class = '';
        if ($atts['allowscroll'] === 'true') {
            if (wp_is_mobile()) {
                $scrollable_class = ' cpg-scrollable-mobile';
            } else {
                $scrollable_class = ' cpg-scrollable-desktop';
            }
        }

        echo '<div class="' . esc_attr($container_class . $scrollable_class) . '"
             data-posts-per-line="' . intval($atts['posts_per_line']) . '"
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

    $output  = '<div class="cpg-wrapper" data-scenario="' . esc_attr($scenario) . '" data-atts="' . esc_attr(json_encode($atts)) . '">';
    $output .= $inner_html;
    $output .= '</div>';

    return $inline_css . $output;
}
add_shortcode('category_post_grid', 'cpg_register_shortcode');