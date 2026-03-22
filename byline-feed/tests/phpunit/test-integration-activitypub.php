<?php
/**
 * Integration tests for ActivityPub-derived actor URL resolution.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Tests;

use WP_UnitTestCase;
use function Byline_Feed\byline_feed_get_authors;
use function Byline_Feed\get_byline_feed_ap_actor_url_for_user;

class Test_Integration_ActivityPub extends WP_UnitTestCase {

	/**
	 * Skip all tests unless the real ActivityPub plugin is active.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! class_exists( '\\Activitypub\\Collection\\Actors' ) || ! function_exists( '\\Activitypub\\user_can_activitypub' ) ) {
			$this->markTestSkipped( 'ActivityPub is not active.' );
		}
	}

	public function test_activitypub_actor_url_is_resolved_for_enabled_user(): void {
		$user_id = self::factory()->user->create(
			array(
				'role'          => 'author',
				'user_nicename' => 'activitypub-author',
			)
		);

		$user = new \WP_User( $user_id );
		$user->add_cap( 'activitypub' );

		$actor = \Activitypub\Collection\Actors::get_by_id( $user_id );

		$this->assertFalse( is_wp_error( $actor ) );
		$this->assertSame( $actor->get_id(), get_byline_feed_ap_actor_url_for_user( $user_id ) );
	}

	public function test_core_author_output_exposes_activitypub_actor_url_when_available(): void {
		$user_id = self::factory()->user->create(
			array(
				'role'          => 'author',
				'display_name'  => 'ActivityPub Core Author',
				'user_nicename' => 'activitypub-core-author',
			)
		);

		$user = new \WP_User( $user_id );
		$user->add_cap( 'activitypub' );

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
			)
		);

		$actor = \Activitypub\Collection\Actors::get_by_id( $user_id );
		$post  = get_post( $post_id );

		$this->assertFalse( is_wp_error( $actor ) );

		$authors = byline_feed_get_authors( $post );

		$this->assertCount( 1, $authors );
		$this->assertSame( $actor->get_id(), $authors[0]->ap_actor_url );
	}
}
