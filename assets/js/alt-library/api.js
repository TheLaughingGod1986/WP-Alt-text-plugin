(function (window) {
	'use strict';

	function getAjaxUrl(config) {
		var ajaxConfig = window.bbai_ajax || {};
		return ajaxConfig.ajax_url || ajaxConfig.ajaxurl || (config && (config.ajaxUrl || config.ajaxurl)) || '';
	}

	function getNonce(config) {
		var ajaxConfig = window.bbai_ajax || {};
		return ajaxConfig.nonce || (window.BBAI && window.BBAI.nonce) || (config && config.nonce) || '';
	}

	function normalizePayload(response) {
		if (response && response.data != null) {
			if (typeof response.data === 'object' && !Array.isArray(response.data)) {
				return response.data;
			}
			if (typeof response.data === 'string') {
				try {
					var parsed = JSON.parse(response.data);
					if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
						return parsed;
					}
				} catch (parseErr) {
					/* ignore malformed nested JSON */
				}
			}
		}

		return response && typeof response === 'object' ? response : {};
	}

	function isSuccess(response) {
		return !!(
			response &&
			(response.success === true ||
				response.success === 1 ||
				response.success === 'true' ||
				response.success === '1')
		);
	}

	function extractAltText(payload) {
		if (!payload || typeof payload !== 'object') {
			return '';
		}

		function pickAlt(obj, depth) {
			var value;
			depth = depth || 0;
			if (depth > 8 || !obj || typeof obj !== 'object') {
				return '';
			}
			value =
				obj.altText ||
				obj.alt_text ||
				obj.alt ||
				obj.description ||
				obj.text ||
				obj.new_alt ||
				obj.newAlt ||
				obj.generated_alt ||
				obj.generatedAlt;
			if (value == null || value === '') {
				return '';
			}
			if (typeof value === 'number' && isFinite(value)) {
				return String(value).trim();
			}
			if (typeof value === 'string') {
				return value.trim();
			}
			if (Array.isArray(value) && value.length) {
				return pickAlt({ alt_text: value[0] }, depth + 1);
			}
			if (typeof value === 'object') {
				return pickAlt(value, depth + 1);
			}
			return '';
		}

		var direct = pickAlt(payload);
		var nestedKeys = ['result', 'output', 'response'];
		var index;
		if (direct) {
			return direct;
		}
		if (payload.data && typeof payload.data === 'object') {
			direct = pickAlt(payload.data);
			if (direct) {
				return direct;
			}
		}
		for (index = 0; index < nestedKeys.length; index++) {
			if (payload[nestedKeys[index]] && typeof payload[nestedKeys[index]] === 'object') {
				direct = pickAlt(payload[nestedKeys[index]]);
				if (direct) {
					return direct;
				}
			}
		}
		return '';
	}

	function buildRegenerateSingleRequest(settings) {
		var options = settings || {};
		var attachmentId = parseInt(options.attachmentId, 10) || 0;
		var requestKey = options.requestKey || ('library-' + attachmentId + '-' + Date.now());
		var ajaxUrl = getAjaxUrl(options.config);

		if (!ajaxUrl) {
			return null;
		}

		return {
			url: ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: 'beepbeepai_regenerate_single',
				attachment_id: attachmentId,
				request_key: requestKey,
				nonce: getNonce(options.config)
			},
			timeout: options.timeout || 120000
		};
	}

	function buildBulkQueueRequest(settings) {
		var options = settings || {};
		var ajaxUrl = getAjaxUrl(options.config);

		if (!ajaxUrl) {
			return null;
		}

		return {
			url: ajaxUrl,
			method: 'POST',
			data: {
				action: 'beepbeepai_bulk_queue',
				attachment_ids: options.ids || [],
				source: options.source || 'bulk',
				skip_schedule: options.skipSchedule ? '1' : '0',
				nonce: getNonce(options.config)
			},
			dataType: 'json'
		};
	}

	function normalizeBulkQueueResponse(response, ids, defaultMessage) {
		var data = response && response.data && typeof response.data === 'object' ? response.data : {};
		var errorMessage = defaultMessage || 'Failed to queue images';
		var errorCode = null;
		var errorRemaining = null;

		if (response && response.success) {
			return {
				success: true,
				queued: data.queued || 0,
				error: null,
				processedIds: ids ? ids.slice(0) : [],
				responseData: data
			};
		}

		if (response && response.data && typeof response.data === 'object') {
			errorMessage = response.data.message || errorMessage;
			errorCode = response.data.code || null;
			if (response.data.remaining !== undefined && response.data.remaining !== null) {
				errorRemaining = parseInt(response.data.remaining, 10);
			}
		}

		return {
			success: false,
			queued: 0,
			error: {
				message: errorMessage,
				code: errorCode,
				remaining: errorRemaining
			},
			processedIds: ids ? ids.slice(0) : [],
			responseData: data
		};
	}

	function normalizeBulkQueueXhrError(xhr, defaultMessage) {
		var errorData = null;
		try {
			var parsed = xhr && xhr.responseText ? JSON.parse(xhr.responseText) : null;
			if (parsed && parsed.data) {
				errorData = {
					message: parsed.data.message || defaultMessage || 'Failed to queue images',
					code: parsed.data.code || null,
					remaining: parsed.data.remaining || null
				};
			}
		} catch (parseErr) {
			/* ignore non-JSON error responses */
		}

		if (xhr && xhr.status === 403) {
			return errorData || {
				message: 'Authentication error. Please refresh the page and try again.'
			};
		}

		return errorData && errorData.message ? errorData : null;
	}

	function buildInlineGenerateRequest(settings) {
		var options = settings || {};
		var ajaxUrl = getAjaxUrl(options.config);

		if (!ajaxUrl) {
			return null;
		}

		return {
			url: ajaxUrl,
			method: 'POST',
			dataType: 'json',
			timeout: options.timeout || 25000,
			data: {
				action: 'beepbeepai_inline_generate',
				attachment_ids: [parseInt(options.id, 10) || 0],
				nonce: getNonce(options.config)
			}
		};
	}

	function normalizeInlineGenerateResponse(response, id, messages) {
		var labels = messages || {};
		var fallbackTitle = labels.title || ('Image #' + id);
		var failedMessage = labels.failed || 'Failed to generate alt text.';
		var unexpectedMessage = labels.unexpected || 'Unexpected response from server. Response structure does not match expected format.';
		var data = response && response.data ? response.data : null;
		var first;
		var payload;
		var normalizedId = String(id || '');
		var altText;

		function getUpdatedImageAlt(envelope) {
			var rows = envelope && Array.isArray(envelope.updated_images) ? envelope.updated_images : [];
			var index;
			var row;

			for (index = 0; index < rows.length; index++) {
				row = rows[index] || {};
				if (
					(!normalizedId || String(row.id || row.attachment_id || row.attachmentId || '') === normalizedId) &&
					row.alt_text
				) {
					return String(row.alt_text || '').trim();
				}
			}

			return '';
		}

		if (response && response.success) {
			if (data && data.results && Array.isArray(data.results)) {
				first = data.results[0];
				if (first && first.success) {
					altText = String(first.alt_text || '').trim() || getUpdatedImageAlt(data);
					if (!altText) {
						return {
							success: false,
							message: first.message || failedMessage,
							code: first.code || 'missing_alt_text',
							remaining: first.remaining !== undefined ? first.remaining : null,
							retry_after: first.retry_after !== undefined ? first.retry_after : null,
							usage: first.usage || data.usage || null
						};
					}
					payload = {
						meta: first.meta || data.meta || null,
						usage: first.usage || data.usage || null
					};
					return {
						success: true,
						kind: 'inline_result',
						id: id,
						alt: altText,
						title: first.title || fallbackTitle,
						usage: payload.usage || null,
						payload: payload,
						envelope: data
					};
				}
				return {
					success: false,
					message: first && first.message
						? first.message
						: (first && first.code && typeof labels.failedWithCode === 'function' ? labels.failedWithCode(first.code) : failedMessage),
					code: first && first.code ? first.code : 'generation_failed',
					remaining: first && first.remaining !== undefined ? first.remaining : null,
					retry_after: first && first.retry_after !== undefined ? first.retry_after : null,
					usage: first && first.usage ? first.usage : null
				};
			}

				if (data && data.alt_text) {
					altText = String(data.alt_text || '').trim();
					if (!altText) {
						return { success: false, message: failedMessage, code: 'missing_alt_text' };
					}
					payload = {
						meta: data.meta || null,
						usage: data.usage || null
				};
				return {
						success: true,
						kind: 'direct_alt',
						id: id,
						alt: altText,
						title: fallbackTitle,
					usage: payload.usage || null,
					payload: payload,
					envelope: data
				};
			}

			if (data && data.message) {
				return { success: false, message: data.message };
			}
		}

		if (response && response.success === false) {
			data = data || {};
			return {
				success: false,
				message: data.message || response.message || failedMessage,
				code: data.code || response.code || 'api_error',
				remaining: data.remaining !== undefined ? data.remaining : null,
				retry_after: data.retry_after !== undefined ? data.retry_after : null,
				usage: data.usage || null
			};
		}

		return { success: false, message: unexpectedMessage, code: 'unexpected_response' };
	}

	function normalizeInlineGenerateXhrError(xhr, messages) {
		var labels = messages || {};
		var message = labels.requestFailed || 'Request failed';
		var errorCode = null;
		var errorUsage = null;
		var errorRemaining = null;
		var retryAfter = null;

		if (xhr && xhr.responseJSON) {
			if (xhr.responseJSON.data && xhr.responseJSON.data.message) {
				message = xhr.responseJSON.data.message;
			} else if (xhr.responseJSON.message) {
				message = xhr.responseJSON.message;
			}
			if (xhr.responseJSON.data && xhr.responseJSON.data.code) {
				errorCode = xhr.responseJSON.data.code;
			} else if (xhr.responseJSON.code) {
				errorCode = xhr.responseJSON.code;
			}
			if (xhr.responseJSON.data && xhr.responseJSON.data.usage) {
				errorUsage = xhr.responseJSON.data.usage;
			}
			if (xhr.responseJSON.data && xhr.responseJSON.data.remaining !== undefined) {
				errorRemaining = xhr.responseJSON.data.remaining;
			}
			if (xhr.responseJSON.data && xhr.responseJSON.data.retry_after !== undefined) {
				retryAfter = xhr.responseJSON.data.retry_after;
			}
		} else if (xhr && xhr.statusText === 'timeout') {
			message = labels.timeout || 'The request took too long and was stopped. Please try again.';
			errorCode = 'request_timeout';
		} else if (xhr && xhr.status === 0) {
			message = labels.network || 'Network error: Unable to connect to server. Please check your internet connection.';
		} else if (xhr && xhr.status === 404) {
			message = labels.notFound || 'AJAX endpoint not found. The plugin may need to be reactivated.';
		} else if (xhr && xhr.status === 500) {
			message = labels.server || 'Server error occurred. Please check your WordPress error logs.';
		} else if (xhr && xhr.status === 200) {
			message = labels.invalid || 'Server returned an invalid response. Please check the browser console for details.';
		} else if (xhr && xhr.status) {
			message = labels.status ? labels.status(xhr.status) : ('Request failed with status ' + xhr.status);
		}

		return {
			message: message,
			code: errorCode,
			remaining: errorRemaining,
			retry_after: retryAfter,
			usage: errorUsage
		};
	}

	window.BBAIAltLibraryApi = {
		buildBulkQueueRequest: buildBulkQueueRequest,
		buildInlineGenerateRequest: buildInlineGenerateRequest,
		buildRegenerateSingleRequest: buildRegenerateSingleRequest,
		extractAltText: extractAltText,
		getAjaxUrl: getAjaxUrl,
		getNonce: getNonce,
		isSuccess: isSuccess,
		normalizeBulkQueueResponse: normalizeBulkQueueResponse,
		normalizeBulkQueueXhrError: normalizeBulkQueueXhrError,
		normalizeInlineGenerateResponse: normalizeInlineGenerateResponse,
		normalizeInlineGenerateXhrError: normalizeInlineGenerateXhrError,
		normalizePayload: normalizePayload
	};
}(window));
