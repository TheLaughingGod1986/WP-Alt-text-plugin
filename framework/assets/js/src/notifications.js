/**
 * Notifications
 *
 * Generic toast notification system for admin UI.
 */

class OpttiNotifications {
	constructor() {
		this.container = null;
		this.notifications = new Map();
		this.init();
	}

	/**
	 * Initialize notification container.
	 */
	init() {
		// Create container if it doesn't exist.
		if ( ! document.getElementById( 'optti-notifications' ) ) {
			this.container = document.createElement( 'div' );
			this.container.id = 'optti-notifications';
			this.container.className = 'optti-notifications';
			document.body.appendChild( this.container );
		} else {
			this.container = document.getElementById( 'optti-notifications' );
		}
	}

	/**
	 * Show success notification.
	 *
	 * @param {string} message Message to display.
	 * @param {number} duration Duration in milliseconds.
	 * @return {string} Notification ID.
	 */
	showSuccess( message, duration = 5000 ) {
		return this.show( message, 'success', duration );
	}

	/**
	 * Show error notification.
	 *
	 * @param {string} message Message to display.
	 * @param {number} duration Duration in milliseconds.
	 * @return {string} Notification ID.
	 */
	showError( message, duration = 7000 ) {
		return this.show( message, 'error', duration );
	}

	/**
	 * Show info notification.
	 *
	 * @param {string} message Message to display.
	 * @param {number} duration Duration in milliseconds.
	 * @return {string} Notification ID.
	 */
	showInfo( message, duration = 5000 ) {
		return this.show( message, 'info', duration );
	}

	/**
	 * Show warning notification.
	 *
	 * @param {string} message Message to display.
	 * @param {number} duration Duration in milliseconds.
	 * @return {string} Notification ID.
	 */
	showWarning( message, duration = 6000 ) {
		return this.show( message, 'warning', duration );
	}

	/**
	 * Show notification.
	 *
	 * @param {string} message Message to display.
	 * @param {string} type Notification type.
	 * @param {number} duration Duration in milliseconds.
	 * @return {string} Notification ID.
	 */
	show( message, type = 'info', duration = 5000 ) {
		const id = `optti-notification-${ Date.now() }-${ Math.random().toString( 36 ).substr( 2, 9 ) }`;
		const notification = document.createElement( 'div' );
		notification.id = id;
		notification.className = `optti-notification optti-notification--${ type }`;
		notification.setAttribute( 'role', 'alert' );

		notification.innerHTML = `
			<div class="optti-notification__content">
				<span class="optti-notification__message">${ this.escapeHtml( message ) }</span>
				<button class="optti-notification__dismiss" aria-label="Dismiss">
					<span aria-hidden="true">&times;</span>
			</button>
			</div>
		`;

		// Add click handler for dismiss button.
		const dismissBtn = notification.querySelector( '.optti-notification__dismiss' );
		dismissBtn.addEventListener( 'click', () => {
			this.dismiss( id );
		} );

		this.container.appendChild( notification );
		this.notifications.set( id, notification );

		// Auto-dismiss after duration.
		if ( duration > 0 ) {
			setTimeout( () => {
				this.dismiss( id );
			}, duration );
		}

		// Trigger animation.
		setTimeout( () => {
			notification.classList.add( 'optti-notification--visible' );
		}, 10 );

		return id;
	}

	/**
	 * Dismiss notification.
	 *
	 * @param {string} id Notification ID.
	 */
	dismiss( id ) {
		const notification = this.notifications.get( id );
		if ( ! notification ) {
			return;
		}

		notification.classList.remove( 'optti-notification--visible' );
		setTimeout( () => {
			if ( notification.parentNode ) {
				notification.parentNode.removeChild( notification );
			}
			this.notifications.delete( id );
		}, 300 );
	}

	/**
	 * Escape HTML.
	 *
	 * @param {string} text Text to escape.
	 * @return {string} Escaped text.
	 */
	escapeHtml( text ) {
		const div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
	}
}

export default OpttiNotifications;

