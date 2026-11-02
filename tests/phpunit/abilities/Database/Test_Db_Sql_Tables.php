<?php
/**
 * Feature 134 — resolving which tables a raw statement touches.
 *
 * The scan half is pure string work and is asserted here. The EXPLAIN half needs a real MySQL —
 * it is what makes a CTE, a derived table and an aliased join resolve — and is skipped with a
 * reason rather than faked, because a fake would be asserting the fake.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.45
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities\Database;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Db_Sql_Tables;
use WP_UnitTestCase;

class Test_Db_Sql_Tables extends WP_UnitTestCase {

	/**
	 * @return bool
	 */
	private function has_db(): bool {
		global $wpdb;

		return isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_results' ) && defined( 'DB_NAME' );
	}

	/**
	 * @param  string $sql Statement.
	 * @return string[]
	 */
	private function scan( string $sql ): array {
		$ref = new \ReflectionMethod( Db_Sql_Tables::class, 'from_scan' );
		$ref->setAccessible( true );

		return $ref->invoke( null, $sql );
	}

	public function test_the_scan_finds_tables_in_every_clause_that_names_one(): void {
		$this->assertContains( 'wp_posts', $this->scan( 'SELECT * FROM wp_posts' ) );
		$this->assertContains( 'wp_usermeta', $this->scan( 'SELECT 1 FROM `wp_usermeta`' ) );
		$this->assertContains( 'wp_users', $this->scan( 'SELECT 1 FROM a JOIN wp_users ON 1=1' ) );
		$this->assertContains( 'wp_options', $this->scan( 'UPDATE wp_options SET a=1' ) );
		$this->assertContains( 'wp_terms', $this->scan( 'INSERT INTO wp_terms (n) VALUES ("x")' ) );
	}

	public function test_the_scan_sees_through_a_comment(): void {
		$this->assertContains( 'wp_users', $this->scan( 'SELECT a /*x*/ FROM /*y*/ wp_users' ) );
	}

	public function test_the_scan_ignores_a_table_named_only_inside_a_comment(): void {
		$this->assertSame( array(), $this->scan( 'SELECT 1 -- FROM wp_users' ) );
		$this->assertSame( array(), $this->scan( 'SELECT 1 /* FROM wp_users */' ) );
	}

	public function test_a_statement_naming_no_table_yields_nothing(): void {
		$this->assertSame( array(), Db_Sql_Tables::referenced( 'SELECT 1' ) );
		$this->assertSame( array(), Db_Sql_Tables::referenced( '' ) );
		$this->assertSame( array(), Db_Sql_Tables::referenced( '   ' ) );
	}

	/**
	 * THE point of using MySQL rather than a regex.
	 *
	 * Each of these hides the real table from a FROM/JOIN scan — an alias, a CTE name, a derived
	 * table, a subquery several levels down. MySQL's rewritten statement names the real one, and
	 * before this existed `SELECT user_login, user_pass FROM wp_users` ran despite `users` being
	 * permanently denied.
	 */
	public function test_mysql_resolves_what_a_scan_cannot(): void {
		if ( ! $this->has_db() ) {
			$this->markTestSkipped( 'Needs a real MySQL: resolution is done by EXPLAIN, not by this plugin.' );
		}

		global $wpdb;

		$cases = array(
			'aliased join'     => "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->users} u ON u.ID = p.post_author",
			'cte'              => "WITH c AS (SELECT user_login FROM {$wpdb->users}) SELECT * FROM c",
			'derived table'    => "SELECT * FROM (SELECT user_login FROM {$wpdb->users}) t",
			'nested subquery'  => "SELECT 1 FROM {$wpdb->options} WHERE option_id IN (SELECT user_id FROM {$wpdb->usermeta})",
		);

		foreach ( $cases as $label => $sql ) {
			$found = Db_Sql_Tables::referenced( $sql );

			$this->assertContains(
				'cte' === $label || 'derived table' === $label || 'aliased join' === $label ? $wpdb->users : $wpdb->usermeta,
				$found,
				"{$label}: the real table was not resolved."
			);
		}
	}

	/**
	 * An alias must not be reported as a table. MySQL qualifies column references the same way it
	 * qualifies tables, so without the lookahead every alias in a join came back as one.
	 */
	public function test_an_alias_is_not_reported_as_a_table(): void {
		if ( ! $this->has_db() ) {
			$this->markTestSkipped( 'Needs a real MySQL.' );
		}

		global $wpdb;

		$found = Db_Sql_Tables::referenced( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID" );

		$this->assertNotContains( 'p', $found );
		$this->assertNotContains( 'm', $found );
		$this->assertContains( $wpdb->posts, $found );
	}

	/**
	 * EXPLAIN plans; it must never execute. A statement that would change data must leave it alone.
	 */
	public function test_resolving_a_statement_does_not_execute_it(): void {
		if ( ! $this->has_db() ) {
			$this->markTestSkipped( 'Needs a real MySQL.' );
		}

		global $wpdb;

		$marker = 'aai-probe-' . wp_rand( 100000, 999999 );
		update_option( $marker, 'intact', false );

		Db_Sql_Tables::referenced( "UPDATE {$wpdb->options} SET option_value = 'CHANGED' WHERE option_name = '{$marker}'" );

		$this->assertSame( 'intact', get_option( $marker ) );

		delete_option( $marker );
	}

	/**
	 * A statement MySQL refuses to plan must not throw or leave an error behind for the next query
	 * to trip over — the scan still answers.
	 */
	public function test_an_unplannable_statement_degrades_to_the_scan(): void {
		if ( ! $this->has_db() ) {
			$this->markTestSkipped( 'Needs a real MySQL.' );
		}

		global $wpdb;

		$found = Db_Sql_Tables::referenced( "SHOW COLUMNS FROM {$wpdb->users}" );

		$this->assertContains( $wpdb->users, $found, 'SHOW cannot be EXPLAINed; the scan must still find the table.' );
		$this->assertSame( '', (string) $wpdb->last_error, 'A failed EXPLAIN must not leave last_error set.' );
	}
}
