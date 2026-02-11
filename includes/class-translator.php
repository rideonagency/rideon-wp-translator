<?php
/**
 * Translator class - Orchestrates translation process
 *
 * @package RideOn_WP_Translator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RideOn_Translator
 */
class RideOn_Translator {

	/**
	 * OpenAI client instance
	 *
	 * @var RideOn_Translator_OpenAI_Client
	 */
	private $openai_client;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->openai_client = new RideOn_Translator_OpenAI_Client();
	}

	/**
	 * Translate a WordPress post
	 *
	 * @param int    $post_id Post ID to translate
	 * @param string $target_lang Target language code
	 * @param string $source_lang Source language code (optional, will be detected if not provided)
	 * @return int|WP_Error Translated post ID or WP_Error on failure
	 */
	public function translate_post( $post_id, $target_lang, $source_lang = '' ) {
		// Get source post
		$source_post = get_post( $post_id );
		if ( ! $source_post ) {
			return new WP_Error( 'post_not_found', __( 'Source post not found.', 'rideon-wp-translator' ) );
		}

		// Detect source language if not provided
		if ( empty( $source_lang ) ) {
			$source_lang = get_option( 'rideon_translator_default_source_lang', 'it' );
		}

		// Extract post content
		$content = $this->extract_post_content( $source_post );

		// Translate title
		$translated_title = $this->translate_text( $content['title'], $source_lang, $target_lang );
		if ( is_wp_error( $translated_title ) ) {
			return $translated_title;
		}

		// Translate content
		$translated_content = $this->translate_text( $content['content'], $source_lang, $target_lang );
		if ( is_wp_error( $translated_content ) ) {
			return $translated_content;
		}

		// Translate excerpt if exists
		$translated_excerpt = '';
		if ( ! empty( $content['excerpt'] ) ) {
			$translated_excerpt_result = $this->translate_text( $content['excerpt'], $source_lang, $target_lang );
			if ( ! is_wp_error( $translated_excerpt_result ) ) {
				$translated_excerpt = $translated_excerpt_result;
			}
		}

		// Create translated post
		$translated_post_id = $this->create_translated_post(
			$source_post,
			array(
				'title'   => $translated_title,
				'content' => $translated_content,
				'excerpt' => $translated_excerpt,
			),
			$target_lang
		);

		if ( is_wp_error( $translated_post_id ) ) {
			return $translated_post_id;
		}

		return $translated_post_id;
	}

	/**
	 * Extract post content for translation
	 *
	 * @param WP_Post $post Post object
	 * @return array
	 */
	private function extract_post_content( $post ) {
		return array(
			'title'   => $post->post_title,
			'content' => $post->post_content,
			'excerpt' => $post->post_excerpt,
		);
	}

	/**
	 * Translate text using OpenAI
	 *
	 * @param string $text Text to translate
	 * @param string $source_lang Source language code
	 * @param string $target_lang Target language code
	 * @return string|WP_Error
	 */
	private function translate_text( $text, $source_lang, $target_lang ) {
		if ( empty( $text ) ) {
			return '';
		}

		// Sanitize language codes
		$source_lang = sanitize_text_field( $source_lang );
		$target_lang = sanitize_text_field( $target_lang );

		$response = $this->openai_client->translate( $text, $source_lang, $target_lang );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! isset( $response['translated_text'] ) || empty( $response['translated_text'] ) ) {
			return new WP_Error( 'empty_translation', __( 'Translation returned empty result.', 'rideon-wp-translator' ) );
		}

		return wp_kses_post( $response['translated_text'] );
	}

	/**
	 * Create translated post
	 *
	 * @param WP_Post $source_post Source post object
	 * @param array   $translated_content Translated content
	 * @param string  $target_lang Target language code
	 * @return int|WP_Error
	 */
	private function create_translated_post( $source_post, $translated_content, $target_lang ) {
		// Sanitize all content
		$post_data = array(
			'post_title'    => sanitize_text_field( $translated_content['title'] ),
			'post_content'  => wp_kses_post( $translated_content['content'] ),
			'post_excerpt'  => sanitize_textarea_field( $translated_content['excerpt'] ),
			'post_status'   => sanitize_text_field( $source_post->post_status ),
			'post_type'     => sanitize_text_field( $source_post->post_type ),
			'post_author'   => absint( $source_post->post_author ),
			'post_category' => array_map( 'absint', wp_get_post_categories( $source_post->ID ) ),
		);

		// Copy tags
		$tags = wp_get_post_tags( $source_post->ID, array( 'fields' => 'names' ) );
		if ( ! empty( $tags ) && is_array( $tags ) ) {
			$post_data['tags_input'] = array_map( 'sanitize_text_field', $tags );
		}

		$translated_post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $translated_post_id ) ) {
			return $translated_post_id;
		}

		// Copy featured image if exists
		$thumbnail_id = get_post_thumbnail_id( $source_post->ID );
		if ( $thumbnail_id ) {
			set_post_thumbnail( $translated_post_id, $thumbnail_id );
		}

		return $translated_post_id;
	}


	/**
	 * Get translations for a post without creating a new post
	 * Used for in-place translation in post editor
	 *
	 * @param int    $post_id Post ID to translate
	 * @param string $target_lang Target language code
	 * @param string $source_lang Source language code (optional, will use default if not provided)
	 * @return array|WP_Error Array with translated title, content, and excerpt or WP_Error on failure
	 */
	public function get_translations( $post_id, $target_lang, $source_lang = '' ) {
		// Get source post
		$source_post = get_post( $post_id );
		if ( ! $source_post ) {
			return new WP_Error( 'post_not_found', __( 'Source post not found.', 'rideon-wp-translator' ) );
		}

		// Detect source language if not provided
		if ( empty( $source_lang ) ) {
			$source_lang = get_option( 'rideon_translator_default_source_lang', 'it' );
		}

		// Extract post content
		$content = $this->extract_post_content( $source_post );

		// Translate title
		$translated_title = $this->translate_text( $content['title'], $source_lang, $target_lang );
		if ( is_wp_error( $translated_title ) ) {
			return $translated_title;
		}

		// Translate content
		$translated_content = $this->translate_text( $content['content'], $source_lang, $target_lang );
		if ( is_wp_error( $translated_content ) ) {
			return $translated_content;
		}

		// Translate excerpt if exists
		$translated_excerpt = '';
		if ( ! empty( $content['excerpt'] ) ) {
			$translated_excerpt_result = $this->translate_text( $content['excerpt'], $source_lang, $target_lang );
			if ( ! is_wp_error( $translated_excerpt_result ) ) {
				$translated_excerpt = $translated_excerpt_result;
			}
		}

		$result = array(
			'title'   => $translated_title,
			'content' => $translated_content,
			'excerpt' => $translated_excerpt,
		);

		// Log debug info if enabled
		if ( get_option( 'rideon_translator_enable_debug_log', false ) ) {
			error_log( '[RideOn Translator] get_translations result | Context: ' . wp_json_encode( array(
				'title_length'   => strlen( $result['title'] ),
				'content_length' => strlen( $result['content'] ),
				'excerpt_length' => strlen( $result['excerpt'] ),
				'title_preview'  => substr( $result['title'], 0, 50 ),
				'content_preview' => substr( $result['content'], 0, 100 ),
			) ) );
		}

		return $result;
	}
}
