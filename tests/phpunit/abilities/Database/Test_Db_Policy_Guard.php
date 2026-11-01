<?php
/**
 * Feature 134 — the database access policy.
 *
 * The assertions that matter are the ones about which way an error falls: an unrecognised mode, a
 * table that merely looks like a user table, a denylist entry that is really a pattern.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.45
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities\Database;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Db_Policy_Guard;
use WP_UnitTestCase;

class Test_Db_Policy_Guard extends WP_UnitTestCase {

	/**
	 * Whether this environment can actually store and read back a policy.
	 *
	 * The suite's bootstrap is a stub WordPress with no `$wpdb`, and this class deliberately reads
	 * through `$wpdb` rather than `get_option()`. So the persistence path cannot run here, and the
	 * honest thing is to skip those assertions with a reason rather than let them pass against a
	 * fallback and look like coverage.
	 *
	 * @return bool
	 */
	private function has_db(): bool {
		global $wpdb;

		return isset( $wpdb ) && is_object( $wpdb ) && is_string( $wpdb->options ?? null ) && '' !== ( $wpdb->options ?? '' );
	}

	/**
	 * Run a candidate policy through the sanitiser without touching storage.
	 *
	 * @param  array<string, mixed> $policy Candidate.
	 * @return array<string, mixed>
	 */
	private function sanitize( array $policy ): array {
		$ref = new \ReflectionMethod( Db_Policy_Guard::class, 'sanitize' );
		$ref->setAccessible( true );

		return $ref->invokeArgs( null, array( $policy ) );
	}

	protected function setUp(): void {
		parent::setUp();
		Db_Policy_Guard::flush();
	}

	protected function tearDown(): void {
		Db_Policy_Guard::flush();
		parent::tearDown();
	}

	public function test_defaults_are_unrestricted_so_an_upgrade_changes_nothing(): void {
		$this->assertSame( Db_Policy_Guard::MODE_UNRESTRICTED, Db_Policy_Guard::defaults()['mode'] );
	}

	public function test_read_only_refuses_writes_and_still_allows_reads(): void {
		if ( ! $this->has_db() ) {
			$this->markTestSkipped( 'Needs a real $wpdb: this class reads its policy through it by design.' );
		}

		Db_Policy_Guard::save( array( 'mode' => Db_Policy_Guard::MODE_READ_ONLY ) );

		$this->assertTrue( Db_Policy_Guard::assert_enabled() );

		$write = Db_Policy_Guard::assert_writable();
		$this->assertInstanceOf( \WP_Error::class, $write );
		$this->assertSame( 'db_read_only', $write->get_error_code() );
	}

	public function test_off_refuses_everything(): void {
		if ( ! $this->has_db() ) {
			$this->markTestSkipped( 'Needs a real $wpdb: this class reads its policy through it by design.' );
		}

		Db_Policy_Guard::save( array( 'mode' => Db_Policy_Guard::MODE_OFF ) );

		$enabled = Db_Policy_Guard::assert_enabled();
		$this->assertInstanceOf( \WP_Error::class, $enabled );
		$this->assertSame( 'db_access_off', $enabled->get_error_code() );
		$this->assertInstanceOf( \WP_Error::class, Db_Policy_Guard::assert_writable() );
	}

	/**
	 * An unrecognised mode must fail SAFE, not fall back to the default.
	 *
	 * The default is `unrestricted`, so treating a typo as "use the default" would widen access on a
	 * malformed policy — the opposite of what a policy error should do.
	 */
	public function test_an_unrecognised_mode_falls_to_read_only_not_to_the_default(): void {
		$saved = $this->sanitize( array( 'mode' => 'definitely-not-a-mode' ) );

		$this->assertSame( Db_Policy_Guard::MODE_READ_ONLY, $saved['mode'] );
		$this->assertNotSame( Db_Policy_Guard::MODE_UNRESTRICTED, $saved['mode'], 'A malformed mode must not widen access to the default.' );
	}

	/**
	 * THE regression this file exists for.
	 *
	 * An earlier version matched `_users` and `_usermeta` as suffixes and permanently locked
	 * `wp_mainwp_users` and `wp_acrossai_mcp_connector_approved_users` — a third-party table and one
	 * of our own — with no way to unlock either, because "always denied" is not configurable.
	 * Over-denying is only safe when it is reversible.
	 */
	public function test_only_the_real_user_tables_are_permanently_denied(): void {
		// always_denied() falls back to prefix+name when $wpdb cannot answer, precisely so this
		// protection never evaporates — so it is testable in the stub environment too.
		$locked_tables = Db_Policy_Guard::always_denied();

		$this->assertNotEmpty( $locked_tables, 'The permanent denial must never resolve to an empty list.' );
		$this->assertCount( 2, $locked_tables );

		foreach ( $locked_tables as $locked ) {
			$result = Db_Policy_Guard::assert_table( $locked );
			$this->assertInstanceOf( \WP_Error::class, $result, "{$locked} must be denied." );
			$this->assertSame( 'db_table_denied', $result->get_error_code() );
		}

		foreach ( array( 'wp_mainwp_users', 'wp_acrossai_mcp_connector_approved_users', 'wp_my_usermeta_cache' ) as $allowed ) {
			$this->assertTrue(
				Db_Policy_Guard::assert_table( $allowed ),
				"{$allowed} merely resembles a user table and must not be permanently locked."
			);
		}
	}

	public function test_the_permanent_denial_cannot_be_configured_away(): void {
		if ( ! $this->has_db() ) {
			$this->markTestSkipped( 'Needs a real $wpdb: this class reads its policy through it by design.' );
		}

		// Not even by an empty operator denylist.
		Db_Policy_Guard::save( array( 'denied_tables' => array() ) );

		$this->assertInstanceOf( \WP_Error::class, Db_Policy_Guard::assert_table( Db_Policy_Guard::always_denied()[0] ) );
	}

	public function test_an_operator_denylist_entry_matches_by_suffix(): void {
		if ( ! $this->has_db() ) {
			$this->markTestSkipped( 'Needs a real $wpdb: this class reads its policy through it by design.' );
		}

		Db_Policy_Guard::save( array( 'denied_tables' => array( 'my_logs' ) ) );

		$this->assertInstanceOf( \WP_Error::class, Db_Policy_Guard::assert_table( 'wp_my_logs' ) );
		$this->assertInstanceOf( \WP_Error::class, Db_Policy_Guard::assert_table( 'wp_7_my_logs' ) );
		$this->assertTrue( Db_Policy_Guard::assert_table( 'wp_my_logs_archive' ) );
	}

	/**
	 * A denylist entry is matched as a bare suffix, so anything that is really a pattern or an
	 * injection attempt is dropped rather than escaped — it would match nothing and read as
	 * protection that is not there.
	 */
	public function test_denylist_entries_that_are_not_table_names_are_dropped(): void {
		$saved = $this->sanitize(
			array( 'denied_tables' => array( 'good_table', '`evil`', 'pat%ern', 'semi;colon', '', 'UPPER_Case' ) )
		);

		$this->assertSame( array( 'good_table', 'upper_case' ), $saved['denied_tables'] );
	}

	public function test_the_row_cap_is_a_ceiling_not_a_default(): void {
		if ( ! $this->has_db() ) {
			$this->markTestSkipped( 'Needs a real $wpdb: this class reads its policy through it by design.' );
		}

		Db_Policy_Guard::save( array( 'max_rows' => 100 ) );

		$this->assertSame( 100, Db_Policy_Guard::row_cap() );
		$this->assertSame( 10, Db_Policy_Guard::row_cap( 10 ) );
		$this->assertSame( 100, Db_Policy_Guard::row_cap( 99999 ) );
		$this->assertSame( 100, Db_Policy_Guard::row_cap( 0 ) );
		$this->assertSame( 100, Db_Policy_Guard::row_cap( -5 ) );
	}

	public function test_numeric_caps_are_clamped_to_sane_bounds(): void {
		$saved = $this->sanitize( array( 'max_rows' => 999999, 'max_seconds' => 9999 ) );

		$this->assertSame( 10000, $saved['max_rows'] );
		$this->assertSame( 300, $saved['max_seconds'] );

		$saved = $this->sanitize( array( 'max_rows' => -1, 'max_seconds' => -1 ) );

		$this->assertSame( 1, $saved['max_rows'] );
		$this->assertSame( 0, $saved['max_seconds'] );
	}

	/**
	 * The policy is read straight from the options table, so an option filter cannot make the
	 * enforced policy differ from the stored one. This is the whole reason the class does not use
	 * `get_option()`, borrowed from Novamira's read mode.
	 */
	public function test_an_option_filter_cannot_change_what_is_enforced(): void {
		if ( ! $this->has_db() ) {
			$this->markTestSkipped( 'Needs a real $wpdb: the whole point of this test is the $wpdb read path.' );
		}

		Db_Policy_Guard::save( array( 'mode' => Db_Policy_Guard::MODE_READ_ONLY ) );

		$widen = static function () {
			return wp_json_encode( array( 'mode' => Db_Policy_Guard::MODE_UNRESTRICTED ) );
		};

		add_filter( 'pre_option_' . Db_Policy_Guard::OPTION, $widen );
		Db_Policy_Guard::flush();

		$this->assertSame(
			Db_Policy_Guard::MODE_READ_ONLY,
			Db_Policy_Guard::mode(),
			'A pre_option filter widened the enforced policy.'
		);

		remove_filter( 'pre_option_' . Db_Policy_Guard::OPTION, $widen );
		Db_Policy_Guard::flush();
	}

	public function test_an_unparseable_stored_policy_falls_back_to_defaults(): void {
		if ( ! $this->has_db() ) {
			$this->markTestSkipped( 'Needs a real $wpdb: this class reads its policy through it by design.' );
		}

		update_option( Db_Policy_Guard::OPTION, 'not json at all', false );
		Db_Policy_Guard::flush();

		$this->assertSame( Db_Policy_Guard::MODE_UNRESTRICTED, Db_Policy_Guard::mode() );
		$this->assertSame( 1000, Db_Policy_Guard::row_cap() );
	}

	public function test_unknown_keys_cannot_survive_into_the_stored_policy(): void {
		$saved = $this->sanitize( array( 'mode' => Db_Policy_Guard::MODE_OFF, 'evil' => 'yes' ) );

		$this->assertArrayNotHasKey( 'evil', $saved );
		$this->assertSame( array_keys( Db_Policy_Guard::defaults() ), array_keys( $saved ) );
	}

	public function test_an_empty_table_name_is_refused(): void {
		$result = Db_Policy_Guard::assert_table( '  ' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'db_table_missing', $result->get_error_code() );
	}
}
