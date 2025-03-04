<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CPG_Renderer {

    public function render_normal_scenario( $atts, $paged, $posts_per_page ) {
        $order = ( strtoupper( $atts['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';
        $query_args = [
            'post_type'      => [ 'post', 'video' ],
            'posts_per_page' => $posts_per_page,
            'paged'          => $paged,
            'orderby'        => ( $atts['sortby'] === 'random' ) ? 'rand' : 'date',
            'order'          => $order,
        ];

        // Apply category filtering.
        if ( $atts['category'] === 'current' ) {
            if ( is_category() ) {
                $queried_object = get_queried_object();
                if ( ! empty( $queried_object->slug ) ) {
                    $query_args['category_name'] = $queried_object->slug;
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

        $query = cpg_get_cached_query( 'category_posts', $query_args );
        ob_start();
        if ( ! empty( $query->posts ) ) {
            $post_items = '';
            foreach ( $query->posts as $post ) {
                setup_postdata( $post );
                $post_items .= $this->get_post_item_html( $post->ID, $atts );
            }
            echo $this->render_container( $post_items, $atts );
            if ( ! empty( $atts['pagination'] ) && $atts['pagination'] !== 'false' ) {
                echo $this->build_pagination_html( $paged, $query->max_num_pages );
            }
            wp_reset_postdata();
        } else {
            echo '<p>No posts found.</p>';
        }
        return ob_get_clean();
    }

    public function render_search_scenario( $atts, $paged, $posts_per_page ) {
        $search_query = trim( $atts['search'] );
        if ( empty( $search_query ) ) {
            return '<p>No search term provided.</p>';
        }

        $query_args = [
            'post_type'        => [ 'post', 'video', 'page' ],
            'posts_per_page'   => $posts_per_page,
            's'                => $search_query,
            'orderby'          => ( $atts['sortby'] === 'random' ) ? 'rand' : 'relevance',
            'order'            => 'DESC',
            'paged'            => $paged,
            'no_found_rows'    => false,
            'suppress_filters' => false,
        ];

        // For search queries, add tag and category if provided.
        if ( ! empty( $atts['tag'] ) ) {
            $query_args['tag'] = sanitize_text_field( $atts['tag'] );
        }
        if ( ! empty( $atts['category'] ) ) {
            $query_args['category_name'] = sanitize_text_field( $atts['category'] );
        }

        // Bypass caching for search queries.
        $query = new WP_Query( $query_args );
        // Force search context.
        $query->is_search = true;
        $query->query_vars['s'] = $search_query;
        $query->set( 'no_found_rows', false );
        
        if ( function_exists( 'relevanssi_do_query' ) ) {
            relevanssi_do_query( $query );
        }
        
        ob_start();
        if ( $query->have_posts() ) {
            $post_items = '';
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_items .= $this->get_post_item_html( get_the_ID(), $atts );
            }
            echo $this->render_container( $post_items, $atts );
            if ( ! empty( $atts['pagination'] ) && $atts['pagination'] !== 'false' ) {
                echo $this->build_pagination_html( $paged, $query->max_num_pages );
            }
            wp_reset_postdata();
        } else {
            echo '<p>No search results found.</p>';
        }
        return ob_get_clean();
    }

    public function render_related_scenario( $atts, $posts_per_page, $paged = 1 ) {
        $post_id = get_the_ID();
        $query_args = [
            'post_type'      => [ 'post', 'video' ],
            'posts_per_page' => $posts_per_page,
            'post__not_in'   => [ $post_id ],
            'orderby'        => ( isset( $atts['sortby'] ) && $atts['sortby'] === 'random' ) ? 'rand' : 'date',
        ];
        $query = cpg_get_cached_query( 'related_posts', $query_args );
        ob_start();
        if ( ! empty( $query->posts ) ) {
            $post_items = '';
            foreach ( $query->posts as $related_id ) {
                $post_items .= $this->get_post_item_html( $related_id, $atts );
            }
            echo $this->render_container( $post_items, $atts );
        } else {
            echo '<p>No related posts found.</p>';
        }
        return ob_get_clean();
    }

    private function render_container( $post_items, $atts ) {
        $template = get_option( 'cpg_post_container_template' );
        $posts_per_line   = isset( $atts['posts_per_line'] ) ? intval( $atts['posts_per_line'] ) : 1;
        $max_image_height = isset( $atts['max_image_height'] ) ? esc_attr( $atts['max_image_height'] ) : 'auto';
        $view             = isset( $atts['view'] ) ? $atts['view'] : 'grid';

        if ( $view === 'list' ) {
            $container_class = ( ! empty( $atts['allowscroll'] ) && $atts['allowscroll'] === 'true' && wp_is_mobile() )
                ? 'cpg-list cpg-scrollable-mobile'
                : 'cpg-list';
        } else {
            $container_class = ( ! empty( $atts['allowscroll'] ) && $atts['allowscroll'] === 'true' )
                ? ( wp_is_mobile() ? 'cpg-grid cpg-scrollable-mobile' : 'cpg-grid cpg-scrollable-desktop' )
                : 'cpg-grid';
        }

        if ( empty( $template ) ) {
            if ( ! empty( $atts['allowscroll'] ) && $atts['allowscroll'] === 'true' ) {
                $template = '<div class="cpg-scroll-container">
                    <button class="cpg-arrow-prev">&lt;</button>
                    <div class="cpg-scrollable-inner">
                        <div class="{{container_class}}" data-posts-per-line="{{posts_per_line}}" style="--posts-per-line:{{posts_per_line}}; --max-image-height:{{max-image_height}};">
                            {{post_items}}
                        </div>
                    </div>
                    <button class="cpg-arrow-next">&gt;</button>
                </div>';
            } else {
                $template = '<div class="{{container_class}}" data-posts-per-line="{{posts_per_line}}" style="--posts-per-line:{{posts_per_line}}; --max-image-height:{{max-image_height}};">
                    {{post_items}}
                </div>';
            }
        }
        $replacements = [
            '{{container_class}}'  => esc_attr( $container_class ),
            '{{posts_per_line}}'   => $posts_per_line,
            '{{max-image_height}}' => $max_image_height,
            '{{post_items}}'       => $post_items,
        ];
        return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
    }

    public function get_post_item_html( $post_id, $atts ) {
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
        $placeholders = [ '{{permalink}}', '{{thumbnail}}', '{{post_icon}}', '{{title}}', '{{excerpt}}' ];
        $replacements = [ $permalink, $thumbnail, $post_icon, $title, $excerpt ];
        return str_replace( $placeholders, $replacements, $template );
    }

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
