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

// WordPress test suite location — set via environment or use the first known
// local install path that exists.
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$candidate_dirs = array(
		'/tmp/byline-wp-tests',
		'/tmp/wordpress-tests-lib',
		rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib',
	);

	foreach ( $candidate_dirs as $candidate_dir ) {
		if ( file_exists( $candidate_dir . '/includes/functions.php' ) ) {
			$_tests_dir = $candidate_dir;
			break;
		}
	}
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
 * Set BYLINE_CAP_PLUGIN, BYLINE_PPA_PLUGIN, or BYLINE_HM_PLUGIN to the absolute path of the
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

$_hm_plugin = getenv( 'BYLINE_HM_PLUGIN' );
if ( $_hm_plugin && file_exists( $_hm_plugin ) ) {
	tests_add_filter(
		'muplugins_loaded',
		static function () use ( $_hm_plugin ) {
			require_once $_hm_plugin;
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
