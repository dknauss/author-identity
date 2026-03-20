<?php
/**
 * Fediverse author-attribution meta tag output.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Fediverse;

use WP_Post;
use function Byline_Feed\byline_feed_get_authors;
use function Byline_Feed\normalize_byline_feed_fediverse;

defined( 'ABSPATH' ) || exit;

/**
 * Register hooks for fediverse output.
 */
function register_hooks(): void {
	add_action( 'wp_head', __NAMESPACE__ . '\\render_meta_tags' );
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
