<?php
/**
 * Integration tests for the Co-Authors Plus adapter against a real CAP installation.
 *
 * These tests require Co-Authors Plus to be loaded. In CI they run via the
 * integration-cap job, which sets BYLINE_CAP_PLUGIN. Locally they are skipped
 * unless Co-Authors Plus is active.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Tests;

use Byline_Feed\Adapter_CAP;
use WP_UnitTestCase;
use function Byline_Feed\byline_feed_get_adapter;
use function Byline_Feed\byline_feed_get_authors;

class Test_Integration_CAP extends WP_UnitTestCase {

	/**
	 * Skip all tests if Co-Authors Plus is not active.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! function_exists( 'get_coauthors' ) ) {
			$this->markTestSkipped( 'Co-Authors Plus is not active.' );
		}
	}

	public function test_cap_is_detected_as_active_adapter(): void {
		// Reset cached adapter so detection runs fresh.
		global $_byline_feed_adapter;
		$_byline_feed_adapter = null;

		$adapter = byline_feed_get_adapter();

		$this->assertInstanceOf( Adapter_CAP::class, $adapter );
	}

	public function test_get_authors_returns_normalized_object_for_coauthor(): void {
		$user_id = self::factory()->user->create(
			array(
				'display_name'  => 'CAP Integration Author',
				'user_nicename' => 'cap-integration-author',
				'role'          => 'editor',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
			)
		);

		// Add the user as a co-author using CAP's own API.
		add_coauthors( $post_id, array( 'cap-integration-author' ) );

		$post    = get_post( $post_id );
		$authors = byline_feed_get_authors( $post );

		$this->assertNotEmpty( $authors, 'Expected at least one author from CAP.' );

		$ids = array_column( $authors, 'id' );
		$this->assertContains( 'cap-integration-author', $ids );

		$author = $authors[ array_search( 'cap-integration-author', $ids, true ) ];

		$this->assertSame( 'CAP Integration Author', $author->display_name );
		$this->assertSame( $user_id, $author->user_id );
		$this->assertFalse( $author->is_guest );
		$this->assertIsString( $author->role );
		$this->assertIsArray( $author->profiles );
	}

	public function test_get_authors_returns_normalized_object_for_guest_author(): void {
		// CAP guest authors are created as custom post type entries.
		// We create one using CAP's API if available, otherwise skip.
		if ( ! function_exists( 'coauthors_plus' ) && ! class_exists( 'CoAuthors_Plus' ) ) {
			$this->markTestSkipped( 'CAP guest author API not available in this version.' );
		}

		global $coauthors_plus;

		if ( ! isset( $coauthors_plus ) || ! method_exists( $coauthors_plus, 'create_guest_author' ) ) {
			$this->markTestSkipped( 'CAP guest author creation API not available.' );
		}

		$guest_id = $coauthors_plus->create_guest_author(
			array(
				'display_name' => 'CAP Guest Writer',
				'user_login'   => 'cap-guest-writer',
			)
		);

		if ( is_wp_error( $guest_id ) ) {
			$this->markTestSkipped( 'Could not create CAP guest author: ' . $guest_id->get_error_message() );
		}

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		add_coauthors( $post_id, array( 'cap-guest-writer' ) );

		$post    = get_post( $post_id );
		$authors = byline_feed_get_authors( $post );

		$ids = array_column( $authors, 'id' );
		$this->assertContains( 'cap-guest-writer', $ids );

		$guest = $authors[ array_search( 'cap-guest-writer', $ids, true ) ];

		$this->assertTrue( $guest->is_guest );
		$this->assertSame( 'guest', $guest->role );
		$this->assertSame( 0, $guest->user_id );
	}

	public function test_multi_author_post_returns_all_coauthors_in_order(): void {
		$user_one = self::factory()->user->create(
			array(
				'display_name'  => 'CAP Author One',
				'user_nicename' => 'cap-author-one',
			)
		);

		$user_two = self::factory()->user->create(
			array(
				'display_name'  => 'CAP Author Two',
				'user_nicename' => 'cap-author-two',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_one,
				'post_status' => 'publish',
			)
		);

		add_coauthors( $post_id, array( 'cap-author-one', 'cap-author-two' ) );

		$post    = get_post( $post_id );
		$authors = byline_feed_get_authors( $post );
		$ids     = array_column( $authors, 'id' );

		$this->assertContains( 'cap-author-one', $ids );
		$this->assertContains( 'cap-author-two', $ids );
		$this->assertCount( 2, $authors );
	}

	public function test_author_object_shape_matches_contract(): void {
		$user_id = self::factory()->user->create(
			array(
				'user_nicename' => 'cap-contract-author',
				'display_name'  => 'CAP Contract Author',
				'description'   => 'Bio text.',
				'user_url'      => 'https://example.com/cap-contract-author',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
			)
		);

		add_coauthors( $post_id, array( 'cap-contract-author' ) );

		$post    = get_post( $post_id );
		$authors = byline_feed_get_authors( $post );
		$ids     = array_column( $authors, 'id' );
		$author  = $authors[ array_search( 'cap-contract-author', $ids, true ) ];

		// Verify all required and optional contract fields are present and typed correctly.
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
}
