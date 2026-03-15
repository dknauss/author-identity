<?php
/**
 * Tests for JSON-LD schema output.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Tests;

use WP_Post;
use WP_UnitTestCase;
use function Byline_Feed\Schema\render_schema_script;

class Test_Schema extends WP_UnitTestCase {

	/**
	 * Original active plugins option.
	 *
	 * @var mixed
	 */
	private $active_plugins_option;

	/**
	 * Original network active plugins option.
	 *
	 * @var mixed
	 */
	private $active_sitewide_plugins_option;

	public function set_up(): void {
		parent::set_up();

		$this->active_plugins_option          = get_option( 'active_plugins', array() );
		$this->active_sitewide_plugins_option = get_site_option( 'active_sitewide_plugins', array() );
	}

	public function tear_down(): void {
		remove_all_filters( 'byline_feed_authors' );
		remove_all_filters( 'byline_feed_schema_enabled' );
		remove_all_filters( 'byline_feed_schema_person' );
		remove_all_filters( 'byline_feed_schema_article' );

		update_option( 'active_plugins', $this->active_plugins_option );
		update_site_option( 'active_sitewide_plugins', $this->active_sitewide_plugins_option );

		parent::tear_down();
	}

	/**
	 * Capture rendered schema output for a routed request.
	 *
	 * @param string $url Request URL.
	 * @return string
	 */
	private function capture_schema_output( string $url ): string {
		$this->go_to( $url );

		ob_start();
		render_schema_script();
		return (string) ob_get_clean();
	}

	/**
	 * Decode the JSON-LD object from a rendered script tag.
	 *
	 * @param string $output Rendered script tag output.
	 * @return array<string, mixed>
	 */
	private function decode_schema_output( string $output ): array {
		$this->assertStringContainsString( 'application/ld+json', $output );

		$matched = preg_match(
			'/<script type="application\/ld\+json">\s*(.+)\s*<\/script>/s',
			$output,
			$matches
		);

		$this->assertSame( 1, $matched );
		$this->assertJson( $matches[1] );

		$decoded = json_decode( $matches[1], true );
		$this->assertIsArray( $decoded );

		return $decoded;
	}

	public function test_single_author_schema_uses_author_archive_fallback_and_publisher_data(): void {
		update_option( 'blogname', 'Byline Feed Test Site' );

		$user_id = self::factory()->user->create(
			array(
				'display_name' => 'Schema Author',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Schema Single Author',
				'post_author' => $user_id,
			)
		);

		add_filter(
			'byline_feed_authors',
			static function ( array $authors, WP_Post $post ) use ( $post_id, $user_id ): array {
				if ( $post_id !== $post->ID ) {
					return $authors;
				}

				return array(
					(object) array(
						'id'           => 'schema-author',
						'display_name' => 'Schema Author',
						'description'  => 'Staff writer covering identity metadata.',
						'avatar_url'   => 'https://example.com/avatar.jpg',
						'user_id'      => $user_id,
						'profiles'     => array(),
						'ap_actor_url' => '',
					),
				);
			},
			10,
			2
		);

		$schema = $this->decode_schema_output( $this->capture_schema_output( get_permalink( $post_id ) ) );

		$this->assertSame( 'https://schema.org', $schema['@context'] );
		$this->assertSame( 'Article', $schema['@type'] );
		$this->assertSame( 'Schema Single Author', $schema['headline'] );
		$this->assertSame( get_permalink( $post_id ), $schema['url'] );
		$this->assertSame( 'Organization', $schema['publisher']['@type'] );
		$this->assertSame( 'Byline Feed Test Site', $schema['publisher']['name'] );
		$this->assertSame( home_url( '/' ), $schema['publisher']['url'] );
		$this->assertCount( 1, $schema['author'] );
		$this->assertSame( 'Person', $schema['author'][0]['@type'] );
		$this->assertSame( 'Schema Author', $schema['author'][0]['name'] );
		$this->assertSame( get_author_posts_url( $user_id ), $schema['author'][0]['url'] );
		$this->assertSame( 'Staff writer covering identity metadata.', $schema['author'][0]['description'] );
		$this->assertSame( 'https://example.com/avatar.jpg', $schema['author'][0]['image'] );
	}

	public function test_multiple_authors_preserve_order_and_same_as_includes_profiles_and_actor_url(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Schema Multi Author',
			)
		);

		add_filter(
			'byline_feed_authors',
			static function ( array $authors, WP_Post $post ) use ( $post_id ): array {
				if ( $post_id !== $post->ID ) {
					return $authors;
				}

				return array(
					(object) array(
						'id'           => 'lead-author',
						'display_name' => 'Lead Author',
						'profiles'     => array(
							array(
								'href' => 'https://mastodon.social/@lead',
								'rel'  => 'me',
							),
							array(
								'href' => 'https://example.com/about/lead',
								'rel'  => 'author',
							),
						),
						'ap_actor_url' => 'https://example.social/users/lead',
					),
					(object) array(
						'id'           => 'guest-author',
						'display_name' => 'Guest Author',
						'is_guest'     => true,
					),
				);
			},
			10,
			2
		);

		$schema = $this->decode_schema_output( $this->capture_schema_output( get_permalink( $post_id ) ) );

		$this->assertCount( 2, $schema['author'] );
		$this->assertSame( 'Lead Author', $schema['author'][0]['name'] );
		$this->assertSame( 'Guest Author', $schema['author'][1]['name'] );
		$this->assertSame(
			array(
				'https://mastodon.social/@lead',
				'https://example.com/about/lead',
				'https://example.social/users/lead',
			),
			$schema['author'][0]['sameAs']
		);
		$this->assertArrayNotHasKey( 'url', $schema['author'][1] );
	}

	public function test_same_as_is_not_inferred_when_actor_url_is_missing(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Schema sameAs',
			)
		);

		add_filter(
			'byline_feed_authors',
			static function ( array $authors, WP_Post $post ) use ( $post_id ): array {
				if ( $post_id !== $post->ID ) {
					return $authors;
				}

				return array(
					(object) array(
						'id'           => 'profile-author',
						'display_name' => 'Profile Author',
						'profiles'     => array(
							array(
								'href' => 'https://social.example/@profile-author',
								'rel'  => 'me',
							),
						),
						'ap_actor_url' => '',
					),
				);
			},
			10,
			2
		);

		$schema = $this->decode_schema_output( $this->capture_schema_output( get_permalink( $post_id ) ) );

		$this->assertSame(
			array( 'https://social.example/@profile-author' ),
			$schema['author'][0]['sameAs']
		);
	}

	public function test_non_singular_routes_emit_no_schema(): void {
		self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Archive Only',
			)
		);

		$this->assertSame( '', $this->capture_schema_output( home_url( '/' ) ) );
	}

	public function test_known_schema_plugin_conflict_disables_output_by_default(): void {
		update_option(
			'active_plugins',
			array(
				'wordpress-seo/wp-seo.php',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Schema Conflict',
			)
		);

		add_filter(
			'byline_feed_authors',
			static function ( array $authors, WP_Post $post ) use ( $post_id ): array {
				if ( $post_id !== $post->ID ) {
					return $authors;
				}

				return array(
					(object) array(
						'id'           => 'schema-conflict-author',
						'display_name' => 'Schema Conflict Author',
					),
				);
			},
			10,
			2
		);

		$this->assertSame( '', $this->capture_schema_output( get_permalink( $post_id ) ) );
	}

	public function test_schema_enabled_filter_can_override_plugin_conflict(): void {
		update_option(
			'active_plugins',
			array(
				'seo-by-rank-math/rank-math.php',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Schema Filter Override',
			)
		);

		add_filter(
			'byline_feed_authors',
			static function ( array $authors, WP_Post $post ) use ( $post_id ): array {
				if ( $post_id !== $post->ID ) {
					return $authors;
				}

				return array(
					(object) array(
						'id'           => 'override-author',
						'display_name' => 'Override Author',
					),
				);
			},
			10,
			2
		);

		add_filter(
			'byline_feed_schema_enabled',
			static function ( bool $enabled ): bool {
				return true;
			}
		);

		$output = $this->capture_schema_output( get_permalink( $post_id ) );

		$this->assertStringContainsString( 'application/ld+json', $output );
	}

	public function test_person_filter_can_modify_person_object(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Schema Person Filter',
			)
		);

		add_filter(
			'byline_feed_authors',
			static function ( array $authors, WP_Post $post ) use ( $post_id ): array {
				if ( $post_id !== $post->ID ) {
					return $authors;
				}

				return array(
					(object) array(
						'id'           => 'filter-author',
						'display_name' => 'Filter Author',
					),
				);
			},
			10,
			2
		);

		add_filter(
			'byline_feed_schema_person',
			static function ( array $person, object $author ): array {
				if ( 'filter-author' === $author->id ) {
					$person['sameAs'] = array( 'https://example.com/profile/filter-author' );
				}

				return $person;
			},
			10,
			2
		);

		$schema = $this->decode_schema_output( $this->capture_schema_output( get_permalink( $post_id ) ) );

		$this->assertSame(
			array( 'https://example.com/profile/filter-author' ),
			$schema['author'][0]['sameAs']
		);
	}
}
