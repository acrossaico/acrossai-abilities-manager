/**
 * Jest tests for list-parameter retention across a bulk action — issue #185.
 *
 * Every bulk action ends with `dispatch( actions.fetchAbilities() )` — no arguments. The parameters
 * that describe what is on screen (toolset, page, page size, search, source/status) used to live
 * only in the component, so a bare call produced an empty query string: applying a bulk action
 * silently dropped all of them and the table jumped back to an unfiltered page 1 at the server's
 * default page size, while the tab strip and pager kept claiming the old state.
 *
 * The contract these tests pin: **`fetchAbilities()` with no arguments means "re-fetch what is
 * displayed"**, not "fetch the server defaults". That is what makes the five existing bulk call
 * sites correct and, more importantly, the sixth one correct by default.
 *
 * @since 0.0.35
 */

jest.mock('@wordpress/data', () => ({
	createReduxStore: jest.fn((name, config) => config),
	register: jest.fn(),
	dispatch: jest.fn(),
	select: jest.fn(),
}));
jest.mock('@wordpress/i18n', () => ({ __: (v) => v }));
jest.mock('../../../src/js/abilities/api/client.js', () => ({
	getAbilities: jest.fn(),
	getAbility: jest.fn(),
	createAbility: jest.fn(),
	updateAbility: jest.fn(),
	deleteAbility: jest.fn(),
	getCategories: jest.fn(),
	deleteOverride: jest.fn(),
	getToolsetCounts: jest.fn(),
}));

const api = require('../../../src/js/abilities/api/client.js');
const storeConfig = require('../../../src/js/abilities/store/index.js');

const actions = storeConfig.store.actions;
const reducer = storeConfig.store.reducer;
const selectors = storeConfig.store.selectors;

/** The query a real screen sends: Cache toolset, page 2, 100 per page, searching. */
const SCREEN_PARAMS = {
	page: 2,
	per_page: 100,
	search: 'transient',
	source: 'plugin',
	status: 'publish',
	tab_group: 'cache',
};

/**
 * Drive a thunk with a stub store whose remembered params are `stored`.
 *
 * @param {Function} thunk  Thunk to run.
 * @param {Object}   stored Value getListParams() should return.
 * @return {Promise<Array>} Dispatched actions.
 */
async function run(thunk, stored = {}) {
	const dispatched = [];
	const dispatch = jest.fn((a) => {
		dispatched.push(a);

		return Promise.resolve();
	});
	const select = { getListParams: () => stored };

	await thunk({ dispatch, select });

	return dispatched;
}

beforeEach(() => {
	api.getAbilities.mockReset();
	api.getAbilities.mockResolvedValue({ abilities: [], total: 0, pages: 1 });
});

describe('fetchAbilities — parameter retention', () => {
	test('an explicit query is sent as given and remembered', async () => {
		const dispatched = await run(actions.fetchAbilities(SCREEN_PARAMS));

		expect(api.getAbilities).toHaveBeenCalledWith(SCREEN_PARAMS);
		expect(dispatched).toContainEqual({
			type: 'SET_LIST_PARAMS',
			params: SCREEN_PARAMS,
		});
	});

	test('no arguments re-sends the remembered query — the #185 regression', async () => {
		await run(actions.fetchAbilities(), SCREEN_PARAMS);

		expect(api.getAbilities).toHaveBeenCalledWith(SCREEN_PARAMS);
	});

	test('no arguments does not overwrite what is remembered', async () => {
		const dispatched = await run(actions.fetchAbilities(), SCREEN_PARAMS);

		expect(
			dispatched.filter((a) => 'SET_LIST_PARAMS' === a?.type)
		).toHaveLength(0);
	});

	test('an explicit empty object still means "no parameters"', async () => {
		// Distinct from omitting the argument. A caller that deliberately asks for an unfiltered
		// list must still get one.
		await run(actions.fetchAbilities({}), SCREEN_PARAMS);

		expect(api.getAbilities).toHaveBeenCalledWith({});
	});

	test('the toolset filter specifically survives a bare refetch', async () => {
		// The reported symptom: the strip still highlighted Cache while the table showed all 419.
		await run(actions.fetchAbilities(), SCREEN_PARAMS);

		expect(api.getAbilities.mock.calls[0][0]).toHaveProperty(
			'tab_group',
			'cache'
		);
	});

	test('the page size specifically survives a bare refetch', async () => {
		// The second symptom: 20 rows served while the pager described a 100-row page.
		await run(actions.fetchAbilities(), SCREEN_PARAMS);

		expect(api.getAbilities.mock.calls[0][0]).toHaveProperty(
			'per_page',
			100
		);
	});
});

describe('every bulk action refetches without discarding the query', () => {
	beforeEach(() => {
		api.updateAbility.mockReset();
		api.deleteAbility.mockReset();
		api.deleteOverride.mockReset();
		api.updateAbility.mockResolvedValue({ ability_slug: 'x' });
		api.deleteAbility.mockResolvedValue({});
		api.deleteOverride.mockResolvedValue({});
	});

	/**
	 * Each bulk thunk dispatches `fetchAbilities()` as a nested thunk. Running that nested thunk
	 * with the remembered params proves the refetch reaches the API with them intact.
	 */
	test.each([
		['bulkUpdateStatus', () => actions.bulkUpdateStatus(['a'], 'publish')],
		[
			'bulkUpdateTristate',
			() => actions.bulkUpdateTristate(['a'], 'site_allowed', false),
		],
		['bulkClearOverrides', () => actions.bulkClearOverrides(['a'])],
		['bulkDeleteAbilities', () => actions.bulkDeleteAbilities(['a'])],
	])('%s', async (_name, make) => {
		const nested = [];
		const dispatch = jest.fn((a) => {
			if ('function' === typeof a) {
				nested.push(a);
			}

			return Promise.resolve();
		});

		await make()({ dispatch });

		expect(nested.length).toBeGreaterThan(0);

		api.getAbilities.mockClear();

		for (const thunk of nested) {
			await thunk({
				dispatch: jest.fn(() => Promise.resolve()),
				select: { getListParams: () => SCREEN_PARAMS },
			});
		}

		expect(api.getAbilities).toHaveBeenCalledWith(SCREEN_PARAMS);
	});
});

describe('SET_LIST_PARAMS reducer and selector', () => {
	test('stores the params and reads them back', () => {
		const next = reducer(undefined, {
			type: 'SET_LIST_PARAMS',
			params: SCREEN_PARAMS,
		});

		expect(selectors.getListParams(next)).toEqual(SCREEN_PARAMS);
	});

	test('defaults to an empty object before any list load', () => {
		const initial = reducer(undefined, { type: '@@INIT' });

		expect(selectors.getListParams(initial)).toEqual({});
	});
});
