/**
 * Event Bus
 *
 * Publish-subscribe pattern for decoupled component communication.
 * Allows components to communicate without direct dependencies.
 *
 * @package BeepBeep\AltText
 * @since   5.0.0
 */

export class EventBus {
	constructor() {
		/**
		 * Event listeners map.
		 * @type {Map<string, Set<Function>>}
		 */
		this.listeners = new Map();
	}

	/**
	 * Subscribe to an event.
	 *
	 * @param {string} event - Event name.
	 * @param {Function} callback - Event handler.
	 * @returns {Function} Unsubscribe function.
	 */
	on(event, callback) {
		if (!this.listeners.has(event)) {
			this.listeners.set(event, new Set());
		}

		this.listeners.get(event).add(callback);

		// Return unsubscribe function.
		return () => {
			this.listeners.get(event)?.delete(callback);
		};
	}

	/**
	 * Subscribe to event once.
	 *
	 * Automatically unsubscribes after first execution.
	 *
	 * @param {string} event - Event name.
	 * @param {Function} callback - Event handler.
	 * @returns {Function} Unsubscribe function.
	 */
	once(event, callback) {
		const unsubscribe = this.on(event, (...args) => {
			callback(...args);
			unsubscribe();
		});

		return unsubscribe;
	}

	/**
	 * Emit an event.
	 *
	 * Calls all registered listeners with the provided data.
	 *
	 * @param {string} event - Event name.
	 * @param {*} data - Event data.
	 */
	emit(event, data) {
		const callbacks = this.listeners.get(event);
		if (!callbacks) return;

		callbacks.forEach(callback => {
			try {
				callback(data);
			} catch (error) {
				console.error(`Error in event listener for ${event}:`, error);
			}
		});
	}

	/**
	 * Remove all listeners for an event.
	 *
	 * @param {string} event - Event name.
	 */
	off(event) {
		this.listeners.delete(event);
	}

	/**
	 * Clear all event listeners.
	 */
	clear() {
		this.listeners.clear();
	}

	/**
	 * Get all registered events.
	 *
	 * @returns {string[]} Event names.
	 */
	getEvents() {
		return Array.from(this.listeners.keys());
	}

	/**
	 * Get listener count for an event.
	 *
	 * @param {string} event - Event name.
	 * @returns {number} Listener count.
	 */
	listenerCount(event) {
		return this.listeners.get(event)?.size || 0;
	}
}

// Create global instance.
export const eventBus = new EventBus();
