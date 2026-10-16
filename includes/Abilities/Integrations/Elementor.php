<?php
/**
 * Elementor's toolset declaration.
 *
 * The same shape as {@see Rank_Math}, and the same bug: this plugin declares 81 `elementor/*`
 * abilities and Elementor 4.x declares 27 more from its own MCP module (`modules/mcp/`). Ours carry
 * `meta.acrossai.tab_group` and always grouped correctly; Elementor's carry nothing, so until this
 * file existed all of theirs fell through to `other` — the Elementor tab showed 81 of 106 and an
 * assistant reaching for `toolset-elementor` could not see the rest (issue #255).
 *
 * Declaring the `elementor` prefix closes that, and closes it permanently: the claim is on the
 * name prefix, not on a list of ability names, so an ability Elementor adds in a later release is
 * grouped the day it ships with no change here.
 *
 * Only the `elementor` prefix is claimed. Our own Elementor abilities also live under it, which is
 * harmless — the tagger never overwrites a declared group, so ours are untouched and in practice
 * this only ever catches Elementor's own.
 *
 * Not an {@see AcrossAI_Integration_Ability_Base}: Elementor does not gate its abilities behind a
 * setting, so there is no opt-in switch to own — only the grouping.
 *
 * Note for anyone reading this after a collision report: Elementor declares 27 abilities but only
 * 25 register, because both plugins declare `elementor/create-page` and
 * `elementor/update-page-settings`. This plugin loads first, so ours win and Elementor's two are
 * dropped with a `_doing_it_wrong` notice. Claiming the prefix does not and cannot fix that; see
 * `recovery/list-ability-collisions`.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Abilities/Integrations
 * @since      0.0.42
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Groups Elementor's own abilities alongside this plugin's.
 */
final class Elementor implements AcrossAI_Toolset_Integration {

	/**
	 * Toolset key.
	 *
	 * Matches what this plugin's 81 Elementor abilities already declare inline and
	 * `Toolset\Elementor::group()`. All three must agree or the halves split apart.
	 *
	 * @since 0.0.42
	 * @var   string
	 */
	public const TAB_GROUP = 'elementor';

	/**
	 * @since  0.0.42
	 * @return string
	 */
	public function group(): string {
		return self::TAB_GROUP;
	}

	/**
	 * Display name.
	 *
	 * Currently unused: `Toolset\Elementor` is a hand-written dispatcher and supplies the live label
	 * and description, so the bootstrap generates nothing for this group — `elementor` is listed in
	 * its `$claimed` array. Both are declared anyway so that retiring that class is a deletion
	 * rather than a rewrite.
	 *
	 * @since  0.0.42
	 * @return string
	 */
	public function toolset_label(): string {
		return __( 'Elementor', 'acrossai-abilities-manager' );
	}

	/**
	 * @since  0.0.42
	 * @return string
	 */
	public function toolset_description(): string {
		return __(
			'Work with Elementor: build and edit pages, manage templates and global widgets, read form submissions, manage custom code and site settings, clear its cache and toggle maintenance mode. Covers both the abilities this plugin provides and the ones Elementor registers itself. Elementor stores its pages as its own JSON document, not as post_content — use this tool rather than the Content or Blocks tools for anything on an Elementor page. Only present when Elementor is active. action=discover lists this group; action=info returns schemas; action=execute runs one ability.',
			'acrossai-abilities-manager'
		);
	}

	/**
	 * Ability names in the Elementor namespace.
	 *
	 * Claims the prefix for both halves. A prefix rather than a name list is the whole point: it
	 * covers abilities Elementor has not written yet.
	 *
	 * @since  0.0.42
	 * @return string[]
	 */
	public function ability_prefixes(): array {
		return array( 'elementor' );
	}

	/**
	 * Whether Elementor is present.
	 *
	 * Same check `Abilities\Elementor\Category_Registrar` uses, so the category and the toolset
	 * appear together.
	 *
	 * @since  0.0.42
	 * @return bool
	 */
	public function is_active(): bool {
		return class_exists( '\Elementor\Plugin' );
	}
}
