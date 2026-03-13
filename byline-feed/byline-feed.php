<?php
/**
 * Plugin Name:       Byline Feed
 * Plugin URI:        https://github.com/dknauss/Author-Identity
 * Description:       Enriches RSS, Atom, and JSON feeds with structured author identity metadata using the Byline extension vocabulary.
 * Version:           0.1.0-dev
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Dan Knauss
 * Author URI:        https://developer.wordpress.org/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       byline-feed
 * Domain Path:       /languages
 *
 * @package Byline_Feed
 */

defined( 'ABSPATH' ) || exit;

define( 'BYLINE_FEED_VERSION', '0.1.0-dev' );
define( 'BYLINE_FEED_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BYLINE_FEED_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BYLINE_FEED_PLUGIN_FILE', __FILE__ );

// Load the public API and hook registration.
require_once BYLINE_FEED_PLUGIN_DIR . 'inc/namespace.php';
require_once BYLINE_FEED_PLUGIN_DIR . 'inc/author-meta.php';

// Load the adapter interface.
require_once BYLINE_FEED_PLUGIN_DIR . 'inc/interface-adapter.php';

// Load all adapter implementations.
require_once BYLINE_FEED_PLUGIN_DIR . 'inc/class-adapter-core.php';
require_once BYLINE_FEED_PLUGIN_DIR . 'inc/class-adapter-cap.php';
require_once BYLINE_FEED_PLUGIN_DIR . 'inc/class-adapter-ppa.php';

// Load output layers.
require_once BYLINE_FEED_PLUGIN_DIR . 'inc/feed-common.php';
require_once BYLINE_FEED_PLUGIN_DIR . 'inc/feed-rss2.php';
require_once BYLINE_FEED_PLUGIN_DIR . 'inc/feed-atom.php';
require_once BYLINE_FEED_PLUGIN_DIR . 'inc/feed-json.php';
require_once BYLINE_FEED_PLUGIN_DIR . 'inc/perspective.php';

// Bootstrap.
add_action( 'plugins_loaded', 'Byline_Feed\\bootstrap' );
