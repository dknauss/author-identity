<?php
/**
 * Tests for fediverse handle sources and for stepping aside for other plugins' tags.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Tests;

use Byline_Feed\Adapter_CAP;
use Byline_Feed\Adapter_PPA;
use ReflectionClass;
use WP_UnitTestCase;
use function Byline_Feed\add_cap_guest_author_fediverse_field;
use function Byline_Feed\add_ppa_author_fediverse_field;
use function Byline_Feed\get_byline_feed_fediverse_for_user;
use function Byline_Feed\get_byline_feed_tsf_fediverse_for_user;
use function Byline_Feed\render_author_meta_fields;
use function Byline_Feed\save_author_meta_fields;
use function Byline_Feed\Fediverse\filter_activitypub_opengraph_metadata;
use function Byline_Feed\Fediverse\filter_tsf_meta_render_data;

class Test_Fediverse_Sources extends WP_UnitTestCase {

	public function tear_down(): void {
		remove_all_filters( 'byline_feed_derived_fediverse_handle' );
		remove_all_filters( 'byline_feed_authors' );
		remove_all_filters( 'byline_feed_author_mode' );
		wp_set_current_user( 0 );
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Pretend another source on the site holds a handle for this user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $handle  Derived handle.
	 */
	private function derive( int $user_id, string $handle ): void {
		add_filter(
			'byline_feed_derived_fediverse_handle',
			static function ( $derived, $filtered_user_id ) use ( $user_id, $handle ) {
				return $filtered_user_id === $user_id ? $handle : $derived;
			},
			10,
			2
		);
	}

	// ────────────────────────────────────────────────────────────
	// Source resolution for linked users
	// ────────────────────────────────────────────────────────────

	public function test_typed_handle_wins_over_derived_handle(): void {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'byline_feed_fediverse', '@typed@example.social' );
		$this->derive( $user_id, '@local@example.org' );

		$this->assertSame( '@typed@example.social', get_byline_feed_fediverse_for_user( $user_id ) );
	}

	public function test_derived_handle_fills_in_when_nothing_is_typed(): void {
		$user_id = self::factory()->user->create();
		$this->derive( $user_id, 'local@example.org' );

		$this->assertSame( '@local@example.org', get_byline_feed_fediverse_for_user( $user_id ) );
	}

	public function test_manual_source_never_falls_back_to_derived_handle(): void {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'byline_feed_fediverse_source', 'manual' );
		$this->derive( $user_id, '@local@example.org' );

		$this->assertSame( '', get_byline_feed_fediverse_for_user( $user_id ) );
	}

	public function test_none_source_credits_nobody_even_with_typed_handle(): void {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'byline_feed_fediverse', '@typed@example.social' );
		update_user_meta( $user_id, 'byline_feed_fediverse_source', 'none' );
		$this->derive( $user_id, '@local@example.org' );

		$this->assertSame( '', get_byline_feed_fediverse_for_user( $user_id ) );
	}

	public function test_unknown_source_value_is_treated_as_auto(): void {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'byline_feed_fediverse_source', 'bogus' );
		$this->derive( $user_id, '@local@example.org' );

		$this->assertSame( '@local@example.org', get_byline_feed_fediverse_for_user( $user_id ) );
	}

	public function test_invalid_derived_handle_is_dropped(): void {
		$user_id = self::factory()->user->create();
		$this->derive( $user_id, 'https://example.org/@local' );

		$this->assertSame( '', get_byline_feed_fediverse_for_user( $user_id ) );
	}

	// ────────────────────────────────────────────────────────────
	// The SEO Framework field
	// ────────────────────────────────────────────────────────────

	public function test_tsf_fediverse_field_is_read_when_tsf_is_active(): void {
		if ( ! defined( 'THE_SEO_FRAMEWORK_USER_OPTIONS' ) ) {
			// Same key TSF itself defines; unit runs have no TSF loaded.
			define( 'THE_SEO_FRAMEWORK_USER_OPTIONS', 'autodescription-user-settings' );
		}

		$user_id = self::factory()->user->create();
		update_user_meta(
			$user_id,
			constant( 'THE_SEO_FRAMEWORK_USER_OPTIONS' ),
			array(
				'fediverse_page'     => '@tsf@example.social',
				'fediverse_page_url' => 'https://example.social/@tsf',
			)
		);

		$this->assertSame( '@tsf@example.social', get_byline_feed_tsf_fediverse_for_user( $user_id ) );
		$this->assertSame( '@tsf@example.social', get_byline_feed_fediverse_for_user( $user_id ) );

		update_user_meta( $user_id, 'byline_feed_fediverse', '@typed@example.social' );

		$this->assertSame( '@typed@example.social', get_byline_feed_fediverse_for_user( $user_id ) );
	}

	// ────────────────────────────────────────────────────────────
	// Profile screen
	// ────────────────────────────────────────────────────────────

	public function test_profile_shows_source_choice_and_found_handle(): void {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'byline_feed_fediverse_source', 'manual' );
		$this->derive( $user_id, '@local@example.org' );

		ob_start();
		render_author_meta_fields( get_userdata( $user_id ) );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="byline_feed_fediverse_source"', $html );
		$this->assertMatchesRegularExpression( '/<option value="manual"\s+selected/', $html );
		$this->assertStringContainsString( '@local@example.org', $html );
		// The typed-handle field stays empty: a found handle is not copied into it.
		$this->assertMatchesRegularExpression( '/id="byline-feed-fediverse" type="text" value=""/', $html );
	}

	public function test_profile_save_stores_and_clears_source(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$_POST = array(
			'byline_feed_author_meta_nonce' => wp_create_nonce( 'byline_feed_author_meta' ),
			'byline_feed_fediverse'         => '',
			'byline_feed_fediverse_source'  => 'none',
		);
		save_author_meta_fields( $admin_id );

		$this->assertSame( 'none', get_user_meta( $admin_id, 'byline_feed_fediverse_source', true ) );

		$_POST['byline_feed_fediverse_source'] = 'auto';
		save_author_meta_fields( $admin_id );

		$this->assertFalse( metadata_exists( 'user', $admin_id, 'byline_feed_fediverse_source' ) );

		$_POST['byline_feed_fediverse_source'] = 'manual';
		save_author_meta_fields( $admin_id );
		unset( $_POST['byline_feed_fediverse_source'] );
		save_author_meta_fields( $admin_id );

		$this->assertSame( 'manual', get_user_meta( $admin_id, 'byline_feed_fediverse_source', true ) );
	}

	// ────────────────────────────────────────────────────────────
	// Guest authors
	// ────────────────────────────────────────────────────────────

	public function test_cap_guest_author_fields_gain_fediverse_handle(): void {
		$fields = add_cap_guest_author_fediverse_field( array(), 'all' );

		$this->assertSame( 'byline_feed_fediverse', $fields[0]['key'] );
		$this->assertSame( 'contact-info', $fields[0]['group'] );
		$this->assertSame( '@guest@example.social', call_user_func( $fields[0]['sanitize_function'], 'guest@example.social' ) );

		$this->assertSame( array(), add_cap_guest_author_fediverse_field( array(), array( 'name' ) ) );
		$this->assertCount( 1, add_cap_guest_author_fediverse_field( $fields, 'all' ) );
	}

	public function test_cap_guest_author_handle_comes_from_guest_record(): void {
		$coauthor = (object) array(
			'type'                  => 'guest-author',
			'ID'                    => 9876,
			'user_nicename'         => 'guest-writer',
			'display_name'          => 'Guest Writer',
			'byline_feed_fediverse' => 'guest@example.social',
		);

		$author = $this->normalize_with( new Adapter_CAP(), $coauthor );

		$this->assertTrue( $author->is_guest );
		$this->assertSame( '@guest@example.social', $author->fediverse );
		$this->assertSame( '', $author->ap_actor_url );
	}

	public function test_ppa_guest_author_field_is_offered_only_without_linked_user(): void {
		$fields = add_ppa_author_fediverse_field( array(), (object) array( 'user_id' => 0 ) );

		$this->assertArrayHasKey( 'byline_feed_fediverse', $fields );
		$this->assertSame( '@guest@example.social', call_user_func( $fields['byline_feed_fediverse']['sanitize'], 'guest@example.social' ) );
		$this->assertSame( array(), add_ppa_author_fediverse_field( array(), (object) array( 'user_id' => 5 ) ) );
	}

	public function test_ppa_guest_author_handle_comes_from_term_meta(): void {
		$term = wp_insert_term( 'PPA Fedi Guest', 'category', array( 'slug' => 'ppa-fedi-guest' ) );
		$this->assertIsArray( $term );
		update_term_meta( (int) $term['term_id'], 'byline_feed_fediverse', '@ppaguest@example.social' );

		$author = $this->normalize_with(
			new Adapter_PPA(),
			(object) array(
				'slug'         => 'ppa-fedi-guest',
				'display_name' => 'PPA Fedi Guest',
				'user_id'      => 0,
				'term_id'      => (int) $term['term_id'],
				'is_guest'     => true,
			)
		);

		$this->assertSame( '@ppaguest@example.social', $author->fediverse );
	}

	// ────────────────────────────────────────────────────────────
	// Stepping aside for TSF and ActivityPub
	// ────────────────────────────────────────────────────────────

	public function test_tsf_creator_tag_gives_way_and_rel_me_stays(): void {
		$this->go_to( get_permalink( $this->post_with_handles( array( '@a@example.social', '@b@example.social' ) ) ) );

		$tags = filter_tsf_meta_render_data(
			array(
				'fediverse:creator' => array( 'attributes' => array( 'name' => 'fediverse:creator', 'content' => '@site@example.social' ) ),
				'rel:me:0'          => array( 'tag' => 'link' ),
			)
		);

		$this->assertArrayNotHasKey( 'fediverse:creator', $tags );
		$this->assertArrayHasKey( 'rel:me:0', $tags );
	}

	public function test_tsf_creator_tag_stays_when_byline_feed_has_no_handle(): void {
		$this->go_to( get_permalink( $this->post_with_handles( array( '' ) ) ) );

		$tags = filter_tsf_meta_render_data( array( 'fediverse:creator' => array() ) );

		$this->assertArrayHasKey( 'fediverse:creator', $tags );
	}

	public function test_tsf_creator_tag_is_removed_in_organization_mode(): void {
		$this->go_to( get_permalink( $this->post_with_handles( array( '' ) ) ) );
		add_filter( 'byline_feed_author_mode', static fn() => 'organization' );

		$tags = filter_tsf_meta_render_data( array( 'fediverse:creator' => array() ) );

		$this->assertArrayNotHasKey( 'fediverse:creator', $tags );
	}

	public function test_activitypub_creator_gives_way_to_byline_feed_handles(): void {
		$this->go_to( get_permalink( $this->post_with_handles( array( '@a@example.social' ) ) ) );

		$metadata = filter_activitypub_opengraph_metadata(
			array(
				'og:type'           => 'article',
				'fediverse:creator' => 'author@example.org',
			)
		);

		$this->assertArrayNotHasKey( 'fediverse:creator', $metadata );
		$this->assertSame( 'article', $metadata['og:type'] );
	}

	public function test_activitypub_creator_stays_when_byline_feed_has_no_handle(): void {
		$this->go_to( get_permalink( $this->post_with_handles( array( '' ) ) ) );

		$metadata = filter_activitypub_opengraph_metadata( array( 'fediverse:creator' => 'author@example.org' ) );

		$this->assertSame( 'author@example.org', $metadata['fediverse:creator'] );
	}

	public function test_foreign_creator_tags_are_untouched_off_singular_views(): void {
		$this->post_with_handles( array( '@a@example.social' ) );
		$this->go_to( home_url( '/' ) );

		$this->assertArrayHasKey( 'fediverse:creator', filter_tsf_meta_render_data( array( 'fediverse:creator' => array() ) ) );
		$this->assertArrayHasKey( 'fediverse:creator', filter_activitypub_opengraph_metadata( array( 'fediverse:creator' => 'x@example.org' ) ) );
	}

	public function test_step_aside_filters_are_registered(): void {
		$this->assertNotFalse( has_filter( 'the_seo_framework_meta_render_data', 'Byline_Feed\\Fediverse\\filter_tsf_meta_render_data' ) );
		$this->assertNotFalse( has_filter( 'opengraph_metadata', 'Byline_Feed\\Fediverse\\filter_activitypub_opengraph_metadata' ) );
	}

	// ────────────────────────────────────────────────────────────
	// Helpers
	// ────────────────────────────────────────────────────────────

	/**
	 * Create a published post whose byline holds one author per handle.
	 *
	 * @param string[] $handles Handles, empty string for an author without one.
	 * @return int Post ID.
	 */
	private function post_with_handles( array $handles ): int {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		add_filter(
			'byline_feed_authors',
			static function ( $authors, $post ) use ( $post_id, $handles ) {
				if ( $post->ID !== $post_id ) {
					return $authors;
				}

				$list = array();

				foreach ( $handles as $i => $handle ) {
					$list[] = (object) array(
						'id'           => 'author-' . $i,
						'display_name' => 'Author ' . $i,
						'fediverse'    => $handle,
					);
				}

				return $list;
			},
			10,
			2
		);

		return $post_id;
	}

	/**
	 * Call an adapter's private normalize() method.
	 *
	 * @param object $adapter Adapter instance.
	 * @param object $raw     Upstream author object.
	 * @return object
	 */
	private function normalize_with( object $adapter, object $raw ): object {
		$method = ( new ReflectionClass( $adapter ) )->getMethod( 'normalize' );
		$method->setAccessible( true );

		return $method->invoke( $adapter, $raw );
	}
}
