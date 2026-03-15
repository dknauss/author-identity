<?php
/**
 * Integration tests for the Human Made Authorship adapter against a real Authorship installation.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Tests;

use Byline_Feed\Adapter_Authorship;
use WP_UnitTestCase;
use function Byline_Feed\byline_feed_get_adapter;
use function Byline_Feed\byline_feed_get_authors;

class Test_Integration_Authorship extends WP_UnitTestCase {

	/**
	 * Skip all tests if Authorship is not active.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! function_exists( 'Authorship\\get_authors' ) || ! function_exists( 'Authorship\\set_authors' ) ) {
			$this->markTestSkipped( 'Authorship is not active.' );
		}
	}

	public function test_authorship_is_detected_as_active_adapter(): void {
		global $_byline_feed_adapter;
		$_byline_feed_adapter = null;

		$adapter = byline_feed_get_adapter();

		$this->assertInstanceOf( Adapter_Authorship::class, $adapter );
	}

	public function test_get_authors_returns_all_authorship_users_in_order(): void {
		$user_one = self::factory()->user->create(
			array(
				'display_name'  => 'HM Author One',
				'user_nicename' => 'hm-author-one',
			)
		);

		$user_two = self::factory()->user->create(
			array(
				'display_name'  => 'HM Author Two',
				'user_nicename' => 'hm-author-two',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_one,
				'post_status' => 'publish',
			)
		);

		\Authorship\set_authors( get_post( $post_id ), array( $user_one, $user_two ) );

		$post    = get_post( $post_id );
		$authors = byline_feed_get_authors( $post );
		$ids     = array_column( $authors, 'id' );

		$this->assertSame( array( 'hm-author-one', 'hm-author-two' ), $ids );
	}

	public function test_guest_authorship_user_maps_to_guest_role_with_real_user_meta(): void {
		$guest_id = self::factory()->user->create(
			array(
				'role'          => 'guest-author',
				'display_name'  => 'HM Guest Author',
				'user_nicename' => 'hm-guest-author',
			)
		);

		update_user_meta( $guest_id, 'byline_feed_fediverse', '@hmguest@example.social' );
		update_user_meta(
			$guest_id,
			'byline_feed_profiles',
			array(
				array(
					'rel'  => 'me',
					'href' => 'https://example.com/hm-guest-author',
				),
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
			)
		);

		\Authorship\set_authors( get_post( $post_id ), array( $guest_id ) );

		$post    = get_post( $post_id );
		$authors = byline_feed_get_authors( $post );

		$this->assertCount( 1, $authors );
		$this->assertTrue( $authors[0]->is_guest );
		$this->assertSame( 'guest', $authors[0]->role );
		$this->assertSame( $guest_id, $authors[0]->user_id );
		$this->assertSame( '@hmguest@example.social', $authors[0]->fediverse );
		$this->assertSame( 'https://example.com/hm-guest-author', $authors[0]->profiles[0]['href'] );
	}

	public function test_linked_user_meta_is_exposed_in_authorship_context(): void {
		$user_id = self::factory()->user->create(
			array(
				'display_name'  => 'HM Profile Author',
				'user_nicename' => 'hm-profile-author',
				'user_url'      => 'https://example.com/hm-profile-author',
			)
		);

		update_user_meta( $user_id, 'byline_feed_now_url', 'https://example.com/hm-profile-author/now' );
		update_user_meta( $user_id, 'byline_feed_uses_url', 'https://example.com/hm-profile-author/uses' );
		update_user_meta( $user_id, 'byline_feed_ai_consent', 'deny' );

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
			)
		);

		\Authorship\set_authors( get_post( $post_id ), array( $user_id ) );

		$post    = get_post( $post_id );
		$authors = byline_feed_get_authors( $post );

		$this->assertCount( 1, $authors );
		$this->assertSame( 'https://example.com/hm-profile-author', $authors[0]->url );
		$this->assertSame( 'https://example.com/hm-profile-author/now', $authors[0]->now_url );
		$this->assertSame( 'https://example.com/hm-profile-author/uses', $authors[0]->uses_url );
		$this->assertSame( 'deny', $authors[0]->ai_consent );
	}
}
