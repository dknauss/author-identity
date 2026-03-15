<?php
/**
 * Human Made Authorship adapter.
 *
 * Resolves authors via Authorship\get_authors() and normalizes them
 * into the Byline Feed author contract.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed;

defined( 'ABSPATH' ) || exit;

/**
 * Human Made Authorship adapter implementation.
 */
class Adapter_Authorship implements Adapter {

	/**
	 * Get normalized authors for a post using Human Made Authorship.
	 *
	 * @param \WP_Post $post Post object.
	 * @return object[]
	 */
	public function get_authors( \WP_Post $post ): array {
		if ( ! function_exists( 'Authorship\\get_authors' ) ) {
			return array();
		}

		$authors = \Authorship\get_authors( $post );

		if ( ! is_array( $authors ) ) {
			return array();
		}

		$authors = array_values(
			array_filter(
				$authors,
				static function ( $author ): bool {
					return $author instanceof \WP_User;
				}
			)
		);

		return array_map( array( $this, 'normalize' ), $authors );
	}

	/**
	 * Normalize an Authorship WP_User object.
	 *
	 * @param \WP_User $user Authorship user object.
	 * @return object Normalized author object.
	 */
	private function normalize( \WP_User $user ): object {
		$guest_role = defined( 'Authorship\\GUEST_ROLE' ) ? constant( 'Authorship\\GUEST_ROLE' ) : 'guest-author';
		$is_guest   = in_array( $guest_role, (array) $user->roles, true );
		$role       = $is_guest ? 'guest' : get_byline_role_from_user( $user );
		$role       = apply_filters( 'byline_feed_role', $role, $user, null );

		$ai_consent = get_user_meta( $user->ID, 'byline_feed_ai_consent', true );
		if ( ! is_string( $ai_consent ) ) {
			$ai_consent = '';
		}

		return (object) array(
			'id'           => $user->user_nicename,
			'display_name' => $user->display_name,
			'description'  => $user->description,
			'url'          => $user->user_url,
			'avatar_url'   => get_avatar_url( $user->ID ),
			'user_id'      => $user->ID,
			'role'         => $role,
			'is_guest'     => $is_guest,
			'profiles'     => get_byline_feed_profiles_for_user( $user->ID ),
			'now_url'      => get_byline_feed_now_url_for_user( $user->ID ),
			'uses_url'     => get_byline_feed_uses_url_for_user( $user->ID ),
			'fediverse'    => get_byline_feed_fediverse_for_user( $user->ID ),
			'ap_actor_url' => get_byline_feed_ap_actor_url_for_user( $user->ID ),
			'ai_consent'   => $ai_consent,
		);
	}
}
