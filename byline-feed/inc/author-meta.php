<?php
/**
 * Author meta helpers and user-profile fields.
 *
 * Canonical plugin-owned storage for Byline-specific author fields that are
 * not consistently available from upstream multi-author plugins.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed;

defined( 'ABSPATH' ) || exit;

/**
 * Register user-profile hooks for canonical Byline author fields.
 */
function register_author_meta_hooks(): void {
	add_action( 'init', __NAMESPACE__ . '\\register_author_meta' );
	add_action( 'show_user_profile', __NAMESPACE__ . '\\render_author_meta_fields' );
	add_action( 'edit_user_profile', __NAMESPACE__ . '\\render_author_meta_fields' );
	add_action( 'personal_options_update', __NAMESPACE__ . '\\save_author_meta_fields' );
	add_action( 'edit_user_profile_update', __NAMESPACE__ . '\\save_author_meta_fields' );
	add_filter( 'coauthors_guest_author_fields', __NAMESPACE__ . '\\add_cap_guest_author_fediverse_field', 10, 2 );
	add_filter( 'authors_editor_fields', __NAMESPACE__ . '\\add_ppa_author_fediverse_field', 10, 2 );
}

/**
 * Add a fediverse handle field to Co-Authors Plus guest authors.
 *
 * Guest authors have no user profile, so the handle lives on the guest
 * record. CAP stores it as `cap-byline_feed_fediverse` post meta and puts it
 * on the coauthor object as `byline_feed_fediverse`. Authored data.
 *
 * @param array        $fields Guest author fields.
 * @param string|array $groups Requested field groups.
 * @return array
 */
function add_cap_guest_author_fediverse_field( $fields, $groups = 'all' ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}

	$groups = (array) $groups;

	if ( ! in_array( 'all', $groups, true ) && ! in_array( 'contact-info', $groups, true ) ) {
		return $fields;
	}

	foreach ( $fields as $field ) {
		if ( is_array( $field ) && isset( $field['key'] ) && 'byline_feed_fediverse' === $field['key'] ) {
			return $fields;
		}
	}

	$fields[] = array(
		'key'               => 'byline_feed_fediverse',
		'label'             => __( 'Fediverse handle', 'byline-feed' ),
		'group'             => 'contact-info',
		'sanitize_function' => __NAMESPACE__ . '\\normalize_byline_feed_fediverse',
	);

	return $fields;
}

/**
 * Add a fediverse handle field to PublishPress Authors guest authors.
 *
 * Stored as `byline_feed_fediverse` term meta on the author term. Shown only
 * for authors without a linked user: a linked user's handle comes from their
 * user profile. Authored data.
 *
 * @param array       $fields Author editor fields.
 * @param object|null $author PPA author being edited, if any.
 * @return array
 */
function add_ppa_author_fediverse_field( $fields, $author = null ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}

	if ( is_object( $author ) && ! empty( $author->user_id ) ) {
		return $fields;
	}

	$fields['byline_feed_fediverse'] = array(
		'label'       => esc_html__( 'Fediverse handle', 'byline-feed' ),
		'type'        => 'text',
		'sanitize'    => __NAMESPACE__ . '\\normalize_byline_feed_fediverse',
		'tab'         => 'general',
		'description' => esc_html__( 'Mastodon or fediverse account, for example @you@mastodon.social. Credits this author on shared links.', 'byline-feed' ),
	);

	return $fields;
}

/**
 * Register plugin-owned user meta surfaced through the REST API.
 */
function register_author_meta(): void {
	register_meta(
		'user',
		'byline_feed_fediverse',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => __NAMESPACE__ . '\\normalize_byline_feed_fediverse',
			'auth_callback'     => __NAMESPACE__ . '\\can_edit_byline_feed_user_meta',
		)
	);

	register_meta(
		'user',
		'byline_feed_fediverse_source',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => 'auto',
			'show_in_rest'      => array(
				'schema' => array(
					'type' => 'string',
					'enum' => array( 'auto', 'manual', 'none' ),
				),
			),
			'sanitize_callback' => __NAMESPACE__ . '\\sanitize_byline_feed_fediverse_source',
			'auth_callback'     => __NAMESPACE__ . '\\can_edit_byline_feed_user_meta',
		)
	);
}

/**
 * Determine whether the current user can edit a Byline user-meta field.
 *
 * The callback signature varies by WordPress version. We only care about the
 * user object being edited, which is passed as the third argument.
 *
 * @param mixed ...$args Auth callback arguments from WordPress.
 * @return bool
 */
function can_edit_byline_feed_user_meta( ...$args ): bool {
	$object_id = isset( $args[2] ) ? (int) $args[2] : 0;

	if ( $object_id > 0 ) {
		return current_user_can( 'edit_user', $object_id );
	}

	return current_user_can( 'edit_users' );
}

/**
 * Return normalized Byline profile links for a user.
 *
 * @param int $user_id User ID.
 * @return array<int, array{href:string,rel:string}>
 */
function get_byline_feed_profiles_for_user( int $user_id ): array {
	$profiles = get_user_meta( $user_id, 'byline_feed_profiles', true );

	return normalize_byline_profiles( $profiles );
}

/**
 * Return the stored /now URL for a user.
 *
 * @param int $user_id User ID.
 * @return string
 */
function get_byline_feed_now_url_for_user( int $user_id ): string {
	$now_url = get_user_meta( $user_id, 'byline_feed_now_url', true );

	return is_string( $now_url ) ? esc_url_raw( $now_url ) : '';
}

/**
 * Return the stored /uses URL for a user.
 *
 * @param int $user_id User ID.
 * @return string
 */
function get_byline_feed_uses_url_for_user( int $user_id ): string {
	$uses_url = get_user_meta( $user_id, 'byline_feed_uses_url', true );

	return is_string( $uses_url ) ? esc_url_raw( $uses_url ) : '';
}

/**
 * Return the effective fediverse handle for a user.
 *
 * Composite field: the handle the user typed into Byline Feed (authored)
 * wins. With no typed handle and the source left on `auto`, a handle is
 * borrowed from another source the site already holds (derived). See
 * get_byline_feed_derived_fediverse_for_user(). The user's source setting:
 *
 * - `auto` (also the default when unset): typed handle, else derived;
 * - `manual`: typed handle only;
 * - `none`: no handle, so the user is never credited.
 *
 * @param int $user_id User ID.
 * @return string Normalized `@user@instance` handle or empty string.
 */
function get_byline_feed_fediverse_for_user( int $user_id ): string {
	if ( $user_id <= 0 ) {
		return '';
	}

	$source = get_byline_feed_fediverse_source_for_user( $user_id );

	if ( 'none' === $source ) {
		return '';
	}

	$handle = get_byline_feed_manual_fediverse_for_user( $user_id );

	if ( '' !== $handle || 'manual' === $source ) {
		return $handle;
	}

	return get_byline_feed_derived_fediverse_for_user( $user_id );
}

/**
 * Return the fediverse handle the user typed into Byline Feed.
 *
 * Authored identity data, normalized to the `@user@instance` format.
 *
 * @param int $user_id User ID.
 * @return string
 */
function get_byline_feed_manual_fediverse_for_user( int $user_id ): string {
	$handle = get_user_meta( $user_id, 'byline_feed_fediverse', true );

	return is_string( $handle ) ? normalize_byline_feed_fediverse( $handle ) : '';
}

/**
 * Return the user's fediverse handle source setting.
 *
 * @param int $user_id User ID.
 * @return string `auto`, `manual`, or `none`.
 */
function get_byline_feed_fediverse_source_for_user( int $user_id ): string {
	return sanitize_byline_feed_fediverse_source( get_user_meta( $user_id, 'byline_feed_fediverse_source', true ) );
}

/**
 * Sanitize a fediverse handle source value.
 *
 * @param mixed $source Candidate value.
 * @return string `auto`, `manual`, or `none`. Anything else is `auto`.
 */
function sanitize_byline_feed_fediverse_source( $source ): string {
	return is_string( $source ) && in_array( $source, array( 'auto', 'manual', 'none' ), true ) ? $source : 'auto';
}

/**
 * Return a fediverse handle derived from data the site already holds.
 *
 * Derived identity data, used only when the user typed no handle. Sources,
 * in order:
 *
 * 1. The SEO Framework's per-user "Fediverse profile" field, when TSF is
 *    active. The user typed it, just in another plugin.
 * 2. The user's local ActivityPub actor, when the ActivityPub plugin is
 *    active and lists this site as allowed to credit its actors.
 *
 * Never inferred from `profiles` links or from `ap_actor_url`.
 *
 * @param int $user_id User ID.
 * @return string
 */
function get_byline_feed_derived_fediverse_for_user( int $user_id ): string {
	$handle = get_byline_feed_tsf_fediverse_for_user( $user_id );

	if ( '' === $handle ) {
		$handle = get_byline_feed_activitypub_fediverse_for_user( $user_id );
	}

	/**
	 * Filters the derived fediverse handle for a linked WordPress user.
	 *
	 * Runs only when the user typed no handle and their source is `auto`.
	 * Filtered values are re-normalized.
	 *
	 * @param string $handle  Derived handle or empty string.
	 * @param int    $user_id WordPress user ID.
	 */
	$handle = apply_filters( 'byline_feed_derived_fediverse_handle', $handle, $user_id );

	return normalize_byline_feed_fediverse( $handle );
}

/**
 * Return the handle from The SEO Framework's per-user "Fediverse profile" field.
 *
 * TSF (5.2+) stores it as `fediverse_page` in its user settings array.
 *
 * @param int $user_id User ID.
 * @return string
 */
function get_byline_feed_tsf_fediverse_for_user( int $user_id ): string {
	if ( ! defined( 'THE_SEO_FRAMEWORK_USER_OPTIONS' ) ) {
		return '';
	}

	$settings = get_user_meta( $user_id, (string) constant( 'THE_SEO_FRAMEWORK_USER_OPTIONS' ), true );

	if ( ! is_array( $settings ) || ! isset( $settings['fediverse_page'] ) ) {
		return '';
	}

	return normalize_byline_feed_fediverse( $settings['fediverse_page'] );
}

/**
 * Return the user's local ActivityPub handle when it can be credited.
 *
 * Confident only when the ActivityPub plugin resolves an actor for the user
 * and lists this site among the actor's attribution domains. Without that,
 * Mastodon would not show the credit, so no handle is returned.
 *
 * @param int $user_id User ID.
 * @return string
 */
function get_byline_feed_activitypub_fediverse_for_user( int $user_id ): string {
	if ( ! class_exists( '\\Activitypub\\Collection\\Actors' ) || ! method_exists( '\\Activitypub\\Collection\\Actors', 'get_by_id' ) ) {
		return '';
	}

	if ( function_exists( '\\Activitypub\\get_attribution_domains' ) ) {
		$domains = \Activitypub\get_attribution_domains();
		$host    = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( ! is_array( $domains ) || ! is_string( $host ) || ! in_array( $host, array_map( 'trim', $domains ), true ) ) {
			return '';
		}
	}

	$actor = \Activitypub\Collection\Actors::get_by_id( $user_id );

	if ( is_wp_error( $actor ) || ! is_object( $actor ) || ! method_exists( $actor, 'get_webfinger' ) ) {
		return '';
	}

	$webfinger = $actor->get_webfinger();

	return is_string( $webfinger ) ? normalize_byline_feed_fediverse( $webfinger ) : '';
}

/**
 * Return the stored AI-consent preference for a user.
 *
 * @param int $user_id User ID.
 * @return string
 */
function get_byline_feed_ai_consent_for_user( int $user_id ): string {
	$consent = get_user_meta( $user_id, 'byline_feed_ai_consent', true );

	return Rights\sanitize_ai_consent( $consent );
}

/**
 * Return the ActivityPub actor URL for a linked WordPress user when resolvable.
 *
 * This is derived identity data. It must remain distinct from authored
 * `profiles` links and from the authored `fediverse` handle.
 *
 * @param int $user_id User ID.
 * @return string
 */
function get_byline_feed_ap_actor_url_for_user( int $user_id ): string {
	if ( $user_id <= 0 ) {
		return '';
	}

	$actor_url = '';

	if ( class_exists( '\\Activitypub\\Collection\\Actors' ) && method_exists( '\\Activitypub\\Collection\\Actors', 'get_by_id' ) ) {
		$actor = \Activitypub\Collection\Actors::get_by_id( $user_id );

		if ( ! is_wp_error( $actor ) ) {
			if ( is_object( $actor ) && method_exists( $actor, 'get_id' ) ) {
				$actor_id  = $actor->get_id();
				$actor_url = is_string( $actor_id ) ? esc_url_raw( $actor_id ) : '';
			} elseif ( is_object( $actor ) && isset( $actor->id ) && is_string( $actor->id ) ) {
				$actor_url = esc_url_raw( $actor->id );
			}
		}
	}

	if ( '' === $actor_url && defined( 'ACTIVITYPUB_REST_NAMESPACE' ) ) {
		$actor_url = esc_url_raw(
			rest_url(
				trailingslashit( constant( 'ACTIVITYPUB_REST_NAMESPACE' ) ) . 'users/' . $user_id
			)
		);
	}

	/**
	 * Filters the resolved ActivityPub actor URL for a linked WP user.
	 *
	 * @param string $actor_url Resolved actor URL or empty string.
	 * @param int    $user_id   WordPress user ID.
	 */
	$actor_url = apply_filters( 'byline_feed_ap_actor_url', $actor_url, $user_id );

	return is_string( $actor_url ) ? esc_url_raw( $actor_url ) : '';
}

/**
 * Normalize a fediverse handle to the expected `@user@instance` format.
 *
 * @param mixed $handle Candidate handle value.
 * @return string
 */
function normalize_byline_feed_fediverse( $handle ): string {
	if ( ! is_string( $handle ) ) {
		return '';
	}

	$handle = trim( sanitize_text_field( $handle ) );

	if ( '' === $handle ) {
		return '';
	}

	if ( ! preg_match( '/^@?[\w.-]+@[\w.-]+$/', $handle ) ) {
		return '';
	}

	if ( '@' !== substr( $handle, 0, 1 ) ) {
		$handle = '@' . $handle;
	}

	return $handle;
}

/**
 * Normalize candidate Byline profile data.
 *
 * Accepts either an array of arrays/objects or the textarea input format used
 * by the user-profile screen: one `rel | URL` pair per line.
 *
 * @param mixed $profiles Candidate profile data.
 * @return array<int, array{href:string,rel:string}>
 */
function normalize_byline_profiles( $profiles ): array {
	$normalized = array();

	if ( is_string( $profiles ) ) {
		$profiles = parse_byline_profiles_textarea( $profiles );
	}

	if ( ! is_array( $profiles ) ) {
		return array();
	}

	foreach ( $profiles as $profile ) {
		$href = '';
		$rel  = '';

		if ( is_array( $profile ) ) {
			$href = isset( $profile['href'] ) && is_string( $profile['href'] ) ? esc_url_raw( $profile['href'] ) : '';
			$rel  = isset( $profile['rel'] ) && is_string( $profile['rel'] ) ? sanitize_text_field( $profile['rel'] ) : '';
		} elseif ( is_object( $profile ) ) {
			$href = isset( $profile->href ) && is_string( $profile->href ) ? esc_url_raw( $profile->href ) : '';
			$rel  = isset( $profile->rel ) && is_string( $profile->rel ) ? sanitize_text_field( $profile->rel ) : '';
		}

		if ( '' === $href || '' === $rel ) {
			continue;
		}

		$normalized[] = array(
			'href' => $href,
			'rel'  => $rel,
		);
	}

	return $normalized;
}

/**
 * Parse the profile textarea value into normalized profile entries.
 *
 * @param string $textarea Raw textarea content.
 * @return array<int, array{href:string,rel:string}>
 */
function parse_byline_profiles_textarea( string $textarea ): array {
	$entries = array();
	$lines   = preg_split( '/\r\n|\r|\n/', $textarea );

	if ( ! is_array( $lines ) ) {
		return array();
	}

	foreach ( $lines as $line ) {
		$line = trim( $line );

		if ( '' === $line ) {
			continue;
		}

		$parts = array_map( 'trim', explode( '|', $line, 2 ) );

		if ( 2 !== count( $parts ) ) {
			continue;
		}

		$entries[] = array(
			'rel'  => $parts[0],
			'href' => $parts[1],
		);
	}

	return normalize_byline_profiles( $entries );
}

/**
 * Render plugin-owned author fields on the user profile screen.
 *
 * @param \WP_User $user User being edited.
 */
function render_author_meta_fields( \WP_User $user ): void {
	$profiles  = get_byline_feed_profiles_for_user( $user->ID );
	$now_url   = get_byline_feed_now_url_for_user( $user->ID );
	$uses_url  = get_byline_feed_uses_url_for_user( $user->ID );
	$fediverse = get_byline_feed_manual_fediverse_for_user( $user->ID );
	$source    = get_byline_feed_fediverse_source_for_user( $user->ID );
	$derived   = get_byline_feed_derived_fediverse_for_user( $user->ID );
	$consent   = get_byline_feed_ai_consent_for_user( $user->ID );
	$policy    = Rights\get_policy_url();

	$profile_lines = array_map(
		static function ( array $profile ): string {
			return $profile['rel'] . ' | ' . $profile['href'];
		},
		$profiles
	);
	?>
	<h2><?php esc_html_e( 'Byline Feed', 'byline-feed' ); ?></h2>
	<?php wp_nonce_field( 'byline_feed_author_meta', 'byline_feed_author_meta_nonce' ); ?>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="byline-feed-profiles"><?php esc_html_e( 'Profile links', 'byline-feed' ); ?></label></th>
			<td>
				<textarea name="byline_feed_profiles" id="byline-feed-profiles" rows="5" class="large-text code"><?php echo esc_textarea( implode( "\n", $profile_lines ) ); ?></textarea>
				<p class="description"><?php esc_html_e( 'One profile per line in the format: rel | https://example.com/profile', 'byline-feed' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="byline-feed-now-url"><?php esc_html_e( '/now URL', 'byline-feed' ); ?></label></th>
			<td>
				<input name="byline_feed_now_url" id="byline-feed-now-url" type="url" value="<?php echo esc_attr( $now_url ); ?>" class="regular-text" />
			</td>
		</tr>
		<tr>
			<th><label for="byline-feed-uses-url"><?php esc_html_e( '/uses URL', 'byline-feed' ); ?></label></th>
			<td>
				<input name="byline_feed_uses_url" id="byline-feed-uses-url" type="url" value="<?php echo esc_attr( $uses_url ); ?>" class="regular-text" />
			</td>
		</tr>
		<tr>
			<th><label for="byline-feed-fediverse"><?php esc_html_e( 'Fediverse handle', 'byline-feed' ); ?></label></th>
			<td>
				<input name="byline_feed_fediverse" id="byline-feed-fediverse" type="text" value="<?php echo esc_attr( $fediverse ); ?>" class="regular-text code" />
				<p class="description"><?php esc_html_e( 'Your Mastodon or fediverse account (for example @you@mastodon.social). Used for author attribution on shared links.', 'byline-feed' ); ?></p>
				<?php if ( '' !== $derived ) : ?>
					<p class="description" id="byline-feed-fediverse-derived">
						<?php
						printf(
							/* translators: %s: fediverse handle found on this site. */
							esc_html__( 'Found on this site: %s. Used when the field above is empty.', 'byline-feed' ),
							'<code>' . esc_html( $derived ) . '</code>'
						);
						?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><label for="byline-feed-fediverse-source"><?php esc_html_e( 'Credit on shared links', 'byline-feed' ); ?></label></th>
			<td>
				<select name="byline_feed_fediverse_source" id="byline-feed-fediverse-source">
					<option value="auto" <?php selected( 'auto', $source ); ?>><?php esc_html_e( 'Automatic: the handle above, or one found on this site', 'byline-feed' ); ?></option>
					<option value="manual" <?php selected( 'manual', $source ); ?>><?php esc_html_e( 'Only the handle above', 'byline-feed' ); ?></option>
					<option value="none" <?php selected( 'none', $source ); ?>><?php esc_html_e( 'Do not credit me', 'byline-feed' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'A handle can be found in The SEO Framework\'s Fediverse profile field, or from this site\'s ActivityPub profile for you. Mastodon shows the credit only if your account lists this site under Verification, Author attribution.', 'byline-feed' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="byline-feed-ai-consent"><?php esc_html_e( 'AI training consent', 'byline-feed' ); ?></label></th>
			<td>
				<select name="byline_feed_ai_consent" id="byline-feed-ai-consent">
					<option value="" <?php selected( '', $consent ); ?>><?php esc_html_e( 'No preference', 'byline-feed' ); ?></option>
					<option value="allow" <?php selected( 'allow', $consent ); ?>><?php esc_html_e( 'Allow', 'byline-feed' ); ?></option>
					<option value="deny" <?php selected( 'deny', $consent ); ?>><?php esc_html_e( 'Deny', 'byline-feed' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Advisory preference used for machine-readable AI training signals on content attributed to you, including feed-level summaries when your work appears in a feed.', 'byline-feed' ); ?></p>
				<?php if ( '' !== $policy ) : ?>
					<p class="description">
						<?php esc_html_e( 'Current site policy endpoint:', 'byline-feed' ); ?>
						<code><?php echo esc_html( $policy ); ?></code>
					</p>
				<?php endif; ?>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Save plugin-owned author fields from the user profile screen.
 *
 * @param int $user_id User ID.
 */
function save_author_meta_fields( int $user_id ): void {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	if ( ! isset( $_POST['byline_feed_author_meta_nonce'] ) ) {
		return;
	}

	$nonce = sanitize_text_field( wp_unslash( $_POST['byline_feed_author_meta_nonce'] ) );

	if ( ! wp_verify_nonce( $nonce, 'byline_feed_author_meta' ) ) {
		return;
	}

		$profiles   = array();
		$now_url    = '';
		$uses_url   = '';
		$fediverse  = '';
		$source     = null;
		$ai_consent = '';

	if ( isset( $_POST['byline_feed_profiles'] ) ) {
		$profiles = parse_byline_profiles_textarea( sanitize_textarea_field( wp_unslash( $_POST['byline_feed_profiles'] ) ) );
	}

	if ( isset( $_POST['byline_feed_now_url'] ) ) {
		$now_url = esc_url_raw( wp_unslash( $_POST['byline_feed_now_url'] ) );
	}

	if ( isset( $_POST['byline_feed_uses_url'] ) ) {
		$uses_url = esc_url_raw( wp_unslash( $_POST['byline_feed_uses_url'] ) );
	}

	if ( isset( $_POST['byline_feed_fediverse'] ) ) {
		$fediverse = normalize_byline_feed_fediverse( sanitize_text_field( wp_unslash( $_POST['byline_feed_fediverse'] ) ) );
	}

	if ( isset( $_POST['byline_feed_fediverse_source'] ) ) {
		$source = sanitize_byline_feed_fediverse_source( sanitize_key( wp_unslash( $_POST['byline_feed_fediverse_source'] ) ) );
	}

	if ( isset( $_POST['byline_feed_ai_consent'] ) ) {
		$ai_consent = Rights\sanitize_ai_consent( sanitize_text_field( wp_unslash( $_POST['byline_feed_ai_consent'] ) ) );
	}

	if ( empty( $profiles ) ) {
		delete_user_meta( $user_id, 'byline_feed_profiles' );
	} else {
		update_user_meta( $user_id, 'byline_feed_profiles', $profiles );
	}

	if ( '' === $now_url ) {
		delete_user_meta( $user_id, 'byline_feed_now_url' );
	} else {
		update_user_meta( $user_id, 'byline_feed_now_url', $now_url );
	}

	if ( '' === $uses_url ) {
		delete_user_meta( $user_id, 'byline_feed_uses_url' );
	} else {
		update_user_meta( $user_id, 'byline_feed_uses_url', $uses_url );
	}

	if ( '' === $fediverse ) {
		delete_user_meta( $user_id, 'byline_feed_fediverse' );
	} else {
		update_user_meta( $user_id, 'byline_feed_fediverse', $fediverse );
	}

	if ( 'auto' === $source ) {
		delete_user_meta( $user_id, 'byline_feed_fediverse_source' );
	} elseif ( null !== $source ) {
		update_user_meta( $user_id, 'byline_feed_fediverse_source', $source );
	}

	if ( '' === $ai_consent ) {
		delete_user_meta( $user_id, 'byline_feed_ai_consent' );
	} else {
		update_user_meta( $user_id, 'byline_feed_ai_consent', $ai_consent );
	}
}
