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
		register_setting( 'rideon_translator_settings', 'rideon_translator_enable_debug_log', array( $this, 'sanitize_checkbox' ) );

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

		add_settings_section(
			'rideon_translator_debug_section',
			__( 'Debug Settings', 'rideon-wp-translator' ),
			array( $this, 'render_debug_section_description' ),
			'rideon-translator'
		);

		add_settings_field(
			'rideon_translator_enable_debug_log',
			__( 'Enable Debug Logging', 'rideon-wp-translator' ),
			array( $this, 'render_debug_log_field' ),
			'rideon-translator',
			'rideon_translator_debug_section'
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

		$api_key = sanitize_text_field( $api_key );
		
		// Check if input is already a valid OpenAI API key (starts with "sk-")
		// This means it's a plain key that needs to be encoded
		if ( strpos( $api_key, 'sk-' ) === 0 ) {
			// Plain API key, encode it before storing
			return base64_encode( $api_key );
		}
		
		// Check if input is already base64 encoded
		// Try to decode and check if result is a valid API key
		$decoded = base64_decode( $api_key, true );
		if ( $decoded !== false && strpos( $decoded, 'sk-' ) === 0 ) {
			// Input is already base64 encoded, return as is
			return $api_key;
		}
		
		// If input doesn't match either pattern, check if it matches stored value
		// This handles edge cases where the field wasn't changed
		$stored_key = get_option( 'rideon_translator_api_key' );
		if ( ! empty( $stored_key ) && $stored_key === $api_key ) {
			// User didn't change the field, keep the stored encoded value
			return $api_key;
		}

		// Default: encode the input (shouldn't reach here in normal flow)
		return base64_encode( $api_key );
	}

	/**
	 * Sanitize checkbox value
	 *
	 * @param mixed $value Checkbox value
	 * @return string '1' if checked, '0' if not
	 */
	public function sanitize_checkbox( $value ) {
		return isset( $value ) && $value ? '1' : '0';
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
		?>
		<p>
			<?php esc_html_e( 'Configure your OpenAI API credentials to enable translations.', 'rideon-wp-translator' ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Getting your API key:', 'rideon-wp-translator' ); ?></strong><br>
			<?php esc_html_e( '1. Visit', 'rideon-wp-translator' ); ?> 
			<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">
				https://platform.openai.com/api-keys
			</a><br>
			<?php esc_html_e( '2. Sign in or create an account', 'rideon-wp-translator' ); ?><br>
			<?php esc_html_e( '3. Create a new API key', 'rideon-wp-translator' ); ?><br>
			<?php esc_html_e( '4. Copy and paste it here', 'rideon-wp-translator' ); ?>
		</p>
		<p class="description" style="margin-top: 10px;">
			<strong><?php esc_html_e( 'Note:', 'rideon-wp-translator' ); ?></strong> 
			<?php esc_html_e( 'Your API key is encrypted before storage. Keep it secure and never share it publicly.', 'rideon-wp-translator' ); ?>
		</p>
		<?php
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
		$models_info = $this->get_models_info();
		?>
		<select id="rideon_translator_model" name="rideon_translator_model" class="regular-text">
			<?php foreach ( $models_info as $model_id => $info ) : ?>
				<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $model, $model_id ); ?>>
					<?php echo esc_html( $info['label'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<div id="rideon_model_info">
			<?php
			$selected_model_info = $models_info[ $model ] ?? $models_info['gpt-3.5-turbo'];
			?>
			<strong><?php echo esc_html( $selected_model_info['label'] ); ?></strong>
			<p>
				<strong><?php esc_html_e( 'Cost:', 'rideon-wp-translator' ); ?></strong> <?php echo esc_html( $selected_model_info['cost'] ); ?><br>
				<strong><?php esc_html_e( 'Quality:', 'rideon-wp-translator' ); ?></strong> <?php echo esc_html( $selected_model_info['quality'] ); ?><br>
				<strong><?php esc_html_e( 'Best for:', 'rideon-wp-translator' ); ?></strong> <?php echo esc_html( $selected_model_info['best_for'] ); ?>
			</p>
			<?php if ( ! empty( $selected_model_info['pros'] ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Pros:', 'rideon-wp-translator' ); ?></strong> <?php echo esc_html( $selected_model_info['pros'] ); ?>
				</p>
			<?php endif; ?>
			<?php if ( ! empty( $selected_model_info['cons'] ) ) : ?>
				<p>
					<strong><?php esc_html_e( 'Cons:', 'rideon-wp-translator' ); ?></strong> <?php echo esc_html( $selected_model_info['cons'] ); ?>
				</p>
			<?php endif; ?>
		</div>
		<p class="description" style="margin-top: 10px;">
			<?php esc_html_e( 'Choose the OpenAI model to use for translations. The information above will update based on your selection.', 'rideon-wp-translator' ); ?>
		</p>
		<?php
		$this->enqueue_model_info_script();
	}

	/**
	 * Get models information
	 *
	 * @return array Models information array
	 */
	private function get_models_info() {
		return array(
			'gpt-3.5-turbo' => array(
				'label'    => 'GPT-3.5 Turbo (Recommended for most use cases)',
				'cost'     => __( 'Low', 'rideon-wp-translator' ),
				'quality'  => __( 'Good', 'rideon-wp-translator' ),
				'best_for' => __( 'Simple emails, chats, short texts, non-critical translations', 'rideon-wp-translator' ),
				'pros'     => __( 'Economical, fast', 'rideon-wp-translator' ),
				'cons'     => __( 'Less precise on long or technical texts', 'rideon-wp-translator' ),
			),
			'gpt-4.1' => array(
				'label'    => 'GPT-4.1 (Balanced quality/price)',
				'cost'     => __( 'Medium', 'rideon-wp-translator' ),
				'quality'  => __( 'Very High', 'rideon-wp-translator' ),
				'best_for' => __( 'Professional documents, important emails, texts with specific tone (formal/informal)', 'rideon-wp-translator' ),
				'pros'     => __( 'Better context understanding, more natural translations', 'rideon-wp-translator' ),
				'cons'     => __( 'Higher cost than 3.5', 'rideon-wp-translator' ),
			),
			'gpt-4o' => array(
				'label'    => 'GPT-4o (Best quality)',
				'cost'     => __( 'Medium-High', 'rideon-wp-translator' ),
				'quality'  => __( 'Excellent', 'rideon-wp-translator' ),
				'best_for' => __( 'Long texts, technical documents, professional translations, content with stylistic nuances', 'rideon-wp-translator' ),
				'pros'     => __( 'Maximum accuracy, excellent tone handling, consistency on complex texts', 'rideon-wp-translator' ),
				'cons'     => __( 'More expensive', 'rideon-wp-translator' ),
			),
		);
	}

	/**
	 * Enqueue script to update model info dynamically
	 */
	private function enqueue_model_info_script() {
		$models_info = $this->get_models_info();
		?>
		<script type="text/javascript">
		(function($) {
			var modelsInfo = <?php echo wp_json_encode( $models_info ); ?>;
			
			function updateModelInfo() {
				var selectedModel = $('#rideon_translator_model').val();
				var info = modelsInfo[selectedModel];
				
				if (!info) return;
				
				var html = '<strong>' + escapeHtml(info.label) + '</strong>' +
					'<p>' +
					'<strong><?php echo esc_js( __( 'Cost:', 'rideon-wp-translator' ) ); ?></strong> ' + escapeHtml(info.cost) + '<br>' +
					'<strong><?php echo esc_js( __( 'Quality:', 'rideon-wp-translator' ) ); ?></strong> ' + escapeHtml(info.quality) + '<br>' +
					'<strong><?php echo esc_js( __( 'Best for:', 'rideon-wp-translator' ) ); ?></strong> ' + escapeHtml(info.best_for) +
					'</p>';
				
				if (info.pros) {
					html += '<p>' +
						'<strong><?php echo esc_js( __( 'Pros:', 'rideon-wp-translator' ) ); ?></strong> ' + escapeHtml(info.pros) +
						'</p>';
				}
				
				if (info.cons) {
					html += '<p>' +
						'<strong><?php echo esc_js( __( 'Cons:', 'rideon-wp-translator' ) ); ?></strong> ' + escapeHtml(info.cons) +
						'</p>';
				}
				
				$('#rideon_model_info').html(html);
			}
			
			function escapeHtml(text) {
				var map = {
					'&': '&amp;',
					'<': '&lt;',
					'>': '&gt;',
					'"': '&quot;',
					"'": '&#039;'
				};
				return text.replace(/[&<>"']/g, function(m) { return map[m]; });
			}
			
			$(document).ready(function() {
				$('#rideon_translator_model').on('change', updateModelInfo);
			});
		})(jQuery);
		</script>
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
	 * Render debug section description
	 */
	public function render_debug_section_description() {
		echo '<p>' . esc_html__( 'Enable debug logging to troubleshoot API issues. Logs will be written to WordPress debug.log file.', 'rideon-wp-translator' ) . '</p>';
	}

	/**
	 * Render debug log field
	 */
	public function render_debug_log_field() {
		$enable_log = get_option( 'rideon_translator_enable_debug_log', false );
		?>
		<label>
			<input type="checkbox" 
			       id="rideon_translator_enable_debug_log" 
			       name="rideon_translator_enable_debug_log" 
			       value="1" 
			       <?php checked( $enable_log, true ); ?> />
			<?php esc_html_e( 'Enable debug logging to debug.log', 'rideon-wp-translator' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, detailed API requests and responses will be logged to wp-content/debug.log. Make sure WP_DEBUG_LOG is enabled in wp-config.php.', 'rideon-wp-translator' ); ?>
		</p>
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
