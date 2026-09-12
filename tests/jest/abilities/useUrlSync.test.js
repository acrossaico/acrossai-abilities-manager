/**
 * Jest tests for useUrlSync's pure URL helpers — Feature 102 (task T021).
 *
 * Repointed from tests/jest/ability-library/useLibraryTabSync.test.js. parseTabFromUrl() and
 * buildUrlFromTab() moved verbatim when the two URL hooks were merged, so their contracts are
 * asserted here unchanged — notably the SEC-052-I-003 sentinel fallback.
 *
 * buildUrl() is new, and is the reason the hooks were merged at all: the old pair each called
 * pushState( build( …, window.location.href ) ), so a tick that changed both view and tab had the
 * second read a stale href and silently drop the first one's query arg.
 *
 * @since 0.2.0
 */

import {
	parseTabFromUrl,
	buildUrlFromTab,
	buildUrl,
} from '../../../src/js/abilities/urlSync';
import { ALL_TABS_KEY } from '../../../src/js/abilities/constants';

const BASE =
	'http://example.test/wp-admin/admin.php?page=acrossai-abilities-manager';
const SLUGS = ['content', 'cache', 'updates'];

// `view` is a { mode, slug } object, not a string — see buildUrlFromView().
const LIST = { mode: 'list' };
const EDIT = { mode: 'edit', slug: 'acrossai/get-post' };

describe('parseTabFromUrl — sentinel contract', () => {
	test('absent tab arg yields the All sentinel', () => {
		expect(parseTabFromUrl(BASE, SLUGS, ALL_TABS_KEY)).toBe(ALL_TABS_KEY);
	});

	test('known tab is returned as-is', () => {
		expect(parseTabFromUrl(`${BASE}&tab=cache`, SLUGS, ALL_TABS_KEY)).toBe(
			'cache'
		);
	});

	test('unknown tab falls back to the sentinel and is never echoed back', () => {
		const out = parseTabFromUrl(
			`${BASE}&tab=<script>`,
			SLUGS,
			ALL_TABS_KEY
		);
		expect(out).toBe(ALL_TABS_KEY);
		expect(out).not.toContain('script');
	});

	test('empty validSlugs falls back rather than trusting the URL', () => {
		expect(parseTabFromUrl(`${BASE}&tab=cache`, [], ALL_TABS_KEY)).toBe(
			ALL_TABS_KEY
		);
	});
});

describe('buildUrlFromTab', () => {
	test('the All sentinel strips the tab arg entirely', () => {
		expect(
			buildUrlFromTab(ALL_TABS_KEY, `${BASE}&tab=cache`, ALL_TABS_KEY)
		).not.toContain('tab=');
	});

	test('a toolset sets the tab arg', () => {
		expect(buildUrlFromTab('cache', BASE, ALL_TABS_KEY)).toContain(
			'tab=cache'
		);
	});

	test('unrelated query args survive', () => {
		expect(
			buildUrlFromTab('cache', `${BASE}&paged=3`, ALL_TABS_KEY)
		).toContain('paged=3');
	});
});

describe('buildUrl — the composition the merge exists for', () => {
	test('carries view and tab in one URL', () => {
		const out = buildUrl(EDIT, 'cache', BASE);
		expect(out).toContain('tab=cache');
		expect(out).toContain('action=edit');
	});

	test('tab survives opening an ability, so Back returns to the toolset', () => {
		const listUrl = buildUrl(LIST, 'cache', BASE);
		const editUrl = buildUrl(EDIT, 'cache', listUrl);
		expect(editUrl).toContain('tab=cache');
	});

	test('leaving a toolset while editing drops only the tab arg', () => {
		const editUrl = buildUrl(EDIT, 'cache', BASE);
		const out = buildUrl(EDIT, ALL_TABS_KEY, editUrl);
		expect(out).not.toContain('tab=');
		expect(out).toContain('action=edit');
	});

	test('is idempotent — re-applying the same state changes nothing', () => {
		const once = buildUrl(LIST, 'cache', BASE);
		expect(buildUrl(LIST, 'cache', once)).toBe(once);
	});
});
