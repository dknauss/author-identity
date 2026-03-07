<?php
/**
 * Structured data class — outputs JSON-LD schema.org/Person markup.
 *
 * @package AuthorIdentity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outputs JSON-LD structured data for the post/page author.
 */
class Author_Identity_Structured_Data {

	/**
	 * Registers WordPress hooks.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'output_json_ld' ) );
	}

	/**
	 * Outputs the JSON-LD block in <head> on singular posts/pages.
	 */
	public function output_json_ld(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post      = get_post();
		$author_id = $post ? (int) $post->post_author : 0;
		$data      = $this->build_json_ld( $author_id );

		if ( empty( $data ) ) {
			return;
		}

		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		if ( false === $json ) {
			return;
		}

		echo '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>' . "\n";
	}

	/**
	 * Builds the schema.org/Person JSON-LD array for a given author.
	 *
	 * @param int $author_id WordPress user ID of the post author.
	 * @return array<string, mixed>
	 */
	public function build_json_ld( int $author_id ): array {
		$identity = $this->get_identity( $author_id );

		if ( empty( $identity['name'] ) ) {
			return array();
		}

		$person = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Person',
			'name'     => $identity['name'],
		);

		if ( ! empty( $identity['url'] ) ) {
			$person['url'] = $identity['url'];
		}

		if ( ! empty( $identity['email'] ) ) {
			$person['email'] = $identity['email'];
		}

		if ( ! empty( $identity['description'] ) ) {
			$person['description'] = $identity['description'];
		}

		if ( ! empty( $identity['job_title'] ) ) {
			$person['jobTitle'] = $identity['job_title'];
		}

		if ( ! empty( $identity['organization'] ) ) {
			$org = array(
				'@type' => 'Organization',
				'name'  => $identity['organization'],
			);
			if ( ! empty( $identity['organization_url'] ) ) {
				$org['url'] = $identity['organization_url'];
			}
			$person['worksFor'] = $org;
		}

		$same_as = $this->build_same_as( $identity );
		if ( ! empty( $same_as ) ) {
			$person['sameAs'] = $same_as;
		}

		return $person;
	}

	/**
	 * Returns the resolved author identity for the given user, with site-wide
	 * settings as fallbacks.
	 *
	 * @param int $author_id WordPress user ID.
	 * @return array<string, string>
	 */
	public function get_identity( int $author_id ): array {
		$site     = (array) get_option( Author_Identity_Admin::OPTION_KEY, array() );
		$identity = array();

		// Fields that map directly from user meta to the identity array.
		$user_meta_map = array(
			'job_title'        => 'author_identity_job_title',
			'organization'     => 'author_identity_organization',
			'organization_url' => 'author_identity_organization_url',
			'mastodon'         => 'author_identity_mastodon',
			'fediverse_creator' => 'author_identity_fediverse_creator',
			'twitter'          => 'author_identity_twitter',
			'linkedin'         => 'author_identity_linkedin',
			'github'           => 'author_identity_github',
			'same_as'          => 'author_identity_same_as',
		);

		// Core WP user data.
		if ( $author_id > 0 ) {
			$user                    = get_userdata( $author_id );
			$identity['name']        = $user ? $user->display_name : '';
			$identity['url']         = $user ? get_author_posts_url( $author_id ) : '';
			$identity['email']       = $user ? $user->user_email : '';
			$identity['description'] = $user ? $user->description : '';

			foreach ( $user_meta_map as $key => $meta_key ) {
				$identity[ $key ] = (string) get_user_meta( $author_id, $meta_key, true );
			}
		}

		// Fall back to site-wide settings for any empty field.
		$fallback_keys = array_merge(
			array( 'name', 'url', 'email', 'description' ),
			array_keys( $user_meta_map )
		);
		foreach ( $fallback_keys as $key ) {
			if ( empty( $identity[ $key ] ) && ! empty( $site[ $key ] ) ) {
				$identity[ $key ] = $site[ $key ];
			}
		}

		return $identity;
	}

	/**
	 * Builds the sameAs URL array from various social profile fields.
	 *
	 * @param array<string, string> $identity Resolved identity array.
	 * @return list<string>
	 */
	private function build_same_as( array $identity ): array {
		$urls = array();

		foreach ( array( 'mastodon', 'linkedin', 'github' ) as $key ) {
			if ( ! empty( $identity[ $key ] ) ) {
				$urls[] = $identity[ $key ];
			}
		}

		if ( ! empty( $identity['twitter'] ) ) {
			$handle = ltrim( $identity['twitter'], '@' );
			$urls[] = 'https://twitter.com/' . $handle;
		}

		if ( ! empty( $identity['same_as'] ) ) {
			foreach ( preg_split( '/\r\n|\r|\n/', $identity['same_as'] ) as $line ) {
				$line = trim( $line );
				if ( '' !== $line ) {
					$urls[] = $line;
				}
			}
		}

		return array_values( array_unique( $urls ) );
	}
}
