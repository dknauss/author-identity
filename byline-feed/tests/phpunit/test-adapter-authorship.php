<?php
/**
 * Tests for the Human Made Authorship adapter normalization.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Tests;

use Byline_Feed\Adapter_Authorship;
use ReflectionClass;
use WP_UnitTestCase;

class Test_Adapter_Authorship extends WP_UnitTestCase {

	/**
	 * @var Adapter_Authorship
	 */
	private $adapter;

	public function set_up(): void {
		parent::set_up();
		$this->adapter = new Adapter_Authorship();
	}

	public function test_get_authors_returns_empty_when_authorship_api_missing(): void {
		if ( function_exists( 'Authorship\\get_authors' ) ) {
			$this->markTestSkipped( 'Authorship is active — test only applies without it.' );
		}

		$post_id = self::factory()->post->create();
		$post    = get_post( $post_id );

		$this->assertSame( array(), $this->adapter->get_authors( $post ) );
	}

	public function test_normalize_maps_wp_user_fields_and_plugin_meta(): void {
		$user_id = self::factory()->user->create(
			array(
				'role'          => 'editor',
				'display_name'  => 'HM Editor',
				'user_nicename' => 'hm-editor',
				'description'   => 'HM editor bio.',
				'user_url'      => 'https://example.com/hm-editor',
			)
		);

		update_user_meta( $user_id, 'byline_feed_fediverse', '@hm-editor@example.social' );
		update_user_meta( $user_id, 'byline_feed_ai_consent', 'allow' );
		update_user_meta(
			$user_id,
			'byline_feed_profiles',
			array(
				array(
					'rel'  => 'me',
					'href' => 'https://example.com/hm-editor/social',
				),
			)
		);
		update_user_meta( $user_id, 'byline_feed_now_url', 'https://example.com/hm-editor/now' );
		update_user_meta( $user_id, 'byline_feed_uses_url', 'https://example.com/hm-editor/uses' );

		$user   = get_userdata( $user_id );
		$author = $this->invoke_normalize( $user );

		$this->assertSame( 'hm-editor', $author->id );
		$this->assertSame( 'HM Editor', $author->display_name );
		$this->assertSame( $user_id, $author->user_id );
		$this->assertSame( 'staff', $author->role );
		$this->assertFalse( $author->is_guest );
		$this->assertSame( '@hm-editor@example.social', $author->fediverse );
		$this->assertSame( 'allow', $author->ai_consent );
		$this->assertSame( 'https://example.com/hm-editor/social', $author->profiles[0]['href'] );
		$this->assertSame( 'https://example.com/hm-editor/now', $author->now_url );
		$this->assertSame( 'https://example.com/hm-editor/uses', $author->uses_url );
	}

	public function test_normalize_maps_guest_author_role_with_real_user_id(): void {
		add_role( 'guest-author', 'Guest Author', array() );

		$user_id = self::factory()->user->create(
			array(
				'role'          => 'guest-author',
				'display_name'  => 'HM Guest',
				'user_nicename' => 'hm-guest',
				'description'   => 'HM guest bio.',
				'user_url'      => 'https://example.com/hm-guest',
			)
		);

		$user   = get_userdata( $user_id );
		$author = $this->invoke_normalize( $user );

		$this->assertSame( 'hm-guest', $author->id );
		$this->assertSame( 'guest', $author->role );
		$this->assertTrue( $author->is_guest );
		$this->assertSame( $user_id, $author->user_id );
		$this->assertSame( 'https://example.com/hm-guest', $author->url );
	}

	/**
	 * Invoke the adapter's private normalize() method.
	 *
	 * @param \WP_User $user Authorship user object.
	 * @return object
	 */
	private function invoke_normalize( \WP_User $user ): object {
		$reflection = new ReflectionClass( Adapter_Authorship::class );
		$method     = $reflection->getMethod( 'normalize' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invoke( $this->adapter, $user );
	}
}
