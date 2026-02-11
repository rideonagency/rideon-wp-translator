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

<style>
.rideon-translator-settings {
	max-width: 900px;
}
.rideon-translator-settings .form-table th {
	width: 200px;
	padding: 20px 10px 20px 0;
}
.rideon-translator-settings .form-table td {
	padding: 15px 10px;
}
#rideon_model_info {
	margin-top: 12px;
	padding: 15px;
	background: #f0f6fc;
	border-left: 4px solid #2271b1;
	border-radius: 4px;
	box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
#rideon_model_info strong {
	color: #1d2327;
	font-size: 14px;
	display: block;
	margin-bottom: 8px;
}
#rideon_model_info p {
	margin: 8px 0 0 0;
	font-size: 13px;
	line-height: 1.6;
	color: #50575e;
}
#rideon_model_info p strong {
	display: inline;
	color: #2271b1;
	font-weight: 600;
}
</style>

<div class="wrap rideon-translator-settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	
	<form action="options.php" method="post">
		<?php
		settings_fields( 'rideon_translator_settings' );
		do_settings_sections( 'rideon-translator' );
		submit_button( __( 'Save Settings', 'rideon-wp-translator' ) );
		?>
	</form>
</div>
