/* global describe, it, expect, afterEach */
/**
 * Feature 099 — wizard flow logic.
 *
 * Tests the pure helpers that decide what the operator sees, without rendering.
 * The progress indicator and the skip behaviour are the two things most likely
 * to drift silently, and spec SC-006 requires no dead ends and a coherent
 * "N of M" on every path.
 */

import {
	shouldSkip,
	readParams,
	STEP_ORDER,
	METHODS,
	DEFAULT_METHOD,
} from '../../../src/js/quick-connect/hooks/useWizardRouter';

import {
	computeSkips,
	buildVisibilityTable,
	computeTotalSteps,
	computeDisplayIndex,
} from '../../../src/js/quick-connect/App';

/**
 * Point window.location.search at a query string.
 *
 * Uses history.replaceState rather than reassigning window.location: jsdom
 * treats assignment as a navigation and emits "Not implemented: navigation",
 * which @wordpress/jest-console then fails the test on.
 *
 * @param {string} search Query string including the leading '?'.
 */
const setSearch = (search) => {
	window.history.replaceState({}, '', `/wp-admin/admin.php${search}`);
};

describe('readParams — SEC-004 enum validation', () => {
	afterEach(() => setSearch(''));

	it('defaults to step 1 and the recommended transport', () => {
		setSearch('?page=acrossai-abilities-manager&quick-connect=1');

		expect(readParams()).toEqual({ step: '1', method: DEFAULT_METHOD });
	});

	it('accepts every known step id', () => {
		STEP_ORDER.forEach((step) => {
			setSearch(`?step=${step}`);
			expect(readParams().step).toBe(step);
		});
	});

	it('accepts both known transports', () => {
		METHODS.forEach((method) => {
			setSearch(`?method=${method}`);
			expect(readParams().method).toBe(method);
		});
	});

	it('falls back to step 1 for an unknown step rather than echoing it', () => {
		setSearch('?step=99');
		expect(readParams().step).toBe('1');
	});

	it('falls back to the default transport for an unknown method', () => {
		setSearch('?method=not-a-transport');
		expect(readParams().method).toBe(DEFAULT_METHOD);
	});

	it('never returns injected markup from a crafted URL', () => {
		setSearch('?step=%3Cscript%3E&method=%3Cimg%20onerror%3D1%3E');

		const params = readParams();

		expect(params.step).toBe('1');
		expect(params.method).toBe(DEFAULT_METHOD);
	});
});

describe('computeSkips', () => {
	it('skips both adapter screens on the recommended path', () => {
		const skips = computeSkips('mcp-manager', { mcpAdapter: 'missing' });

		expect(skips.skipAdapterInstall).toBe(true);
		expect(skips.skipAdapterAbilities).toBe(true);
	});

	it('shows both adapter screens when the adapter is chosen and absent', () => {
		const skips = computeSkips('mcp-adapter', { mcpAdapter: 'missing' });

		expect(skips.skipAdapterInstall).toBe(false);
		expect(skips.skipAdapterAbilities).toBe(false);
	});

	it('skips the install instructions when the adapter is already active (FR-011a)', () => {
		const skips = computeSkips('mcp-adapter', { mcpAdapter: 'active' });

		expect(skips.skipAdapterInstall).toBe(true);
		expect(skips.skipAdapterAbilities).toBe(false);
	});
});

describe('shouldSkip', () => {
	it('never skips the five always-visible screens', () => {
		const skips = computeSkips('mcp-adapter', { mcpAdapter: 'missing' });

		['1', '2', '3', '4', '5'].forEach((step) => {
			expect(shouldSkip(step, skips)).toBe(false);
		});
	});

	it('skips step 6 when its predicate is set', () => {
		expect(shouldSkip('6', { skipAdapterInstall: true })).toBe(true);
	});

	it('skips step 7 when its predicate is set', () => {
		expect(shouldSkip('7', { skipAdapterAbilities: true })).toBe(true);
	});

	it('tolerates being called with no flags', () => {
		expect(shouldSkip('6')).toBe(false);
	});
});

describe('progress indicator — SC-006', () => {
	const paths = {
		'recommended transport': computeSkips('mcp-manager', {
			mcpAdapter: 'missing',
		}),
		'adapter needing install': computeSkips('mcp-adapter', {
			mcpAdapter: 'missing',
		}),
		'adapter already present': computeSkips('mcp-adapter', {
			mcpAdapter: 'active',
		}),
	};

	it('reports 5 visible steps on the recommended path', () => {
		const table = buildVisibilityTable(paths['recommended transport']);
		expect(computeTotalSteps(table)).toBe(5);
	});

	it('reports 7 visible steps when the adapter must be installed', () => {
		const table = buildVisibilityTable(paths['adapter needing install']);
		expect(computeTotalSteps(table)).toBe(7);
	});

	it('reports 6 visible steps when the adapter is already present', () => {
		const table = buildVisibilityTable(paths['adapter already present']);
		expect(computeTotalSteps(table)).toBe(6);
	});

	it('numbers visible steps consecutively from 1 with no gaps', () => {
		const table = buildVisibilityTable(paths['adapter already present']);
		const total = computeTotalSteps(table);

		const indices = table
			.filter((row) => !row.skip)
			.map((row) => computeDisplayIndex(row.id, table, total));

		expect(indices).toEqual([1, 2, 3, 4, 5, 6]);
	});

	it('never reports an index above the total on any path', () => {
		Object.values(paths).forEach((skips) => {
			const table = buildVisibilityTable(skips);
			const total = computeTotalSteps(table);

			table.forEach((row) => {
				expect(
					computeDisplayIndex(row.id, table, total)
				).toBeLessThanOrEqual(total);
			});
		});
	});

	it('places the completion screen at the end of every path', () => {
		Object.values(paths).forEach((skips) => {
			const table = buildVisibilityTable(skips);
			const total = computeTotalSteps(table);

			expect(computeDisplayIndex('done', table, total)).toBe(total);
		});
	});
});
