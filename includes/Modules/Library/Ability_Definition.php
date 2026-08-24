<?php
/**
 * Abstract base class for ability definitions.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage includes/Modules/Library
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Modules\Library;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Config;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for ability definitions.
 *
 * Subclasses implement one abstract method (ability()) — the Library page
 * derives its grouping fields (category, slug, labels) automatically.
 * The constructor hooks acrossai_abilities_api_init automatically.
 *
 * Feature 041: plugin-specific Library display fields live under
 * $args['meta']['acrossai']. Sibling of $args['meta']['mcp'] and
 * $args['meta']['annotations']. See PATTERN-META-ACROSSAI-NAMESPACE.
 *
 * Optional: $args['meta']['acrossai']['sub_group'] adds a display-only
 * sub-heading inside the Library Specific panel. Does NOT affect saved
 * config or execution.
 *
 * Optional: $args['meta']['acrossai']['sub_group_label'] overrides the
 * auto-derived ucwords(str_replace('-', ' ', sub_group)) label.
 *
 * Optional: $args['meta']['acrossai']['tab_group'] groups the ability
 * under a page-level tab on the Library admin page. Display-only — never
 * persisted, never affects execution or REST. Sanitized at the Registry
 * boundary.
 */
abstract class Ability_Definition {

	/**
	 * Register the push_definition filter callback.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		add_filter( 'acrossai_abilities_api_init', array( $this, 'push_definition' ) );
	}

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * Must return an array with:
	 *   - 'name'  (string) the unique ability name, e.g. 'plugin-slug/ability-slug'
	 *   - 'args'  (array)  the args passed to wp_register_ability:
	 *                      label, description, category, execute_callback,
	 *                      permission_callback, input_schema, output_schema, meta
	 *
	 * The Library page derives its display fields from this return value:
	 *   - Library card grouping: args['category']
	 *   - Per-row label:         args['label']
	 *   - Unique slug:           name
	 */
	abstract protected function ability(): array;

	/**
	 * Return external WordPress plugins the site admin may consider as
	 * alternatives or specialists for this ability's scope.
	 *
	 * Optional; default is an empty list. Override in subclasses that want to
	 * surface plugin suggestions on their Library card and in agent discovery.
	 *
	 * Each entry SHOULD include:
	 *   - 'slug'   (string, required)  wordpress.org plugin slug
	 *   - 'name'   (string, required)  display name
	 *   - 'reason' (string, required)  one-line "why this plugin for this ability"
	 *
	 * And MAY include:
	 *   - 'url'                           (string) override link target; defaults
	 *                                     to https://wordpress.org/plugins/{slug}/
	 *   - 'covers'                        (string) short scope summary
	 *   - 'plugin_provides_abilities'     (bool)   does the suggested plugin ship
	 *                                     its own wp_register_ability() abilities?
	 *   - 'acrossai_provides_integration' (bool)   does our plugin ship an
	 *                                     AcrossAI_Integration_Ability_Base for it?
	 *
	 * Feature 088. See specs/088-ability-suggested-plugins-framework/ for the
	 * full data model and behavioural contracts. Author-curated at declaration
	 * time; runtime install-status enrichment happens in the Registry.
	 *
	 * @since 0.0.33
	 * @return array<int,array<string,scalar>>
	 */
	protected function suggested_plugins(): array {
		return array();
	}

	/**
	 * Filter callback — wired automatically by the constructor.
	 *
	 * Derives Library grouping fields from ability() so subclasses only need
	 * to implement the single ability() method.
	 *
	 * @param array $definitions Existing definitions collected so far.
	 * @return array
	 */
	public function push_definition( array $definitions ): array {
		$spec = $this->ability();
		$name = $spec['name'] ?? '';
		$args = $spec['args'] ?? array();

		// Feature 088: auto-inject subclass-declared suggested plugins into
		// meta.acrossai.suggested_plugins. Only writes when the subclass
		// override returned a non-empty array; abilities that do not override
		// suggested_plugins() see zero payload change.
		$suggested = $this->suggested_plugins();
		if ( ! empty( $suggested ) && is_array( $suggested ) ) {
			if ( ! isset( $args['meta'] ) || ! is_array( $args['meta'] ) ) {
				$args['meta'] = array();
			}
			if ( ! isset( $args['meta']['acrossai'] ) || ! is_array( $args['meta']['acrossai'] ) ) {
				$args['meta']['acrossai'] = array();
			}
			$args['meta']['acrossai']['suggested_plugins'] = array_values( $suggested );
		}

		$category = $args['category'] ?? '';

		// Feature 041: plugin-specific Library display fields live under
		// $args['meta']['acrossai']. Hard cut — top-level $args['sub_group']
		// / $args['tab_group'] / $args['sub_group_label'] no longer read.
		// See PATTERN-META-ACROSSAI-NAMESPACE.
		$meta_acrossai = ( isset( $args['meta']['acrossai'] ) && is_array( $args['meta']['acrossai'] ) )
			? $args['meta']['acrossai']
			: array();

		$sub_group = isset( $meta_acrossai['sub_group'] ) ? (string) $meta_acrossai['sub_group'] : '';
		$tab_group = isset( $meta_acrossai['tab_group'] ) ? (string) $meta_acrossai['tab_group'] : '';

		$row = array(
			'category'       => $category,
			'category_label' => ucwords( str_replace( '-', ' ', $category ) ),
			'slug'           => $name,
			'slug_label'     => $args['label'] ?? $name,
			'name'           => $name,
			'args'           => $args,
		);

		if ( '' !== $sub_group ) {
			$row['sub_group']       = $sub_group;
			$row['sub_group_label'] = isset( $meta_acrossai['sub_group_label'] ) && '' !== $meta_acrossai['sub_group_label']
				? (string) $meta_acrossai['sub_group_label']
				: ucwords( str_replace( '-', ' ', $sub_group ) );
		}

		if ( '' !== $tab_group ) {
			$row['tab_group'] = $tab_group;
		}

		$definitions[] = $row;

		return $definitions;
	}

	/**
	 * Returns true when the saved library config represents an all-enabled state.
	 *
	 * Every persisted entry must have enabled=true. Empty saved config (the
	 * post-Enable-All sparse-storage state) also returns true because absent
	 * entries default to enabled=true.
	 *
	 * @since 0.1.0
	 * @return bool
	 */
	public static function is_all_enabled(): bool {
		$config = AcrossAI_Ability_Library_Config::get_config();
		foreach ( $config as $entry ) {
			if ( isset( $entry['enabled'] ) && false === (bool) $entry['enabled'] ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Returns true when every currently registered category has an explicit
	 * enabled=false entry in the saved library config.
	 *
	 * Cross-references the Registry to know the full set of registered
	 * categories — an admin-visible "Disable All" state requires an
	 * explicit false for every one of them (sparse storage never yields
	 * this state implicitly).
	 *
	 * @since 0.1.0
	 * @return bool
	 */
	public static function is_all_disabled(): bool {
		$config     = AcrossAI_Ability_Library_Config::get_config();
		$registered = self::registered_category_slugs();
		if ( empty( $registered ) ) {
			return false;
		}
		foreach ( $registered as $category ) {
			$entry = $config[ $category ] ?? null;
			if ( ! is_array( $entry ) || true === ( $entry['enabled'] ?? true ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Returns the tri-state of the bulk toggle across the FULL registered set.
	 *
	 * This is the value the JS reads on first paint (initial 'All' tab).
	 * After first paint the JS re-derives per-tab state from the live config;
	 * this helper is not consulted for tab-scoped decisions.
	 *
	 * @since 0.1.0
	 * @return string One of 'all' | 'none' | 'mixed'.
	 */
	public static function bulk_toggle_state(): string {
		if ( self::is_all_enabled() ) {
			return 'all';
		}
		if ( self::is_all_disabled() ) {
			return 'none';
		}
		return 'mixed';
	}

	/**
	 * Collect the unique category slugs from the Library Registry.
	 *
	 * SEC-052-I-001: class_exists() uses default autoload=on. Passing false
	 * as the second argument silently no-ops when nothing else has referenced
	 * the class yet (BUG-CLASS-EXISTS-AUTOLOAD-FALSE-SILENT). Do not change.
	 *
	 * @since 0.1.0
	 * @return string[]
	 */
	private static function registered_category_slugs(): array {
		if ( ! class_exists( AcrossAI_Ability_Library_Registry::class ) ) {
			return array();
		}
		$definitions = AcrossAI_Ability_Library_Registry::instance()->get_definitions();
		$slugs       = array();
		foreach ( $definitions as $def ) {
			if ( isset( $def['category'] ) && '' !== $def['category'] ) {
				$slugs[ $def['category'] ] = true;
			}
		}
		return array_keys( $slugs );
	}
}
