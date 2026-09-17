<?php
/**
 * Feature 126 — registers the ability category used by the backup suite.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Backups
 * @since      0.0.52
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Backups;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Backups\Backup_Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Registers `acrossai-backups` on wp_abilities_api_categories_init.
 *
 * WP core silently drops any ability whose category was not pre-registered
 * (BUG-WP-CORE-ABILITY-CATEGORY-PRE-REGISTRATION), so this must keep running before the Library
 * Processor calls wp_register_ability() at wp_abilities_api_init P5.
 */
final class Category_Registrar {

	/**
	 * @since 0.0.52
	 * @var   self|null
	 */
	protected static $instance = null;

	/**
	 * Private constructor — access via instance().
	 */
	private function __construct() {}

	/**
	 * @since  0.0.52
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @since  0.0.52
	 * @return void
	 */
	public function register(): void {
		if ( ! Backup_Guard::is_available() ) {
			return;
		}

		wp_register_ability_category(
			'acrossai-backups',
			array(
				'label'       => __( 'AcrossAI Abilities Manager — Backups', 'acrossai-abilities-manager' ),
				'description' => __( 'Abilities for backups: what exists, how recent it is, whether it is exposed, and taking or restoring one.', 'acrossai-abilities-manager' ),
			)
		);
	}
}
