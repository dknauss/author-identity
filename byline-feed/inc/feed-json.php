<?php
/**
 * JSON Feed Byline output.
 *
 * Outputs Byline-structured author identity as _byline extension
 * properties in JSON Feed 1.1 output. Works in two modes:
 *
 * 1. If a JSON Feed plugin is active (detected by filter or function),
 *    hooks into its pipeline to enrich existing JSON Feed output.
 * 2. If no JSON Feed plugin is found, registers a /feed/json endpoint
 *    and renders a complete JSON Feed with Byline extensions.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Feed_JSON;

use function Byline_Feed\byline_feed_get_authors;
use function Byline_Feed\byline_feed_get_perspective;

defined( 'ABSPATH' ) || exit;

/**
 * Register JSON Feed hooks.
 *
 * JSON Feed coexistence is decided on init, after other plugins have
 * had a chance to register their routes and filters.
 */
function register_hooks(): void {
	add_action( 'init', __NAMESPACE__ . '\\register_json_support', 20 );
}

/**
 * Register JSON Feed integration filters or fallback endpoint.
 */
function register_json_support(): void {
	/*
	 * Detection strategy:
	 * - json_feed_item filter   → WP JSON Feed plugin (manton/jsonfeed-wp).
	 * - json_feed_init function → Alternate JSON Feed plugins.
	 * - do_feed_json action     → A plugin has already registered the endpoint.
	 */
	if ( has_filter( 'json_feed_item' ) || function_exists( 'json_feed_init' ) || has_action( 'do_feed_json' ) ) {
		add_filter( 'json_feed_item', __NAMESPACE__ . '\\filter_json_feed_item', 10, 2 );
		add_filter( 'json_feed_channel', __NAMESPACE__ . '\\filter_json_feed_channel' );
		return;
	}

	register_feed_endpoint();
}

/**
 * Register a /feed/json endpoint.
 *
 * Uses WordPress's native feed registration. The endpoint responds
 * at example.com/feed/json with a complete JSON Feed 1.1 document.
 */
function register_feed_endpoint(): void {
	add_feed( 'json', __NAMESPACE__ . '\\render_json_feed' );
}

/**
 * Build the _byline extension object for a single author.
 *
 * This object sits inside a JSON Feed author entry and carries
 * the Byline-specific identity data that the standard author
 * fields (name, url, avatar) don't cover.
 *
 * @param object        $author Normalized author object from the adapter layer.
 * @param \WP_Post|null $post   The post for per-item context, or null for feed-level.
 * @return array The _byline extension data.
 */
function build_author_byline_extension( object $author, ?\WP_Post $post = null ): array {
	$ext = array(
		'id' => $author->id,
	);

	if ( ! empty( $author->description ) ) {
		$ext['context'] = mb_substr( wp_strip_all_tags( $author->description ), 0, 280 );
	}

	if ( ! empty( $author->role ) ) {
		$ext['role'] = $author->role;
	}

	if ( ! empty( $author->is_guest ) ) {
		$ext['is_guest'] = true;
	}

	if ( ! empty( $author->profiles ) && is_array( $author->profiles ) ) {
		$ext['profiles'] = $author->profiles;
	}

	if ( ! empty( $author->now_url ) ) {
		$ext['now_url'] = $author->now_url;
	}

	if ( ! empty( $author->uses_url ) ) {
		$ext['uses_url'] = $author->uses_url;
	}

	if ( ! empty( $author->fediverse ) ) {
		$ext['fediverse'] = $author->fediverse;
	}

	/**
	 * Filters the _byline extension object for a JSON Feed author entry.
	 *
	 * @param array         $ext    The extension data.
	 * @param object        $author The normalized author object.
	 * @param \WP_Post|null $post   The post, or null for feed-level authors.
	 */
	return apply_filters( 'byline_feed_json_author_extension', $ext, $author, $post );
}

/**
 * Build a JSON Feed author entry from a normalized author object.
 *
 * Returns a JSON Feed 1.1 compliant author object with standard
 * fields (name, url, avatar) plus a _byline extension object
 * carrying the richer identity data.
 *
 * @param object        $author Normalized author object.
 * @param \WP_Post|null $post   The post for per-item context.
 * @return array JSON Feed author object.
 */
function build_json_author( object $author, ?\WP_Post $post = null ): array {
	$entry = array();

	if ( ! empty( $author->display_name ) ) {
		$entry['name'] = $author->display_name;
	}

	if ( ! empty( $author->url ) ) {
		$entry['url'] = $author->url;
	}

	if ( ! empty( $author->avatar_url ) ) {
		$entry['avatar'] = $author->avatar_url;
	}

	$entry['_byline'] = build_author_byline_extension( $author, $post );

	return $entry;
}

/**
 * Filter a JSON Feed item to add Byline author and perspective data.
 *
 * Used when hooking into an existing JSON Feed plugin's pipeline.
 * Replaces or enriches the item's authors array with normalized
 * author data from the adapter layer.
 *
 * @param array    $item The JSON Feed item array.
 * @param \WP_Post $post The WordPress post.
 * @return array Modified item.
 */
function filter_json_feed_item( array $item, \WP_Post $post ): array {
	$authors = byline_feed_get_authors( $post );

	if ( ! empty( $authors ) ) {
		$item['authors'] = array_map(
			function ( $author ) use ( $post ) {
				return build_json_author( $author, $post );
			},
			$authors
		);
	}

	$perspective = byline_feed_get_perspective( $post );
	if ( '' !== $perspective ) {
		if ( ! isset( $item['_byline'] ) ) {
			$item['_byline'] = array();
		}
		$item['_byline']['perspective'] = $perspective;
	}

	return $item;
}

/**
 * Filter the JSON Feed channel to add feed-level Byline metadata.
 *
 * Adds a _byline object with spec version and organization data
 * to the feed-level output.
 *
 * @param array $channel The JSON Feed channel array.
 * @return array Modified channel.
 */
function filter_json_feed_channel( array $channel ): array {
	$channel['_byline'] = array(
		'spec_version' => '1.0',
	);

	$site_name = get_bloginfo( 'name' );
	$site_url  = get_bloginfo( 'url' );

	if ( $site_name || $site_url ) {
		$channel['_byline']['org'] = array_filter(
			array(
				'name' => $site_name,
				'url'  => $site_url,
			)
		);
	}

	return $channel;
}

/**
 * Render a complete JSON Feed with Byline extensions.
 *
 * Standalone fallback for sites without a dedicated JSON Feed plugin.
 * Produces a valid JSON Feed 1.1 document with Byline extension
 * properties on both feed-level and item-level author entries.
 */
function render_json_feed(): void {
	header( 'Content-Type: application/feed+json; charset=' . get_option( 'blog_charset' ), true );

	$posts_per_feed = (int) get_option( 'posts_per_rss', 10 );

	$posts = get_posts(
		array(
			'numberposts' => $posts_per_feed,
			'post_status' => 'publish',
			'post_type'   => get_post_types( array( 'public' => true ) ),
		)
	);

	/*
	 * Contributor pre-pass: collect unique authors across all items
	 * for the feed-level authors array. This mirrors the XML output
	 * pattern where <byline:contributors> in the channel head contains
	 * a <byline:person> for each unique author in the feed.
	 *
	 * In JSON Feed, this maps to the top-level "authors" array.
	 * Unlike XML, there's no ref/id indirection — but we still benefit
	 * from having the complete author set at feed level so readers can
	 * display a "contributors" panel without parsing every item.
	 */
	$seen         = array();
	$feed_authors = array();
	$post_authors = array(); // Cache for per-item output.

	foreach ( $posts as $post ) {
		$authors                   = byline_feed_get_authors( $post );
		$post_authors[ $post->ID ] = $authors;

		foreach ( $authors as $author ) {
			if ( ! isset( $seen[ $author->id ] ) ) {
				$seen[ $author->id ] = true;
				$feed_authors[]      = build_json_author( $author );
			}
		}
	}

	$items = array();
	foreach ( $posts as $post ) {
		$authors = $post_authors[ $post->ID ];

		$item = array(
			'id'             => get_the_guid( $post ),
			'url'            => get_permalink( $post ),
			'title'          => get_the_title( $post ),
			'content_html'   => apply_filters( 'the_content', $post->post_content ),
			'date_published' => get_the_date( 'c', $post ),
			'date_modified'  => get_the_modified_date( 'c', $post ),
		);

		if ( ! empty( $authors ) ) {
			$item['authors'] = array_map(
				function ( $author ) use ( $post ) {
					return build_json_author( $author, $post );
				},
				$authors
			);
		}

		$perspective = byline_feed_get_perspective( $post );
		if ( '' !== $perspective ) {
			$item['_byline'] = array( 'perspective' => $perspective );
		}

		/**
		 * Filters a single JSON Feed item before it's added to the feed.
		 *
		 * @param array    $item The item array.
		 * @param \WP_Post $post The WordPress post.
		 */
		$items[] = apply_filters( 'byline_feed_json_item', $item, $post );
	}

	$feed = array(
		'version'       => 'https://jsonfeed.org/version/1.1',
		'title'         => get_bloginfo( 'name' ),
		'home_page_url' => home_url( '/' ),
		'feed_url'      => home_url( '/feed/json' ),
		'description'   => get_bloginfo( 'description' ),
		'language'      => get_bloginfo( 'language' ),
		'authors'       => $feed_authors,
		'_byline'       => array(
			'spec_version' => '1.0',
			'org'          => array_filter(
				array(
					'name' => get_bloginfo( 'name' ),
					'url'  => get_bloginfo( 'url' ),
				)
			),
		),
		'items'         => $items,
	);

	/**
	 * Filters the complete JSON Feed output before encoding.
	 *
	 * @param array $feed The complete feed array.
	 */
	$feed = apply_filters( 'byline_feed_json_feed', $feed );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	echo wp_json_encode( $feed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}
