<?php
/**
 * Perspective meta field registration and classic editor metabox.
 *
 * Registers the _byline_perspective post meta with REST API support
 * and provides a classic editor metabox fallback.
 *
 * The block editor sidebar panel is handled by src/perspective-panel.tsx.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed\Perspective;

defined( 'ABSPATH' ) || exit;

/**
 * Allowed perspective values.
 *
 * @return string[]
 */
function get_allowed_values(): array {
	return array(
		'personal',
		'reporting',
		'analysis',
		'official',
		'sponsored',
		'satire',
		'review',
		'announcement',
		'tutorial',
		'curation',
		'fiction',
		'interview',
	);
}

/**
 * Register hooks for the perspective meta field.
 */
function register_hooks(): void {
	add_action( 'init', __NAMESPACE__ . '\\register_meta' );
	add_action( 'add_meta_boxes', __NAMESPACE__ . '\\register_metabox' );
	add_action( 'save_post', __NAMESPACE__ . '\\save_metabox' );
	add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_editor_assets' );
}

/**
 * Register the _byline_perspective post meta.
 */
function register_meta(): void {
	register_post_meta(
		'',
		'_byline_perspective',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_perspective',
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}

/**
 * Sanitize a perspective value.
 *
 * @param mixed $value The value to sanitize.
 * @return string Sanitized value or empty string.
 */
function sanitize_perspective( $value ): string {
	if ( in_array( $value, get_allowed_values(), true ) ) {
		return $value;
	}
	return '';
}

/**
 * Register the classic editor metabox.
 */
function register_metabox(): void {
	$post_types = get_post_types( array( 'public' => true ) );

	foreach ( $post_types as $post_type ) {
		add_meta_box(
			'byline-feed-perspective',
			__( 'Content Perspective', 'byline-feed' ),
			__NAMESPACE__ . '\\render_metabox',
			$post_type,
			'side',
			'default'
		);
	}
}

/**
 * Render the classic editor metabox.
 *
 * @param \WP_Post $post The current post.
 */
function render_metabox( \WP_Post $post ): void {
	$current = get_post_meta( $post->ID, '_byline_perspective', true );
	$values  = get_allowed_values();

	wp_nonce_field( 'byline_feed_perspective', 'byline_feed_perspective_nonce' );

	echo '<select name="byline_feed_perspective" id="byline-feed-perspective">';
	echo '<option value="">' . esc_html__( '— None —', 'byline-feed' ) . '</option>';

	foreach ( $values as $value ) {
		printf(
			'<option value="%s"%s>%s</option>',
			esc_attr( $value ),
			selected( $current, $value, false ),
			esc_html( ucfirst( $value ) )
		);
	}

	echo '</select>';
	echo '<p class="description">' . esc_html__( 'The editorial perspective or intent of this content.', 'byline-feed' ) . '</p>';
}

/**
 * Save the perspective meta from the classic editor.
 *
 * @param int $post_id The post ID.
 */
function save_metabox( int $post_id ): void {
	if ( ! isset( $_POST['byline_feed_perspective_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['byline_feed_perspective_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'byline_feed_perspective' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$value = '';

	if ( isset( $_POST['byline_feed_perspective'] ) ) {
		$raw_value = sanitize_text_field( wp_unslash( $_POST['byline_feed_perspective'] ) );
		$value     = sanitize_perspective( $raw_value );
	}

	if ( '' === $value ) {
		delete_post_meta( $post_id, '_byline_perspective' );
	} else {
		update_post_meta( $post_id, '_byline_perspective', $value );
	}
}

/**
 * Enqueue the block editor sidebar panel script.
 */
function enqueue_editor_assets(): void {
	$asset_file = BYLINE_FEED_PLUGIN_DIR . 'build/perspective-panel.tsx.asset.php';

	if ( ! file_exists( $asset_file ) ) {
		return;
	}

	$asset = require $asset_file;

	wp_enqueue_script(
		'byline-feed-perspective-panel',
		BYLINE_FEED_PLUGIN_URL . 'build/perspective-panel.tsx.js',
		$asset['dependencies'] ?? array(),
		$asset['version'] ?? BYLINE_FEED_VERSION,
		true
	);
}
