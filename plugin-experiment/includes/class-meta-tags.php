<?php
/**
 * Meta tags class — outputs Open Graph and fediverse author meta tags.
 *
 * @package AuthorIdentity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outputs <meta> tags for author identity on singular posts/pages.
 */
class Author_Identity_Meta_Tags {

	/**
	 * Registers WordPress hooks.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'output_meta_tags' ) );
	}

	/**
	 * Outputs author meta tags in <head> on singular posts/pages.
	 */
	public function output_meta_tags(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post      = get_post();
		$author_id = $post ? (int) $post->post_author : 0;

		// Reuse the identity resolution from the structured data class.
		$structured_data = new Author_Identity_Structured_Data();
		$identity        = $structured_data->get_identity( $author_id );

		if ( empty( $identity['name'] ) ) {
			return;
		}

		// --- Standard author meta ---
		$this->meta( 'author', $identity['name'] );

		// --- Article author (Open Graph) ---
		if ( ! empty( $identity['url'] ) ) {
			$this->meta_property( 'article:author', $identity['url'] );
		}

		// --- Fediverse creator (for Mastodon link verification) ---
		if ( ! empty( $identity['fediverse_creator'] ) ) {
			$this->meta( 'fediverse:creator', $identity['fediverse_creator'] );
		} elseif ( ! empty( $identity['mastodon'] ) ) {
			// Derive a @user@domain handle from the Mastodon profile URL as a
			// best-effort fallback (e.g. https://mastodon.social/@alice → @alice@mastodon.social).
			$handle = $this->mastodon_url_to_handle( $identity['mastodon'] );
			if ( '' !== $handle ) {
				$this->meta( 'fediverse:creator', $handle );
			}
		}
	}

	/**
	 * Emits a standard <meta name="…" content="…"> tag.
	 *
	 * @param string $name    Meta name attribute.
	 * @param string $content Meta content attribute.
	 */
	private function meta( string $name, string $content ): void {
		printf(
			'<meta name="%s" content="%s" />' . "\n",
			esc_attr( $name ),
			esc_attr( $content )
		);
	}

	/**
	 * Emits a <meta property="…" content="…"> tag (Open Graph style).
	 *
	 * @param string $property Meta property attribute.
	 * @param string $content  Meta content attribute.
	 */
	private function meta_property( string $property, string $content ): void {
		printf(
			'<meta property="%s" content="%s" />' . "\n",
			esc_attr( $property ),
			esc_attr( $content )
		);
	}

	/**
	 * Converts a Mastodon profile URL to a @user@domain handle.
	 *
	 * Handles the common URL pattern: https://mastodon.social/@alice
	 *
	 * @param string $url Mastodon profile URL.
	 * @return string Handle like @alice@mastodon.social, or empty string on failure.
	 */
	private function mastodon_url_to_handle( string $url ): string {
		$parsed = wp_parse_url( $url );
		if ( ! $parsed || empty( $parsed['host'] ) || empty( $parsed['path'] ) ) {
			return '';
		}

		// Path should start with /@username.
		if ( ! preg_match( '#^/@([^/]+)$#', $parsed['path'], $matches ) ) {
			return '';
		}

		return '@' . $matches[1] . '@' . $parsed['host'];
	}
}
