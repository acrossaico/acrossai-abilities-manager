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
		return __( 'Work with Elementor: build and edit pages, manage templates and global widgets, read form submissions, manage custom code and site settings, clear its cache and toggle maintenance mode. Only present when Elementor is active. action=discover lists this group; action=info returns schemas; action=execute runs one ability.', 'acrossai-abilities-manager' );
	}
}
