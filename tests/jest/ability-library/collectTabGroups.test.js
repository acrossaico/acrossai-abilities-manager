/* global jest, describe, test, expect */
/**
 * Jest tests for Feature 037 — collectTabGroups().
 *
 * Asserts the pure helper that derives the sorted unique set of tab_group
 * identifiers from grouped items. PATTERN-NAMED-EXPORT-JEST.
 *
 * @since 0.1.0
 */

jest.mock('@wordpress/components', () => ({
	Button: () => null,
	Notice: () => null,
	TabPanel: ({ children }) =>
		typeof children === 'function' ? children({ name: '__all__' }) : null,
}));
jest.mock('../../../src/js/ability-library/hooks/useLibraryTabSync', () => ({
	__esModule: true,
	default: () => {},
}));
jest.mock('@wordpress/element', () => ({
	useEffect: () => {},
	useMemo: (fn) => fn(),
	useRef: () => ({ current: false }),
	useState: (init) => [init, () => {}],
}));
jest.mock('@wordpress/i18n', () => ({ __: (v) => v }));
jest.mock('@wordpress/icons', () => ({
	Icon: () => null,
	plugins: null,
}));
jest.mock('../../../src/js/ability-library/api', () => ({
	fetchConfig: jest.fn(() => Promise.resolve({})),
	saveConfig: jest.fn(() => Promise.resolve()),
}));
jest.mock('../../../src/js/ability-library/components/LibraryCard', () => ({
	__esModule: true,
	default: () => null,
}));

const {
	collectTabGroups,
} = require('../../../src/js/ability-library/components/LibraryPage');

function itemWithSlugs(slugs) {
	return {
		id: 'cat',
		category: 'cat',
		categoryLabel: 'Cat',
		slugs,
	};
}

function slug(overrides = {}) {
	return {
		slug: 'plugin/a',
		slugLabel: 'A',
		name: 'plugin/a',
		subGroup: '',
		subGroupLabel: '',
		tabGroup: '',
		description: '',
		...overrides,
	};
}

describe('collectTabGroups', () => {
	test('returns an empty array when no slug declares tabGroup', () => {
		const result = collectTabGroups([
			itemWithSlugs([slug(), slug({ slug: 'b' })]),
		]);
		expect(result).toEqual([]);
	});

	test('dedupes when multiple slugs share the same tabGroup', () => {
		const items = [
			itemWithSlugs([
				slug({ slug: 'a', tabGroup: 'sales' }),
				slug({ slug: 'b', tabGroup: 'sales' }),
				slug({ slug: 'c', tabGroup: 'support' }),
			]),
		];
		expect(collectTabGroups(items)).toEqual(['sales', 'support']);
	});

	test('sorts alphabetically case-insensitively (FR-013)', () => {
		const items = [
			itemWithSlugs([
				slug({ slug: 'a', tabGroup: 'support' }),
				slug({ slug: 'b', tabGroup: 'crm' }),
				slug({ slug: 'c', tabGroup: 'analytics' }),
			]),
		];
		expect(collectTabGroups(items)).toEqual([
			'analytics',
			'crm',
			'support',
		]);
	});

	test('aggregates tabGroup across multiple items / categories', () => {
		const items = [
			itemWithSlugs([slug({ slug: 'a', tabGroup: 'sales' })]),
			{
				id: 'cat2',
				category: 'cat2',
				categoryLabel: 'Cat 2',
				slugs: [slug({ slug: 'b', tabGroup: 'support' })],
			},
		];
		expect(collectTabGroups(items)).toEqual(['sales', 'support']);
	});

	test('ignores empty-string and falsy tabGroup values', () => {
		const items = [
			itemWithSlugs([
				slug({ slug: 'a', tabGroup: '' }),
				slug({ slug: 'b', tabGroup: 'sales' }),
			]),
		];
		expect(collectTabGroups(items)).toEqual(['sales']);
	});

	test('pins the `content` tab_group to first position (Feature 101)', () => {
		const items = [
			itemWithSlugs([
				slug({ slug: 'a', tabGroup: 'updates' }),
				slug({ slug: 'b', tabGroup: 'blocks' }),
				slug({ slug: 'c', tabGroup: 'content' }),
				slug({ slug: 'd', tabGroup: 'users' }),
			]),
		];
		expect(collectTabGroups(items)).toEqual([
			'content',
			'blocks',
			'updates',
			'users',
		]);
	});

	test('is a no-op when `content` is absent', () => {
		const items = [
			itemWithSlugs([
				slug({ slug: 'a', tabGroup: 'updates' }),
				slug({ slug: 'b', tabGroup: 'blocks' }),
			]),
		];
		expect(collectTabGroups(items)).toEqual(['blocks', 'updates']);
	});

	test('leaves `content` alone when already first', () => {
		const items = [
			itemWithSlugs([
				slug({ slug: 'a', tabGroup: 'content' }),
				slug({ slug: 'b', tabGroup: 'updates' }),
			]),
		];
		expect(collectTabGroups(items)).toEqual(['content', 'updates']);
	});

	test('retired `core` is no longer special (Feature 101)', () => {
		const items = [
			itemWithSlugs([
				slug({ slug: 'a', tabGroup: 'updates' }),
				slug({ slug: 'b', tabGroup: 'core' }),
			]),
		];
		// `core` sorts alphabetically like any other identifier now.
		expect(collectTabGroups(items)).toEqual(['core', 'updates']);
	});
});
