#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
TMP_DIR="${PLUGIN_DIR}/.tmp"
POST_ID_FILE="${TMP_DIR}/e2e-post-id"

mkdir -p "${TMP_DIR}"

cd "${PLUGIN_DIR}"

npm run build
npx @wordpress/env start

npx @wordpress/env run cli wp plugin activate byline-feed --allow-root
npx @wordpress/env run cli wp option update permalink_structure '/%postname%/' --allow-root
npx @wordpress/env run cli wp user update admin --user_pass='password' --allow-root

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
		--post_title='Byline Perspective E2E' \
		--post_name='byline-perspective-e2e' \
		--porcelain \
		--allow-root | tr -d '\r')"
fi

npx @wordpress/env run cli wp post meta delete "${POST_ID}" _byline_perspective --allow-root >/dev/null 2>&1 || true

printf '%s\n' "${POST_ID}" > "${POST_ID_FILE}"
echo "E2E fixture post: ${POST_ID}"
