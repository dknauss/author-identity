<?php
/**
 * Tests for the Perspective meta field.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Tests;

use WP_UnitTestCase;
use function Byline_Feed\byline_feed_get_perspective;
use function Byline_Feed\Perspective\enqueue_editor_assets;
use function Byline_Feed\Perspective\register_meta;

class Test_Perspective extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( 'byline_feed_perspective' );
		parent::tear_down();
	}

	public function test_returns_empty_when_not_set(): void {
		$post_id = self::factory()->post->create();
		$post    = get_post( $post_id );

		$this->assertSame( '', byline_feed_get_perspective( $post ) );
	}

	public function test_returns_valid_perspective(): void {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_byline_perspective', 'reporting' );
		$post = get_post( $post_id );

		$this->assertSame( 'reporting', byline_feed_get_perspective( $post ) );
	}

	public function test_rejects_invalid_perspective(): void {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_byline_perspective', 'propaganda' );
		$post = get_post( $post_id );

		$this->assertSame( '', byline_feed_get_perspective( $post ) );
	}

	public function test_all_allowed_values_accepted(): void {
		$allowed = array(
			'personal', 'reporting', 'analysis', 'official',
			'sponsored', 'satire', 'review', 'announcement',
			'tutorial', 'curation', 'fiction', 'interview',
		);

		$post_id = self::factory()->post->create();
		$post    = get_post( $post_id );

		foreach ( $allowed as $value ) {
			update_post_meta( $post_id, '_byline_perspective', $value );
			clean_post_cache( $post_id );
			$post = get_post( $post_id );

			$this->assertSame( $value, byline_feed_get_perspective( $post ), "Expected '{$value}' to be accepted." );
		}
	}

	public function test_filter_can_override_perspective(): void {
		$post_id = self::factory()->post->create();
		$post    = get_post( $post_id );

		add_filter( 'byline_feed_perspective', function () {
			return 'satire';
		} );

		$this->assertSame( 'satire', byline_feed_get_perspective( $post ) );
	}

	public function test_meta_is_registered_for_rest_with_expected_shape(): void {
		register_meta();

		$registered = get_registered_meta_keys( 'post', '' );
		$meta       = $registered['_byline_perspective'] ?? null;

		$this->assertIsArray( $meta );
		$this->assertSame( 'string', $meta['type'] );
		$this->assertTrue( $meta['single'] );
		$this->assertTrue( $meta['show_in_rest'] );
		$this->assertIsCallable( $meta['sanitize_callback'] );
		$this->assertIsCallable( $meta['auth_callback'] );
	}

	public function test_editor_assets_enqueue_expected_built_script(): void {
		wp_scripts();
		enqueue_editor_assets();

		$handle = wp_scripts()->registered['byline-feed-perspective-panel'] ?? null;

		$this->assertNotNull( $handle );
		$this->assertContains( 'byline-feed-perspective-panel', wp_scripts()->queue );
		$this->assertStringEndsWith( 'build/perspective-panel.tsx.js', $handle->src );
	}
}
