<?php
/**
 * Fediverse author-attribution meta tag output.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Fediverse;

use WP_Post;
use function Byline_Feed\byline_feed_get_authors;
use function Byline_Feed\byline_feed_get_author_mode;
use function Byline_Feed\normalize_byline_feed_fediverse;

defined( 'ABSPATH' ) || exit;

/**
 * Register hooks for fediverse output.
 */
function register_hooks(): void {
	add_action( 'wp_head', __NAMESPACE__ . '\\render_meta_tags' );
	add_filter( 'jetpack_open_graph_tags', __NAMESPACE__ . '\\filter_jetpack_open_graph_tags', 20 );
}

/**
 * Remove Jetpack's `fediverse:creator` tag where this plugin decides instead.
 *
 * Jetpack (13.8+) adds `fediverse:creator` from a Jetpack Social Mastodon
 * connection: the post author's own, or else a site-wide one. It does not
 * know who a post is attributed to, so it credits that handle even on posts
 * attributed to the site. The tag is removed:
 *   - in `organization` mode, where no personal creator is credited;
 *   - when this plugin prints its own handles, which already name every
 *     author, so the page does not carry a duplicate or a different one.
 * Otherwise Jetpack's tag is left alone.
 *
 * @param array $tags Jetpack Open Graph tags.
 * @return array
 */
function filter_jetpack_open_graph_tags( $tags ) {
	if ( ! is_array( $tags ) || ! isset( $tags['fediverse:creator'] ) || ! is_singular() ) {
		return $tags;
	}

	$post = get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		return $tags;
	}

	if (
		'organization' === byline_feed_get_author_mode( $post )
		|| array() !== get_handles_for_post( $post )
	) {
		unset( $tags['fediverse:creator'] );
	}

	return $tags;
}

/**
 * Render fediverse:creator meta tags for the current singular post.
 */
function render_meta_tags(): void {
	if ( ! is_singular() ) {
		return;
	}

	$post = get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$handles = get_handles_for_post( $post );

	foreach ( $handles as $handle ) {
		printf(
			"<meta name=\"fediverse:creator\" content=\"%s\" />\n",
			esc_attr( $handle )
		);
	}
}

/**
 * Return normalized fediverse handles for a post's resolved authors.
 *
 * @param WP_Post $post Post object.
 * @return string[]
 */
function get_handles_for_post( WP_Post $post ): array {
	// A post attributed to the site has no personal creator to credit.
	if ( 'organization' === byline_feed_get_author_mode( $post ) ) {
		return array();
	}

	$authors = byline_feed_get_authors( $post );
	$handles = array();

	foreach ( $authors as $author ) {
		$handle = isset( $author->fediverse ) && is_string( $author->fediverse ) ? $author->fediverse : '';
		$handle = normalize_byline_feed_fediverse( $handle );

		/**
		 * Filters a fediverse handle before meta-tag output.
		 *
		 * Filtered values are re-normalized to ensure valid handle format.
		 *
		 * @param string $handle Normalized fediverse handle or empty string.
		 * @param object $author Normalized author object.
		 */
		$handle = apply_filters( 'byline_feed_fediverse_handle', $handle, $author );

		if ( is_string( $handle ) && '' !== $handle ) {
			$handle = normalize_byline_feed_fediverse( $handle );
		}

		if ( '' === $handle || in_array( $handle, $handles, true ) ) {
			continue;
		}

		$handles[] = $handle;
	}

	return $handles;
}
