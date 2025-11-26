/**
 * Benefits Panel Component
 *
 * Displays plugin benefits and statistics.
 */

class OpttiBenefitsPanel {
	constructor( container, apiClient ) {
		this.container = container;
		this.apiClient = apiClient;
		this.init();
	}

	/**
	 * Initialize benefits panel.
	 */
	async init() {
		if ( ! this.container ) {
			return;
		}

		const pluginSlug = this.container.dataset.pluginSlug || '';
		if ( ! pluginSlug ) {
			return;
		}

		try {
			const data = await this.apiClient.request( `/api/benefits?plugin_slug=${ pluginSlug }`, 'GET' );
			this.render( data );
		} catch ( error ) {
			console.error( 'Failed to load benefits:', error );
		}
	}

	/**
	 * Render benefits panel.
	 *
	 * @param {object} data Benefits data.
	 */
	render( data ) {
		const { imagesOptimized = 0, hoursSaved = 0, bandwidthSaved = 0 } = data;

		this.container.innerHTML = `
			<div class="optti-benefits-panel">
				<h3 class="optti-benefits-panel__title">Your Impact</h3>
				<div class="optti-benefits-panel__stats">
					<div class="optti-benefits-panel__stat">
						<div class="optti-benefits-panel__stat-value">${ imagesOptimized.toLocaleString() }</div>
						<div class="optti-benefits-panel__stat-label">Images Optimized</div>
					</div>
					<div class="optti-benefits-panel__stat">
						<div class="optti-benefits-panel__stat-value">${ hoursSaved }</div>
						<div class="optti-benefits-panel__stat-label">Hours Saved</div>
					</div>
					<div class="optti-benefits-panel__stat">
						<div class="optti-benefits-panel__stat-value">${ bandwidthSaved }GB</div>
						<div class="optti-benefits-panel__stat-label">Bandwidth Saved</div>
					</div>
				</div>
			</div>
		`;
	}
}

export default OpttiBenefitsPanel;

