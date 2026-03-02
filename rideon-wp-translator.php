<?php
/**
 * Plugin Name: Ride On WP Translator
 * Plugin URI: https://rideonagency.com/
 * Description: Translate WordPress posts automatically from one language to another using OpenAI API.
 * Version: 1.0.0
 * Author: Ride On Agency
 * Author URI: https://rideonagency.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rideon-wp-translator
 * Domain Path: /languages
 * Network: false
 *
 * @package RideOn_WP_Translator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'RIDEON_TRANSLATOR_VERSION', '1.0.0' );
define( 'RIDEON_TRANSLATOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RIDEON_TRANSLATOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RIDEON_TRANSLATOR_PLUGIN_FILE', __FILE__ );

/**
 * Main plugin class
 */
class RideOn_WP_Translator {

	/**
	 * Single instance of the class
	 *
	 * @var RideOn_WP_Translator
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return RideOn_WP_Translator
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
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load plugin dependencies
	 */
	private function load_dependencies() {
		require_once RIDEON_TRANSLATOR_PLUGIN_DIR . 'includes/class-openai-client.php';
		require_once RIDEON_TRANSLATOR_PLUGIN_DIR . 'includes/class-translator.php';
		require_once RIDEON_TRANSLATOR_PLUGIN_DIR . 'includes/class-admin.php';
		require_once RIDEON_TRANSLATOR_PLUGIN_DIR . 'includes/class-post-handler.php';
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		
		// Initialize admin
		if ( is_admin() ) {
			RideOn_Translator_Admin::get_instance();
			RideOn_Translator_Post_Handler::get_instance();
		}
	}

	/**
	 * Load plugin textdomain
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'rideon-wp-translator',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on post edit pages and settings page
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php', 'settings_page_rideon-translator' ), true ) ) {
			return;
		}

		// Enqueue CSS
		wp_enqueue_style(
			'rideon-translator-admin',
			RIDEON_TRANSLATOR_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			RIDEON_TRANSLATOR_VERSION
		);

		// Enqueue JavaScript
		wp_enqueue_script(
			'rideon-translator-admin',
			RIDEON_TRANSLATOR_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			RIDEON_TRANSLATOR_VERSION,
			true
		);

		// Localize script for AJAX
		wp_localize_script(
			'rideon-translator-admin',
			'rideonTranslator',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'rideon_translator_nonce' ),
				'debug'   => (bool) get_option( 'rideon_translator_enable_debug_log', false ),
				'i18n'    => array(
					'translating' => __( 'Translating...', 'rideon-wp-translator' ),
					'success'     => __( 'Translation completed successfully!', 'rideon-wp-translator' ),
					'error'       => __( 'An error occurred during translation.', 'rideon-wp-translator' ),
				),
			)
		);
	}
}

/**
 * Activation hook
 */
function rideon_translator_activate() {
	// Set default options if they don't exist
	if ( ! get_option( 'rideon_translator_model' ) ) {
		update_option( 'rideon_translator_model', 'gpt-3.5-turbo' );
	}
	if ( ! get_option( 'rideon_translator_default_source_lang' ) ) {
		update_option( 'rideon_translator_default_source_lang', 'it' );
	}
	if ( ! get_option( 'rideon_translator_default_target_lang' ) ) {
		update_option( 'rideon_translator_default_target_lang', 'en' );
	}
}
register_activation_hook( __FILE__, 'rideon_translator_activate' );

/**
 * Deactivation hook
 */
function rideon_translator_deactivate() {
	// Clean up if needed
}
register_deactivation_hook( __FILE__, 'rideon_translator_deactivate' );

/**
 * Initialize the plugin
 */
function rideon_translator_init() {
	return RideOn_WP_Translator::get_instance();
}

// Start the plugin
rideon_translator_init();
