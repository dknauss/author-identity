<?php
/**
 * Public API functions and hook registration.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed;

defined( 'ABSPATH' ) || exit;

/**
 * Cached adapter instance.
 *
 * @var Adapter|null
 */
$_byline_feed_adapter = null;

/**
 * Bootstrap the plugin.
 *
 * Detects the active multi-author plugin, loads the corresponding adapter,
 * and registers all output-layer hooks.
 *
 * Runs on `plugins_loaded`.
 */
function bootstrap(): void {
	// Detect and cache the adapter.
	byline_feed_get_adapter();

	// Register feed output hooks.
	Feed_RSS2\register_hooks();
	Feed_Atom\register_hooks();
	Feed_JSON\register_hooks();
	Fediverse\register_hooks();
	Schema\register_hooks();
	Rights\register_hooks();
	register_author_meta_hooks();

	// Register perspective meta field.
	Perspective\register_hooks();
}

/**
 * Detect and return the active adapter.
 *
 * Priority order:
 * 1. PublishPress Authors — publishpress-specific API/class present.
 * 2. Human Made Authorship — Authorship\get_authors()
 * 3. Co-Authors Plus       — function_exists( 'get_coauthors' )
 * 4. Core WordPress        — always available (fallback)
 *
 * @return Adapter
 */
function byline_feed_get_adapter(): Adapter {
	global $_byline_feed_adapter;

	if ( null !== $_byline_feed_adapter ) {
		return $_byline_feed_adapter;
	}

	if (
		function_exists( 'publishpress_authors_get_post_authors' )
		|| function_exists( 'get_post_authors' )
		|| class_exists( 'MultipleAuthors\\Classes\\Objects\\Author' )
	) {
		$adapter = new Adapter_PPA();
	} elseif ( function_exists( 'Authorship\\get_authors' ) ) {
		$adapter = new Adapter_Authorship();
	} elseif ( function_exists( 'get_coauthors' ) ) {
		$adapter = new Adapter_CAP();
	} else {
		$adapter = new Adapter_Core();
	}

	/**
	 * Filters the adapter instance.
	 *
	 * @param Adapter $adapter The auto-detected adapter.
	 */
	$_byline_feed_adapter = apply_filters( 'byline_feed_adapter', $adapter );

	return $_byline_feed_adapter;
}

/**
 * Returns the normalized author array for a given post.
 *
 * This is the primary public API function. All output layers
 * should call this rather than the adapter directly.
 *
 * @param \WP_Post $post The post.
 * @return object[] Ordered array of normalized author objects.
 */
function byline_feed_get_authors( \WP_Post $post ): array {
	$adapter = byline_feed_get_adapter();
	$authors = $adapter->get_authors( $post );

	/**
	 * Filters the normalized author array after adapter resolution.
	 *
	 * Filtered authors are re-validated against the normalized contract.
	 * Invalid entries are dropped and logged via log_invalid_author_contract().
	 *
	 * @param object[] $authors Normalized author objects.
	 * @param \WP_Post $post    The post.
	 */
	$authors = apply_filters( 'byline_feed_authors', $authors, $post );

	return validate_author_objects( $authors, $post );
}

/**
 * Returns the perspective value for a given post.
 *
 * @param \WP_Post $post The post.
 * @return string Perspective value or empty string.
 */
function byline_feed_get_perspective( \WP_Post $post ): string {
	$perspective = get_post_meta( $post->ID, '_byline_perspective', true );

	/**
	 * Filters the perspective value.
	 *
	 * @param string   $perspective The perspective value.
	 * @param \WP_Post $post        The post.
	 */
	$perspective = apply_filters( 'byline_feed_perspective', $perspective, $post );

	if ( ! in_array( $perspective, Perspective\get_allowed_values(), true ) ) {
		return '';
	}

	return $perspective;
}

/**
 * Derives a Byline role string from a WordPress user's capabilities.
 *
 * @param \WP_User|null $user The WordPress user, or null.
 * @return string Byline role: 'staff', 'contributor', etc.
 */
function get_byline_role_from_user( ?\WP_User $user ): string {
	if ( ! $user ) {
		return 'contributor';
	}

	if ( user_can( $user, 'edit_others_posts' ) ) {
		return 'staff';
	}

	return 'contributor';
}

/**
 * Validate and normalize the author array returned by adapters/filters.
 *
 * Invalid entries are dropped before output layers consume them. Optional
 * fields are normalized to zero-values so downstream renderers can rely on
 * a consistent object shape.
 *
 * @param mixed    $authors Candidate author array.
 * @param \WP_Post $post    Post being resolved.
 * @return object[]
 */
function validate_author_objects( $authors, \WP_Post $post ): array {
	if ( ! is_array( $authors ) ) {
		log_invalid_author_contract( 'Author data must be an array of objects.', $post );
		return array();
	}

	$validated = array();

	foreach ( $authors as $author ) {
		$normalized = normalize_author_object( $author, $post );

		if ( null === $normalized ) {
			continue;
		}

		$validated[] = $normalized;
	}

	return $validated;
}

/**
 * Normalize a single author object to the expected contract.
 *
 * @param mixed    $author Candidate author object.
 * @param \WP_Post $post   Post being resolved.
 * @return object|null
 */
function normalize_author_object( $author, \WP_Post $post ): ?object {
	if ( ! is_object( $author ) ) {
		log_invalid_author_contract( 'Author entries must be objects.', $post );
		return null;
	}

	if ( ! isset( $author->id ) || ! is_string( $author->id ) || '' === $author->id ) {
		log_invalid_author_contract( 'Author object is missing a valid string id.', $post );
		return null;
	}

	if ( ! isset( $author->display_name ) || ! is_string( $author->display_name ) || '' === $author->display_name ) {
		log_invalid_author_contract( 'Author object is missing a valid display_name.', $post );
		return null;
	}

	$normalized = (object) array(
		'id'           => $author->id,
		'display_name' => $author->display_name,
		'description'  => isset( $author->description ) && is_string( $author->description ) ? $author->description : '',
		'url'          => isset( $author->url ) && is_string( $author->url ) ? $author->url : '',
		'avatar_url'   => isset( $author->avatar_url ) && is_string( $author->avatar_url ) ? $author->avatar_url : '',
		'user_id'      => isset( $author->user_id ) ? (int) $author->user_id : 0,
		'role'         => isset( $author->role ) && is_string( $author->role ) ? $author->role : '',
		'is_guest'     => ! empty( $author->is_guest ),
		'profiles'     => isset( $author->profiles ) ? normalize_byline_profiles( $author->profiles ) : array(),
		'now_url'      => isset( $author->now_url ) && is_string( $author->now_url ) ? $author->now_url : '',
		'uses_url'     => isset( $author->uses_url ) && is_string( $author->uses_url ) ? $author->uses_url : '',
		'fediverse'    => isset( $author->fediverse ) && is_string( $author->fediverse ) ? $author->fediverse : '',
		// Derived field: only populate when ActivityPub identity is confidently resolvable.
		'ap_actor_url' => isset( $author->ap_actor_url ) && is_string( $author->ap_actor_url ) ? $author->ap_actor_url : '',
		'ai_consent'   => isset( $author->ai_consent ) && is_string( $author->ai_consent ) ? $author->ai_consent : '',
	);

	return $normalized;
}

/**
 * Emit a developer-facing contract validation event.
 *
 * @param string   $message Validation message.
 * @param \WP_Post $post    Post being resolved.
 */
function log_invalid_author_contract( string $message, \WP_Post $post ): void {
	$full_message = sprintf(
		/* translators: 1: validation message, 2: post ID */
		__( '%1$s Post ID: %2$d', 'byline-feed' ),
		$message,
		(int) $post->ID
	);

	/**
	 * Fires when an adapter/filter returns invalid author contract data.
	 *
	 * @param string   $message The validation message including post context.
	 * @param \WP_Post $post    The post being resolved.
	 */
	do_action( 'byline_feed_invalid_author_contract', $full_message, $post );
}
