#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

DB_CONTAINER_NAME="${BYLINE_TEST_DB_CONTAINER:-byline-feed-test-db}"
DB_IMAGE="${BYLINE_TEST_DB_IMAGE:-mysql:8.4}"
DB_NAME="${BYLINE_TEST_DB_NAME:-byline_feed_tests}"
DB_USER="${BYLINE_TEST_DB_USER:-root}"
DB_PASSWORD="${BYLINE_TEST_DB_PASSWORD:-root}"
DB_PORT="${BYLINE_TEST_DB_PORT:-33306}"
WP_VERSION="${BYLINE_TEST_WP_VERSION:-latest}"
WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/byline-wp-tests-docker}"
STARTED_CONTAINER=0

cleanup() {
	if [[ "${STARTED_CONTAINER}" == "1" ]]; then
		docker rm -f "${DB_CONTAINER_NAME}" >/dev/null 2>&1 || true
	fi
}
trap cleanup EXIT

if ! command -v docker >/dev/null 2>&1; then
	echo "Docker is required for bin/run-phpunit-local.sh." >&2
	exit 1
fi

if ! docker ps --format '{{.Names}}' | grep -qx "${DB_CONTAINER_NAME}"; then
	docker rm -f "${DB_CONTAINER_NAME}" >/dev/null 2>&1 || true
	docker run -d \
		--name "${DB_CONTAINER_NAME}" \
		-e MYSQL_ROOT_PASSWORD="${DB_PASSWORD}" \
		-e MYSQL_ROOT_HOST='%' \
		-p "127.0.0.1:${DB_PORT}:3306" \
		"${DB_IMAGE}" >/dev/null
	STARTED_CONTAINER=1
fi

echo "Waiting for MySQL container ${DB_CONTAINER_NAME} on 127.0.0.1:${DB_PORT}..."
for _ in $(seq 1 60); do
	if php -r '
		mysqli_report(MYSQLI_REPORT_OFF);
		[$host, $port, $password] = array_slice($argv, 1);
		$mysqli = mysqli_init();
		if ($mysqli && @mysqli_real_connect($mysqli, $host, "root", $password, null, (int) $port, null)) {
			exit(0);
		}
		exit(1);
	' "127.0.0.1" "${DB_PORT}" "${DB_PASSWORD}" >/dev/null 2>&1; then
		break
	fi
	sleep 1
done

if ! php -r '
	mysqli_report(MYSQLI_REPORT_OFF);
	[$host, $port, $password] = array_slice($argv, 1);
	$mysqli = mysqli_init();
	if ($mysqli && @mysqli_real_connect($mysqli, $host, "root", $password, null, (int) $port, null)) {
		exit(0);
	}
	exit(1);
' "127.0.0.1" "${DB_PORT}" "${DB_PASSWORD}" >/dev/null 2>&1; then
	echo "MySQL container did not become ready." >&2
	exit 1
fi

echo "Preparing WordPress test suite in ${WP_TESTS_DIR}..."
WP_TESTS_DIR="${WP_TESTS_DIR}" "${SCRIPT_DIR}/install-wp-tests.sh" "${DB_NAME}" "${DB_USER}" "${DB_PASSWORD}" "127.0.0.1:${DB_PORT}" "${WP_VERSION}"

echo "Running PHPUnit with disposable MySQL on 127.0.0.1:${DB_PORT}..."
WP_TESTS_DIR="${WP_TESTS_DIR}" \
	BYLINE_TEST_DB_NAME="${DB_NAME}" \
	BYLINE_TEST_DB_USER="${DB_USER}" \
	BYLINE_TEST_DB_PASSWORD="${DB_PASSWORD}" \
	BYLINE_TEST_DB_HOST="127.0.0.1:${DB_PORT}" \
	"${SCRIPT_DIR}/run-integration-tests.sh"
