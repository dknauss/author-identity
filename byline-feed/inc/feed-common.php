<?php
/**
 * Shared feed output helpers.
 *
 * Functions used by multiple feed output layers (RSS2, Atom, JSON).
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Feed_Common;

defined( 'ABSPATH' ) || exit;

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
		$context = mb_substr( wp_strip_all_tags( $author->description ), 0, 280, 'UTF-8' );
		$xml    .= "\t\t\t\t<byline:context>" . esc_xml_value( $context ) . "</byline:context>\n";
	}

	if ( ! empty( $author->url ) ) {
		$xml .= "\t\t\t\t<byline:url>" . esc_url( $author->url ) . "</byline:url>\n";
	}

	if ( ! empty( $author->avatar_url ) ) {
		$xml .= "\t\t\t\t<byline:avatar>" . esc_url( $author->avatar_url ) . "</byline:avatar>\n";
	}

	if ( ! empty( $author->profiles ) && is_array( $author->profiles ) ) {
		foreach ( $author->profiles as $profile ) {
			$href = isset( $profile['href'] ) ? esc_url( $profile['href'] ) : '';
			$rel  = isset( $profile['rel'] ) ? esc_attr( $profile['rel'] ) : '';

			if ( '' === $href || '' === $rel ) {
				continue;
			}

			$xml .= "\t\t\t\t<byline:profile href=\"{$href}\" rel=\"{$rel}\"/>\n";
		}
	}

	if ( ! empty( $author->now_url ) ) {
		$xml .= "\t\t\t\t<byline:now>" . esc_url( $author->now_url ) . "</byline:now>\n";
	}

	if ( ! empty( $author->uses_url ) ) {
		$xml .= "\t\t\t\t<byline:uses>" . esc_url( $author->uses_url ) . "</byline:uses>\n";
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
 * Escape a string for XML output.
 *
 * Uses esc_xml() when available (WP 5.5+), falls back to esc_html().
 *
 * @param string $text The text to escape.
 * @return string Escaped text.
 */
function esc_xml_value( string $text ): string {
	if ( function_exists( 'esc_xml' ) ) {
		return esc_xml( $text );
	}
	return esc_html( $text );
}
