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
use function Byline_Feed\get_byline_feed_fediverse_for_user;

class Test_Integration_ActivityPub extends WP_UnitTestCase {

	/**
	 * Skip all tests unless the real ActivityPub plugin is active.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! class_exists( '\\Activitypub\\Collection\\Actors' ) || ! function_exists( '\\Activitypub\\user_can_activitypub' ) ) {
			\byline_feed_tests_require_integration( $this, 'BYLINE_ACTIVITYPUB_PLUGIN', 'ActivityPub is not active.' );
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

	public function tear_down(): void {
		delete_option( 'activitypub_use_opengraph' );
		delete_option( 'activitypub_attribution_domains' );
		parent::tear_down();
	}

	/**
	 * Create a user who may federate through ActivityPub.
	 *
	 * @param string $nicename User nicename.
	 * @return int
	 */
	private function activitypub_user( string $nicename ): int {
		$user_id = self::factory()->user->create(
			array(
				'role'          => 'author',
				'user_login'    => $nicename,
				'user_nicename' => $nicename,
			)
		);

		( new \WP_User( $user_id ) )->add_cap( 'activitypub' );

		return $user_id;
	}

	public function test_local_activitypub_handle_is_derived_when_nothing_is_typed(): void {
		$user_id = $this->activitypub_user( 'apderived' );
		$actor   = \Activitypub\Collection\Actors::get_by_id( $user_id );

		$this->assertFalse( is_wp_error( $actor ) );
		$this->assertSame( '@' . $actor->get_webfinger(), get_byline_feed_fediverse_for_user( $user_id ) );
	}

	public function test_typed_handle_overrides_local_activitypub_handle(): void {
		$user_id = $this->activitypub_user( 'aptyped' );
		update_user_meta( $user_id, 'byline_feed_fediverse', '@elsewhere@example.social' );

		$this->assertSame( '@elsewhere@example.social', get_byline_feed_fediverse_for_user( $user_id ) );
	}

	public function test_no_local_handle_when_site_is_not_an_attribution_domain(): void {
		$user_id = $this->activitypub_user( 'apnodomain' );

		update_option( 'activitypub_attribution_domains', 'other.example' );
		$this->assertSame( '', get_byline_feed_fediverse_for_user( $user_id ) );

		delete_option( 'activitypub_attribution_domains' );
		update_option( 'activitypub_use_opengraph', '0' );
		$this->assertSame( '', get_byline_feed_fediverse_for_user( $user_id ) );
	}

	public function test_no_local_handle_for_user_who_cannot_federate(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->assertSame( '', get_byline_feed_fediverse_for_user( $user_id ) );
	}

	public function test_activitypub_tag_gives_way_to_every_byline_author(): void {
		$user_id = $this->activitypub_user( 'apheadauthor' );
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
			)
		);
		update_user_meta( $user_id, 'byline_feed_fediverse', '@lead@example.social' );

		$this->go_to( get_permalink( $post_id ) );

		$metadata = apply_filters( 'opengraph_metadata', array() );

		$this->assertArrayNotHasKey( 'fediverse:creator', $metadata );

		ob_start();
		do_action( 'wp_head' );
		$head = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $head, 'fediverse:creator' ) );
		$this->assertStringContainsString( 'content="@lead@example.social"', $head );
	}
}
