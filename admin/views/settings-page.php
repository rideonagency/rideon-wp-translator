<?php
/**
 * Settings page view
 *
 * @package RideOn_WP_Translator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<form action="options.php" method="post">
		<?php
		settings_fields( 'rideon_translator_settings' );
		do_settings_sections( 'rideon-translator' );
		submit_button( __( 'Save Settings', 'rideon-wp-translator' ) );
		?>
	</form>
</div>
