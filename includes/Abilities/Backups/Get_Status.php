<?php
/**
 * Feature 126 - the headline question: can this site be recovered.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Backups
 * @since      0.0.52
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Backups;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Backups\Provider_Registry;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Whether this site is backed up, how recently, and whether it worked.
 *
 * @since 0.0.52
 */
final class Get_Status extends Base_Backup_Ability {

	/**
	 * @since  0.0.52
	 * @return string
	 */
	protected function slug(): string {
		return 'backups/get-status';
	}

	/**
	 * @since  0.0.52
	 * @return string
	 */
	protected function ability_label(): string {
		return __( 'Get Backup Status', 'acrossai-abilities-manager' );
	}

	/**
	 * @since  0.0.52
	 * @return string
	 */
	protected function ability_description(): string {
		return __( 'Report whether this site can be recovered. For every active backup plugin: when it last ran, whether that run succeeded, how many backup sets exist, whether one is running now, what is scheduled next, how long sets are kept, and which remote destinations are configured by name. Run this before any bulk or destructive change - it is the question of whether the change can be undone. Remote storage is named and never its credentials.', 'acrossai-abilities-manager' );
	}

	/**
	 * @since  0.0.52
	 * @return string
	 */
	protected function sub_group(): string {
		return 'state';
	}

	/**
	 * @since  0.0.52
	 * @return array<string, mixed>
	 */
	protected function input_properties(): array {
		return array(

		);
	}

	/**
	 * @since  0.0.52
	 * @return array<int, string>
	 */
	protected function required_input(): array {
		return array();
	}

	/**
	 * @since  0.0.52
	 * @return array<string, mixed>
	 */
	protected function output_properties(): array {
		return array(
			'providers'        => array(
				'type'  => 'array',
				'items' => array( 'type' => 'object', 'additionalProperties' => true ),
			),
			'any_backup'       => array(
				'type'        => 'boolean',
				'description' => __( 'True when at least one backup set exists anywhere.', 'acrossai-abilities-manager' ),
			),
			'newest_backup_time' => array( 'type' => array( 'integer', 'null' ) ),
			'newest_backup_age'  => array( 'type' => array( 'string', 'null' ) ),
			'inactive_providers' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'note'             => array( 'type' => 'string' ),
		);
	}

	/**
	 * @since  0.0.52
	 * @return array<string, bool>
	 */
	protected function annotations(): array {
		return array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		);
	}

	/**
	 * @since  0.0.52
	 * @param  array<string, mixed> $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	protected function run( array $input ) {
		unset( $input );

		$providers = array();
		$newest    = null;
		$any       = false;

		foreach ( Provider_Registry::active() as $provider ) {
			$status = $provider::status();

			$status['supports'] = array(
				'start'    => $provider::supports( 'start' ),
				'progress' => $provider::supports( 'progress' ),
				'delete'   => $provider::supports( 'delete' ),
				'restore'  => $provider::supports( 'restore' ),
				'label'    => $provider::supports( 'label' ),
				'schedule' => $provider::supports( 'schedule' ),
			);

			if ( ! empty( $status['backup_count'] ) ) {
				$any = true;
			}

			$last = isset( $status['last_backup_time'] ) ? (int) $status['last_backup_time'] : 0;

			if ( $last > 0 && ( null === $newest || $last > $newest ) ) {
				$newest = $last;
			}

			$providers[] = $status;
		}

		$inactive = array();

		foreach ( Provider_Registry::all() as $provider ) {
			if ( ! $provider::is_active() ) {
				$inactive[] = $provider::label();
			}
		}

		return array(
			'providers'          => $providers,
			'any_backup'         => $any,
			'newest_backup_time' => $newest,
			'newest_backup_age'  => null === $newest ? null : human_time_diff( $newest, time() ) . __( ' ago', 'acrossai-abilities-manager' ),
			'inactive_providers' => $inactive,
			'note'               => $any
				? ''
				: __( 'A backup plugin is active but has never produced a backup set, so there is nothing to restore from. Take one with backups/start-backup before making changes that would be hard to undo.', 'acrossai-abilities-manager' ),
		);
	}
}
