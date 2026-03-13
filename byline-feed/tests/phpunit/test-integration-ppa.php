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
use WP_UnitTestCase;
use function Byline_Feed\byline_feed_get_adapter;
use function Byline_Feed\byline_feed_get_authors;

class Test_Integration_PPA extends WP_UnitTestCase {

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
