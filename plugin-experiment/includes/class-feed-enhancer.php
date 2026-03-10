<?php
/**
 * Feed enhancer class — enriches RSS/Atom feeds with author identity data.
 *
 * @package AuthorIdentity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enriches WordPress RSS2 / Atom feeds with structured author identity.
 */
class Author_Identity_Feed_Enhancer {

	/**
	 * Registers WordPress hooks.
	 */
	public function __construct() {
		// Add author identity block to the end of each feed item's content.
		add_filter( 'the_content_feed', array( $this, 'append_author_card' ), 20 );
		// Add custom channel-level elements (RSS2 only).
		add_action( 'rss2_head', array( $this, 'output_rss2_author_channel' ) );
		// Add per-item author metadata to RSS2 items.
		add_action( 'rss2_item', array( $this, 'output_rss2_author_item' ) );
	}

	/**
	 * Appends a plain-text author card to feed item content.
	 *
	 * @param string $content Post content for the feed.
	 * @return string Modified content.
	 */
	public function append_author_card( string $content ): string {
		$post      = get_post();
		$author_id = $post ? (int) $post->post_author : 0;

		$structured_data = new Author_Identity_Structured_Data();
		$identity        = $structured_data->get_identity( $author_id );

		if ( empty( $identity['name'] ) ) {
			return $content;
		}

		$card = $this->build_author_card( $identity );
		return $content . $card;
	}

	/**
	 * Outputs author identity channel-level elements for RSS2 feeds.
	 */
	public function output_rss2_author_channel(): void {
		$site  = (array) get_option( Author_Identity_Admin::OPTION_KEY, array() );
		$name  = ! empty( $site['name'] ) ? $site['name'] : '';
		$email = ! empty( $site['email'] ) ? $site['email'] : '';

		if ( empty( $name ) ) {
			return;
		}

		// RSS 2.0 spec requires "email (name)" format; omit when email is absent.
		if ( ! empty( $email ) ) {
			echo '<managingEditor>' . esc_html( $email ) . ' (' . esc_html( $name ) . ')</managingEditor>' . "\n";
		}
	}

	/**
	 * Outputs per-item author dc:creator elements for RSS2 feeds.
	 */
	public function output_rss2_author_item(): void {
		$post      = get_post();
		$author_id = $post ? (int) $post->post_author : 0;

		$structured_data = new Author_Identity_Structured_Data();
		$identity        = $structured_data->get_identity( $author_id );

		if ( empty( $identity['name'] ) ) {
			return;
		}

		echo '<dc:creator><![CDATA[' . esc_html( $identity['name'] ) . ']]></dc:creator>' . "\n";
	}

	/**
	 * Builds an HTML author card for appending to feed item content.
	 *
	 * @param array<string, string> $identity Resolved identity array.
	 * @return string HTML markup.
	 */
	private function build_author_card( array $identity ): string {
		$lines = array();
		$lines[] = '<hr />';
		$lines[] = '<p><strong>' . esc_html__( 'About the Author', 'author-identity' ) . '</strong></p>';

		$name_part = esc_html( $identity['name'] );
		if ( ! empty( $identity['url'] ) ) {
			$name_part = '<a href="' . esc_url( $identity['url'] ) . '">' . $name_part . '</a>';
		}
		$lines[] = '<p>' . $name_part . '</p>';

		if ( ! empty( $identity['description'] ) ) {
			$lines[] = '<p>' . esc_html( $identity['description'] ) . '</p>';
		}

		$social_links = array();
		if ( ! empty( $identity['mastodon'] ) ) {
			$social_links[] = '<a href="' . esc_url( $identity['mastodon'] ) . '">Mastodon</a>';
		}
		if ( ! empty( $identity['linkedin'] ) ) {
			$social_links[] = '<a href="' . esc_url( $identity['linkedin'] ) . '">LinkedIn</a>';
		}
		if ( ! empty( $identity['github'] ) ) {
			$social_links[] = '<a href="' . esc_url( $identity['github'] ) . '">GitHub</a>';
		}
		if ( ! empty( $identity['twitter'] ) ) {
			$handle         = ltrim( $identity['twitter'], '@' );
			$social_links[] = '<a href="' . esc_url( 'https://twitter.com/' . $handle ) . '">X / Twitter</a>';
		}

		if ( ! empty( $social_links ) ) {
			$lines[] = '<p>' . implode( ' &middot; ', $social_links ) . '</p>';
		}

		return implode( "\n", $lines ) . "\n";
	}
}
