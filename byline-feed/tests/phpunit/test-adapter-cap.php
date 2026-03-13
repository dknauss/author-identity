<?php
/**
 * Tests for the Co-Authors Plus adapter normalization.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Tests;

use Byline_Feed\Adapter_CAP;
use ReflectionClass;
use WP_UnitTestCase;

class Test_Adapter_CAP extends WP_UnitTestCase {

	/**
	 * @var Adapter_CAP
	 */
	private $adapter;

	public function set_up(): void {
		parent::set_up();
		$this->adapter = new Adapter_CAP();
	}

	public function test_get_authors_returns_empty_when_cap_api_missing(): void {
		$post_id = self::factory()->post->create();
		$post    = get_post( $post_id );

		$this->assertSame( array(), $this->adapter->get_authors( $post ) );
	}

	public function test_normalize_maps_wp_user_author_fields(): void {
		$user_id = self::factory()->user->create(
			array(
				'role'          => 'editor',
				'display_name'  => 'Jane Doe',
				'user_nicename' => 'jane-doe',
				'description'   => 'CAP user description.',
				'user_url'      => 'https://example.com/jane',
			)
		);

		update_user_meta( $user_id, 'byline_feed_fediverse', '@jane@example.social' );
		update_user_meta( $user_id, 'byline_feed_ai_consent', 'allow' );

		$coauthor = (object) array(
			'type'          => 'wpuser',
			'ID'            => $user_id,
			'user_nicename' => 'jane-doe',
			'display_name'  => 'Jane Doe',
			'description'   => 'CAP user description.',
			'website'       => 'https://example.com/jane',
		);

		$author = $this->invoke_normalize( $coauthor );

		$this->assertSame( 'jane-doe', $author->id );
		$this->assertSame( 'Jane Doe', $author->display_name );
		$this->assertSame( $user_id, $author->user_id );
		$this->assertSame( 'staff', $author->role );
		$this->assertFalse( $author->is_guest );
		$this->assertSame( '@jane@example.social', $author->fediverse );
		$this->assertSame( 'allow', $author->ai_consent );
	}

	public function test_normalize_maps_guest_author_fields(): void {
		$coauthor = (object) array(
			'type'          => 'guest-author',
			'ID'            => 98765,
			'user_nicename' => 'guest-writer',
			'display_name'  => 'Guest Writer',
			'description'   => 'Guest author bio.',
			'website'       => 'https://example.com/guest',
		);

		$author = $this->invoke_normalize( $coauthor );

		$this->assertSame( 'guest-writer', $author->id );
		$this->assertSame( 'Guest Writer', $author->display_name );
		$this->assertSame( 0, $author->user_id );
		$this->assertSame( 'guest', $author->role );
		$this->assertTrue( $author->is_guest );
		$this->assertSame( '', $author->fediverse );
		$this->assertSame( '', $author->ai_consent );
	}

	/**
	 * Invoke the adapter's private normalize() method.
	 *
	 * @param object $coauthor CAP coauthor object.
	 * @return object
	 */
	private function invoke_normalize( object $coauthor ): object {
		$reflection = new ReflectionClass( Adapter_CAP::class );
		$method     = $reflection->getMethod( 'normalize' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invoke( $this->adapter, $coauthor );
	}
}
