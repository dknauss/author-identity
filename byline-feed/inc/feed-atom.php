<?php
/**
 * Atom Byline output hooks.
 *
 * Adds the Byline XML namespace and per-entry author metadata
 * to WordPress Atom feeds.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Feed_Atom;

use function Byline_Feed\byline_feed_get_authors;
use function Byline_Feed\byline_feed_get_perspective;
use function Byline_Feed\Feed_RSS2\esc_xml_value;

defined( 'ABSPATH' ) || exit;

/**
 * Register Atom feed hooks.
 */
function register_hooks(): void {
	add_action( 'atom_ns', __NAMESPACE__ . '\\output_namespace' );
	add_action( 'atom_head', __NAMESPACE__ . '\\output_contributors' );
	add_action( 'atom_entry', __NAMESPACE__ . '\\output_entry' );
}

/**
 * Declare the Byline XML namespace on the <feed> element.
 */
function output_namespace(): void {
	echo ' xmlns:byline="https://bylinespec.org/1.0"' . "\n";
}

/**
 * Output <byline:contributors> in the feed head.
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
	 * Fires after the <byline:contributors> block is output in the Atom feed head.
	 */
	do_action( 'byline_feed_after_atom_contributors' );
}

/**
 * Output a single <byline:person> element.
 *
 * @param object $author Normalized author object.
 */
function output_person( object $author ): void {
	$id = esc_attr( $author->id );

	$xml  = "\t\t\t<byline:person id=\"{$id}\">\n";
	$xml .= "\t\t\t\t<byline:name>" . esc_xml_value( $author->display_name ) . "</byline:name>\n";

	if ( ! empty( $author->description ) ) {
		$context = mb_substr( wp_strip_all_tags( $author->description ), 0, 280 );
		$xml    .= "\t\t\t\t<byline:context>" . esc_xml_value( $context ) . "</byline:context>\n";
	}

	if ( ! empty( $author->url ) ) {
		$xml .= "\t\t\t\t<byline:url>" . esc_url( $author->url ) . "</byline:url>\n";
	}

	if ( ! empty( $author->avatar_url ) ) {
		$xml .= "\t\t\t\t<byline:avatar>" . esc_url( $author->avatar_url ) . "</byline:avatar>\n";
	}

	$xml .= "\t\t\t</byline:person>\n";

	/**
	 * Filters the XML for a <byline:person> element.
	 *
	 * @param string $xml    The person XML.
	 * @param object $author The normalized author object.
	 */
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML payload is escaped before filter application.
	echo apply_filters( 'byline_feed_person_xml', $xml, $author );
}

/**
 * Output per-entry Byline elements.
 */
function output_entry(): void {
	$post = get_post();

	if ( ! $post ) {
		return;
	}

	$authors = byline_feed_get_authors( $post );
	$xml     = '';

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
	 * Filters the per-entry Byline XML output.
	 *
	 * @param string   $xml     The entry XML.
	 * @param \WP_Post $post    The post.
	 * @param object[] $authors The normalized author array.
	 */
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML payload is escaped before filter application.
	echo apply_filters( 'byline_feed_item_xml', $xml, $post, $authors );

	/**
	 * Fires after per-entry Byline elements are output.
	 */
	do_action( 'byline_feed_after_atom_entry' );
}
