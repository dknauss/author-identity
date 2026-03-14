<?php
/**
 * Tests for JSON Feed Byline output.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Tests;

use WP_UnitTestCase;
use function Byline_Feed\Feed_JSON\render_json_feed;

class Test_Feed_JSON extends WP_UnitTestCase {

	/**
	 * Capture and decode the fallback JSON Feed output.
	 *
	 * @return array<string, mixed>
	 */
	private function render_feed(): array {
		set_error_handler(
			static function ( int $errno, string $errstr ): bool {
				if ( E_WARNING === $errno && false !== strpos( $errstr, 'Cannot modify header information' ) ) {
					return true;
				}

				return false;
			}
		);

		ob_start();
		render_json_feed();
		$json = (string) ob_get_clean();
		restore_error_handler();

		$this->assertJson( $json, 'JSON Feed output must be valid JSON.' );

		$decoded = json_decode( $json, true );
		$this->assertIsArray( $decoded, 'Decoded JSON Feed must be an array.' );

		return $decoded;
	}

	/**
	 * Index feed items by title for stable assertions.
	 *
	 * @param array<int, array<string, mixed>> $items Feed items.
	 * @return array<string, array<string, mixed>>
	 */
	private function index_items_by_title( array $items ): array {
		$indexed = array();

		foreach ( $items as $item ) {
			if ( isset( $item['title'] ) && is_string( $item['title'] ) ) {
				$indexed[ $item['title'] ] = $item;
			}
		}

		return $indexed;
	}

	public function tear_down(): void {
		remove_all_filters( 'byline_feed_authors' );
		remove_all_filters( 'byline_feed_json_author_extension' );
		remove_all_filters( 'byline_feed_json_item' );
		remove_all_filters( 'byline_feed_json_feed' );
		remove_all_filters( 'the_content' );
		parent::tear_down();
	}

	public function test_render_json_feed_returns_valid_json_feed_1_1_with_org_metadata(): void {
		update_option( 'blogname', 'Byline Feed Test Site' );
		update_option( 'blogdescription', 'JSON Feed test description' );

		$user_id = self::factory()->user->create(
			array(
				'display_name'  => 'JSON Author',
				'user_nicename' => 'json-author',
			)
		);

		self::factory()->post->create(
			array(
				'post_author'  => $user_id,
				'post_status'  => 'publish',
				'post_title'   => 'JSON Feed Test Post',
				'post_content' => 'Rendered content',
			)
		);

		$feed = $this->render_feed();

		$this->assertSame( 'https://jsonfeed.org/version/1.1', $feed['version'] );
		$this->assertSame( 'Byline Feed Test Site', $feed['title'] );
		$this->assertSame( home_url( '/' ), $feed['home_page_url'] );
		$this->assertSame( home_url( '/feed/json' ), $feed['feed_url'] );
		$this->assertSame( '1.0', $feed['_byline']['spec_version'] );
		$this->assertSame( 'Byline Feed Test Site', $feed['_byline']['org']['name'] );
		$this->assertSame( get_bloginfo( 'url' ), $feed['_byline']['org']['url'] );
		$this->assertCount( 1, $feed['items'] );
		$this->assertSame( 'JSON Feed Test Post', $feed['items'][0]['title'] );
	}

	public function test_feed_level_authors_are_deduplicated_and_include_byline_extensions(): void {
		update_option( 'posts_per_rss', 10 );

		$user_id = self::factory()->user->create(
			array(
				'display_name'  => 'Shared JSON Author',
				'user_nicename' => 'shared-json-author',
			)
		);

		self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
				'post_title'  => 'First JSON Post',
			)
		);

		self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
				'post_title'  => 'Second JSON Post',
			)
		);

		$feed = $this->render_feed();

		$this->assertCount( 1, $feed['authors'] );
		$this->assertSame( 'Shared JSON Author', $feed['authors'][0]['name'] );
		$this->assertSame( 'shared-json-author', $feed['authors'][0]['_byline']['id'] );
	}

	public function test_item_authors_carry_correct_roles_and_empty_optional_fields_are_omitted(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Filtered JSON Post',
			)
		);

		add_filter(
			'byline_feed_authors',
			static function ( $authors, $post ) use ( $post_id ) {
				if ( $post_id !== $post->ID ) {
					return $authors;
				}

				return array(
					(object) array(
						'id'           => 'json-one',
						'display_name' => 'JSON One',
						'role'         => 'staff',
					),
					(object) array(
						'id'           => 'json-two',
						'display_name' => 'JSON Two',
						'role'         => 'guest',
						'is_guest'     => true,
					),
				);
			},
			10,
			2
		);

		$feed  = $this->render_feed();
		$items = $this->index_items_by_title( $feed['items'] );
		$item  = $items['Filtered JSON Post'];

		$this->assertCount( 2, $item['authors'] );
		$this->assertSame( 'staff', $item['authors'][0]['_byline']['role'] );
		$this->assertSame( 'guest', $item['authors'][1]['_byline']['role'] );
		$this->assertTrue( $item['authors'][1]['_byline']['is_guest'] );

		$this->assertArrayNotHasKey( 'url', $item['authors'][0] );
		$this->assertArrayNotHasKey( 'avatar', $item['authors'][0] );
		$this->assertArrayNotHasKey( 'context', $item['authors'][0]['_byline'] );
		$this->assertArrayNotHasKey( 'profiles', $item['authors'][0]['_byline'] );
		$this->assertArrayNotHasKey( 'now_url', $item['authors'][0]['_byline'] );
		$this->assertArrayNotHasKey( 'uses_url', $item['authors'][0]['_byline'] );
		$this->assertArrayNotHasKey( 'fediverse', $item['authors'][0]['_byline'] );
	}

	public function test_item_byline_perspective_is_present_when_set_and_absent_when_unset(): void {
		$user_id = self::factory()->user->create();

		$unset_post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
				'post_title'  => 'No Perspective Post',
				'post_date'   => '2026-03-13 10:00:00',
			)
		);

		$set_post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
				'post_title'  => 'Perspective Post',
				'post_date'   => '2026-03-13 11:00:00',
			)
		);

		update_post_meta( $set_post_id, '_byline_perspective', 'analysis' );
		$this->assertNotSame( $unset_post_id, $set_post_id );

		$feed  = $this->render_feed();
		$items = $this->index_items_by_title( $feed['items'] );

		$this->assertSame( 'analysis', $items['Perspective Post']['_byline']['perspective'] );
		$this->assertArrayNotHasKey( '_byline', $items['No Perspective Post'] );
	}

	public function test_filter_json_feed_item_can_add_standalone_byline_data_without_breaking_feed_shape(): void {
		$user_id = self::factory()->user->create();
		self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
				'post_title'  => 'Filtered Item Hook Post',
			)
		);

		add_filter(
			'byline_feed_json_item',
			static function ( array $item ): array {
				$item['_byline']['custom_flag'] = 'yes';
				return $item;
			}
		);

		$feed  = $this->render_feed();
		$items = $this->index_items_by_title( $feed['items'] );

		$this->assertSame( 'yes', $items['Filtered Item Hook Post']['_byline']['custom_flag'] );
	}
}
