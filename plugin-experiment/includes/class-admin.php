<?php
/**
 * Admin class — settings page and user profile fields.
 *
 * @package AuthorIdentity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles all wp-admin functionality for Author Identity.
 */
class Author_Identity_Admin {

	/**
	 * Option key used to store site-level author identity data.
	 */
	const OPTION_KEY = 'author_identity_settings';

	/**
	 * Registers WordPress hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'activation_notice' ) );

		// Per-user profile fields.
		add_action( 'show_user_profile', array( $this, 'render_user_profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'render_user_profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_profile_fields' ) );
	}

	// -------------------------------------------------------------------------
	// Settings page
	// -------------------------------------------------------------------------

	/**
	 * Adds the plugin settings page under the Settings menu.
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'Author Identity', 'author-identity' ),
			__( 'Author Identity', 'author-identity' ),
			'manage_options',
			'author-identity',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Registers settings, sections, and fields.
	 */
	public function register_settings(): void {
		register_setting(
			'author_identity_group',
			self::OPTION_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'author_identity_defaults',
			__( 'Site-Wide Author Defaults', 'author-identity' ),
			array( $this, 'render_section_defaults' ),
			'author-identity'
		);

		$fields = $this->get_site_fields();
		foreach ( $fields as $key => $label ) {
			add_settings_field(
				'author_identity_' . $key,
				$label,
				array( $this, 'render_field' ),
				'author-identity',
				'author_identity_defaults',
				array(
					'key'   => $key,
					'label' => $label,
				)
			);
		}
	}

	/**
	 * Returns the list of site-level fields as key => label pairs.
	 *
	 * @return array<string, string>
	 */
	private function get_site_fields(): array {
		return array(
			'name'               => __( 'Author Name', 'author-identity' ),
			'url'                => __( 'Author URL', 'author-identity' ),
			'email'              => __( 'Public E-mail', 'author-identity' ),
			'description'        => __( 'Bio / Description', 'author-identity' ),
			'job_title'          => __( 'Job Title', 'author-identity' ),
			'organization'       => __( 'Organization', 'author-identity' ),
			'organization_url'   => __( 'Organization URL', 'author-identity' ),
			'mastodon'           => __( 'Mastodon Profile URL', 'author-identity' ),
			'fediverse_creator'  => __( 'Fediverse Creator Handle (@user@domain)', 'author-identity' ),
			'twitter'            => __( 'X / Twitter Handle (@username)', 'author-identity' ),
			'linkedin'           => __( 'LinkedIn Profile URL', 'author-identity' ),
			'github'             => __( 'GitHub Profile URL', 'author-identity' ),
			'same_as'            => __( 'Additional Profile URLs (one per line)', 'author-identity' ),
		);
	}

	/**
	 * Renders the section description.
	 */
	public function render_section_defaults(): void {
		echo '<p>' . esc_html__( 'These values are used as fallbacks when a post author has not filled in their own profile fields.', 'author-identity' ) . '</p>';
	}

	/**
	 * Renders an individual settings field.
	 *
	 * @param array<string,string> $args Field arguments.
	 */
	public function render_field( array $args ): void {
		$options = get_option( self::OPTION_KEY, array() );
		$key     = $args['key'];
		$value   = $options[ $key ] ?? '';
		$id      = 'author_identity_' . $key;

		if ( 'description' === $key || 'same_as' === $key ) {
			printf(
				'<textarea id="%1$s" name="%2$s[%3$s]" rows="4" class="large-text">%4$s</textarea>',
				esc_attr( $id ),
				esc_attr( self::OPTION_KEY ),
				esc_attr( $key ),
				esc_textarea( $value )
			);
		} else {
			printf(
				'<input type="text" id="%1$s" name="%2$s[%3$s]" value="%4$s" class="regular-text" />',
				esc_attr( $id ),
				esc_attr( self::OPTION_KEY ),
				esc_attr( $key ),
				esc_attr( $value )
			);
		}
	}

	/**
	 * Sanitizes site-level settings before saving.
	 *
	 * @param mixed $input Raw POST data.
	 * @return array<string,string>
	 */
	public function sanitize_settings( $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$sanitized = array();
		$url_keys  = array( 'url', 'organization_url', 'mastodon', 'linkedin', 'github' );

		foreach ( $this->get_site_fields() as $key => $label ) {
			$raw = $input[ $key ] ?? '';

			if ( in_array( $key, $url_keys, true ) ) {
				$sanitized[ $key ] = esc_url_raw( $raw );
			} elseif ( 'email' === $key ) {
				$sanitized[ $key ] = sanitize_email( $raw );
			} elseif ( 'same_as' === $key || 'description' === $key ) {
				$sanitized[ $key ] = sanitize_textarea_field( $raw );
			} else {
				$sanitized[ $key ] = sanitize_text_field( $raw );
			}
		}

		return $sanitized;
	}

	/**
	 * Renders the plugin settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		require AUTHOR_IDENTITY_PLUGIN_DIR . 'admin/partials/settings-page.php';
	}

	// -------------------------------------------------------------------------
	// Activation notice
	// -------------------------------------------------------------------------

	/**
	 * Shows a one-time welcome notice after activation.
	 */
	public function activation_notice(): void {
		if ( ! get_option( 'author_identity_activated' ) ) {
			return;
		}
		delete_option( 'author_identity_activated' );
		$url = admin_url( 'options-general.php?page=author-identity' );
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			sprintf(
				/* translators: %s: settings page URL */
				wp_kses(
					__( 'Author Identity is active. <a href="%s">Configure your author profile →</a>', 'author-identity' ),
					array( 'a' => array( 'href' => array() ) )
				),
				esc_url( $url )
			)
		);
	}

	// -------------------------------------------------------------------------
	// Per-user profile fields
	// -------------------------------------------------------------------------

	/**
	 * Returns the list of per-user profile field definitions.
	 *
	 * @return array<string, array{label: string, type: string}>
	 */
	private function get_user_fields(): array {
		return array(
			'author_identity_job_title'         => array( 'label' => __( 'Job Title', 'author-identity' ), 'type' => 'text' ),
			'author_identity_organization'      => array( 'label' => __( 'Organization', 'author-identity' ), 'type' => 'text' ),
			'author_identity_organization_url'  => array( 'label' => __( 'Organization URL', 'author-identity' ), 'type' => 'url' ),
			'author_identity_mastodon'          => array( 'label' => __( 'Mastodon Profile URL', 'author-identity' ), 'type' => 'url' ),
			'author_identity_fediverse_creator' => array( 'label' => __( 'Fediverse Creator Handle (@user@domain)', 'author-identity' ), 'type' => 'text' ),
			'author_identity_twitter'           => array( 'label' => __( 'X / Twitter Handle (@username)', 'author-identity' ), 'type' => 'text' ),
			'author_identity_linkedin'          => array( 'label' => __( 'LinkedIn Profile URL', 'author-identity' ), 'type' => 'url' ),
			'author_identity_github'            => array( 'label' => __( 'GitHub Profile URL', 'author-identity' ), 'type' => 'url' ),
			'author_identity_same_as'           => array( 'label' => __( 'Additional Profile URLs (one per line)', 'author-identity' ), 'type' => 'textarea' ),
		);
	}

	/**
	 * Renders extra fields on the user profile / edit-user screens.
	 *
	 * @param \WP_User $user The user being edited.
	 */
	public function render_user_profile_fields( \WP_User $user ): void {
		wp_nonce_field( 'author_identity_user_fields', 'author_identity_nonce' );
		?>
		<h2><?php esc_html_e( 'Author Identity', 'author-identity' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'These values override the site-wide Author Identity defaults for posts written by this user.', 'author-identity' ); ?>
		</p>
		<table class="form-table" role="presentation">
		<?php foreach ( $this->get_user_fields() as $meta_key => $field ) : ?>
			<tr>
				<th scope="row">
					<label for="<?php echo esc_attr( $meta_key ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
				</th>
				<td>
					<?php if ( 'textarea' === $field['type'] ) : ?>
						<textarea
							id="<?php echo esc_attr( $meta_key ); ?>"
							name="<?php echo esc_attr( $meta_key ); ?>"
							rows="4"
							class="large-text"
						><?php echo esc_textarea( get_user_meta( $user->ID, $meta_key, true ) ); ?></textarea>
					<?php else : ?>
						<input
							type="text"
							id="<?php echo esc_attr( $meta_key ); ?>"
							name="<?php echo esc_attr( $meta_key ); ?>"
							value="<?php echo esc_attr( get_user_meta( $user->ID, $meta_key, true ) ); ?>"
							class="regular-text"
						/>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</table>
		<?php
	}

	/**
	 * Saves the per-user Author Identity profile fields.
	 *
	 * @param int $user_id The ID of the user being saved.
	 */
	public function save_user_profile_fields( int $user_id ): void {
		if (
			! isset( $_POST['author_identity_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['author_identity_nonce'] ) ), 'author_identity_user_fields' )
		) {
			return;
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$url_keys = array(
			'author_identity_organization_url',
			'author_identity_mastodon',
			'author_identity_linkedin',
			'author_identity_github',
		);

		foreach ( $this->get_user_fields() as $meta_key => $field ) {
			$raw = isset( $_POST[ $meta_key ] ) ? wp_unslash( $_POST[ $meta_key ] ) : '';

			if ( in_array( $meta_key, $url_keys, true ) ) {
				$value = esc_url_raw( $raw );
			} elseif ( 'textarea' === $field['type'] ) {
				$value = sanitize_textarea_field( $raw );
			} else {
				$value = sanitize_text_field( $raw );
			}

			update_user_meta( $user_id, $meta_key, $value );
		}
	}
}
