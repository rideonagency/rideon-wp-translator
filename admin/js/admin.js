/**
 * Admin JavaScript for RideOn Translator
 */
(function ($) {
	'use strict';

	$(document).ready(function () {
		const $translateBtn = $('#rideon-translator-translate-btn');
		const $swapBtn = $('#rideon-translator-swap-btn');
		const $sourceLang = $('#rideon-translator-source-lang');
		const $targetLang = $('#rideon-translator-target-lang');
		const $message = $('#rideon-translator-message');
		const $spinner = $translateBtn.find('.rideon-translator-spinner');

		// Check if we're on post edit screen
		const isPostEditScreen = $('#post_ID').length > 0;

		// Store pending translation content to apply when TinyMCE initializes
		let pendingTranslationContent = null;

		/**
		 * Get TinyMCE editor instance for the 'content' editor (WordPress post content)
		 * Returns a Promise that resolves with the editor instance
		 */
		function getTinyMCEEditor() {
			return new Promise(function(resolve, reject) {
				// If TinyMCE is not available, reject immediately
				if (typeof tinymce === 'undefined') {
					reject(new Error('TinyMCE is not available'));
					return;
				}

				// Helper function to get the 'content' editor specifically
				function getContentEditor() {
					// Try to get editor by ID first (most reliable)
					if (tinymce.get('content')) {
						return tinymce.get('content');
					}
					// Fallback to activeEditor if it's the content editor
					const activeEditor = tinymce.activeEditor;
					if (activeEditor && activeEditor.id === 'content') {
						return activeEditor;
					}
					return null;
				}

				// Check if content editor is already ready
				const editor = getContentEditor();
				if (editor && !editor.isHidden() && editor.initialized) {
					resolve(editor);
					return;
				}

				// Wait for editor to be initialized using events
				let resolved = false;

				// Function to check and resolve with content editor
				function tryResolve(editorInstance) {
					// Only resolve if it's the content editor
					if (!resolved && editorInstance && editorInstance.id === 'content' && 
						!editorInstance.isHidden() && editorInstance.initialized) {
						resolved = true;
						resolve(editorInstance);
					}
				}

				// Listen for editor initialization using TinyMCE events
				if (tinymce.on) {
					// Listen for new editors being added
					tinymce.on('AddEditor', function(e) {
						const newEditor = e.editor;
						newEditor.on('init', function() {
							if (!resolved && newEditor.id === 'content') {
								tryResolve(newEditor);
							}
						});
					});

					// Also check if editor already exists but isn't initialized yet
					if (editor && !editor.initialized) {
						editor.on('init', function() {
							if (!resolved) {
								tryResolve(editor);
							}
						});
					}
				}

				// Listen for WordPress-specific TinyMCE events
				$(document).on('tinymce-editor-init', function(event, editorInstance) {
					if (!resolved && editorInstance && editorInstance.id === 'content') {
						tryResolve(editorInstance);
					}
				});

				// Fallback timeout (shouldn't be needed, but safety net)
				setTimeout(function() {
					if (!resolved) {
						resolved = true;
						// Try one last time
						const finalEditor = getContentEditor();
						if (finalEditor && !finalEditor.isHidden() && finalEditor.initialized) {
							resolve(finalEditor);
						} else {
							reject(new Error('TinyMCE content editor not available after timeout'));
						}
					}
				}, 5000);
			});
		}

		// Handle swap button click
		$swapBtn.on('click', function (e) {
			e.preventDefault();
			
			const sourceValue = $sourceLang.val();
			const targetValue = $targetLang.val();
			
			// Only swap if target language is selected
			if (!targetValue) {
				return;
			}
			
			// Swap the values
			$sourceLang.val(targetValue).trigger('change');
			$targetLang.val(sourceValue).trigger('change');
		});

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
		 * Update slug field value (helper function for classic editor)
		 * Directly updates the display span #editable-post-name
		 */
		function updateSlugField(slugValue) {
			// Update the display span #editable-post-name directly
			const $slugSpan = $('#editable-post-name');
			if ($slugSpan.length) {
				$slugSpan.text(slugValue);
				
				// Also update the full version if it exists
				const $slugSpanFull = $('#editable-post-name-full');
				if ($slugSpanFull.length) {
					$slugSpanFull.text(slugValue);
				}
				
				// Trigger change event on the span to notify WordPress
				$slugSpan.trigger('change');
			}
			
			// Also update the input field #post_name if it exists (might be hidden)
			const $slugInput = $('#post_name');
			if ($slugInput.length) {
				$slugInput.val(slugValue);
				$slugInput.trigger('input').trigger('change').trigger('blur');
				
				// Also trigger native events
				if ($slugInput[0]) {
					const inputEvent = new Event('input', { bubbles: true });
					const changeEvent = new Event('change', { bubbles: true });
					$slugInput[0].dispatchEvent(inputEvent);
					$slugInput[0].dispatchEvent(changeEvent);
				}
			}
		}

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

						// Update slug if provided
						if (response.data.slug) {
							// For Gutenberg editor, update via data dispatch
							if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
								try {
									const editorStore = wp.data.select('core/editor');
									if (editorStore) {
										wp.data.dispatch('core/editor').editPost({ slug: response.data.slug });
									}
								} catch (e) {
									// Gutenberg editor not available, continue with classic editor approach
								}
							}
							
							// For classic editor, update the slug field and display span
							updateSlugField(response.data.slug);
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
		 * Uses Promise-based approach with event listeners instead of polling
		 */
		function updatePostContent(content) {
			if (!content || content.trim() === '') {
				return;
			}

			// Function to update TinyMCE editor content
			function updateTinyMCE(editor) {
				try {
					editor.setContent(content, { format: 'raw' });
					editor.fire('change');
					editor.fire('input');
					editor.nodeChanged();
					return true;
				} catch (e) {
					console.error('Error updating TinyMCE content:', e);
					return false;
				}
			}

			// Function to update textarea
			function updateTextarea() {
				const $content = $('#content');
				if ($content.length) {
					$content.val(content);
					$content.trigger('input').trigger('change');
					return true;
				}
				return false;
			}

			// Function to update Gutenberg editor
			function updateGutenberg() {
				if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
					try {
						const editorStore = wp.data.select('core/editor');
						if (editorStore) {
							wp.data.dispatch('core/editor').editPost({ content: content });
							return true;
						}
					} catch (e) {
						// Gutenberg editor not available, ignore
					}
				}
				return false;
			}

			// Function to mark editor as changed
			function markAsChanged() {
				if (typeof wp !== 'undefined' && wp.autosave) {
					wp.autosave.server.triggerSave();
				}
			}

			// Always update textarea first (works for both TinyMCE and textarea-only mode)
			updateTextarea();

			// Try to update TinyMCE using Promise-based approach
			if (typeof tinymce !== 'undefined') {
				getTinyMCEEditor()
					.then(function(editor) {
						// Editor is ready, update it
						updateTinyMCE(editor);
						// Ensure textarea is still in sync
						updateTextarea();
						// Clear pending content
						pendingTranslationContent = null;
					})
					.catch(function(error) {
						// TinyMCE not available or timeout - textarea already updated
						// Store content in case TinyMCE initializes later
						pendingTranslationContent = content;
					});
			} else {
				// No TinyMCE available, textarea already updated
				pendingTranslationContent = null;
			}

			// Update Gutenberg editor if available
			updateGutenberg();

			// Mark editor as changed
			markAsChanged();
		}

		// Listen for TinyMCE initialization to apply pending translations
		// This handles the case where TinyMCE initializes after we've already tried to update
		if (typeof tinymce !== 'undefined') {
			// Function to apply pending translation
			function applyPendingTranslation(editor) {
				// Only apply to content editor, not other editors like acf_content
				if (pendingTranslationContent && editor && editor.id === 'content' && 
					editor.initialized && !editor.isHidden()) {
					try {
						editor.setContent(pendingTranslationContent, { format: 'raw' });
						editor.fire('change');
						editor.fire('input');
						editor.nodeChanged();
						
						// Also update textarea to keep in sync
						const $content = $('#content');
						if ($content.length) {
							$content.val(pendingTranslationContent);
							$content.trigger('input').trigger('change');
						}
						
						pendingTranslationContent = null;
					} catch (e) {
						console.error('Error applying pending translation to TinyMCE:', e);
					}
				}
			}

			// Listen for WordPress-specific TinyMCE events
			$(document).on('tinymce-editor-init', function(event, editor) {
				applyPendingTranslation(editor);
			});

			// Listen using TinyMCE's native event system
			if (tinymce.on) {
				tinymce.on('AddEditor', function(e) {
					const editor = e.editor;
					editor.on('init', function() {
						applyPendingTranslation(editor);
					});
				});
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
