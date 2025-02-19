<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CPG_Renderer {

    /**
     * Renders the “normal” scenario.
     *
     * @param array $atts
     * @param int   $paged
     * @param int   $posts_per_page
     * @return string
     */
    public function render_normal_scenario( $atts, $paged, $posts_per_page ) {
        $order = ( strtoupper( $atts['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';

    // Build the base query arguments using sortby.
    $query_args = [
        'post_type'      => [ 'post', 'video' ],
        'posts_per_page' => $posts_per_page,
        'paged'          => $paged,
        'orderby'        => ( $atts['sortby'] === 'random' ) ? 'rand' : 'date',
        'order'          => $order,
    ];

        // Handle category filtering.
        if ( $atts['category'] === 'current' ) {
            if ( is_category() || is_tag() ) {
                $queried_object = get_queried_object();
                if ( ! empty( $queried_object->slug ) ) {
                    $tax = is_category() ? 'category' : 'post_tag';
                    $query_args['tax_query'] = [
                        [
                            'taxonomy' => $tax,
                            'field'    => 'slug',
                            'terms'    => $queried_object->slug,
                        ],
                    ];
                }
            } elseif ( is_single() ) {
                $categories = get_the_category();
                if ( ! empty( $categories ) ) {
                    $query_args['cat'] = $categories[0]->term_id;
                }
            }
        } else {
            if ( ! empty( $atts['category'] ) ) {
                $query_args['category_name'] = sanitize_text_field( $atts['category'] );
            }
            if ( ! empty( $atts['tag'] ) ) {
                $query_args['tag'] = sanitize_text_field( $atts['tag'] );
            }
        }

        $query = cpg_get_cached_query( 'category_posts', $query_args);

        ob_start();
        if ( ! empty( $query->posts ) ) {
            if ( ! empty( $atts['allowscroll'] ) && $atts['allowscroll'] === 'true' ) {
                echo '<div class="cpg-scroll-container">';
                echo '<button class="cpg-arrow-prev">&lt;</button>';
                echo '<div class="cpg-scrollable-inner">';
                $container_class = ( $atts['view'] === 'list' ) ? 'cpg-list' : 'cpg-grid cpg-scrollable-desktop';
                echo '<div class="' . esc_attr( $container_class ) . '" data-posts-per-line="' . intval( $atts['posts_per_line'] ) . '" style="--posts-per-line:' . intval( $atts['posts_per_line'] ) . '; --max-image-height:' . esc_attr( $atts['max_image_height'] ) . ';">';
                foreach ( $query->posts as $post ) {
                    setup_postdata( $post );
                    echo $this->get_post_item_html( $post->ID, $atts );
                }
                echo '</div>'; // close grid container
                echo '</div>'; // close scrollable inner
                echo '<button class="cpg-arrow-next">&gt;</button>';
                echo '</div>'; // close scroll container
            } else {
                $container_class = ( $atts['view'] === 'list' ) ? 'cpg-list' : 'cpg-grid';
                echo '<div class="' . esc_attr( $container_class ) . '" data-posts-per-line="' . intval( $atts['posts_per_line'] ) . '" style="--posts-per-line:' . intval( $atts['posts_per_line'] ) . '; --max-image-height:' . esc_attr( $atts['max_image_height'] ) . ';">';
                foreach ( $query->posts as $post ) {
                    setup_postdata( $post );
                    echo $this->get_post_item_html( $post->ID, $atts );
                }
                echo '</div>';
            }

            if ( ! empty( $atts['pagination'] ) && $atts['pagination'] !== 'false' ) {
                echo $this->build_pagination_html( $paged, $query->max_num_pages );
            }
            wp_reset_postdata();
        } else {
            echo '<p>No posts found.</p>';
        }
        return ob_get_clean();
    }

    /**
     * Renders the “search” scenario
     *
     * @param array $atts
     * @param int   $paged
     * @param int   $posts_per_page
     * @return string
     */
    public function render_search_scenario( $atts, $paged, $posts_per_page ) {
        $search_query = trim( $atts['search'] );
        if ( empty( $search_query ) ) {
            return '<p>No search term provided.</p>';
        }

        $query_args = [
            'post_type'      => [ 'post', 'video', 'page' ],
            'posts_per_page' => $posts_per_page,
            's'              => $search_query,
            'orderby'        => ( $atts['sortby'] === 'random' ) ? 'rand' : 'relevance',
            'order'          => 'DESC',
            'paged'          => $paged,
        ];

        $query = cpg_get_cached_query( 'search_posts', $query_args);

        if ( function_exists( 'relevanssi_do_query' ) ) {
            relevanssi_do_query( $query );
        }

        ob_start();
        if ( $query->have_posts() ) {
            $container_class = ( $atts['view'] === 'list' ) ? 'cpg-list' : 'cpg-grid';
            echo '<div class="' . esc_attr( $container_class ) . '" data-posts-per-line="' . intval( $atts['posts_per_line'] ) . '" style="--posts-per-line:' . intval( $atts['posts_per_line'] ) . '; --max-image-height:' . esc_attr( $atts['max_image_height'] ) . ';">';
            while ( $query->have_posts() ) {
                $query->the_post();
                echo $this->get_post_item_html( get_the_ID(), $atts );
            }
            echo '</div>';
            if ( ! empty( $atts['pagination'] ) && $atts['pagination'] !== 'false' ) {
                echo $this->build_pagination_html( $paged, $query->max_num_pages );
            }
            wp_reset_postdata();
        } else {
            echo '<p>No search results found.</p>';
        }
        return ob_get_clean();
    }

    /**
     * Renders the “related” scenario
     *
     * @param array $atts
     * @param int   $posts_per_page
     * @param int   $paged
     * @return string
     */
    public function render_related_scenario( $atts, $posts_per_page, $paged = 1 ) {
        $post_id = get_the_ID();
        $query_args = [
            'post_type'      => [ 'post', 'video' ],
            'posts_per_page' => $posts_per_page,
            'post__not_in'   => [ $post_id ],
            'orderby'        => ( isset( $atts['sortby'] ) && $atts['sortby'] === 'random' ) ? 'rand' : 'date',
        ];

        // Here we force caching by not using "random" as the bypass trigger.
        $query = cpg_get_cached_query( 'related_posts', $query_args);

        ob_start();
        if ( ! empty( $query->posts ) ) {
            $container_class = ( $atts['view'] === 'list' ) ? 'cpg-list' : 'cpg-grid';
            echo '<div class="' . esc_attr( $container_class ) . '" data-posts-per-line="' . intval( $atts['posts_per_line'] ) . '" style="--posts-per-line:' . intval( $atts['posts_per_line'] ) . '; --max-image-height:' . esc_attr( $atts['max_image_height'] ) . ';">';
            foreach ( $query->posts as $related_id ) {
                echo $this->get_post_item_html( $related_id, $atts );
            }
            echo '</div>';
        } else {
            echo '<p>No related posts found.</p>';
        }
        return ob_get_clean();
    }


    /**
     * Returns the HTML for a single post item using admin-defined templates.
     *
     * @param int   $post_id
     * @param array $atts
     * @return string
     */
    public function get_post_item_html( $post_id, $atts ) {
        // Determine which template to use based on the view type.
        $view = isset( $atts['view'] ) ? $atts['view'] : 'grid';
        if ( $view === 'list' ) {
            $template = get_option( 'cpg_post_list_item_template' );
            if ( empty( $template ) ) {
                $template = $this->get_default_list_template();
            }
        } else {
            $template = get_option( 'cpg_post_grid_item_template' );
            if ( empty( $template ) ) {
                $template = $this->get_default_grid_template();
            }
        }

        $permalink = get_permalink( $post_id );
        if ( has_post_thumbnail( $post_id ) ) {
            $thumbnail = get_the_post_thumbnail( $post_id, 'cpg-grid-thumb', [ 'class' => 'featured', 'loading' => 'lazy' ] );
        } else {
            $default_thumb = get_option( 'cpg_default_thumbnail', '/wp-content/uploads/2024/09/default-thumbnail.png' );
            $thumbnail = '<img src="' . esc_url( $default_thumb ) . '" class="featured" alt="Fallback Thumbnail" width="240" height="200" loading="lazy" />';
        }

        $post_icon_meta = get_post_meta( $post_id, '_cpg_post_icon', true );
        $post_icon = $post_icon_meta ? '<img src="' . esc_url( $post_icon_meta ) . '" class="icon-overlay" alt="Post Icon">' : '';
        $title = get_the_title( $post_id );
        $excerpt = ( ! empty( $atts['show_post_excerpt'] ) && $atts['show_post_excerpt'] === 'true' )
                    ? '<p>' . wp_trim_words( get_the_excerpt( $post_id ), 15, '...' ) . '</p>'
                    : '';

        // Replace placeholders with dynamic content.
        $placeholders = [ '{{permalink}}', '{{thumbnail}}', '{{post_icon}}', '{{title}}', '{{excerpt}}' ];
        $replacements = [ $permalink, $thumbnail, $post_icon, $title, $excerpt ];
        $html = str_replace( $placeholders, $replacements, $template );

        return $html;
    }

    /**
     * Returns the default grid item template.
     *
     * @return string
     */
    private function get_default_grid_template() {
        return '<a href="{{permalink}}" class="cpg-item">
    <div class="cpg-image-wrapper">
        {{thumbnail}}
    </div>
    {{post_icon}}
    <div class="cpg-content">
        <h3>{{title}}</h3>
        {{excerpt}}
    </div>
</a>';
    }

    /**
     * Returns the default list item template.
     *
     * @return string
     */
    private function get_default_list_template() {
        return '<a href="{{permalink}}" class="cpg-item">
    <div class="cpg-image-wrapper">
        {{thumbnail}}
    </div>
    {{post_icon}}
    <div class="cpg-content">
        <h3>{{title}}</h3>
        {{excerpt}}
    </div>
</a>';
    }

    /**
     * Builds the pagination HTML.
     *
     * @param int $current_page
     * @param int $max_num_pages
     * @return string
     */
    private function build_pagination_html( $current_page, $max_num_pages ) {
        if ( $max_num_pages <= 1 ) {
            return '';
        }
        $html = '<div class="cpg-pagination">';
        if ( $current_page > 1 ) {
            $html .= '<a href="#" data-page="' . ( $current_page - 1 ) . '">« Prev&nbsp;</a>';
        }
        $max_shown = 5;
        $start     = $current_page - floor( $max_shown / 2 );
        $end       = $current_page + floor( $max_shown / 2 );
        if ( $start < 1 ) {
            $end   += ( 1 - $start );
            $start  = 1;
        }
        if ( $end > $max_num_pages ) {
            $start -= ( $end - $max_num_pages );
            $end    = $max_num_pages;
        }
        if ( $start < 1 ) {
            $start = 1;
        }
        $actual_count = $end - $start + 1;
        if ( $actual_count < $max_shown && $max_num_pages >= $max_shown ) {
            $missing = $max_shown - $actual_count;
            $end     = $end + $missing;
            if ( $end > $max_num_pages ) {
                $end = $max_num_pages;
            }
        }
        if ( $start > 1 ) {
            $html .= '<a href="#" data-page="1">1</a>';
            if ( $start > 2 ) {
                $html .= '&nbsp;<span class="cpg-dots">…</span>';
            }
        }
        for ( $i = $start; $i <= $end; $i++ ) {
            if ( $i == $current_page ) {
                $html .= '&nbsp;<span class="cpg-active-page">' . $i . '</span>';
            } else {
                $html .= '&nbsp;<a href="#" data-page="' . $i . '">' . $i . '</a>';
            }
        }
        if ( $end < $max_num_pages ) {
            if ( $end < ( $max_num_pages - 1 ) ) {
                $html .= '<span class="cpg-dots">…</span>';
            }
            $html .= '&nbsp;<a href="#" data-page="' . $max_num_pages . '">' . $max_num_pages . '</a>';
        }
        if ( $current_page < $max_num_pages ) {
            $html .= '&nbsp;<a href="#" data-page="' . ( $current_page + 1 ) . '">Next »</a>';
        }
        $html .= '</div>';
        return $html;
    }
}
