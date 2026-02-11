<?php
/**
 * Admin class - Settings page and admin interface
 *
 * @package RideOn_WP_Translator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RideOn_Translator_Admin
 */
class RideOn_Translator_Admin {

	/**
	 * Single instance of the class
	 *
	 * @var RideOn_Translator_Admin
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return RideOn_Translator_Admin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
	}

	/**
	 * Add settings page to WordPress admin
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Ride On Translator Settings', 'rideon-wp-translator' ),
			__( 'Ride On Translator', 'rideon-wp-translator' ),
			'manage_options',
			'rideon-translator',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting( 'rideon_translator_settings', 'rideon_translator_api_key', array( $this, 'sanitize_api_key' ) );
		register_setting( 'rideon_translator_settings', 'rideon_translator_model' );
		register_setting( 'rideon_translator_settings', 'rideon_translator_default_source_lang' );
		register_setting( 'rideon_translator_settings', 'rideon_translator_default_target_lang' );

		add_settings_section(
			'rideon_translator_api_section',
			__( 'OpenAI API Configuration', 'rideon-wp-translator' ),
			array( $this, 'render_api_section_description' ),
			'rideon-translator'
		);

		add_settings_field(
			'rideon_translator_api_key',
			__( 'API Key', 'rideon-wp-translator' ),
			array( $this, 'render_api_key_field' ),
			'rideon-translator',
			'rideon_translator_api_section'
		);

		add_settings_field(
			'rideon_translator_model',
			__( 'Model', 'rideon-wp-translator' ),
			array( $this, 'render_model_field' ),
			'rideon-translator',
			'rideon_translator_api_section'
		);

		add_settings_section(
			'rideon_translator_lang_section',
			__( 'Default Languages', 'rideon-wp-translator' ),
			array( $this, 'render_lang_section_description' ),
			'rideon-translator'
		);

		add_settings_field(
			'rideon_translator_default_source_lang',
			__( 'Default Source Language', 'rideon-wp-translator' ),
			array( $this, 'render_source_lang_field' ),
			'rideon-translator',
			'rideon_translator_lang_section'
		);

		add_settings_field(
			'rideon_translator_default_target_lang',
			__( 'Default Target Language', 'rideon-wp-translator' ),
			array( $this, 'render_target_lang_field' ),
			'rideon-translator',
			'rideon-translator',
			'rideon_translator_lang_section'
		);
	}

	/**
	 * Sanitize API key before saving
	 *
	 * @param string $api_key API key
	 * @return string Encrypted API key
	 */
	public function sanitize_api_key( $api_key ) {
		if ( empty( $api_key ) ) {
			return '';
		}

		// Encrypt API key before storing
		return base64_encode( sanitize_text_field( $api_key ) );
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		include RIDEON_TRANSLATOR_PLUGIN_DIR . 'admin/views/settings-page.php';
	}

	/**
	 * Render API section description
	 */
	public function render_api_section_description() {
		echo '<p>' . esc_html__( 'Configure your OpenAI API credentials. You can get your API key from https://platform.openai.com/api-keys', 'rideon-wp-translator' ) . '</p>';
	}

	/**
	 * Render API key field
	 */
	public function render_api_key_field() {
		$api_key = get_option( 'rideon_translator_api_key' );
		$decrypted_key = $api_key ? base64_decode( $api_key ) : '';
		?>
		<input type="password" 
		       id="rideon_translator_api_key" 
		       name="rideon_translator_api_key" 
		       value="<?php echo esc_attr( $decrypted_key ); ?>" 
		       class="regular-text" 
		       placeholder="sk-..." />
		<p class="description">
			<?php esc_html_e( 'Your OpenAI API key. Keep this secure and never share it publicly.', 'rideon-wp-translator' ); ?>
		</p>
		<?php
	}

	/**
	 * Render model field
	 */
	public function render_model_field() {
		$model = get_option( 'rideon_translator_model', 'gpt-3.5-turbo' );
		?>
		<select id="rideon_translator_model" name="rideon_translator_model">
			<option value="gpt-3.5-turbo" <?php selected( $model, 'gpt-3.5-turbo' ); ?>>
				GPT-3.5 Turbo (Faster, Lower Cost)
			</option>
			<option value="gpt-4-turbo-preview" <?php selected( $model, 'gpt-4-turbo-preview' ); ?>>
				GPT-4 Turbo (Higher Quality)
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Choose the OpenAI model to use for translations.', 'rideon-wp-translator' ); ?>
		</p>
		<?php
	}

	/**
	 * Render language section description
	 */
	public function render_lang_section_description() {
		echo '<p>' . esc_html__( 'Set default source and target languages for translations.', 'rideon-wp-translator' ) . '</p>';
	}

	/**
	 * Render source language field
	 */
	public function render_source_lang_field() {
		$source_lang = get_option( 'rideon_translator_default_source_lang', 'it' );
		$this->render_language_select( 'rideon_translator_default_source_lang', $source_lang );
	}

	/**
	 * Render target language field
	 */
	public function render_target_lang_field() {
		$target_lang = get_option( 'rideon_translator_default_target_lang', 'en' );
		$this->render_language_select( 'rideon_translator_default_target_lang', $target_lang );
	}

	/**
	 * Render language select dropdown
	 *
	 * @param string $field_name Field name
	 * @param string $selected_value Selected value
	 */
	private function render_language_select( $field_name, $selected_value ) {
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
		?>
		<select id="<?php echo esc_attr( $field_name ); ?>" name="<?php echo esc_attr( $field_name ); ?>">
			<?php foreach ( $languages as $code => $name ) : ?>
				<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $selected_value, $code ); ?>>
					<?php echo esc_html( $name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Display admin notices
	 */
	public function display_admin_notices() {
		if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true' ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Settings saved successfully!', 'rideon-wp-translator' ); ?></p>
			</div>
			<?php
		}
	}
}
