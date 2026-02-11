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
?>

<div id="rideon-translator-metabox">
	<?php if ( ! empty( $translations ) ) : ?>
		<div class="rideon-translator-existing">
			<h4><?php esc_html_e( 'Existing Translations', 'rideon-wp-translator' ); ?></h4>
			<ul class="rideon-translator-translations-list">
				<?php foreach ( $translations as $lang_code => $translated_post_id ) : ?>
					<?php
					$translated_post = get_post( $translated_post_id );
					if ( $translated_post ) :
						?>
						<li>
							<strong><?php echo esc_html( $languages[ $lang_code ] ?? $lang_code ); ?>:</strong>
							<a href="<?php echo esc_url( get_edit_post_link( $translated_post_id ) ); ?>" target="_blank">
								<?php echo esc_html( $translated_post->post_title ); ?>
							</a>
						</li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<div class="rideon-translator-controls">
		<label for="rideon-translator-target-lang">
			<strong><?php esc_html_e( 'Translate to:', 'rideon-wp-translator' ); ?></strong>
		</label>
		<select id="rideon-translator-target-lang" class="widefat">
			<option value=""><?php esc_html_e( 'Select language...', 'rideon-wp-translator' ); ?></option>
			<?php foreach ( $languages as $code => $name ) : ?>
				<?php if ( ! isset( $translations[ $code ] ) ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $default_target_lang, $code ); ?>>
						<?php echo esc_html( $name ); ?>
					</option>
				<?php endif; ?>
			<?php endforeach; ?>
		</select>

		<button type="button" 
		        id="rideon-translator-translate-btn" 
		        class="button button-primary button-large" 
		        data-post-id="<?php echo esc_attr( $source_post_id ); ?>">
			<span class="rideon-translator-btn-text"><?php esc_html_e( 'Translate', 'rideon-wp-translator' ); ?></span>
			<span class="rideon-translator-spinner spinner" style="float: none; margin: 0 0 0 8px; visibility: hidden;"></span>
		</button>
	</div>

	<div id="rideon-translator-message" class="rideon-translator-message" style="display: none;"></div>
</div>
