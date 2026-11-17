<?php
/**
 * Integration tests for The SEO Framework's fediverse output.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Tests;

use WP_UnitTestCase;
use function Byline_Feed\get_byline_feed_fediverse_for_user;

class Test_Integration_TSF extends WP_UnitTestCase {

	/**
	 * Skip all tests unless the real The SEO Framework plugin is active.
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! function_exists( 'tsf' ) || ! class_exists( '\\The_SEO_Framework\\Meta\\Fediverse' ) ) {
			\byline_feed_tests_require_integration( $this, 'BYLINE_TSF_PLUGIN', 'The SEO Framework 5.2+ is not active.' );
		}
	}

	public function tear_down(): void {
		remove_all_filters( 'byline_feed_authors' );
		parent::tear_down();
	}

	/**
	 * Give a user a handle in TSF's own profile field.
	 *
	 * @param int    $user_id User ID.
	 * @param string $handle  Handle.
	 */
	private function set_tsf_handle( int $user_id, string $handle ): void {
		\The_SEO_Framework\Data\Plugin\User::save_meta( $user_id, array( 'fediverse_page' => $handle ) );
	}

	/**
	 * Run TSF's head output for a URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function head_for( string $url ): string {
		$this->go_to( $url );

		ob_start();
		do_action( 'wp_head' );
		return (string) ob_get_clean();
	}

	public function test_tsf_profile_handle_is_used_when_nothing_is_typed(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->set_tsf_handle( $user_id, '@tsfauthor@example.social' );

		$this->assertSame( '@tsfauthor@example.social', get_byline_feed_fediverse_for_user( $user_id ) );
	}

	public function test_page_carries_every_author_and_no_tsf_duplicate(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->set_tsf_handle( $user_id, '@lead@example.social' );
		\The_SEO_Framework\Data\Plugin::update_option( 'fediverse_site', '@site@example.social' );

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
			)
		);

		add_filter(
			'byline_feed_authors',
			static function ( $authors, $post ) use ( $post_id ) {
				if ( $post->ID !== $post_id ) {
					return $authors;
				}

				$authors[] = (object) array(
					'id'           => 'co-author',
					'display_name' => 'Co Author',
					'fediverse'    => '@co@example.social',
				);

				return $authors;
			},
			10,
			2
		);

		$head = $this->head_for( get_permalink( $post_id ) );

		$this->assertSame( 2, substr_count( $head, 'name="fediverse:creator"' ) );
		$this->assertStringContainsString( 'content="@lead@example.social"', $head );
		$this->assertStringContainsString( 'content="@co@example.social"', $head );
		$this->assertStringNotContainsString( 'content="@site@example.social"', $head );
		$this->assertStringContainsString( 'rel="me"', $head, 'TSF rel=me links should stay.' );
	}

	public function test_tsf_tag_stays_when_byline_feed_has_no_handle(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\The_SEO_Framework\Data\Plugin::update_option( 'fediverse_site', '@site@example.social' );
		update_user_meta( $user_id, 'byline_feed_fediverse_source', 'none' );

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
			)
		);

		$head = $this->head_for( get_permalink( $post_id ) );

		// TSF memoizes per request, so its exact handle can carry over from an
		// earlier test here. What matters: its one tag is kept, and it is TSF's.
		$this->assertSame( 1, substr_count( $head, 'fediverse:creator' ) );
		$this->assertLessThan(
			strpos( $head, '<!-- / The SEO Framework' ),
			strpos( $head, 'name="fediverse:creator"' )
		);
	}
}
