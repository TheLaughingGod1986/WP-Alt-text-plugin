/**
 * API Client Tests
 *
 * Tests for OpttiApiClient class.
 */

import OpttiApiClient from '../../assets/js/src/api-client.js';

describe( 'OpttiApiClient', () => {
	let apiClient;

	beforeEach( () => {
		apiClient = new OpttiApiClient( {
			apiBase: 'https://api.example.com',
			nonce: 'test-nonce',
			pluginSlug: 'test-plugin',
		} );
	} );

	test( 'should initialize with config', () => {
		expect( apiClient.apiBase ).toBe( 'https://api.example.com' );
		expect( apiClient.nonce ).toBe( 'test-nonce' );
		expect( apiClient.pluginSlug ).toBe( 'test-plugin' );
	} );

	// Add more tests as needed.
} );

