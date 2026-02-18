<?php
/**
 * OpenAI API Client
 *
 * @package RideOn_WP_Translator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class RideOn_Translator_OpenAI_Client
 */
class RideOn_Translator_OpenAI_Client
{

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
	public function __construct()
	{
		$this->api_key = $this->get_api_key();
		$this->model = get_option('rideon_translator_model', 'gpt-3.5-turbo');
	}

	/**
	 * Get API key (decrypted)
	 *
	 * @return string
	 */
	private function get_api_key()
	{
		$encrypted_key = get_option('rideon_translator_api_key');
		if (!$encrypted_key) {
			return '';
		}

		// Simple encryption/decryption using WordPress functions
		// In production, consider using more secure methods
		$decoded = base64_decode($encrypted_key, true);

		// If decoding fails, the key might not be encoded or might be double-encoded
		// Try decoding multiple times if needed (handles double-encoding cases)
		if ($decoded === false || strpos($decoded, 'sk-') !== 0) {
			$current_key = $encrypted_key;
			$attempts = 0;
			while ($attempts < 3) {
				$test_decode = base64_decode($current_key, true);
				if ($test_decode === false || $test_decode === $current_key) {
					break;
				}
				if (strpos($test_decode, 'sk-') === 0) {
					return $test_decode;
				}
				$current_key = $test_decode;
				$attempts++;
			}
		}

		// If decoded key is valid, return it
		if ($decoded !== false && strpos($decoded, 'sk-') === 0) {
			return $decoded;
		}

		// If stored key is already a plain API key (shouldn't happen, but handle it)
		if (strpos($encrypted_key, 'sk-') === 0) {
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
	 * @param bool   $is_content Whether this is post content (applies paragraph normalization)
	 * @param bool   $is_slug Whether this is a slug (applies URL-friendly formatting)
	 * @return array|WP_Error Response array with translated text or WP_Error on failure
	 */
	public function translate($text, $source_lang, $target_lang, $is_content = false, $is_slug = false)
	{
		$this->log_debug('Translation request started', array(
			'source_lang' => $source_lang,
			'target_lang' => $target_lang,
			'text_length' => strlen($text),
			'is_content' => $is_content,
			'is_slug' => $is_slug,
		));

		// Log original text with visible line breaks for debugging
		$this->log_text_debug('ORIGINAL TEXT RECEIVED', $text);

		if (empty($this->api_key)) {
			$this->log_debug('Translation failed: API key not configured');
			return new WP_Error('no_api_key', __('OpenAI API key is not configured.', 'rideon-wp-translator'));
		}

		if (empty($text)) {
			$this->log_debug('Translation failed: Empty text provided');
			return new WP_Error('empty_text', __('Text to translate is empty.', 'rideon-wp-translator'));
		}

		$prompt = $this->build_translation_prompt($text, $source_lang, $target_lang, $is_content, $is_slug);

		// Log the prompt being sent
		$this->log_text_debug('PROMPT BEING SENT TO API', $prompt);

		$response = $this->make_api_request($prompt);

		if (is_wp_error($response)) {
			$this->log_debug('Translation failed', array(
				'error_code' => $response->get_error_code(),
				'error_message' => $response->get_error_message(),
			));
			return $response;
		}

		$result = $this->parse_response($response);

		if (is_wp_error($result)) {
			$this->log_debug('Translation parsing failed', array(
				'error_code' => $result->get_error_code(),
				'error_message' => $result->get_error_message(),
			));
		} else {
			$this->log_debug('Translation completed successfully', array(
				'translated_length' => isset($result['translated_text']) ? strlen($result['translated_text']) : 0,
			));

			// Log translated text with visible line breaks for debugging
			if (isset($result['translated_text'])) {
				$this->log_text_debug('TRANSLATED TEXT RECEIVED FROM API', $result['translated_text']);
			}
		}

		return $result;
	}

	/**
	 * Build translation prompt for OpenAI
	 *
	 * @param string $text Text to translate
	 * @param string $source_lang Source language code
	 * @param string $target_lang Target language code
	 * @param bool   $is_content Whether this is post content (applies paragraph normalization)
	 * @param bool   $is_slug Whether this is a slug (applies URL-friendly formatting)
	 * @return string
	 */
	private function build_translation_prompt($text, $source_lang, $target_lang, $is_content = false, $is_slug = false)
	{
		$source_lang_name = $this->get_language_name($source_lang);
		$target_lang_name = $this->get_language_name($target_lang);

		// Handle slug translation with special prompt
		if ($is_slug) {
			return sprintf(
				'Translate the following text from %s to %s and convert it to a URL-friendly slug format.\n\nCRITICAL REQUIREMENTS:\n- Use lowercase letters only\n- Separate words with hyphens\n- Remove all special characters, accents, and punctuation\n- Maximum 50 characters\n- Return ONLY the slug, no explanations or additional text\n- Do not include any prefixes, suffixes, or metadata\n\nText to translate:\n%s',
				$source_lang_name,
				$target_lang_name,
				sanitize_text_field($text)
			);
		}

		// Normalize content only if it's post content (not title or excerpt)
		if ($is_content) {
			// Normalize content: convert plain text paragraphs to HTML for better preservation
			$normalized_text = $this->normalize_content_for_translation($text);
			$this->log_text_debug('TEXT AFTER NORMALIZATION', $normalized_text);
		} else {
			// For title and excerpt, use text as is
			$normalized_text = $text;
		}

		// Sanitize the text content to remove potentially dangerous HTML while preserving formatting
		// Use wp_kses_post to allow safe HTML tags (p, strong, em, br, etc.) while removing dangerous ones
		$sanitized_text = wp_kses_post($normalized_text);
		$this->log_text_debug('TEXT AFTER SANITIZATION (wp_kses_post)', $sanitized_text);

		// Check if text contains HTML tags
		$contains_html = $this->contains_html($sanitized_text);
		$this->log_debug('Content analysis', array(
			'contains_html' => $contains_html,
			'is_content' => $is_content,
		));

		if ($contains_html) {
			// HTML-aware translation prompt with explicit paragraph and blank line preservation
			return sprintf(
				'Translate the following HTML content from %s to %s.\n\nCRITICAL INSTRUCTIONS:\n- Preserve ALL HTML tags, attributes, and structure exactly as they are\n- Only translate the text content inside the tags, not the tags themselves\n- CRITICAL: Preserve blank lines (empty lines) between HTML blocks exactly as they appear\n- If there is a blank line between two HTML blocks, keep that blank line in the translation\n- Preserve paragraph breaks: keep <p> tags and double line breaks between paragraphs\n- Maintain the same tone, style, and formatting\n- Do not modify, remove, or add any HTML tags\n- Do NOT remove blank lines that exist between paragraphs\n- Return only the translated HTML without any additional explanations or notes\n\n%s',
				$source_lang_name,
				$target_lang_name,
				$sanitized_text
			);
		} else {
			// Plain text translation prompt
			if ($is_content) {
				// For content, emphasize paragraph preservation with very explicit instructions
				return sprintf(
					'Translate the following text from %s to %s.\n\nCRITICAL INSTRUCTIONS FOR PARAGRAPH PRESERVATION:\n- You MUST preserve the exact paragraph structure\n- Where you see TWO consecutive line breaks (blank line), keep TWO consecutive line breaks in the translation\n- Do NOT merge paragraphs that are separated by blank lines\n- Do NOT add extra line breaks where there are none\n- Maintain the same tone, style, and formatting\n- Return ONLY the translated text without any additional explanations or notes\n\nText to translate:\n%s',
					$source_lang_name,
					$target_lang_name,
					$sanitized_text
				);
			} else {
				// For title/excerpt, simple translation
				return sprintf(
					"Translate the following text from %s to %s.\n\nCRITICAL INSTRUCTIONS:\n- Maintain the exact same tone, style, and formatting\n- IMPORTANT: Do not add a trailing period (full stop) if the source text does not have one\n- If the source text ends without punctuation, the translation must also end without punctuation\n- Return ONLY the translated text without any additional explanations or notes.\n\nText to translate:\n%s",
					$source_lang_name,
					$target_lang_name,
					$sanitized_text
				);
			}
		}
	}

	/**
	 * Normalize content for translation to ensure paragraph structure is preserved
	 * For both plain text and HTML: ensures line breaks and blank lines are preserved
	 *
	 * @param string $text Text to normalize
	 * @return string Normalized text
	 */
	private function normalize_content_for_translation($text)
	{
		// First, normalize Windows line breaks (\r\n) to Unix (\n)
		// This handles cases like \r\n\n (Windows line break + blank line)
		$text = str_replace("\r\n", "\n", $text);

		// Then normalize Mac line breaks (\r) to Unix (\n)
		$text = str_replace("\r", "\n", $text);

		// Normalize blank lines: convert patterns like \n[spaces/tabs]\n to \n\n
		// This preserves paragraph breaks while normalizing whitespace
		$text = preg_replace('/\n[ \t]+\n/', "\n\n", $text);

		// For HTML content: convert double line breaks to <p> tags for better preservation
		// This ensures paragraph structure is explicit and preserved by the API
		if ($this->contains_html($text)) {
			// Split by double line breaks to identify paragraphs
			$paragraphs = preg_split('/\n\s*\n/', $text);

			// Filter out empty paragraphs
			$paragraphs = array_filter($paragraphs, function ($para) {
				return trim($para) !== '';
			});

			// If we have multiple paragraphs, wrap them in <p> tags
			if (count($paragraphs) > 1) {
				$wrapped_paragraphs = array();
				foreach ($paragraphs as $para) {
					$trimmed = trim($para);
					if (!empty($trimmed)) {
						// Preserve single line breaks within paragraphs as <br>
						$trimmed = nl2br($trimmed, false);
						$wrapped_paragraphs[] = '<p>' . $trimmed . '</p>';
					}
				}
				$text = implode("\n\n", $wrapped_paragraphs);
			} elseif (count($paragraphs) === 1) {
				// Single paragraph: wrap in <p> tag
				$first_para = trim(reset($paragraphs));
				if (!empty($first_para)) {
					$first_para = nl2br($first_para, false);
					$text = '<p>' . $first_para . '</p>';
				}
			}

			// Ensure we don't have more than 2 consecutive newlines
			$text = preg_replace('/\n{3,}/', "\n\n", $text);
		} else {
			// For plain text: ensure double line breaks are preserved
			$text = preg_replace('/\n{3,}/', "\n\n", $text);
		}

		return $text;
	}

	/**
	 * Check if text contains HTML tags
	 *
	 * @param string $text Text to check
	 * @return bool True if text contains HTML tags
	 */
	private function contains_html($text)
	{
		// Check for HTML tags (simple but effective check)
		// This will match tags like <p>, </p>, <strong>, <br>, etc.
		return preg_match('/<[^>]+>/', $text) === 1;
	}

	/**
	 * Get language name from code
	 *
	 * @param string $code Language code
	 * @return string
	 */
	private function get_language_name($code)
	{
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

		return isset($languages[$code]) ? $languages[$code] : $code;
	}

	/**
	 * Log debug message to debug.log if enabled
	 *
	 * @param string $message Log message
	 * @param array  $context Additional context data
	 */
	private function log_debug($message, $context = array())
	{
		if (!$this->is_debug_log_enabled()) {
			return;
		}

		$log_message = '[RideOn Translator] ' . $message;

		if (!empty($context)) {
			$log_message .= ' | Context: ' . wp_json_encode($context);
		}

		error_log($log_message);
	}

	/**
	 * Log text content with visible line breaks and special characters for debugging
	 *
	 * @param string $label Label for the log entry
	 * @param string $text Text to log
	 */
	private function log_text_debug($label, $text)
	{
		if (!$this->is_debug_log_enabled()) {
			return;
		}

		// Show text with visible line breaks and special characters
		$visible_text = $text;
		$visible_text = str_replace("\r\n", "\\r\\n\n", $visible_text);
		$visible_text = str_replace("\r", "\\r\n", $visible_text);
		$visible_text = str_replace("\n", "\\n\n", $visible_text);
		$visible_text = str_replace("\t", "\\t", $visible_text);

		// Count line breaks
		$line_break_count = substr_count($text, "\n");
		$double_line_break_count = substr_count($text, "\n\n");

		error_log('[RideOn Translator] ===== ' . $label . ' =====');
		error_log('[RideOn Translator] Length: ' . strlen($text) . ' chars');
		error_log('[RideOn Translator] Line breaks (\\n): ' . $line_break_count);
		error_log('[RideOn Translator] Double line breaks (\\n\\n): ' . $double_line_break_count);
		error_log('[RideOn Translator] Contains HTML: ' . ($this->contains_html($text) ? 'YES' : 'NO'));
		error_log('[RideOn Translator] Text with visible breaks:');
		error_log($visible_text);
		error_log('[RideOn Translator] ===== END ' . $label . ' =====');
	}

	/**
	 * Check if debug logging is enabled
	 *
	 * @return bool
	 */
	private function is_debug_log_enabled()
	{
		$enabled = get_option('rideon_translator_enable_debug_log', false);
		return $enabled === '1' || $enabled === true || $enabled === 1;
	}

	/**
	 * Make API request to OpenAI
	 *
	 * @param string $prompt Translation prompt
	 * @return array|WP_Error
	 */
	private function make_api_request($prompt)
	{
		// Prompt is already sanitized in build_translation_prompt
		// No need to sanitize again here as it would remove HTML formatting

		// Get temperature from settings, default to 0.3
		$temperature = floatval(get_option('rideon_translator_temperature', 0.3));

		// Ensure temperature is between 0 and 2
		if ($temperature < 0) {
			$temperature = 0;
		} elseif ($temperature > 2) {
			$temperature = 2;
		}

		$request_body = array(
			'model' => sanitize_text_field($this->model),
			'messages' => array(
				array(
					'role' => 'user',
					'content' => $prompt,
				),
			),
			'temperature' => $temperature,
			'max_tokens' => 4000,
		);

		$args = array(
			'method' => 'POST',
			'timeout' => 60,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . sanitize_text_field($this->api_key),
			),
			'body' => wp_json_encode($request_body),
		);

		// Log request details if debug is enabled
		$this->log_debug('Making API request', array(
			'endpoint' => $this->api_endpoint,
			'model' => $this->model,
			'prompt_length' => strlen($prompt),
			'api_key_prefix' => substr($this->api_key, 0, 7) . '...',
		));

		$response = wp_remote_request(esc_url_raw($this->api_endpoint), $args);

		if (is_wp_error($response)) {
			$this->log_debug('API request failed with WP_Error', array(
				'error_code' => $response->get_error_code(),
				'error_message' => $response->get_error_message(),
			));
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);

		// Log response details if debug is enabled
		$this->log_debug('API response received', array(
			'status_code' => $status_code,
			'response_length' => strlen($body),
		));

		if ($status_code !== 200) {
			$error_data = json_decode($body, true);
			$error_msg = isset($error_data['error']['message']) ? $error_data['error']['message'] : __('Unknown API error.', 'rideon-wp-translator');

			// Log error details
			$this->log_debug('API error response', array(
				'status_code' => $status_code,
				'error_data' => $error_data,
				'response_body' => $body,
			));

			// Handle specific error codes
			if ($status_code === 401) {
				$error_msg = __('Invalid API key. Please check your OpenAI API key in settings.', 'rideon-wp-translator');
			} elseif ($status_code === 429) {
				$error_msg = __('Rate limit exceeded. Please try again later.', 'rideon-wp-translator');
			} elseif ($status_code === 500 || $status_code === 503) {
				$error_msg = __('OpenAI service is temporarily unavailable. Please try again later.', 'rideon-wp-translator');
			}

			return new WP_Error('api_error', $error_msg, array('status' => $status_code));
		}

		$decoded_response = json_decode($body, true);

		// Log successful response details if debug is enabled
		$this->log_debug('API request successful', array(
			'usage' => isset($decoded_response['usage']) ? $decoded_response['usage'] : null,
		));

		return $decoded_response;
	}

	/**
	 * Parse API response
	 *
	 * @param array $response API response
	 * @return array|WP_Error
	 */
	private function parse_response($response)
	{
		if (!isset($response['choices'][0]['message']['content'])) {
			return new WP_Error('invalid_response', __('Invalid response from OpenAI API.', 'rideon-wp-translator'));
		}

		$translated_text = trim($response['choices'][0]['message']['content']);

		return array(
			'translated_text' => $translated_text,
			'usage' => isset($response['usage']) ? $response['usage'] : null,
		);
	}

	/**
	 * Test API key validity
	 *
	 * @return bool|WP_Error
	 */
	public function test_api_key()
	{
		if (empty($this->api_key)) {
			return new WP_Error('no_api_key', __('API key is not set.', 'rideon-wp-translator'));
		}

		$test_response = $this->translate('Hello', 'en', 'it');

		if (is_wp_error($test_response)) {
			return $test_response;
		}

		return true;
	}
}
