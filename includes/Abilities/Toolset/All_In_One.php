<?php
/**
 * The All-in-One WP Migration Toolset.
 *
 * Archive state, inventory, exposure, and exporting or labelling an archive.
 *
 * Four declarations and no behaviour — everything else is
 * {@see Base_Toolset_Ability}. If this class ever needs more than these
 * methods, the shared class is missing something; add it there.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Abilities/Toolset
 * @since      0.0.35
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Toolset;

defined( 'ABSPATH' ) || exit;

/**
 * Dispatcher for the Cache group.
 */
final class All_In_One extends Base_Toolset_Ability {

	/**
	 * @return string
	 */
	protected function group(): string {
		return 'all-in-one-wp-migration';
	}

	/**
	 * @return string
	 */
	protected function slug(): string {
		return 'toolset/all-in-one-wp-migration';
	}

	/**
	 * @return string
	 */
	protected function toolset_label(): string {
		return __( 'All-in-One WP Migration', 'acrossai-abilities-manager' );
	}

	/**
	 * @return string
	 */
	protected function toolset_description(): string {
		return __( 'Whether this site can be recovered through All-in-One WP Migration: what archives exist and how recent they are, whether they are reachable over HTTP, and exporting, labelling or removing one. Restoring is part of their paid Unlimited Extension, and the ability says so rather than failing. action=discover lists this group; action=info returns schemas; action=execute runs one ability.', 'acrossai-abilities-manager' );
	}
}
