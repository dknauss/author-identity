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
use function Byline_Feed\Feed_Common\esc_xml_value;
use function Byline_Feed\Feed_Common\output_person;
use function Byline_Feed\Rights\get_feed_rights;
use function Byline_Feed\Rights\resolve_ai_consent;
use function Byline_Feed\Rights\get_policy_url;

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

	if ( empty( $wp_query ) || empty( $wp_query->posts ) ) {
		return;
	}

	$seen       = array();
	$persons    = array();
	$feed_posts = array();

	foreach ( $wp_query->posts as $post ) {
		if ( ! $post instanceof \WP_Post ) {
			continue;
		}

			$feed_posts[] = $post;
			$authors      = byline_feed_get_authors( $post );

		foreach ( $authors as $author ) {
			if ( isset( $seen[ $author->id ] ) ) {
				continue;
			}
			$seen[ $author->id ] = true;
			$persons[]           = $author;
		}
	}

		$rights = get_feed_rights( $feed_posts );

	if ( empty( $persons ) && empty( $rights ) ) {
		return;
	}

	if ( ! empty( $persons ) ) {
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

	if ( ! empty( $rights ) ) {
		$xml = "\t\t<byline:rights consent=\"" . esc_attr( $rights['consent'] ) . '"';

		if ( ! empty( $rights['policy'] ) ) {
			$xml .= ' policy="' . esc_url( $rights['policy'] ) . '"';
		}

		$xml .= "/>\n";

		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML attributes are escaped above.
	}
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

	$consent = resolve_ai_consent( $post );
	if ( 'deny' === $consent ) {
		$xml   .= "\t\t<byline:rights consent=\"deny\"";
		$policy = get_policy_url( $post );
		if ( '' !== $policy ) {
			$xml .= ' policy="' . esc_url( $policy ) . '"';
		}
		$xml .= "/>\n";
	}

	/**
	 * Filters the per-entry Byline XML output for Atom feeds.
	 *
	 * Use byline_feed_atom_entry_xml to target Atom entries specifically.
	 * Use byline_feed_item_xml in RSS2 for RSS2 items.
	 *
	 * @param string   $xml     The entry XML.
	 * @param \WP_Post $post    The post.
	 * @param object[] $authors The normalized author array.
	 */
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- XML payload is escaped before filter application.
	echo apply_filters( 'byline_feed_atom_entry_xml', $xml, $post, $authors );

	/**
	 * Fires after per-entry Byline elements are output.
	 */
	do_action( 'byline_feed_after_atom_entry' );
}
