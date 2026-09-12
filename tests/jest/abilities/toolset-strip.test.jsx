/**
 * Jest tests for the toolset strip and Toolset column — Feature 102 (tasks T055, T056).
 *
 * The strip is navigation links, not an ARIA tabs widget (spec FR-012a). These tests pin that
 * distinction, because it is the kind of thing a later "let's use TabPanel, it's tidier" change
 * would quietly undo: they assert real anchors with real hrefs and no `role="tablist"`.
 *
 * @since 0.2.0
 */

// `act` is not exported by this @wordpress/element build (v6+) — it lives on react itself. Rather
// than import react directly, which would be the one place in the codebase reaching past
// @wordpress/element, this follows the convention already set by
// tests/jest/abilities/ability-form-user-access-section.test.jsx: mock @wordpress/element as a
// pass-through over the real module and add `act` to it. Everything else — createRoot,
// createElement — is the genuine implementation.
import { act, createRoot, createElement } from '@wordpress/element';

jest.mock('@wordpress/element', () => {
	const actual = jest.requireActual('@wordpress/element');

	return {
		...actual,
		act: jest.requireActual('react').act,
	};
});
import GroupTabs from '../../../src/js/abilities/components/GroupTabs';
import { ToolsetCell } from '../../../src/js/abilities/components/cells';
import { ALL_TABS_KEY } from '../../../src/js/abilities/constants';

// React 18 requires this flag before act() will run without warning. @wordpress/jest-console
// fails any test that emits console.error, so the warning is a hard failure rather than noise.
global.IS_REACT_ACT_ENVIRONMENT = true;

let container;
let root;

beforeEach(() => {
	container = document.createElement('div');
	document.body.appendChild(container);
	root = createRoot(container);
	// window.location is not assignable in modern jsdom; replaceState moves the URL within the
	// same origin, which is enough for buildUrlFromTab() to have something real to work from.
	window.history.replaceState(
		{},
		'',
		'/wp-admin/admin.php?page=acrossai-abilities-manager'
	);
});

afterEach(() => {
	act(() => root.unmount());
	container.remove();
});

const render = (el) => act(() => root.render(el));

const COUNTS = { cache: 7, content: 29, settings: 11 };

describe('GroupTabs — declared labels beat the derived one', () => {
	// The strip derives a label from the group key via titleCaseTabLabel, which cannot know an
	// acronym: `acf` becomes "Acf", which reads as a typo. An integration declares its own name and
	// the route returns it, so the declaration wins where there is one (issue #184).
	test('a declared label is used in place of the derived one', () => {
		render(
			createElement(GroupTabs, {
				counts: { acf: 6, cache: 7 },
				labels: { acf: 'Advanced Custom Fields' },
				activeTab: ALL_TABS_KEY,
				total: 425,
				onSelect: () => {},
			})
		);

		const text = container.textContent;
		expect(text).toContain('Advanced Custom Fields');
		expect(text).not.toContain('Acf');
		// A group with no declared label still derives one.
		expect(text).toContain('Cache');
	});

	test('missing labels fall back to the derived rule', () => {
		render(
			createElement(GroupTabs, {
				counts: { acf: 6 },
				activeTab: ALL_TABS_KEY,
				total: 6,
				onSelect: () => {},
			})
		);

		// No `labels` prop at all — the strip must not throw, and must still name the tab.
		expect(container.textContent).toContain('Acf');
	});
});

describe('GroupTabs — navigation links, not tabs', () => {
	test('renders All plus one entry per toolset, alphabetically', () => {
		render(
			createElement(GroupTabs, {
				counts: COUNTS,
				activeTab: ALL_TABS_KEY,
				total: 419,
				onSelect: () => {},
			})
		);

		const items = container.querySelectorAll('.toolset-strip__item');
		expect(items).toHaveLength(4);
		expect(items[0].textContent).toContain('All');
		expect(items[1].textContent).toContain('Cache');
		expect(items[2].textContent).toContain('Content');
		expect(items[3].textContent).toContain('Settings');
	});

	test('every entry is an anchor with a real href', () => {
		render(
			createElement(GroupTabs, {
				counts: COUNTS,
				activeTab: ALL_TABS_KEY,
				total: 419,
				onSelect: () => {},
			})
		);

		const items = Array.from(
			container.querySelectorAll('.toolset-strip__item')
		);

		items.forEach((el) => {
			expect(el.tagName).toBe('A');
			expect(el.getAttribute('href')).toBeTruthy();
		});

		// A toolset link carries its tab; the All link carries none.
		expect(items[1].getAttribute('href')).toContain('tab=cache');
		expect(items[0].getAttribute('href')).not.toContain('tab=');
	});

	test('does not declare the ARIA tabs pattern', () => {
		render(
			createElement(GroupTabs, {
				counts: COUNTS,
				activeTab: ALL_TABS_KEY,
				total: 419,
				onSelect: () => {},
			})
		);

		// role="tablist" would promise arrow-key roving focus that links do not implement.
		expect(container.querySelector('[role="tablist"]')).toBeNull();
		expect(container.querySelector('[role="tab"]')).toBeNull();
		expect(container.querySelector('nav')).not.toBeNull();
	});

	test('marks the active entry and exposes it to assistive tech', () => {
		render(
			createElement(GroupTabs, {
				counts: COUNTS,
				activeTab: 'cache',
				total: 419,
				onSelect: () => {},
			})
		);

		const active = container.querySelectorAll(
			'.toolset-strip__item.is-active'
		);
		expect(active).toHaveLength(1);
		expect(active[0].textContent).toContain('Cache');
		expect(active[0].getAttribute('aria-current')).toBe('page');
	});

	test('shows the count beside each entry, and the total beside All', () => {
		render(
			createElement(GroupTabs, {
				counts: COUNTS,
				activeTab: ALL_TABS_KEY,
				total: 419,
				onSelect: () => {},
			})
		);

		const counts = Array.from(
			container.querySelectorAll('.toolset-strip__count')
		).map((el) => el.textContent);

		expect(counts).toEqual(['419', '7', '29', '11']);
	});

	test('a plain click selects without navigating', () => {
		const onSelect = jest.fn();
		render(
			createElement(GroupTabs, {
				counts: COUNTS,
				activeTab: ALL_TABS_KEY,
				total: 419,
				onSelect,
			})
		);

		const cache = container.querySelectorAll('.toolset-strip__item')[1];
		const event = new MouseEvent('click', {
			bubbles: true,
			cancelable: true,
		});
		act(() => {
			cache.dispatchEvent(event);
		});

		expect(onSelect).toHaveBeenCalledWith('cache');
		expect(event.defaultPrevented).toBe(true);
	});

	test('renders only All when no toolsets are known yet', () => {
		render(
			createElement(GroupTabs, {
				counts: {},
				activeTab: ALL_TABS_KEY,
				total: 0,
				onSelect: () => {},
			})
		);

		expect(
			container.querySelectorAll('.toolset-strip__item')
		).toHaveLength(1);
	});
});

describe('ToolsetCell', () => {
	test('renders the toolset identifier', () => {
		render(createElement(ToolsetCell, { item: { tab_group: 'content' } }));
		expect(container.textContent).toBe('toolset/content');
	});

	test('renders an em dash when the ability belongs to no toolset', () => {
		render(createElement(ToolsetCell, { item: { tab_group: '' } }));
		expect(container.textContent).toBe('—');
		expect(container.querySelector('.tspill')).toBeNull();
	});

	test('treats a missing tab_group the same as an empty one', () => {
		render(createElement(ToolsetCell, { item: {} }));
		expect(container.textContent).toBe('—');
	});
});
