<?php
/**
 * Feature 126 - remove a backup set, and with it that recovery point.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Backups
 * @since      0.0.34
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Backups;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Backups\Provider_Registry;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Deleting a backup removes the ability to go back to that moment.
 *
 * @since 0.0.34
 */
final class Delete_Backup extends Base_Backup_Ability {

	/**
	 * @since  0.0.34
	 * @return string
	 */
	protected function slug(): string {
		return 'backups/delete-backup';
	}

	/**
	 * @since  0.0.34
	 * @return string
	 */
	protected function ability_label(): string {
		return __( 'Delete Backup', 'acrossai-abilities-manager' );
	}

	/**
	 * @since  0.0.34
	 * @return string
	 */
	protected function ability_description(): string {
		return __( 'Delete one backup set and its archive files from this server, freeing the space. This removes a recovery point: whatever state the site was in when that backup was taken can no longer be returned to. Requires confirmation. Copies held on remote storage are NOT removed - delete those in the storage provider itself.', 'acrossai-abilities-manager' );
	}

	/**
	 * @since  0.0.34
	 * @return string
	 */
	protected function sub_group(): string {
		return 'inventory';
	}

	/**
	 * @since  0.0.34
	 * @return bool
	 */
	protected function requires_confirmation(): bool {
		return true;
	}

	/**
	 * @since  0.0.34
	 * @return string
	 */
	protected function confirmation_message(): string {
		return __( 'Deleting this backup removes a recovery point - the state of the site at that moment can no longer be restored. Pass confirm: true to proceed.', 'acrossai-abilities-manager' );
	}

	/**
	 * @since  0.0.34
	 * @return array<string, mixed>
	 */
	protected function input_properties(): array {
		return array(
			'id'       => array(
				'type'        => 'string',
				'description' => __( 'Backup identifier from backups/list-backups.', 'acrossai-abilities-manager' ),
			),
			'provider' => array(
				'type'        => 'string',
				'description' => __( 'Which backup plugin to use. Optional when only one is active; required when more than one is.', 'acrossai-abilities-manager' ),
			),
		);
	}

	/**
	 * @since  0.0.34
	 * @return array<int, string>
	 */
	protected function required_input(): array {
		return array('id');
	}

	/**
	 * @since  0.0.34
	 * @return array<string, mixed>
	 */
	protected function output_properties(): array {
		return array(
			'provider'      => array( 'type' => 'string' ),
			'backup_id'     => array( 'type' => 'string' ),
			'files_removed' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'bytes_freed'   => array( 'type' => 'integer' ),
			'remaining'     => array( 'type' => 'integer' ),
			'note'          => array( 'type' => 'string' ),
		);
	}

	/**
	 * @since  0.0.34
	 * @return array<string, bool>
	 */
	protected function annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => true,
			'idempotent'  => false,
		);
	}

	/**
	 * @since  0.0.34
	 * @param  array<string, mixed> $input Input.
	 * @return array<string, mixed>|WP_Error
	 */
	protected function run( array $input ) {
		$provider = Provider_Registry::resolve_for( isset( $input['provider'] ) ? (string) $input['provider'] : '', 'delete' );

		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		$result = $provider::delete_backup( (string) $input['id'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Read the count back rather than assuming the delete landed, and say plainly when it was
		// the last one: a site with no backups left is a different situation from one with nine.
		$remaining = $provider::status();
		$left      = isset( $remaining['backup_count'] ) ? (int) $remaining['backup_count'] : 0;

		$result['remaining'] = $left;

		if ( 0 === $left ) {
			$result['note'] = trim(
				( isset( $result['note'] ) ? (string) $result['note'] . ' ' : '' )
				. __( 'That was the last backup set this plugin held. There is now nothing to restore this site from.', 'acrossai-abilities-manager' )
			);
		}

		return $result;
	}
}
