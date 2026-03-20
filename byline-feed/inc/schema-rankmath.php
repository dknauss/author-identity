<?php
/**
 * Schema enrichment for Rank Math (Mode B).
 *
 * Hooks into Rank Math's `rank_math/json_ld` filter to inject multi-author
 * arrays and byline-specific signals into Rank Math's JSON-LD output.
 *
 * Rank Math does not have per-node Person filters like Yoast's
 * `wpseo_schema_person_data`. Person enrichment (sameAs, role, consent)
 * is applied inline via the shared `get_person_schema()` builder.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Schema_Rankmath;

use WP_Post;
use function Byline_Feed\byline_feed_get_authors;
use function Byline_Feed\Schema\get_person_schema;
use function Byline_Feed\Schema\get_perspective_additional_property;

defined( 'ABSPATH' ) || exit;

/**
 * Register Rank Math schema enrichment filter.
 */
function register_hooks(): void {
	add_filter( 'rank_math/json_ld', __NAMESPACE__ . '\\enrich_json_ld', 10, 2 );
}

/**
 * Enrich Rank Math's JSON-LD output with multi-author data and perspective.
 *
 * Iterates the assembled JSON-LD data array looking for Article nodes.
 * Replaces each Article's author with the full multi-author Person array
 * and adds bylinePerspective.
 *
 * @param array  $data   Rank Math's assembled JSON-LD data array.
 * @param object $jsonld Rank Math's JsonLD object.
 * @return array Modified data array.
 */
function enrich_json_ld( array $data, $jsonld ): array {
	if ( ! is_singular() ) {
		return $data;
	}

	$post = get_post();

	if ( ! $post instanceof WP_Post ) {
		return $data;
	}

	$authors = byline_feed_get_authors( $post );

	if ( empty( $authors ) ) {
		return $data;
	}

	$person_objects = array_map(
		function ( $author ) {
			return get_person_schema( $author );
		},
		$authors
	);

	foreach ( $data as $key => $node ) {
		if ( ! is_array( $node ) || ! isset( $node['@type'] ) ) {
			continue;
		}

		$types = (array) $node['@type'];

		if ( ! in_array( 'Article', $types, true ) && ! in_array( 'BlogPosting', $types, true ) && ! in_array( 'NewsArticle', $types, true ) ) {
			continue;
		}

		// Replace Rank Math's author with our multi-author array.
		$data[ $key ]['author'] = $person_objects;

		// Add bylinePerspective as Article-level additionalProperty.
		$perspective_props = get_perspective_additional_property( $post );

		if ( ! empty( $perspective_props ) ) {
			if ( ! isset( $data[ $key ]['additionalProperty'] ) || ! is_array( $data[ $key ]['additionalProperty'] ) ) {
				$data[ $key ]['additionalProperty'] = array();
			}

			$data[ $key ]['additionalProperty'] = array_merge(
				$data[ $key ]['additionalProperty'],
				$perspective_props
			);
		}
	}

	/**
	 * Filters the enriched Rank Math JSON-LD data.
	 *
	 * @param array    $data    Full JSON-LD data array after enrichment.
	 * @param WP_Post  $post    The current post.
	 * @param object[] $authors Normalized author objects.
	 */
	return apply_filters( 'byline_feed_rankmath_json_ld', $data, $post, $authors );
}
