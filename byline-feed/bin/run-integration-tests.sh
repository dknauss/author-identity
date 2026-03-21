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

parse_db_host() {
	local input="$1"
	local host="$input"
	local port=""
	local socket=""

	if [[ "$input" =~ ^localhost:(/.*)$ ]]; then
		host="localhost"
		socket="${BASH_REMATCH[1]}"
	elif [[ "$input" =~ ^([^:]+):([0-9]+)$ ]]; then
		host="${BASH_REMATCH[1]}"
		port="${BASH_REMATCH[2]}"
	fi

	printf '%s\n%s\n%s\n' "$host" "$port" "$socket"
}

recreate_db_via_php() {
	local db_name="$1"
	local db_user="$2"
	local db_password="$3"
	local db_host="$4"
	local db_port="$5"
	local db_socket="$6"

	php -r '
		[$dbName, $dbUser, $dbPass, $dbHost, $dbPort, $dbSocket] = array_slice($argv, 1);
		mysqli_report(MYSQLI_REPORT_OFF);
		$mysqli = mysqli_init();
		if (! $mysqli) {
			fwrite(STDERR, "Could not initialize mysqli.\n");
			exit(1);
		}
		$port = "" === $dbPort ? 0 : (int) $dbPort;
		$socket = "" === $dbSocket ? null : $dbSocket;
		if (! @mysqli_real_connect($mysqli, $dbHost, $dbUser, $dbPass, null, $port, $socket)) {
			fwrite(STDERR, mysqli_connect_error() . "\n");
			exit(1);
		}
		$escaped = str_replace("`", "``", $dbName);
		if (! mysqli_query($mysqli, "DROP DATABASE IF EXISTS `" . $escaped . "`")) {
			fwrite(STDERR, mysqli_error($mysqli) . "\n");
			exit(1);
		}
		if (! mysqli_query($mysqli, "CREATE DATABASE `" . $escaped . "`")) {
			fwrite(STDERR, mysqli_error($mysqli) . "\n");
			exit(1);
		}
	' "$db_name" "$db_user" "$db_password" "$db_host" "$db_port" "$db_socket"
}

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

BASE_DB_NAME="${BYLINE_TEST_DB_NAME:-$(extract_define "DB_NAME")}"
DB_USER="${BYLINE_TEST_DB_USER:-$(extract_define "DB_USER")}"
DB_PASSWORD="${BYLINE_TEST_DB_PASSWORD:-$(extract_define "DB_PASSWORD")}"
DB_HOST="${BYLINE_TEST_DB_HOST:-$(extract_define "DB_HOST")}"

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
	$db_user = $argv[3];
	$db_password = $argv[4];
	$db_host = $argv[5];
	$multisite = $argv[6];
	$content = preg_replace(
		"/define\\(\\s*[\"\\x27]DB_NAME[\"\\x27]\\s*,\\s*[\"\\x27][^\"\\x27]*[\"\\x27]\\s*\\)/",
		"define( \x27DB_NAME\x27, \x27{$db_name}\x27 )",
		$content,
		1
	);
	$content = preg_replace(
		"/define\\(\\s*[\"\\x27]DB_USER[\"\\x27]\\s*,\\s*[\"\\x27][^\"\\x27]*[\"\\x27]\\s*\\)/",
		"define( \x27DB_USER\x27, \x27{$db_user}\x27 )",
		$content,
		1
	);
	$content = preg_replace(
		"/define\\(\\s*[\"\\x27]DB_PASSWORD[\"\\x27]\\s*,\\s*[\"\\x27][^\"\\x27]*[\"\\x27]\\s*\\)/",
		"define( \x27DB_PASSWORD\x27, \x27{$db_password}\x27 )",
		$content,
		1
	);
	$content = preg_replace(
		"/define\\(\\s*[\"\\x27]DB_HOST[\"\\x27]\\s*,\\s*[\"\\x27][^\"\\x27]*[\"\\x27]\\s*\\)/",
		"define( \x27DB_HOST\x27, \x27{$db_host}\x27 )",
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
	file_put_contents($argv[7], $content);
 ' "${DEFAULT_TESTS_DIR}/wp-tests-config.php" "${DB_NAME}" "${DB_USER}" "${DB_PASSWORD}" "${DB_HOST}" "${MULTISITE}" "${TESTS_RUN_DIR}/wp-tests-config.php"

DB_HOSTNAME=""
DB_PORT=""
DB_SOCKET=""
while IFS= read -r line; do
	if [[ -z "${DB_HOSTNAME}" ]]; then
		DB_HOSTNAME="${line}"
	elif [[ -z "${DB_PORT}" ]]; then
		DB_PORT="${line}"
	else
		DB_SOCKET="${line}"
	fi
done < <(parse_db_host "${DB_HOST}")

recreate_db_via_php "${DB_NAME}" "${DB_USER}" "${DB_PASSWORD}" "${DB_HOSTNAME}" "${DB_PORT}" "${DB_SOCKET}"

echo "Running ${MODE} integration tests with DB ${DB_NAME} and WP_TESTS_DIR=${TESTS_RUN_DIR}"

(
	cd "${PLUGIN_DIR}"
	WP_TESTS_DIR="${TESTS_RUN_DIR}" WP_MULTISITE="${MULTISITE}" composer test
)
