<?php
/**
 * The Elementor Toolset.
 *
 * Elementor page building, templates, forms and site settings.
 *
 * Four declarations and no behaviour — everything else is
 * {@see Base_Toolset_Ability}. If this class ever needs more than these
 * methods, the shared class is missing something; add it there.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Abilities/Toolset
 * @since      0.0.34
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Toolset;

defined( 'ABSPATH' ) || exit;

/**
 * Dispatcher for the Elementor group.
 */
final class Elementor extends Base_Toolset_Ability {

	/**
	 * @return string
	 */
	protected function group(): string {
		return 'elementor';
	}

	/**
	 * @return string
	 */
	protected function slug(): string {
		return 'toolset/elementor';
	}

	/**
	 * @return string
	 */
	protected function toolset_label(): string {
		return __( 'Elementor', 'acrossai-abilities-manager' );
	}

	/**
	 * @return string
	 */
	protected function toolset_description(): string {
		return __( 'Work with Elementor: build and edit pages, manage templates and global widgets, read form submissions, manage custom code and site settings, clear its cache and toggle maintenance mode. Elementor stores its pages as its own JSON document, not as post_content — use this tool rather than the Content or Blocks tools for anything on an Elementor page. Only present when Elementor is active. This group is large, so narrow action=discover with sub_group: elementor-elements (element tree and widget inserts), elementor-documents (whole-document read and write), elementor-templates (templates, import/export, theme builder conditions), elementor-kits (kits and global styles), elementor-guidance (widget catalog, style guide, render context), elementor-design-audit (read-only design analysis), elementor-design-fixes (subtree mutations that apply those findings), elementor-system (cache, URL replace, maintenance, experiments), elementor-custom-code and elementor-forms (both Elementor Pro). action=info returns schemas; action=execute runs one ability.', 'acrossai-abilities-manager' );
	}
}
