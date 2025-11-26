/**
 * Settings Page Handler
 *
 * Handles settings form submission and validation.
 */

class OpttiSettingsPage {
	constructor( apiClient, notifications ) {
		this.apiClient = apiClient;
		this.notifications = notifications;
		this.init();
	}

	/**
	 * Initialize settings page handler.
	 */
	init() {
		// Find all forms with data-optti-settings attribute.
		const forms = document.querySelectorAll( '[data-optti-settings]' );
		forms.forEach( form => {
			form.addEventListener( 'submit', ( e ) => {
				this.handleSubmit( e, form );
			} );
		} );
	}

	/**
	 * Handle form submission.
	 *
	 * @param {Event} event Submit event.
	 * @param {HTMLFormElement} form Form element.
	 */
	async handleSubmit( event, form ) {
		event.preventDefault();

		const formData = new FormData( form );
		const data = {};
		for ( const [ key, value ] of formData.entries() ) {
			data[ key ] = value;
		}

		// Validate required fields.
		const requiredFields = form.querySelectorAll( '[required]' );
		let isValid = true;
		requiredFields.forEach( field => {
			if ( ! field.value.trim() ) {
				isValid = false;
				field.classList.add( 'optti-form__field--error' );
			} else {
				field.classList.remove( 'optti-form__field--error' );
			}
		} );

		if ( ! isValid ) {
			this.notifications.showError( 'Please fill in all required fields.' );
			return;
		}

		// Show loading state.
		const submitButton = form.querySelector( 'button[type="submit"]' );
		const originalText = submitButton.textContent;
		submitButton.disabled = true;
		submitButton.textContent = 'Saving...';

		try {
			// Submit via REST API.
			const endpoint = form.dataset.opttiSettingsEndpoint || '/wp/v2/settings';
			const response = await this.apiClient.request( endpoint, 'POST', data );

			this.notifications.showSuccess( 'Settings saved successfully.' );
			form.dispatchEvent( new CustomEvent( 'optti:settings:saved', { detail: response } ) );
		} catch ( error ) {
			this.notifications.showError( error.message || 'Failed to save settings.' );
		} finally {
			submitButton.disabled = false;
			submitButton.textContent = originalText;
		}
	}
}

export default OpttiSettingsPage;

