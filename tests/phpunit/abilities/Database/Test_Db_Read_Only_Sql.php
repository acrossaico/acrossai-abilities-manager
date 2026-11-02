<?php
/**
 * Issue #293 — the read-only SQL checks, as pure functions.
 *
 * Deliberately requires no database. The two defects in #293 lived inside a method that needs
 * $wpdb, so #298's has_db() skip meant nothing covering them ever ran. These execute in the stub
 * harness and in CI.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.44
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Db_Read_Only_Sql;

require_once dirname( __DIR__, 4 ) . '/includes/Abilities/Utilities/Db_Read_Only_Sql.php';

final class Test_Db_Read_Only_Sql extends TestCase {

	/* ------------------------------------------------------------------ */
	/* Verb guard                                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * @return array<string,array{0:string,1:bool}>
	 */
	public static function verb_provider(): array {
		return array(
			'select'                 => array( 'SELECT 1', true ),
			'lowercase select'       => array( 'select 1', true ),
			'show'                   => array( 'SHOW TABLES', true ),
			'describe'               => array( 'DESCRIBE wp_posts', true ),
			'explain'                => array( 'EXPLAIN SELECT 1', true ),
			'comment-prefixed'       => array( '/*c*/ SELECT 1', true ),
			'line-comment-prefixed'  => array( "-- c\nSELECT 1", true ),
			'insert'                 => array( 'INSERT INTO wp_posts VALUES (1)', false ),
			'delete'                 => array( 'DELETE FROM wp_posts', false ),
			'update'                 => array( 'UPDATE wp_posts SET ID = 1', false ),
			'drop'                   => array( 'DROP TABLE wp_posts', false ),
			// MySQL 8 CTE-write. #293 recorded this as already handled; pinned so it stays handled.
			'cte wrapping a delete'  => array( 'WITH x AS (SELECT 1) DELETE FROM wp_posts', false ),
			'empty'                  => array( '', false ),
		);
	}

	/**
	 * @dataProvider verb_provider
	 * @param string $sql     Statement.
	 * @param bool   $allowed Expected.
	 */
	public function test_verb_guard( string $sql, bool $allowed ): void {
		$this->assertSame( $allowed, Db_Read_Only_Sql::verb_allowed( $sql ), $sql );
	}

	/* ------------------------------------------------------------------ */
	/* Defect 1: INTO OUTFILE passes the verb guard                       */
	/* ------------------------------------------------------------------ */

	/**
	 * @return array<string,array{0:string,1:?string}>
	 */
	public static function file_clause_provider(): array {
		return array(
			'outfile'            => array( 'SELECT * FROM wp_users INTO OUTFILE "/tmp/x.txt"', 'OUTFILE' ),
			'dumpfile lowercase' => array( 'select user_pass from wp_users into dumpfile "/tmp/y"', 'DUMPFILE' ),
			'extra whitespace'   => array( "SELECT 1 INTO\n\tOUTFILE '/tmp/z'", 'OUTFILE' ),
			'hidden by comment'  => array( "SELECT 1 INTO/*x*/OUTFILE '/tmp/p'", 'OUTFILE' ),
			'plain select'       => array( 'SELECT * FROM wp_posts', null ),
			// "into" as part of a longer word must not trip it.
			'into in a column'   => array( 'SELECT intoxication FROM wp_posts', null ),
		);
	}

	/**
	 * @dataProvider file_clause_provider
	 * @param string      $sql      Statement.
	 * @param string|null $expected Expected clause.
	 */
	public function test_file_write_clause_is_detected( string $sql, ?string $expected ): void {
		$this->assertSame( $expected, Db_Read_Only_Sql::file_write_clause( $sql ), $sql );
	}

	public function test_a_file_writing_select_still_passes_the_verb_guard(): void {
		// The point of #293: the verb guard is not the boundary. Both assertions must hold, or the
		// explicit refusal is not actually needed.
		$sql = 'SELECT * FROM wp_users INTO OUTFILE "/tmp/x.txt"';

		$this->assertTrue( Db_Read_Only_Sql::verb_allowed( $sql ) );
		$this->assertSame( 'OUTFILE', Db_Read_Only_Sql::file_write_clause( $sql ) );
	}

	/* ------------------------------------------------------------------ */
	/* Defect 2: LIMIT anywhere suppressed the cap                        */
	/* ------------------------------------------------------------------ */

	/**
	 * @return array<string,array{0:string,1:bool}>
	 */
	public static function limit_provider(): array {
		return array(
			'trailing limit'            => array( 'SELECT 1 FROM wp_posts LIMIT 5', true ),
			'trailing with semicolon'   => array( 'SELECT 1 FROM wp_posts LIMIT 5;', true ),
			'offset form'              => array( 'SELECT 1 FROM wp_posts LIMIT 10, 5', true ),
			'no limit'                 => array( 'SELECT 1 FROM wp_posts', false ),
			// Both measured in #293 as suppressing the cap under the old /\bLIMIT\b/ test.
			'limit inside a subquery'  => array( 'SELECT * FROM (SELECT 1 LIMIT 1) t', false ),
			'limit in a string literal' => array( 'SELECT "LIMIT" AS a FROM wp_posts', false ),
		);
	}

	/**
	 * @dataProvider limit_provider
	 * @param string $sql      Statement.
	 * @param bool   $trailing Expected.
	 */
	public function test_trailing_limit_detection( string $sql, bool $trailing ): void {
		$this->assertSame( $trailing, Db_Read_Only_Sql::has_trailing_limit( $sql ), $sql );
	}

	public function test_cap_is_appended_where_the_old_check_skipped_it(): void {
		$this->assertStringEndsWith(
			'LIMIT 2',
			Db_Read_Only_Sql::with_limit( 'SELECT * FROM (SELECT 1 LIMIT 1) t', 2 ),
			'A LIMIT inside a subquery must no longer suppress the outer cap.'
		);
		$this->assertStringEndsWith(
			'LIMIT 2',
			Db_Read_Only_Sql::with_limit( 'SELECT "LIMIT" AS a FROM wp_posts', 2 ),
			'A LIMIT in a string literal must no longer suppress the outer cap.'
		);
	}

	public function test_an_existing_trailing_limit_is_not_doubled(): void {
		$sql = 'SELECT 1 FROM wp_posts LIMIT 5';

		$this->assertSame( $sql, Db_Read_Only_Sql::with_limit( $sql, 2 ) );
	}

	public function test_limit_is_not_appended_to_statements_that_reject_it(): void {
		foreach ( array( 'SHOW TABLES', 'DESCRIBE wp_posts', 'EXPLAIN SELECT 1' ) as $sql ) {
			$this->assertSame( $sql, Db_Read_Only_Sql::with_limit( $sql, 10 ), $sql );
		}
	}

	public function test_a_zero_or_negative_cap_appends_nothing(): void {
		$sql = 'SELECT 1 FROM wp_posts';

		$this->assertSame( $sql, Db_Read_Only_Sql::with_limit( $sql, 0 ) );
		$this->assertSame( $sql, Db_Read_Only_Sql::with_limit( $sql, -5 ) );
	}

	public function test_trailing_semicolon_does_not_produce_a_broken_statement(): void {
		$this->assertSame(
			'SELECT 1 FROM wp_posts LIMIT 3',
			Db_Read_Only_Sql::with_limit( 'SELECT 1 FROM wp_posts;  ', 3 )
		);
	}
}
