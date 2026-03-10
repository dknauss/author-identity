<?php
/**
 * Core plugin class.
 *
 * Bootstraps all plugin components and registers activation/deactivation hooks.
 *
 * @package AuthorIdentity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Author_Identity class (singleton).
 */
class Author_Identity {

	/**
	 * Single instance of the plugin.
	 *
	 * @var Author_Identity|null
	 */
	private static ?Author_Identity $instance = null;

	/**
	 * Returns — or creates — the single plugin instance.
	 *
	 * @return Author_Identity
	 */
	public static function instance(): Author_Identity {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — loads all sub-components.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Loads required class files.
	 */
	private function load_dependencies(): void {
		require_once AUTHOR_IDENTITY_PLUGIN_DIR . 'includes/class-admin.php';
		require_once AUTHOR_IDENTITY_PLUGIN_DIR . 'includes/class-structured-data.php';
		require_once AUTHOR_IDENTITY_PLUGIN_DIR . 'includes/class-meta-tags.php';
		require_once AUTHOR_IDENTITY_PLUGIN_DIR . 'includes/class-feed-enhancer.php';
	}

	/**
	 * Registers activation/deactivation hooks and bootstraps sub-components.
	 */
	private function init_hooks(): void {
		register_activation_hook( AUTHOR_IDENTITY_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( AUTHOR_IDENTITY_PLUGIN_FILE, array( $this, 'deactivate' ) );

		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Boot sub-components.
		new Author_Identity_Admin();
		new Author_Identity_Structured_Data();
		new Author_Identity_Meta_Tags();
		new Author_Identity_Feed_Enhancer();
	}

	/**
	 * Plugin activation callback.
	 * Sets a flag so an admin notice can welcome new users.
	 */
	public function activate(): void {
		add_option( 'author_identity_activated', true );
	}

	/**
	 * Plugin deactivation callback.
	 */
	public function deactivate(): void {
		// Nothing to clean up at deactivation time.
	}

	/**
	 * Loads plugin text domain for translations.
	 */
	public function load_plugin_textdomain(): void {
		load_plugin_textdomain(
			'author-identity',
			false,
			dirname( plugin_basename( AUTHOR_IDENTITY_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
