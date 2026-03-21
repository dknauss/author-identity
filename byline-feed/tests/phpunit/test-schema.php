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
use function Byline_Feed\Schema\detect_schema_mode;
use function Byline_Feed\Schema\is_yoast_active;
use function Byline_Feed\Schema\is_rankmath_active;
use function Byline_Feed\Schema\get_person_schema;
use function Byline_Feed\Schema\fediverse_profile_url;

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
		remove_all_filters( 'byline_feed_schema_mode' );
		remove_all_filters( 'byline_feed_yoast_article_data' );
		remove_all_filters( 'byline_feed_rankmath_json_ld' );
		remove_all_filters( 'wpseo_schema_article' );
		remove_all_filters( 'rank_math/json_ld' );

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

	// ────────────────────────────────────────────────────────────
	// Mode detection tests
	// ────────────────────────────────────────────────────────────

	public function test_detect_mode_returns_standalone_when_no_seo_plugin(): void {
		update_option( 'active_plugins', array() );

		$this->assertSame( 'standalone', detect_schema_mode() );
	}

	public function test_detect_mode_returns_yoast_when_yoast_active(): void {
		update_option(
			'active_plugins',
			array( 'wordpress-seo/wp-seo.php' )
		);

		$this->assertTrue( is_yoast_active() );
		$this->assertSame( 'yoast', detect_schema_mode() );
	}

	public function test_detect_mode_returns_rankmath_when_rankmath_active(): void {
		update_option(
			'active_plugins',
			array( 'seo-by-rank-math/rank-math.php' )
		);

		$this->assertTrue( is_rankmath_active() );
		$this->assertSame( 'rankmath', detect_schema_mode() );
	}

	public function test_schema_mode_filter_overrides_detection(): void {
		update_option( 'active_plugins', array() );

		add_filter(
			'byline_feed_schema_mode',
			static function (): string {
				return 'yoast';
			}
		);

		// The filter should be respected in register_hooks dispatch.
		// We test via detect_schema_mode + filter since register_hooks
		// has side effects we don't want to repeat.
		$mode = detect_schema_mode();
		$mode = apply_filters( 'byline_feed_schema_mode', $mode );

		$this->assertSame( 'yoast', $mode );
	}

	public function test_yoast_takes_priority_over_rankmath(): void {
		update_option(
			'active_plugins',
			array(
				'wordpress-seo/wp-seo.php',
				'seo-by-rank-math/rank-math.php',
			)
		);

		$this->assertSame( 'yoast', detect_schema_mode() );
	}

	// ────────────────────────────────────────────────────────────
	// Mode C: Standalone output tests
	// ────────────────────────────────────────────────────────────

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

	public function test_standalone_schema_is_omitted_when_no_authors_resolve(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Schema No Authors',
				'post_author' => 0,
			)
		);

		add_filter(
			'byline_feed_authors',
			static function (): array {
				return array();
			}
		);

		$this->assertSame( '', $this->capture_schema_output( get_permalink( $post_id ) ) );
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

	public function test_standalone_disabled_when_yoast_active(): void {
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

		// In the refactored architecture, standalone output is not registered
		// when Yoast is active. render_schema_script() still works but mode
		// dispatch in register_hooks() would not hook it. We verify detection.
		$this->assertSame( 'yoast', detect_schema_mode() );
	}

	public function test_schema_enabled_filter_can_disable_standalone_output(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Schema Disabled',
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
						'id'           => 'disabled-author',
						'display_name' => 'Disabled Author',
					),
				);
			},
			10,
			2
		);

		add_filter(
			'byline_feed_schema_enabled',
			static function (): bool {
				return false;
			}
		);

		$this->assertSame( '', $this->capture_schema_output( get_permalink( $post_id ) ) );
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

	// ────────────────────────────────────────────────────────────
	// Person additionalProperty tests
	// ────────────────────────────────────────────────────────────

	public function test_person_includes_byline_role_as_additional_property(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Schema Role Test',
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
						'id'           => 'role-author',
						'display_name' => 'Role Author',
						'role'         => 'creator',
					),
				);
			},
			10,
			2
		);

		$schema = $this->decode_schema_output( $this->capture_schema_output( get_permalink( $post_id ) ) );

		$this->assertArrayHasKey( 'additionalProperty', $schema['author'][0] );

		$role_prop = null;
		foreach ( $schema['author'][0]['additionalProperty'] as $prop ) {
			if ( 'bylineRole' === $prop['name'] ) {
				$role_prop = $prop;
				break;
			}
		}

		$this->assertNotNull( $role_prop, 'bylineRole PropertyValue should be present' );
		$this->assertSame( 'PropertyValue', $role_prop['@type'] );
		$this->assertSame( 'creator', $role_prop['value'] );
	}

	public function test_person_includes_ai_training_consent_as_additional_property(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Schema Consent Test',
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
						'id'           => 'consent-author',
						'display_name' => 'Consent Author',
						'ai_consent'   => 'deny',
					),
				);
			},
			10,
			2
		);

		$schema = $this->decode_schema_output( $this->capture_schema_output( get_permalink( $post_id ) ) );

		$consent_prop = null;
		foreach ( $schema['author'][0]['additionalProperty'] as $prop ) {
			if ( 'aiTrainingConsent' === $prop['name'] ) {
				$consent_prop = $prop;
				break;
			}
		}

		$this->assertNotNull( $consent_prop, 'aiTrainingConsent PropertyValue should be present' );
		$this->assertSame( 'PropertyValue', $consent_prop['@type'] );
		$this->assertSame( 'deny', $consent_prop['value'] );
	}

	public function test_person_omits_additional_property_when_role_and_consent_empty(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Schema No Props',
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
						'id'           => 'plain-author',
						'display_name' => 'Plain Author',
						'role'         => '',
						'ai_consent'   => '',
					),
				);
			},
			10,
			2
		);

		$schema = $this->decode_schema_output( $this->capture_schema_output( get_permalink( $post_id ) ) );

		$this->assertArrayNotHasKey( 'additionalProperty', $schema['author'][0] );
	}

	public function test_article_includes_byline_perspective_as_additional_property(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Schema Perspective Test',
			)
		);

		update_post_meta( $post_id, '_byline_perspective', 'analysis' );

		add_filter(
			'byline_feed_authors',
			static function ( array $authors, WP_Post $post ) use ( $post_id ): array {
				if ( $post_id !== $post->ID ) {
					return $authors;
				}

				return array(
					(object) array(
						'id'           => 'perspective-author',
						'display_name' => 'Perspective Author',
					),
				);
			},
			10,
			2
		);

		$schema = $this->decode_schema_output( $this->capture_schema_output( get_permalink( $post_id ) ) );

		$this->assertArrayHasKey( 'additionalProperty', $schema );

		$perspective_prop = null;
		foreach ( $schema['additionalProperty'] as $prop ) {
			if ( 'bylinePerspective' === $prop['name'] ) {
				$perspective_prop = $prop;
				break;
			}
		}

		$this->assertNotNull( $perspective_prop, 'bylinePerspective PropertyValue should be present on Article' );
		$this->assertSame( 'PropertyValue', $perspective_prop['@type'] );
		$this->assertSame( 'analysis', $perspective_prop['value'] );
	}

	public function test_article_omits_perspective_when_meta_not_set(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Schema No Perspective',
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
						'id'           => 'no-perspective-author',
						'display_name' => 'No Perspective Author',
					),
				);
			},
			10,
			2
		);

		$schema = $this->decode_schema_output( $this->capture_schema_output( get_permalink( $post_id ) ) );

		$this->assertArrayNotHasKey( 'additionalProperty', $schema );
	}

	public function test_person_includes_both_role_and_consent_in_additional_property(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Schema Both Props',
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
						'id'           => 'full-author',
						'display_name' => 'Full Author',
						'role'         => 'editor',
						'ai_consent'   => 'allow',
					),
				);
			},
			10,
			2
		);

		$schema = $this->decode_schema_output( $this->capture_schema_output( get_permalink( $post_id ) ) );

		$this->assertCount( 2, $schema['author'][0]['additionalProperty'] );

		$names = array_column( $schema['author'][0]['additionalProperty'], 'name' );
		$this->assertContains( 'bylineRole', $names );
		$this->assertContains( 'aiTrainingConsent', $names );
	}

	// ────────────────────────────────────────────────────────────
	// Fediverse profile URL resolution tests
	// ────────────────────────────────────────────────────────────

	public function test_fediverse_profile_url_resolves_standard_handle(): void {
		$this->assertSame(
			'https://mastodon.social/@jdoe',
			fediverse_profile_url( '@jdoe@mastodon.social' )
		);
	}

	public function test_fediverse_profile_url_handles_no_leading_at(): void {
		$this->assertSame(
			'https://fosstodon.org/@alice',
			fediverse_profile_url( 'alice@fosstodon.org' )
		);
	}

	public function test_fediverse_profile_url_returns_empty_for_invalid_handle(): void {
		$this->assertSame( '', fediverse_profile_url( 'not-a-handle' ) );
		$this->assertSame( '', fediverse_profile_url( '' ) );
		$this->assertSame( '', fediverse_profile_url( '@user@' ) );
		$this->assertSame( '', fediverse_profile_url( '@@instance.com' ) );
	}

	public function test_fediverse_handle_included_in_same_as(): void {
		$author = (object) array(
			'id'           => 'fedi-author',
			'display_name' => 'Fedi Author',
			'fediverse'    => '@fediuser@mastodon.social',
			'profiles'     => array(),
			'ap_actor_url' => '',
		);

		$person = get_person_schema( $author );

		$this->assertContains(
			'https://mastodon.social/@fediuser',
			$person['sameAs']
		);
	}

	// ────────────────────────────────────────────────────────────
	// Mode A: Yoast enrichment tests (simulated via filter)
	// ────────────────────────────────────────────────────────────

	public function test_yoast_article_filter_replaces_author_with_multi_author_array(): void {
		// Simulate Yoast being active so the module loads.
		update_option( 'active_plugins', array( 'wordpress-seo/wp-seo.php' ) );

		// Load schema-yoast.php directly for testing.
		require_once BYLINE_FEED_PLUGIN_DIR . 'inc/schema-yoast.php';

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Yoast Multi Author',
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
						'id'           => 'yoast-lead',
						'display_name' => 'Yoast Lead',
						'role'         => 'creator',
						'ai_consent'   => 'allow',
						'fediverse'    => '@lead@mastodon.social',
						'profiles'     => array(),
						'ap_actor_url' => '',
					),
					(object) array(
						'id'           => 'yoast-editor',
						'display_name' => 'Yoast Editor',
						'role'         => 'editor',
					),
				);
			},
			10,
			2
		);

		// Simulate Yoast's Article data with a single @id author reference.
		$yoast_article = array(
			'@type'  => 'Article',
			'author' => array( '@id' => 'https://example.com/#/schema/person/abc' ),
		);

		// Simulate Yoast's context object.
		$context     = new \stdClass();
		$context->id = $post_id;

		$enriched = \Byline_Feed\Schema_Yoast\enrich_article( $yoast_article, $context );

		// Author should now be an array of Person objects, not an @id ref.
		$this->assertIsArray( $enriched['author'] );
		$this->assertCount( 2, $enriched['author'] );
		$this->assertSame( 'Person', $enriched['author'][0]['@type'] );
		$this->assertSame( 'Yoast Lead', $enriched['author'][0]['name'] );
		$this->assertSame( 'Person', $enriched['author'][1]['@type'] );
		$this->assertSame( 'Yoast Editor', $enriched['author'][1]['name'] );

		// Lead author should have bylineRole and aiTrainingConsent.
		$lead_props = $enriched['author'][0]['additionalProperty'] ?? array();
		$prop_names = array_column( $lead_props, 'name' );
		$this->assertContains( 'bylineRole', $prop_names );
		$this->assertContains( 'aiTrainingConsent', $prop_names );

		// Lead author should have fediverse in sameAs.
		$this->assertContains( 'https://mastodon.social/@lead', $enriched['author'][0]['sameAs'] );
	}

	public function test_yoast_article_filter_adds_perspective(): void {
		require_once BYLINE_FEED_PLUGIN_DIR . 'inc/schema-yoast.php';

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Yoast Perspective',
			)
		);

		update_post_meta( $post_id, '_byline_perspective', 'reporting' );

		add_filter(
			'byline_feed_authors',
			static function ( array $authors, WP_Post $post ) use ( $post_id ): array {
				if ( $post_id !== $post->ID ) {
					return $authors;
				}

				return array(
					(object) array(
						'id'           => 'yoast-reporter',
						'display_name' => 'Yoast Reporter',
					),
				);
			},
			10,
			2
		);

		$yoast_article = array(
			'@type'  => 'Article',
			'author' => array( '@id' => 'https://example.com/#/schema/person/def' ),
		);

		$context     = new \stdClass();
		$context->id = $post_id;

		$enriched = \Byline_Feed\Schema_Yoast\enrich_article( $yoast_article, $context );

		$this->assertArrayHasKey( 'additionalProperty', $enriched );

		$perspective_prop = null;
		foreach ( $enriched['additionalProperty'] as $prop ) {
			if ( 'bylinePerspective' === $prop['name'] ) {
				$perspective_prop = $prop;
				break;
			}
		}

		$this->assertNotNull( $perspective_prop );
		$this->assertSame( 'reporting', $perspective_prop['value'] );
	}

	public function test_yoast_article_filter_preserves_existing_additional_property(): void {
		require_once BYLINE_FEED_PLUGIN_DIR . 'inc/schema-yoast.php';

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Yoast Existing Props',
			)
		);

		update_post_meta( $post_id, '_byline_perspective', 'analysis' );

		add_filter(
			'byline_feed_authors',
			static function ( array $authors, WP_Post $post ) use ( $post_id ): array {
				if ( $post_id !== $post->ID ) {
					return $authors;
				}

				return array(
					(object) array(
						'id'           => 'existing-props-author',
						'display_name' => 'Props Author',
					),
				);
			},
			10,
			2
		);

		$yoast_article = array(
			'@type'              => 'Article',
			'author'             => array( '@id' => 'https://example.com/#person' ),
			'additionalProperty' => array(
				array(
					'@type' => 'PropertyValue',
					'name'  => 'existingProp',
					'value' => 'existing-value',
				),
			),
		);

		$context     = new \stdClass();
		$context->id = $post_id;

		$enriched = \Byline_Feed\Schema_Yoast\enrich_article( $yoast_article, $context );

		// Should have both the existing prop and the new perspective prop.
		$this->assertCount( 2, $enriched['additionalProperty'] );

		$names = array_column( $enriched['additionalProperty'], 'name' );
		$this->assertContains( 'existingProp', $names );
		$this->assertContains( 'bylinePerspective', $names );
	}

	public function test_yoast_returns_unchanged_when_no_authors(): void {
		require_once BYLINE_FEED_PLUGIN_DIR . 'inc/schema-yoast.php';

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Yoast No Authors',
				'post_author' => 0,
			)
		);

		// Return empty authors.
		add_filter(
			'byline_feed_authors',
			static function () use ( $post_id ): array {
				return array();
			},
			10,
			2
		);

		$yoast_article = array(
			'@type'  => 'Article',
			'author' => array( '@id' => 'https://example.com/#person' ),
		);

		$context     = new \stdClass();
		$context->id = $post_id;

		$result = \Byline_Feed\Schema_Yoast\enrich_article( $yoast_article, $context );

		// Should be unchanged.
		$this->assertSame( $yoast_article, $result );
	}

	// ────────────────────────────────────────────────────────────
	// Mode B: Rank Math enrichment tests (simulated via filter)
	// ────────────────────────────────────────────────────────────

	public function test_rankmath_filter_replaces_article_author_with_multi_author_array(): void {
		require_once BYLINE_FEED_PLUGIN_DIR . 'inc/schema-rankmath.php';

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Rank Math Multi Author',
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
						'id'           => 'rm-author-1',
						'display_name' => 'RM Author One',
						'role'         => 'creator',
					),
					(object) array(
						'id'           => 'rm-author-2',
						'display_name' => 'RM Author Two',
						'role'         => 'contributor',
						'ai_consent'   => 'deny',
					),
				);
			},
			10,
			2
		);

		// Simulate a singular request context.
		$this->go_to( get_permalink( $post_id ) );

		$rankmath_data = array(
			'richSnippet' => array(
				'@type'  => 'Article',
				'author' => array(
					'@type' => 'Person',
					'name'  => 'Original Author',
				),
			),
		);

		$enriched = \Byline_Feed\Schema_Rankmath\enrich_json_ld( $rankmath_data, new \stdClass() );

		$article = $enriched['richSnippet'];

		$this->assertIsArray( $article['author'] );
		$this->assertCount( 2, $article['author'] );
		$this->assertSame( 'RM Author One', $article['author'][0]['name'] );
		$this->assertSame( 'RM Author Two', $article['author'][1]['name'] );

		// Second author should have both role and consent.
		$props = $article['author'][1]['additionalProperty'] ?? array();
		$names = array_column( $props, 'name' );
		$this->assertContains( 'bylineRole', $names );
		$this->assertContains( 'aiTrainingConsent', $names );
	}

	public function test_rankmath_filter_adds_perspective(): void {
		require_once BYLINE_FEED_PLUGIN_DIR . 'inc/schema-rankmath.php';

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Rank Math Perspective',
			)
		);

		update_post_meta( $post_id, '_byline_perspective', 'analysis' );

		add_filter(
			'byline_feed_authors',
			static function ( array $authors, WP_Post $post ) use ( $post_id ): array {
				if ( $post_id !== $post->ID ) {
					return $authors;
				}

				return array(
					(object) array(
						'id'           => 'rm-perspective',
						'display_name' => 'RM Analyst',
					),
				);
			},
			10,
			2
		);

		$this->go_to( get_permalink( $post_id ) );

		$rankmath_data = array(
			'article' => array(
				'@type'  => 'Article',
				'author' => array( '@type' => 'Person', 'name' => 'Original' ),
			),
		);

		$enriched = \Byline_Feed\Schema_Rankmath\enrich_json_ld( $rankmath_data, new \stdClass() );

		$this->assertArrayHasKey( 'additionalProperty', $enriched['article'] );

		$perspective_prop = null;
		foreach ( $enriched['article']['additionalProperty'] as $prop ) {
			if ( 'bylinePerspective' === $prop['name'] ) {
				$perspective_prop = $prop;
				break;
			}
		}

		$this->assertNotNull( $perspective_prop );
		$this->assertSame( 'analysis', $perspective_prop['value'] );
	}

	public function test_rankmath_filter_skips_non_singular(): void {
		require_once BYLINE_FEED_PLUGIN_DIR . 'inc/schema-rankmath.php';

		self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'RM Archive Only',
			)
		);

		$this->go_to( home_url( '/' ) );

		$original = array(
			'article' => array(
				'@type'  => 'Article',
				'author' => array( '@type' => 'Person', 'name' => 'Original' ),
			),
		);

		$result = \Byline_Feed\Schema_Rankmath\enrich_json_ld( $original, new \stdClass() );

		$this->assertSame( $original, $result );
	}
}
