/**
 * AI Valve Admin API client.
 *
 * Provides typed wrappers around the AI Valve REST API endpoints.
 * Expects `window.aiValve.restUrl` and `window.aiValve.nonce` to be
 * localized via `wp_localize_script()`.
 */

/**
 * @typedef {Object} AIValveConfig
 * @property {string} restUrl  - Base REST URL (e.g. "/wp-json/ai-valve/v1").
 * @property {string} nonce    - WP REST nonce.
 */

/**
 * @param {AIValveConfig} config
 * @returns {Object} API client methods.
 */
export function createApiClient(config) {
	const { restUrl, nonce } = config;

	if (!restUrl || !nonce) {
		throw new Error('AI Valve API client requires restUrl and nonce.');
	}

	/**
	 * Shared fetch with auth headers.
	 *
	 * @param {string} endpoint
	 * @param {RequestInit} [opts]
	 * @returns {Promise<any>}
	 */
	async function apiFetch(endpoint, opts = {}) {
		const url = `${restUrl.replace(/\/$/, '')}/${endpoint.replace(/^\//, '')}`;
		const response = await fetch(url, {
			...opts,
			headers: {
				'X-WP-Nonce': nonce,
				'Content-Type': 'application/json',
				...(opts.headers || {}),
			},
		});

		if (!response.ok) {
			const body = await response.json().catch(() => ({}));
			const error = new Error(body.message || `HTTP ${response.status}`);
			error.status = response.status;
			error.data = body;
			throw error;
		}

		return response.json();
	}

	return {
		/**
		 * Fetch current usage summary.
		 * @returns {Promise<Object>}
		 */
		getUsage() {
			return apiFetch('/usage');
		},

		/**
		 * Fetch paginated log entries.
		 *
		 * @param {Object} [params]
		 * @param {number} [params.page]
		 * @param {number} [params.per_page]
		 * @param {string} [params.plugin_slug]
		 * @param {string} [params.provider_id]
		 * @param {string} [params.context]
		 * @param {string} [params.status]
		 * @returns {Promise<{items: Array, total: number, totalPages: number}>}
		 */
		async getLogs(params = {}) {
			const query = new URLSearchParams();
			for (const [key, value] of Object.entries(params)) {
				if (value !== undefined && value !== null && value !== '') {
					query.set(key, String(value));
				}
			}
			const qs = query.toString();
			const endpoint = qs ? `/logs?${qs}` : '/logs';

			const url = `${restUrl.replace(/\/$/, '')}${endpoint}`;
			const response = await fetch(url, {
				headers: {
					'X-WP-Nonce': nonce,
					'Content-Type': 'application/json',
				},
			});

			if (!response.ok) {
				const body = await response.json().catch(() => ({}));
				const error = new Error(body.message || `HTTP ${response.status}`);
				error.status = response.status;
				throw error;
			}

			const items = await response.json();
			return {
				items,
				total: parseInt(response.headers.get('X-WP-Total') || '0', 10),
				totalPages: parseInt(response.headers.get('X-WP-TotalPages') || '0', 10),
			};
		},

		/**
		 * Update plugin settings.
		 *
		 * @param {Object} settings - Partial settings object.
		 * @returns {Promise<{updated: boolean, settings: Object}>}
		 */
		updateSettings(settings) {
			return apiFetch('/settings', {
				method: 'POST',
				body: JSON.stringify({ settings }),
			});
		},
	};
}

/**
 * Format a token count for display.
 *
 * @param {number} tokens
 * @returns {string}
 */
export function formatTokenCount(tokens) {
	if (tokens >= 1_000_000) {
		return `${(tokens / 1_000_000).toFixed(1)}M`;
	}
	if (tokens >= 1_000) {
		return `${(tokens / 1_000).toFixed(1)}K`;
	}
	return String(tokens);
}

/**
 * Calculate budget percentage, clamped to 0.
 *
 * @param {number} used
 * @param {number} limit
 * @returns {number} Percentage (can exceed 100).
 */
export function budgetPercentage(used, limit) {
	if (limit <= 0) {
		return 0;
	}
	return (used / limit) * 100;
}

/**
 * Determine severity level from a percentage.
 *
 * @param {number} pct
 * @param {number} [threshold=80]
 * @returns {'ok'|'warning'|'critical'}
 */
export function severityLevel(pct, threshold = 80) {
	if (pct >= 100) {
		return 'critical';
	}
	if (pct >= threshold) {
		return 'warning';
	}
	return 'ok';
}
