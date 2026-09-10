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
 * Run_Db_Select_Query ability class (absorbed).
 */
class Run_Db_Select_Query extends Ability_Definition {

	/**
	 * Full ability spec for wp_register_ability().
	 *
	 * @return array
	 */
	protected function ability(): array {
		return array(
			'name' => 'database/run-db-select-query',
			'args' => array(
				'label'               => __( 'Run SELECT Query', 'acrossai-abilities-manager' ),
				'description'         => __( 'Executes a read-only SQL query (SELECT, SHOW, DESCRIBE, EXPLAIN). Pass the query in "sql" (or the alias "query"). Write statements are rejected. Results are capped by the limit parameter.', 'acrossai-abilities-manager' ),
				'category'            => 'acrossai-database',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'sql'   => array(
							'type'        => 'string',
							'description' => __( 'SQL query to execute. Must start with SELECT, SHOW, DESCRIBE, DESC, or EXPLAIN.', 'acrossai-abilities-manager' ),
						),
						'query' => array(
							'type'        => 'string',
							'description' => __( 'Alias for "sql". If both are provided, "sql" wins.', 'acrossai-abilities-manager' ),
						),
						'limit' => array(
							'type'        => 'integer',
							'default'     => 1000,
							'minimum'     => 1,
							'maximum'     => 10000,
							'description' => __( 'Maximum rows to return (1–10000, default 1000). Appended as LIMIT if the query does not already contain one.', 'acrossai-abilities-manager' ),
						),
					),
					'anyOf'                => array(
						array( 'required' => array( 'sql' ) ),
						array( 'required' => array( 'query' ) ),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'properties'           => array(
						'success'   => array( 'type' => 'boolean' ),
						'rows'      => array( 'type' => 'array' ),
						'row_count' => array( 'type' => 'integer' ),
						'truncated' => array( 'type' => 'boolean' ),
						'message'   => array( 'type' => 'string' ),
					),
					'required'             => array( 'success', 'rows', 'row_count', 'truncated' ),
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
						'readonly'    => true,
						'destructive' => false,
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

		$sql   = isset( $input['sql'] ) && '' !== trim( $input['sql'] )
			? trim( $input['sql'] )
			: trim( (string) ( $input['query'] ?? '' ) );
		$limit = isset( $input['limit'] ) ? min( (int) $input['limit'], 10000 ) : 1000;

		if ( '' === $sql ) {
			return array(
				'success'   => false,
				'rows'      => array(),
				'row_count' => 0,
				'truncated' => false,
				'message'   => __( 'sql (or its alias "query") is required.', 'acrossai-abilities-manager' ),
			);
		}

		// Verb guard: strip SQL comments then check the first keyword.
		$stripped = preg_replace( '/\/\*.*?\*\/|--[^\n]*|#[^\n]*/s', '', $sql );
		preg_match( '/^\s*(\w+)/i', $stripped, $m );
		$first_keyword = strtoupper( $m[1] ?? '' );
		$allowed_verbs = array( 'SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN' );

		if ( ! in_array( $first_keyword, $allowed_verbs, true ) ) {
			return array(
				'success'   => false,
				'rows'      => array(),
				'row_count' => 0,
				'truncated' => false,
				'message'   => sprintf(
					/* translators: %s: comma-separated list of allowed SQL verbs */
					__( 'Only %s queries are permitted.', 'acrossai-abilities-manager' ),
					implode( ', ', $allowed_verbs )
				),
			);
		}

		// Append LIMIT if absent and requested.
		if ( $limit > 0 && ! preg_match( '/\bLIMIT\b/i', $sql ) ) {
			$sql = rtrim( $sql, '; ' ) . ' LIMIT ' . $limit;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		// phpcs:enable

		if ( null === $rows ) {
			return array(
				'success'   => false,
				'rows'      => array(),
				'row_count' => 0,
				'truncated' => false,
				'message'   => $wpdb->last_error ?: __( 'Query returned null.', 'acrossai-abilities-manager' ),
			);
		}

		$row_count = count( $rows );

		return array(
			'success'   => true,
			'rows'      => $rows,
			'row_count' => $row_count,
			'truncated' => $row_count === $limit,
		);
	}
}
