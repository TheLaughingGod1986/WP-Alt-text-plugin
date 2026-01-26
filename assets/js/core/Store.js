/**
 * Store
 *
 * Simple state management store with reactive updates.
 * Provides centralized state for the application.
 *
 * @package BeepBeep\AltText
 * @since   5.0.0
 */

export class Store {
	/**
	 * Constructor.
	 *
	 * @param {Object} initialState - Initial state object.
	 */
	constructor(initialState = {}) {
		/**
		 * Current state.
		 * @type {Object}
		 */
		this.state = initialState;

		/**
		 * State change listeners.
		 * @type {Set<Function>}
		 */
		this.listeners = new Set();

		/**
		 * Middleware functions.
		 * @type {Function[]}
		 */
		this.middlewares = [];
	}

	/**
	 * Get current state.
	 *
	 * Returns a shallow copy to prevent direct mutation.
	 *
	 * @returns {Object} Current state.
	 */
	getState() {
		return { ...this.state };
	}

	/**
	 * Update state.
	 *
	 * @param {Object|Function} updates - State updates or updater function.
	 */
	setState(updates) {
		const prevState = this.getState();

		// Support both object and function updates.
		const nextState = typeof updates === 'function'
			? updates(prevState)
			: { ...prevState, ...updates };

		// Run middlewares.
		let finalState = nextState;
		for (const middleware of this.middlewares) {
			finalState = middleware(prevState, finalState, this) || finalState;
		}

		this.state = finalState;

		// Notify listeners.
		this.listeners.forEach(listener => {
			try {
				listener(finalState, prevState);
			} catch (error) {
				console.error('Error in state listener:', error);
			}
		});
	}

	/**
	 * Subscribe to state changes.
	 *
	 * @param {Function} listener - State change callback.
	 * @returns {Function} Unsubscribe function.
	 */
	subscribe(listener) {
		this.listeners.add(listener);

		// Return unsubscribe function.
		return () => {
			this.listeners.delete(listener);
		};
	}

	/**
	 * Add middleware.
	 *
	 * Middleware receives (prevState, nextState, store) and can modify nextState.
	 *
	 * @param {Function} middleware - Middleware function.
	 */
	use(middleware) {
		this.middlewares.push(middleware);
	}

	/**
	 * Reset state to initial value.
	 *
	 * @param {Object} initialState - New initial state.
	 */
	reset(initialState = {}) {
		this.state = initialState;
		this.listeners.forEach(listener => {
			listener(initialState, {});
		});
	}

	/**
	 * Get specific state property.
	 *
	 * @param {string} path - Dot-notation path (e.g., 'user.name').
	 * @returns {*} Property value.
	 */
	get(path) {
		return path.split('.').reduce((obj, key) => obj?.[key], this.state);
	}

	/**
	 * Set specific state property.
	 *
	 * @param {string} path - Dot-notation path (e.g., 'user.name').
	 * @param {*} value - New value.
	 */
	set(path, value) {
		const keys = path.split('.');
		const lastKey = keys.pop();

		const updates = { ...this.state };
		let current = updates;

		for (const key of keys) {
			current[key] = { ...(current[key] || {}) };
			current = current[key];
		}

		current[lastKey] = value;
		this.setState(updates);
	}
}

/**
 * Create global store with initial state.
 */
export const store = new Store({
	user: null,
	license: null,
	usage: null,
	queue: {
		stats: {},
		jobs: []
	},
	ui: {
		loading: false,
		error: null,
		modal: null
	}
});
