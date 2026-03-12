#!/usr/bin/env bash
#
# Install WordPress test suite for PHPUnit testing.
#
# Usage: bin/install-wp-tests.sh [db-name] [db-user] [db-pass] [db-host] [wp-version] [skip-database-creation]
#
# Environment variables WP_TESTS_DIR and WP_CORE_DIR can override install paths.

if [ $# -lt 3 ]; then
	echo "Usage: $0 db-name db-user db-pass [db-host] [wp-version] [skip-database-creation]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo "$TMPDIR" | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

download() {
	if [ "$(which curl)" ]; then
		curl -fsSL "$1" > "$2"
	elif [ "$(which wget)" ]; then
		wget -nv -O "$2" "$1"
	fi
}

if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+\-(beta|RC)[0-9]+$ ]]; then
	WP_BRANCH=${WP_VERSION%\-*}
	WP_TESTS_TAG="branches/$WP_BRANCH"
elif [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
	WP_TESTS_TAG="branches/$WP_VERSION"
elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0-9]+ ]]; then
	if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0] ]]; then
		WP_TESTS_TAG="tags/${WP_VERSION%??}"
	else
		WP_TESTS_TAG="tags/$WP_VERSION"
	fi
elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
	WP_TESTS_TAG="trunk"
else
	# http is faster for downloading from the API.
	download http://api.wordpress.org/core/version-check/1.7/ /tmp/wp-latest.json
	grep -o '"version":"[^"]*"' /tmp/wp-latest.json | head -1 | sed 's/"version":"//;s/"//' > /tmp/wp-latest-version.txt
	LATEST_VERSION=$(cat /tmp/wp-latest-version.txt)
	if [[ -z "$LATEST_VERSION" ]]; then
		echo "Could not determine latest WordPress version."
		exit 1
	fi
	WP_TESTS_TAG="tags/$LATEST_VERSION"
fi

set -ex

install_wp() {
	if [ -d "$WP_CORE_DIR" ]; then
		return
	fi

	mkdir -p "$WP_CORE_DIR"

	if [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
		mkdir -p "$TMPDIR/wordpress-trunk"
		rm -rf "$TMPDIR/wordpress-trunk/*"
		svn export --quiet https://core.svn.wordpress.org/trunk "$TMPDIR/wordpress-trunk/wordpress"
		mv "$TMPDIR/wordpress-trunk/wordpress/"* "$WP_CORE_DIR"
	else
		if [ "$WP_VERSION" == 'latest' ]; then
			local ARCHIVE_NAME='latest'
		elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+ ]]; then
			local ARCHIVE_NAME="wordpress-$WP_VERSION"
		else
			local ARCHIVE_NAME="wordpress-$WP_VERSION"
		fi

		download "https://wordpress.org/${ARCHIVE_NAME}.tar.gz" "$TMPDIR/wordpress.tar.gz"
		tar --strip-components=1 -zxmf "$TMPDIR/wordpress.tar.gz" -C "$WP_CORE_DIR"
	fi

	download https://raw.githubusercontent.com/marber/wp-config-github/master/wp-tests-config-sample.php "$WP_CORE_DIR/wp-tests-config.php"
}

install_test_suite() {
	# Portable path for in-place sed.
	local ioption='-i'
	if [[ $(uname -s) == 'Darwin' ]]; then
		ioption='-i.bak'
	fi

	if [ ! -d "$WP_TESTS_DIR" ]; then
		mkdir -p "$WP_TESTS_DIR"
		svn export --quiet --ignore-externals "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/" "$WP_TESTS_DIR/includes"
		svn export --quiet --ignore-externals "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/" "$WP_TESTS_DIR/data"
	fi

	if [ ! -f wp-tests-config.php ]; then
		download "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"
		sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR/wp-tests-config.php"
		sed $ioption "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed $ioption "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed $ioption "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed $ioption "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR/wp-tests-config.php"
	fi
}

recreate_db() {
	shopt -s nocasematch
	if [[ $1 =~ ^(y|yes)$ ]]; then
		return
	fi
	shopt -u nocasematch

	mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS" --host="$DB_HOST" --protocol=tcp 2>/dev/null || true
}

install_wp
install_test_suite

if [ "$SKIP_DB_CREATE" != "true" ]; then
	recreate_db "$SKIP_DB_CREATE"
fi
