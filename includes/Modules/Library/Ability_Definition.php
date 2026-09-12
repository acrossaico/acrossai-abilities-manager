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

defined( 'ABSPATH' ) || exit;

/**
 * Base class for ability definitions.
 *
 * Subclasses implement one abstract method (ability()) — the grouping fields
 * (category, slug, labels) are derived from it automatically.
 * The constructor hooks acrossai_abilities_api_init automatically.
 *
 * Feature 041: plugin-specific display fields live under
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
	 * Display fields are derived from this return value:
	 *   - Grouping key:  args['category']
	 *   - Per-row label: args['label']
	 *   - Unique slug:   name
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
	 * Return other abilities an AI caller might use instead of this one.
	 *
	 * Optional; default is an empty list. Override in subclasses that want to
	 * hint AI callers at cheaper alternatives — e.g. `content/update-page`
	 * pointing at `blocks/outline-post-blocks` + `blocks/update-post-block`
	 * for narrow edits that don't need a full-body rewrite.
	 *
	 * Each entry SHOULD include:
	 *   - 'slug'   (string, required) another ability's registered name
	 *   - 'reason' (string, required) one-line hint the AI reads
	 *
	 * And MAY include:
	 *   - 'saves'  (string) free-form savings hint, e.g.
	 *              "~29K tokens vs full page rewrite on a 97 KB page".
	 *
	 * The framework does NOT validate that the slug points at a registered
	 * ability (third-party ability plugins ship suggestions pointing at
	 * abilities their users may not have installed — validation would break
	 * that pattern). It also does NOT enforce field-level shape; ability
	 * tests should guard entries with empty slug or reason.
	 *
	 * Feature 095. Mirror of `suggested_plugins()` (Feature 088). Advisory
	 * only — no ability's execution depends on which suggestion the AI does
	 * or does not take.
	 *
	 * @since 0.0.34
	 * @return array<int,array<string,string>>
	 */
	protected function suggested_abilities(): array {
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

		// Feature 095: auto-inject subclass-declared suggested abilities into
		// meta.acrossai.suggested_abilities. Same silent-default behaviour —
		// empty override yields byte-identical payload to pre-Feature-095.
		$suggested_abilities = $this->suggested_abilities();
		if ( ! empty( $suggested_abilities ) && is_array( $suggested_abilities ) ) {
			if ( ! isset( $args['meta'] ) || ! is_array( $args['meta'] ) ) {
				$args['meta'] = array();
			}
			if ( ! isset( $args['meta']['acrossai'] ) || ! is_array( $args['meta']['acrossai'] ) ) {
				$args['meta']['acrossai'] = array();
			}
			$args['meta']['acrossai']['suggested_abilities'] = array_values( $suggested_abilities );
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
}
