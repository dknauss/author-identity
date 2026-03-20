<?php
/**
 * JSON-LD schema output — mode dispatch and shared builders.
 *
 * Three modes:
 * (A) Yoast SEO active → enrich Yoast's schema graph via filters.
 * (B) Rank Math active → enrich Rank Math's JSON-LD via filter.
 * (C) No SEO plugin   → standalone Article + Person JSON-LD block.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Schema;

use WP_Post;
use function Byline_Feed\byline_feed_get_authors;
use function Byline_Feed\byline_feed_get_perspective;

defined( 'ABSPATH' ) || exit;

/**
 * Register hooks for schema output based on detected mode.
 *
 * Called from bootstrap(). At this point plugins_loaded has fired,
 * so SEO plugin constants and classes are available for detection.
 */
function register_hooks(): void {
	$mode = detect_schema_mode();

	/**
	 * Filters the schema output mode.
	 *
	 * @param string $mode Detected mode: 'yoast', 'rankmath', or 'standalone'.
	 */
	$mode = apply_filters( 'byline_feed_schema_mode', $mode );

	switch ( $mode ) {
		case 'yoast':
			require_once BYLINE_FEED_PLUGIN_DIR . 'inc/schema-yoast.php';
			\Byline_Feed\Schema_Yoast\register_hooks();
			break;

		case 'rankmath':
			require_once BYLINE_FEED_PLUGIN_DIR . 'inc/schema-rankmath.php';
			\Byline_Feed\Schema_Rankmath\register_hooks();
			break;

		default:
			add_action( 'wp_head', __NAMESPACE__ . '\\render_schema_script' );
			break;
	}
}

/**
 * Detect which schema mode to use.
 *
 * @return string 'yoast', 'rankmath', or 'standalone'.
 */
function detect_schema_mode(): string {
	if ( is_yoast_active() ) {
		return 'yoast';
	}

	if ( is_rankmath_active() ) {
		return 'rankmath';
	}

	return 'standalone';
}

/**
 * Check if Yoast SEO is active with schema support.
 *
 * @return bool
 */
function is_yoast_active(): bool {
	// Check active plugins list.
	$active_plugins = get_option( 'active_plugins', array() );

	if ( ! is_array( $active_plugins ) ) {
		$active_plugins = array();
	}

	$sitewide_plugins = get_site_option( 'active_sitewide_plugins', array() );

	if ( ! is_array( $sitewide_plugins ) ) {
		$sitewide_plugins = array();
	}

	$plugin_file = 'wordpress-seo/wp-seo.php';

	$file_active = in_array( $plugin_file, $active_plugins, true )
		|| isset( $sitewide_plugins[ $plugin_file ] );

	if ( ! $file_active && ! defined( 'WPSEO_VERSION' ) && ! class_exists( 'WPSEO_Options' ) ) {
		return false;
	}

	return true;
}

/**
 * Check if Rank Math is active.
 *
 * @return bool
 */
function is_rankmath_active(): bool {
	$active_plugins = get_option( 'active_plugins', array() );

	if ( ! is_array( $active_plugins ) ) {
		$active_plugins = array();
	}

	$sitewide_plugins = get_site_option( 'active_sitewide_plugins', array() );

	if ( ! is_array( $sitewide_plugins ) ) {
		$sitewide_plugins = array();
	}

	$plugin_file = 'seo-by-rank-math/rank-math.php';

	$file_active = in_array( $plugin_file, $active_plugins, true )
		|| isset( $sitewide_plugins[ $plugin_file ] );

	if ( ! $file_active && ! defined( 'RANK_MATH_VERSION' ) && ! defined( 'RANK_MATH_FILE' ) && ! class_exists( 'RankMath' ) ) {
		return false;
	}

	return true;
}

// ────────────────────────────────────────────────────────────────
// Mode C: Standalone output (no SEO plugin)
// ────────────────────────────────────────────────────────────────

/**
 * Render the JSON-LD script for the current singular post.
 *
 * Only fires in standalone mode (no Yoast/Rank Math).
 */
function render_schema_script(): void {
	if ( ! is_singular() ) {
		return;
	}

	$post = get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		return;
	}

	/**
	 * Filters whether Byline Feed should emit its standalone JSON-LD Article graph.
	 *
	 * Only applies to standalone mode. In Yoast/Rank Math modes, enrichment
	 * is controlled by those plugins' own filter systems.
	 *
	 * @param bool    $enabled Whether schema output is enabled.
	 * @param WP_Post $post    Current singular post.
	 */
	$enabled = apply_filters( 'byline_feed_schema_enabled', true, $post );

	if ( ! $enabled ) {
		return;
	}

	$schema = get_article_schema_for_post( $post );

	if ( empty( $schema ) ) {
		return;
	}

	echo "<script type=\"application/ld+json\">\n";
	echo wp_json_encode(
		$schema,
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);
	echo "\n</script>\n";
}

// ────────────────────────────────────────────────────────────────
// Shared builders — used by all three modes
// ────────────────────────────────────────────────────────────────

/**
 * Build a schema.org Person object from a normalized author object.
 *
 * Used by standalone, Yoast, and Rank Math modes. Includes bylineRole,
 * aiTrainingConsent, sameAs from profiles + fediverse + ap_actor_url.
 *
 * @param object $author Normalized author object.
 * @return array<string, mixed>
 */
function get_person_schema( object $author ): array {
	$person = array(
		'@type' => 'Person',
		'name'  => $author->display_name,
	);

	$url = get_person_url( $author );

	if ( '' !== $url ) {
		$person['url'] = $url;
	}

	if ( isset( $author->description ) && is_string( $author->description ) && '' !== $author->description ) {
		$person['description'] = wp_strip_all_tags( $author->description );
	}

	if ( isset( $author->avatar_url ) && is_string( $author->avatar_url ) && '' !== $author->avatar_url ) {
		$person['image'] = esc_url_raw( $author->avatar_url );
	}

	$same_as = get_same_as_urls( $author );

	if ( ! empty( $same_as ) ) {
		$person['sameAs'] = $same_as;
	}

	// additionalProperty: bylineRole and aiTrainingConsent (omitted if empty).
	$additional = array();

	if ( isset( $author->role ) && is_string( $author->role ) && '' !== $author->role ) {
		$additional[] = array(
			'@type' => 'PropertyValue',
			'name'  => 'bylineRole',
			'value' => $author->role,
		);
	}

	if ( isset( $author->ai_consent ) && is_string( $author->ai_consent ) && '' !== $author->ai_consent ) {
		$additional[] = array(
			'@type' => 'PropertyValue',
			'name'  => 'aiTrainingConsent',
			'value' => $author->ai_consent,
		);
	}

	if ( ! empty( $additional ) ) {
		$person['additionalProperty'] = $additional;
	}

	/**
	 * Filters the JSON-LD Person object for a normalized author.
	 *
	 * Fires in all three modes (Yoast, Rank Math, standalone).
	 *
	 * @param array<string, mixed> $person JSON-LD Person array.
	 * @param object               $author Normalized author object.
	 */
	$person = apply_filters( 'byline_feed_schema_person', $person, $author );

	return is_array( $person ) ? $person : array();
}

/**
 * Build an Article schema object for a singular post.
 *
 * Used by standalone mode. Yoast and Rank Math modes enrich
 * the Article node via their respective filter hooks instead.
 *
 * @param WP_Post $post Post object.
 * @return array<string, mixed>
 */
function get_article_schema_for_post( WP_Post $post ): array {
	$post_type = get_post_type_object( $post->post_type );

	if ( ! $post_type || ! $post_type->public ) {
		return array();
	}

	$authors = byline_feed_get_authors( $post );

	if ( empty( $authors ) ) {
		return array();
	}

	$person_objects = array();

	foreach ( $authors as $author ) {
		$person = get_person_schema( $author );

		if ( ! empty( $person ) ) {
			$person_objects[] = $person;
		}
	}

	if ( empty( $person_objects ) ) {
		return array();
	}

	$article = array(
		'@context'      => 'https://schema.org',
		'@type'         => 'Article',
		'headline'      => wp_strip_all_tags( get_the_title( $post ) ),
		'datePublished' => get_post_time( DATE_W3C, true, $post ),
		'dateModified'  => get_post_modified_time( DATE_W3C, true, $post ),
		'url'           => get_permalink( $post ),
		'author'        => $person_objects,
		'publisher'     => get_publisher_schema(),
	);

	// Add bylinePerspective as Article-level additionalProperty when WP-03 meta is set.
	$perspective = byline_feed_get_perspective( $post );

	if ( '' !== $perspective ) {
		$article['additionalProperty'] = array(
			array(
				'@type' => 'PropertyValue',
				'name'  => 'bylinePerspective',
				'value' => $perspective,
			),
		);
	}

	/**
	 * Filters the full JSON-LD Article object before output.
	 *
	 * @param array<string, mixed> $article Article schema array.
	 * @param WP_Post              $post    Current singular post.
	 */
	$article = apply_filters( 'byline_feed_schema_article', $article, $post );

	return is_array( $article ) ? $article : array();
}

/**
 * Resolve the canonical schema URL for an author.
 *
 * @param object $author Normalized author object.
 * @return string
 */
function get_person_url( object $author ): string {
	if ( isset( $author->url ) && is_string( $author->url ) && '' !== $author->url ) {
		return esc_url_raw( $author->url );
	}

	if ( ! empty( $author->user_id ) ) {
		return esc_url_raw( get_author_posts_url( (int) $author->user_id ) );
	}

	return '';
}

/**
 * Return schema.org sameAs URLs for an author.
 *
 * Collects URLs from profile links, fediverse handle (resolved to canonical
 * HTTPS URL), and AP actor URL. Deduplicates.
 *
 * @param object $author Normalized author object.
 * @return string[]
 */
function get_same_as_urls( object $author ): array {
	$urls = array();

	if ( isset( $author->profiles ) && is_array( $author->profiles ) ) {
		foreach ( $author->profiles as $profile ) {
			if ( ! is_array( $profile ) ) {
				continue;
			}

			$href = isset( $profile['href'] ) && is_string( $profile['href'] ) ? esc_url_raw( $profile['href'] ) : '';

			if ( '' === $href || in_array( $href, $urls, true ) ) {
				continue;
			}

			$urls[] = $href;
		}
	}

	// Fediverse handle → canonical profile URL in sameAs.
	if ( isset( $author->fediverse ) && is_string( $author->fediverse ) && '' !== $author->fediverse ) {
		$fediverse_url = fediverse_profile_url( $author->fediverse );

		if ( '' !== $fediverse_url && ! in_array( $fediverse_url, $urls, true ) ) {
			$urls[] = $fediverse_url;
		}
	}

	if ( isset( $author->ap_actor_url ) && is_string( $author->ap_actor_url ) ) {
		$actor_url = esc_url_raw( $author->ap_actor_url );

		if ( '' !== $actor_url && ! in_array( $actor_url, $urls, true ) ) {
			$urls[] = $actor_url;
		}
	}

	return $urls;
}

/**
 * Resolve a fediverse handle to a canonical profile URL.
 *
 * Converts `@user@instance` to `https://instance/@user`. This is a simple
 * string transform, not a WebFinger lookup. Most Mastodon-compatible servers
 * serve profile pages at `/@username`.
 *
 * @param string $handle Fediverse handle (e.g., `@user@mastodon.social`).
 * @return string Canonical HTTPS URL, or empty string if handle cannot be parsed.
 */
function fediverse_profile_url( string $handle ): string {
	// Strip leading @ if present.
	$handle = ltrim( $handle, '@' );

	$parts = explode( '@', $handle );

	if ( count( $parts ) !== 2 || '' === $parts[0] || '' === $parts[1] ) {
		return '';
	}

	$user     = $parts[0];
	$instance = $parts[1];

	// Validate instance looks like a domain.
	if ( ! preg_match( '/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $instance ) ) {
		return '';
	}

	return esc_url_raw( 'https://' . $instance . '/@' . $user );
}

/**
 * Build the Article-level additionalProperty array for perspective.
 *
 * Shared helper used by Yoast and Rank Math enrichment modes.
 *
 * @param WP_Post $post Post object.
 * @return array Empty array if no perspective, otherwise array with one PropertyValue.
 */
function get_perspective_additional_property( WP_Post $post ): array {
	$perspective = byline_feed_get_perspective( $post );

	if ( '' === $perspective ) {
		return array();
	}

	return array(
		array(
			'@type' => 'PropertyValue',
			'name'  => 'bylinePerspective',
			'value' => $perspective,
		),
	);
}

/**
 * Build the schema publisher organization.
 *
 * @return array<string, mixed>
 */
function get_publisher_schema(): array {
	$publisher = array(
		'@type' => 'Organization',
		'name'  => get_bloginfo( 'name' ),
		'url'   => home_url( '/' ),
	);

	$site_icon = get_site_icon_url();

	if ( is_string( $site_icon ) && '' !== $site_icon ) {
		$publisher['logo'] = array(
			'@type' => 'ImageObject',
			'url'   => esc_url_raw( $site_icon ),
		);
	}

	return $publisher;
}
