<?php
/**
 * Elementor's own abilities — the switch that makes them exist.
 *
 * Elementor 4.x ships an MCP module at `elementor/modules/mcp/` declaring 27 abilities of its own,
 * and registers none of them until a site setting says so. `modules/mcp/module.php:151`:
 *
 *     add_action( 'wp_abilities_api_categories_init', [ $this, 'register_ability_category' ] );
 *
 *     if ( self::is_site_mcp_exposure_enabled() ) {
 *         add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
 *     }
 *
 * Note the asymmetry: the category registers unconditionally while the abilities do not. That is why
 * a site can show `elementor` among the registered ability categories and still hold none of
 * Elementor's abilities — which reads as a bug in our grouping and is not one.
 *
 * The setting is the `elementor_mcp_enabled` option, read by
 * `Elementor\MCP\Composer\Admin\McpSettingsController::is_enabled()`, which returns false when the
 * option is absent. Its own docblock claims the opposite ("Missing or invalid values default to
 * enabled"); the code is authoritative, and the default is off. Elementor surfaces it at
 * Elementor → Elementor MCP (`page=elementor-mcp`).
 *
 * **An option, not a filter** — which is the whole reason
 * {@see AcrossAI_Integration_Ability_Base::force_option_true()} exists. We short-circuit the read
 * rather than writing the option, so Elementor's stored setting stays whatever the site owner chose
 * and switching this row off restores it rather than leaving ours behind.
 *
 * Two of Elementor's 27 never register even with this on: `elementor/create-page` and
 * `elementor/update-page-settings` are names this plugin also declares, and we load first. See
 * issue #261 for that decision; it is not something this switch can fix.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage includes/Abilities/Integrations
 * @since      0.0.42
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Integrations;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Integrations\AcrossAI_Integration_Ability_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes Elementor's MCP exposure setting as one switch.
 */
final class Elementor_Opt_In extends AcrossAI_Integration_Ability_Base {

	/**
	 * Group and Library category this integration owns.
	 *
	 * The same key `Toolset\Elementor::group()` uses, so our 81 Elementor abilities and Elementor's
	 * own share one row and one switch.
	 *
	 * @since 0.0.42
	 * @var   string
	 */
	public const TAB_GROUP = 'elementor';

	/**
	 * The option Elementor reads.
	 *
	 * @since 0.0.42
	 * @var   string
	 */
	private const HOST_OPTION = 'elementor_mcp_enabled';

	/**
	 * @since  0.0.42
	 * @return string
	 */
	protected function slug(): string {
		return self::TAB_GROUP;
	}

	/**
	 * @since  0.0.42
	 * @return string
	 */
	protected function label(): string {
		return __( 'Elementor (its own MCP abilities)', 'acrossai-abilities-manager' );
	}

	/**
	 * Seeded on, like every other integration.
	 *
	 * This returned false until 0.0.42, on the argument that switching it on turns on Elementor's
	 * own site-wide MCP exposure — a feature its authors ship off — and that making that decision
	 * from inside an unrelated plugin's update was overreach.
	 *
	 * The counter-argument won, and it is about what the switch is for. Somebody who installs
	 * Abilities Manager has asked for their site's abilities to be usable; an Elementor row that
	 * reads "Enabled · 109 abilities" while quietly withholding 25 of them is not describing the
	 * site. Worse, the two halves then had different defaults — ours on, Elementor's off — so one
	 * switch produced two answers and the card could not honestly report either.
	 *
	 * The decision is still the site owner's, and still reversible in one click: switching this row
	 * off restores Elementor's own setting untouched, because {@see force_option_true()} filters
	 * the read rather than writing the option.
	 *
	 * @since  0.0.42
	 * @return bool
	 */
	protected function seeds_enabled(): bool {
		return true;
	}

	/**
	 * Whether Elementor is present, with the MCP module this switch drives.
	 *
	 * Checks the module rather than just `Elementor\Plugin`: on an Elementor old enough to lack
	 * `modules/mcp/` there is no setting to flip, and a switch that cannot do anything should not
	 * appear.
	 *
	 * @since  0.0.42
	 * @return bool
	 */
	protected function is_plugin_active(): bool {
		return class_exists( '\Elementor\Plugin' )
			&& class_exists( '\Elementor\Modules\Mcp\Module' );
	}

	/**
	 * Satisfy the host's gate without writing to it.
	 *
	 * @since  0.0.42
	 * @return void
	 */
	protected function enable_filter(): void {
		$this->force_option_true( self::HOST_OPTION );
	}

	/**
	 * What switching this on unlocks.
	 *
	 * Display-only, and deliberately not the full 27: this names the groups rather than every slug,
	 * because Elementor's set moves between releases and a stale list here would describe abilities
	 * that no longer exist. The live set is always what `toolset/elementor` discover returns.
	 *
	 * @since  0.0.42
	 * @return array<int, array{slug: string, label: string, description?: string}>
	 */
	protected function abilities(): array {
		return array(
			array(
				'slug'        => 'elementor/build-composition',
				'label'       => __( 'Build and edit V4 element trees', 'acrossai-abilities-manager' ),
				'description' => __( 'Elementor\'s own authoring path for atomic (V4) elements, plus publishing and preview links.', 'acrossai-abilities-manager' ),
			),
			array(
				'slug'        => 'elementor/manage-global-variable',
				'label'       => __( 'V4 global design system', 'acrossai-abilities-manager' ),
				'description' => __( 'Global variables, global CSS classes and site-wide default styles on the active kit.', 'acrossai-abilities-manager' ),
			),
			array(
				'slug'        => 'elementor/list-widget-schemas',
				'label'       => __( 'Widget schemas and guidance', 'acrossai-abilities-manager' ),
				'description' => __( 'Per-widget JSON Schemas, dynamic tags, component and asset catalogues, and Elementor\'s own design guidance.', 'acrossai-abilities-manager' ),
			),
		);
	}
}
