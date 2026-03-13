<?php
/**
 * Tests for the PublishPress Authors adapter normalization.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Tests;

use Byline_Feed\Adapter_PPA;
use ReflectionClass;
use WP_UnitTestCase;

class Test_Adapter_PPA extends WP_UnitTestCase {

	/**
	 * @var Adapter_PPA
	 */
	private $adapter;

	public function set_up(): void {
		parent::set_up();
		$this->adapter = new Adapter_PPA();
	}

	public function test_get_authors_returns_empty_when_ppa_api_missing(): void {
		$post_id = self::factory()->post->create();
		$post    = get_post( $post_id );

		$this->assertSame( array(), $this->adapter->get_authors( $post ) );
	}

	public function test_normalize_prefers_term_meta_and_maps_linked_user_fields(): void {
		$user_id = self::factory()->user->create(
			array(
				'role'          => 'author',
				'display_name'  => 'Alex Author',
				'user_nicename' => 'alex-author',
				'description'   => 'User profile bio.',
				'user_url'      => 'https://example.com/alex',
			)
		);

		update_user_meta( $user_id, 'byline_feed_fediverse', '@alex@example.social' );
		update_user_meta( $user_id, 'byline_feed_ai_consent', 'deny' );

		$term = wp_insert_term( 'Alex Author Term', 'category', array( 'slug' => 'alex-term' ) );
		$this->assertIsArray( $term );
		$term_id = (int) $term['term_id'];

		update_term_meta( $term_id, 'description', 'Term meta bio.' );
		update_term_meta( $term_id, 'avatar', 'https://cdn.example.com/alex-avatar.jpg' );

		$author_object = (object) array(
			'slug'         => 'alex-author',
			'display_name' => 'Alex Author',
			'user_id'      => $user_id,
			'term_id'      => $term_id,
			'is_guest'     => false,
		);

		$author = $this->invoke_normalize( $author_object );

		$this->assertSame( 'alex-author', $author->id );
		$this->assertSame( 'Term meta bio.', $author->description );
		$this->assertSame( 'https://example.com/alex', $author->url );
		$this->assertSame( 'https://cdn.example.com/alex-avatar.jpg', $author->avatar_url );
		$this->assertSame( 'contributor', $author->role );
		$this->assertFalse( $author->is_guest );
		$this->assertSame( '@alex@example.social', $author->fediverse );
		$this->assertSame( 'deny', $author->ai_consent );
	}

	public function test_normalize_falls_back_to_user_profile_when_term_meta_missing(): void {
		$user_id = self::factory()->user->create(
			array(
				'display_name'  => 'Fallback User',
				'user_nicename' => 'fallback-user',
				'description'   => 'Fallback bio.',
				'user_url'      => 'https://example.com/fallback',
			)
		);

		$term = wp_insert_term( 'Fallback Term', 'category', array( 'slug' => 'fallback-term' ) );
		$this->assertIsArray( $term );
		$term_id = (int) $term['term_id'];

		$author_object = (object) array(
			'slug'         => 'fallback-user',
			'display_name' => 'Fallback User',
			'user_id'      => $user_id,
			'term_id'      => $term_id,
			'is_guest'     => false,
		);

		$author = $this->invoke_normalize( $author_object );

		$this->assertSame( 'Fallback bio.', $author->description );
		$this->assertSame( 'https://example.com/fallback', $author->url );
		$this->assertNotSame( '', $author->avatar_url );
	}

	public function test_normalize_maps_guest_author_to_guest_role(): void {
		$term = wp_insert_term( 'Guest Contributor', 'category', array( 'slug' => 'guest-contributor' ) );
		$this->assertIsArray( $term );
		$term_id = (int) $term['term_id'];

		update_term_meta( $term_id, 'description', 'Guest term bio.' );
		update_term_meta( $term_id, 'avatar', 'https://cdn.example.com/guest.jpg' );

		$author_object = (object) array(
			'slug'         => 'guest-contributor',
			'display_name' => 'Guest Contributor',
			'user_id'      => 0,
			'term_id'      => $term_id,
			'is_guest'     => true,
		);

		$author = $this->invoke_normalize( $author_object );

		$this->assertTrue( $author->is_guest );
		$this->assertSame( 'guest', $author->role );
		$this->assertSame( 0, $author->user_id );
		$this->assertSame( '', $author->url );
		$this->assertSame( '', $author->fediverse );
		$this->assertSame( '', $author->ai_consent );
	}

	/**
	 * Invoke the adapter's private normalize() method.
	 *
	 * @param object $author_object PPA author object.
	 * @return object
	 */
	private function invoke_normalize( object $author_object ): object {
		$reflection = new ReflectionClass( Adapter_PPA::class );
		$method     = $reflection->getMethod( 'normalize' );
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		return $method->invoke( $this->adapter, $author_object );
	}
}
