<?php
/**
 * Feature 102 — persistence behaviour of the gate migration.
 *
 * Integration tests: these need a database and the BerlinDB override table, so like
 * AbilitiesReadControllerTest this file is a WP_UnitTestCase and is deliberately NOT listed in
 * phpunit.xml.dist. It runs under wp-env.
 *
 * The decision rules are covered separately and without WordPress in
 * Test_Library_Gate_Migration_Rules.php. What is left here is everything that can only go wrong
 * against real storage: not clobbering an operator's explicit setting, idempotency, and the atomic
 * claim that stops concurrent requests both translating.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.34
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Modules\Abilities;

use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Library_Gate_Migration;
use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\Database\AcrossAI_Abilities_Query;
use WP_UnitTestCase;

/**
 * Covers the migration's writes.
 */
class Test_Library_Gate_Migration_Persistence extends WP_UnitTestCase {

	/**
	 * Clear the flag and the source option between tests.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		delete_option( AcrossAI_Library_Gate_Migration::DONE_OPTION );
		delete_site_option( AcrossAI_Library_Gate_Migration::SOURCE_OPTION );
		delete_site_option( AcrossAI_Library_Gate_Migration::INTEGRATIONS_OPTION );
	}

	/**
	 * T040 — the claim is exclusive: a second run does no work.
	 *
	 * @return void
	 */
	public function test_second_run_is_a_no_op(): void {
		update_site_option(
			AcrossAI_Library_Gate_Migration::SOURCE_OPTION,
			array( 'acrossai-cache' => array( 'enabled' => false ) )
		);

		AcrossAI_Library_Gate_Migration::instance()->maybe_migrate();
		$after_first = $this->override_count();

		// Re-seed the source so a second translation would have something to do if it ran.
		update_site_option(
			AcrossAI_Library_Gate_Migration::SOURCE_OPTION,
			array( 'acrossai-content' => array( 'enabled' => false ) )
		);
		AcrossAI_Library_Gate_Migration::instance()->maybe_migrate();

		$this->assertSame(
			$after_first,
			$this->override_count(),
			'The flag is claimed before the work, so a second call must write nothing.'
		);
	}

	/**
	 * T037 — the flag is present after a run, so nothing re-runs on later requests.
	 *
	 * @return void
	 */
	public function test_flag_is_set_after_running(): void {
		AcrossAI_Library_Gate_Migration::instance()->maybe_migrate();

		$this->assertSame( '1', get_option( AcrossAI_Library_Gate_Migration::DONE_OPTION ) );
	}

	/**
	 * T035 — an explicit access setting is never replaced.
	 *
	 * The guard is on `site_allowed` specifically. A row that exists only to override a label must
	 * still receive the block, or the translation silently under-blocks.
	 *
	 * @return void
	 */
	public function test_explicit_site_allowed_is_preserved_but_other_overrides_are_not_a_shield(): void {
		$query = AcrossAI_Abilities_Query::instance();

		// Operator has explicitly allowed this one — the translation must not touch it.
		$query->save_override( 'cache/flush-transients', array( 'site_allowed' => true ) );

		// This one carries only a label override; site_allowed is still inherit.
		$query->save_override( 'cache/get-transient', array( 'label' => 'Renamed' ) );

		update_site_option(
			AcrossAI_Library_Gate_Migration::SOURCE_OPTION,
			array( 'acrossai-cache' => array( 'enabled' => false ) )
		);

		AcrossAI_Library_Gate_Migration::instance()->maybe_migrate();

		$kept = $query->get_override_by_slug( 'cache/flush-transients' );
		$this->assertNotNull( $kept );
		$this->assertTrue( (bool) $kept->site_allowed, 'An explicit Force Allow must survive.' );

		$labelled = $query->get_override_by_slug( 'cache/get-transient' );
		$this->assertNotNull( $labelled );
		$this->assertFalse(
			(bool) $labelled->site_allowed,
			'A pre-existing label override must not shield an ability from being blocked.'
		);
		$this->assertSame( 'Renamed', $labelled->label, 'The unrelated override must be preserved.' );
	}

	/**
	 * T028 — an enabled integration opt-in survives, and is never demoted.
	 *
	 * @return void
	 */
	public function test_integration_opt_in_is_or_monotonic(): void {
		update_site_option( AcrossAI_Library_Gate_Migration::INTEGRATIONS_OPTION, array( 'acf' => true ) );
		update_site_option(
			AcrossAI_Library_Gate_Migration::SOURCE_OPTION,
			array( 'acf' => array( 'enabled' => false ) )
		);

		AcrossAI_Library_Gate_Migration::instance()->maybe_migrate();

		$stored = get_site_option( AcrossAI_Library_Gate_Migration::INTEGRATIONS_OPTION, array() );

		$this->assertTrue(
			! empty( $stored['acf'] ),
			'A truthy opt-in must never be demoted, even when the retiring config disagrees.'
		);
	}

	/**
	 * T029 — the source option is retired on single-site only.
	 *
	 * @return void
	 */
	/**
	 * A written override must not claim to be a user-created ability.
	 *
	 * `source` is `NOT NULL DEFAULT 'db'` and save_override() leaves it to the caller. When the
	 * migration omitted it, all 16 rows it wrote on the development site came back from the
	 * `source=db` listing as custom abilities — with null label, status and callback. The block
	 * itself worked, which is why every other test here passed.
	 *
	 * @return void
	 */
	public function test_written_overrides_are_not_stamped_as_db_abilities(): void {
		update_site_option(
			AcrossAI_Library_Gate_Migration::SOURCE_OPTION,
			array( 'acrossai-cache' => array( 'enabled' => false ) )
		);

		AcrossAI_Library_Gate_Migration::instance()->maybe_migrate();

		$query = AcrossAI_Abilities_Query::instance();

		// The column value itself — the thing that was wrong. Asserting only "absent from the
		// source=db listing" would also pass if no row had been written at all.
		$written = 0;

		foreach ( (array) $query->query( array( 'number' => 500 ) ) as $row ) {
			if ( 'cache' !== substr( (string) $row->ability_slug, 0, 5 ) ) {
				continue;
			}

			++$written;

			$this->assertSame(
				'plugin',
				$row->source,
				'A migration-written override must be stamped source=plugin. The column is '
					. "NOT NULL DEFAULT 'db' and save_override() leaves it to the caller, so omitting "
					. 'it silently marks the row as a user-created ability.'
			);
		}

		$this->assertGreaterThan(
			0,
			$written,
			'The migration must actually have written the cache overrides, or this proves nothing.'
		);

		// And the consequence: none of them show up as custom abilities.
		foreach ( (array) $query->query( array( 'source' => 'db', 'number' => 500 ) ) as $row ) {
			$this->assertNotSame(
				'cache',
				substr( (string) $row->ability_slug, 0, 5 ),
				'A migration-written override must not appear in the source=db listing.'
			);
		}
	}

	public function test_source_option_removal_is_single_site_only(): void {
		update_site_option(
			AcrossAI_Library_Gate_Migration::SOURCE_OPTION,
			array( 'acrossai-cache' => array( 'enabled' => false ) )
		);

		AcrossAI_Library_Gate_Migration::instance()->maybe_migrate();

		$still_there = get_site_option( AcrossAI_Library_Gate_Migration::SOURCE_OPTION, null );

		if ( is_multisite() ) {
			$this->assertNotNull(
				$still_there,
				'On multisite the option must be retained: translations are per-site and nothing tracks completion.'
			);

			return;
		}

		$this->assertNull( $still_there, 'On single-site the option is safe to retire.' );
	}

	/**
	 * Count override rows.
	 *
	 * @return int
	 */
	private function override_count(): int {
		global $wpdb;

		$table = $wpdb->prefix . 'acrossai_abilities';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- test-only count against an internally built table name.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
	}
}
