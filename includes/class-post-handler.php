<?php
/**
 * Post Handler class - Metabox and AJAX handling
 *
 * @package RideOn_WP_Translator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RideOn_Translator_Post_Handler
 */
class RideOn_Translator_Post_Handler {

	/**
	 * Single instance of the class
	 *
	 * @var RideOn_Translator_Post_Handler
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return RideOn_Translator_Post_Handler
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
		add_action( 'add_meta_boxes', array( $this, 'add_translation_metabox' ) );
		add_action( 'wp_ajax_rideon_translate_post', array( $this, 'handle_ajax_translation' ) );
	}

	/**
	 * Add translation metabox to post edit screen
	 *
	 * @param string $post_type Post type
	 */
	public function add_translation_metabox( $post_type ) {
		// Only add to post types that support translations
		$supported_types = apply_filters( 'rideon_translator_supported_post_types', array( 'post' ) );
		
		if ( ! in_array( $post_type, $supported_types, true ) ) {
			return;
		}

		add_meta_box(
			'rideon_translator_metabox',
			__( 'RideOn Translator', 'rideon-wp-translator' ),
			array( $this, 'render_metabox' ),
			$post_type,
			'side',
			'default'
		);
	}

	/**
	 * Render translation metabox
	 *
	 * @param WP_Post $post Post object
	 */
	public function render_metabox( $post ) {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		// Check if API key is configured
		$api_key = get_option( 'rideon_translator_api_key' );
		if ( empty( $api_key ) ) {
			echo '<p>' . esc_html__( 'Please configure your OpenAI API key in', 'rideon-wp-translator' ) . ' <a href="' . esc_url( admin_url( 'options-general.php?page=rideon-translator' ) ) . '">' . esc_html__( 'Settings', 'rideon-wp-translator' ) . '</a>.</p>';
			return;
		}

		// Get existing translations
		$translations = get_post_meta( $post->ID, '_translations', true );
		if ( ! is_array( $translations ) ) {
			$translations = array();
		}

		// Check if this post is a translation
		$translation_of = get_post_meta( $post->ID, '_translation_of', true );
		if ( $translation_of ) {
			$parent_translations = get_post_meta( $translation_of, '_translations', true );
			if ( is_array( $parent_translations ) ) {
				$translations = $parent_translations;
			}
			$source_post_id = $translation_of;
		} else {
			$source_post_id = $post->ID;
		}

		include RIDEON_TRANSLATOR_PLUGIN_DIR . 'admin/views/metabox-translate.php';
	}

	/**
	 * Handle AJAX translation request
	 */
	public function handle_ajax_translation() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rideon_translator_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'rideon-wp-translator' ) ) );
		}

		// Check user capability
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to translate posts.', 'rideon-wp-translator' ) ) );
		}

		// Get parameters
		$post_id     = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		$target_lang = isset( $_POST['target_lang'] ) ? sanitize_text_field( wp_unslash( $_POST['target_lang'] ) ) : '';

		if ( empty( $post_id ) || empty( $target_lang ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parameters.', 'rideon-wp-translator' ) ) );
		}

		// Check if post exists
		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => __( 'Post not found.', 'rideon-wp-translator' ) ) );
		}

		// Check user can edit this post
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this post.', 'rideon-wp-translator' ) ) );
		}

		// Perform translation
		$translator = new RideOn_Translator();
		$result     = $translator->translate_post( $post_id, $target_lang );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Get edit link for translated post
		$edit_link = get_edit_post_link( $result, 'raw' );

		wp_send_json_success(
			array(
				'message'   => __( 'Translation completed successfully!', 'rideon-wp-translator' ),
				'post_id'   => $result,
				'edit_link' => $edit_link,
			)
		);
	}
}
