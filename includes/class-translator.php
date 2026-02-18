<?php
/**
 * Translator class - Orchestrates translation process
 *
 * @package RideOn_WP_Translator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class RideOn_Translator
 */
class RideOn_Translator
{

	/**
	 * OpenAI client instance
	 *
	 * @var RideOn_Translator_OpenAI_Client
	 */
	private $openai_client;

	/**
	 * Constructor
	 */
	public function __construct()
	{
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
	public function translate_post($post_id, $target_lang, $source_lang = '')
	{
		// Get source post
		$source_post = get_post($post_id);
		if (!$source_post) {
			return new WP_Error('post_not_found', __('Source post not found.', 'rideon-wp-translator'));
		}

		// Detect source language if not provided
		if (empty($source_lang)) {
			$source_lang = get_option('rideon_translator_default_source_lang', 'it');
		}

		// Extract post content
		$content = $this->extract_post_content($source_post);

		// Translate title
		$translated_title = $this->translate_text($content['title'], $source_lang, $target_lang);
		if (is_wp_error($translated_title)) {
			return $translated_title;
		}

		// Translate slug - generate from translated title using OpenAI
		$translated_slug = '';
		if (!empty($translated_title)) {
			$translated_slug_result = $this->translate_slug($translated_title, $source_lang, $target_lang, $source_post->post_type);
			if (!is_wp_error($translated_slug_result) && !empty($translated_slug_result)) {
				$translated_slug = $translated_slug_result;
			}
		}

		// If slug translation failed or is empty, generate from translated title using WordPress sanitize_title
		if (empty($translated_slug)) {
			$translated_slug = sanitize_title($translated_title);
			$translated_slug = $this->ensure_unique_slug($translated_slug, $source_post->post_type);
		}

		// Translate content
		$translated_content = $this->translate_text($content['content'], $source_lang, $target_lang);
		if (is_wp_error($translated_content)) {
			return $translated_content;
		}

		// Translate excerpt if exists
		$translated_excerpt = '';
		if (!empty($content['excerpt'])) {
			$translated_excerpt_result = $this->translate_text($content['excerpt'], $source_lang, $target_lang);
			if (!is_wp_error($translated_excerpt_result)) {
				$translated_excerpt = $translated_excerpt_result;
			}
		}

		// Create translated post
		$translated_post_id = $this->create_translated_post(
			$source_post,
			array(
				'title' => $translated_title,
				'content' => $translated_content,
				'excerpt' => $translated_excerpt,
				'slug' => $translated_slug,
			),
			$target_lang
		);

		if (is_wp_error($translated_post_id)) {
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
	private function extract_post_content($post)
	{
		// Get slug, or generate from title if empty
		$slug = $post->post_name;
		if (empty($slug)) {
			$slug = sanitize_title($post->post_title);
		}

		return array(
			'title' => $post->post_title,
			'content' => $post->post_content,
			'excerpt' => $post->post_excerpt,
			'slug' => $slug,
		);
	}

	/**
	 * Translate text using OpenAI
	 *
	 * @param string $text Text to translate
	 * @param string $source_lang Source language code
	 * @param string $target_lang Target language code
	 * @param bool   $is_content Whether this is post content (applies paragraph normalization)
	 * @return string|WP_Error
	 */
	private function translate_text($text, $source_lang, $target_lang, $is_content = false)
	{
		if (empty($text)) {
			return '';
		}

		// Sanitize language codes
		$source_lang = sanitize_text_field($source_lang);
		$target_lang = sanitize_text_field($target_lang);

		$response = $this->openai_client->translate($text, $source_lang, $target_lang, $is_content);

		if (is_wp_error($response)) {
			return $response;
		}

		if (!isset($response['translated_text']) || empty($response['translated_text'])) {
			return new WP_Error('empty_translation', __('Translation returned empty result.', 'rideon-wp-translator'));
		}

		// Log before wp_kses_post
		if (get_option('rideon_translator_enable_debug_log', false)) {
			$this->log_text_before_sanitize($response['translated_text']);
		}


		$sanitized_result = wp_kses_post($response['translated_text']);

		// BUG FIX: If original text didn't end with a period, but translation does, remove it
		// This is specific to titles and excerpts (non-content fields)
		if (!$is_content && !empty($text)) {
			$trimmed_source = trim($text);
			$trimmed_result = trim($sanitized_result);
			if (!empty($trimmed_source) && !empty($trimmed_result)) {
				if (substr($trimmed_source, -1) !== '.' && substr($trimmed_result, -1) === '.') {
					$sanitized_result = rtrim($trimmed_result, '.');
				}
			}
		}

		// Log after wp_kses_post
		if (get_option('rideon_translator_enable_debug_log', false)) {
			$this->log_text_after_sanitize($sanitized_result);
		}

		return $sanitized_result;
	}

	/**
	 * Generate slug from translated title using OpenAI
	 *
	 * @param string $translated_title Translated title to convert to slug
	 * @param string $source_lang Source language code (not used, kept for compatibility)
	 * @param string $target_lang Target language code (not used, kept for compatibility)
	 * @param string $post_type Post type for conflict checking
	 * @param int    $exclude_id Post ID to exclude from conflict check
	 * @return string|WP_Error Generated and sanitized slug
	 */
	private function translate_slug($translated_title, $source_lang, $target_lang, $post_type = 'post', $exclude_id = 0)
	{
		if (empty($translated_title)) {
			return '';
		}

		// Sanitize language codes
		$source_lang = sanitize_text_field($source_lang);
		$target_lang = sanitize_text_field($target_lang);

		// Generate slug from translated title using OpenAI with slug-specific prompt
		// OpenAI will convert the translated title to a URL-friendly slug format
		$response = $this->openai_client->translate($translated_title, $source_lang, $target_lang, false, true);

		if (is_wp_error($response)) {
			// If translation fails, return empty to trigger fallback
			return '';
		}

		if (!isset($response['translated_text']) || empty($response['translated_text'])) {
			// If translation is empty, return empty to trigger fallback
			return '';
		}

		// Get translated slug text and sanitize it
		$translated_slug_text = trim($response['translated_text']);

		// Remove any extra text that might have been returned (explanations, etc.)
		// Take only the first line if multiple lines
		$lines = explode("\n", $translated_slug_text);
		$translated_slug_text = trim($lines[0]);

		// Sanitize to URL-friendly format
		// This will ensure proper slug format (lowercase, hyphens, etc.)
		$sanitized_slug = sanitize_title($translated_slug_text);

		// Ensure slug is unique
		$unique_slug = $this->ensure_unique_slug($sanitized_slug, $post_type, $exclude_id);

		return $unique_slug;
	}

	/**
	 * Ensure slug is unique by checking for conflicts
	 *
	 * @param string $slug Slug to check
	 * @param string $post_type Post type
	 * @param int    $exclude_id Post ID to exclude from check
	 * @return string Unique slug
	 */
	private function ensure_unique_slug($slug, $post_type = 'post', $exclude_id = 0)
	{
		$original_slug = $slug;
		$counter = 1;

		// Check if slug already exists
		$existing_post = get_page_by_path($slug, OBJECT, $post_type);

		while ($existing_post && ($exclude_id === 0 || $existing_post->ID !== $exclude_id)) {
			$slug = $original_slug . '-' . $counter;
			$existing_post = get_page_by_path($slug, OBJECT, $post_type);
			$counter++;
		}

		return $slug;
	}

	/**
	 * Create translated post
	 *
	 * @param WP_Post $source_post Source post object
	 * @param array   $translated_content Translated content
	 * @param string  $target_lang Target language code
	 * @return int|WP_Error
	 */
	private function create_translated_post($source_post, $translated_content, $target_lang)
	{
		// Sanitize all content
		$post_data = array(
			'post_title' => sanitize_text_field($translated_content['title']),
			'post_content' => wp_kses_post($translated_content['content']),
			'post_excerpt' => sanitize_textarea_field($translated_content['excerpt']),
			'post_status' => sanitize_text_field($source_post->post_status),
			'post_type' => sanitize_text_field($source_post->post_type),
			'post_author' => absint($source_post->post_author),
			'post_category' => array_map('absint', wp_get_post_categories($source_post->ID)),
		);

		// Add translated slug if provided
		if (!empty($translated_content['slug'])) {
			$post_data['post_name'] = sanitize_text_field($translated_content['slug']);
		}

		// Copy tags
		$tags = wp_get_post_tags($source_post->ID, array('fields' => 'names'));
		if (!empty($tags) && is_array($tags)) {
			$post_data['tags_input'] = array_map('sanitize_text_field', $tags);
		}

		$translated_post_id = wp_insert_post($post_data, true);

		if (is_wp_error($translated_post_id)) {
			return $translated_post_id;
		}

		// Copy featured image if exists
		$thumbnail_id = get_post_thumbnail_id($source_post->ID);
		if ($thumbnail_id) {
			set_post_thumbnail($translated_post_id, $thumbnail_id);
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
	public function get_translations($post_id, $target_lang, $source_lang = '')
	{
		// Get source post
		$source_post = get_post($post_id);
		if (!$source_post) {
			return new WP_Error('post_not_found', __('Source post not found.', 'rideon-wp-translator'));
		}

		// Detect source language if not provided
		if (empty($source_lang)) {
			$source_lang = get_option('rideon_translator_default_source_lang', 'it');
		}

		// Extract post content
		$content = $this->extract_post_content($source_post);

		// Translate title
		$translated_title = $this->translate_text($content['title'], $source_lang, $target_lang, false);
		if (is_wp_error($translated_title)) {
			return $translated_title;
		}

		// Translate slug - generate from translated title using OpenAI
		$translated_slug = '';
		if (!empty($translated_title)) {
			$translated_slug_result = $this->translate_slug($translated_title, $source_lang, $target_lang, $source_post->post_type, $source_post->ID);
			if (!is_wp_error($translated_slug_result) && !empty($translated_slug_result)) {
				$translated_slug = $translated_slug_result;
			}
		}

		// If slug translation failed or is empty, generate from translated title using WordPress sanitize_title
		if (empty($translated_slug)) {
			$translated_slug = sanitize_title($translated_title);
			$translated_slug = $this->ensure_unique_slug($translated_slug, $source_post->post_type, $source_post->ID);
		}

		// Translate content (with normalization for paragraph preservation)
		$translated_content = $this->translate_text($content['content'], $source_lang, $target_lang, true);
		if (is_wp_error($translated_content)) {
			return $translated_content;
		}

		// Translate excerpt if exists
		$translated_excerpt = '';
		if (!empty($content['excerpt'])) {
			$translated_excerpt_result = $this->translate_text($content['excerpt'], $source_lang, $target_lang, false);
			if (!is_wp_error($translated_excerpt_result)) {
				$translated_excerpt = $translated_excerpt_result;
			}
		}

		$result = array(
			'title' => $translated_title,
			'content' => $translated_content,
			'excerpt' => $translated_excerpt,
			'slug' => $translated_slug,
		);

		// Log debug info if enabled
		if (get_option('rideon_translator_enable_debug_log', false)) {
			error_log('[RideOn Translator] get_translations result | Context: ' . wp_json_encode(array(
				'title_length' => strlen($result['title']),
				'content_length' => strlen($result['content']),
				'excerpt_length' => strlen($result['excerpt']),
				'slug_length' => strlen($result['slug']),
				'title_preview' => substr($result['title'], 0, 50),
				'content_preview' => substr($result['content'], 0, 100),
				'slug_preview' => $result['slug'],
			)));
		}

		return $result;
	}

	/**
	 * Log text before sanitization with wp_kses_post
	 *
	 * @param string $text Text to log
	 */
	private function log_text_before_sanitize($text)
	{
		$line_break_count = substr_count($text, "\n");
		$double_line_break_count = substr_count($text, "\n\n");

		$visible_text = str_replace("\n", "\\n\n", $text);

		error_log('[RideOn Translator] ===== BEFORE wp_kses_post SANITIZATION =====');
		error_log('[RideOn Translator] Length: ' . strlen($text) . ' chars');
		error_log('[RideOn Translator] Line breaks (\\n): ' . $line_break_count);
		error_log('[RideOn Translator] Double line breaks (\\n\\n): ' . $double_line_break_count);
		error_log('[RideOn Translator] Text:');
		error_log($visible_text);
		error_log('[RideOn Translator] ===== END BEFORE SANITIZATION =====');
	}

	/**
	 * Log text after sanitization with wp_kses_post
	 *
	 * @param string $text Text to log
	 */
	private function log_text_after_sanitize($text)
	{
		$line_break_count = substr_count($text, "\n");
		$double_line_break_count = substr_count($text, "\n\n");

		$visible_text = str_replace("\n", "\\n\n", $text);

		error_log('[RideOn Translator] ===== AFTER wp_kses_post SANITIZATION =====');
		error_log('[RideOn Translator] Length: ' . strlen($text) . ' chars');
		error_log('[RideOn Translator] Line breaks (\\n): ' . $line_break_count);
		error_log('[RideOn Translator] Double line breaks (\\n\\n): ' . $double_line_break_count);
		error_log('[RideOn Translator] Text:');
		error_log($visible_text);
		error_log('[RideOn Translator] ===== END AFTER SANITIZATION =====');
	}
}
