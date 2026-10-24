<?php
/**
 * Behavioral tests for format handling in database row write abilities.
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Database\Delete_Db_Rows;
use AcrossAI_Abilities_Manager\Includes\Abilities\Database\Insert_Db_Row;
use AcrossAI_Abilities_Manager\Includes\Abilities\Database\Update_Db_Rows;
use AcrossAI_Abilities_Manager\Includes\Utilities\Database_Format_Validator;
use PHPUnit\Framework\TestCase;

/**
 * Minimal wpdb stand-in that records write calls.
 */
final class Issue275_Fake_Wpdb {
	public array $insert_calls = array();
	public array $update_calls = array();
	public array $delete_calls = array();
	public int $insert_id = 19304;
	public string $last_error = '';

	public function get_col( string $query ): array {
		return array( 'wp_postmeta' );
	}

	public function insert( string $table, array $data, mixed $format = null ): int {
		$this->insert_calls[] = array(
			'table'  => $table,
			'data'   => $data,
			'format' => $format,
		);

		return 1;
	}

	public function update( string $table, array $data, array $where, mixed $data_format = null, mixed $where_format = null ): int {
		$this->update_calls[] = array(
			'table'        => $table,
			'data'         => $data,
			'where'        => $where,
			'data_format'  => $data_format,
			'where_format' => $where_format,
		);

		return 1;
	}

	public function delete( string $table, array $where, mixed $where_format = null ): int {
		$this->delete_calls[] = array(
			'table'        => $table,
			'where'        => $where,
			'where_format' => $where_format,
		);

		return 1;
	}
}

/**
 * Regression coverage for positional and column-keyed formats.
 */
class Test_Database_Row_Formats extends TestCase {
	private bool $had_wpdb = false;
	private mixed $previous_wpdb = null;
	private Issue275_Fake_Wpdb $wpdb;

	protected function setUp(): void {
		parent::setUp();
		$this->had_wpdb      = array_key_exists( 'wpdb', $GLOBALS );
		$this->previous_wpdb = $GLOBALS['wpdb'] ?? null;
		$this->wpdb          = new Issue275_Fake_Wpdb();
		$GLOBALS['wpdb']     = $this->wpdb;
	}

	protected function tearDown(): void {
		if ( $this->had_wpdb ) {
			$GLOBALS['wpdb'] = $this->previous_wpdb;
		} else {
			unset( $GLOBALS['wpdb'] );
		}

		parent::tearDown();
	}

	public function test_reported_insert_mismatch_is_rejected_before_writing(): void {
		$result = ( new Insert_Db_Row() )->execute(
			array(
				'table'  => 'wp_postmeta',
				'data'   => array(
					'meta_key'   => 'rank_math_title',
					'meta_value' => 'WordPress MCP in 2027: MCP Adapter, Use Cases & Plugins Compared',
					'post_id'    => 96298,
				),
				'format' => array( '%d', '%s', '%s' ),
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( 0, $result['insert_id'] );
		$this->assertSame( array(), $this->wpdb->insert_calls );
	}

	public function test_insert_column_map_is_normalized_to_data_key_order(): void {
		$result = ( new Insert_Db_Row() )->execute(
			array(
				'table'  => 'wp_postmeta',
				'data'   => array(
					'meta_key'   => 'rank_math_title',
					'meta_value' => 'A title',
					'post_id'    => 96298,
				),
				'format' => array(
					'post_id'    => '%d',
					'meta_value' => '%s',
					'meta_key'   => '%s',
				),
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( array( '%s', '%s', '%d' ), $this->wpdb->insert_calls[0]['format'] );
	}

	public function test_insert_column_map_defaults_omitted_columns_to_strings(): void {
		$result = ( new Insert_Db_Row() )->execute(
			array(
				'table'  => 'wp_postmeta',
				'data'   => array(
					'meta_key'   => 'rank_math_title',
					'meta_value' => 'A title',
					'post_id'    => 96298,
				),
				'format' => array( 'post_id' => '%d' ),
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( array( '%s', '%s', '%d' ), $this->wpdb->insert_calls[0]['format'] );
	}

	public function test_valid_positional_insert_formats_remain_supported(): void {
		$result = ( new Insert_Db_Row() )->execute(
			array(
				'table'  => 'wp_postmeta',
				'data'   => array(
					'post_id'  => '96298',
					'meta_key' => 'rank_math_title',
				),
				'format' => array( '%d', '%s' ),
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( array( '%d', '%s' ), $this->wpdb->insert_calls[0]['format'] );
	}

	public function test_update_rejects_mismatched_data_and_where_formats_before_writing(): void {
		$data_result = ( new Update_Db_Rows() )->execute(
			array(
				'table'        => 'wp_postmeta',
				'data'         => array( 'meta_key' => 'rank_math_title' ),
				'data_format'  => array( '%d' ),
				'where'        => array( 'meta_id' => 19303 ),
				'where_format' => array( '%d' ),
			)
		);
		$where_result = ( new Update_Db_Rows() )->execute(
			array(
				'table'        => 'wp_postmeta',
				'data'         => array( 'meta_key' => 'rank_math_title' ),
				'data_format'  => array( '%s' ),
				'where'        => array( 'meta_id' => 'not-a-number' ),
				'where_format' => array( '%d' ),
			)
		);

		$this->assertFalse( $data_result['success'] );
		$this->assertFalse( $where_result['success'] );
		$this->assertSame( array(), $this->wpdb->update_calls );
	}

	public function test_update_column_maps_are_normalized_independently(): void {
		$result = ( new Update_Db_Rows() )->execute(
			array(
				'table'       => 'wp_postmeta',
				'data'        => array(
					'meta_key' => 'rank_math_title',
					'post_id'  => 96298,
				),
				'data_format' => array(
					'post_id'  => '%d',
					'meta_key' => '%s',
				),
				'where'        => array(
					'meta_key' => 'old_title',
					'meta_id'  => 19303,
				),
				'where_format' => array(
					'meta_id'  => '%d',
					'meta_key' => '%s',
				),
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( array( '%s', '%d' ), $this->wpdb->update_calls[0]['data_format'] );
		$this->assertSame( array( '%s', '%d' ), $this->wpdb->update_calls[0]['where_format'] );
	}

	public function test_delete_rejects_mismatched_where_format_before_writing(): void {
		$result = ( new Delete_Db_Rows() )->execute(
			array(
				'table'        => 'wp_postmeta',
				'where'        => array( 'meta_key' => 'rank_math_title' ),
				'where_format' => array( '%d' ),
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( array(), $this->wpdb->delete_calls );
	}

	public function test_formats_reject_unknown_values_and_columns(): void {
		$this->assertFalse( Database_Format_Validator::normalize( array( 'count' => 3 ), array( 'count' => '%q' ) ) );
		$this->assertFalse( Database_Format_Validator::normalize( array( 'id' => 1, 'name' => 'x' ), array( 'missing' => '%d' ) ) );
		$this->assertFalse( Database_Format_Validator::normalize( array( 'id' => 1 ), array( '%d', '%s' ) ) );
		$this->assertSame( array( '%d', '%s' ), Database_Format_Validator::normalize( array( 'id' => 1, 'name' => 'x' ), array( '%d' ) ) );
	}

	public function test_numeric_formats_accept_compatible_values(): void {
		$this->assertSame( array( '%d', '%f' ), Database_Format_Validator::normalize( array( 'id' => '0012', 'ratio' => '1.25' ), array( 'id' => '%d', 'ratio' => '%f' ) ) );
		$this->assertFalse( Database_Format_Validator::normalize( array( 'id' => '12.5' ), array( 'id' => '%d' ) ) );
		$this->assertFalse( Database_Format_Validator::normalize( array( 'ratio' => '1e9999' ), array( 'ratio' => '%f' ) ) );
	}

	public function test_format_schema_advertises_arrays_and_column_maps(): void {
		$schema = Database_Format_Validator::schema( 'format schema' );

		$this->assertSame( array( 'array', 'object' ), array_column( $schema['anyOf'], 'type' ) );
	}
}
