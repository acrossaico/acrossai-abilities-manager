/**
 * Shared constants for the abilities screen.
 *
 * @since 0.2.0
 */

/**
 * Sentinel for the "All" toolset tab.
 *
 * Salvaged from the retiring ability-library page in Feature 102. It is the value that means
 * "no toolset filter", and `buildUrlFromTab()` strips `?tab=` entirely when it sees it, so the
 * canonical URL for the default view stays clean.
 *
 * @type {string}
 */
export const ALL_TABS_KEY = '__all__';
