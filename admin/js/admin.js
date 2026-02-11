/**
 * Admin JavaScript for RideOn Translator
 */
(function ($) {
	'use strict';

	$(document).ready(function () {
		const $translateBtn = $('#rideon-translator-translate-btn');
		const $sourceLang = $('#rideon-translator-source-lang');
		const $targetLang = $('#rideon-translator-target-lang');
		const $message = $('#rideon-translator-message');
		const $spinner = $translateBtn.find('.rideon-translator-spinner');

		// Check if we're on post edit screen
		const isPostEditScreen = $('#post_ID').length > 0;

		// Handle translate button click
		$translateBtn.on('click', function (e) {
			e.preventDefault();

			const postId = $(this).data('post-id');
			const sourceLang = $sourceLang.val();
			const targetLang = $targetLang.val();

			if (!targetLang) {
				showMessage('error', rideonTranslator.i18n.error + ': ' + 'Please select a target language.');
				return;
			}

			// Disable button and show spinner
			$translateBtn.prop('disabled', true);
			$translateBtn.find('.rideon-translator-btn-text').text(rideonTranslator.i18n.translating);
			$spinner.addClass('is-active');

			// Hide previous messages
			$message.hide();

			// If we're on post edit screen, use in-place translation
			if (isPostEditScreen) {
				translateInPlace(postId, sourceLang, targetLang);
			} else {
				// Otherwise, create new translated post
				createTranslatedPost(postId, sourceLang, targetLang);
			}
		});

		/**
		 * Translate post content in-place (client-side update)
		 */
		function translateInPlace(postId, sourceLang, targetLang) {
			$.ajax({
				url: rideonTranslator.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rideon_get_translations',
					nonce: rideonTranslator.nonce,
					post_id: postId,
					source_lang: sourceLang,
					target_lang: targetLang
				},
				success: function (response) {

					if (response.success) {
						// Update title
						const $title = $('#title');
						if ($title.length) {
							$title.val(response.data.title);
							// Trigger change event for WordPress
							$title.trigger('input').trigger('change');
						}

						// Update content
						updatePostContent(response.data.content);

						// Update excerpt
						const $excerpt = $('#excerpt');
						if ($excerpt.length) {
							$excerpt.val(response.data.excerpt);
							$excerpt.trigger('input').trigger('change');
						}

						// Mark post as changed for WordPress
						if (typeof wp !== 'undefined' && wp.autosave) {
							wp.autosave.server.triggerSave();
						}

						showMessage('success', rideonTranslator.i18n.success + ' ' + 'The content has been translated. Review and save when ready.');
						resetButton();
					} else {
						console.error('Translation failed:', response.data);
						showMessage('error', response.data.message || rideonTranslator.i18n.error);
						resetButton();
					}
				},
				error: function (xhr, status, error) {
					console.error('AJAX error:', xhr, status, error);
					showMessage('error', rideonTranslator.i18n.error + ': ' + error);
					resetButton();
				}
			});
		}

		/**
		 * Update post content (handles both TinyMCE and textarea)
		 */
		function updatePostContent(content) {

			if (!content || content.trim() === '') {
				console.warn('Empty content provided to updatePostContent');
				return;
			}

			// Function to actually update the content
			function doUpdate() {
				// Check if TinyMCE is available
				if (typeof tinymce !== 'undefined') {
					// Try to get the active editor
					const editor = tinymce.activeEditor;

					if (editor && !editor.isHidden() && editor.initialized) {
						// TinyMCE is active, use its API
						editor.setContent(content);
						// Trigger change event to mark content as modified
						editor.fire('change');
						editor.fire('input');
						editor.nodeChanged();

						// Also update the underlying textarea
						const $content = $('#content');
						if ($content.length) {
							$content.val(content);
							$content.trigger('input').trigger('change');
						}

						// Mark WordPress post as changed (for Gutenberg editor)
						if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch && wp.data.select('core/editor')) {
							try {
								wp.data.dispatch('core/editor').editPost({ content: content });
							} catch (e) {
								console.log('Could not update Gutenberg editor:', e);
							}
						}

						// Mark classic editor as changed
						if (typeof wp !== 'undefined' && wp.autosave) {
							wp.autosave.server.triggerSave();
						}

						return true;
					}
				}

				// Fallback to textarea
				const $content = $('#content');
				if ($content.length) {
					$content.val(content);
					$content.trigger('input').trigger('change');

					// Mark WordPress post as changed (for Gutenberg editor)
					if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch && wp.data.select('core/editor')) {
						try {
							wp.data.dispatch('core/editor').editPost({ content: content });
						} catch (e) {
							console.log('Could not update Gutenberg editor:', e);
						}
					}

					return true;
				} else {
					console.error('Content textarea not found');
					return false;
				}
			}

			// Try to update immediately
			if (doUpdate()) {
				return;
			}

			// If TinyMCE is not ready, wait for it
			if (typeof tinymce !== 'undefined') {
				let attempts = 0;
				const maxAttempts = 10;
				const checkInterval = setInterval(function () {
					attempts++;
					if (doUpdate() || attempts >= maxAttempts) {
						clearInterval(checkInterval);
						if (attempts >= maxAttempts) {
							console.error('Failed to update content after waiting for TinyMCE');
						}
					}
				}, 200);
			} else {
				console.error('TinyMCE not available and textarea not found');
			}
		}

		/**
		 * Create new translated post (original behavior)
		 */
		function createTranslatedPost(postId, sourceLang, targetLang) {
			$.ajax({
				url: rideonTranslator.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rideon_translate_post',
					nonce: rideonTranslator.nonce,
					post_id: postId,
					source_lang: sourceLang,
					target_lang: targetLang
				},
				success: function (response) {
					if (response.success) {
						const message = response.data.message;
						const editLink = response.data.edit_link;

						let messageHtml = message;
						if (editLink) {
							messageHtml += ' <a href="' + editLink + '" target="_blank">' + 'View translated post' + '</a>';
						}

						showMessage('success', messageHtml);

						// Reload page after 2 seconds to show new translation
						setTimeout(function () {
							location.reload();
						}, 2000);
					} else {
						showMessage('error', response.data.message || rideonTranslator.i18n.error);
						resetButton();
					}
				},
				error: function (xhr, status, error) {
					showMessage('error', rideonTranslator.i18n.error + ': ' + error);
					resetButton();
				}
			});
		}

		/**
		 * Show message
		 */
		function showMessage(type, message) {
			$message
				.removeClass('success error')
				.addClass(type)
				.html(message)
				.show();
		}

		/**
		 * Reset button state
		 */
		function resetButton() {
			$translateBtn.prop('disabled', false);
			$translateBtn.find('.rideon-translator-btn-text').text('Translate');
			$spinner.removeClass('is-active');
		}
	});
})(jQuery);
