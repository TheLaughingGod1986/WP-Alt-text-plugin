/**
 * Admin Header Component
 *
 * Shared admin header for all Optti plugins.
 */

class OpttiAdminHeader {
	constructor( container, config ) {
		this.container = container;
		this.config = config;
		this.init();
	}

	/**
	 * Initialize header.
	 */
	init() {
		if ( ! this.container ) {
			return;
		}

		this.render();
	}

	/**
	 * Render header.
	 */
	render() {
		const { pluginName, planName, renewalDate, hasLicense, upgradeUrl } = this.config;

		this.container.innerHTML = `
			<div class="optti-admin-header">
				<div class="optti-admin-header__brand">
					<img src="${ this.config.logoUrl || '' }" alt="Optti" class="optti-admin-header__logo" />
					<span class="optti-admin-header__plugin-name">${ pluginName || '' }</span>
				</div>
				<div class="optti-admin-header__actions">
					${ hasLicense ? `
						<div class="optti-admin-header__plan">
							<span class="optti-admin-header__plan-name">${ planName || 'Free' }</span>
							${ renewalDate ? `<span class="optti-admin-header__renewal">Renews: ${ renewalDate }</span>` : '' }
						</div>
						<a href="${ this.config.accountUrl || '#' }" class="optti-button optti-button--outline">Manage Account</a>
					` : `
						<a href="${ upgradeUrl || '#' }" class="optti-button optti-button--primary">Upgrade</a>
					` }
				</div>
			</div>
		`;
	}
}

export default OpttiAdminHeader;

