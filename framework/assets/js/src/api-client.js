/**
 * API Client
 *
 * Wraps REST and backend API calls for the Optti framework.
 */

class OpttiApiClient {
	constructor( config ) {
		this.apiBase = opttiApi?.baseUrl ?? config.apiBase ?? OPTTI_PLUGIN?.apiBase ?? 'https://alttext-ai-backend.onrender.com';
		this.nonce = config.nonce || '';
		this.pluginSlug = config.pluginSlug || '';
	}

	/**
	 * Make API request.
	 *
	 * @param {string} endpoint API endpoint.
	 * @param {string} method HTTP method.
	 * @param {object} data Request data.
	 * @param {object} options Additional options.
	 * @return {Promise} Response promise.
	 */
	async request( endpoint, method = 'GET', data = null, options = {} ) {
		const url = `${ this.apiBase }/${ endpoint.replace( /^\//, '' ) }`;
		const headers = {
			'Content-Type': 'application/json',
			'X-WP-Nonce': this.nonce,
		};

		if ( options.includeAuth !== false ) {
			// Add authentication headers if needed.
			const userInfo = window.OPTTI_PLUGIN?.userInfo;
			if ( userInfo ) {
				headers['X-User-ID'] = userInfo.id || '';
			}
		}

		const config = {
			method,
			headers,
			credentials: 'same-origin',
		};

		if ( data && [ 'POST', 'PUT', 'PATCH' ].includes( method ) ) {
			config.body = JSON.stringify( data );
		}

		try {
			const response = await fetch( url, config );
			const result = await response.json();

			if ( ! response.ok ) {
				throw new Error( result.error || result.message || 'API request failed' );
			}

			return result;
		} catch ( error ) {
			console.error( 'API request failed:', error );
			throw error;
		}
	}

	/**
	 * Login user.
	 *
	 * @param {string} email Email address.
	 * @param {string} password Password.
	 * @return {Promise} Login response.
	 */
	async login( email, password ) {
		return this.request( '/auth/login', 'POST', { email, password }, { includeAuth: false } );
	}

	/**
	 * Register user.
	 *
	 * @param {string} email Email address.
	 * @param {string} password Password.
	 * @return {Promise} Registration response.
	 */
	async register( email, password ) {
		return this.request( '/auth/register', 'POST', { email, password }, { includeAuth: false } );
	}

	/**
	 * Get usage summary.
	 *
	 * @param {string} licenseKey License key.
	 * @param {string} siteUrl Site URL.
	 * @return {Promise} Usage data.
	 */
	async fetchUsageSummary( licenseKey, siteUrl ) {
		return this.request( '/usage', 'GET', null, {
			headers: {
				'X-License-Key': licenseKey,
				'X-Site-URL': siteUrl,
			},
		} );
	}

	/**
	 * Get usage (alias for fetchUsageSummary).
	 *
	 * @param {string} licenseKey License key.
	 * @param {string} siteUrl Site URL.
	 * @return {Promise} Usage data.
	 */
	async getUsage( licenseKey, siteUrl ) {
		return this.fetchUsageSummary( licenseKey, siteUrl );
	}

	/**
	 * Create checkout session.
	 *
	 * @param {string} priceId Price ID.
	 * @param {string} successUrl Success URL.
	 * @param {string} cancelUrl Cancel URL.
	 * @return {Promise} Checkout session.
	 */
	async createCheckoutSession( priceId, successUrl = null, cancelUrl = null ) {
		return this.request( '/billing/checkout', 'POST', {
			price_id: priceId,
			success_url: successUrl || window.location.href,
			cancel_url: cancelUrl || window.location.href,
		} );
	}

	/**
	 * Get billing portal URL.
	 *
	 * @param {string} userId User ID.
	 * @return {Promise} Portal URL.
	 */
	async openBillingPortal( userId ) {
		const response = await this.request( '/billing/portal', 'POST', {
			return_url: window.location.href,
		} );
		if ( response.url ) {
			window.location.href = response.url;
		}
		return response;
	}

	/**
	 * Get billing portal URL (alias for openBillingPortal).
	 *
	 * @param {string} userId User ID.
	 * @return {Promise} Portal URL.
	 */
	async getBillingPortalUrl( userId ) {
		const response = await this.request( '/billing/portal', 'POST', {
			return_url: window.location.href,
		} );
		return response.url;
	}

	/**
	 * Get invoices.
	 *
	 * @param {string} userId User ID.
	 * @param {number} limit Limit.
	 * @return {Promise} Invoices data.
	 */
	async fetchInvoices( userId, limit = 10 ) {
		return this.request( `/billing/invoices?limit=${ limit }`, 'GET' );
	}

	/**
	 * Get invoices (alias for fetchInvoices).
	 *
	 * @param {string} userId User ID.
	 * @param {number} limit Limit.
	 * @return {Promise} Invoices data.
	 */
	async getInvoices( userId, limit = 10 ) {
		return this.fetchInvoices( userId, limit );
	}

	/**
	 * Get plugin metadata.
	 *
	 * @param {string} userId User ID.
	 * @return {Promise} Plugin metadata.
	 */
	async fetchPluginMeta( userId ) {
		return this.request( `/plugins?user_id=${ userId }`, 'GET' );
	}

	/**
	 * Get plugin metadata (alias for fetchPluginMeta).
	 *
	 * @param {string} userId User ID.
	 * @return {Promise} Plugin metadata.
	 */
	async getPluginMeta( userId ) {
		return this.fetchPluginMeta( userId );
	}

	/**
	 * Record analytics event.
	 *
	 * @param {string} eventName Event name.
	 * @param {object} payload Event payload.
	 * @return {Promise} Response.
	 */
	async recordEvent( eventName, payload = {} ) {
		return this.request( '/analytics/events', 'POST', {
			event_name: eventName,
			plugin_slug: this.pluginSlug,
			payload: payload,
		} );
	}
}

export default OpttiApiClient;

