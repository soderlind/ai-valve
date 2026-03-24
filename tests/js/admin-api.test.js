import { describe, it, expect, vi, beforeEach } from 'vitest';
import {
	createApiClient,
	formatTokenCount,
	budgetPercentage,
	severityLevel,
} from '../../src/js/admin-api.js';

/* ------------------------------------------------------------------
 * formatTokenCount()
 * ----------------------------------------------------------------*/

describe('formatTokenCount', () => {
	it('returns plain number for small values', () => {
		expect(formatTokenCount(0)).toBe('0');
		expect(formatTokenCount(999)).toBe('999');
	});

	it('formats thousands with K suffix', () => {
		expect(formatTokenCount(1000)).toBe('1.0K');
		expect(formatTokenCount(5500)).toBe('5.5K');
		expect(formatTokenCount(999_999)).toBe('1000.0K');
	});

	it('formats millions with M suffix', () => {
		expect(formatTokenCount(1_000_000)).toBe('1.0M');
		expect(formatTokenCount(2_500_000)).toBe('2.5M');
	});
});

/* ------------------------------------------------------------------
 * budgetPercentage()
 * ----------------------------------------------------------------*/

describe('budgetPercentage', () => {
	it('returns 0 when limit is zero', () => {
		expect(budgetPercentage(500, 0)).toBe(0);
	});

	it('returns 0 when limit is negative', () => {
		expect(budgetPercentage(500, -100)).toBe(0);
	});

	it('calculates correct percentage', () => {
		expect(budgetPercentage(5000, 10000)).toBe(50);
		expect(budgetPercentage(8000, 10000)).toBe(80);
	});

	it('can exceed 100%', () => {
		expect(budgetPercentage(15000, 10000)).toBe(150);
	});

	it('returns 0 when used is 0', () => {
		expect(budgetPercentage(0, 10000)).toBe(0);
	});
});

/* ------------------------------------------------------------------
 * severityLevel()
 * ----------------------------------------------------------------*/

describe('severityLevel', () => {
	it('returns ok below threshold', () => {
		expect(severityLevel(0)).toBe('ok');
		expect(severityLevel(79)).toBe('ok');
	});

	it('returns warning at or above threshold', () => {
		expect(severityLevel(80)).toBe('warning');
		expect(severityLevel(99)).toBe('warning');
	});

	it('returns critical at or above 100', () => {
		expect(severityLevel(100)).toBe('critical');
		expect(severityLevel(150)).toBe('critical');
	});

	it('respects custom threshold', () => {
		expect(severityLevel(60, 50)).toBe('warning');
		expect(severityLevel(40, 50)).toBe('ok');
	});
});

/* ------------------------------------------------------------------
 * createApiClient()
 * ----------------------------------------------------------------*/

describe('createApiClient', () => {
	it('throws if restUrl or nonce is missing', () => {
		expect(() => createApiClient({ restUrl: '', nonce: 'abc' })).toThrow();
		expect(() => createApiClient({ restUrl: '/wp-json', nonce: '' })).toThrow();
	});

	it('creates client with expected methods', () => {
		const client = createApiClient({
			restUrl: '/wp-json/ai-valve/v1',
			nonce: 'test-nonce',
		});
		expect(typeof client.getUsage).toBe('function');
		expect(typeof client.getLogs).toBe('function');
		expect(typeof client.updateSettings).toBe('function');
	});
});

/* ------------------------------------------------------------------
 * API client — fetch integration
 * ----------------------------------------------------------------*/

describe('createApiClient fetch calls', () => {
	const config = {
		restUrl: '/wp-json/ai-valve/v1',
		nonce: 'test-nonce-123',
	};

	beforeEach(() => {
		vi.restoreAllMocks();
	});

	it('getUsage fetches /usage with nonce header', async () => {
		const mockData = { daily: { total_tokens: 1000 } };

		globalThis.fetch = vi.fn().mockResolvedValue({
			ok: true,
			json: () => Promise.resolve(mockData),
		});

		const client = createApiClient(config);
		const result = await client.getUsage();

		expect(result).toEqual(mockData);
		expect(fetch).toHaveBeenCalledOnce();

		const [url, opts] = fetch.mock.calls[0];
		expect(url).toBe('/wp-json/ai-valve/v1/usage');
		expect(opts.headers['X-WP-Nonce']).toBe('test-nonce-123');
	});

	it('getLogs builds query string from params', async () => {
		const headers = new Map([
			['X-WP-Total', '42'],
			['X-WP-TotalPages', '3'],
		]);

		globalThis.fetch = vi.fn().mockResolvedValue({
			ok: true,
			json: () => Promise.resolve([{ id: 1 }]),
			headers: { get: (key) => headers.get(key) || null },
		});

		const client = createApiClient(config);
		const result = await client.getLogs({ page: 2, plugin_slug: 'my-plugin' });

		expect(result.items).toEqual([{ id: 1 }]);
		expect(result.total).toBe(42);
		expect(result.totalPages).toBe(3);

		const url = fetch.mock.calls[0][0];
		expect(url).toContain('page=2');
		expect(url).toContain('plugin_slug=my-plugin');
	});

	it('getLogs omits empty/null params', async () => {
		const headers = new Map();
		globalThis.fetch = vi.fn().mockResolvedValue({
			ok: true,
			json: () => Promise.resolve([]),
			headers: { get: (key) => headers.get(key) || null },
		});

		const client = createApiClient(config);
		await client.getLogs({ page: 1, plugin_slug: '', context: null });

		const url = fetch.mock.calls[0][0];
		expect(url).toContain('page=1');
		expect(url).not.toContain('plugin_slug');
		expect(url).not.toContain('context');
	});

	it('updateSettings sends POST with settings body', async () => {
		globalThis.fetch = vi.fn().mockResolvedValue({
			ok: true,
			json: () => Promise.resolve({ updated: true }),
		});

		const client = createApiClient(config);
		await client.updateSettings({ enabled: false });

		const [url, opts] = fetch.mock.calls[0];
		expect(url).toBe('/wp-json/ai-valve/v1/settings');
		expect(opts.method).toBe('POST');

		const body = JSON.parse(opts.body);
		expect(body.settings.enabled).toBe(false);
	});

	it('throws on HTTP error with message from response', async () => {
		globalThis.fetch = vi.fn().mockResolvedValue({
			ok: false,
			status: 403,
			json: () => Promise.resolve({ message: 'Forbidden' }),
		});

		const client = createApiClient(config);
		const error = await client.getUsage().catch((e) => e);

		expect(error).toBeInstanceOf(Error);
		expect(error.message).toBe('Forbidden');
		expect(error.status).toBe(403);
	});

	it('throws with fallback message when JSON parse fails', async () => {
		globalThis.fetch = vi.fn().mockResolvedValue({
			ok: false,
			status: 500,
			json: () => Promise.reject(new SyntaxError('bad json')),
		});

		const client = createApiClient(config);
		const error = await client.getUsage().catch((e) => e);

		expect(error).toBeInstanceOf(Error);
		expect(error.message).toBe('HTTP 500');
	});
});
