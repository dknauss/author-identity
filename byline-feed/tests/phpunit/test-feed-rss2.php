<?php
/**
 * Tests for RSS2 Byline feed output.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Tests;

use WP_UnitTestCase;
use function Byline_Feed\Feed_RSS2\output_contributors;
use function Byline_Feed\Feed_RSS2\output_item;
use function Byline_Feed\Feed_RSS2\output_namespace;

class Test_Feed_RSS2 extends WP_UnitTestCase {

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
	 * Render the real core RSS2 template body with headers bypassed.
	 *
	 * WordPress's feed template calls header() before output, which conflicts
	 * with the PHPUnit bootstrap output. Strip that single line so the rest of
	 * the real template can render unchanged for additive-behavior assertions.
	 *
	 * @return string
	 */
	private function render_rss2_template_body(): string {
		$template_path = ABSPATH . WPINC . '/feed-rss2.php';
		$template      = file_get_contents( $template_path );

		$this->assertIsString( $template );

		$template = preg_replace( "/^header\\(.*?\\);\\n/m", '', $template, 1 );

		ob_start();
		eval( '?>' . $template );
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
	 * Set current global post used by output_item().
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
		$user_id = self::factory()->user->create( array(
			'display_name' => 'Test Author',
		) );

		$post_id = self::factory()->post->create( array(
			'post_author' => $user_id,
			'post_status' => 'publish',
		) );

		$this->set_feed_posts( array( $post_id ) );

		$feed = $this->capture_output(
			static function () {
				output_contributors();
			}
		);

		$this->assertStringContainsString( '<byline:contributors>', $feed );
		$this->assertStringContainsString( '</byline:contributors>', $feed );
		$this->assertStringContainsString( '<byline:person', $feed );
		$this->assertStringContainsString( '<byline:name>Test Author</byline:name>', $feed );
	}

	public function test_item_author_ref_present(): void {
		$user_id = self::factory()->user->create( array(
			'user_nicename' => 'test-author',
		) );

		$post_id = self::factory()->post->create( array(
			'post_author' => $user_id,
			'post_status' => 'publish',
		) );

		$this->set_current_post( $post_id );

		$feed = $this->capture_output(
			static function () {
				output_item();
			}
		);

		$this->assertStringContainsString( '<byline:author ref="test-author"/>', $feed );
	}

	public function test_perspective_in_feed_when_set(): void {
		$user_id = self::factory()->user->create();
		$post_id = self::factory()->post->create( array(
			'post_author' => $user_id,
			'post_status' => 'publish',
		) );

		update_post_meta( $post_id, '_byline_perspective', 'analysis' );

		$this->set_current_post( $post_id );

		$feed = $this->capture_output(
			static function () {
				output_item();
			}
		);

		$this->assertStringContainsString(
			'<byline:perspective>analysis</byline:perspective>',
			$feed
		);
	}

	public function test_no_perspective_when_unset(): void {
		$user_id = self::factory()->user->create();
		$post_id = self::factory()->post->create( array(
			'post_author' => $user_id,
			'post_status' => 'publish',
		) );

		$this->set_current_post( $post_id );

		$feed = $this->capture_output(
			static function () {
				output_item();
			}
		);

		$this->assertStringNotContainsString( '<byline:perspective>', $feed );
	}

	public function test_feed_is_well_formed_xml(): void {
		$user_id = self::factory()->user->create( array(
			'display_name' => 'XML Test Author',
		) );

		$post_id = self::factory()->post->create( array(
			'post_author' => $user_id,
			'post_status' => 'publish',
		) );

		$this->set_feed_posts( array( $post_id ) );
		$this->set_current_post( $post_id );

		$contributors = $this->capture_output(
			static function () {
				output_contributors();
			}
		);

		$item = $this->capture_output(
			static function () {
				output_item();
			}
		);

		$feed = '<rss xmlns:byline="https://bylinespec.org/1.0"><channel>' . $contributors . '<item>' . $item . '</item></channel></rss>';
		$xml  = simplexml_load_string( $feed );

		$this->assertNotFalse( $xml, 'Feed output must be well-formed XML.' );
	}

	public function test_contributors_are_deduplicated_across_posts(): void {
		$post_ids = array(
			self::factory()->post->create(
				array(
					'post_status' => 'publish',
				)
			),
			self::factory()->post->create(
				array(
					'post_status' => 'publish',
				)
			),
		);

		add_filter(
			'byline_feed_authors',
			static function ( $authors, $post ) use ( $post_ids ) {
				if ( ! in_array( $post->ID, $post_ids, true ) ) {
					return $authors;
				}

				return array(
					(object) array(
						'id'           => 'shared-author',
						'display_name' => 'Shared Author',
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
				);
			},
			10,
			2
		);

		$this->set_feed_posts( $post_ids );

		$feed = $this->capture_output(
			static function () {
				output_contributors();
			}
		);

		$this->assertSame( 1, substr_count( $feed, '<byline:person id="shared-author">' ) );
	}

	public function test_item_outputs_multiple_author_refs_when_filtered_authors_present(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$this->set_current_post( $post_id );

		add_filter(
			'byline_feed_authors',
			static function () {
				return array(
					(object) array(
						'id'           => 'author-one',
						'display_name' => 'Author One',
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
						'id'           => 'author-two',
						'display_name' => 'Author Two',
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
				output_item();
			}
		);

		$this->assertSame( 2, substr_count( $feed, '<byline:author ref="' ) );
		$this->assertStringContainsString( '<byline:author ref="author-one"/>', $feed );
		$this->assertStringContainsString( '<byline:author ref="author-two"/>', $feed );
	}

	public function test_person_omits_empty_optional_fields(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$this->set_feed_posts( array( $post_id ) );

		add_filter(
			'byline_feed_authors',
			static function () {
				return array(
					(object) array(
						'id'           => 'minimal-author',
						'display_name' => 'Minimal Author',
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
		$this->assertStringNotContainsString( '<byline:profile ', $feed );
		$this->assertStringNotContainsString( '<byline:now>', $feed );
		$this->assertStringNotContainsString( '<byline:uses>', $feed );
	}

	public function test_person_outputs_profiles_now_and_uses_when_present(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$this->set_feed_posts( array( $post_id ) );

		add_filter(
			'byline_feed_authors',
			static function () {
				return array(
					(object) array(
						'id'           => 'linked-author',
						'display_name' => 'Linked Author',
						'description'  => '',
						'url'          => '',
						'avatar_url'   => '',
						'user_id'      => 1,
						'role'         => 'staff',
						'is_guest'     => false,
						'profiles'     => array(
							array(
								'rel'  => 'me',
								'href' => 'https://example.com/@linked-author',
							),
						),
						'now_url'      => 'https://example.com/now/',
						'uses_url'     => 'https://example.com/uses/',
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

		$this->assertStringContainsString( '<byline:profile href="https://example.com/@linked-author" rel="me"/>', $feed );
		$this->assertStringContainsString( '<byline:now>https://example.com/now/</byline:now>', $feed );
		$this->assertStringContainsString( '<byline:uses>https://example.com/uses/</byline:uses>', $feed );
	}

	public function test_full_rss2_template_preserves_dc_creator_alongside_byline_output(): void {
		$user_id = self::factory()->user->create(
			array(
				'display_name'  => 'Template Author',
				'user_nicename' => 'template-author',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
				'post_title'  => 'Template Test Post',
			)
		);

		$this->go_to( '/?feed=rss2&p=' . $post_id );

		$feed = $this->render_rss2_template_body();

		$this->assertStringContainsString( '<dc:creator><![CDATA[Template Author]]></dc:creator>', $feed );
		$this->assertStringContainsString( '<byline:author ref="template-author"/>', $feed );
		$this->assertStringContainsString( '<byline:contributors>', $feed );
	}

	public function test_empty_author_array_omits_byline_output_without_breaking_xml(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => 0,
				'post_status' => 'publish',
				'post_title'  => 'Authorless RSS2 Post',
			)
		);

		add_filter(
			'byline_feed_authors',
			static function (): array {
				return array();
			}
		);

		$this->set_feed_posts( array( $post_id ) );
		$this->set_current_post( $post_id );

		$contributors = $this->capture_output(
			static function () {
				output_contributors();
			}
		);
		$item         = $this->capture_output(
			static function () {
				output_item();
			}
		);

		$this->assertSame( '', $contributors );
		$this->assertSame( '', $item );

		$xml = simplexml_load_string( '<rss xmlns:byline="https://bylinespec.org/1.0"><channel><item></item></channel></rss>' );
		$this->assertNotFalse( $xml );
	}

	public function test_special_characters_in_author_fields_produce_well_formed_rss2_xml(): void {
		$post_id      = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$display_name = 'A <B> & "C" 😄 漢字';
		$description  = 'Context with <strong>markup</strong> & symbols 😄 漢字';

		$this->set_feed_posts( array( $post_id ) );
		$this->set_current_post( $post_id );

		add_filter(
			'byline_feed_authors',
			static function () use ( $display_name, $description ): array {
				return array(
					(object) array(
						'id'           => 'special-rss2-author',
						'display_name' => $display_name,
						'description'  => $description,
						'url'          => 'https://example.com/authors/special?x=1&y=2',
						'avatar_url'   => '',
						'user_id'      => 0,
						'role'         => 'staff',
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

		$contributors = $this->capture_output(
			static function () {
				output_contributors();
			}
		);
		$item         = $this->capture_output(
			static function () {
				output_item();
			}
		);
		$xml          = simplexml_load_string( '<rss xmlns:byline="https://bylinespec.org/1.0"><channel>' . $contributors . '<item>' . $item . '</item></channel></rss>' );

		$this->assertNotFalse( $xml, 'RSS2 output must remain well-formed with special characters.' );

		$namespaces    = $xml->getNamespaces( true );
		$channel       = $xml->channel;
		$contributorsn = $channel->children( $namespaces['byline'] )->contributors;
		$person        = $contributorsn->children( $namespaces['byline'] )->person[0];

		$this->assertSame( $display_name, (string) $person->children( $namespaces['byline'] )->name );
		$this->assertStringContainsString( 'markup & symbols 😄 漢字', (string) $person->children( $namespaces['byline'] )->context );
	}
}
