/* global describe, it, expect */
/**
 * Feature 099 — the three routes through the wizard (T060).
 *
 * The adapter path is the one that varies: whether the install screen appears
 * depends on the adapter already being present, so the same selection produces
 * a different number of screens on two different sites. Spec FR-009 requires
 * the reported total to count only screens the operator will actually see, and
 * SC-006 requires no dead ends — both are invisible failures. A wrong "N of M"
 * looks like a working wizard, and a deep link landing on a skipped screen
 * looks like a blank step rather than a routing bug.
 */

import {
	computeSkips,
	buildVisibilityTable,
	computeTotalSteps,
	computeDisplayIndex,
} from '../../../src/js/quick-connect/App';

import { shouldSkip } from '../../../src/js/quick-connect/hooks/useWizardRouter';

/**
 * Visible step ids for a given selection and site state.
 *
 * @param {string} method  Selected transport.
 * @param {Object} plugins Transport states from /state.
 * @return {string[]} Ids the operator will see, in order.
 */
const visibleSteps = (method, plugins) =>
	buildVisibilityTable(computeSkips(method, plugins))
		.filter((row) => !row.skip)
		.map((row) => row.id);

describe('wizard paths — FR-009 step counts', () => {
	it('recommended transport: 5 screens, no adapter detour', () => {
		const steps = visibleSteps('mcp-manager', { mcpAdapter: 'missing' });

		expect(steps).toEqual(['1', '2', '3', '4', '5']);
		expect(computeTotalSteps(buildVisibilityTable(
			computeSkips('mcp-manager', { mcpAdapter: 'missing' })
		))).toBe(5);
	});

	it('adapter not yet installed: 7 screens, install then enablement', () => {
		const steps = visibleSteps('mcp-adapter', { mcpAdapter: 'missing' });

		expect(steps).toEqual(['1', '2', '3', '4', '5', '6', '7']);
	});

	it('adapter already active: 6 screens, install instructions skipped (FR-011a)', () => {
		const steps = visibleSteps('mcp-adapter', { mcpAdapter: 'active' });

		expect(steps).toEqual(['1', '2', '3', '4', '5', '7']);
		expect(steps).not.toContain('6');
	});

	it('an installed-but-inactive adapter still needs the install screen', () => {
		// "Present" means active. An inactive plugin registers nothing, so the
		// operator still has work to do on screen 6 — activating it.
		const steps = visibleSteps('mcp-adapter', { mcpAdapter: 'inactive' });

		expect(steps).toContain('6');
	});

	it('the recommended path never shows an adapter screen, whatever the adapter state', () => {
		for (const adapterState of ['missing', 'inactive', 'active']) {
			const steps = visibleSteps('mcp-manager', {
				mcpAdapter: adapterState,
			});

			expect(steps).not.toContain('6');
			expect(steps).not.toContain('7');
		}
	});
});

describe('progress indicator — no gaps, no overshoot', () => {
	it('numbers the adapter path 1..7 with no repeats or holes', () => {
		const skips = computeSkips('mcp-adapter', { mcpAdapter: 'missing' });
		const table = buildVisibilityTable(skips);
		const total = computeTotalSteps(table);

		const seen = ['1', '2', '3', '4', '5', '6', '7'].map((id) =>
			computeDisplayIndex(id, table, total)
		);

		expect(seen).toEqual([1, 2, 3, 4, 5, 6, 7]);
	});

	it('closes the gap left by a skipped screen rather than counting past it', () => {
		// The adapter is already active, so screen 6 is hidden. Screen 7 must
		// read "6 of 6" — not "7 of 6", which is what a naive index would give.
		const skips = computeSkips('mcp-adapter', { mcpAdapter: 'active' });
		const table = buildVisibilityTable(skips);
		const total = computeTotalSteps(table);

		expect(total).toBe(6);
		expect(computeDisplayIndex('7', table, total)).toBe(6);
		expect(computeDisplayIndex('5', table, total)).toBe(5);
	});

	it('reports the completion screen as the last of the visible steps', () => {
		const skips = computeSkips('mcp-manager', {});
		const table = buildVisibilityTable(skips);
		const total = computeTotalSteps(table);

		expect(computeDisplayIndex('done', table, total)).toBe(total);
	});
});

describe('deep links into skipped screens', () => {
	it('skips screen 6 when the URL carries no method', () => {
		// Bookmarks and support links land here. Without a method the wizard is
		// on the recommended path, so the adapter screens do not apply.
		const skips = computeSkips(undefined, {});

		expect(shouldSkip('6', skips)).toBe(true);
		expect(shouldSkip('7', skips)).toBe(true);
	});

	it('skips screen 6 but not screen 7 when the adapter is already active', () => {
		const skips = computeSkips('mcp-adapter', { mcpAdapter: 'active' });

		expect(shouldSkip('6', skips)).toBe(true);
		expect(shouldSkip('7', skips)).toBe(false);
	});

	it('keeps both adapter screens on a fresh adapter install', () => {
		const skips = computeSkips('mcp-adapter', { mcpAdapter: 'missing' });

		expect(shouldSkip('6', skips)).toBe(false);
		expect(shouldSkip('7', skips)).toBe(false);
	});

	it('never skips a screen every path shares', () => {
		const skips = computeSkips('mcp-adapter', { mcpAdapter: 'missing' });

		for (const id of ['1', '2', '3', '4', '5']) {
			expect(shouldSkip(id, skips)).toBe(false);
		}
	});
});
