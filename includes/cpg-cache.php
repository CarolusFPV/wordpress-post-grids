<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * A lightweight WP_Query subclass to hold cached query data.
 * (Search queries bypass caching.)
 */
if ( ! class_exists( 'CPG_Cached_Query' ) ) {
    class CPG_Cached_Query extends WP_Query {
        /**
         * Constructor.
         *
         * @param array $posts         Array of cached posts.
         * @param int   $max_num_pages Cached maximum number of pages.
         * @param array $query_vars    The query variables used.
         */
        public function __construct( $posts, $max_num_pages, $query_vars = array() ) {
            $this->posts         = is_array( $posts ) ? $posts : [];
            $this->max_num_pages = is_numeric( $max_num_pages ) ? $max_num_pages : 1;
            $this->query_vars    = is_array( $query_vars ) ? $query_vars : [];
            $this->current_post  = -1;
            $this->post_count    = count( $this->posts );
            $this->is_admin      = false;
        }
    }
}

/**
 * Returns the current cache version.
 *
 * @return int
 */
function cpg_get_cache_version() {
    return (int) get_option( 'cpg_cache_version', 1 );
}

/**
 * Retrieves a cached query or performs a new WP_Query and caches its posts.
 * Caching is bypassed for paginated and search queries.
 *
 * @param string $base       Unique key base (e.g., 'category_posts', 'search_posts').
 * @param array  $query_args WP_Query arguments.
 * @return WP_Query|CPG_Cached_Query
 */
function cpg_get_cached_query( $base, $query_args ) {
    // Bypass caching for search queries.
    if ( isset( $query_args['s'] ) && ! empty( $query_args['s'] ) ) {
        return new WP_Query( $query_args );
    }

    // Bypass caching for paginated queries.
    $paged = isset( $query_args['paged'] ) ? intval( $query_args['paged'] ) : 1;
    if ( $paged > 1 ) {
        return new WP_Query( $query_args );
    }

    // Bypass caching if orderby is random.
    $orderby = isset( $query_args['orderby'] ) ? $query_args['orderby'] : 'date';
    if ( 'rand' === $orderby ) {
        return new WP_Query( $query_args );
    }

    $expiration  = intval( get_option( 'cpg_cache_expiration', 7 ) ) * DAY_IN_SECONDS;
    $cache_key   = cpg_build_cache_key( $base, $query_args );
    $cached_data = cpg_get_cache( $cache_key );
    if ( is_array( $cached_data ) && isset( $cached_data['posts'], $cached_data['max_num_pages'], $cached_data['query_vars'] ) ) {
        return new CPG_Cached_Query( $cached_data['posts'], $cached_data['max_num_pages'], $cached_data['query_vars'] );
    }
    $query = new WP_Query( $query_args );
    $data = array(
        'posts'         => $query->posts,
        'max_num_pages' => $query->max_num_pages,
        'query_vars'    => $query->query_vars,
    );
    cpg_set_cache( $cache_key, $data, $expiration );
    return $query;
}

/**
 * Builds a cache key from the base key, query arguments, and cache version.
 *
 * @param string $base  Unique key base.
 * @param mixed  $args  Arguments to include.
 * @return string
 */
function cpg_build_cache_key( $base, $args = array() ) {
    $version = cpg_get_cache_version();
    return 'cpg_' . $base . '_v' . $version . '_' . md5( json_encode( $args ) );
}

/**
 * Retrieves a cached value.
 *
 * @param string $key
 * @return mixed
 */
function cpg_get_cache( $key ) {
    return get_transient( $key );
}

/**
 * Sets a cached value.
 *
 * @param string $key
 * @param mixed  $data
 * @param int    $expiration Expiration in seconds.
 * @return bool
 */
function cpg_set_cache( $key, $data, $expiration ) {
    return set_transient( $key, $data, $expiration );
}

/**
 * Clears all plugin cache (deletes transients matching our prefix) and flushes the object cache.
 */
function cpg_clear_all_cache() {
    global $wpdb;
    $like = '_transient_cpg_%';
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
    if ( function_exists( 'wp_cache_flush' ) ) {
        wp_cache_flush();
    }
    cpg_increment_cache_version();
}

/**
 * Increments the cache version to invalidate previous keys.
 *
 * @return int New version.
 */
function cpg_increment_cache_version() {
    $version = cpg_get_cache_version();
    $new_version = $version + 1;
    update_option( 'cpg_cache_version', $new_version );
    return $new_version;
}

/**
 * Invalidate cache on post save.
 */
function cpg_invalidate_cache_on_new_post( $post_id, $post ) {
    if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
        return;
    }
    if ( 'publish' !== $post->post_status ) {
        return;
    }
    cpg_increment_cache_version();
}
add_action( 'save_post', 'cpg_invalidate_cache_on_new_post', 10, 2 );

function cpg_invalidate_cache_on_delete_post( $post_id ) {
    cpg_increment_cache_version();
}
add_action( 'delete_post', 'cpg_invalidate_cache_on_delete_post' );

function cpg_invalidate_cache_on_trash_post( $post_id ) {
    cpg_increment_cache_version();
}
add_action( 'wp_trash_post', 'cpg_invalidate_cache_on_trash_post' );

function cpg_transition_cache_invalidate( $new_status, $old_status, $post ) {
    if ( $old_status !== 'publish' && $new_status === 'publish' ) {
        cpg_increment_cache_version();
    }
}
add_action( 'transition_post_status', 'cpg_transition_cache_invalidate', 10, 3 );
