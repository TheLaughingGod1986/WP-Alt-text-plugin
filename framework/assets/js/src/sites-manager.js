/**
 * Sites Manager Component
 *
 * Manages connected sites for agency/multi-site users.
 */

class OpttiSitesManager {
	constructor( container, apiClient ) {
		this.container = container;
		this.apiClient = apiClient;
		this.init();
	}

	/**
	 * Initialize sites manager.
	 */
	async init() {
		if ( ! this.container ) {
			return;
		}

		try {
			const userId = this.container.dataset.userId || '';
			const data = await this.apiClient.request( `/api/sites?user_id=${ userId }`, 'GET' );
			this.render( data );
		} catch ( error ) {
			console.error( 'Failed to load sites:', error );
		}
	}

	/**
	 * Render sites manager.
	 *
	 * @param {object} data Sites data.
	 */
	render( data ) {
		const { sites = [] } = data;

		if ( sites.length === 0 ) {
			this.container.innerHTML = '<p>No connected sites.</p>';
			return;
		}

		const rows = sites.map( site => `
			<tr>
				<td>${ site.domain || '' }</td>
				<td>${ site.pluginType || '' }</td>
				<td><span class="optti-badge optti-badge--${ site.status === 'active' ? 'success' : 'error' }">${ site.status || '' }</span></td>
				<td>${ site.lastSync || 'Never' }</td>
				<td>
					<button class="optti-button optti-button--ghost" data-action="disconnect" data-site-id="${ site.id }">Disconnect</button>
					<button class="optti-button optti-button--ghost" data-action="manage" data-site-id="${ site.id }">Manage</button>
				</td>
			</tr>
		` ).join( '' );

		this.container.innerHTML = `
			<div class="optti-sites-manager">
				<table class="optti-table optti-table--bordered">
					<thead>
						<tr>
							<th>Domain</th>
							<th>Plugin Type</th>
							<th>Status</th>
							<th>Last Sync</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						${ rows }
					</tbody>
				</table>
			</div>
		`;

		// Add event listeners.
		this.container.querySelectorAll( '[data-action]' ).forEach( button => {
			button.addEventListener( 'click', ( e ) => {
				this.handleAction( e, button );
			} );
		} );
	}

	/**
	 * Handle action button click.
	 *
	 * @param {Event} event Click event.
	 * @param {HTMLElement} button Button element.
	 */
	async handleAction( event, button ) {
		const action = button.dataset.action;
		const siteId = button.dataset.siteId;

		if ( action === 'disconnect' ) {
			if ( ! confirm( 'Are you sure you want to disconnect this site?' ) ) {
				return;
			}

			try {
				await this.apiClient.request( `/api/sites/${ siteId }/disconnect`, 'POST' );
				this.init(); // Reload.
			} catch ( error ) {
				console.error( 'Failed to disconnect site:', error );
			}
		} else if ( action === 'manage' ) {
			// Navigate to site management page.
			window.location.href = `?page=optti-sites&site_id=${ siteId }`;
		}
	}
}

export default OpttiSitesManager;

