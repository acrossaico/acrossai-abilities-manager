<?php
/**
 * Feature 126 - name a backup so it can be recognised later.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Backups
 * @since      0.0.52
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Backups;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Backups\All_In_One_Provider;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Backups\Provider_Registry;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A dated filename says nothing about why a backup was taken.
 *
 * @since 0.0.52
 */
final class Set_Backup_Label extends Base_Backup_Ability {

	/**
	 * @since  0.0.52
	 * @return string
	 */
	protected function slug(): string {
		return 'backups/set-backup-label';
	}

	/**
	 * @since  0.0.52
	 * @return string
	 */
	protected function ability_label(): string {
		return __( 'Set Backup Label', 'acrossai-abilities-manager' );
	}

	/**
	 * @since  0.0.52
	 * @return string
	 */
	protected function ability_description(): string {
		return __( 'Attach a label to a backup set, so a list of timestamps becomes a list of reasons - "before the price change", "before the theme swap". Pass an empty label to remove one. Only backup plugins that store labels support this; backups/get-status reports which.', 'acrossai-abilities-manager' );
	}

	/**
	 * @since  0.0.52
	 * @return string
	 */
	protected function sub_group(): string {
		return 'inventory';
	}

	/**
	 * @since  0.0.52
	 * @return array<string, mixed>
	 */
	protected function input_properties(): array {
		return array(
			'id'       => array(
				'type'        => 'string',
				'description' => __( 'Backup identifier from backups/list-backups.', 'acrossai-abilities-manager' ),
			),
			'label'    => array(
				'type'        => 'string',
				'maxLength'   => 200,
				'description' => __( 'The label. Pass an empty string to remove it.', 'acrossai-abilities-manager' ),
			),
			'provider' => array(
				'type'        => 'string',
				'description' => __( 'Which backup plugin to use. Optional when only one is active; required when more than one is.', 'acrossai-abilities-manager' ),
			),
		);
	}

	/**
	 * @since  0.0.52
	 * @return array<int, string>
	 */
	protected function required_input(): array {
		return array('id', 'label');
	}

	/**
	 * @since  0.0.52
	 * @return array<string, mixed>
	 */
	protected function output_properties(): array {
		return array(
			'provider'  => array( 'type' => 'string' ),
			'backup_id' => array( 'type' => 'string' ),
			'label'     => array( 'type' => 'string' ),
		);
	}

	/**
	 * @since  0.0.52
	 * @return array<string, bool>
	 */
	protected function annotations(): array {
		return array(
			'readonly'    => false,
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
		$provider = Provider_Registry::resolve_for( isset( $input['provider'] ) ? (string) $input['provider'] : '', 'label' );

		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		if ( ! is_a( $provider, All_In_One_Provider::class, true ) ) {
			return new WP_Error(
				'unsupported_by_provider',
				sprintf(
					/* translators: %s: provider label. */
					__( '%s reports that it stores labels but exposes no way to set one, which is a bug in this plugin rather than in yours.', 'acrossai-abilities-manager' ),
					$provider::label()
				)
			);
		}

		return All_In_One_Provider::set_label( (string) $input['id'], (string) $input['label'] );
	}
}
