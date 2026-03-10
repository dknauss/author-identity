<?php
/**
 * Plugin Name:       Author Identity
 * Plugin URI:        https://github.com/dknauss/Author-Identity
 * Description:       Structured author identity that travels with the work — across feeds, search, the fediverse, and AI — from one source of truth in WordPress.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Dan Knauss
 * Author URI:        https://github.com/dknauss
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       author-identity
 * Domain Path:       /languages
 *
 * @package AuthorIdentity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AUTHOR_IDENTITY_VERSION', '1.0.0' );
define( 'AUTHOR_IDENTITY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AUTHOR_IDENTITY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AUTHOR_IDENTITY_PLUGIN_FILE', __FILE__ );

require_once AUTHOR_IDENTITY_PLUGIN_DIR . 'includes/class-author-identity.php';

/**
 * Returns the main instance of Author_Identity.
 *
 * @return Author_Identity
 */
function author_identity(): Author_Identity {
	return Author_Identity::instance();
}

author_identity();
