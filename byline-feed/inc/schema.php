<?php
/**
 * JSON-LD schema output.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Schema;

use WP_Post;
use function Byline_Feed\byline_feed_get_authors;

defined( 'ABSPATH' ) || exit;

/**
 * Register hooks for schema output.
 */
function register_hooks(): void {
	add_action( 'wp_head', __NAMESPACE__ . '\\render_schema_script' );
}

/**
 * Render the JSON-LD script for the current singular post when enabled.
 */
function render_schema_script(): void {
	if ( ! is_singular() ) {
		return;
	}

	$post = get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		return;
	}

	if ( ! is_schema_enabled( $post ) ) {
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

/**
 * Determine whether schema output should be enabled for the current post.
 *
 * Known SEO plugins disable this output by default so sites do not emit
 * competing Article graphs unless they opt in via filter.
 *
 * @param WP_Post $post Post object.
 * @return bool
 */
function is_schema_enabled( WP_Post $post ): bool {
	$enabled = ! has_known_schema_plugin_conflict();

	/**
	 * Filters whether Byline Feed should emit its JSON-LD Article graph.
	 *
	 * @param bool    $enabled Whether schema output is enabled.
	 * @param WP_Post $post    Current singular post.
	 */
	$enabled = apply_filters( 'byline_feed_schema_enabled', $enabled, $post );

	return (bool) $enabled;
}

/**
 * Detect known schema-owning SEO plugins.
 *
 * @return bool
 */
function has_known_schema_plugin_conflict(): bool {
	$active_plugins = get_option( 'active_plugins', array() );

	if ( ! is_array( $active_plugins ) ) {
		$active_plugins = array();
	}

	$sitewide_plugins = get_site_option( 'active_sitewide_plugins', array() );

	if ( ! is_array( $sitewide_plugins ) ) {
		$sitewide_plugins = array();
	}

	$known_plugins = array(
		'wordpress-seo/wp-seo.php',
		'seo-by-rank-math/rank-math.php',
	);

	foreach ( $known_plugins as $plugin_file ) {
		if ( in_array( $plugin_file, $active_plugins, true ) || isset( $sitewide_plugins[ $plugin_file ] ) ) {
			return true;
		}
	}

	if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
		return true;
	}

	if ( defined( 'RANK_MATH_VERSION' ) || defined( 'RANK_MATH_FILE' ) || class_exists( 'RankMath' ) ) {
		return true;
	}

	return false;
}

/**
 * Build an Article schema object for a singular post.
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
 * Build a schema.org Person object from a normalized author object.
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

	if ( '' !== $author->description ) {
		$person['description'] = wp_strip_all_tags( $author->description );
	}

	if ( '' !== $author->avatar_url ) {
		$person['image'] = esc_url_raw( $author->avatar_url );
	}

	$same_as = get_same_as_urls( $author );

	if ( ! empty( $same_as ) ) {
		$person['sameAs'] = $same_as;
	}

	/**
	 * Filters the JSON-LD Person object for a normalized author.
	 *
	 * @param array<string, mixed> $person JSON-LD Person array.
	 * @param object               $author Normalized author object.
	 */
	$person = apply_filters( 'byline_feed_schema_person', $person, $author );

	return is_array( $person ) ? $person : array();
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

	if ( isset( $author->ap_actor_url ) && is_string( $author->ap_actor_url ) ) {
		$actor_url = esc_url_raw( $author->ap_actor_url );

		if ( '' !== $actor_url && ! in_array( $actor_url, $urls, true ) ) {
			$urls[] = $actor_url;
		}
	}

	return $urls;
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
