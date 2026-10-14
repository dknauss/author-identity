<?php
/**
 * Author mode post meta, classic editor metabox, and block editor panel.
 *
 * Stores an editor's explicit choice of who a post is attributed to in
 * `_byline_author_mode`: 'person' (its authors) or 'organization' (the
 * site). An empty or missing value means the editor has not chosen, and
 * the `byline_feed_author_mode` filter decides (default 'person'). That is
 * where another plugin, such as one that detects first-person writing, can
 * supply an automatic answer without overriding a choice made by hand.
 *
 * The block editor panel is src/author-mode-panel.tsx.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Author_Mode;

defined( 'ABSPATH' ) || exit;

/**
 * Post meta key for the editor's explicit choice.
 */
const META_KEY = '_byline_author_mode';

/**
 * Values an editor can store. Empty means "not chosen".
 *
 * @return string[]
 */
function get_allowed_values(): array {
	return array( 'person', 'organization' );
}

/**
 * Register hooks for the author mode field.
 */
function register_hooks(): void {
	add_action( 'init', __NAMESPACE__ . '\\register_meta' );
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\\register_metabox' );
	add_action( 'save_post', __NAMESPACE__ . '\\save_metabox' );
	add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_editor_assets' );
}

/**
 * Register the author mode post meta.
 */
function register_meta(): void {
	register_post_meta(
		'',
		META_KEY,
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_author_mode',
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}

/**
 * Sanitize a stored author mode.
 *
 * @param mixed $value The value to sanitize.
 * @return string 'person', 'organization', or '' (not chosen).
 */
function sanitize_author_mode( $value ): string {
	return in_array( $value, get_allowed_values(), true ) ? $value : '';
}

/**
 * Return the editor's explicit choice for a post, if any.
 *
 * @param \WP_Post $post The post.
 * @return string 'person', 'organization', or '' when not chosen.
 */
function get_stored_author_mode( \WP_Post $post ): string {
	return sanitize_author_mode( get_post_meta( $post->ID, META_KEY, true ) );
}

/**
 * Register the classic editor metabox.
 */
function register_metabox(): void {
	foreach ( get_post_types( array( 'public' => true ) ) as $post_type ) {
		add_meta_box(
			'byline-feed-author-mode',
			__( 'Attribution', 'byline-feed' ),
			__NAMESPACE__ . '\\render_metabox',
			$post_type,
			'side',
			'default'
		);
	}
}

/**
 * Labels for the choices, keyed by stored value.
 *
 * @return array<string, string>
 */
function get_choice_labels(): array {
	return array(
		''             => __( 'Automatic', 'byline-feed' ),
		'person'       => __( 'The authors', 'byline-feed' ),
		'organization' => __( 'The site (no personal byline)', 'byline-feed' ),
	);
}

/**
 * Render the classic editor metabox.
 *
 * @param \WP_Post $post The current post.
 */
function render_metabox( \WP_Post $post ): void {
	$current = get_stored_author_mode( $post );

	wp_nonce_field( 'byline_feed_author_mode', 'byline_feed_author_mode_nonce' );

	echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Attribute this post to', 'byline-feed' ) . '</legend>';

	foreach ( get_choice_labels() as $value => $label ) {
		printf(
			'<p><label><input type="radio" name="byline_feed_author_mode" value="%s"%s /> %s</label></p>',
			esc_attr( $value ),
			checked( $current, $value, false ),
			esc_html( $label )
		);
	}

	echo '</fieldset>';
	echo '<p class="description">' . esc_html__( 'Who structured data and link previews credit with this post. Automatic lets the site or another plugin decide; it credits the authors unless told otherwise.', 'byline-feed' ) . '</p>';
}

/**
 * Save the author mode from the classic editor.
 *
 * @param int $post_id The post ID.
 */
function save_metabox( int $post_id ): void {
	if ( ! isset( $_POST['byline_feed_author_mode_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['byline_feed_author_mode_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'byline_feed_author_mode' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$value = isset( $_POST['byline_feed_author_mode'] )
		? sanitize_author_mode( sanitize_text_field( wp_unslash( $_POST['byline_feed_author_mode'] ) ) )
		: '';

	if ( '' === $value ) {
		delete_post_meta( $post_id, META_KEY );
	} else {
		update_post_meta( $post_id, META_KEY, $value );
	}
}

/**
 * Enqueue the block editor sidebar panel script.
 */
function enqueue_editor_assets(): void {
	$asset_file = BYLINE_FEED_PLUGIN_DIR . 'build/author-mode-panel.tsx.asset.php';

	if ( ! file_exists( $asset_file ) ) {
		return;
	}

	$asset = require $asset_file;

	wp_enqueue_script(
		'byline-feed-author-mode-panel',
		BYLINE_FEED_PLUGIN_URL . 'build/author-mode-panel.tsx.js',
		$asset['dependencies'] ?? array(),
		$asset['version'] ?? BYLINE_FEED_VERSION,
		true
	);
}
