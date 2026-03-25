/**
 * AI Valve — React hooks for REST API calls.
 *
 * Uses @wordpress/api-fetch which handles nonce middleware automatically
 * when enqueued as a dependency via @wordpress/scripts.
 */
import apiFetch from '@wordpress/api-fetch';

const BASE = '/ai-valve/v1';

/** GET /ai-valve/v1/usage */
export async function fetchUsage() {
	return apiFetch( { path: `${ BASE }/usage` } );
}

/** GET /ai-valve/v1/settings */
export async function fetchSettings() {
	return apiFetch( { path: `${ BASE }/settings` } );
}

/** POST /ai-valve/v1/settings */
export async function saveSettings( settings ) {
	return apiFetch( {
		path: `${ BASE }/settings`,
		method: 'POST',
		data: { settings },
	} );
}

/**
 * GET /ai-valve/v1/logs
 *
 * @param {Object} params Filter/pagination params.
 * @returns {{ items: Array, total: number, totalPages: number }}
 */
export async function fetchLogs( params = {} ) {
	const query = new URLSearchParams();
	for ( const [ key, value ] of Object.entries( params ) ) {
		if ( value !== undefined && value !== null && value !== '' ) {
			query.set( key, String( value ) );
		}
	}
	const qs = query.toString();
	const path = qs ? `${ BASE }/logs?${ qs }` : `${ BASE }/logs`;

	return apiFetch( { path, parse: false } ).then( async ( response ) => {
		const items = await response.json();
		return {
			items,
			total: parseInt( response.headers.get( 'X-WP-Total' ) || '0', 10 ),
			totalPages: parseInt(
				response.headers.get( 'X-WP-TotalPages' ) || '0',
				10
			),
		};
	} );
}

/** GET /ai-valve/v1/logs/filters — distinct filter values */
export async function fetchLogFilterOptions() {
	return apiFetch( { path: `${ BASE }/logs/filters` } );
}

/** DELETE /ai-valve/v1/logs */
export async function purgeLogs() {
	return apiFetch( {
		path: `${ BASE }/logs`,
		method: 'DELETE',
	} );
}
