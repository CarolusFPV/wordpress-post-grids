<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get the current cache version.
 *
 * @return int
 */
function cpg_get_cache_version() {
    return (int) get_option( 'cpg_cache_version', 1 );
}

/**
 * Retrieve a cached query or perform a new WP_Query and cache its posts.
 *
 * @param string $base       A unique key base (e.g., 'category_posts', 'search_posts', etc.)
 * @param array  $query_args The WP_Query arguments.
 * @return WP_Query|object  A WP_Query object (or a simple object with posts and max_num_pages)
 */
function cpg_get_cached_query( $base, $query_args ) {
    $orderby = isset( $query_args['orderby'] ) ? $query_args['orderby'] : 'date';

    // Bypass caching if the query is set to random order.
    if ( 'rand' === $orderby ) {
        return new WP_Query( $query_args );
    }

    // Calculate the expiration time from plugin settings.
    $expiration = intval( get_option( 'cpg_cache_expiration', 7 ) ) * DAY_IN_SECONDS;

    // Build a cache key using the base key, query arguments, and current cache version.
    $cache_key    = cpg_build_cache_key( $base, $query_args );
    $cached_posts = cpg_get_cache( $cache_key );
    if ( false !== $cached_posts ) {
        return (object)[ 'posts' => $cached_posts, 'max_num_pages' => 1 ];
    }
    $query = new WP_Query( $query_args );
    cpg_set_cache( $cache_key, $query->posts, $expiration );
    return $query;
}



/**
 * Build a cache key that includes the current version.
 *
 * @param string $base  A unique base key for your cache group.
 * @param mixed  $args  An array of parameters to include in the key.
 *
 * @return string
 */
function cpg_build_cache_key( $base, $args = array() ) {
    $version = cpg_get_cache_version();
    return 'cpg_' . $base . '_v' . $version . '_' . md5( json_encode( $args ) );
}

/**
 * Get a cached value.
 *
 * @param string $key
 * @return mixed
 */
function cpg_get_cache( $key ) {
    return get_transient( $key );
}

/**
 * Set a cached value.
 *
 * @param string $key
 * @param mixed  $data
 * @param int    $expiration  Expiration in seconds.
 *
 * @return bool
 */
function cpg_set_cache( $key, $data, $expiration ) {
    return set_transient( $key, $data, $expiration );
}

/**
 * Clear all plugin cache (delete transients matching our prefix)
 * and flush the object cache.
 */
function cpg_clear_all_cache() {
    global $wpdb;
    $like = '_transient_cpg_%';
    $wpdb->query( $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $like
    ) );

    // Flush persistent object cache if available.
    if ( function_exists( 'wp_cache_flush' ) ) {
        wp_cache_flush();
    }
    // Increment cache version so that old keys become invalid.
    cpg_increment_cache_version();
}

/**
 * Increment the cache version to invalidate all previous keys.
 *
 * @return int The new version.
 */
function cpg_increment_cache_version() {
    $version = cpg_get_cache_version();
    $new_version = $version + 1;
    update_option( 'cpg_cache_version', $new_version );
    return $new_version;
}

/**
 * Invalidate cache when a post is published or updated.
 *
 * This handles both immediate and scheduled posts.
 *
 * @param int     $post_id
 * @param WP_Post $post
 */
function cpg_invalidate_cache_on_new_post( $post_id, $post ) {
    // Skip revisions and autosaves.
    if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
        return;
    }

    // Only update version if the post status is publish.
    if ( 'publish' !== $post->post_status ) {
        return;
    }

    cpg_increment_cache_version();
}
add_action( 'save_post', 'cpg_invalidate_cache_on_new_post', 10, 2 );

/**
 * Also catch transitions (for scheduled posts turning to published).
 *
 * @param string  $new_status
 * @param string  $old_status
 * @param WP_Post $post
 */
function cpg_transition_cache_invalidate( $new_status, $old_status, $post ) {
    if ( $old_status !== 'publish' && $new_status === 'publish' ) {
        cpg_increment_cache_version();
    }
}
add_action( 'transition_post_status', 'cpg_transition_cache_invalidate', 10, 3 );
