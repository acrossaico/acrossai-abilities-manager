import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { Button, Notice, TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { Icon, plugins } from '@wordpress/icons';
import { fetchConfig, saveConfig } from '../api';
import useLibraryTabSync from '../hooks/useLibraryTabSync';
import LibraryCard from './LibraryCard';

/**
 * Sentinel name for the always-present default tab. Underscores chosen to
 * avoid collision with any sanitize_key() output (which strips underscores
 * inside identifiers but allows them as full names).
 */
export const ALL_TABS_KEY = '__all__';

/**
 * Group flat definitions array by category into card items.
 *
 * Named export so the helper can be unit-tested without rendering React
 * (per PATTERN-NAMED-EXPORT-JEST).
 *
 * @param {Array} definitions Raw definitions from window.acrossaiAbilityLibraryData.
 * @return {Array} Grouped items, one per category.
 */
export function groupDefinitions(definitions) {
	const map = new Map();
	for (const def of definitions) {
		const {
			category,
			category_label: categoryLabel,
			slug,
			slug_label: slugLabel,
			name,
			sub_group: subGroup,
			sub_group_label: subGroupLabel,
			tab_group: tabGroup,
			card_variant: cardVariant,
			args,
		} = def;
		const description =
			typeof args?.description === 'string'
				? args.description.trim()
				: '';
		if (!map.has(category)) {
			map.set(category, {
				id: category,
				category,
				categoryLabel,
				// Feature 055 UI — sorted-unique tab_group identifiers this
				// category contributes to. Usually one entry (a single
				// category maps 1:1 to one tab), but a category whose
				// abilities declare mixed tab_groups will surface all of
				// them so the header chip shows the full membership.
				tabGroups: new Set(),
				// Feature 060 — display-only card variant marker. Present
				// when the definition rows declared card_variant (e.g.
				// 'integration' for third-party integration cards). First
				// non-empty value seen wins; regular categories leave this
				// undefined so LibraryCard renders exactly as today.
				cardVariant: undefined,
				slugs: [],
			});
		}
		const group = map.get(category);
		if (tabGroup) {
			group.tabGroups.add(tabGroup);
		}
		if (cardVariant && !group.cardVariant) {
			group.cardVariant = cardVariant;
		}
		if (!group.slugs.some((s) => s.slug === slug)) {
			group.slugs.push({
				slug,
				slugLabel,
				name,
				subGroup: subGroup || '',
				subGroupLabel: subGroupLabel || '',
				tabGroup: tabGroup || '',
				description,
			});
		}
	}
	// Freeze tabGroups as a sorted array (deterministic React key order).
	return Array.from(map.values()).map((item) => ({
		...item,
		tabGroups: Array.from(item.tabGroups).sort((a, b) =>
			a.toLowerCase().localeCompare(b.toLowerCase())
		),
	}));
}

/**
 * Reserved tab identifier pinned to the first position in the tab list
 * (i.e. immediately after the `All` tab).
 *
 * Feature 046: the absorbed acrossai-core-abilities plugin's default
 * `tab_group` is `core`. Site admins expect the Core tab to appear as
 * the second option (right after `All`) even after other categories
 * introduce their own tab_groups. When `core` is absent from the
 * definitions this pin is a no-op.
 */
const PINNED_FIRST_TAB_GROUP = 'core';

/**
 * Collect the unique non-empty tab_group identifiers across all slug records.
 *
 * Sort: `core` is pinned first (Feature 046); remaining identifiers sort
 * case-insensitive alphabetical by sanitized identifier (FR-013).
 * Named export per PATTERN-NAMED-EXPORT-JEST.
 *
 * @param {Array} items Output of groupDefinitions().
 * @return {string[]} Sorted unique tab_group identifiers.
 */
export function collectTabGroups(items) {
	const set = new Set();
	for (const item of items) {
		for (const slug of item.slugs) {
			if (slug.tabGroup) {
				set.add(slug.tabGroup);
			}
		}
	}
	const sorted = Array.from(set).sort((a, b) =>
		a.toLowerCase().localeCompare(b.toLowerCase())
	);
	const coreIndex = sorted.indexOf(PINNED_FIRST_TAB_GROUP);
	if (coreIndex > 0) {
		sorted.splice(coreIndex, 1);
		sorted.unshift(PINNED_FIRST_TAB_GROUP);
	}
	return sorted;
}

/**
 * Filter the grouped items by the active tab.
 *
 * When activeTab equals ALL_TABS_KEY, returns the input array reference
 * unchanged (so React useMemo identity is stable). Otherwise, returns new
 * item objects whose slugs array is filtered to entries matching the
 * active tab; items with no surviving slug are dropped.
 *
 * Named export per PATTERN-NAMED-EXPORT-JEST.
 *
 * @param {Array}  items     Output of groupDefinitions().
 * @param {string} activeTab Active tab identifier or ALL_TABS_KEY.
 * @return {Array} Filtered items.
 */
export function filterItemsByTabGroup(items, activeTab) {
	if (activeTab === ALL_TABS_KEY) {
		return items;
	}
	const out = [];
	for (const item of items) {
		const matching = item.slugs.filter((s) => s.tabGroup === activeTab);
		if (matching.length > 0) {
			out.push({ ...item, slugs: matching });
		}
	}
	return out;
}

/**
 * Convert a sanitized tab_group identifier into a display label.
 *
 * Feature 099 moved the implementation to `src/js/shared/titleCaseTabLabel.js`
 * when the Quick Connect wizard became a second consumer (Constitution §VI —
 * extract before the second use, never duplicate). Re-exported here so existing
 * importers (`LibraryCard`, the Feature 052 test) keep working unchanged.
 *
 * Named export per PATTERN-NAMED-EXPORT-JEST.
 */
export { titleCaseTabLabel } from '../../shared/titleCaseTabLabel';

/**
 * Collect the set of category slugs currently in scope for a bulk action,
 * based on the active tab.
 *
 * When `activeTab === allTabsKey`, returns every unique category present in
 * `items`. Otherwise returns only the categories whose slugs include at least
 * one with `tabGroup === activeTab`. Matches the same tab-membership rule
 * used by `filterItemsByTabGroup()`.
 *
 * Named export per PATTERN-NAMED-EXPORT-JEST.
 *
 * @param {Array}  items      Grouped category items (from groupDefinitions).
 * @param {string} activeTab  Current tab slug (or ALL_TABS_KEY sentinel).
 * @param {string} allTabsKey The sentinel for the "All" tab.
 * @return {string[]}         In-scope category slugs.
 */
export function collectInScopeCategories(items, activeTab, allTabsKey) {
	const out = [];
	for (const item of items) {
		if (!item.category) {
			continue;
		}
		if (activeTab === allTabsKey) {
			out.push(item.category);
			continue;
		}
		if (item.slugs.some((s) => s.tabGroup === activeTab)) {
			out.push(item.category);
		}
	}
	return out;
}

/**
 * Build a bulk-toggle patch scoped to a specific set of categories.
 *
 * Only entries for `inScopeCategories` are rewritten — their `enabled`
 * boolean is set to `enabled` and their prior `mode` + `sub_keys` are
 * preserved. Entries outside the scope pass through byte-for-byte.
 *
 * Named export per PATTERN-NAMED-EXPORT-JEST.
 *
 * @param {Object}   currentConfig     Existing config keyed by category.
 * @param {string[]} inScopeCategories Categories the action targets.
 * @param {boolean}  enabled           Target enabled value for in-scope entries.
 * @return {Object}                    New config object.
 */
export function buildBulkPatch(currentConfig, inScopeCategories, enabled) {
	const next = { ...currentConfig };
	for (const category of inScopeCategories) {
		const prior = currentConfig[category] ?? { mode: 'all', sub_keys: {} };
		next[category] = {
			enabled,
			mode: prior.mode ?? 'all',
			sub_keys: prior.sub_keys ?? {},
		};
	}
	return next;
}

/**
 * Return 'all' | 'none' | 'mixed' for the given in-scope categories against
 * the current config. Missing entries default to enabled=true (matching the
 * server-side sparse-storage semantics).
 *
 * Named export per PATTERN-NAMED-EXPORT-JEST.
 *
 * @param {Object}   currentConfig     Full config keyed by category.
 * @param {string[]} inScopeCategories Categories to evaluate.
 * @return {'all' | 'none' | 'mixed'}
 */
export function computeInScopeBulkState(currentConfig, inScopeCategories) {
	if (inScopeCategories.length === 0) {
		return 'all';
	}
	let anyEnabled = false;
	let anyDisabled = false;
	for (const category of inScopeCategories) {
		const isEnabled = currentConfig[category]?.enabled ?? true;
		if (isEnabled) {
			anyEnabled = true;
		} else {
			anyDisabled = true;
		}
		if (anyEnabled && anyDisabled) {
			return 'mixed';
		}
	}
	if (anyEnabled && !anyDisabled) {
		return 'all';
	}
	return 'none';
}

/**
 * Library admin page — renders one LibraryCard per registered ability group.
 */
export default function LibraryPage() {
	const data = window.acrossaiAbilityLibraryData || {};
	const items = groupDefinitions(data.definitions || []);

	const [config, setConfig] = useState({});
	const [error, setError] = useState(null);
	const [activeTab, setActiveTab] = useState(ALL_TABS_KEY);

	const initialLoadComplete = useRef(false);

	useEffect(() => {
		fetchConfig()
			.then((saved) => {
				setConfig(saved);
				initialLoadComplete.current = true;
			})
			.catch(() => {
				setError(
					__(
						'Failed to load configuration.',
						'acrossai-abilities-manager'
					)
				);
				initialLoadComplete.current = true;
			});
	}, []);

	function handleChange(category, updatedEntry) {
		const next = { ...config, [category]: updatedEntry };
		setConfig(next);

		if (!initialLoadComplete.current) {
			return;
		}

		setError(null);
		saveConfig(next).catch(() =>
			setError(
				__(
					'Failed to save configuration.',
					'acrossai-abilities-manager'
				)
			)
		);
	}

	const tabGroups = useMemo(() => collectTabGroups(items), [items]);

	useLibraryTabSync(activeTab, setActiveTab, tabGroups);

	const inScopeCategories = useMemo(
		() => collectInScopeCategories(items, activeTab, ALL_TABS_KEY),
		[items, activeTab]
	);

	const inScopeBulkState = useMemo(
		() => computeInScopeBulkState(config, inScopeCategories),
		[config, inScopeCategories]
	);

	function runBulkPatch(enabled) {
		// FR-008: click is a silent no-op when already at target state.
		if (enabled && inScopeBulkState === 'all') {
			return;
		}
		if (!enabled && inScopeBulkState === 'none') {
			return;
		}
		const next = buildBulkPatch(config, inScopeCategories, enabled);
		setConfig(next);
		setError(null);
		saveConfig(next).catch(() =>
			setError(__('Failed to save.', 'acrossai-abilities-manager'))
		);
	}

	function handleEnableAll() {
		runBulkPatch(true);
	}

	function handleDisableAll() {
		runBulkPatch(false);
	}

	function renderCards(visibleItems) {
		return visibleItems.map((item) => (
			<LibraryCard
				key={item.category}
				item={item}
				config={config}
				onChange={handleChange}
			/>
		));
	}

	const tabs = useMemo(
		() => [
			{
				name: ALL_TABS_KEY,
				title: __('All', 'acrossai-abilities-manager'),
			},
			...tabGroups.map((g) => ({ name: g, title: titleCaseTabLabel(g) })),
		],
		[tabGroups]
	);

	return (
		<div className="acrossai-library-page">
			{error && (
				<Notice
					status="error"
					isDismissible
					onRemove={() => setError(null)}
				>
					{error}
				</Notice>
			)}

			<div className="acrossai-library-page__header">
				<h1 className="acrossai-library-page__title">
					{__('Ability Integrations', 'acrossai-abilities-manager')}
				</h1>
				<div className="acrossai-library-page__header-actions">
					<Button variant="primary" onClick={handleEnableAll}>
						{__('Enable All', 'acrossai-abilities-manager')}
					</Button>
					<Button
						variant="secondary"
						isDestructive
						onClick={handleDisableAll}
					>
						{__('Disable All', 'acrossai-abilities-manager')}
					</Button>
				</div>
			</div>

			{items.length === 0 && (
				<div
					className="acrossai-library-page__empty"
					role="region"
					aria-labelledby="acrossai-library-empty-title"
				>
					<div className="acrossai-library-page__empty-icon">
						<Icon icon={plugins} size={40} />
					</div>
					<h2
						id="acrossai-library-empty-title"
						className="acrossai-library-page__empty-title"
					>
						{__(
							'Ability library is empty',
							'acrossai-abilities-manager'
						)}
					</h2>
					<p className="acrossai-library-page__empty-description">
						{__(
							'The bundled abilities did not load this request. Try deactivating and reactivating the plugin, then reload this page. If the problem persists, check the WordPress debug log for filter or class-loading errors.',
							'acrossai-abilities-manager'
						)}
					</p>
				</div>
			)}

			{items.length > 0 && tabGroups.length === 0 && renderCards(items)}

			{items.length > 0 && tabGroups.length > 0 && (
				<TabPanel
					key={activeTab}
					className="acrossai-library-page__tabs"
					tabs={tabs}
					initialTabName={activeTab}
					onSelect={setActiveTab}
				>
					{(tab) =>
						renderCards(filterItemsByTabGroup(items, tab.name))
					}
				</TabPanel>
			)}
		</div>
	);
}
