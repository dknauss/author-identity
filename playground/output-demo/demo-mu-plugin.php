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
 * Wrap text in a paragraph block.
 *
 * @param string $html Paragraph HTML (already escaped).
 * @return string Block markup.
 */
function byline_feed_playground_paragraph( string $html ): string {
	return "<!-- wp:paragraph -->\n<p>" . $html . "</p>\n<!-- /wp:paragraph -->\n\n";
}

/**
 * Build the body of a demo post: an intro, then a guide to every demo route.
 *
 * Links are built from home_url() and get_feed_link() on each request so they
 * stay inside Playground's scoped site URL.
 *
 * @param string[] $intro Intro paragraphs (already escaped HTML).
 * @return string Block markup.
 */
function byline_feed_playground_demo_content( array $intro ): string {
	$routes = array(
		array(
			home_url( '/?p=1' ),
			'Allowed multi-author post',
			'Two authors, Jane Editor (staff) and Sam Guest (guest). Neither has set an AI-use preference, so no denial signals appear. In the page source, look for two <code>fediverse:creator</code> meta tags and a JSON-LD <code>Article</code> with both authors as <code>Person</code> entries, in byline order.',
		),
		array(
			get_feed_link( 'rss2' ),
			'RSS2 feed',
			'Look for the <code>xmlns:byline</code> namespace, a <code>byline:contributors</code> block describing both authors, and per-item <code>byline:author</code> and <code>byline:role</code> pairs.',
		),
		array(
			get_feed_link( 'atom' ),
			'Atom feed',
			'The same Byline elements in Atom.',
		),
		array(
			get_feed_link( 'json' ),
			'JSON Feed',
			'Look for <code>_byline</code> at feed level (<code>version</code>, <code>contributors</code>) and on each item (<code>author</code>, plus <code>role</code> for single-author items).',
		),
		array(
			home_url( '/?p=101' ),
			'Denied by author consent',
			'Jane allows AI use and Sam denies it, so the post is denied. In the page source, look for <code>noai, noimageai</code> in the robots meta tag.',
		),
		array(
			home_url( '/?p=102' ),
			'Denied by post override',
			'Neither author has denied AI use, but the post itself is set to deny. The page source shows the same robots signal.',
		),
		array(
			home_url( '/ai.txt' ),
			'ai.txt',
			'The site-wide AI policy file. It stays site-level; per-post denials do not change it.',
		),
	);

	$items = '';
	foreach ( $routes as $route ) {
		$items .= sprintf(
			"<!-- wp:list-item -->\n<li><strong><a href=\"%s\">%s</a></strong>: %s</li>\n<!-- /wp:list-item -->\n",
			esc_url( $route[0] ),
			esc_html( $route[1] ),
			$route[2]
		);
	}

	$content = '';
	foreach ( $intro as $paragraph ) {
		$content .= byline_feed_playground_paragraph( $paragraph );
	}

	$content .= "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">See the output</h2>\n<!-- /wp:heading -->\n\n";
	$content .= byline_feed_playground_paragraph( 'Each link opens in this Playground. You can also type a path such as <code>/feed/</code> or <code>/ai.txt</code> into the address bar in the Playground toolbar. Feeds display as raw XML or JSON. Meta tags and JSON-LD are in the page head: right-click the page and choose <strong>View Frame Source</strong> (Chrome, Edge) or <strong>View Source</strong> inside the frame (Firefox), then search for the text named below.' );
	$content .= "<!-- wp:list -->\n<ul class=\"wp-block-list\">\n" . $items . "</ul>\n<!-- /wp:list -->\n\n";
	$content .= byline_feed_playground_paragraph( 'The authors here are demo fixtures injected by a small mu-plugin, so the output is the same on every run. On a real site they come from WordPress users, Co-Authors Plus, PublishPress Authors, or Authorship.' );

	return $content;
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
				'post_content' => byline_feed_playground_demo_content(
					array(
						'This Playground runs the <strong>Byline Feed</strong> plugin. Byline Feed takes the authors of each post, in byline order, and publishes them in feeds, structured data, link-preview meta tags, and AI-consent signals.',
						'This post has two authors. Use the links below to see what Byline Feed outputs for them.',
					)
				),
			)
		);
	}

	$posts = array(
		BYLINE_FEED_PLAYGROUND_AUTHOR_DENY_POST_ID => array(
			'post_title'   => 'Per-Author Rights Demo',
			'post_name'    => 'per-author-rights-demo',
			'post_excerpt' => 'This post is denied by author consent: Jane allows, Sam denies.',
			'post_content' => byline_feed_playground_demo_content(
				array(
					'This post is <strong>denied for AI use by author consent</strong>. Jane Editor allows AI use and Sam Guest denies it. When any author on a post denies, the post is denied.',
					'View this page\'s source to see <code>noai, noimageai</code> in the robots meta tag. The response also carries a <code>TDMRep</code> header, which you can see in your browser\'s developer tools under Network.',
				)
			),
			'post_meta'    => array(),
		),
		BYLINE_FEED_PLAYGROUND_POST_DENY_POST_ID => array(
			'post_title'   => 'Per-Post Rights Demo',
			'post_name'    => 'per-post-rights-demo',
			'post_excerpt' => 'This post is denied by explicit post override.',
			'post_content' => byline_feed_playground_demo_content(
				array(
					'This post is <strong>denied for AI use by a post-level override</strong>. Neither author has denied AI use, but an editor set this post to deny, and a post setting always wins over author preferences.',
					'View this page\'s source to see <code>noai, noimageai</code> in the robots meta tag. The response also carries a <code>TDMRep</code> header, which you can see in your browser\'s developer tools under Network.',
				)
			),
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
