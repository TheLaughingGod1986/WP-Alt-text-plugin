/**
 * Admin Core
 *
 * Bootstrap for admin functionality.
 */

import OpttiApiClient from './api-client.js';
import OpttiNotifications from './notifications.js';
import OpttiUsageWidgets from './usage-widgets.js';
import OpttiSettingsPage from './settings-page.js';
import OpttiAdminHeader from './admin-header.js';
import OpttiBenefitsPanel from './benefits-panel.js';
import OpttiSitesManager from './sites-manager.js';

class OpttiAdminCore {
	constructor() {
		this.config = window.OPTTI_PLUGIN || {};
		this.apiClient = null;
		this.notifications = null;
		this.settingsPage = null;
	}

	/**
	 * Initialize admin core.
	 */
	init() {
		// Initialize API client.
		this.apiClient = new OpttiApiClient( {
			apiBase: opttiApi?.baseUrl ?? this.config.apiBase ?? OPTTI_PLUGIN?.apiBase ?? 'https://alttext-ai-backend.onrender.com',
			nonce: this.config.nonce || '',
			pluginSlug: this.config.pluginSlug || '',
		} );

		// Initialize notifications.
		this.notifications = new OpttiNotifications();

		// Initialize settings page handler.
		this.settingsPage = new OpttiSettingsPage( this.apiClient, this.notifications );

		// Initialize usage widgets if containers exist.
		this.initUsageWidgets();

		// Initialize shared UX components.
		this.initUXComponents();

		// Expose to global scope for backward compatibility.
		window.OpttiAdmin = {
			api: this.apiClient,
			notifications: this.notifications,
			widgets: OpttiUsageWidgets,
		};
	}

	/**
	 * Initialize shared UX components.
	 */
	initUXComponents() {
		// Initialize admin header.
		const headerContainer = document.getElementById( 'optti-admin-header' );
		if ( headerContainer ) {
			new OpttiAdminHeader( headerContainer, {
				pluginName: this.config.pluginName || '',
				planName: this.config.planName || '',
				renewalDate: this.config.renewalDate || '',
				hasLicense: this.config.hasLicense || false,
				upgradeUrl: this.config.upgradeUrl || '#',
				accountUrl: this.config.accountUrl || '#',
				logoUrl: this.config.logoUrl || '',
			} );
		}

		// Initialize benefits panel.
		const benefitsContainer = document.getElementById( 'optti-benefits-panel' );
		if ( benefitsContainer ) {
			new OpttiBenefitsPanel( benefitsContainer, this.apiClient );
		}

		// Initialize sites manager.
		const sitesContainer = document.getElementById( 'optti-sites-manager' );
		if ( sitesContainer ) {
			new OpttiSitesManager( sitesContainer, this.apiClient );
		}
	}

	/**
	 * Initialize usage widgets.
	 */
	initUsageWidgets() {
		// Find all usage widget containers.
		const creditsContainer = document.querySelector( '[data-optti-widget="credits"]' );
		if ( creditsContainer && this.config.usageData ) {
			OpttiUsageWidgets.renderCreditsWidget( creditsContainer, this.config.usageData );
		}

		const imagesContainer = document.querySelector( '[data-optti-widget="images"]' );
		if ( imagesContainer && this.config.usageData ) {
			OpttiUsageWidgets.renderImagesProcessedWidget( imagesContainer, this.config.usageData );
		}

		const chartContainer = document.querySelector( '[data-optti-widget="chart"]' );
		if ( chartContainer && this.config.chartData ) {
			OpttiUsageWidgets.renderUsageChart( chartContainer, this.config.chartData );
		}
	}
}

// Initialize on DOM ready.
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', () => {
		const adminCore = new OpttiAdminCore();
		adminCore.init();
	} );
} else {
	const adminCore = new OpttiAdminCore();
	adminCore.init();
}

export default OpttiAdminCore;

