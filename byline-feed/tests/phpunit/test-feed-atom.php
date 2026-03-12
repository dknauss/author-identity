<?php
/**
 * Tests for Atom Byline feed output.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Tests;

use WP_UnitTestCase;
use function Byline_Feed\Feed_Atom\output_contributors;
use function Byline_Feed\Feed_Atom\output_entry;
use function Byline_Feed\Feed_Atom\output_namespace;

class Test_Feed_Atom extends WP_UnitTestCase {

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

	/**
	 * Set the global feed query posts used by output_contributors().
	 *
	 * @param int[] $post_ids Post IDs.
	 */
	private function set_feed_posts( array $post_ids ): void {
		global $wp_query;

		$wp_query        = new \WP_Query();
		$wp_query->posts = array_map( 'get_post', $post_ids );
	}

	/**
	 * Set current global post used by output_entry().
	 *
	 * @param int $post_id Post ID.
	 */
	private function set_current_post( int $post_id ): void {
		global $post;

		$post = get_post( $post_id );
		setup_postdata( $post );
	}

	public function tear_down(): void {
		remove_all_filters( 'byline_feed_authors' );
		remove_all_filters( 'byline_feed_person_xml' );
		remove_all_filters( 'byline_feed_item_xml' );
		wp_reset_postdata();
		parent::tear_down();
	}

	public function test_byline_namespace_is_declared(): void {
		$feed = $this->capture_output(
			static function () {
				output_namespace();
			}
		);

		$this->assertStringContainsString(
			'xmlns:byline="https://bylinespec.org/1.0"',
			$feed
		);
	}

	public function test_contributors_block_present(): void {
		$user_id = self::factory()->user->create(
			array(
				'display_name' => 'Atom Author',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
			)
		);

		$this->set_feed_posts( array( $post_id ) );

		$feed = $this->capture_output(
			static function () {
				output_contributors();
			}
		);

		$this->assertStringContainsString( '<byline:contributors>', $feed );
		$this->assertStringContainsString( '<byline:person', $feed );
		$this->assertStringContainsString( '<byline:name>Atom Author</byline:name>', $feed );
	}

	public function test_entry_author_ref_present(): void {
		$user_id = self::factory()->user->create(
			array(
				'user_nicename' => 'atom-author',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
			)
		);

		$this->set_current_post( $post_id );

		$feed = $this->capture_output(
			static function () {
				output_entry();
			}
		);

		$this->assertStringContainsString( '<byline:author ref="atom-author"/>', $feed );
	}

	public function test_perspective_in_entry_when_set(): void {
		$user_id = self::factory()->user->create();
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
			)
		);

		update_post_meta( $post_id, '_byline_perspective', 'analysis' );

		$this->set_current_post( $post_id );

		$feed = $this->capture_output(
			static function () {
				output_entry();
			}
		);

		$this->assertStringContainsString(
			'<byline:perspective>analysis</byline:perspective>',
			$feed
		);
	}

	public function test_contributors_are_deduplicated_across_posts(): void {
		$user_id = self::factory()->user->create(
			array(
				'display_name'  => 'Shared Atom Author',
				'user_nicename' => 'shared-atom-author',
			)
		);

		$post_ids = array(
			self::factory()->post->create(
				array(
					'post_author' => $user_id,
					'post_status' => 'publish',
				)
			),
			self::factory()->post->create(
				array(
					'post_author' => $user_id,
					'post_status' => 'publish',
				)
			),
		);

		$this->set_feed_posts( $post_ids );

		$feed = $this->capture_output(
			static function () {
				output_contributors();
			}
		);

		$this->assertSame( 1, substr_count( $feed, '<byline:person id="shared-atom-author">' ) );
	}

	public function test_entry_outputs_multiple_author_refs_when_filtered_authors_present(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$this->set_current_post( $post_id );

		add_filter(
			'byline_feed_authors',
			static function () {
				return array(
					(object) array(
						'id'           => 'atom-one',
						'display_name' => 'Atom One',
						'description'  => '',
						'url'          => '',
						'avatar_url'   => '',
						'user_id'      => 1,
						'role'         => 'staff',
						'is_guest'     => false,
						'profiles'     => array(),
						'now_url'      => '',
						'uses_url'     => '',
						'fediverse'    => '',
						'ai_consent'   => '',
					),
					(object) array(
						'id'           => 'atom-two',
						'display_name' => 'Atom Two',
						'description'  => '',
						'url'          => '',
						'avatar_url'   => '',
						'user_id'      => 2,
						'role'         => 'guest',
						'is_guest'     => true,
						'profiles'     => array(),
						'now_url'      => '',
						'uses_url'     => '',
						'fediverse'    => '',
						'ai_consent'   => '',
					),
				);
			}
		);

		$feed = $this->capture_output(
			static function () {
				output_entry();
			}
		);

		$this->assertSame( 2, substr_count( $feed, '<byline:author ref="' ) );
		$this->assertStringContainsString( '<byline:author ref="atom-one"/>', $feed );
		$this->assertStringContainsString( '<byline:author ref="atom-two"/>', $feed );
	}

	public function test_contributors_omit_empty_optional_fields(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$this->set_feed_posts( array( $post_id ) );

		add_filter(
			'byline_feed_authors',
			static function () {
				return array(
					(object) array(
						'id'           => 'minimal-atom-author',
						'display_name' => 'Minimal Atom Author',
						'description'  => '',
						'url'          => '',
						'avatar_url'   => '',
						'user_id'      => 0,
						'role'         => 'contributor',
						'is_guest'     => false,
						'profiles'     => array(),
						'now_url'      => '',
						'uses_url'     => '',
						'fediverse'    => '',
						'ai_consent'   => '',
					),
				);
			}
		);

		$feed = $this->capture_output(
			static function () {
				output_contributors();
			}
		);

		$this->assertStringNotContainsString( '<byline:context>', $feed );
		$this->assertStringNotContainsString( '<byline:url>', $feed );
		$this->assertStringNotContainsString( '<byline:avatar>', $feed );
	}

	public function test_atom_output_is_well_formed_xml(): void {
		$user_id = self::factory()->user->create(
			array(
				'display_name' => 'Atom XML Author',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
			)
		);

		$this->set_feed_posts( array( $post_id ) );
		$this->set_current_post( $post_id );

		$contributors = $this->capture_output(
			static function () {
				output_contributors();
			}
		);

		$entry = $this->capture_output(
			static function () {
				output_entry();
			}
		);

		$feed = '<feed xmlns:byline="https://bylinespec.org/1.0">' . $contributors . '<entry>' . $entry . '</entry></feed>';
		$xml  = simplexml_load_string( $feed );

		$this->assertNotFalse( $xml, 'Atom output must be well-formed XML.' );
	}

	public function test_person_xml_filter_applies_to_atom_contributors(): void {
		$user_id = self::factory()->user->create(
			array(
				'display_name' => 'Filtered Atom Author',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
			)
		);

		$this->set_feed_posts( array( $post_id ) );

		add_filter(
			'byline_feed_person_xml',
			static function ( string $xml ): string {
				return $xml . "\t\t\t<byline:test>yes</byline:test>\n";
			}
		);

		$feed = $this->capture_output(
			static function () {
				output_contributors();
			}
		);

		$this->assertStringContainsString( '<byline:test>yes</byline:test>', $feed );
	}

	public function test_item_xml_filter_applies_to_atom_entries(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$this->set_current_post( $post_id );

		add_filter(
			'byline_feed_item_xml',
			static function ( string $xml ): string {
				return $xml . "\t\t<byline:test-entry>yes</byline:test-entry>\n";
			}
		);

		$feed = $this->capture_output(
			static function () {
				output_entry();
			}
		);

		$this->assertStringContainsString( '<byline:test-entry>yes</byline:test-entry>', $feed );
	}
}
