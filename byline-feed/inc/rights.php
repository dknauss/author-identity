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
 * Request-local cache of previous AI-consent values for audit logging.
 *
 * @var array<string, string>
 */
$_byline_feed_ai_consent_previous = array();

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
	add_action( 'admin_menu', __NAMESPACE__ . '\\register_audit_log_page' );
	add_filter( 'update_user_metadata', __NAMESPACE__ . '\\capture_previous_user_consent_for_update', 10, 5 );
	add_filter( 'delete_user_metadata', __NAMESPACE__ . '\\capture_previous_user_consent_for_delete', 10, 5 );
	add_filter( 'update_post_metadata', __NAMESPACE__ . '\\capture_previous_post_consent_for_update', 10, 5 );
	add_filter( 'delete_post_metadata', __NAMESPACE__ . '\\capture_previous_post_consent_for_delete', 10, 5 );
	add_action( 'added_user_meta', __NAMESPACE__ . '\\maybe_log_added_user_consent', 10, 4 );
	add_action( 'updated_user_meta', __NAMESPACE__ . '\\maybe_log_updated_user_consent', 10, 4 );
	add_action( 'deleted_user_meta', __NAMESPACE__ . '\\maybe_log_deleted_user_consent', 10, 4 );
	add_action( 'added_post_meta', __NAMESPACE__ . '\\maybe_log_added_post_consent', 10, 4 );
	add_action( 'updated_post_meta', __NAMESPACE__ . '\\maybe_log_updated_post_consent', 10, 4 );
	add_action( 'deleted_post_meta', __NAMESPACE__ . '\\maybe_log_deleted_post_consent', 10, 4 );
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
 * Sanitize a feed-level AI-consent summary value.
 *
 * Feed-level consent can summarize multiple items, so it accepts `mixed`
 * in addition to the item-level `allow` / `deny` values.
 *
 * @param mixed $value Candidate value.
 * @return string
 */
function sanitize_feed_ai_consent( $value ): string {
	return in_array( $value, array( 'allow', 'deny', 'mixed' ), true ) ? (string) $value : '';
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
 * Return feed-level rights metadata for a collection of posts.
 *
 * The summary is:
 * - `deny` when every explicit item-level signal is deny.
 * - `allow` when every explicit item-level signal is allow.
 * - `mixed` when the feed contains a combination of deny/allow/unset states.
 *
 * @param array<int, mixed> $posts Candidate posts in the current feed.
 * @return array<string, string>
 */
function get_feed_rights( array $posts ): array {
	$has_deny  = false;
	$has_allow = false;
	$has_unset = false;

	foreach ( $posts as $post ) {
		if ( ! $post instanceof WP_Post ) {
			continue;
		}

		$consent = resolve_ai_consent( $post );

		if ( 'deny' === $consent ) {
			$has_deny = true;
		} elseif ( 'allow' === $consent ) {
			$has_allow = true;
		} else {
			$has_unset = true;
		}
	}

	$consent = '';

	if ( $has_deny && ! $has_allow && ! $has_unset ) {
		$consent = 'deny';
	} elseif ( $has_allow && ! $has_deny && ! $has_unset ) {
		$consent = 'allow';
	} elseif ( $has_deny || $has_allow ) {
		$consent = 'mixed';
	}

	if ( '' === $consent ) {
		return array();
	}

	$rights = array(
		'consent' => $consent,
	);

	$policy_url = get_policy_url();

	if ( '' !== $policy_url ) {
		$rights['policy'] = $policy_url;
	}

	/**
	 * Filters feed-level rights metadata derived from the current feed posts.
	 *
	 * @param array<string, string> $rights Feed-level rights metadata.
	 * @param array<int, mixed>     $posts  Posts considered for the summary.
	 */
	$rights = apply_filters( 'byline_feed_feed_rights', $rights, $posts );

	if ( ! is_array( $rights ) ) {
		return array();
	}

	$normalized_consent = isset( $rights['consent'] ) && is_string( $rights['consent'] )
		? sanitize_feed_ai_consent( $rights['consent'] )
		: '';

	if ( '' === $normalized_consent ) {
		return array();
	}

	$normalized = array(
		'consent' => $normalized_consent,
	);

	if ( isset( $rights['policy'] ) && is_string( $rights['policy'] ) ) {
		$policy = esc_url_raw( $rights['policy'] );

		if ( '' !== $policy ) {
			$normalized['policy'] = $policy;
		}
	}

	return $normalized;
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
	$policy  = get_policy_url( $post );

	wp_nonce_field( 'byline_feed_ai_consent', 'byline_feed_ai_consent_nonce' );
	?>
	<select name="byline_feed_ai_consent" id="byline-feed-ai-consent">
		<option value="" <?php selected( '', $current ); ?>><?php esc_html_e( 'Inherit from authors', 'byline-feed' ); ?></option>
		<option value="allow" <?php selected( 'allow', $current ); ?>><?php esc_html_e( 'Allow AI training', 'byline-feed' ); ?></option>
		<option value="deny" <?php selected( 'deny', $current ); ?>><?php esc_html_e( 'Deny AI training', 'byline-feed' ); ?></option>
	</select>
	<p class="description"><?php esc_html_e( 'Controls advisory machine-readable AI training signals for this post and any feed items generated from it. When unset, the most restrictive linked-author preference wins.', 'byline-feed' ); ?></p>
	<?php if ( '' !== $policy ) : ?>
		<p class="description">
			<?php esc_html_e( 'Current site policy endpoint:', 'byline-feed' ); ?>
			<code><?php echo esc_html( $policy ); ?></code>
		</p>
	<?php endif; ?>
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
 * Register the audit-log screen under Tools.
 */
function register_audit_log_page(): void {
	add_management_page(
		__( 'AI Consent Audit Log', 'byline-feed' ),
		__( 'AI Consent Audit Log', 'byline-feed' ),
		'manage_options',
		'byline-feed-ai-consent-audit-log',
		__NAMESPACE__ . '\\render_audit_log_page'
	);
}

/**
 * Render the admin-only AI-consent audit log.
 */
function render_audit_log_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to view this page.', 'byline-feed' ) );
	}

	$entries = get_audit_log_entries();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'AI Consent Audit Log', 'byline-feed' ); ?></h1>
		<p><?php esc_html_e( 'Recent changes to per-author and per-post AI consent values. Newest entries appear first.', 'byline-feed' ); ?></p>
		<?php if ( empty( $entries ) ) : ?>
			<p><?php esc_html_e( 'No AI consent changes have been recorded yet.', 'byline-feed' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Timestamp (UTC)', 'byline-feed' ); ?></th>
						<th><?php esc_html_e( 'Actor', 'byline-feed' ); ?></th>
						<th><?php esc_html_e( 'Target', 'byline-feed' ); ?></th>
						<th><?php esc_html_e( 'Old value', 'byline-feed' ); ?></th>
						<th><?php esc_html_e( 'New value', 'byline-feed' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $entries as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( $entry['timestamp_gmt'] ); ?></td>
							<td><?php echo esc_html( get_actor_label( (int) $entry['actor_user_id'] ) ); ?></td>
							<td><?php echo esc_html( get_audit_target_label( $entry['target_type'], (int) $entry['target_id'] ) ); ?></td>
							<td><code><?php echo esc_html( format_audit_value( $entry['old_value'] ) ); ?></code></td>
							<td><code><?php echo esc_html( format_audit_value( $entry['new_value'] ) ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
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
 * Return the option name used for AI-consent audit entries.
 *
 * @return string
 */
function get_audit_log_option_name(): string {
	return 'byline_feed_ai_consent_audit_log';
}

/**
 * Return the maximum number of audit entries to retain.
 *
 * @return int
 */
function get_audit_log_limit(): int {
	$limit = (int) apply_filters( 'byline_feed_ai_consent_audit_log_limit', 100 );

	return max( 1, $limit );
}

/**
 * Return normalized audit entries, newest first.
 *
 * @return array<int, array{timestamp_gmt:string,actor_user_id:int,target_type:string,target_id:int,old_value:string,new_value:string}>
 */
function get_audit_log_entries(): array {
	$entries = get_option( get_audit_log_option_name(), array() );

	if ( ! is_array( $entries ) ) {
		return array();
	}

	$normalized = array();

	foreach ( $entries as $entry ) {
		if ( ! is_array( $entry ) ) {
			continue;
		}

		$target_type = isset( $entry['target_type'] ) && is_string( $entry['target_type'] ) ? $entry['target_type'] : '';
		if ( ! in_array( $target_type, array( 'user', 'post' ), true ) ) {
			continue;
		}

		$normalized[] = array(
			'timestamp_gmt' => isset( $entry['timestamp_gmt'] ) && is_string( $entry['timestamp_gmt'] ) ? $entry['timestamp_gmt'] : '',
			'actor_user_id' => isset( $entry['actor_user_id'] ) ? (int) $entry['actor_user_id'] : 0,
			'target_type'   => $target_type,
			'target_id'     => isset( $entry['target_id'] ) ? (int) $entry['target_id'] : 0,
			'old_value'     => isset( $entry['old_value'] ) ? sanitize_ai_consent( $entry['old_value'] ) : '',
			'new_value'     => isset( $entry['new_value'] ) ? sanitize_ai_consent( $entry['new_value'] ) : '',
		);
	}

	return $normalized;
}

/**
 * Clear audit-log state.
 *
 * Primarily used by the automated test suite.
 */
function clear_audit_log_entries(): void {
	global $_byline_feed_ai_consent_previous;

	$_byline_feed_ai_consent_previous = array();
	delete_option( get_audit_log_option_name() );
}

/**
 * Capture the current user-consent value before an update runs.
 *
 * @param mixed  $check     Short-circuit value.
 * @param int    $user_id   User ID.
 * @param string $meta_key  Meta key.
 * @param mixed  $meta_value New value.
 * @param mixed  $prev_value Previous value constraint.
 * @return mixed
 */
function capture_previous_user_consent_for_update( $check, int $user_id, string $meta_key, $meta_value, $prev_value ) {
	unset( $meta_value, $prev_value );

	if ( 'byline_feed_ai_consent' === $meta_key ) {
		stash_previous_meta_value( 'user', $user_id, $meta_key, get_user_meta( $user_id, $meta_key, true ) );
	}

	return $check;
}

/**
 * Capture the current user-consent value before a delete runs.
 *
 * @param mixed  $delete     Short-circuit value.
 * @param int    $user_id    User ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value Meta value constraint.
 * @param bool   $delete_all Whether delete_all was requested.
 * @return mixed
 */
function capture_previous_user_consent_for_delete( $delete, int $user_id, string $meta_key, $meta_value, bool $delete_all ) {
	unset( $meta_value, $delete_all );

	if ( 'byline_feed_ai_consent' === $meta_key ) {
		stash_previous_meta_value( 'user', $user_id, $meta_key, get_user_meta( $user_id, $meta_key, true ) );
	}

	return $delete;
}

/**
 * Capture the current post-consent value before an update runs.
 *
 * @param mixed  $check      Short-circuit value.
 * @param int    $post_id    Post ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value New value.
 * @param mixed  $prev_value Previous value constraint.
 * @return mixed
 */
function capture_previous_post_consent_for_update( $check, int $post_id, string $meta_key, $meta_value, $prev_value ) {
	unset( $meta_value, $prev_value );

	if ( '_byline_ai_consent' === $meta_key ) {
		stash_previous_meta_value( 'post', $post_id, $meta_key, get_post_meta( $post_id, $meta_key, true ) );
	}

	return $check;
}

/**
 * Capture the current post-consent value before a delete runs.
 *
 * @param mixed  $delete     Short-circuit value.
 * @param int    $post_id    Post ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value Meta value constraint.
 * @param bool   $delete_all Whether delete_all was requested.
 * @return mixed
 */
function capture_previous_post_consent_for_delete( $delete, int $post_id, string $meta_key, $meta_value, bool $delete_all ) {
	unset( $meta_value, $delete_all );

	if ( '_byline_ai_consent' === $meta_key ) {
		stash_previous_meta_value( 'post', $post_id, $meta_key, get_post_meta( $post_id, $meta_key, true ) );
	}

	return $delete;
}

/**
 * Log an added user-consent value.
 *
 * @param int    $meta_id   Meta ID.
 * @param int    $user_id   User ID.
 * @param string $meta_key  Meta key.
 * @param mixed  $meta_value Meta value.
 */
function maybe_log_added_user_consent( int $meta_id, int $user_id, string $meta_key, $meta_value ): void {
	record_ai_consent_audit_event( 'user', $user_id, '', $meta_value, $meta_key );
}

/**
 * Log an updated user-consent value.
 *
 * @param int    $meta_id    Meta ID.
 * @param int    $user_id    User ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value Updated value.
 */
function maybe_log_updated_user_consent( int $meta_id, int $user_id, string $meta_key, $meta_value ): void {
	record_ai_consent_audit_event(
		'user',
		$user_id,
		consume_previous_meta_value( 'user', $user_id, $meta_key ),
		$meta_value,
		$meta_key
	);
}

/**
 * Log a deleted user-consent value.
 *
 * @param array<int> $meta_ids  Deleted meta IDs.
 * @param int        $user_id   User ID.
 * @param string     $meta_key  Meta key.
 * @param mixed      $meta_value Deleted value.
 */
function maybe_log_deleted_user_consent( array $meta_ids, int $user_id, string $meta_key, $meta_value ): void {
	unset( $meta_value );

	record_ai_consent_audit_event(
		'user',
		$user_id,
		consume_previous_meta_value( 'user', $user_id, $meta_key ),
		'',
		$meta_key
	);
}

/**
 * Log an added post-consent value.
 *
 * @param int    $meta_id    Meta ID.
 * @param int    $post_id    Post ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value Meta value.
 */
function maybe_log_added_post_consent( int $meta_id, int $post_id, string $meta_key, $meta_value ): void {
	record_ai_consent_audit_event( 'post', $post_id, '', $meta_value, $meta_key );
}

/**
 * Log an updated post-consent value.
 *
 * @param int    $meta_id    Meta ID.
 * @param int    $post_id    Post ID.
 * @param string $meta_key   Meta key.
 * @param mixed  $meta_value Updated value.
 */
function maybe_log_updated_post_consent( int $meta_id, int $post_id, string $meta_key, $meta_value ): void {
	record_ai_consent_audit_event(
		'post',
		$post_id,
		consume_previous_meta_value( 'post', $post_id, $meta_key ),
		$meta_value,
		$meta_key
	);
}

/**
 * Log a deleted post-consent value.
 *
 * @param array<int> $meta_ids  Deleted meta IDs.
 * @param int        $post_id   Post ID.
 * @param string     $meta_key  Meta key.
 * @param mixed      $meta_value Deleted value.
 */
function maybe_log_deleted_post_consent( array $meta_ids, int $post_id, string $meta_key, $meta_value ): void {
	unset( $meta_value );

	record_ai_consent_audit_event(
		'post',
		$post_id,
		consume_previous_meta_value( 'post', $post_id, $meta_key ),
		'',
		$meta_key
	);
}

/**
 * Store the current consent value for a later audit comparison.
 *
 * @param string $meta_type Meta type.
 * @param int    $object_id Object ID.
 * @param string $meta_key  Meta key.
 * @param mixed  $value     Previous value.
 */
function stash_previous_meta_value( string $meta_type, int $object_id, string $meta_key, $value ): void {
	global $_byline_feed_ai_consent_previous;

	$_byline_feed_ai_consent_previous[ get_previous_value_cache_key( $meta_type, $object_id, $meta_key ) ] = sanitize_ai_consent( $value );
}

/**
 * Consume a cached previous consent value.
 *
 * @param string $meta_type Meta type.
 * @param int    $object_id Object ID.
 * @param string $meta_key  Meta key.
 * @return string
 */
function consume_previous_meta_value( string $meta_type, int $object_id, string $meta_key ): string {
	global $_byline_feed_ai_consent_previous;

	$key = get_previous_value_cache_key( $meta_type, $object_id, $meta_key );

	if ( isset( $_byline_feed_ai_consent_previous[ $key ] ) ) {
		$value = $_byline_feed_ai_consent_previous[ $key ];
		unset( $_byline_feed_ai_consent_previous[ $key ] );
		return sanitize_ai_consent( $value );
	}

	return '';
}

/**
 * Return the request-local cache key for a previous-value lookup.
 *
 * @param string $meta_type Meta type.
 * @param int    $object_id Object ID.
 * @param string $meta_key  Meta key.
 * @return string
 */
function get_previous_value_cache_key( string $meta_type, int $object_id, string $meta_key ): string {
	return implode( ':', array( $meta_type, (string) $object_id, $meta_key ) );
}

/**
 * Record an AI-consent state change if the key/value pair is relevant.
 *
 * @param string $target_type Target type: user or post.
 * @param int    $target_id   Target object ID.
 * @param mixed  $old_value   Previous value.
 * @param mixed  $new_value   New value.
 * @param string $meta_key    Meta key.
 */
function record_ai_consent_audit_event( string $target_type, int $target_id, $old_value, $new_value, string $meta_key ): void {
	if ( ! is_audit_meta_key( $target_type, $meta_key ) ) {
		return;
	}

	$old_value = sanitize_ai_consent( $old_value );
	$new_value = sanitize_ai_consent( $new_value );

	if ( $old_value === $new_value ) {
		return;
	}

	$entry = array(
		'timestamp_gmt' => gmdate( 'c' ),
		'actor_user_id' => get_current_user_id(),
		'target_type'   => $target_type,
		'target_id'     => $target_id,
		'old_value'     => $old_value,
		'new_value'     => $new_value,
	);

	/**
	 * Filters an audit-log entry before persistence.
	 *
	 * @param array<string, mixed> $entry Audit entry array.
	 */
	$entry = apply_filters( 'byline_feed_ai_consent_audit_entry', $entry );

	if ( ! is_array( $entry ) ) {
		return;
	}

	append_audit_log_entry( $entry );
}

/**
 * Persist an audit-log entry and enforce the retention cap.
 *
 * @param array<string, mixed> $entry Audit entry array.
 */
function append_audit_log_entry( array $entry ): void {
	$entries    = get_audit_log_entries();
	$normalized = array(
		'timestamp_gmt' => isset( $entry['timestamp_gmt'] ) && is_string( $entry['timestamp_gmt'] ) ? $entry['timestamp_gmt'] : gmdate( 'c' ),
		'actor_user_id' => isset( $entry['actor_user_id'] ) ? (int) $entry['actor_user_id'] : 0,
		'target_type'   => isset( $entry['target_type'] ) && is_string( $entry['target_type'] ) ? $entry['target_type'] : '',
		'target_id'     => isset( $entry['target_id'] ) ? (int) $entry['target_id'] : 0,
		'old_value'     => isset( $entry['old_value'] ) ? sanitize_ai_consent( $entry['old_value'] ) : '',
		'new_value'     => isset( $entry['new_value'] ) ? sanitize_ai_consent( $entry['new_value'] ) : '',
	);

	array_unshift( $entries, $normalized );

	if ( count( $entries ) > get_audit_log_limit() ) {
		$entries = array_slice( $entries, 0, get_audit_log_limit() );
	}
	$entries = array_values( $entries );

	if ( false === get_option( get_audit_log_option_name(), false ) ) {
		add_option( get_audit_log_option_name(), $entries, '', false );
		return;
	}

	update_option( get_audit_log_option_name(), $entries, false );
}

/**
 * Determine whether a meta key should be included in the audit log.
 *
 * @param string $target_type Target type.
 * @param string $meta_key    Meta key.
 * @return bool
 */
function is_audit_meta_key( string $target_type, string $meta_key ): bool {
	if ( 'user' === $target_type ) {
		return 'byline_feed_ai_consent' === $meta_key;
	}

	if ( 'post' === $target_type ) {
		return '_byline_ai_consent' === $meta_key;
	}

	return false;
}

/**
 * Return a readable label for an actor user.
 *
 * @param int $user_id User ID.
 * @return string
 */
function get_actor_label( int $user_id ): string {
	if ( $user_id <= 0 ) {
		return __( 'System', 'byline-feed' );
	}

	$user = get_userdata( $user_id );

	if ( ! $user ) {
		return sprintf(
			/* translators: %d: user ID */
			__( 'User #%d', 'byline-feed' ),
			$user_id
		);
	}

	return sprintf(
		/* translators: 1: display name, 2: user ID */
		__( '%1$s (#%2$d)', 'byline-feed' ),
		$user->display_name,
		$user_id
	);
}

/**
 * Return a readable label for an audited target object.
 *
 * @param string $target_type Target type.
 * @param int    $target_id   Target object ID.
 * @return string
 */
function get_audit_target_label( string $target_type, int $target_id ): string {
	if ( 'user' === $target_type ) {
		$user = get_userdata( $target_id );

		if ( $user ) {
			return sprintf(
				/* translators: 1: display name, 2: user ID */
				__( 'User: %1$s (#%2$d)', 'byline-feed' ),
				$user->display_name,
				$target_id
			);
		}

		return sprintf(
			/* translators: %d: user ID */
			__( 'User #%d', 'byline-feed' ),
			$target_id
		);
	}

	$post = get_post( $target_id );

	if ( $post instanceof WP_Post ) {
		return sprintf(
			/* translators: 1: post title, 2: post ID */
			__( 'Post: %1$s (#%2$d)', 'byline-feed' ),
			get_the_title( $post ),
			$target_id
		);
	}

	return sprintf(
		/* translators: %d: post ID */
		__( 'Post #%d', 'byline-feed' ),
		$target_id
	);
}

/**
 * Format a consent value for audit-log display.
 *
 * @param string $value Stored value.
 * @return string
 */
function format_audit_value( string $value ): string {
	return '' === $value ? '(inherit)' : $value;
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
