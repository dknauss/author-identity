#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
TMP_DIR="${PLUGIN_DIR}/.tmp"
POST_ID_FILE="${TMP_DIR}/e2e-post-id"
MU_PLUGIN_FILE='/var/www/html/wp-content/mu-plugins/byline-feed-e2e-tools.php'

SKIP_START=false
for arg in "$@"; do
  case "$arg" in
    --skip-start) SKIP_START=true ;;
  esac
done

mkdir -p "${TMP_DIR}"

cd "${PLUGIN_DIR}"

npm run build

if [[ "${SKIP_START}" != "true" ]]; then
  npx @wordpress/env start
fi

npx @wordpress/env run cli wp plugin activate byline-feed --allow-root
npx @wordpress/env run cli wp option update permalink_structure '/%postname%/' --allow-root
npx @wordpress/env run cli wp rewrite flush --allow-root
npx @wordpress/env run cli wp user update admin --user_pass='password' --allow-root
npx @wordpress/env run cli bash -lc "mkdir -p /var/www/html/wp-content/mu-plugins" --allow-root
npx @wordpress/env run cli bash -lc "cat > '${MU_PLUGIN_FILE}' <<'PHP'
<?php
/**
 * Byline Feed E2E helpers.
 */

add_filter(
	'use_block_editor_for_post',
	static function ( \$use_block_editor ) {
		if ( isset( \$_GET['byline_force_classic'] ) && '1' === (string) \$_GET['byline_force_classic'] ) {
			return false;
		}

		return \$use_block_editor;
	}
);
PHP" --allow-root

# Draft fixture post for perspective-panel editor tests.
POST_ID="$(
	npx @wordpress/env run cli wp eval '
		$post = get_page_by_path( "byline-perspective-e2e", OBJECT, "post" );
		if ( $post instanceof WP_Post ) {
			echo $post->ID;
		}
	' --allow-root | tr -d '\r'
)"

if [[ -z "${POST_ID}" ]]; then
	POST_ID="$(npx @wordpress/env run cli wp post create \
		--post_type=post \
		--post_status=draft \
		--post_author=1 \
		--post_title='Byline Perspective E2E' \
		--post_name='byline-perspective-e2e' \
		--porcelain \
		--allow-root | tr -d '\r')"
fi

npx @wordpress/env run cli wp post update "${POST_ID}" --post_author=1 --allow-root >/dev/null

npx @wordpress/env run cli wp post meta delete "${POST_ID}" _byline_perspective --allow-root >/dev/null 2>&1 || true
npx @wordpress/env run cli wp post meta delete "${POST_ID}" _byline_ai_consent --allow-root >/dev/null 2>&1 || true
npx @wordpress/env run cli wp user meta delete admin byline_feed_ai_consent --allow-root >/dev/null 2>&1 || true

printf '%s\n' "${POST_ID}" > "${POST_ID_FILE}"
echo "E2E fixture post (draft): ${POST_ID}"

# Published fixture post for feed-output and schema tests.
PUBLISHED_POST_ID_FILE="${TMP_DIR}/e2e-published-post-id"

PUB_POST_ID="$(
	npx @wordpress/env run cli wp eval '
		$posts = get_posts( array(
			"name"        => "byline-feed-output-e2e",
			"post_type"   => "post",
			"post_status" => "publish",
			"numberposts" => 1,
		) );
		if ( ! empty( $posts ) ) {
			echo $posts[0]->ID;
		}
	' --allow-root | tr -d '\r'
)"

if [[ -z "${PUB_POST_ID}" ]]; then
	PUB_POST_ID="$(npx @wordpress/env run cli wp post create \
		--post_type=post \
		--post_status=publish \
		--post_author=1 \
		--post_title='Byline Feed Output E2E' \
		--post_name='byline-feed-output-e2e' \
		--post_content='Feed output fixture content for E2E testing.' \
		--porcelain \
		--allow-root | tr -d '\r')"
fi

npx @wordpress/env run cli wp post update "${PUB_POST_ID}" --post_author=1 --allow-root >/dev/null

printf '%s\n' "${PUB_POST_ID}" > "${PUBLISHED_POST_ID_FILE}"
echo "E2E fixture post (published): ${PUB_POST_ID}"
