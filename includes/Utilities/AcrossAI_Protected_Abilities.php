<?php
/**
 * Manages protected system abilities that are hidden from REST endpoints and the UI.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Utilities
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Utilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Provides a single source of truth for protected ability slugs.
 *
 * Protected abilities are excluded from:
 * - GET /sitewide/abilities list response
 * - GET /sitewide/abilities/{slug} endpoints (returns 404)
 *
 * Extensible via the 'acrossai_abilities_manager_protected_slugs' WordPress filter.
 *
 * @since 0.1.0
 */
class AcrossAI_Protected_Abilities {

	/**
	 * The Toolset dispatchers, one per ability group.
	 *
	 * These are plumbing rather than abilities anyone edits: each takes an
	 * `action` of discover | info | execute and routes to the abilities in its
	 * own group. Listing one in the sitewide Abilities UI invites an operator
	 * to override or disable a dispatcher and silently lose the whole group
	 * behind it, and `AcrossAI_Abilities_Write_Controller` refuses writes to a
	 * protected slug — which is exactly the protection wanted here.
	 *
	 * Held as literals rather than read from the classes so this stays a plain
	 * value list with no load-order dependency: it is consulted long before the
	 * Toolsets construct. `Test_Protected_Abilities_Toolset_Coverage` pins it
	 * against the real `slug()` methods so a fourteenth group cannot drift.
	 *
	 * @since 0.0.34
	 * @var string[]
	 */
	public const TOOLSET_SLUGS = array(
		'toolset/appearance',
		'toolset/blocks',
		'toolset/cache',
		'toolset/configuration',
		'toolset/content',
		'toolset/cron',
		'toolset/database',
		'toolset/diagnostics',
		'toolset/elementor',
		'toolset/files',
		'toolset/rank-math',
		'toolset/updates',
		'toolset/users',
	);

	/**
	 * Get the list of protected ability slugs.
	 *
	 * Default protected slugs are the three vendor meta tools plus the Toolset
	 * dispatchers in self::TOOLSET_SLUGS.
	 *
	 * Every slug is listed unconditionally, including the two whose Toolsets
	 * only register when their plugin is active. A slug that is not registered
	 * cannot be looked up anyway, so naming it is inert — and the alternative,
	 * gating on plugin state, would make the protected set depend on load order
	 * for no gain.
	 *
	 * Other plugins can extend this list via the filter.
	 *
	 * @since  0.1.0
	 * @return string[] Array of protected ability slugs.
	 */
	public static function get_protected_slugs(): array {
		$default = array_merge(
			array(
				'mcp-adapter/discover-abilities',
				'mcp-adapter/execute-ability',
				'mcp-adapter/get-ability-info',
			),
			self::TOOLSET_SLUGS
		);

		/**
		 * Filters the list of protected ability slugs.
		 *
		 * Protected abilities are hidden from REST endpoints and the UI.
		 * They are excluded from GET /sitewide/abilities and return 404 from
		 * GET /sitewide/abilities/{slug}.
		 *
		 * @since 0.1.0
		 * @param string[] $default Array of default protected ability slugs.
		 */
		return (array) apply_filters( 'acrossai_abilities_manager_protected_slugs', $default );
	}

	/**
	 * Check if an ability slug is protected.
	 *
	 * @since  0.1.0
	 * @param  string $slug Ability slug to check.
	 * @return bool True if the slug is protected, false otherwise.
	 */
	public static function is_protected( string $slug ): bool {
		$protected_slugs = self::get_protected_slugs();
		return in_array( $slug, $protected_slugs, true );
	}
}
