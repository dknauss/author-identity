<?php
/**
 * Tests for organization author mode, article/publisher @id, and author meta tags.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Tests;

use WP_Post;
use WP_UnitTestCase;
use function Byline_Feed\byline_feed_get_author_mode;
use function Byline_Feed\Fediverse\get_handles_for_post;
use function Byline_Feed\Head_Meta\get_meta_tags_for_post;
use function Byline_Feed\Head_Meta\render_meta_tags;
use function Byline_Feed\Schema\get_article_schema_for_post;

class Test_Author_Mode extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( 'byline_feed_authors' );
		remove_all_filters( 'byline_feed_author_mode' );
		remove_all_filters( 'byline_feed_schema_mode' );
		remove_all_filters( 'byline_feed_author_meta_tags_enabled' );
		remove_all_filters( 'byline_feed_author_meta_tags' );
		remove_all_filters( 'byline_feed_author_mode_test_seen' );
		parent::tear_down();
	}

	/**
	 * Create a published post whose authors resolve to the given objects.
	 *
	 * @param object[] $authors Normalized author objects.
	 * @return int Post ID.
	 */
	private function post_with_authors( array $authors ): int {
		update_option( 'blogname', 'Counterfoil' );

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Nemesis, the goddess of limits',
			)
		);

		add_filter(
			'byline_feed_authors',
			static function ( array $resolved, WP_Post $post ) use ( $post_id, $authors ): array {
				return $post_id === $post->ID ? $authors : $resolved;
			},
			10,
			2
		);

		return $post_id;
	}

	private function dan(): object {
		return (object) array(
			'id'           => 'dan',
			'display_name' => 'Dan Knauss',
			'url'          => 'https://dan.knauss.ca/',
			'fediverse'    => '@dan_kn@mastodon.social',
			'profiles'     => array(),
			'ap_actor_url' => '',
		);
	}

	private function use_organization_mode(): void {
		add_filter(
			'byline_feed_author_mode',
			static function () {
				return 'organization';
			}
		);
	}

	// ────────────────────────────────────────────────────────────
	// Mode resolution
	// ────────────────────────────────────────────────────────────

	public function test_mode_defaults_to_person_and_rejects_unknown_values(): void {
		$post = get_post( $this->post_with_authors( array( $this->dan() ) ) );

		$this->assertSame( 'person', byline_feed_get_author_mode( $post ) );

		add_filter(
			'byline_feed_author_mode',
			static function () {
				return 'collective';
			}
		);

		$this->assertSame( 'person', byline_feed_get_author_mode( $post ) );
	}

	// ────────────────────────────────────────────────────────────
	// Stored choice (Attribution panel)
	// ────────────────────────────────────────────────────────────

	public function test_stored_choice_sets_the_mode(): void {
		$post = get_post( $this->post_with_authors( array( $this->dan() ) ) );

		update_post_meta( $post->ID, '_byline_author_mode', 'organization' );

		$this->assertSame( 'organization', byline_feed_get_author_mode( $post ) );
	}

	public function test_filter_sees_the_stored_choice_so_automatic_answers_can_defer(): void {
		$post = get_post( $this->post_with_authors( array( $this->dan() ) ) );

		// An automatic suggester that respects a choice made by hand.
		add_filter(
			'byline_feed_author_mode',
			static function ( $mode, $post, $stored ) {
				return '' !== $stored ? $mode : 'organization';
			},
			10,
			3
		);

		$this->assertSame( 'organization', byline_feed_get_author_mode( $post ), 'No stored choice: the suggestion applies.' );

		update_post_meta( $post->ID, '_byline_author_mode', 'person' );

		$this->assertSame( 'person', byline_feed_get_author_mode( $post ), 'Stored choice: the suggestion defers.' );
	}

	public function test_invalid_stored_values_are_ignored(): void {
		$post = get_post( $this->post_with_authors( array( $this->dan() ) ) );

		update_post_meta( $post->ID, '_byline_author_mode', 'collective' );

		$this->assertSame( '', \Byline_Feed\Author_Mode\get_stored_author_mode( $post ) );
		$this->assertSame( 'person', byline_feed_get_author_mode( $post ) );
		$this->assertSame( '', \Byline_Feed\Author_Mode\sanitize_author_mode( 'collective' ) );
	}

	public function test_stored_choice_is_registered_for_rest(): void {
		\Byline_Feed\Author_Mode\register_meta();

		$registered = get_registered_meta_keys( 'post', '' );

		$this->assertArrayHasKey( '_byline_author_mode', $registered );
		$this->assertTrue( (bool) $registered['_byline_author_mode']['show_in_rest'] );
		$this->assertSame( '', $registered['_byline_author_mode']['default'] );
	}

	// ────────────────────────────────────────────────────────────
	// Standalone JSON-LD
	// ────────────────────────────────────────────────────────────

	public function test_article_and_publisher_carry_stable_ids(): void {
		$post   = get_post( $this->post_with_authors( array( $this->dan() ) ) );
		$schema = get_article_schema_for_post( $post );

		$this->assertSame( get_permalink( $post ) . '#article', $schema['@id'] );
		$this->assertSame(
			array(
				'@type' => 'WebPage',
				'@id'   => get_permalink( $post ),
			),
			$schema['mainEntityOfPage']
		);
		$this->assertSame( home_url( '/#organization' ), $schema['publisher']['@id'] );
		$this->assertSame( 'Person', $schema['author'][0]['@type'] );
	}

	public function test_organization_mode_names_the_publisher_as_author(): void {
		$post = get_post( $this->post_with_authors( array( $this->dan() ) ) );
		$this->use_organization_mode();

		$schema = get_article_schema_for_post( $post );

		$this->assertCount( 1, $schema['author'] );
		$this->assertSame( $schema['publisher'], $schema['author'][0] );
		$this->assertSame( 'Organization', $schema['author'][0]['@type'] );
		$this->assertSame( 'Counterfoil', $schema['author'][0]['name'] );
		$this->assertStringNotContainsString( 'Dan Knauss', (string) wp_json_encode( $schema ) );
	}

	public function test_organization_mode_emits_schema_even_without_resolved_authors(): void {
		$post = get_post( $this->post_with_authors( array() ) );

		$this->assertSame( array(), get_article_schema_for_post( $post ) );

		$this->use_organization_mode();

		$this->assertSame( 'Organization', get_article_schema_for_post( $post )['author'][0]['@type'] );
	}

	// ────────────────────────────────────────────────────────────
	// fediverse:creator
	// ────────────────────────────────────────────────────────────

	public function test_organization_mode_suppresses_fediverse_creator(): void {
		$post = get_post( $this->post_with_authors( array( $this->dan() ) ) );

		$this->assertSame( array( '@dan_kn@mastodon.social' ), get_handles_for_post( $post ) );

		$this->use_organization_mode();

		$this->assertSame( array(), get_handles_for_post( $post ) );
	}

	// ────────────────────────────────────────────────────────────
	// Author meta tags
	// ────────────────────────────────────────────────────────────

	public function test_meta_tags_name_every_author_and_link_profiles(): void {
		$second = (object) array(
			'id'           => 'ivan',
			'display_name' => 'Ivan Illich',
			'profiles'     => array(),
			'ap_actor_url' => '',
		);
		$post_id = $this->post_with_authors( array( $this->dan(), $second ) );

		$this->go_to( get_permalink( $post_id ) );
		ob_start();
		render_meta_tags();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '<meta name="author" content="Dan Knauss, Ivan Illich" />', $html );
		$this->assertStringContainsString( '<meta property="og:author" content="Dan Knauss, Ivan Illich" />', $html );
		$this->assertStringContainsString( '<meta property="article:author" content="https://dan.knauss.ca/" />', $html );
		// An author with neither a URL nor a user account has no profile to link.
		$this->assertSame( 1, substr_count( $html, 'article:author' ) );
	}

	public function test_meta_tag_values_are_escaped(): void {
		$author               = $this->dan();
		$author->display_name = 'Dan "Quote" Knauss';
		$post_id              = $this->post_with_authors( array( $author ) );

		$this->go_to( get_permalink( $post_id ) );
		ob_start();
		render_meta_tags();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'content="Dan &quot;Quote&quot; Knauss"', $html );
	}

	public function test_organization_mode_prints_no_author_meta_tags(): void {
		$post = get_post( $this->post_with_authors( array( $this->dan() ) ) );
		$this->use_organization_mode();

		$this->assertSame( array(), get_meta_tags_for_post( $post ) );
	}

	public function test_meta_tags_defer_to_seo_plugins_unless_enabled(): void {
		$post = get_post( $this->post_with_authors( array( $this->dan() ) ) );

		add_filter(
			'byline_feed_schema_mode',
			static function () {
				return 'yoast';
			}
		);

		$this->assertSame( array(), get_meta_tags_for_post( $post ) );

		add_filter( 'byline_feed_author_meta_tags_enabled', '__return_true' );

		$this->assertNotEmpty( get_meta_tags_for_post( $post ) );
	}

	public function test_meta_tags_filter_can_drop_invalid_entries(): void {
		$post = get_post( $this->post_with_authors( array( $this->dan() ) ) );

		add_filter(
			'byline_feed_author_meta_tags',
			static function ( array $tags ): array {
				$tags[] = array(
					'attribute' => 'name',
					'key'       => '',
					'content'   => 'x',
				);
				$tags[] = 'not a tag';
				return $tags;
			}
		);

		$keys = array_column( get_meta_tags_for_post( $post ), 'key' );

		$this->assertSame( array( 'author', 'og:author', 'article:author' ), $keys );
	}

	// ────────────────────────────────────────────────────────────
	// SEO plugin enrichment
	// ────────────────────────────────────────────────────────────

	public function test_yoast_organization_mode_points_author_at_yoast_publisher(): void {
		require_once BYLINE_FEED_PLUGIN_DIR . 'inc/schema-yoast.php';

		$post_id = $this->post_with_authors( array( $this->dan() ) );
		$this->use_organization_mode();

		$context     = new \stdClass();
		$context->id = $post_id;

		$enriched = \Byline_Feed\Schema_Yoast\enrich_article(
			array(
				'@type'     => 'Article',
				'author'    => array( '@id' => 'https://example.com/#/schema/person/abc' ),
				'publisher' => array( '@id' => 'https://example.com/#organization' ),
			),
			$context
		);

		$this->assertSame( array( '@id' => 'https://example.com/#organization' ), $enriched['author'] );
	}

	public function test_rankmath_organization_mode_points_author_at_publisher(): void {
		require_once BYLINE_FEED_PLUGIN_DIR . 'inc/schema-rankmath.php';

		$post_id = $this->post_with_authors( array( $this->dan() ) );
		$this->use_organization_mode();
		$this->go_to( get_permalink( $post_id ) );

		$article = array(
			'@type'  => 'Article',
			'author' => array(
				'@type' => 'Person',
				'name'  => 'Original Author',
			),
		);

		$with_publisher = \Byline_Feed\Schema_Rankmath\enrich_json_ld(
			array(
				'publisher'   => array(
					'@type' => 'Organization',
					'@id'   => 'https://example.com/#organization',
				),
				'richSnippet' => $article,
			),
			new \stdClass()
		);

		$this->assertSame( array( '@id' => 'https://example.com/#organization' ), $with_publisher['richSnippet']['author'] );

		$without_publisher = \Byline_Feed\Schema_Rankmath\enrich_json_ld(
			array( 'richSnippet' => $article ),
			new \stdClass()
		);

		$this->assertSame( 'Organization', $without_publisher['richSnippet']['author']['@type'] );
		$this->assertSame( 'Counterfoil', $without_publisher['richSnippet']['author']['name'] );
	}
}
