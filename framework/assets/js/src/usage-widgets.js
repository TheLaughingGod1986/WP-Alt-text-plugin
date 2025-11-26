/**
 * Usage Widgets
 *
 * Reusable widgets for displaying usage statistics.
 */

class OpttiUsageWidgets {
	/**
	 * Render credits widget.
	 *
	 * @param {HTMLElement} container Container element.
	 * @param {object} data Usage data.
	 */
	static renderCreditsWidget( container, data ) {
		const { used = 0, limit = 0, remaining = 0 } = data;
		const percentage = limit > 0 ? Math.round( ( used / limit ) * 100 ) : 0;

		container.innerHTML = `
			<div class="optti-credits-widget">
				<div class="optti-credits-widget__header">
					<h3>Credits Remaining</h3>
				</div>
				<div class="optti-credits-widget__content">
					<div class="optti-credits-widget__value">${ remaining.toLocaleString() }</div>
					<div class="optti-credits-widget__label">of ${ limit.toLocaleString() } used</div>
					<div class="optti-credits-widget__progress">
						<div class="optti-credits-widget__progress-bar" style="width: ${ percentage }%"></div>
					</div>
				</div>
			</div>
		`;
	}

	/**
	 * Render images processed widget.
	 *
	 * @param {HTMLElement} container Container element.
	 * @param {object} data Usage data.
	 */
	static renderImagesProcessedWidget( container, data ) {
		const { processed = 0, total = 0 } = data;
		const percentage = total > 0 ? Math.round( ( processed / total ) * 100 ) : 0;

		container.innerHTML = `
			<div class="optti-images-widget">
				<div class="optti-images-widget__header">
					<h3>Images Processed</h3>
				</div>
				<div class="optti-images-widget__content">
					<div class="optti-images-widget__value">${ processed.toLocaleString() }</div>
					<div class="optti-images-widget__label">of ${ total.toLocaleString() } total</div>
					<div class="optti-images-widget__progress">
						<div class="optti-images-widget__progress-bar" style="width: ${ percentage }%"></div>
					</div>
				</div>
			</div>
		`;
	}

	/**
	 * Render usage chart.
	 *
	 * @param {HTMLElement} container Container element.
	 * @param {object} data Chart data.
	 */
	static renderUsageChart( container, data ) {
		const { labels = [], datasets = [] } = data;

		// Simple bar chart implementation.
		// For production, consider using Chart.js or similar.
		const maxValue = Math.max( ...datasets.flatMap( d => d.data || [] ), 1 );
		const bars = labels.map( ( label, index ) => {
			const value = datasets[0]?.data[ index ] || 0;
			const height = ( value / maxValue ) * 100;
			return `
				<div class="optti-chart__bar">
					<div class="optti-chart__bar-value" style="height: ${ height }%"></div>
					<div class="optti-chart__bar-label">${ label }</div>
				</div>
			`;
		} ).join( '' );

		container.innerHTML = `
			<div class="optti-chart">
				<div class="optti-chart__bars">
					${ bars }
				</div>
			</div>
		`;
	}
}

export default OpttiUsageWidgets;

