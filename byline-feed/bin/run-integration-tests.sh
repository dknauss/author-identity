#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

DEFAULT_TESTS_DIR=""
for candidate in "${WP_TESTS_DIR:-}" /tmp/byline-wp-tests /tmp/wordpress-tests-lib "${TMPDIR:-/tmp}/wordpress-tests-lib"; do
	if [[ -n "${candidate}" && -f "${candidate}/includes/functions.php" ]]; then
		DEFAULT_TESTS_DIR="${candidate}"
		break
	fi
done

if [[ -z "${DEFAULT_TESTS_DIR}" ]]; then
	echo "Could not find WordPress test suite. Set WP_TESTS_DIR or run byline-feed/bin/install-wp-tests.sh first." >&2
	exit 1
fi

if [[ ! -f "${DEFAULT_TESTS_DIR}/wp-tests-config.php" ]]; then
	echo "Could not find wp-tests-config.php in ${DEFAULT_TESTS_DIR}." >&2
	exit 1
fi

MULTISITE="${WP_MULTISITE:-0}"
MODE="single"
DB_NAME_SUFFIX="single"
if [[ "${MULTISITE}" == "1" ]]; then
	MODE="multisite"
	DB_NAME_SUFFIX="multisite"
fi

CONFIG_CONTENT="$(cat "${DEFAULT_TESTS_DIR}/wp-tests-config.php")"

extract_define() {
	local key="$1"
	php -r '
		$content = file_get_contents($argv[1]);
		$key = $argv[2];
		if (! preg_match("/define\\(\\s*[\"\\x27]" . preg_quote($key, "/") . "[\"\\x27]\\s*,\\s*[\"\\x27]([^\"\\x27]*)[\"\\x27]\\s*\\)/", $content, $matches)) {
			exit(1);
		}
		echo $matches[1];
	' "${DEFAULT_TESTS_DIR}/wp-tests-config.php" "${key}"
}

BASE_DB_NAME="$(extract_define "DB_NAME")"
DB_USER="$(extract_define "DB_USER")"
DB_PASSWORD="$(extract_define "DB_PASSWORD")"
DB_HOST="$(extract_define "DB_HOST")"

TESTS_RUN_DIR="/tmp/byline-feed-wp-tests-${MODE}"
DB_NAME="${BASE_DB_NAME}_${DB_NAME_SUFFIX}"

rm -rf "${TESTS_RUN_DIR}"
mkdir -p "${TESTS_RUN_DIR}"
cleanup() {
	rm -rf "${TESTS_RUN_DIR}"
}
trap cleanup EXIT

ln -s "${DEFAULT_TESTS_DIR}/includes" "${TESTS_RUN_DIR}/includes"
ln -s "${DEFAULT_TESTS_DIR}/data" "${TESTS_RUN_DIR}/data"

php -r '
	$content = file_get_contents($argv[1]);
	$db_name = $argv[2];
	$multisite = $argv[3];
	$content = preg_replace(
		"/define\\(\\s*[\"\\x27]DB_NAME[\"\\x27]\\s*,\\s*[\"\\x27][^\"\\x27]*[\"\\x27]\\s*\\)/",
		"define( \x27DB_NAME\x27, \x27{$db_name}\x27 )",
		$content,
		1
	);
	if ($multisite === "1" && strpos($content, "define( \x27WP_TESTS_MULTISITE\x27, true );") === false) {
		$content = preg_replace(
			"/\\/\\/ define\\( \x27WP_TESTS_MULTISITE\x27, true \\);/",
			"define( \x27WP_TESTS_MULTISITE\x27, true );",
			$content,
			1
		);
	}
	file_put_contents($argv[4], $content);
 ' "${DEFAULT_TESTS_DIR}/wp-tests-config.php" "${DB_NAME}" "${MULTISITE}" "${TESTS_RUN_DIR}/wp-tests-config.php"

MYSQL_PWD="${DB_PASSWORD}" mysql --protocol=tcp --host="${DB_HOST}" --user="${DB_USER}" \
	-e "DROP DATABASE IF EXISTS \`${DB_NAME}\`; CREATE DATABASE \`${DB_NAME}\`;" >/dev/null

echo "Running ${MODE} integration tests with DB ${DB_NAME} and WP_TESTS_DIR=${TESTS_RUN_DIR}"

(
	cd "${PLUGIN_DIR}"
	WP_TESTS_DIR="${TESTS_RUN_DIR}" WP_MULTISITE="${MULTISITE}" composer test
)
