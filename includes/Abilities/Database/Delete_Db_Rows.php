<?php
/**
 * Absorbed ability class scaffolded from acrossai-core-abilities (Feature 046).
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Database
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Database;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;

defined( 'ABSPATH' ) || exit;

/**
 * Delete_Db_Rows ability class (absorbed).
 */
class Delete_Db_Rows extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'database/delete-db-rows',
			'args' => array(
				'label'               => __( 'Delete Rows', 'acrossai-abilities-manager' ),
				'description'         => __( 'Deletes rows matching the where clause using $wpdb->delete() (values are auto-escaped). Requires a non-empty where to prevent accidental full-table deletion.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-database',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'table'        => array(
							'type'        => 'string',
							'description' => __( 'Target table name.', 'acrossai-abilities-manager' ),
						),
						'where'        => array(
							'type'        => 'object',
							'description' => __( 'Column → value conditions (AND-joined). Must be non-empty.', 'acrossai-abilities-manager' ),
						),
						'where_format' => array(
							'type'        => 'array',
							'items'       => array(
								'type' => 'string',
								'enum' => array( '%s', '%d', '%f' ),
							),
							'description' => __( 'Optional format per where column.', 'acrossai-abilities-manager' ),
						),
					),
					'required'             => array( 'table', 'where' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'       => array( 'type' => 'boolean' ),
						'rows_affected' => array( 'type' => 'integer' ),
						'message'       => array( 'type' => 'string' ),
					),
					'required'             => array( 'success', 'rows_affected' ),
					'additionalProperties' => false,
				),
				'meta'                => array(
					'acrossai'     => array(
						'tab_group'       => 'database',
						'sub_group'       => 'queries',
						'sub_group_label' => __( 'Queries', 'acrossai-abilities-manager' ),
					),
					'show_in_rest' => true,
					'mcp'          => array(
						'public' => false,
						'type'   => 'tool',
					),
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			),
		);
	}

	/**
	 * Execute the ability.
	 *
	 * @param array $input Ability input payload.
	 * @return array
	 */
	public function execute( array $input = array() ): array {
		global $wpdb;

		$table        = sanitize_text_field( $input['table'] ?? '' );
		$where        = $input['where'] ?? array();
		$where_format = $input['where_format'] ?? null;

		if ( '' === $table ) {
			return array(
				'success'       => false,
				'rows_affected' => 0,
				'message'       => __( 'table is required.', 'acrossai-abilities-manager' ),
			);
		}

		if ( empty( $where ) || ! is_array( $where ) ) {
			return array(
				'success'       => false,
				'rows_affected' => 0,
				'message'       => __( 'where must be a non-empty object to prevent full-table deletion.', 'acrossai-abilities-manager' ),
			);
		}

		// Validate table exists.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$tables = $wpdb->get_col( 'SHOW TABLES' );
		// phpcs:enable
		if ( ! in_array( $table, (array) $tables, true ) ) {
			return array(
				'success'       => false,
				'rows_affected' => 0,
				'message'       => __( 'Table not found in the database.', 'acrossai-abilities-manager' ),
			);
		}

		$result = $wpdb->delete( $table, $where, $where_format );

		if ( false === $result ) {
			return array(
				'success'       => false,
				'rows_affected' => 0,
				'message'       => $wpdb->last_error ?: __( 'Delete failed.', 'acrossai-abilities-manager' ),
			);
		}

		return array(
			'success'       => true,
			'rows_affected' => (int) $result,
		);
	}
}
