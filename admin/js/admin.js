/**
 * Admin JavaScript for RideOn Translator
 */
(function($) {
	'use strict';

	$(document).ready(function() {
		const $translateBtn = $('#rideon-translator-translate-btn');
		const $targetLang = $('#rideon-translator-target-lang');
		const $message = $('#rideon-translator-message');
		const $spinner = $translateBtn.find('.rideon-translator-spinner');

		// Handle translate button click
		$translateBtn.on('click', function(e) {
			e.preventDefault();

			const postId = $(this).data('post-id');
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

			// Make AJAX request
			$.ajax({
				url: rideonTranslator.ajaxUrl,
				type: 'POST',
				data: {
					action: 'rideon_translate_post',
					nonce: rideonTranslator.nonce,
					post_id: postId,
					target_lang: targetLang
				},
				success: function(response) {
					if (response.success) {
						const message = response.data.message;
						const editLink = response.data.edit_link;
						
						let messageHtml = message;
						if (editLink) {
							messageHtml += ' <a href="' + editLink + '" target="_blank">' + 'View translated post' + '</a>';
						}
						
						showMessage('success', messageHtml);
						
						// Reload page after 2 seconds to show new translation
						setTimeout(function() {
							location.reload();
						}, 2000);
					} else {
						showMessage('error', response.data.message || rideonTranslator.i18n.error);
						resetButton();
					}
				},
				error: function(xhr, status, error) {
					showMessage('error', rideonTranslator.i18n.error + ': ' + error);
					resetButton();
				}
			});
		});

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
