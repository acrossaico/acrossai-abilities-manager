<?php
/**
 * Security tests: column names cannot break out of $wpdb's backtick quoting.
 *
 * `$wpdb->insert()`, `->update()` and `->delete()` interpolate `array_keys()` into the query inside
 * backticks WITHOUT escaping them. These abilities take column names straight from the caller, so a
 * key containing a backtick is a SQL injection. Proven before the fix, against a scratch table:
 *
 *     delete  where = { "owner` = 1 OR `owner` > 0 -- ": 1 }
 *     SQL     DELETE FROM `t` WHERE `owner` = 1 OR `owner` > 0 -- ` = 1
 *     result  a delete of owner=1 removed every row
 *
 * #275's format validation does not touch this — it checks values, not key names — so #276 fixing
 * #275 left it open. These tests pin the column-name allowlist that closes it.
 *
 * Driven through each ability's execute() with the same fake $wpdb the #275 suite uses, asserting
 * NO write was recorded. If a crafted name ever reaches $wpdb again, the `*_calls` array is
 * non-empty and the test fails.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.42
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Database\Delete_Db_Rows;
use AcrossAI_Abilities_Manager\Includes\Abilities\Database\Insert_Db_Row;
use AcrossAI_Abilities_Manager\Includes\Abilities\Database\Update_Db_Rows;
use AcrossAI_Abilities_Manager\Includes\Utilities\Database_Format_Validator;
use PHPUnit\Framework\TestCase;

/**
 * Records write calls and reports the table as existing, like the #275 suite's stand-in.
 */
final class Column_Safety_Fake_Wpdb {

	/** @var array<int, mixed> */
	public array $insert_calls = array();
	/** @var array<int, mixed> */
	public array $update_calls = array();
	/** @var array<int, mixed> */
	public array $delete_calls = array();
	public int $insert_id = 1;
	public string $last_error = '';

	/**
	 * @param  string $query Query.
	 * @return array<int, string>
	 */
	public function get_col( string $query ): array {
		return array( 'wp_postmeta' );
	}

	/**
	 * @param  string $table  Table.
	 * @param  array  $data   Data.
	 * @param  mixed  $format Format.
	 * @return int
	 */
	public function insert( string $table, array $data, mixed $format = null ): int {
		$this->insert_calls[] = compact( 'table', 'data', 'format' );
		return 1;
	}

	/**
	 * @param  string $table        Table.
	 * @param  array  $data         Data.
	 * @param  array  $where        Where.
	 * @param  mixed  $data_format  Data format.
	 * @param  mixed  $where_format Where format.
	 * @return int
	 */
	public function update( string $table, array $data, array $where, mixed $data_format = null, mixed $where_format = null ): int {
		$this->update_calls[] = compact( 'table', 'data', 'where', 'data_format', 'where_format' );
		return 1;
	}

	/**
	 * @param  string $table        Table.
	 * @param  array  $where        Where.
	 * @param  mixed  $where_format Where format.
	 * @return int
	 */
	public function delete( string $table, array $where, mixed $where_format = null ): int {
		$this->delete_calls[] = compact( 'table', 'where', 'where_format' );
		return 1;
	}
}

/**
 * Column-name injection cannot reach $wpdb.
 */
class Test_Database_Column_Name_Safety extends TestCase {

	private bool $had_wpdb       = false;
	private mixed $previous_wpdb = null;
	private Column_Safety_Fake_Wpdb $wpdb;

	/**
	 * The exact break-out a live exploit used: closes the backtick, adds a disjunction, comments
	 * out the rest.
	 *
	 * @var string
	 */
	private const INJECTION = 'owner` = 1 OR `owner` > 0 -- ';

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->had_wpdb      = array_key_exists( 'wpdb', $GLOBALS );
		$this->previous_wpdb = $GLOBALS['wpdb'] ?? null;
		$this->wpdb          = new Column_Safety_Fake_Wpdb();
		$GLOBALS['wpdb']     = $this->wpdb;
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		if ( $this->had_wpdb ) {
			$GLOBALS['wpdb'] = $this->previous_wpdb;
		} else {
			unset( $GLOBALS['wpdb'] );
		}
		parent::tearDown();
	}

	/**
	 * A backtick in a column name is reported as unsafe.
	 *
	 * @return void
	 */
	public function test_a_backtick_column_name_is_unsafe(): void {
		$this->assertSame(
			self::INJECTION,
			Database_Format_Validator::unsafe_column( array( self::INJECTION => 1 ) )
		);
	}

	/**
	 * Spaces, dashes, dots and quotes are all rejected — anything outside [A-Za-z0-9_].
	 *
	 * @return void
	 */
	public function test_everything_outside_the_allowlist_is_unsafe(): void {
		foreach ( array( 'has space', 'has-dash', 'table.col', "quote'd", 'back`tick', 'semi;colon', '' ) as $bad ) {
			$this->assertNotNull(
				Database_Format_Validator::unsafe_column( array( $bad => 1 ) ),
				'"' . $bad . '" must be rejected.'
			);
		}
	}

	/**
	 * Ordinary WordPress column names pass untouched.
	 *
	 * @return void
	 */
	public function test_legitimate_column_names_pass(): void {
		$this->assertNull(
			Database_Format_Validator::unsafe_column(
				array(
					'meta_key'   => 'x',
					'meta_value' => 'y',
					'post_id'    => 1,
					'ID'         => 2,
					'user_login' => 'z',
				)
			)
		);
	}

	/**
	 * delete-db-rows: the live exploit is refused with no delete issued.
	 *
	 * @return void
	 */
	public function test_delete_refuses_an_injected_where_column(): void {
		$result = ( new Delete_Db_Rows() )->execute(
			array(
				'table' => 'wp_postmeta',
				'where' => array( self::INJECTION => 1 ),
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( array(), $this->wpdb->delete_calls, 'No delete may be issued with a crafted column name.' );
		$this->assertStringContainsString( 'not allowed', (string) $result['message'] );
	}

	/**
	 * insert-db-row: a crafted data column is refused with no insert issued.
	 *
	 * @return void
	 */
	public function test_insert_refuses_an_injected_data_column(): void {
		$result = ( new Insert_Db_Row() )->execute(
			array(
				'table' => 'wp_postmeta',
				'data'  => array( 'meta_value`, `post_id' => 'x' ),
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( array(), $this->wpdb->insert_calls );
	}

	/**
	 * update-db-rows: a crafted column in EITHER data or where is refused.
	 *
	 * @return void
	 */
	public function test_update_refuses_an_injected_column_in_data_or_where(): void {
		$in_where = ( new Update_Db_Rows() )->execute(
			array(
				'table' => 'wp_postmeta',
				'data'  => array( 'meta_value' => 'x' ),
				'where' => array( self::INJECTION => 1 ),
			)
		);
		$this->assertFalse( $in_where['success'] );
		$this->assertSame( array(), $this->wpdb->update_calls, 'Injected where column must not reach $wpdb.' );

		$in_data = ( new Update_Db_Rows() )->execute(
			array(
				'table' => 'wp_postmeta',
				'data'  => array( 'meta_value`, `post_id' => 'x' ),
				'where' => array( 'meta_id' => 1 ),
			)
		);
		$this->assertFalse( $in_data['success'] );
		$this->assertSame( array(), $this->wpdb->update_calls, 'Injected data column must not reach $wpdb.' );
	}

	/**
	 * A legitimate delete still issues exactly one call — the guard does not break the happy path.
	 *
	 * @return void
	 */
	public function test_a_legitimate_write_still_goes_through(): void {
		$result = ( new Delete_Db_Rows() )->execute(
			array(
				'table'        => 'wp_postmeta',
				'where'        => array( 'post_id' => 1 ),
				'where_format' => array( '%d' ),
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertCount( 1, $this->wpdb->delete_calls );
		$this->assertSame( array( 'post_id' => 1 ), $this->wpdb->delete_calls[0]['where'] );
	}
}
