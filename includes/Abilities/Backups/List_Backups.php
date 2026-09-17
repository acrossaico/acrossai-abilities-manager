<?php
/**
 * Feature 126 - enumerate the backup sets that exist.
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
 * What could actually be restored from, newest first.
 *
 * @since 0.0.52
 */
final class List_Backups extends Base_Backup_Ability {

	/**
	 * @since  0.0.52
	 * @return string
	 */
	protected function slug(): string {
		return 'backups/list-backups';
	}

	/**
	 * @since  0.0.52
	 * @return string
	 */
	protected function ability_label(): string {
		return __( 'List Backups', 'acrossai-abilities-manager' );
	}

	/**
	 * @since  0.0.52
	 * @return string
	 */
	protected function ability_description(): string {
		return __( 'List the backup sets held on this site, newest first, with when each was taken, how old it is, what it contains, its size on disk and any label. Covers every active backup plugin unless one is named. Sets held only on remote storage are not listed - this reads what is on this server.', 'acrossai-abilities-manager' );
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
			'provider' => array(
				'type'        => 'string',
				'description' => __( 'Which backup plugin to use. Optional when only one is active; required when more than one is.', 'acrossai-abilities-manager' ),
			),
			'limit'    => array(
				'type'    => 'integer',
				'minimum' => 1,
				'maximum' => 100,
				'default' => 20,
			),
			'offset'   => array(
				'type'    => 'integer',
				'minimum' => 0,
				'default' => 0,
			),
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
			'results' => array(
				'type'  => 'array',
				'items' => array( 'type' => 'object', 'additionalProperties' => true ),
			),
			'count'   => array( 'type' => 'integer' ),
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
		$limit  = isset( $input['limit'] ) ? (int) $input['limit'] : 20;
		$offset = isset( $input['offset'] ) ? (int) $input['offset'] : 0;
		$wanted = isset( $input['provider'] ) ? (string) $input['provider'] : '';

		if ( '' !== $wanted ) {
			$provider = Provider_Registry::resolve( $wanted );

			if ( is_wp_error( $provider ) ) {
				return $provider;
			}

			$providers = array( $provider );
		} else {
			$providers = Provider_Registry::active();
		}

		$results = array();
		$count   = 0;

		foreach ( $providers as $provider ) {
			$listing = $provider::list_backups( $limit, $offset );
			$count  += isset( $listing['count'] ) ? (int) $listing['count'] : 0;
			$results[] = $listing;
		}

		return array(
			'results' => $results,
			'count'   => $count,
		);
	}
}
