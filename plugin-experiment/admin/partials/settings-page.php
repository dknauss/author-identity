<?php
/**
 * Settings page template.
 *
 * @package AuthorIdentity
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Configure the site-wide author identity that powers JSON-LD structured data, feed enrichment, and fediverse meta tags. Individual authors can override these values from their own profile page.', 'author-identity' ); ?>
	</p>
	<form method="post" action="options.php">
		<?php
		settings_fields( 'author_identity_group' );
		do_settings_sections( 'author-identity' );
		submit_button( __( 'Save Author Identity', 'author-identity' ) );
		?>
	</form>
</div>
