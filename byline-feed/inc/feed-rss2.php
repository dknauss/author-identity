<?php
/**
 * RSS2 Byline output hooks.
 *
 * Adds the Byline XML namespace, channel-level <byline:contributors>,
 * and per-item <byline:author>, <byline:role>, and <byline:perspective>
 * to WordPress RSS2 feeds.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Feed_RSS2;

use function Byline_Feed\byline_feed_get_authors;
use function Byline_Feed\byline_feed_get_perspective;
use function Byline_Feed\Feed_Common\esc_xml_value;
use function Byline_Feed\Feed_Common\output_person;

defined( 'ABSPATH' ) || exit;

/**
 * Register RSS2 feed hooks.
 */
function register_hooks(): void {
	add_action( 'rss2_ns', __NAMESPACE__ . '\\output_namespace' );
	add_action( 'rss2_head', __NAMESPACE__ . '\\output_contributors' );
	add_action( 'rss2_item', __NAMESPACE__ . '\\output_item' );
}

/**
 * Declare the Byline XML namespace on the <rss> element.
 */
function output_namespace(): void {
	echo ' xmlns:byline="https://bylinespec.org/1.0"' . "\n";
}

/**
 * Output <byline:contributors> in the channel head.
 *
 * Collects all unique authors across recent posts and outputs
 * a <byline:person> element for each.
 */
function output_contributors(): void {
	global $wp_query;

	if ( empty( $wp_query->posts ) ) {
		return;
	}

	$seen    = array();
	$persons = array();

	foreach ( $wp_query->posts as $post ) {
		$authors = byline_feed_get_authors( $post );

		foreach ( $authors as $author ) {
			if ( isset( $seen[ $author->id ] ) ) {
				continue;
			}
			$seen[ $author->id ] = true;
			$persons[]           = $author;
		}
	}

	if ( empty( $persons ) ) {
		return;
	}

	echo "\t\t<byline:contributors>\n";

	foreach ( $persons as $author ) {
		output_person( $author );
	}

	echo "\t\t</byline:contributors>\n";

	/**
	 * Fires after the <byline:contributors> block is output in the RSS2 head.
	 */
	do_action( 'byline_feed_after_rss2_contributors' );
}

/**
 * Output per-item Byline elements: author refs, roles, and perspective.
 */
function output_item(): void {
	$post = get_post();

	if ( ! $post ) {
		return;
	}

	$authors = byline_feed_get_authors( $post );

	$xml = '';

	foreach ( $authors as $author ) {
		$ref  = esc_attr( $author->id );
		$xml .= "\t\t<byline:author ref=\"{$ref}\"/>\n";

		if ( ! empty( $author->role ) ) {
			$xml .= "\t\t<byline:role>" . esc_xml_value( $author->role ) . "</byline:role>\n";
		}
	}

	$perspective = byline_feed_get_perspective( $post );
	if ( '' !== $perspective ) {
		$xml .= "\t\t<byline:perspective>" . esc_xml_value( $perspective ) . "</byline:perspective>\n";
	}

	/**
	 * Filters the per-item Byline XML output.
	 *
	 * @param string   $xml     The item XML.
	 * @param \WP_Post $post    The post.
	 * @param object[] $authors The normalized author array.
	 */
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML payload is escaped before filter application.
	echo apply_filters( 'byline_feed_item_xml', $xml, $post, $authors );

	/**
	 * Fires after per-item Byline elements are output.
	 */
	do_action( 'byline_feed_after_rss2_item' );
}
