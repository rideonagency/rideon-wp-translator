<?php
/**
 * Translation metabox view
 *
 * @package RideOn_WP_Translator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$languages = array(
	'it' => __( 'Italian', 'rideon-wp-translator' ),
	'en' => __( 'English', 'rideon-wp-translator' ),
	'es' => __( 'Spanish', 'rideon-wp-translator' ),
	'fr' => __( 'French', 'rideon-wp-translator' ),
	'de' => __( 'German', 'rideon-wp-translator' ),
	'pt' => __( 'Portuguese', 'rideon-wp-translator' ),
	'ru' => __( 'Russian', 'rideon-wp-translator' ),
	'zh' => __( 'Chinese', 'rideon-wp-translator' ),
	'ja' => __( 'Japanese', 'rideon-wp-translator' ),
	'ko' => __( 'Korean', 'rideon-wp-translator' ),
	'ar' => __( 'Arabic', 'rideon-wp-translator' ),
);

$default_target_lang = get_option( 'rideon_translator_default_target_lang', 'en' );
$default_source_lang = get_option( 'rideon_translator_default_source_lang', 'it' );
?>

<div id="rideon-translator-metabox">
	<div class="rideon-translator-controls">
		<label for="rideon-translator-source-lang">
			<strong><?php esc_html_e( 'Source Language:', 'rideon-wp-translator' ); ?></strong>
		</label>
		<select id="rideon-translator-source-lang" class="widefat">
			<?php foreach ( $languages as $code => $name ) : ?>
				<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $default_source_lang, $code ); ?>>
					<?php echo esc_html( $name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description" style="margin-top: 5px;">
			<?php esc_html_e( 'Default can be changed in', 'rideon-wp-translator' ); ?> 
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=rideon-translator' ) ); ?>">
				<?php esc_html_e( 'Settings', 'rideon-wp-translator' ); ?>
			</a>
		</p>

		<label for="rideon-translator-target-lang" style="margin-top: 15px; display: block;">
			<strong><?php esc_html_e( 'Translate to:', 'rideon-wp-translator' ); ?></strong>
		</label>
		<select id="rideon-translator-target-lang" class="widefat">
			<option value=""><?php esc_html_e( 'Select language...', 'rideon-wp-translator' ); ?></option>
			<?php foreach ( $languages as $code => $name ) : ?>
				<option value="<?php echo esc_attr( $code ); ?>" <?php selected( 'en', $code ); ?>>
					<?php echo esc_html( $name ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<button type="button" 
		        id="rideon-translator-translate-btn" 
		        class="button button-primary button-large" 
		        style="margin-top: 15px; width: 100%;"
		        data-post-id="<?php echo esc_attr( $source_post_id ); ?>">
			<span class="rideon-translator-btn-text"><?php esc_html_e( 'Translate', 'rideon-wp-translator' ); ?></span>
			<span class="rideon-translator-spinner spinner" style="float: none; margin: 0 0 0 8px; visibility: hidden;"></span>
		</button>
	</div>

	<div id="rideon-translator-message" class="rideon-translator-message" style="display: none;"></div>
</div>
