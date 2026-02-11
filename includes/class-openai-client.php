<?php
/**
 * OpenAI API Client
 *
 * @package RideOn_WP_Translator
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RideOn_Translator_OpenAI_Client
 */
class RideOn_Translator_OpenAI_Client {

	/**
	 * OpenAI API endpoint
	 *
	 * @var string
	 */
	private $api_endpoint = 'https://api.openai.com/v1/chat/completions';

	/**
	 * API key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Model to use
	 *
	 * @var string
	 */
	private $model;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->api_key = $this->get_api_key();
		$this->model   = get_option( 'rideon_translator_model', 'gpt-3.5-turbo' );
	}

	/**
	 * Get API key (decrypted)
	 *
	 * @return string
	 */
	private function get_api_key() {
		$encrypted_key = get_option( 'rideon_translator_api_key' );
		if ( ! $encrypted_key ) {
			return '';
		}
		
		// Simple encryption/decryption using WordPress functions
		// In production, consider using more secure methods
		$decoded = base64_decode( $encrypted_key, true );
		
		// If decoding fails, the key might not be encoded or might be double-encoded
		// Try decoding multiple times if needed (handles double-encoding cases)
		if ( $decoded === false || strpos( $decoded, 'sk-' ) !== 0 ) {
			$current_key = $encrypted_key;
			$attempts = 0;
			while ( $attempts < 3 ) {
				$test_decode = base64_decode( $current_key, true );
				if ( $test_decode === false || $test_decode === $current_key ) {
					break;
				}
				if ( strpos( $test_decode, 'sk-' ) === 0 ) {
					return $test_decode;
				}
				$current_key = $test_decode;
				$attempts++;
			}
		}
		
		// If decoded key is valid, return it
		if ( $decoded !== false && strpos( $decoded, 'sk-' ) === 0 ) {
			return $decoded;
		}
		
		// If stored key is already a plain API key (shouldn't happen, but handle it)
		if ( strpos( $encrypted_key, 'sk-' ) === 0 ) {
			return $encrypted_key;
		}
		
		// Return decoded value or empty string
		return $decoded !== false ? $decoded : '';
	}

	/**
	 * Translate text from source language to target language
	 *
	 * @param string $text Text to translate
	 * @param string $source_lang Source language code
	 * @param string $target_lang Target language code
	 * @return array|WP_Error Response array with translated text or WP_Error on failure
	 */
	public function translate( $text, $source_lang, $target_lang ) {
		$this->log_debug( 'Translation request started', array(
			'source_lang' => $source_lang,
			'target_lang' => $target_lang,
			'text_length' => strlen( $text ),
		) );

		if ( empty( $this->api_key ) ) {
			$this->log_debug( 'Translation failed: API key not configured' );
			return new WP_Error( 'no_api_key', __( 'OpenAI API key is not configured.', 'rideon-wp-translator' ) );
		}

		if ( empty( $text ) ) {
			$this->log_debug( 'Translation failed: Empty text provided' );
			return new WP_Error( 'empty_text', __( 'Text to translate is empty.', 'rideon-wp-translator' ) );
		}

		$prompt = $this->build_translation_prompt( $text, $source_lang, $target_lang );

		$response = $this->make_api_request( $prompt );

		if ( is_wp_error( $response ) ) {
			$this->log_debug( 'Translation failed', array(
				'error_code' => $response->get_error_code(),
				'error_message' => $response->get_error_message(),
			) );
			return $response;
		}

		$result = $this->parse_response( $response );
		
		if ( is_wp_error( $result ) ) {
			$this->log_debug( 'Translation parsing failed', array(
				'error_code' => $result->get_error_code(),
				'error_message' => $result->get_error_message(),
			) );
		} else {
			$this->log_debug( 'Translation completed successfully', array(
				'translated_length' => isset( $result['translated_text'] ) ? strlen( $result['translated_text'] ) : 0,
			) );
		}

		return $result;
	}

	/**
	 * Build translation prompt for OpenAI
	 *
	 * @param string $text Text to translate
	 * @param string $source_lang Source language code
	 * @param string $target_lang Target language code
	 * @return string
	 */
	private function build_translation_prompt( $text, $source_lang, $target_lang ) {
		$source_lang_name = $this->get_language_name( $source_lang );
		$target_lang_name = $this->get_language_name( $target_lang );

		return sprintf(
			'Translate the following text from %s to %s. Maintain the same tone, style, and formatting. Only return the translated text without any additional explanations or notes.\n\n%s',
			$source_lang_name,
			$target_lang_name,
			$text
		);
	}

	/**
	 * Get language name from code
	 *
	 * @param string $code Language code
	 * @return string
	 */
	private function get_language_name( $code ) {
		$languages = array(
			'it' => 'Italian',
			'en' => 'English',
			'es' => 'Spanish',
			'fr' => 'French',
			'de' => 'German',
			'pt' => 'Portuguese',
			'ru' => 'Russian',
			'zh' => 'Chinese',
			'ja' => 'Japanese',
			'ko' => 'Korean',
			'ar' => 'Arabic',
		);

		return isset( $languages[ $code ] ) ? $languages[ $code ] : $code;
	}

	/**
	 * Log debug message to debug.log if enabled
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 */
	private function log_debug( $message, $context = array() ) {
		if ( ! $this->is_debug_log_enabled() ) {
			return;
		}

		$log_message = '[RideOn Translator] ' . $message;
		
		if ( ! empty( $context ) ) {
			$log_message .= ' | Context: ' . wp_json_encode( $context );
		}

		error_log( $log_message );
	}

	/**
	 * Check if debug logging is enabled
	 *
	 * @return bool
	 */
	private function is_debug_log_enabled() {
		$enabled = get_option( 'rideon_translator_enable_debug_log', false );
		return $enabled === '1' || $enabled === true || $enabled === 1;
	}

	/**
	 * Make API request to OpenAI
	 *
	 * @param string $prompt Translation prompt
	 * @return array|WP_Error
	 */
	private function make_api_request( $prompt ) {
		// Sanitize prompt
		$prompt = sanitize_text_field( $prompt );
		
		$request_body = array(
			'model'       => sanitize_text_field( $this->model ),
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
			'temperature' => 0.3,
			'max_tokens'  => 4000,
		);

		$args = array(
			'method'  => 'POST',
			'timeout' => 60,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . sanitize_text_field( $this->api_key ),
			),
			'body'    => wp_json_encode( $request_body ),
		);

		// Log request details if debug is enabled
		$this->log_debug( 'Making API request', array(
			'endpoint' => $this->api_endpoint,
			'model'    => $this->model,
			'prompt_length' => strlen( $prompt ),
			'api_key_prefix' => substr( $this->api_key, 0, 7 ) . '...',
		) );

		$response = wp_remote_request( esc_url_raw( $this->api_endpoint ), $args );

		if ( is_wp_error( $response ) ) {
			$this->log_debug( 'API request failed with WP_Error', array(
				'error_code' => $response->get_error_code(),
				'error_message' => $response->get_error_message(),
			) );
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		// Log response details if debug is enabled
		$this->log_debug( 'API response received', array(
			'status_code' => $status_code,
			'response_length' => strlen( $body ),
		) );

		if ( $status_code !== 200 ) {
			$error_data = json_decode( $body, true );
			$error_msg  = isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : __( 'Unknown API error.', 'rideon-wp-translator' );
			
			// Log error details
			$this->log_debug( 'API error response', array(
				'status_code' => $status_code,
				'error_data' => $error_data,
				'response_body' => $body,
			) );
			
			// Handle specific error codes
			if ( $status_code === 401 ) {
				$error_msg = __( 'Invalid API key. Please check your OpenAI API key in settings.', 'rideon-wp-translator' );
			} elseif ( $status_code === 429 ) {
				$error_msg = __( 'Rate limit exceeded. Please try again later.', 'rideon-wp-translator' );
			} elseif ( $status_code === 500 || $status_code === 503 ) {
				$error_msg = __( 'OpenAI service is temporarily unavailable. Please try again later.', 'rideon-wp-translator' );
			}
			
			return new WP_Error( 'api_error', $error_msg, array( 'status' => $status_code ) );
		}

		$decoded_response = json_decode( $body, true );
		
		// Log successful response details if debug is enabled
		$this->log_debug( 'API request successful', array(
			'usage' => isset( $decoded_response['usage'] ) ? $decoded_response['usage'] : null,
		) );

		return $decoded_response;
	}

	/**
	 * Parse API response
	 *
	 * @param array $response API response
	 * @return array|WP_Error
	 */
	private function parse_response( $response ) {
		if ( ! isset( $response['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response from OpenAI API.', 'rideon-wp-translator' ) );
		}

		$translated_text = trim( $response['choices'][0]['message']['content'] );

		return array(
			'translated_text' => $translated_text,
			'usage'           => isset( $response['usage'] ) ? $response['usage'] : null,
		);
	}

	/**
	 * Test API key validity
	 *
	 * @return bool|WP_Error
	 */
	public function test_api_key() {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'API key is not set.', 'rideon-wp-translator' ) );
		}

		$test_response = $this->translate( 'Hello', 'en', 'it' );

		if ( is_wp_error( $test_response ) ) {
			return $test_response;
		}

		return true;
	}
}
