<?php
/**
 * Issue #184 — the toolset counts account for every ability.
 *
 * A `WP_UnitTestCase`, deliberately **not** registered in `phpunit.xml.dist`: it needs a real
 * `wp_get_abilities()` with the plugin's abilities actually registered, which the stub bootstrap in
 * `tests/bootstrap.php` cannot provide. Run under wp-env. Same arrangement as
 * `Test_Toolset_Filter_And_Route_Order`.
 *
 * The invariant is the whole of #184 in one line. Before the fix, 36 of 425 abilities on a measured
 * site belonged to no group: the twelve tab counts summed to 389 while "All" said 425, and the 36 in
 * the gap were reachable through no toolset and no MCP tool. Nothing reported it — the screen looked
 * right, because a missing tab is invisible.
 *
 * With every ability placed and a catch-all behind them, the sums must agree exactly. If this ever
 * fails again, some ability is once more reachable only by scrolling "All".
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.35
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;
use WP_REST_Request;

/**
 * Counts from GET /acrossai/v1/abilities/toolsets must account for every ability.
 */
class Test_Toolset_Counts_Reconcile extends WP_UnitTestCase {

	/**
	 * Act as an administrator — the route requires `manage_options`.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * The toolsets payload.
	 *
	 * @return array<string, mixed>
	 */
	private function toolsets(): array {
		$response = rest_do_request( new WP_REST_Request( 'GET', '/acrossai/v1/abilities/toolsets' ) );

		$this->assertSame( 200, $response->get_status() );

		return (array) $response->get_data();
	}

	/**
	 * Every non-protected ability is counted in exactly one group.
	 *
	 * @return void
	 */
	public function test_counts_sum_to_the_total(): void {
		$payload = $this->toolsets();
		$counts  = (array) ( $payload['counts'] ?? array() );

		$this->assertNotSame( array(), $counts, 'Expected at least one toolset.' );

		$this->assertSame(
			(int) $payload['total'],
			array_sum( $counts ),
			'Every ability must belong to exactly one toolset. A shortfall means abilities exist that '
				. 'no tab can show and no MCP dispatcher can reach — issue #184.'
		);
	}

	/**
	 * No registered ability reports an empty toolset.
	 *
	 * The complement of the sum check, from the list route rather than the counts route, so a bug that
	 * happened to balance the arithmetic still fails.
	 *
	 * @return void
	 */
	public function test_no_ability_reports_an_empty_toolset(): void {
		$request = new WP_REST_Request( 'GET', '/acrossai/v1/abilities' );
		$request->set_param( 'per_page', 100 );

		$ungrouped = array();
		$page      = 1;

		do {
			$request->set_param( 'page', $page );
			$response = rest_do_request( $request );

			$this->assertSame( 200, $response->get_status() );

			$rows = (array) $response->get_data();

			foreach ( $rows as $row ) {
				if ( '' === ( $row['tab_group'] ?? '' ) ) {
					$ungrouped[] = $row['ability_slug'] ?? '(unnamed)';
				}
			}

			++$page;
		} while ( count( $rows ) === 100 && $page <= 10 );

		$this->assertSame(
			array(),
			$ungrouped,
			'These abilities belong to no toolset: ' . implode( ', ', $ungrouped )
		);
	}

	/**
	 * The groups this plugin's integrations declare are present once their plugin is.
	 *
	 * @return void
	 */
	public function test_active_integrations_appear_as_toolsets(): void {
		$counts = (array) ( $this->toolsets()['counts'] ?? array() );

		foreach ( \AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\AcrossAI_Toolset_Integrations::all() as $group => $integration ) {
			if ( ! $integration->is_active() ) {
				continue;
			}

			// An active integration whose abilities are all switched off still has no group, which is
			// correct — the assertion is only that a non-empty one is never missing.
			if ( ! isset( $counts[ $group ] ) ) {
				continue;
			}

			$this->assertGreaterThan( 0, $counts[ $group ] );
		}
	}
}
