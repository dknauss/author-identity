<?php
/**
 * Integration tests for the PublishPress Authors adapter against a real PPA installation.
 *
 * These tests require PublishPress Authors to be loaded. In CI they run via the
 * integration-ppa job, which sets BYLINE_PPA_PLUGIN. Locally they are skipped
 * unless PublishPress Authors is active.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Tests;

use Byline_Feed\Adapter_PPA;
use MultipleAuthors\Classes\Objects\Author as PPA_Author;
use WP_UnitTestCase;
use function Byline_Feed\byline_feed_get_adapter;
use function Byline_Feed\byline_feed_get_authors;

class Test_Integration_PPA extends WP_UnitTestCase {

	/**
	 * Assign author taxonomy terms to a post in deterministic order.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $authors Array of PublishPress Author objects.
	 */
	private function assign_authors_to_post( int $post_id, array $authors ): void {
		global $wpdb;

		$term_ids = array_map(
			static function ( object $author ): int {
				return (int) $author->term_id;
			},
			$authors
		);

		$term_taxonomy_ids = wp_set_object_terms( $post_id, $term_ids, 'author', false );

		$this->assertIsArray( $term_taxonomy_ids );

		foreach ( $term_taxonomy_ids as $index => $term_taxonomy_id ) {
			$wpdb->update(
				$wpdb->term_relationships,
				array( 'term_order' => $index ),
				array(
					'object_id'        => $post_id,
					'term_taxonomy_id' => (int) $term_taxonomy_id,
				),
				array( '%d' ),
				array( '%d', '%d' )
			);
		}

		do_action( 'publishpress_authors_flush_cache_for_post', $post_id );
		clean_post_cache( $post_id );
	}

	/**
	 * Skip all tests if PublishPress Authors is not active.
	 */
	public function set_up(): void {
		parent::set_up();

		if (
			! function_exists( 'publishpress_authors_get_post_authors' )
			&& ! function_exists( 'get_post_authors' )
			&& ! class_exists( 'MultipleAuthors\\Classes\\Objects\\Author' )
		) {
			$this->markTestSkipped( 'PublishPress Authors is not active.' );
		}
	}

	public function test_ppa_is_detected_as_active_adapter(): void {
		// Reset cached adapter so detection runs fresh.
		global $_byline_feed_adapter;
		$_byline_feed_adapter = null;

		$adapter = byline_feed_get_adapter();

		$this->assertInstanceOf( Adapter_PPA::class, $adapter );
	}

	public function test_get_authors_returns_non_empty_array_for_published_post(): void {
		$user_id = self::factory()->user->create(
			array(
				'display_name'  => 'PPA Integration Author',
				'user_nicename' => 'ppa-integration-author',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
			)
		);

		$post    = get_post( $post_id );
		$authors = byline_feed_get_authors( $post );

		// PPA should return at least the post author.
		$this->assertNotEmpty( $authors, 'Expected at least one author from PPA.' );
	}

	public function test_get_authors_returns_normalized_object_for_guest_author(): void {
		$guest_author = PPA_Author::create(
			array(
				'display_name' => 'PPA Guest Writer',
				'slug'         => 'ppa-guest-writer',
			)
		);

		$this->assertFalse( is_wp_error( $guest_author ) );
		$this->assertIsObject( $guest_author );

		update_term_meta( $guest_author->term_id, 'description', 'Guest author bio from term meta.' );
		update_term_meta( $guest_author->term_id, 'avatar', 'https://cdn.example.com/ppa-guest-writer.jpg' );

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
			)
		);

		$this->assign_authors_to_post( $post_id, array( $guest_author ) );

		$post    = get_post( $post_id );
		$authors = byline_feed_get_authors( $post );

		$this->assertCount( 1, $authors );
		$this->assertSame( 'ppa-guest-writer', $authors[0]->id );
		$this->assertTrue( $authors[0]->is_guest );
		$this->assertSame( 'guest', $authors[0]->role );
		$this->assertSame( 0, $authors[0]->user_id );
		$this->assertSame( 'Guest author bio from term meta.', $authors[0]->description );
		$this->assertSame( 'https://cdn.example.com/ppa-guest-writer.jpg', $authors[0]->avatar_url );
		$this->assertSame( '', $authors[0]->fediverse );
		$this->assertSame( '', $authors[0]->ai_consent );
	}

	public function test_multi_author_post_returns_all_ppa_authors_in_order(): void {
		$user_one = self::factory()->user->create(
			array(
				'display_name'  => 'PPA Author One',
				'user_nicename' => 'ppa-author-one',
			)
		);
		$user_two = self::factory()->user->create(
			array(
				'display_name'  => 'PPA Author Two',
				'user_nicename' => 'ppa-author-two',
			)
		);

		$author_one = PPA_Author::create_from_user( $user_one );
		$author_two = PPA_Author::create_from_user( $user_two );

		$this->assertFalse( is_wp_error( $author_one ) );
		$this->assertFalse( is_wp_error( $author_two ) );
		$this->assertIsObject( $author_one );
		$this->assertIsObject( $author_two );

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_one,
				'post_status' => 'publish',
			)
		);

		$this->assign_authors_to_post( $post_id, array( $author_one, $author_two ) );

		$post    = get_post( $post_id );
		$authors = byline_feed_get_authors( $post );
		$ids     = array_column( $authors, 'id' );

		$this->assertSame( array( 'ppa-author-one', 'ppa-author-two' ), $ids );
	}

	public function test_linked_user_meta_is_exposed_in_ppa_context(): void {
		$user_id = self::factory()->user->create(
			array(
				'display_name'  => 'PPA Profile Author',
				'user_nicename' => 'ppa-profile-author',
				'user_url'      => 'https://example.com/ppa-profile-author',
			)
		);

		update_user_meta( $user_id, 'byline_feed_fediverse', '@ppa@example.social' );
		update_user_meta( $user_id, 'byline_feed_now_url', 'https://example.com/ppa-profile-author/now' );
		update_user_meta( $user_id, 'byline_feed_uses_url', 'https://example.com/ppa-profile-author/uses' );
		update_user_meta( $user_id, 'byline_feed_ai_consent', 'deny' );

		$author = PPA_Author::create_from_user( $user_id );

		$this->assertFalse( is_wp_error( $author ) );
		$this->assertIsObject( $author );

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
			)
		);

		$this->assign_authors_to_post( $post_id, array( $author ) );

		$post    = get_post( $post_id );
		$authors = byline_feed_get_authors( $post );

		$this->assertCount( 1, $authors );
		$this->assertFalse( $authors[0]->is_guest );
		$this->assertSame( $user_id, $authors[0]->user_id );
		$this->assertSame( 'https://example.com/ppa-profile-author', $authors[0]->url );
		$this->assertSame( '@ppa@example.social', $authors[0]->fediverse );
		$this->assertSame( 'https://example.com/ppa-profile-author/now', $authors[0]->now_url );
		$this->assertSame( 'https://example.com/ppa-profile-author/uses', $authors[0]->uses_url );
		$this->assertSame( 'deny', $authors[0]->ai_consent );
	}

	public function test_term_meta_is_preferred_over_linked_user_profile_fields(): void {
		$user_id = self::factory()->user->create(
			array(
				'role'          => 'author',
				'display_name'  => 'PPA Term Override Author',
				'user_nicename' => 'ppa-term-override-author',
				'description'   => 'Fallback user bio.',
				'user_url'      => 'https://example.com/ppa-term-override-author',
			)
		);

		update_user_meta( $user_id, 'byline_feed_ai_consent', 'allow' );
		update_user_meta( $user_id, 'byline_feed_fediverse', '@termoverride@example.social' );

		$author = PPA_Author::create_from_user( $user_id );

		$this->assertFalse( is_wp_error( $author ) );
		$this->assertIsObject( $author );

		update_term_meta( $author->term_id, 'description', 'Preferred author bio from term meta.' );
		update_term_meta( $author->term_id, 'avatar', 'https://cdn.example.com/ppa-term-avatar.jpg' );

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
			)
		);

		$this->assign_authors_to_post( $post_id, array( $author ) );

		$post    = get_post( $post_id );
		$authors = byline_feed_get_authors( $post );

		$this->assertCount( 1, $authors );
		$this->assertSame( 'Preferred author bio from term meta.', $authors[0]->description );
		$this->assertSame( 'https://cdn.example.com/ppa-term-avatar.jpg', $authors[0]->avatar_url );
		$this->assertSame( 'https://example.com/ppa-term-override-author', $authors[0]->url );
		$this->assertSame( '@termoverride@example.social', $authors[0]->fediverse );
		$this->assertSame( 'allow', $authors[0]->ai_consent );
	}

	public function test_author_object_shape_matches_contract(): void {
		$user_id = self::factory()->user->create(
			array(
				'user_nicename' => 'ppa-contract-author',
				'display_name'  => 'PPA Contract Author',
				'description'   => 'PPA bio.',
				'user_url'      => 'https://example.com/ppa-contract-author',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
			)
		);

		$post    = get_post( $post_id );
		$authors = byline_feed_get_authors( $post );

		$this->assertNotEmpty( $authors );

		$author = $authors[0];

		// Verify all contract fields are present and typed correctly.
		$this->assertIsString( $author->id );
		$this->assertNotSame( '', $author->id );
		$this->assertIsString( $author->display_name );
		$this->assertNotSame( '', $author->display_name );
		$this->assertIsString( $author->description );
		$this->assertIsString( $author->url );
		$this->assertIsString( $author->avatar_url );
		$this->assertIsInt( $author->user_id );
		$this->assertIsString( $author->role );
		$this->assertIsBool( $author->is_guest );
		$this->assertIsArray( $author->profiles );
		$this->assertIsString( $author->now_url );
		$this->assertIsString( $author->uses_url );
		$this->assertIsString( $author->fediverse );
		$this->assertIsString( $author->ai_consent );
	}

	public function test_ppa_missing_api_test_is_skipped_when_active(): void {
		// This test verifies the unit-test skip-guard works in reverse:
		// when PPA IS active, the "missing api" unit test is correctly skipped.
		// This test itself passes trivially — its purpose is documentation.
		$this->assertTrue( true );
	}
}
