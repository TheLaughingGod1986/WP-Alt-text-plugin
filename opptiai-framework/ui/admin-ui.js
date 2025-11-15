/**
 * OpptiAI Framework - Admin UI Scripts
 *
 * Consolidated JavaScript for all OpptiAI admin pages
 *
 * @package OpptiAI\Framework\UI
 * @version 1.0.0
 */

(function($) {
	'use strict';

	// Global namespace
	window.OpptiAI = window.OpptiAI || {};

	/**
	 * Tab Navigation
	 */
	OpptiAI.tabs = {
		init: function() {
			$(document).on('click', '.opptiai-tab', function(e) {
				e.preventDefault();

				const $tab = $(this);
				const tabId = $tab.data('tab') || $tab.attr('href').substring(1);

				// Update active tab
				$tab.addClass('opptiai-tab-active')
					.siblings()
					.removeClass('opptiai-tab-active');

				// Show corresponding panel
				$('#' + tabId).removeClass('opptiai-tab-panel-hidden')
					.siblings('.opptiai-tab-panel')
					.addClass('opptiai-tab-panel-hidden');

				// Store in session storage
				sessionStorage.setItem('opptiai_active_tab', tabId);
			});

			// Restore active tab from session storage
			const activeTab = sessionStorage.getItem('opptiai_active_tab');
			if (activeTab) {
				$('.opptiai-tab[data-tab="' + activeTab + '"]').trigger('click');
			}
		}
	};

	/**
	 * Modal Management
	 */
	OpptiAI.modal = {
		open: function(modalId) {
			const $modal = $('#' + modalId);
			if ($modal.length) {
				$modal.fadeIn(200);
				$('body').addClass('opptiai-modal-open');
			}
		},

		close: function(modalId) {
			const $modal = modalId ? $('#' + modalId) : $('.opptiai-modal:visible');
			$modal.fadeOut(200);
			$('body').removeClass('opptiai-modal-open');
		},

		init: function() {
			// Close modal on overlay click
			$(document).on('click', '.opptiai-modal-overlay', function() {
				OpptiAI.modal.close();
			});

			// Close modal on close button click
			$(document).on('click', '.opptiai-modal-close', function() {
				const modalId = $(this).closest('.opptiai-modal').attr('id');
				OpptiAI.modal.close(modalId);
			});

			// Close modal on Escape key
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape' && $('.opptiai-modal:visible').length) {
					OpptiAI.modal.close();
				}
			});
		}
	};

	/**
	 * Notice Management
	 */
	OpptiAI.notice = {
		show: function(message, type, dismissible) {
			type = type || 'info';
			dismissible = dismissible !== false;

			const $notice = $('<div>')
				.addClass('opptiai-notice opptiai-notice-' + type)
				.html('<div class="opptiai-notice-content">' + message + '</div>');

			if (dismissible) {
				$notice.addClass('opptiai-notice-dismissible');
				$notice.append(
					'<button type="button" class="opptiai-notice-dismiss" aria-label="Dismiss">' +
					'<span aria-hidden="true">&times;</span>' +
					'</button>'
				);
			}

			// Insert notice at the top of the page content
			$('.opptiai-page-content').prepend($notice);

			// Auto-dismiss after 5 seconds
			if (dismissible) {
				setTimeout(function() {
					$notice.fadeOut(300, function() {
						$(this).remove();
					});
				}, 5000);
			}

			return $notice;
		},

		dismiss: function($notice) {
			$notice.fadeOut(300, function() {
				$(this).remove();
			});
		},

		init: function() {
			$(document).on('click', '.opptiai-notice-dismiss', function() {
				OpptiAI.notice.dismiss($(this).closest('.opptiai-notice'));
			});
		}
	};

	/**
	 * AJAX Helper
	 */
	OpptiAI.ajax = {
		request: function(action, data, options) {
			options = options || {};

			const defaults = {
				method: 'POST',
				dataType: 'json',
				beforeSend: function() {},
				success: function() {},
				error: function() {},
				complete: function() {}
			};

			options = $.extend(defaults, options);

			const ajaxData = {
				action: action,
				nonce: window.opptiaiFramework ? window.opptiaiFramework.nonce : '',
				...data
			};

			return $.ajax({
				url: window.opptiaiFramework ? window.opptiaiFramework.ajaxUrl : ajaxurl,
				type: options.method,
				dataType: options.dataType,
				data: ajaxData,
				beforeSend: options.beforeSend,
				success: options.success,
				error: function(xhr, status, error) {
					console.error('AJAX Error:', error);
					options.error(xhr, status, error);
				},
				complete: options.complete
			});
		}
	};

	/**
	 * Form Validation
	 */
	OpptiAI.form = {
		validate: function($form) {
			let isValid = true;

			$form.find('[required]').each(function() {
				const $field = $(this);
				const value = $field.val().trim();

				if (!value) {
					isValid = false;
					$field.addClass('opptiai-form-error');
				} else {
					$field.removeClass('opptiai-form-error');
				}
			});

			return isValid;
		},

		clearErrors: function($form) {
			$form.find('.opptiai-form-error').removeClass('opptiai-form-error');
		}
	};

	/**
	 * Loading Spinner
	 */
	OpptiAI.spinner = {
		show: function($element) {
			const $spinner = $('<span class="opptiai-spinner"></span>');
			$element.append($spinner);
			$element.prop('disabled', true);
		},

		hide: function($element) {
			$element.find('.opptiai-spinner').remove();
			$element.prop('disabled', false);
		}
	};

	/**
	 * Confirm Dialog
	 */
	OpptiAI.confirm = function(message, callback) {
		if (confirm(message || window.opptiaiFramework.i18n.confirm)) {
			callback();
		}
	};

	/**
	 * Debounce Helper
	 */
	OpptiAI.debounce = function(func, wait) {
		let timeout;
		return function(...args) {
			clearTimeout(timeout);
			timeout = setTimeout(() => func.apply(this, args), wait);
		};
	};

	/**
	 * Initialize all components
	 */
	$(document).ready(function() {
		OpptiAI.tabs.init();
		OpptiAI.modal.init();
		OpptiAI.notice.init();
	});

})(jQuery);
