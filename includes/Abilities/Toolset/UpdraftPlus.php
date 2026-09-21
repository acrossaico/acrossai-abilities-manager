<?php
/**
 * The UpdraftPlus Toolset.
 *
 * Backup state, inventory, exposure, and taking or restoring a backup.
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
final class UpdraftPlus extends Base_Toolset_Ability {

	/**
	 * @return string
	 */
	protected function group(): string {
		return 'updraftplus';
	}

	/**
	 * @return string
	 */
	protected function slug(): string {
		return 'toolset/updraftplus';
	}

	/**
	 * @return string
	 */
	protected function toolset_label(): string {
		return __( 'UpdraftPlus', 'acrossai-abilities-manager' );
	}

	/**
	 * @return string
	 */
	protected function toolset_description(): string {
		return __( 'Whether this site can be recovered through UpdraftPlus: when a backup last ran and whether it worked, what sets exist and what each contains, whether the archives are reachable over HTTP, and taking, deleting or restoring one. action=discover lists this group; action=info returns schemas; action=execute runs one ability.', 'acrossai-abilities-manager' );
	}
}
