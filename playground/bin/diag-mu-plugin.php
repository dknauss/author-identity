<?php
/**
 * Plugin Name: Playground diagnostics (temporary, issue #88)
 */
add_action( 'wp_head', static function () {
	global $wpdb;
	$p = get_post( 1 );
	$raw = $wpdb->get_var( "SELECT post_title FROM {$wpdb->posts} WHERE ID = 1" );
	printf(
		"<!-- diag pid=%s worker_req=%s active=%s byline_fn=%s has_filter=%s title_cache=%s title_db=%s seed_prio=%s ext_cache=%s did_init=%s qo=%s ts=%s -->\n",
		getmypid(),
		isset( $GLOBALS['diag_n'] ) ? 'y' : 'n',
		esc_html( wp_json_encode( get_option( 'active_plugins' ) ) ),
		function_exists( 'Byline_Feed\\bootstrap' ) ? 'y' : 'n',
		has_filter( 'byline_feed_authors' ) ? 'y' : 'n',
		esc_html( $p ? $p->post_title : '-' ),
		esc_html( (string) $raw ),
		var_export( has_action( 'init', 'byline_feed_playground_seed_demo_content' ), true ),
		wp_using_ext_object_cache() ? 'y' : 'n',
		did_action( 'init' ),
		esc_html( get_queried_object() instanceof WP_Post ? get_queried_object()->post_title : '-' ),
		microtime( true )
	);
}, 1 );
