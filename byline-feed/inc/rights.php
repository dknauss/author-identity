<?php
/**
 * Rights and AI-consent output.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Rights;

use WP;
use WP_Post;
use function Byline_Feed\byline_feed_get_authors;

defined( 'ABSPATH' ) || exit;

/**
 * Register hooks for rights output and consent controls.
 */
function register_hooks(): void {
	add_action( 'init', __NAMESPACE__ . '\\register_consent_meta' );
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\\register_metabox' );
	add_action( 'save_post', __NAMESPACE__ . '\\save_metabox' );
	add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_editor_assets' );
	add_action( 'wp_head', __NAMESPACE__ . '\\render_robots_meta' );
	add_filter( 'wp_headers', __NAMESPACE__ . '\\filter_wp_headers', 10, 1 );
	add_action( 'template_redirect', __NAMESPACE__ . '\\maybe_render_ai_txt' );
}

/**
 * Register user and post meta used for AI-consent resolution.
 */
function register_consent_meta(): void {
	register_meta(
		'user',
		'byline_feed_ai_consent',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_ai_consent',
			'auth_callback'     => 'Byline_Feed\\can_edit_byline_feed_user_meta',
		)
	);

	register_post_meta(
		'',
		'_byline_ai_consent',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_ai_consent',
			'auth_callback'     => __NAMESPACE__ . '\\can_edit_ai_consent_post_meta',
		)
	);
}

/**
 * Determine whether the current user can edit AI-consent post meta.
 *
 * @param mixed ...$args WordPress auth-callback arguments.
 * @return bool
 */
function can_edit_ai_consent_post_meta( ...$args ): bool {
	$object_id = isset( $args[2] ) ? (int) $args[2] : 0;

	if ( $object_id > 0 ) {
		return current_user_can( 'edit_post', $object_id );
	}

	return current_user_can( 'edit_posts' );
}

/**
 * Sanitize an AI-consent value.
 *
 * @param mixed $value Candidate value.
 * @return string
 */
function sanitize_ai_consent( $value ): string {
	return in_array( $value, array( 'allow', 'deny' ), true ) ? (string) $value : '';
}

/**
 * Resolve the effective AI-consent value for a post.
 *
 * Most restrictive author preference wins when there is no post override.
 *
 * @param WP_Post $post Post object.
 * @return string
 */
function resolve_ai_consent( WP_Post $post ): string {
	$post_consent = sanitize_ai_consent( get_post_meta( $post->ID, '_byline_ai_consent', true ) );

	if ( '' !== $post_consent ) {
		$consent = $post_consent;
	} else {
		$authors  = byline_feed_get_authors( $post );
		$consents = array();

		foreach ( $authors as $author ) {
			if ( isset( $author->ai_consent ) && is_string( $author->ai_consent ) ) {
				$consent = sanitize_ai_consent( $author->ai_consent );

				if ( '' !== $consent ) {
					$consents[] = $consent;
				}
			}
		}

		if ( in_array( 'deny', $consents, true ) ) {
			$consent = 'deny';
		} elseif ( in_array( 'allow', $consents, true ) ) {
			$consent = 'allow';
		} else {
			$consent = '';
		}
	}

	/**
	 * Filters the resolved AI-consent value for a post.
	 *
	 * @param string  $consent Resolved consent: `allow`, `deny`, or empty string.
	 * @param WP_Post $post    Post object.
	 */
	$consent = apply_filters( 'byline_feed_ai_consent', $consent, $post );

	return sanitize_ai_consent( $consent );
}

/**
 * Register the classic editor metabox for per-post AI-consent overrides.
 */
function register_metabox(): void {
	$post_types = get_post_types( array( 'public' => true ) );

	foreach ( $post_types as $post_type ) {
		add_meta_box(
			'byline-feed-ai-consent',
			__( 'AI Training Consent', 'byline-feed' ),
			__NAMESPACE__ . '\\render_metabox',
			$post_type,
			'side',
			'default'
		);
	}
}

/**
 * Render the AI-consent metabox.
 *
 * @param WP_Post $post Post object.
 */
function render_metabox( WP_Post $post ): void {
	$current = sanitize_ai_consent( get_post_meta( $post->ID, '_byline_ai_consent', true ) );

	wp_nonce_field( 'byline_feed_ai_consent', 'byline_feed_ai_consent_nonce' );
	?>
	<select name="byline_feed_ai_consent" id="byline-feed-ai-consent">
		<option value="" <?php selected( '', $current ); ?>><?php esc_html_e( 'Inherit from authors', 'byline-feed' ); ?></option>
		<option value="allow" <?php selected( 'allow', $current ); ?>><?php esc_html_e( 'Allow AI training', 'byline-feed' ); ?></option>
		<option value="deny" <?php selected( 'deny', $current ); ?>><?php esc_html_e( 'Deny AI training', 'byline-feed' ); ?></option>
	</select>
	<p class="description"><?php esc_html_e( 'Controls machine-readable AI training signals for this post. When unset, the most restrictive linked-author preference wins.', 'byline-feed' ); ?></p>
	<?php
}

/**
 * Save the classic editor metabox value.
 *
 * @param int $post_id Post ID.
 */
function save_metabox( int $post_id ): void {
	if ( ! isset( $_POST['byline_feed_ai_consent_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['byline_feed_ai_consent_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'byline_feed_ai_consent' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$value = '';

	if ( isset( $_POST['byline_feed_ai_consent'] ) ) {
		$value = sanitize_ai_consent( sanitize_text_field( wp_unslash( $_POST['byline_feed_ai_consent'] ) ) );
	}

	if ( '' === $value ) {
		delete_post_meta( $post_id, '_byline_ai_consent' );
	} else {
		update_post_meta( $post_id, '_byline_ai_consent', $value );
	}
}

/**
 * Render robots meta output for denied posts.
 */
function render_robots_meta(): void {
	if ( ! is_singular() ) {
		return;
	}

	$post = get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$content = get_robots_meta_content( $post );

	if ( '' === $content ) {
		return;
	}

	printf(
		"<meta name=\"robots\" content=\"%s\" />\n",
		esc_attr( $content )
	);
}

/**
 * Return the robots meta content for a post.
 *
 * @param WP_Post $post Post object.
 * @return string
 */
function get_robots_meta_content( WP_Post $post ): string {
	$content = '';

	if ( 'deny' === resolve_ai_consent( $post ) ) {
		$content = 'noai, noimageai';
	}

	/**
	 * Filters the robots meta content for a post.
	 *
	 * @param string  $content Meta content or empty string.
	 * @param WP_Post $post    Post object.
	 */
	$content = apply_filters( 'byline_feed_ai_robots_content', $content, $post );

	return is_string( $content ) ? trim( $content ) : '';
}

/**
 * Filter response headers for singular denied posts.
 *
 * @param array<string, string> $headers Existing headers.
 * @return array<string, string>
 */
function filter_wp_headers( array $headers ): array {
	if ( ! is_singular() ) {
		return $headers;
	}

	$post = get_queried_object();

	if ( ! $post instanceof WP_Post ) {
		return $headers;
	}

	if ( 'deny' !== resolve_ai_consent( $post ) ) {
		return $headers;
	}

	$policy_url = get_policy_url( $post );

	if ( '' !== $policy_url ) {
		$headers['TDMRep'] = $policy_url;
	}

	/**
	 * Filters the final headers for Byline AI-consent output.
	 *
	 * @param array<string, string> $headers Updated headers.
	 * @param WP_Post               $post    Post object.
	 */
	$headers = apply_filters( 'byline_feed_ai_headers', $headers, $post );

	return is_array( $headers ) ? $headers : array();
}

/**
 * Return the canonical policy URL used in AI-consent headers and ai.txt.
 *
 * @param WP_Post|null $post Post context when available.
 * @return string
 */
function get_policy_url( ?WP_Post $post = null ): string {
	$url = home_url( '/ai.txt' );

	/**
	 * Filters the AI policy URL.
	 *
	 * @param string       $url  Policy URL.
	 * @param WP_Post|null $post Post context when available.
	 */
	$url = apply_filters( 'byline_feed_ai_policy_url', $url, $post );

	return is_string( $url ) ? esc_url_raw( $url ) : '';
}

/**
 * Return the ai.txt body.
 *
 * @return string
 */
function get_ai_txt_content(): string {
	$content = implode(
		"\n",
		array(
			'# ai.txt generated by Byline Feed',
			'# Per-page AI-consent signals are expressed with robots metadata and TDMRep headers.',
			'User-agent: *',
			'Allow: /',
			'Policy: ' . get_policy_url(),
			'',
		)
	);

	/**
	 * Filters the ai.txt response body.
	 *
	 * @param string $content Generated ai.txt content.
	 */
	$content = apply_filters( 'byline_feed_ai_txt_content', $content );

	return is_string( $content ) ? $content : '';
}

/**
 * Render ai.txt directly for matching frontend requests.
 */
function maybe_render_ai_txt(): void {
	if ( ! is_ai_txt_request() ) {
		return;
	}

	if ( ! headers_sent() ) {
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=' . get_option( 'blog_charset' ) );
	}

	echo wp_kses_post( get_ai_txt_content() );

	/**
	 * Filters whether ai.txt rendering should terminate the request.
	 *
	 * @param bool $should_exit Whether to exit after rendering.
	 */
	$should_exit = apply_filters( 'byline_feed_ai_txt_should_exit', true );

	if ( $should_exit ) {
		exit;
	}
}

/**
 * Determine whether the current request is for ai.txt.
 *
 * @return bool
 */
function is_ai_txt_request(): bool {
	global $wp;

	if ( $wp instanceof WP && 'ai.txt' === trim( (string) $wp->request, '/' ) ) {
		return true;
	}

	if ( isset( $_SERVER['REQUEST_URI'] ) ) {
		$path = wp_parse_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH );

		if ( is_string( $path ) && '/ai.txt' === untrailingslashit( $path ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Enqueue the block editor sidebar panel script for AI consent.
 */
function enqueue_editor_assets(): void {
	$asset_file = BYLINE_FEED_PLUGIN_DIR . 'build/ai-consent-panel.tsx.asset.php';

	if ( ! file_exists( $asset_file ) ) {
		return;
	}

	$asset = require_once $asset_file;

	wp_enqueue_script(
		'byline-feed-ai-consent-panel',
		BYLINE_FEED_PLUGIN_URL . 'build/ai-consent-panel.tsx.js',
		$asset['dependencies'] ?? array(),
		$asset['version'] ?? BYLINE_FEED_VERSION,
		true
	);
}
