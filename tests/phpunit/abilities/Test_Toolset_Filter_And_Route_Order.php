<?php
/**
 * Feature 102 — toolset filter, status filter, and REST route ordering.
 *
 * Integration tests. Like AbilitiesReadControllerTest, this file is a WP_UnitTestCase and is NOT
 * listed in phpunit.xml.dist — it needs a booted WordPress and runs under wp-env, not CI.
 *
 * ## Why the route-order test asserts against rest_get_server(), not this controller's source
 *
 * The first implementation registered '/abilities/toolsets' immediately above this controller's own
 * '/abilities/(?P<slug>[^/]+)' route and looked correct. It was not: AcrossAI_Abilities_Write_Controller
 * registers the same wildcard and runs FIRST in the orchestrator, so the literal landed at route index
 * 4 with the wildcard already at index 2 and was unreachable — WP_REST_Server::dispatch() walks routes
 * in registration order and takes the first regex match.
 *
 * A test asserting registration order within one controller would have passed while the route 404'd.
 * That is why these assertions go through the real server (BUG-REST-ROUTE-ORDER-LITERAL-BEFORE-WILDCARD).
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Covers the Feature 102 additions to the acrossai/v1 abilities surface.
 */
class Test_Toolset_Filter_And_Route_Order extends WP_UnitTestCase {

	/**
	 * Administrator user id.
	 *
	 * @var int
	 */
	protected $admin_id;

	/**
	 * Boot the REST server with an administrator in context.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
		do_action( 'rest_api_init' );
	}

	/**
	 * Ability routes in this namespace, in registration order.
	 *
	 * @return array<int, string>
	 */
	private function ability_routes(): array {
		$all = array_keys( rest_get_server()->get_routes() );

		return array_values(
			array_filter(
				$all,
				static function ( $route ) {
					return 0 === strpos( $route, '/acrossai/v1/abilities' );
				}
			)
		);
	}

	/**
	 * T012 — the literal toolsets route must precede the slug wildcard.
	 *
	 * @return void
	 */
	public function test_toolsets_route_registers_before_the_slug_wildcard(): void {
		$routes    = $this->ability_routes();
		$literal   = array_search( '/acrossai/v1/abilities/toolsets', $routes, true );
		$wildcard  = null;

		foreach ( $routes as $i => $route ) {
			if ( false !== strpos( $route, '(?P<slug>' ) ) {
				$wildcard = $i;
				break;
			}
		}

		$this->assertNotFalse( $literal, '/abilities/toolsets must be registered.' );
		$this->assertNotNull( $wildcard, 'The slug wildcard route must exist for this test to mean anything.' );
		$this->assertLessThan(
			$wildcard,
			$literal,
			'/abilities/toolsets must register before the slug wildcard or dispatch() never reaches it.'
		);
	}

	/**
	 * T012 — and it must actually dispatch, rather than 404 as an ability named "toolsets".
	 *
	 * @return void
	 */
	public function test_toolsets_route_dispatches(): void {
		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/acrossai/v1/abilities/toolsets' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * T010 — tab_group returns exactly the group's members.
	 *
	 * @return void
	 */
	public function test_tab_group_filter_returns_group_members_only(): void {
		$expected = \AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Group::member_names( 'cache' );

		$request = new WP_REST_Request( 'GET', '/acrossai/v1/abilities' );
		$request->set_param( 'tab_group', 'cache' );
		$request->set_param( 'per_page', 100 );
		$data = rest_get_server()->dispatch( $request )->get_data();

		$returned = wp_list_pluck( $data, 'ability_slug' );
		sort( $returned );
		sort( $expected );

		$this->assertSame( $expected, $returned );
	}

	/**
	 * T010 — an unknown toolset is an empty collection, not an error.
	 *
	 * @return void
	 */
	public function test_unknown_tab_group_returns_empty_not_error(): void {
		$request = new WP_REST_Request( 'GET', '/acrossai/v1/abilities' );
		$request->set_param( 'tab_group', 'no-such-group' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
		$this->assertSame( '0', $response->get_headers()['X-WP-Total'] );
	}

	/**
	 * T010 — the toolset dispatcher abilities never appear inside their own toolset.
	 *
	 * AcrossAI_Ability_Group excludes protected slugs; resolving tab_group anywhere else would
	 * lose that (DEC-PROTECTED-SLUGS-PATTERN).
	 *
	 * @return void
	 */
	public function test_dispatchers_are_absent_from_their_own_toolset(): void {
		$request = new WP_REST_Request( 'GET', '/acrossai/v1/abilities' );
		$request->set_param( 'tab_group', 'cache' );
		$request->set_param( 'per_page', 100 );
		$slugs = wp_list_pluck( rest_get_server()->dispatch( $request )->get_data(), 'ability_slug' );

		$this->assertNotContains( 'toolset/cache', $slugs );
	}

	/**
	 * T010 — every returned record carries tab_group.
	 *
	 * @return void
	 */
	public function test_records_expose_tab_group(): void {
		$request = new WP_REST_Request( 'GET', '/acrossai/v1/abilities' );
		$request->set_param( 'per_page', 5 );
		$data = rest_get_server()->dispatch( $request )->get_data();

		$this->assertNotEmpty( $data );
		foreach ( $data as $row ) {
			$this->assertArrayHasKey( 'tab_group', $row );
			$this->assertIsString( $row['tab_group'] );
		}
	}

	/**
	 * T011 — status=draft is answered from the DB branch, because a draft is never registered.
	 *
	 * Filtering the registry for drafts can only ever return nothing; routing to the DB table is
	 * what makes the control do something (FR-015a).
	 *
	 * @return void
	 */
	public function test_status_draft_does_not_return_published_registry_rows(): void {
		$request = new WP_REST_Request( 'GET', '/acrossai/v1/abilities' );
		$request->set_param( 'status', 'draft' );
		$request->set_param( 'per_page', 100 );
		$data = rest_get_server()->dispatch( $request )->get_data();

		foreach ( $data as $row ) {
			$this->assertSame( 'draft', $row['status'] );
		}
	}

	/**
	 * T011 — status=publish does not exclude registry rows, which are always published.
	 *
	 * @return void
	 */
	public function test_status_publish_retains_registry_rows(): void {
		$request = new WP_REST_Request( 'GET', '/acrossai/v1/abilities' );
		$request->set_param( 'status', 'publish' );
		$request->set_param( 'per_page', 5 );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertNotEmpty( $response->get_data() );
	}
}
