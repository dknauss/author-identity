<?php
/**
 * Tests for rights and AI-consent output.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Tests;

use WP_Post;
use WP_UnitTestCase;
use function Byline_Feed\Rights\filter_wp_headers;
use function Byline_Feed\Rights\clear_audit_log_entries;
use function Byline_Feed\Rights\get_audit_log_entries;
use function Byline_Feed\Rights\get_ai_txt_content;
use function Byline_Feed\Rights\get_policy_url;
use function Byline_Feed\Rights\maybe_render_ai_txt;
use function Byline_Feed\Rights\register_consent_meta;
use function Byline_Feed\Rights\render_audit_log_page;
use function Byline_Feed\Rights\render_metabox;
use function Byline_Feed\Rights\render_robots_meta;
use function Byline_Feed\Rights\resolve_ai_consent;
use function Byline_Feed\Rights\save_metabox;
use function Byline_Feed\Feed_RSS2\output_item as rss2_output_item;
use function Byline_Feed\Feed_Atom\output_entry as atom_output_entry;
use function Byline_Feed\Feed_JSON\build_author_byline_extension;

class Test_Rights extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( 'byline_feed_authors' );
		remove_all_filters( 'byline_feed_ai_consent' );
		remove_all_filters( 'byline_feed_ai_robots_content' );
		remove_all_filters( 'byline_feed_ai_policy_url' );
		remove_all_filters( 'byline_feed_ai_headers' );
		remove_all_filters( 'byline_feed_ai_consent_audit_entry' );
		remove_all_filters( 'byline_feed_ai_txt_content' );
		remove_all_filters( 'byline_feed_ai_txt_should_exit' );
		clear_audit_log_entries();
		wp_set_current_user( 0 );
		$_POST                  = array();
		$_SERVER['REQUEST_URI'] = '';
		parent::tear_down();
	}

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

	public function test_register_meta_exposes_user_and_post_consent_fields_in_rest(): void {
		register_consent_meta();

		$user_meta = get_registered_meta_keys( 'user' );
		$post_meta = get_registered_meta_keys( 'post', '' );

		$this->assertArrayHasKey( 'byline_feed_ai_consent', $user_meta );
		$this->assertTrue( $user_meta['byline_feed_ai_consent']['show_in_rest'] );
		$this->assertArrayHasKey( '_byline_ai_consent', $post_meta );
		$this->assertTrue( $post_meta['_byline_ai_consent']['show_in_rest'] );
	}

	public function test_resolve_ai_consent_prefers_deny_over_allow_across_authors(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$post    = get_post( $post_id );

		add_filter(
			'byline_feed_authors',
			static function ( array $authors, WP_Post $filtered_post ) use ( $post_id ): array {
				if ( $filtered_post->ID !== $post_id ) {
					return $authors;
				}

				return array(
					(object) array(
						'id'           => 'allow-author',
						'display_name' => 'Allow Author',
						'ai_consent'   => 'allow',
					),
					(object) array(
						'id'           => 'deny-author',
						'display_name' => 'Deny Author',
						'ai_consent'   => 'deny',
					),
				);
			},
			10,
			2
		);

		$this->assertSame( 'deny', resolve_ai_consent( $post ) );
	}

	public function test_post_override_beats_author_preferences(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		update_post_meta( $post_id, '_byline_ai_consent', 'allow' );
		$post = get_post( $post_id );

		add_filter(
			'byline_feed_authors',
			static function ( array $authors, WP_Post $filtered_post ) use ( $post_id ): array {
				if ( $filtered_post->ID !== $post_id ) {
					return $authors;
				}

				return array(
					(object) array(
						'id'           => 'deny-author',
						'display_name' => 'Deny Author',
						'ai_consent'   => 'deny',
					),
				);
			},
			10,
			2
		);

		$this->assertSame( 'allow', resolve_ai_consent( $post ) );
	}

	public function test_ai_consent_filter_can_override_resolved_value(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$post    = get_post( $post_id );

		add_filter(
			'byline_feed_ai_consent',
			static function (): string {
				return 'deny';
			}
		);

		$this->assertSame( 'deny', resolve_ai_consent( $post ) );
	}

	public function test_denied_singular_post_outputs_robots_meta_tag(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Denied AI Post',
			)
		);

		add_filter(
			'byline_feed_authors',
			static function ( array $authors, WP_Post $filtered_post ) use ( $post_id ): array {
				if ( $filtered_post->ID !== $post_id ) {
					return $authors;
				}

				return array(
					(object) array(
						'id'           => 'deny-author',
						'display_name' => 'Deny Author',
						'ai_consent'   => 'deny',
					),
				);
			},
			10,
			2
		);

		$this->go_to( get_permalink( $post_id ) );

		$output = $this->capture_output( static function () {
			render_robots_meta();
		} );

		$this->assertStringContainsString( 'name="robots"', $output );
		$this->assertStringContainsString( 'content="noai, noimageai"', $output );
	}

	public function test_allow_or_unset_consent_outputs_no_robots_meta(): void {
		$allow_post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$unset_post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		add_filter(
			'byline_feed_authors',
			static function ( array $authors, WP_Post $filtered_post ) use ( $allow_post_id, $unset_post_id ): array {
				if ( $filtered_post->ID === $allow_post_id ) {
					return array(
						(object) array(
							'id'           => 'allow-author',
							'display_name' => 'Allow Author',
							'ai_consent'   => 'allow',
						),
					);
				}

				if ( $filtered_post->ID === $unset_post_id ) {
					return array(
						(object) array(
							'id'           => 'unset-author',
							'display_name' => 'Unset Author',
							'ai_consent'   => '',
						),
					);
				}

				return $authors;
			},
			10,
			2
		);

		$this->go_to( get_permalink( $allow_post_id ) );
		$this->assertSame(
			'',
			$this->capture_output( static function () {
				render_robots_meta();
			} )
		);

		$this->go_to( get_permalink( $unset_post_id ) );
		$this->assertSame(
			'',
			$this->capture_output( static function () {
				render_robots_meta();
			} )
		);
	}

	public function test_denied_singular_post_adds_tdmrep_header(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		add_filter(
			'byline_feed_authors',
			static function ( array $authors, WP_Post $filtered_post ) use ( $post_id ): array {
				if ( $filtered_post->ID !== $post_id ) {
					return $authors;
				}

				return array(
					(object) array(
						'id'           => 'deny-author',
						'display_name' => 'Deny Author',
						'ai_consent'   => 'deny',
					),
				);
			},
			10,
			2
		);

		add_filter(
			'byline_feed_ai_policy_url',
			static function (): string {
				return 'https://example.com/ai-policy';
			}
		);

		$this->go_to( get_permalink( $post_id ) );

		$headers = filter_wp_headers( array() );

		$this->assertSame( 'https://example.com/ai-policy', $headers['TDMRep'] );
	}

	public function test_ai_txt_default_content_is_generated(): void {
		$content = get_ai_txt_content();

		$this->assertStringContainsString( '# ai.txt generated by Byline Feed', $content );
		$this->assertStringContainsString( 'User-agent: *', $content );
		$this->assertStringContainsString( 'Policy: ' . home_url( '/ai.txt' ), $content );
	}

	public function test_ai_txt_request_renders_content_without_exiting_when_filtered(): void {
		add_filter(
			'byline_feed_ai_txt_should_exit',
			static function (): bool {
				return false;
			}
		);

		$_SERVER['REQUEST_URI'] = '/ai.txt';
		$this->go_to( home_url( '/ai.txt' ) );

		$output = $this->capture_output( static function () {
			maybe_render_ai_txt();
		} );

		$this->assertStringContainsString( 'User-agent: *', $output );
	}

	public function test_render_metabox_outputs_expected_control(): void {
		$post_id = self::factory()->post->create();
		$post    = get_post( $post_id );

		$output = $this->capture_output(
			static function () use ( $post ) {
				render_metabox( $post );
			}
		);

		$this->assertStringContainsString( 'name="byline_feed_ai_consent"', $output );
		$this->assertStringContainsString( 'Inherit from authors', $output );
	}

	public function test_save_metabox_stores_and_deletes_override(): void {
		$post_id  = self::factory()->post->create();
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$_POST = array(
			'byline_feed_ai_consent_nonce' => wp_create_nonce( 'byline_feed_ai_consent' ),
			'byline_feed_ai_consent'       => 'deny',
		);

		save_metabox( $post_id );

		$this->assertSame( 'deny', get_post_meta( $post_id, '_byline_ai_consent', true ) );

		$_POST = array(
			'byline_feed_ai_consent_nonce' => wp_create_nonce( 'byline_feed_ai_consent' ),
			'byline_feed_ai_consent'       => '',
		);

		save_metabox( $post_id );

		$this->assertSame( '', get_post_meta( $post_id, '_byline_ai_consent', true ) );
	}

	public function test_user_consent_changes_are_logged_for_audit(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$user_id  = self::factory()->user->create();

		wp_set_current_user( $admin_id );

		update_user_meta( $user_id, 'byline_feed_ai_consent', 'deny' );
		delete_user_meta( $user_id, 'byline_feed_ai_consent' );

		$entries = get_audit_log_entries();

		$this->assertCount( 2, $entries );
		$this->assertSame( 'user', $entries[0]['target_type'] );
		$this->assertSame( $user_id, $entries[0]['target_id'] );
		$this->assertSame( 'deny', $entries[0]['old_value'] );
		$this->assertSame( '', $entries[0]['new_value'] );
		$this->assertSame( $admin_id, $entries[0]['actor_user_id'] );
		$this->assertSame( '', $entries[1]['old_value'] );
		$this->assertSame( 'deny', $entries[1]['new_value'] );
		$this->assertNotSame( '', $entries[0]['timestamp_gmt'] );
	}

	public function test_post_consent_changes_are_logged_for_audit(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$post_id  = self::factory()->post->create();

		wp_set_current_user( $admin_id );

		update_post_meta( $post_id, '_byline_ai_consent', 'allow' );
		update_post_meta( $post_id, '_byline_ai_consent', 'deny' );
		delete_post_meta( $post_id, '_byline_ai_consent' );

		$entries = get_audit_log_entries();

		$this->assertCount( 3, $entries );
		$this->assertSame( 'post', $entries[0]['target_type'] );
		$this->assertSame( $post_id, $entries[0]['target_id'] );
		$this->assertSame( 'deny', $entries[0]['old_value'] );
		$this->assertSame( '', $entries[0]['new_value'] );
		$this->assertSame( 'allow', $entries[1]['old_value'] );
		$this->assertSame( 'deny', $entries[1]['new_value'] );
		$this->assertSame( '', $entries[2]['old_value'] );
		$this->assertSame( 'allow', $entries[2]['new_value'] );
	}

	public function test_audit_log_page_renders_entries_for_admins(): void {
		$admin_id = self::factory()->user->create(
			array(
				'role'         => 'administrator',
				'display_name' => 'Audit Admin',
			)
		);
		$user_id  = self::factory()->user->create(
			array(
				'display_name' => 'Logged Author',
			)
		);

		wp_set_current_user( $admin_id );
		update_user_meta( $user_id, 'byline_feed_ai_consent', 'deny' );

		$output = $this->capture_output(
			static function () {
				render_audit_log_page();
			}
		);

		$this->assertStringContainsString( 'AI Consent Audit Log', $output );
		$this->assertStringContainsString( 'Audit Admin', $output );
		$this->assertStringContainsString( 'Logged Author', $output );
		$this->assertStringContainsString( 'deny', $output );
	}

	public function test_rss2_item_includes_rights_element_for_denied_post(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$post    = get_post( $post_id );

		add_filter(
			'byline_feed_authors',
			static function ( array $authors, WP_Post $filtered_post ) use ( $post_id ): array {
				if ( $filtered_post->ID !== $post_id ) {
					return $authors;
				}

				return array(
					(object) array(
						'id'           => 'deny-author',
						'display_name' => 'Deny Author',
						'role'         => 'author',
						'ai_consent'   => 'deny',
					),
				);
			},
			10,
			2
		);

		$this->go_to( get_permalink( $post_id ) );

		// Set global post for RSS2 output_item.
		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		$output = $this->capture_output( static function () {
			rss2_output_item();
		} );

		wp_reset_postdata();

		$this->assertStringContainsString( '<byline:rights consent="deny"', $output );
		$this->assertStringContainsString( 'policy=', $output );
	}

	public function test_rss2_item_omits_rights_element_for_allowed_post(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$post    = get_post( $post_id );

		add_filter(
			'byline_feed_authors',
			static function ( array $authors, WP_Post $filtered_post ) use ( $post_id ): array {
				if ( $filtered_post->ID !== $post_id ) {
					return $authors;
				}

				return array(
					(object) array(
						'id'           => 'allow-author',
						'display_name' => 'Allow Author',
						'role'         => 'author',
						'ai_consent'   => 'allow',
					),
				);
			},
			10,
			2
		);

		$this->go_to( get_permalink( $post_id ) );
		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		$output = $this->capture_output( static function () {
			rss2_output_item();
		} );

		wp_reset_postdata();

		$this->assertStringNotContainsString( '<byline:rights', $output );
	}

	public function test_atom_entry_includes_rights_element_for_denied_post(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$post    = get_post( $post_id );

		add_filter(
			'byline_feed_authors',
			static function ( array $authors, WP_Post $filtered_post ) use ( $post_id ): array {
				if ( $filtered_post->ID !== $post_id ) {
					return $authors;
				}

				return array(
					(object) array(
						'id'           => 'deny-author',
						'display_name' => 'Deny Author',
						'role'         => 'author',
						'ai_consent'   => 'deny',
					),
				);
			},
			10,
			2
		);

		$this->go_to( get_permalink( $post_id ) );
		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		$output = $this->capture_output( static function () {
			atom_output_entry();
		} );

		wp_reset_postdata();

		$this->assertStringContainsString( '<byline:rights consent="deny"', $output );
	}

	public function test_json_feed_item_includes_rights_for_denied_post(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$post    = get_post( $post_id );

		update_post_meta( $post_id, '_byline_ai_consent', 'deny' );

		add_filter(
			'byline_feed_ai_policy_url',
			static function (): string {
				return 'https://example.com/ai-policy';
			}
		);

		$item = \Byline_Feed\Feed_JSON\filter_json_feed_item( array(), $post );

		$this->assertArrayHasKey( '_byline', $item );
		$this->assertArrayHasKey( 'rights', $item['_byline'] );
		$this->assertSame( 'deny', $item['_byline']['rights']['consent'] );
		$this->assertSame( 'https://example.com/ai-policy', $item['_byline']['rights']['policy'] );
	}

	public function test_json_feed_item_omits_rights_for_allowed_post(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$post    = get_post( $post_id );

		update_post_meta( $post_id, '_byline_ai_consent', 'allow' );

		$item = \Byline_Feed\Feed_JSON\filter_json_feed_item( array(), $post );

		if ( isset( $item['_byline'] ) ) {
			$this->assertArrayNotHasKey( 'rights', $item['_byline'] );
		} else {
			$this->assertTrue( true ); // No _byline key at all is also correct.
		}
	}

	public function test_enqueue_editor_assets_registers_script_when_asset_file_exists(): void {
		$asset_file = BYLINE_FEED_PLUGIN_DIR . 'build/ai-consent-panel.tsx.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			$this->markTestSkipped( 'Build assets not present.' );
		}

		\Byline_Feed\Rights\enqueue_editor_assets();

		$this->assertTrue( wp_script_is( 'byline-feed-ai-consent-panel', 'registered' ) );
	}
}
