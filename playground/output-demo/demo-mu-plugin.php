<?php
/**
 * Plugin Name: Byline Feed Playground Demo
 * Description: Deterministic author fixtures for the Byline Feed output-demo blueprint.
 */

defined( 'ABSPATH' ) || exit;

const BYLINE_FEED_PLAYGROUND_ALLOWED_POST_ID     = 1;
const BYLINE_FEED_PLAYGROUND_AUTHOR_DENY_POST_ID = 101;
const BYLINE_FEED_PLAYGROUND_POST_DENY_POST_ID   = 102;

/**
 * Return the deterministic author set for a demo post.
 *
 * @param WP_Post $post Post object.
 * @return array<int, object>
 */
function byline_feed_playground_demo_authors( WP_Post $post ): array {
	$jane_consent = '';
	$sam_consent  = '';

	if ( BYLINE_FEED_PLAYGROUND_AUTHOR_DENY_POST_ID === (int) $post->ID ) {
		$jane_consent = 'allow';
		$sam_consent  = 'deny';
	}

	return array(
		(object) array(
			'id'           => 'jane-editor',
			'display_name' => 'Jane Editor',
			'description'  => 'Investigative editor covering publishing systems and attribution.',
			'url'          => 'https://example.org/authors/jane-editor',
			'avatar_url'   => 'https://secure.gravatar.com/avatar/11111111111111111111111111111111?s=96&d=identicon&r=g',
			'user_id'      => 0,
			'role'         => 'staff',
			'is_guest'     => false,
			'profiles'     => array(
				array(
					'href' => 'https://example.org/authors/jane-editor',
					'rel'  => 'author',
				),
				array(
					'href' => 'https://mastodon.example/@janeeditor',
					'rel'  => 'me',
				),
			),
			'now_url'      => 'https://example.org/now',
			'uses_url'     => 'https://example.org/uses',
			'fediverse'    => '@janeeditor@mastodon.example',
			'ap_actor_url' => 'https://mastodon.example/users/janeeditor',
			'ai_consent'   => $jane_consent,
		),
		(object) array(
			'id'           => 'sam-guest',
			'display_name' => 'Sam Guest',
			'description'  => 'Guest contributor focused on AI policy and publishing rights.',
			'url'          => '',
			'avatar_url'   => 'https://secure.gravatar.com/avatar/22222222222222222222222222222222?s=96&d=identicon&r=g',
			'user_id'      => 0,
			'role'         => 'guest',
			'is_guest'     => true,
			'profiles'     => array(
				array(
					'href' => 'https://social.example/@samguest',
					'rel'  => 'me',
				),
			),
			'now_url'      => '',
			'uses_url'     => '',
			'fediverse'    => '@samguest@social.example',
			'ap_actor_url' => '',
			'ai_consent'   => $sam_consent,
		),
	);
}

/**
 * Ensure deterministic demo posts exist.
 */
function byline_feed_playground_seed_demo_content(): void {
	if ( ! function_exists( 'wp_insert_post' ) ) {
		return;
	}

	$allowed_post = get_post( BYLINE_FEED_PLAYGROUND_ALLOWED_POST_ID );
	if ( $allowed_post instanceof WP_Post && 'post' === $allowed_post->post_type ) {
		wp_update_post(
			array(
				'ID'           => BYLINE_FEED_PLAYGROUND_ALLOWED_POST_ID,
				'post_title'   => 'Allowed Multi-Author Demo',
				'post_name'    => 'allowed-multi-author-demo',
				'post_status'  => 'publish',
				'post_excerpt' => 'Inspect this singular page for fediverse:creator and JSON-LD output, then inspect the feeds for multi-author Byline output.',
				'post_content' => "This is the primary allowed multi-author Playground demo post.\n\nInspect /?p=1 for fediverse:creator meta tags and JSON-LD Article + Person output.\nInspect /feed/, /feed/atom/, and /feed/json/ for multi-author feed output.",
			)
		);
	}

	$posts = array(
		BYLINE_FEED_PLAYGROUND_AUTHOR_DENY_POST_ID => array(
			'post_title'   => 'Per-Author Rights Demo',
			'post_name'    => 'per-author-rights-demo',
			'post_excerpt' => 'This post is denied by author consent: Jane allows, Sam denies.',
			'post_content' => "Inspect /?p=101 for denied-page rights signaling.\n\nExpected: robots meta noai/noimageai, TDMRep header, and ai.txt remains site-level.",
			'post_meta'    => array(),
		),
		BYLINE_FEED_PLAYGROUND_POST_DENY_POST_ID => array(
			'post_title'   => 'Per-Post Rights Demo',
			'post_name'    => 'per-post-rights-demo',
			'post_excerpt' => 'This post is denied by explicit post override.',
			'post_content' => "Inspect /?p=102 for denied-page rights signaling driven by a post-level override.\n\nExpected: robots meta noai/noimageai, TDMRep header, and ai.txt remains site-level.",
			'post_meta'    => array(
				'_byline_ai_consent' => 'deny',
			),
		),
	);

	foreach ( $posts as $post_id => $post_data ) {
		$existing = get_post( $post_id );

		$args = array(
			'import_id'     => $post_id,
			'post_type'     => 'post',
			'post_status'   => 'publish',
			'post_author'   => 1,
			'post_title'    => $post_data['post_title'],
			'post_name'     => $post_data['post_name'],
			'post_excerpt'  => $post_data['post_excerpt'],
			'post_content'  => $post_data['post_content'],
			'comment_status'=> 'closed',
			'ping_status'   => 'closed',
		);

		if ( $existing instanceof WP_Post ) {
			$args['ID'] = $post_id;
			unset( $args['import_id'] );
		}

		$result = wp_insert_post( $args, true );

		if ( is_wp_error( $result ) ) {
			continue;
		}

		delete_post_meta( $post_id, '_byline_ai_consent' );
		foreach ( $post_data['post_meta'] as $meta_key => $meta_value ) {
			update_post_meta( $post_id, $meta_key, $meta_value );
		}
	}
}
add_action( 'init', 'byline_feed_playground_seed_demo_content', 20 );

add_filter(
	'byline_feed_authors',
	static function ( array $authors, WP_Post $post ): array {
		if ( 'post' !== $post->post_type ) {
			return $authors;
		}

		return byline_feed_playground_demo_authors( $post );
	},
	10,
	2
);
