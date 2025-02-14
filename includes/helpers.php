<?php
if (!defined('ABSPATH')) {
    exit;
}

function cpg_enqueue_scroll_scripts() {
    if (!is_admin()) {
        wp_register_script(
            'cpg-scroll-script',
            plugin_dir_url(dirname(__FILE__)) . 'js/cpg-scroll.js', // Ensure it loads from the root plugin directory
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

function cpg_get_related_posts_relevanssi($post_id, $results_needed = 5, $post_types = ['post', 'video']) {
    if (!function_exists('relevanssi_do_query')) {
        return [];
    }

    $search_terms = [];

    $title = get_the_title($post_id);
    if (!empty($title)) {
        $title_words  = preg_split('/\s+/', $title);
        $search_terms = array_merge($search_terms, $title_words);
    }

    $category_names = wp_get_post_terms($post_id, 'category', ['fields' => 'names']);
    if (!empty($category_names)) {
        $search_terms = array_merge($search_terms, $category_names);
    }

    $tag_names = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'names']);
    if (!empty($tag_names)) {
        $search_terms = array_merge($search_terms, $tag_names);
    }

    $search_string = implode(' ', $search_terms);

    $args = [
        'post_type'      => $post_types,
        'posts_per_page' => $results_needed,
        's'              => $search_string,
        'orderby'        => 'relevance',
        'order'          => 'DESC',
        'post__not_in'   => [$post_id],
    ];

    $query = new WP_Query($args);

    relevanssi_do_query($query);

    $related_ids = [];
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $related_ids[] = get_the_ID();
        }
    }
    wp_reset_postdata();

    return $related_ids;
}

function cpg_get_cached_related_posts($post_id, $limit = 10) {
    $cache_key = 'cpg_related_posts_' . $post_id;
    $related_posts = get_transient($cache_key);

    if ($related_posts === false) {
        $related_posts = cpg_get_related_posts_relevanssi($post_id, $limit, ['post', 'video']);
        set_transient($cache_key, $related_posts, HOUR_IN_SECONDS * 6); // Cache for 6 hours
    }

    return $related_posts;
}
