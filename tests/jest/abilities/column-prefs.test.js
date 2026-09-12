/**
 * Jest tests for loadColumnPrefs() — Feature 025, repointed in Feature 102 (task T017).
 *
 * Until Feature 102 this file did not test loadColumnPrefs() at all. It mocked five modules just
 * to get past importing AbilitiesList.jsx, then declared its own copy of the function and asserted
 * against that. The copy could drift from the real implementation without a single test failing —
 * and adding a column to COLUMN_DEFAULTS in the component would have left the copy silently stale.
 *
 * Extracting the preferences into src/js/abilities/columns.js removed the reason for all of it:
 * the real function is now importable on its own, with no component, no store and no @wordpress
 * mocks. The assertions below are unchanged; only their subject is now the shipping code.
 *
 * @since 0.1.0
 */

import { loadColumnPrefs, COLUMN_DEFAULTS, LS_KEY } from '../../../src/js/abilities/columns';

beforeEach(() => {
	localStorage.clear();
});

// ---------------------------------------------------------------------------
// No saved preferences
// ---------------------------------------------------------------------------

test('returns all columns visible when localStorage is empty', () => {
	const prefs = loadColumnPrefs();
	expect(prefs).toEqual(COLUMN_DEFAULTS);
	Object.values(prefs).forEach((v) => expect(v).toBe(true));
});

// ---------------------------------------------------------------------------
// Saved preferences — partial hide
// ---------------------------------------------------------------------------

test('merges saved hidden columns over defaults', () => {
	localStorage.setItem(LS_KEY, JSON.stringify({ label: false, mcp: false }));
	const prefs = loadColumnPrefs();
	expect(prefs.label).toBe(false);
	expect(prefs.mcp).toBe(false);
	expect(prefs.category).toBe(true);
	expect(prefs.source).toBe(true);
});

// ---------------------------------------------------------------------------
// New columns default to visible with existing saved prefs (FR-025)
// ---------------------------------------------------------------------------

test('new column not in saved prefs defaults to visible', () => {
	// Simulate an old save that does not include 'description' or 'show_in_rest'
	localStorage.setItem(
		LS_KEY,
		JSON.stringify({ label: true, category: false })
	);
	const prefs = loadColumnPrefs();
	expect(prefs.description).toBe(true);
	expect(prefs.show_in_rest).toBe(true);
	expect(prefs.category).toBe(false);
});

// ---------------------------------------------------------------------------
// Value normalisation — FINDING-SEC-02
// ---------------------------------------------------------------------------

test('normalises truthy non-boolean saved values to true', () => {
	localStorage.setItem(LS_KEY, JSON.stringify({ label: 1, category: 'yes' }));
	const prefs = loadColumnPrefs();
	expect(prefs.label).toBe(true);
	expect(prefs.category).toBe(true);
});

test('normalises falsy non-boolean saved values to false', () => {
	localStorage.setItem(LS_KEY, JSON.stringify({ label: 0, category: null }));
	const prefs = loadColumnPrefs();
	expect(prefs.label).toBe(false);
	expect(prefs.category).toBe(false);
});

// ---------------------------------------------------------------------------
// Corrupt / invalid localStorage — silent fallback
// ---------------------------------------------------------------------------

test('falls back to defaults when localStorage contains invalid JSON', () => {
	localStorage.setItem(LS_KEY, '{ not valid json');
	const prefs = loadColumnPrefs();
	expect(prefs).toEqual(COLUMN_DEFAULTS);
});

test('falls back to defaults when localStorage item is null', () => {
	// getItem returns null when key absent — covered by empty-string fallback
	const prefs = loadColumnPrefs();
	expect(prefs).toEqual(COLUMN_DEFAULTS);
});

// ---------------------------------------------------------------------------
// Unknown saved keys are ignored (no pollution of result)
// ---------------------------------------------------------------------------

test('ignores unknown saved keys not in COLUMN_DEFAULTS', () => {
	localStorage.setItem(
		LS_KEY,
		JSON.stringify({ label: false, future_column: false })
	);
	const prefs = loadColumnPrefs();
	expect(prefs).not.toHaveProperty('future_column');
	expect(prefs.label).toBe(false);
});
