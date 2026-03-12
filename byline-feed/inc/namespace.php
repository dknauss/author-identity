<?php
/**
 * Public API functions and hook registration.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed;

defined( 'ABSPATH' ) || exit;

/**
 * Cached adapter instance.
 *
 * @var Adapter|null
 */
$_byline_feed_adapter = null;

/**
 * Bootstrap the plugin.
 *
 * Detects the active multi-author plugin, loads the corresponding adapter,
 * and registers all output-layer hooks.
 *
 * Runs on `plugins_loaded`.
 */
function bootstrap(): void {
	// Detect and cache the adapter.
	byline_feed_get_adapter();

	// Register feed output hooks.
	Feed_RSS2\register_hooks();
	Feed_Atom\register_hooks();

	// Register perspective meta field.
	Perspective\register_hooks();
}

/**
 * Detect and return the active adapter.
 *
 * Priority order:
 * 1. Co-Authors Plus     — function_exists( 'get_coauthors' )
 * 2. PublishPress Authors — function_exists( 'publishpress_authors_get_post_authors' )
 * 3. Core WordPress      — always available (fallback)
 *
 * @return Adapter
 */
function byline_feed_get_adapter(): Adapter {
	global $_byline_feed_adapter;

	if ( null !== $_byline_feed_adapter ) {
		return $_byline_feed_adapter;
	}

	if ( function_exists( 'get_coauthors' ) ) {
		$adapter = new Adapter_CAP();
	} elseif (
		function_exists( 'publishpress_authors_get_post_authors' )
		|| function_exists( 'get_post_authors' )
		|| class_exists( 'MultipleAuthors\\Classes\\Objects\\Author' )
	) {
		$adapter = new Adapter_PPA();
	} else {
		$adapter = new Adapter_Core();
	}

	/**
	 * Filters the adapter instance.
	 *
	 * @param Adapter $adapter The auto-detected adapter.
	 */
	$_byline_feed_adapter = apply_filters( 'byline_feed_adapter', $adapter );

	return $_byline_feed_adapter;
}

/**
 * Returns the normalized author array for a given post.
 *
 * This is the primary public API function. All output layers
 * should call this rather than the adapter directly.
 *
 * @param \WP_Post $post The post.
 * @return object[] Ordered array of normalized author objects.
 */
function byline_feed_get_authors( \WP_Post $post ): array {
	$adapter = byline_feed_get_adapter();
	$authors = $adapter->get_authors( $post );

	/**
	 * Filters the normalized author array after adapter resolution.
	 *
	 * @param object[] $authors Normalized author objects.
	 * @param \WP_Post $post    The post.
	 */
	return apply_filters( 'byline_feed_authors', $authors, $post );
}

/**
 * Returns the perspective value for a given post.
 *
 * @param \WP_Post $post The post.
 * @return string Perspective value or empty string.
 */
function byline_feed_get_perspective( \WP_Post $post ): string {
	$perspective = get_post_meta( $post->ID, '_byline_perspective', true );

	/**
	 * Filters the perspective value.
	 *
	 * @param string   $perspective The perspective value.
	 * @param \WP_Post $post        The post.
	 */
	$perspective = apply_filters( 'byline_feed_perspective', $perspective, $post );

	$allowed = array(
		'personal',
		'reporting',
		'analysis',
		'official',
		'sponsored',
		'satire',
		'review',
		'announcement',
		'tutorial',
		'curation',
		'fiction',
		'interview',
	);

	if ( ! in_array( $perspective, $allowed, true ) ) {
		return '';
	}

	return $perspective;
}

/**
 * Derives a Byline role string from a WordPress user's capabilities.
 *
 * @param \WP_User|null $user The WordPress user, or null.
 * @return string Byline role: 'staff', 'contributor', etc.
 */
function get_byline_role_from_user( ?\WP_User $user ): string {
	if ( ! $user ) {
		return 'contributor';
	}

	if ( user_can( $user, 'edit_others_posts' ) ) {
		return 'staff';
	}

	return 'contributor';
}
