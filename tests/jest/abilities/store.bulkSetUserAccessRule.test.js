/**
 * Jest tests for the bulkSetUserAccessRule store thunk — Feature 056 FR-006/FR-011.
 *
 * Loops the composer's per-slug PUT /wpb-ac/v1/{slug}/rules/... endpoint
 * (wrapped by api.setAccessControlRule) under Promise.all. Empty acKey +
 * empty acOptions = clear rule (Everyone allowed).
 *
 * Guards:
 * - SEC-001: partial per-slug failure re-throws so the modal keeps its
 *   busy/error state intact.
 * - BUG-BULK-USER-ACCESS-SLASH-CORRUPTION (analysis I4): the ability slug
 *   contains a literal '/' (e.g. "acrossai/foo") which
 *   MUST pass through the URL raw. Do NOT encodeURIComponent — %2F gets
 *   stripped by the composer sanitizer and produces a corrupt key
 *   (observed: "acrossai-abilities-managerblock-pattern-delete").
 *
 * @since 0.0.15
 */

jest.mock('@wordpress/data', () => ({
	createReduxStore: jest.fn((name, config) => config),
	register: jest.fn(),
	dispatch: jest.fn(),
	select: jest.fn(),
}));
jest.mock('@wordpress/i18n', () => ({ __: (v) => v }));

const mockApiFetch = jest.fn();
jest.mock('@wordpress/api-fetch', () => mockApiFetch, { virtual: true });

// Provide the abilitiesConfig the client reads at module-load time.
globalThis.window = globalThis.window || {};
globalThis.window.acrossaiAbilitiesManager = {
	rest_namespace: 'acrossai/v1',
	access_control_slug: 'abilities',
};

const storeConfig = require('../../../src/js/abilities/store/index.js');
const actions = storeConfig.store.actions;

describe('bulkSetUserAccessRule — success path', () => {
	beforeEach(() => {
		mockApiFetch.mockReset();
		mockApiFetch.mockResolvedValue({ ok: true });
	});

	test('fires one PUT per slug with the correct payload', async () => {
		const thunk = actions.bulkSetUserAccessRule(
			['a', 'b', 'c'],
			'wp_role',
			['editor']
		);
		const dispatch = jest.fn(() => Promise.resolve());
		await thunk({ dispatch });
		expect(mockApiFetch).toHaveBeenCalledTimes(3);
		mockApiFetch.mock.calls.forEach(([opts]) => {
			expect(opts.method).toBe('PUT');
			expect(opts.data).toEqual({
				ac_key: 'wp_role',
				ac_options: ['editor'],
			});
		});
	});

	test('clear-rule path sends empty acKey + empty acOptions', async () => {
		const thunk = actions.bulkSetUserAccessRule(['a'], '', []);
		const dispatch = jest.fn(() => Promise.resolve());
		await thunk({ dispatch });
		const [opts] = mockApiFetch.mock.calls[0];
		expect(opts.data).toEqual({ ac_key: '', ac_options: [] });
	});

	test('brackets the dispatch chain with SET_SAVING true/false', async () => {
		const thunk = actions.bulkSetUserAccessRule(['x'], 'wp_role', [
			'editor',
		]);
		const dispatch = jest.fn(() => Promise.resolve());
		await thunk({ dispatch });
		const savingCalls = dispatch.mock.calls.filter(
			(c) => c[0]?.type === 'SET_SAVING'
		);
		expect(savingCalls).toHaveLength(2);
		expect(savingCalls[0][0].isSaving).toBe(true);
		expect(savingCalls[1][0].isSaving).toBe(false);
	});
});

describe('bulkSetUserAccessRule — slug pass-through (I4 regression guard)', () => {
	beforeEach(() => {
		mockApiFetch.mockReset();
		mockApiFetch.mockResolvedValue({ ok: true });
	});

	test('slug with literal "/" reaches URL raw, NOT percent-encoded', async () => {
		const slug = 'blocks/delete-block-pattern';
		const thunk = actions.bulkSetUserAccessRule(
			[slug],
			'wp_capability',
			['activate_plugins']
		);
		const dispatch = jest.fn(() => Promise.resolve());
		await thunk({ dispatch });
		const [opts] = mockApiFetch.mock.calls[0];
		// The path MUST contain the literal slash — %2F would be silently
		// stripped by the composer sanitizer, producing a corrupt DB key.
		expect(opts.path).toContain(
			'blocks/delete-block-pattern'
		);
		expect(opts.path).not.toContain('%2F');
		expect(opts.path).not.toContain('%2f');
	});

	test('access_control_slug is still URL-encoded (safe: no slashes expected there)', async () => {
		const thunk = actions.bulkSetUserAccessRule(
			['acrossai/foo'],
			'',
			[]
		);
		const dispatch = jest.fn(() => Promise.resolve());
		await thunk({ dispatch });
		const [opts] = mockApiFetch.mock.calls[0];
		// Path shape: /wpb-ac/v1/{ac_slug}/rules/acrossai-abilities/{ability_slug}
		expect(opts.path).toMatch(
			/^\/wpb-ac\/v1\/abilities\/rules\/acrossai-abilities\/acrossai\/foo$/
		);
	});
});

describe('bulkSetUserAccessRule — partial-failure re-throw (SEC-001)', () => {
	test('re-throws when any per-slug PUT rejects', async () => {
		mockApiFetch.mockReset();
		mockApiFetch
			.mockResolvedValueOnce({ ok: true })
			.mockRejectedValueOnce(new Error('boom'))
			.mockResolvedValueOnce({ ok: true });
		const dispatch = jest.fn(() => Promise.resolve());
		const thunk = actions.bulkSetUserAccessRule(
			['a', 'b', 'c'],
			'wp_role',
			['editor']
		);
		await expect(thunk({ dispatch })).rejects.toThrow('boom');
	});

	test('dispatches SET_SAVE_ERROR on failure', async () => {
		mockApiFetch.mockReset();
		mockApiFetch.mockRejectedValue(new Error('nope'));
		const dispatch = jest.fn(() => Promise.resolve());
		const thunk = actions.bulkSetUserAccessRule(['a'], 'wp_role', [
			'editor',
		]);
		await expect(thunk({ dispatch })).rejects.toThrow('nope');
		const saveErrorCall = dispatch.mock.calls.find(
			(c) => c[0]?.type === 'SET_SAVE_ERROR'
		);
		expect(saveErrorCall).toBeDefined();
		expect(saveErrorCall[0].error).toBe('nope');
	});
});

describe('bulkSetUserAccessRule — SEC-006 null-response guard (BUG-AC-NULL-RETURN-SILENT-FAIL)', () => {
	test('rejects when any per-slug response is null', async () => {
		mockApiFetch.mockReset();
		mockApiFetch
			.mockResolvedValueOnce({ ok: true })
			.mockResolvedValueOnce(null)
			.mockResolvedValueOnce({ ok: true });
		const dispatch = jest.fn(() => Promise.resolve());
		const thunk = actions.bulkSetUserAccessRule(
			['a', 'b', 'c'],
			'wp_role',
			['editor']
		);
		await expect(thunk({ dispatch })).rejects.toThrow(/1 of 3/);
	});

	test('rejects when any per-slug response is undefined', async () => {
		mockApiFetch.mockReset();
		mockApiFetch
			.mockResolvedValueOnce({ ok: true })
			.mockResolvedValueOnce(undefined)
			.mockResolvedValueOnce({ ok: true });
		const dispatch = jest.fn(() => Promise.resolve());
		const thunk = actions.bulkSetUserAccessRule(
			['a', 'b', 'c'],
			'wp_role',
			['editor']
		);
		await expect(thunk({ dispatch })).rejects.toThrow(/1 of 3/);
	});

	test('null-response failure surfaces via SET_SAVE_ERROR', async () => {
		mockApiFetch.mockReset();
		mockApiFetch.mockResolvedValue(null);
		const dispatch = jest.fn(() => Promise.resolve());
		const thunk = actions.bulkSetUserAccessRule(['a', 'b'], '', []);
		await expect(thunk({ dispatch })).rejects.toThrow(/2 of 2/);
		const saveErrorCall = dispatch.mock.calls.find(
			(c) => c[0]?.type === 'SET_SAVE_ERROR'
		);
		expect(saveErrorCall).toBeDefined();
		expect(saveErrorCall[0].error).toMatch(/2 of 2/);
	});

	test('does NOT dispatch fetchAbilities when null responses trigger rejection', async () => {
		mockApiFetch.mockReset();
		mockApiFetch.mockResolvedValue(null);
		const dispatch = jest.fn(() => Promise.resolve());
		const thunk = actions.bulkSetUserAccessRule(['a'], 'wp_role', [
			'editor',
		]);
		await expect(thunk({ dispatch })).rejects.toThrow();
		// fetchAbilities is a thunk itself — dispatched only on the success path.
		const fetchAbilitiesCalls = dispatch.mock.calls.filter(
			(c) => typeof c[0] === 'function'
		);
		expect(fetchAbilitiesCalls).toHaveLength(0);
	});
});
