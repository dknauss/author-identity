#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

VERSION="$(
	sed -n "s/^define( 'BYLINE_FEED_VERSION', '\\(.*\\)' );$/\\1/p" \
		"${PLUGIN_DIR}/byline-feed.php"
)"

if [[ -z "${VERSION}" ]]; then
	echo "Unable to determine BYLINE_FEED_VERSION from byline-feed.php" >&2
	exit 1
fi

DIST_DIR="${PLUGIN_DIR}/dist"
STAGE_DIR="${DIST_DIR}/byline-feed"
ARCHIVE_PATH="${DIST_DIR}/byline-feed-${VERSION}.zip"
CHECKSUM_PATH="${ARCHIVE_PATH}.sha256"

rm -rf "${STAGE_DIR}" "${ARCHIVE_PATH}" "${CHECKSUM_PATH}"
mkdir -p "${STAGE_DIR}"

for path in byline-feed.php readme.txt build inc; do
	rsync -a "${PLUGIN_DIR}/${path}" "${STAGE_DIR}/"
done

(
	cd "${DIST_DIR}"
	COPYFILE_DISABLE=1 zip -X -rq "$(basename "${ARCHIVE_PATH}")" byline-feed
)

(
	cd "${DIST_DIR}"
	shasum -a 256 "$(basename "${ARCHIVE_PATH}")" > "$(basename "${CHECKSUM_PATH}")"
)

echo "Built ${ARCHIVE_PATH}"
echo "Built ${CHECKSUM_PATH}"
