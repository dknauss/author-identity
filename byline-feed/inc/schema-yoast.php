<?php
/**
 * Schema enrichment for Yoast SEO (Mode A).
 *
 * Hooks into Yoast's schema graph filters to inject multi-author arrays,
 * byline-specific signals (role, perspective, consent), fediverse identity,
 * and profile links into the graph that NLWeb's schemamap endpoint queries.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Schema_Yoast;

use WP_Post;
use function Byline_Feed\byline_feed_get_authors;
use function Byline_Feed\Schema\get_person_schema;
use function Byline_Feed\Schema\get_perspective_additional_property;

defined( 'ABSPATH' ) || exit;

/**
 * Register Yoast schema enrichment filters.
 */
function register_hooks(): void {
	add_filter( 'wpseo_schema_article', __NAMESPACE__ . '\\enrich_article', 10, 2 );
}

/**
 * Enrich the Yoast Article schema node with multi-author data and perspective.
 *
 * Replaces Yoast's single-author @id reference with a full array of Person
 * objects built from the normalized author data. Adds bylinePerspective
 * as an Article-level additionalProperty when WP-03 meta is set.
 *
 * @param array  $data    Yoast's assembled Article schema array.
 * @param object $context Yoast's Meta_Tags_Context object (has ->id for post ID).
 * @return array Modified Article data.
 */
function enrich_article( array $data, $context ): array {
	$post_id = isset( $context->id ) ? (int) $context->id : 0;
	$post    = get_post( $post_id );

	if ( ! $post instanceof WP_Post ) {
		return $data;
	}

	$authors = byline_feed_get_authors( $post );

	if ( empty( $authors ) ) {
		return $data;
	}

	// Replace Yoast's single @id reference with full Person objects.
	// Using inline objects so the data is self-contained when NLWeb
	// ingests the schemamap endpoint.
	$data['author'] = array_map(
		function ( $author ) {
			return get_person_schema( $author );
		},
		$authors
	);

	// Add bylinePerspective as Article-level additionalProperty.
	$perspective_props = get_perspective_additional_property( $post );

	if ( ! empty( $perspective_props ) ) {
		if ( ! isset( $data['additionalProperty'] ) || ! is_array( $data['additionalProperty'] ) ) {
			$data['additionalProperty'] = array();
		}

		$data['additionalProperty'] = array_merge( $data['additionalProperty'], $perspective_props );
	}

	/**
	 * Filters the enriched Article data before Yoast emits it.
	 *
	 * @param array    $data    Enriched Article schema array.
	 * @param WP_Post  $post    The current post.
	 * @param object[] $authors Normalized author objects.
	 */
	return apply_filters( 'byline_feed_yoast_article_data', $data, $post, $authors );
}
