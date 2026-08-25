<?php
/**
 * Fired during plugin activation.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes
 * @since      0.0.1
 */

namespace AcrossAI_Abilities_Manager\Includes;

use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Table;
use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Abilities_Access_Control;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Path_Allowlist_Guard;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Secret_Redactor;
use WPBoilerplate\AccessControl\Database\Rule\RuleTable;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      0.0.1
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes
 * @author     AcrossWP <deepak@acrosswp.com>
 */
class AcrossAI_Activator {

	/**
	 * Run activation tasks.
	 *
	 * Creates or upgrades the {prefix}acrossai_abilities
	 * and {prefix}abilities_access_control tables.
	 *
	 * Feature 046: also runs the one-time absorbed-code option-key migration.
	 *
	 * @since  0.0.1
	 * @return void
	 */
	public static function activate(): void {
		( new AcrossAI_Abilities_Table() )->maybe_upgrade();
		( new RuleTable( AcrossAI_Abilities_Access_Control::TABLE_SLUG ) )->maybe_upgrade();
		self::migrate_absorbed_options();
		self::seed_file_manager_settings();
	}

	/**
	 * Feature 092: seed the three file-manager settings options with sensible
	 * defaults on activation. Uses add_option() so an existing value on
	 * re-activation or upgrade is preserved.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private static function seed_file_manager_settings(): void {
		add_option( Path_Allowlist_Guard::OPTION_WRITE, Path_Allowlist_Guard::DEFAULT_WRITE_ALLOWLIST );
		add_option( Path_Allowlist_Guard::OPTION_READ, Path_Allowlist_Guard::DEFAULT_READ_ALLOWLIST );
		add_option( Secret_Redactor::OPTION, Secret_Redactor::default_config() );
	}

	/**
	 * One-time absorbed-code option-key migration (Feature 046).
	 *
	 * Idempotent: repeated activation is a no-op once the legacy keys are gone.
	 * OR-monotonic for the uninstall opt-in: only ever transitions the
	 * manager's existing key false → true, never demotes a manager true.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	private static function migrate_absorbed_options(): void {
		// (a) Copy the extra-MIME-types option under its new manager-branded key.
		// Preserve any manual edits: only copy when the target key is unset.
		$legacy_mimes = get_option( 'acrossai_core_abilities_extra_mimes', null );
		if ( null !== $legacy_mimes ) {
			$existing_mimes = get_option( 'acrossai_abilities_manager_extra_mimes', null );
			if ( null === $existing_mimes ) {
				update_option( 'acrossai_abilities_manager_extra_mimes', $legacy_mimes );
			}
			delete_option( 'acrossai_core_abilities_extra_mimes' );
		}

		// (b) Fold the legacy uninstall opt-in into the manager's existing one.
		// Monotonic OR: only ever flip false → true.
		$legacy_uninstall = get_option( 'acrossai_core_abilities_uninstall_delete_data', null );
		if ( null !== $legacy_uninstall ) {
			if ( ! empty( $legacy_uninstall ) && empty( get_option( 'acrossai_abilities_uninstall_delete_data', 0 ) ) ) {
				update_option( 'acrossai_abilities_uninstall_delete_data', 1 );
			}
			delete_option( 'acrossai_core_abilities_uninstall_delete_data' );
		}
	}
}
