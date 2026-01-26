/**
 * HTTP Client
 *
 * WordPress AJAX client with automatic nonce handling.
 * Provides clean interface for making AJAX requests.
 *
 * @package BeepBeep\AltText
 * @since   5.0.0
 */

export class Http {
	/**
	 * Constructor.
	 *
	 * @param {Object} config - Configuration options.
	 */
	constructor(config = {}) {
		this.config = {
			baseUrl: window.bbaiData?.ajaxUrl || window.bbai_ajax?.ajaxurl || window.bbai_ajax?.ajax_url || '',
			nonce: window.bbaiData?.nonce || '',
			timeout: 30000,
			...config
		};
	}

	/**
	 * Make POST request.
	 *
	 * @param {string} action - WordPress AJAX action.
	 * @param {Object} data - Request data.
	 * @param {Object} options - Request options.
	 * @returns {Promise<Object>} Response data.
	 */
	async post(action, data = {}, options = {}) {
		const formData = new FormData();
		formData.append('action', action);
		formData.append('nonce', this.config.nonce);

		// Append data to FormData.
		for (const [key, value] of Object.entries(data)) {
			if (value instanceof File || value instanceof Blob) {
				formData.append(key, value);
			} else if (typeof value === 'object') {
				formData.append(key, JSON.stringify(value));
			} else {
				formData.append(key, value);
			}
		}

		const controller = new AbortController();
		const timeout = setTimeout(
			() => controller.abort(),
			options.timeout || this.config.timeout
		);

		try {
			const response = await fetch(this.config.baseUrl, {
				method: 'POST',
				body: formData,
				signal: controller.signal,
				credentials: 'same-origin',
				...options
			});

			clearTimeout(timeout);

			if (!response.ok) {
				throw new Error(`HTTP ${response.status}: ${response.statusText}`);
			}

			const json = await response.json();

			if (!json.success) {
				throw new Error(json.data?.message || 'Request failed');
			}

			return json.data;
		} catch (error) {
			if (error.name === 'AbortError') {
				throw new Error('Request timeout');
			}
			throw error;
		}
	}

	/**
	 * Make GET request (via POST with action).
	 *
	 * WordPress doesn't support true GET AJAX, so we use POST.
	 *
	 * @param {string} action - WordPress AJAX action.
	 * @param {Object} params - Query parameters.
	 * @param {Object} options - Request options.
	 * @returns {Promise<Object>} Response data.
	 */
	async get(action, params = {}, options = {}) {
		return this.post(action, params, options);
	}

	/**
	 * Upload file.
	 *
	 * @param {string} action - WordPress AJAX action.
	 * @param {File} file - File to upload.
	 * @param {Object} data - Additional data.
	 * @param {Function} onProgress - Progress callback.
	 * @returns {Promise<Object>} Response data.
	 */
	async upload(action, file, data = {}, onProgress = null) {
		return new Promise((resolve, reject) => {
			const xhr = new XMLHttpRequest();
			const formData = new FormData();

			formData.append('action', action);
			formData.append('nonce', this.config.nonce);
			formData.append('file', file);

			for (const [key, value] of Object.entries(data)) {
				formData.append(key, value);
			}

			// Track upload progress.
			if (onProgress) {
				xhr.upload.addEventListener('progress', (e) => {
					if (e.lengthComputable) {
						onProgress(Math.round((e.loaded / e.total) * 100));
					}
				});
			}

			xhr.addEventListener('load', () => {
				if (xhr.status === 200) {
					try {
						const json = JSON.parse(xhr.responseText);
						if (json.success) {
							resolve(json.data);
						} else {
							reject(new Error(json.data?.message || 'Upload failed'));
						}
					} catch (error) {
						reject(new Error('Invalid response'));
					}
				} else {
					reject(new Error(`HTTP ${xhr.status}: ${xhr.statusText}`));
				}
			});

			xhr.addEventListener('error', () => {
				reject(new Error('Network error'));
			});

			xhr.addEventListener('timeout', () => {
				reject(new Error('Request timeout'));
			});

			xhr.timeout = this.config.timeout;
			xhr.open('POST', this.config.baseUrl);
			xhr.send(formData);
		});
	}

	/**
	 * Set nonce for requests.
	 *
	 * @param {string} nonce - New nonce value.
	 */
	setNonce(nonce) {
		this.config.nonce = nonce;
	}

	/**
	 * Set base URL.
	 *
	 * @param {string} url - New base URL.
	 */
	setBaseUrl(url) {
		this.config.baseUrl = url;
	}
}

// Create global instance.
export const http = new Http();
