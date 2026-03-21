<?php
/**
 * PublishPress Authors adapter.
 *
 * Resolves authors via publishpress_authors_get_post_authors() and
 * normalizes them into the Byline Feed author contract.
 *
 * @package Byline_Feed
 */

namespace Byline_Feed;

defined( 'ABSPATH' ) || exit;

/**
 * PublishPress Authors adapter implementation.
 */
class Adapter_PPA implements Adapter {

	/**
	 * Get normalized authors for a post using PublishPress Authors.
	 *
	 * @param \WP_Post $post Post object.
	 * @return object[]
	 */
	public function get_authors( \WP_Post $post ): array {
		if ( function_exists( 'publishpress_authors_get_post_authors' ) ) {
			$authors = publishpress_authors_get_post_authors( $post->ID );
		} elseif ( function_exists( 'get_post_authors' ) ) {
			$authors = get_post_authors( $post->ID );
		} else {
			return array();
		}

		if ( ! is_array( $authors ) ) {
			return array();
		}

		$authors = array_values(
			array_filter(
				$authors,
				static function ( $author ): bool {
					return is_object( $author );
				}
			)
		);

		return array_map( array( $this, 'normalize' ), $authors );
	}

	/**
	 * Normalize a PublishPress Authors author object.
	 *
	 * @param object $author A PPA author object.
	 * @return object Normalized author object.
	 */
	private function normalize( object $author ): object {
		$is_guest = false;

		if ( method_exists( $author, 'is_guest' ) ) {
			$is_guest = (bool) $author->is_guest();
		} elseif ( ! empty( $author->is_guest ) ) {
			$is_guest = true;
		}

			$user_id = $author->user_id ?? 0;
			$user    = $user_id ? get_userdata( $user_id ) : null;
			$term_id = $author->term_id ?? 0;

		$role = $is_guest ? 'guest' : get_byline_role_from_user( $user );
		$role = apply_filters( 'byline_feed_role', $role, $author, null );

		// PPA stores profile data in term meta for guest authors, user meta for linked users.
		$description = '';
		$url         = '';
		$avatar_url  = '';

		if ( $term_id ) {
			$description_meta = get_term_meta( $term_id, 'description', true );
			$avatar_meta      = get_term_meta( $term_id, 'avatar', true );
			$description      = is_string( $description_meta ) ? $description_meta : '';
			$avatar_url       = is_string( $avatar_meta ) ? $avatar_meta : '';
		}

		if ( $user ) {
			if ( '' === $description ) {
				$description = $user->description;
			}
			$url = $user->user_url;
			if ( '' === $avatar_url ) {
				$avatar_url = get_avatar_url( $user->ID );
			}
		}

		$fediverse    = '';
		$ap_actor_url = '';
		$ai_consent   = '';

		if ( $user_id ) {
			$fediverse       = get_byline_feed_fediverse_for_user( (int) $user_id );
			$ap_actor_url    = get_byline_feed_ap_actor_url_for_user( (int) $user_id );
			$ai_consent_meta = get_user_meta( $user_id, 'byline_feed_ai_consent', true );
			$ai_consent      = is_string( $ai_consent_meta ) ? $ai_consent_meta : '';
		}

		return (object) array(
			'id'           => $author->slug ?? '',
			'display_name' => $author->display_name ?? '',
			'description'  => $description,
			'url'          => $url,
			'avatar_url'   => $avatar_url,
			'user_id'      => (int) $user_id,
			'role'         => $role,
			'is_guest'     => $is_guest,
			'profiles'     => $user_id ? get_byline_feed_profiles_for_user( (int) $user_id ) : array(),
			'now_url'      => $user_id ? get_byline_feed_now_url_for_user( (int) $user_id ) : '',
			'uses_url'     => $user_id ? get_byline_feed_uses_url_for_user( (int) $user_id ) : '',
			'fediverse'    => $fediverse,
			'ap_actor_url' => $ap_actor_url,
			'ai_consent'   => $ai_consent,
		);
	}
}
