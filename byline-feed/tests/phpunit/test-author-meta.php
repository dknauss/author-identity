<?php
/**
 * Tests for author meta helpers and user-profile save/render paths.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Tests;

use WP_UnitTestCase;
use function Byline_Feed\get_byline_feed_profiles_for_user;
use function Byline_Feed\get_byline_feed_now_url_for_user;
use function Byline_Feed\get_byline_feed_uses_url_for_user;
use function Byline_Feed\normalize_byline_profiles;
use function Byline_Feed\parse_byline_profiles_textarea;
use function Byline_Feed\render_author_meta_fields;
use function Byline_Feed\save_author_meta_fields;

class Test_Author_Meta extends WP_UnitTestCase {

	/**
	 * Capture output from a callback.
	 *
	 * @param callable $callback Callback to execute.
	 * @return string
	 */
	private function capture_output( callable $callback ): string {
		ob_start();
		$callback();
		return (string) ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// normalize_byline_profiles
	// -------------------------------------------------------------------------

	public function test_normalize_profiles_from_array(): void {
		$input = array(
			array(
				'href' => 'https://example.com/profile',
				'rel'  => 'me',
			),
		);

		$result = normalize_byline_profiles( $input );

		$this->assertCount( 1, $result );
		$this->assertSame( 'https://example.com/profile', $result[0]['href'] );
		$this->assertSame( 'me', $result[0]['rel'] );
	}

	public function test_normalize_profiles_drops_entries_with_missing_href(): void {
		$input = array(
			array( 'rel' => 'me' ),
		);

		$result = normalize_byline_profiles( $input );

		$this->assertEmpty( $result );
	}

	public function test_normalize_profiles_drops_entries_with_missing_rel(): void {
		$input = array(
			array( 'href' => 'https://example.com/profile' ),
		);

		$result = normalize_byline_profiles( $input );

		$this->assertEmpty( $result );
	}

	public function test_normalize_profiles_returns_empty_for_non_array(): void {
		$result = normalize_byline_profiles( false );

		$this->assertSame( array(), $result );
	}

	// -------------------------------------------------------------------------
	// parse_byline_profiles_textarea
	// -------------------------------------------------------------------------

	public function test_parse_textarea_parses_rel_pipe_url_format(): void {
		$textarea = "me | https://mastodon.social/@user\nauthor | https://example.com/about";

		$result = parse_byline_profiles_textarea( $textarea );

		$this->assertCount( 2, $result );
		$this->assertSame( 'me', $result[0]['rel'] );
		$this->assertSame( 'https://mastodon.social/@user', $result[0]['href'] );
		$this->assertSame( 'author', $result[1]['rel'] );
	}

	public function test_parse_textarea_skips_lines_without_pipe(): void {
		$textarea = "not a valid line\nme | https://example.com/profile";

		$result = parse_byline_profiles_textarea( $textarea );

		$this->assertCount( 1, $result );
		$this->assertSame( 'me', $result[0]['rel'] );
	}

	public function test_parse_textarea_skips_blank_lines(): void {
		$textarea = "\nme | https://example.com/profile\n\n";

		$result = parse_byline_profiles_textarea( $textarea );

		$this->assertCount( 1, $result );
	}

	// -------------------------------------------------------------------------
	// User meta getters
	// -------------------------------------------------------------------------

	public function test_get_profiles_returns_empty_when_no_meta(): void {
		$user_id = self::factory()->user->create();

		$result = get_byline_feed_profiles_for_user( $user_id );

		$this->assertSame( array(), $result );
	}

	public function test_get_now_url_returns_empty_when_no_meta(): void {
		$user_id = self::factory()->user->create();

		$result = get_byline_feed_now_url_for_user( $user_id );

		$this->assertSame( '', $result );
	}

	public function test_get_uses_url_returns_empty_when_no_meta(): void {
		$user_id = self::factory()->user->create();

		$result = get_byline_feed_uses_url_for_user( $user_id );

		$this->assertSame( '', $result );
	}

	public function test_get_profiles_returns_stored_profiles(): void {
		$user_id = self::factory()->user->create();
		update_user_meta(
			$user_id,
			'byline_feed_profiles',
			array(
				array(
					'href' => 'https://example.com/profile',
					'rel'  => 'me',
				),
			)
		);

		$result = get_byline_feed_profiles_for_user( $user_id );

		$this->assertCount( 1, $result );
		$this->assertSame( 'https://example.com/profile', $result[0]['href'] );
	}

	// -------------------------------------------------------------------------
	// render_author_meta_fields
	// -------------------------------------------------------------------------

	public function test_render_outputs_nonce_field(): void {
		$user_id = self::factory()->user->create();
		$user    = get_user_by( 'id', $user_id );

		$output = $this->capture_output(
			static function () use ( $user ) {
				render_author_meta_fields( $user );
			}
		);

		$this->assertStringContainsString( 'byline_feed_author_meta_nonce', $output );
	}

	public function test_render_outputs_profiles_textarea(): void {
		$user_id = self::factory()->user->create();
		$user    = get_user_by( 'id', $user_id );

		$output = $this->capture_output(
			static function () use ( $user ) {
				render_author_meta_fields( $user );
			}
		);

		$this->assertStringContainsString( 'name="byline_feed_profiles"', $output );
	}

	public function test_render_outputs_now_url_field(): void {
		$user_id = self::factory()->user->create();
		$user    = get_user_by( 'id', $user_id );

		$output = $this->capture_output(
			static function () use ( $user ) {
				render_author_meta_fields( $user );
			}
		);

		$this->assertStringContainsString( 'name="byline_feed_now_url"', $output );
	}

	public function test_render_outputs_uses_url_field(): void {
		$user_id = self::factory()->user->create();
		$user    = get_user_by( 'id', $user_id );

		$output = $this->capture_output(
			static function () use ( $user ) {
				render_author_meta_fields( $user );
			}
		);

		$this->assertStringContainsString( 'name="byline_feed_uses_url"', $output );
	}

	// -------------------------------------------------------------------------
	// save_author_meta_fields
	// -------------------------------------------------------------------------

	public function test_save_returns_early_without_capability(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$other   = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		wp_set_current_user( $user_id );

		$_POST = array(
			'byline_feed_author_meta_nonce' => wp_create_nonce( 'byline_feed_author_meta' ),
			'byline_feed_now_url'           => 'https://example.com/now',
		);

		save_author_meta_fields( $other );

		$this->assertSame( '', get_byline_feed_now_url_for_user( $other ) );

		$_POST = array();
	}

	public function test_save_returns_early_without_nonce(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_POST = array(
			'byline_feed_now_url' => 'https://example.com/now',
		);

		save_author_meta_fields( $user_id );

		$this->assertSame( '', get_byline_feed_now_url_for_user( $user_id ) );

		$_POST = array();
	}

	public function test_save_stores_now_url_with_valid_nonce_and_capability(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_POST = array(
			'byline_feed_author_meta_nonce' => wp_create_nonce( 'byline_feed_author_meta' ),
			'byline_feed_now_url'           => 'https://example.com/now/',
		);

		save_author_meta_fields( $user_id );

		$this->assertSame( 'https://example.com/now/', get_byline_feed_now_url_for_user( $user_id ) );

		$_POST = array();
	}

	public function test_save_deletes_now_url_when_empty(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		update_user_meta( $user_id, 'byline_feed_now_url', 'https://example.com/now/' );

		$_POST = array(
			'byline_feed_author_meta_nonce' => wp_create_nonce( 'byline_feed_author_meta' ),
			'byline_feed_now_url'           => '',
		);

		save_author_meta_fields( $user_id );

		$this->assertSame( '', get_byline_feed_now_url_for_user( $user_id ) );

		$_POST = array();
	}

	public function test_save_stores_profiles_from_textarea(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$_POST = array(
			'byline_feed_author_meta_nonce' => wp_create_nonce( 'byline_feed_author_meta' ),
			'byline_feed_profiles'          => "me | https://mastodon.social/@user",
		);

		save_author_meta_fields( $user_id );

		$profiles = get_byline_feed_profiles_for_user( $user_id );

		$this->assertCount( 1, $profiles );
		$this->assertSame( 'me', $profiles[0]['rel'] );
		$this->assertSame( 'https://mastodon.social/@user', $profiles[0]['href'] );

		$_POST = array();
	}

	public function test_save_deletes_profiles_when_empty(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		update_user_meta( $user_id, 'byline_feed_profiles', array( array( 'href' => 'https://example.com', 'rel' => 'me' ) ) );

		$_POST = array(
			'byline_feed_author_meta_nonce' => wp_create_nonce( 'byline_feed_author_meta' ),
			'byline_feed_profiles'          => '',
		);

		save_author_meta_fields( $user_id );

		$this->assertSame( array(), get_byline_feed_profiles_for_user( $user_id ) );

		$_POST = array();
	}
}
