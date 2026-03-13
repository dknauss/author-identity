<?php
/**
 * PHPUnit bootstrap file for the Byline Feed plugin.
 *
 * @package Byline_Feed
 */

// Composer autoloader (for Yoast PHPUnit Polyfills).
$composer_autoload = dirname( __DIR__, 2 ) . '/vendor/autoload.php';
if ( file_exists( $composer_autoload ) ) {
	require_once $composer_autoload;
}

// WordPress test suite location — set via environment or use default.
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Verify the test suite exists.
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find WordPress test suite at {$_tests_dir}.\n";
	echo "Run bin/install-wp-tests.sh to set it up.\n";
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Optionally load integration plugins before the main plugin bootstraps.
 *
 * Set BYLINE_CAP_PLUGIN or BYLINE_PPA_PLUGIN to the absolute path of the
 * plugin's main file to activate real-plugin integration mode in CI.
 */
$_cap_plugin = getenv( 'BYLINE_CAP_PLUGIN' );
if ( $_cap_plugin && file_exists( $_cap_plugin ) ) {
	tests_add_filter(
		'muplugins_loaded',
		static function () use ( $_cap_plugin ) {
			require_once $_cap_plugin;
		},
		1
	);
}

$_ppa_plugin = getenv( 'BYLINE_PPA_PLUGIN' );
if ( $_ppa_plugin && file_exists( $_ppa_plugin ) ) {
	tests_add_filter(
		'muplugins_loaded',
		static function () use ( $_ppa_plugin ) {
			require_once $_ppa_plugin;
		},
		1
	);
}

/**
 * Manually load the plugin for testing.
 */
tests_add_filter( 'muplugins_loaded', function () {
	require dirname( __DIR__, 2 ) . '/byline-feed.php';
} );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
