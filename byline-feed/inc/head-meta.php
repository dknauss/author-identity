<?php
/**
 * Author meta tags in the HTML head.
 *
 * Link-preview readers that ignore JSON-LD `Article` nodes still read plain
 * meta tags. Mastodon's link cards, for example, take the byline from
 * structured data only when it is typed `WebPage` or `NewsArticle`, and
 * otherwise fall back to `og:author`. This module prints, for a singular post
 * attributed to people:
 *
 * - `<meta name="author">` with the authors' names, comma-separated;
 * - `<meta property="og:author">` with the same names (read by Mastodon);
 * - one `<meta property="article:author">` per author with a profile URL
 *   (Open Graph's article vocabulary expects profile URLs).
 *
 * Posts in `organization` author mode get none of these: they carry no
 * personal byline. By default the tags print only in standalone schema mode,
 * because Yoast SEO and Rank Math print their own author tags.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Head_Meta;

use WP_Post;
use function Byline_Feed\byline_feed_get_authors;
use function Byline_Feed\byline_feed_get_author_mode;
use function Byline_Feed\Schema\get_person_url;
use function Byline_Feed\Schema\get_schema_mode;

defined( 'ABSPATH' ) || exit;

/**
 * Register hooks for author meta tag output.
 */
function register_hooks(): void {
	add_action( 'wp_head', __NAMESPACE__ . '\\render_meta_tags' );
}

/**
 * Print author meta tags for the current singular post.
 */
function render_meta_tags(): void {
	if ( ! is_singular() ) {
		return;
	}

	$post = get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		return;
	}

	foreach ( get_meta_tags_for_post( $post ) as $tag ) {
		printf(
			"<meta %s=\"%s\" content=\"%s\" />\n",
			'property' === $tag['attribute'] ? 'property' : 'name',
			esc_attr( $tag['key'] ),
			esc_attr( $tag['content'] )
		);
	}
}

/**
 * Build the author meta tags for a post.
 *
 * @param WP_Post $post Post object.
 * @return array<int, array{attribute: string, key: string, content: string}>
 */
function get_meta_tags_for_post( WP_Post $post ): array {
	$post_type = get_post_type_object( $post->post_type );

	if ( ! $post_type || ! $post_type->public ) {
		return array();
	}

	/**
	 * Filters whether Byline Feed prints author meta tags for a post.
	 *
	 * Defaults to true only in standalone schema mode, since Yoast SEO and
	 * Rank Math print their own author tags.
	 *
	 * @param bool    $enabled Whether to print the tags.
	 * @param WP_Post $post    Current singular post.
	 */
	$enabled = apply_filters( 'byline_feed_author_meta_tags_enabled', 'standalone' === get_schema_mode(), $post );

	if ( ! $enabled || 'organization' === byline_feed_get_author_mode( $post ) ) {
		return array();
	}

	$names = array();
	$urls  = array();

	foreach ( byline_feed_get_authors( $post ) as $author ) {
		$name = isset( $author->display_name ) && is_string( $author->display_name ) ? trim( wp_strip_all_tags( $author->display_name ) ) : '';

		if ( '' !== $name && ! in_array( $name, $names, true ) ) {
			$names[] = $name;
		}

		$url = get_person_url( $author );

		if ( '' !== $url && ! in_array( $url, $urls, true ) ) {
			$urls[] = $url;
		}
	}

	$tags = array();

	if ( ! empty( $names ) ) {
		$joined = implode( ', ', $names );
		$tags[] = array(
			'attribute' => 'name',
			'key'       => 'author',
			'content'   => $joined,
		);
		$tags[] = array(
			'attribute' => 'property',
			'key'       => 'og:author',
			'content'   => $joined,
		);
	}

	foreach ( $urls as $url ) {
		$tags[] = array(
			'attribute' => 'property',
			'key'       => 'article:author',
			'content'   => $url,
		);
	}

	/**
	 * Filters the author meta tags before output.
	 *
	 * Each tag is an array with `attribute` ('name' or 'property'), `key`, and
	 * `content`. Values are escaped on output.
	 *
	 * @param array   $tags Tags to print.
	 * @param WP_Post $post Current singular post.
	 */
	$tags = apply_filters( 'byline_feed_author_meta_tags', $tags, $post );

	if ( ! is_array( $tags ) ) {
		return array();
	}

	return array_values(
		array_filter(
			$tags,
			static function ( $tag ) {
				return is_array( $tag )
					&& isset( $tag['attribute'], $tag['key'], $tag['content'] )
					&& is_string( $tag['key'] ) && '' !== $tag['key']
					&& is_string( $tag['content'] ) && '' !== $tag['content'];
			}
		)
	);
}
