<?php
/**
 * Gives an ability a toolset when its own author did not.
 *
 * `AcrossAI_Ability_Group::of()` reads `meta.acrossai.tab_group` and returns `''` when it is absent —
 * and an ability registered by another plugin has no reason to set a key in this plugin's namespace.
 * Such an ability then belongs to no group: no tab, no count, `—` in the Toolset column, and
 * unreachable through every MCP Toolset dispatcher. On one measured site that was 36 of 425 abilities,
 * with nothing anywhere reporting it (issue #184).
 *
 * The fix is applied at registration, through the core `wp_register_ability_args` filter, rather than
 * as a fallback inside the readers. Two reasons, both load-bearing:
 *
 * 1. There is more than one reader. `AcrossAI_Ability_Group::of()` and
 *    `AcrossAI_Ability_Merger::normalize_registry()` read the same meta path independently, so a
 *    fallback in one would group the ability in the tab strip while the Toolset column still showed a
 *    dash. Writing the value once means every reader — including the MCP dispatchers and anything
 *    added later — sees the same answer without knowing this class exists.
 * 2. The value becomes real. It is on the `WP_Ability`, not synthesised per read.
 *
 * **It only ever fills a gap.** An ability that already declares a group is returned untouched. That
 * single rule is what lets an integration be supplied entirely by its host plugin, entirely by us, or
 * by both at once, with no coordination between the halves.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Utilities
 * @since      0.0.35
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Includes\Utilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\AcrossAI_Toolset_Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Assigns a `tab_group` to abilities that arrive without one.
 */
class AcrossAI_Ability_Group_Tagger {

	/**
	 * Abilities placed individually, because their plugin does not warrant a toolset.
	 *
	 * WordPress core's abilities are placed here because a rule keyed on the name prefix would produce
	 * a `core` group, and Feature 101 retired `core` deliberately after it had accumulated 105
	 * abilities as a catch-all. Placing these three by hand is what keeps that decision from being
	 * undone by a general rule.
	 *
	 * Only genuinely first-party abilities belong in this list. An ability from a third-party plugin
	 * with no toolset of its own — MCP Tracker's, for instance — is left to fall through to the
	 * catch-all instead. Placing it in one of our curated groups would imply this plugin owns it, and
	 * would quietly grow Diagnostics into the same bucket `core` became.
	 *
	 * @since 0.0.35
	 * @var   array<string, string>
	 */
	private const ABILITY_GROUPS = array(
		'core/get-site-info'        => 'diagnostics',
		'core/get-environment-info' => 'diagnostics',
		'core/get-user-info'        => 'users',
	);

	/**
	 * Tag an ability's registration args when they carry no group.
	 *
	 * Hooked to `wp_register_ability_args` at priority 10 — long before
	 * `AcrossAI_Ability_Override_Processor` injects DB overrides at P100000. The two touch different
	 * keys, so the ordering is for clarity rather than correctness.
	 *
	 * @since  0.0.35
	 * @param  mixed  $args Registration args.
	 * @param  string $name Ability name, namespaced.
	 * @return mixed Args, with `meta.acrossai.tab_group` filled when it was missing.
	 */
	public static function tag( $args, $name = '' ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}

		$name = (string) $name;

		if ( '' === $name || '' !== self::declared_group( $args ) ) {
			return $args;
		}

		// A Toolset dispatcher declares `meta.acrossai.toolset` and no group, on purpose: it must not
		// appear inside the group it serves. Tagging it would put it in its own toolset.
		$acrossai = ( isset( $args['meta']['acrossai'] ) && is_array( $args['meta']['acrossai'] ) )
			? $args['meta']['acrossai']
			: array();

		if ( ! empty( $acrossai['toolset'] ) ) {
			return $args;
		}

		$group = self::resolve( $name );

		if ( '' === $group ) {
			return $args;
		}

		$acrossai['tab_group']    = $group;
		$args['meta']             = isset( $args['meta'] ) && is_array( $args['meta'] ) ? $args['meta'] : array();
		$args['meta']['acrossai'] = $acrossai;

		return $args;
	}

	/**
	 * The group an untagged ability belongs to.
	 *
	 * Order matters: a per-ability placement beats its plugin's prefix, so one ability can be pulled
	 * out of an integration's group without disturbing the rest.
	 *
	 * @since  0.0.35
	 * @param  string $name Ability name.
	 * @return string Group key, or '' to leave the ability ungrouped.
	 */
	public static function resolve( string $name ): string {
		$abilities = self::ability_map();

		if ( isset( $abilities[ $name ] ) ) {
			return (string) $abilities[ $name ];
		}

		$prefixes = self::prefix_map();
		$slash    = strpos( $name, '/' );
		$prefix   = false !== $slash ? substr( $name, 0, $slash ) : $name;

		if ( isset( $prefixes[ $prefix ] ) ) {
			return (string) $prefixes[ $prefix ];
		}

		return AcrossAI_Toolset_Integrations::CATCH_ALL_GROUP;
	}

	/**
	 * Per-ability placements, filterable.
	 *
	 * @since  0.0.35
	 * @return array<string, string>
	 */
	private static function ability_map(): array {
		/**
		 * Filters the per-ability toolset placements.
		 *
		 * Keyed by full ability name. Takes precedence over the prefix map, so this is how a single
		 * ability is moved out of the group its plugin otherwise owns.
		 *
		 * @since 0.0.35
		 * @param array<string, string> $map Ability name => group key.
		 */
		$map = apply_filters( 'acrossai_ability_group_tagger_map', self::ABILITY_GROUPS );

		return is_array( $map ) ? $map : self::ABILITY_GROUPS;
	}

	/**
	 * Prefix placements, from the registered integrations.
	 *
	 * @since  0.0.35
	 * @return array<string, string>
	 */
	private static function prefix_map(): array {
		/**
		 * Filters the ability-name-prefix toolset placements.
		 *
		 * Derived from every registered integration's `ability_prefixes()`. Prefer registering an
		 * integration over filtering this — an integration also supplies the label, the description
		 * and the dispatcher, where a bare prefix here would create a group with no MCP tool.
		 *
		 * @since 0.0.35
		 * @param array<string, string> $map Ability-name prefix => group key.
		 */
		$map = apply_filters(
			'acrossai_ability_group_tagger_prefix_map',
			AcrossAI_Toolset_Integrations::prefix_map()
		);

		return is_array( $map ) ? $map : array();
	}

	/**
	 * The group already declared in the args, if any.
	 *
	 * @since  0.0.35
	 * @param  array<string, mixed> $args Registration args.
	 * @return string
	 */
	private static function declared_group( array $args ): string {
		if ( ! isset( $args['meta']['acrossai']['tab_group'] ) ) {
			return '';
		}

		return (string) $args['meta']['acrossai']['tab_group'];
	}
}
