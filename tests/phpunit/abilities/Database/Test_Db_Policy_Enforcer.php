<?php
/**
 * Feature 134 — the policy enforcement wrapper.
 *
 * The wrapper is what makes the policy apply to all eighteen database abilities without any of them
 * knowing about it, so the assertions here are about the decisions it makes rather than about any
 * one ability.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.45
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities\Database;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Db_Policy_Enforcer;
use WP_UnitTestCase;

class Test_Db_Policy_Enforcer extends WP_UnitTestCase {

	/**
	 * Whether this environment has a real `$wpdb` able to resolve logical table keys.
	 *
	 * The suite's bootstrap is a stub WordPress, so `$wpdb->posts` is absent and a logical key
	 * cannot be resolved to a physical name. Those assertions are skipped with a reason rather than
	 * rewritten to assert the stub's behaviour, which would be testing the stub.
	 *
	 * @return bool
	 */
	private function can_resolve(): bool {
		global $wpdb;

		return isset( $wpdb ) && is_object( $wpdb ) && is_string( $wpdb->posts ?? null ) && '' !== ( $wpdb->posts ?? '' );
	}

	/**
	 * Extract table names from an input array through the private SQL scanner.
	 *
	 * @param  array<string, mixed> $input Input.
	 * @return string[]
	 */
	private function sql_tables( array $input ): array {
		$ref = new \ReflectionMethod( Db_Policy_Enforcer::class, 'tables_in_sql' );
		$ref->setAccessible( true );

		return $ref->invokeArgs( null, array( $input ) );
	}

	/**
	 * Extract table names from a call's parameters.
	 *
	 * @param  array<string, mixed> $input Input.
	 * @return string[]
	 */
	private function param_tables( array $input ): array {
		$ref = new \ReflectionMethod( Db_Policy_Enforcer::class, 'tables_in' );
		$ref->setAccessible( true );

		return $ref->invokeArgs( null, array( $input ) );
	}

	/**
	 * THE regression this file exists for.
	 *
	 * Before the SQL scanner, `SELECT user_login, user_pass FROM wp_users` returned rows even though
	 * `users` is permanently denied — because the table was in the statement, not in a parameter.
	 * The "always denied, not configurable" promise was false for the one ability most likely to be
	 * pointed at a sensitive table.
	 */
	public function test_a_table_named_only_inside_sql_is_still_found(): void {
		$this->assertContains( 'wp_users', $this->sql_tables( array( 'sql' => 'SELECT user_pass FROM wp_users' ) ) );
	}

	public function test_tables_are_found_through_every_clause_that_names_one(): void {
		$cases = array(
			'SELECT * FROM wp_posts'                                   => 'wp_posts',
			'SELECT * FROM `wp_usermeta`'                              => 'wp_usermeta',
			'SELECT 1 FROM wp_a JOIN wp_users u ON 1=1'                => 'wp_users',
			'UPDATE wp_options SET a=1'                                => 'wp_options',
			'INSERT INTO wp_terms (name) VALUES ("x")'                 => 'wp_terms',
		);

		foreach ( $cases as $sql => $expected ) {
			$this->assertContains( $expected, $this->sql_tables( array( 'sql' => $sql ) ), "Missed {$expected} in: {$sql}" );
		}
	}

	/**
	 * Comments are stripped first, so a denied table cannot be hidden behind one.
	 */
	public function test_a_table_hidden_behind_a_comment_is_still_found(): void {
		$this->assertContains(
			'wp_users',
			$this->sql_tables( array( 'sql' => 'SELECT a /*x*/ FROM /*y*/ wp_users' ) )
		);
	}

	/**
	 * …and a table named only inside a comment is NOT reported, so a commented-out query does not
	 * produce a refusal that makes no sense to the caller.
	 */
	public function test_a_table_only_inside_a_comment_is_not_reported(): void {
		$this->assertSame( array(), $this->sql_tables( array( 'sql' => 'SELECT 1 -- FROM wp_users' ) ) );
		$this->assertSame( array(), $this->sql_tables( array( 'sql' => 'SELECT 1 /* FROM wp_users */' ) ) );
	}

	public function test_a_statement_naming_no_table_yields_nothing(): void {
		$this->assertSame( array(), $this->sql_tables( array( 'sql' => 'SELECT 1' ) ) );
		$this->assertSame( array(), $this->sql_tables( array() ) );
		$this->assertSame( array(), $this->sql_tables( array( 'sql' => array( 'not', 'a', 'string' ) ) ) );
	}

	public function test_table_parameters_are_collected_in_both_shapes(): void {
		// Shape collection works without a database; only the logical->physical step needs one.
		$this->assertContains( 'custom_thing', $this->param_tables( array( 'table' => 'custom_thing' ) ) );
		$this->assertContains( 'custom_thing', $this->param_tables( array( 'tables' => array( 'custom_thing' ) ) ) );
		$this->assertSame( array(), $this->param_tables( array( 'table' => '  ' ) ) );
		$this->assertSame( array(), $this->param_tables( array( 'table' => array( array( 'nested' ) ) ) ) );

		if ( ! $this->can_resolve() ) {
			$this->markTestSkipped( 'Needs a real $wpdb to resolve a logical table key to its physical name.' );
		}

		$this->assertContains( 'wp_posts', $this->param_tables( array( 'table' => 'posts' ) ) );
		$this->assertContains( 'wp_posts', $this->param_tables( array( 'tables' => array( 'posts' ) ) ) );
	}

	/**
	 * A logical key and the physical name it resolves to must reach the same decision, or an
	 * operator who denied `wp_posts` would find `posts` still worked.
	 */
	public function test_a_logical_key_resolves_to_the_same_physical_name(): void {
		if ( ! $this->can_resolve() ) {
			$this->markTestSkipped( 'Needs a real $wpdb to resolve a logical table key to its physical name.' );
		}

		global $wpdb;

		$this->assertSame(
			$this->param_tables( array( 'table' => $wpdb->posts ) ),
			$this->param_tables( array( 'table' => 'posts' ) )
		);
	}

	/**
	 * Which abilities write is DECLARED, never derived from `annotations.readonly`, because an
	 * ability mislabelled readonly would then be allowed to write in read-only mode.
	 */
	public function test_the_write_list_is_explicit_and_covers_the_known_writers(): void {
		foreach ( array(
			'database/insert-db-row',
			'database/update-db-rows',
			'database/delete-db-rows',
			'database/search-replace',
			'database/optimize-db-tables',
			'database/convert-core-tables-to-innodb',
			'database/cleanup-expired-transients',
			'database/set-option-autoload',
		) as $writer ) {
			$this->assertContains( $writer, Db_Policy_Enforcer::WRITES, "{$writer} must be declared a write." );
		}
	}

	/**
	 * Every entry must be a `database/` name — a typo would silently classify a real write as a read.
	 */
	public function test_every_declared_write_is_a_database_ability_name(): void {
		foreach ( Db_Policy_Enforcer::WRITES as $name ) {
			$this->assertStringStartsWith( 'database/', $name );
		}

		$this->assertSame( array_unique( Db_Policy_Enforcer::WRITES ), Db_Policy_Enforcer::WRITES );
	}

	/**
	 * The wrapper only touches the database category — it must not wrap, say, a file ability.
	 */
	public function test_only_database_abilities_are_wrapped(): void {
		$callback = static fn( $i = array() ) => array( 'success' => true );

		$untouched = Db_Policy_Enforcer::wrap(
			array( 'category' => 'acrossai-file-manager', 'execute_callback' => $callback ),
			'file-manager/read-file'
		);
		$this->assertSame( $callback, $untouched['execute_callback'] );

		$wrapped = Db_Policy_Enforcer::wrap(
			array( 'category' => Db_Policy_Enforcer::CATEGORY, 'execute_callback' => $callback ),
			'database/list-db-tables'
		);
		$this->assertNotSame( $callback, $wrapped['execute_callback'] );
	}

	public function test_malformed_args_are_returned_untouched(): void {
		$this->assertSame( 'not an array', Db_Policy_Enforcer::wrap( 'not an array', 'database/x' ) );

		$no_callback = array( 'category' => Db_Policy_Enforcer::CATEGORY );
		$this->assertSame( $no_callback, Db_Policy_Enforcer::wrap( $no_callback, 'database/x' ) );
	}
}
