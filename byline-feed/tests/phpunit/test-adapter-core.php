<?php
/**
 * Tests for the Core WordPress adapter.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Tests;

use Byline_Feed\Adapter_Core;
use WP_UnitTestCase;
use function Byline_Feed\get_byline_feed_ap_actor_url_for_user;

class Test_Adapter_Core extends WP_UnitTestCase {

	/**
	 * @var Adapter_Core
	 */
	private $adapter;

	public function set_up(): void {
		parent::set_up();
		$this->adapter = new Adapter_Core();
	}

	public function test_returns_single_author_for_standard_post(): void {
		$user_id = self::factory()->user->create( array(
			'display_name'  => 'Jane Doe',
			'user_nicename' => 'jane-doe',
			'description'   => 'A test author.',
			'user_url'      => 'https://example.com',
		) );

		$post_id = self::factory()->post->create( array(
			'post_author' => $user_id,
		) );

		$post    = get_post( $post_id );
		$authors = $this->adapter->get_authors( $post );

		$this->assertCount( 1, $authors );
		$this->assertSame( 'jane-doe', $authors[0]->id );
		$this->assertSame( 'Jane Doe', $authors[0]->display_name );
		$this->assertSame( 'A test author.', $authors[0]->description );
		$this->assertSame( 'https://example.com', $authors[0]->url );
		$this->assertFalse( $authors[0]->is_guest );
		$this->assertSame( $user_id, $authors[0]->user_id );
	}

	public function test_returns_empty_for_invalid_author(): void {
		$post_id = self::factory()->post->create( array(
			'post_author' => 0,
		) );

		$post    = get_post( $post_id );
		$authors = $this->adapter->get_authors( $post );

		$this->assertSame( array(), $authors );
	}

	public function test_standard_wordpress_roles_map_to_expected_byline_roles(): void {
		$cases = array(
			'administrator' => 'staff',
			'editor'        => 'staff',
			'author'        => 'contributor',
			'contributor'   => 'contributor',
			'subscriber'    => 'contributor',
		);

		foreach ( $cases as $wp_role => $expected_byline_role ) {
			$user_id = self::factory()->user->create( array( 'role' => $wp_role ) );
			$post_id = self::factory()->post->create( array( 'post_author' => $user_id ) );

			$post    = get_post( $post_id );
			$authors = $this->adapter->get_authors( $post );

			$this->assertSame( $expected_byline_role, $authors[0]->role, 'Failed asserting Byline role for WP role ' . $wp_role );
		}
	}

	public function test_all_optional_fields_have_zero_values(): void {
		$user_id = self::factory()->user->create( array(
			'display_name'  => 'Minimal User',
			'user_nicename' => 'minimal',
		) );

		$post_id = self::factory()->post->create( array(
			'post_author' => $user_id,
		) );

		$post    = get_post( $post_id );
		$authors = $this->adapter->get_authors( $post );
		$author  = $authors[0];

		$this->assertIsArray( $author->profiles );
		$this->assertEmpty( $author->profiles );
		$this->assertSame( '', $author->now_url );
		$this->assertSame( '', $author->uses_url );
		$this->assertSame( '', $author->fediverse );
		$this->assertSame( get_byline_feed_ap_actor_url_for_user( $user_id ), $author->ap_actor_url );
		$this->assertSame( '', $author->ai_consent );
	}

	public function test_reads_plugin_owned_profile_meta_for_user(): void {
		$user_id = self::factory()->user->create(
			array(
				'display_name'  => 'Profile User',
				'user_nicename' => 'profile-user',
			)
		);

		update_user_meta(
			$user_id,
			'byline_feed_profiles',
			array(
				array(
					'rel'  => 'me',
					'href' => 'https://example.com/@profile-user',
				),
			)
		);
		update_user_meta( $user_id, 'byline_feed_now_url', 'https://example.com/now/' );
		update_user_meta( $user_id, 'byline_feed_uses_url', 'https://example.com/uses/' );

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
			)
		);

		$post    = get_post( $post_id );
		$authors = $this->adapter->get_authors( $post );
		$author  = $authors[0];

		$this->assertSame( 'https://example.com/@profile-user', $author->profiles[0]['href'] );
		$this->assertSame( 'me', $author->profiles[0]['rel'] );
		$this->assertSame( 'https://example.com/now/', $author->now_url );
		$this->assertSame( 'https://example.com/uses/', $author->uses_url );
		$this->assertSame( get_byline_feed_ap_actor_url_for_user( $user_id ), $author->ap_actor_url );
	}
}
