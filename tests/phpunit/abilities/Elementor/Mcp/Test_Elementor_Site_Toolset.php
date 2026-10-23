<?php
/**
 * Tests: the gateway published onto Elementor's MCP server.
 *
 * Driven through `execute()` and read from the response, never asserted against the source. Two
 * bugs in this feature's own work passed a source-assertion test while the behaviour was wrong.
 *
 * Three things here are worth more than they look:
 *
 * - **The menu names `area`, not `sub_group`.** A sub-group is a division WITHIN a group, so it can
 *   never equal a group name. The first draft offered `sub_group=database` and the drill came back
 *   empty with nothing to explain why — the exact trap `Toolset\Integrations` carries a comment
 *   about. A test that only checked "the menu has rows" would have shipped it.
 * - **`info` keeps its schemas.** The lean-by-default override has to apply to `discover` alone.
 *   Applied to `info` as well it strips the input and output schemas off every call, leaving a
 *   model guessing at parameters — the one thing `info` exists to prevent.
 * - **Nothing here reaches an AcrossAI server.** This Toolset is written for Elementor's audience
 *   and must not be offered to ours.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.42
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Tests\Abilities\Elementor\Mcp;

use AcrossAI_Abilities_Manager\Includes\Abilities\Elementor\Mcp\Site_Guide;
use AcrossAI_Abilities_Manager\Includes\Abilities\Elementor\Mcp\Site_Toolset;
use AcrossAI_Abilities_Manager\Includes\Abilities\Toolset\Database;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Group;
use PHPUnit\Framework\TestCase;
use WP_Ability;

/**
 * A member of some group, as any ability suite would register one.
 */
final class Fixture_Site_Ability extends WP_Ability {

	/**
	 * @param  mixed $input Input.
	 * @return bool
	 */
	public function check_permissions( $input = null ) {
		return true;
	}

	/**
	 * @param  mixed $input Input.
	 * @return mixed
	 */
	public function execute( $input = null ) {
		return array( 'ran' => $this->get_name() );
	}
}

/**
 * Gateway behaviour.
 */
class Test_Elementor_Site_Toolset extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['acrossai_test_abilities']    = array();
		$GLOBALS['acrossai_test_capabilities'] = array( 'read', 'manage_options' );
		$GLOBALS['acrossai_test_current_user'] = 1;

		AcrossAI_Ability_Group::flush();
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['acrossai_test_abilities'] = array();

		unset( $GLOBALS['acrossai_test_filter_values'] );

		AcrossAI_Ability_Group::flush();

		parent::tearDown();
	}

	/**
	 * Put an ability in a group.
	 *
	 * @param  string $name  Ability name.
	 * @param  string $group Its group.
	 * @return void
	 */
	private function given_member( string $name, string $group ): void {
		$GLOBALS['acrossai_test_abilities'][ $name ] = new Fixture_Site_Ability(
			$name,
			array(
				'label'         => ucwords( str_replace( array( '/', '-' ), ' ', $name ) ),
				'description'   => 'Does something in ' . $group . '. More detail follows here.',
				'category'      => 'acrossai-' . $group,
				'input_schema'  => array( 'type' => 'object', 'properties' => array() ),
				'output_schema' => array( 'type' => 'object' ),
				'meta'          => array(
					'acrossai' => array( 'tab_group' => $group ),
					'mcp'      => array( 'type' => 'tool' ),
				),
			)
		);

		AcrossAI_Ability_Group::flush();
	}

	/**
	 * Put a NON-dispatchable entry in a group, as Elementor's best-practice documents are.
	 *
	 * `mcp.type: resource` lands in a group and counts towards
	 * `AcrossAI_Ability_Group::counts()`, but a Toolset can never dispatch to it.
	 *
	 * @param  string $name  Ability name.
	 * @param  string $group Its group.
	 * @return void
	 */
	private function given_resource( string $name, string $group ): void {
		$GLOBALS['acrossai_test_abilities'][ $name ] = new Fixture_Site_Ability(
			$name,
			array(
				'label'    => $name,
				'category' => 'acrossai-' . $group,
				'meta'     => array(
					'acrossai' => array( 'tab_group' => $group ),
					'mcp'      => array( 'type' => 'resource' ),
				),
			)
		);

		AcrossAI_Ability_Group::flush();
	}

	/**
	 * A site with two populated areas.
	 *
	 * @return void
	 */
	private function given_a_two_area_site(): void {
		$this->given_member( 'database/get-db-prefix', 'database' );
		$this->given_member( 'database/audit-health', 'database' );
		$this->given_member( 'content/create-post', 'content' );
	}

	/**
	 * Run the gateway.
	 *
	 * @param  array<string, mixed> $input Caller input.
	 * @return array<string, mixed>
	 */
	private function call( array $input ): array {
		return ( new Site_Toolset() )->execute( $input );
	}

	/* ---------------------------------------------------------------------
	 * The menu.
	 * ------------------------------------------------------------------ */

	/**
	 * A bare discover returns areas with counts, not abilities.
	 *
	 * @return void
	 */
	public function test_a_bare_discover_returns_the_area_menu(): void {
		$this->given_a_two_area_site();

		$out = $this->call( array( 'action' => 'discover' ) );

		$this->assertTrue( $out['success'] );
		$this->assertArrayNotHasKey(
			'abilities',
			$out,
			'"What is here?" must not be answered with every ability on the site.'
		);
		$this->assertSame( 2, $out['total_areas'] );
		$this->assertSame( 3, $out['total_abilities'] );
	}

	/**
	 * The menu offers `area`, which is the parameter that actually narrows.
	 *
	 * The regression this file exists for. `sub_group` reads plausibly and returns nothing.
	 *
	 * @return void
	 */
	public function test_the_menu_offers_the_parameter_that_narrows(): void {
		$this->given_a_two_area_site();

		$out = $this->call( array( 'action' => 'discover' ) );

		foreach ( $out['areas'] as $row ) {
			$this->assertArrayHasKey( 'area', $row, 'Each row must name the area to pass back.' );
			$this->assertArrayNotHasKey( 'sub_group', $row );
		}

		$this->assertStringContainsString( 'area=', (string) $out['hint'] );
		$this->assertStringNotContainsString( 'sub_group=', (string) $out['hint'] );
	}

	/**
	 * Every area the menu names can actually be drilled into.
	 *
	 * The assertion the first draft failed. It checked the menu's shape and the drill separately;
	 * this one follows the route the menu advertises, which is the only thing a model will do.
	 *
	 * @return void
	 */
	public function test_every_area_the_menu_names_can_be_drilled_into(): void {
		$this->given_a_two_area_site();

		$menu = $this->call( array( 'action' => 'discover' ) );

		$this->assertNotEmpty( $menu['areas'] );

		foreach ( $menu['areas'] as $row ) {
			$drill = $this->call(
				array(
					'action' => 'discover',
					'area'   => $row['area'],
				)
			);

			$this->assertTrue( $drill['success'], $row['area'] . ' must be reachable.' );
			$this->assertSame(
				$row['abilities'],
				$drill['total'],
				$row['area'] . ': the menu promised ' . $row['abilities'] . ' and the drill must deliver them.'
			);
		}
	}

	/**
	 * The menu counts what a drill returns, not what the group holds.
	 *
	 * A live check caught this: the gateway advertised `elementor 106` while the drill returned 99,
	 * because the menu was built from `AcrossAI_Ability_Group::counts()` and seven of those entries
	 * are `mcp.type: resource` — Elementor's best-practice documents, which a Toolset can never
	 * dispatch to. The gap was exactly the resource count.
	 *
	 * Overstating is the harmful direction: a caller told an area holds 106 and handed 99 has no
	 * way to know which seven were never real, or whether something is broken.
	 *
	 * @return void
	 */
	public function test_the_menu_does_not_count_abilities_it_cannot_dispatch_to(): void {
		$this->given_member( 'database/get-db-prefix', 'database' );
		$this->given_member( 'database/audit-health', 'database' );
		$this->given_resource( 'database/best-practices', 'database' );

		$menu = $this->call( array( 'action' => 'discover' ) );
		$row  = array_column( $menu['areas'], null, 'area' )['database'];

		$this->assertSame(
			2,
			$row['abilities'],
			'The resource must not be counted — three are in the group, two can be dispatched to.'
		);

		$drill = $this->call(
			array(
				'action' => 'discover',
				'area'   => 'database',
			)
		);

		$this->assertSame(
			$row['abilities'],
			$drill['total'],
			'Menu and drill must agree; they disagreed by exactly the resource count in production.'
		);
	}

	/**
	 * Each area row carries a one-line summary, so a model can route without drilling.
	 *
	 * The requirement behind this feature: knowing which area to call for a database job.
	 *
	 * @return void
	 */
	public function test_area_rows_carry_a_summary_for_routing(): void {
		$this->given_a_two_area_site();
		$GLOBALS['acrossai_test_abilities']['toolset/database'] = new Fixture_Site_Ability(
			'toolset/database',
			array(
				'label'       => 'Database',
				'description' => 'Work with the database: inspect the schema and run queries. Second sentence ignored.',
				'category'    => 'acrossai-toolset',
			)
		);

		$out  = $this->call( array( 'action' => 'discover' ) );
		$rows = array_column( $out['areas'], null, 'area' );

		$this->assertSame( 'Database', $rows['database']['label'] );
		$this->assertSame(
			'Work with the database: inspect the schema and run queries.',
			$rows['database']['covers'],
			'The opening sentence only — fourteen full descriptions would cost more than the menu saves.'
		);
	}

	/**
	 * An empty area is left out of the menu.
	 *
	 * @return void
	 */
	public function test_an_empty_site_says_so_rather_than_erroring(): void {
		$out = $this->call( array( 'action' => 'discover' ) );

		$this->assertTrue( $out['success'] );
		$this->assertSame( array(), $out['areas'] );
		$this->assertNotEmpty( $out['message'] );
	}

	/**
	 * The menu says it goes stale, because it does.
	 *
	 * This spans every group, so any plugin activation changes the answer, and the client's tool
	 * list cannot be refreshed to tell it.
	 *
	 * @return void
	 */
	public function test_the_menu_declares_itself_volatile(): void {
		$this->given_a_two_area_site();

		$this->assertTrue( $this->call( array( 'action' => 'discover' ) )['volatile'] );
	}

	/* ---------------------------------------------------------------------
	 * Drilling, and what a row costs.
	 * ------------------------------------------------------------------ */

	/**
	 * A drill returns only that area's abilities.
	 *
	 * @return void
	 */
	public function test_a_drill_returns_only_that_areas_abilities(): void {
		$this->given_a_two_area_site();

		$out = $this->call(
			array(
				'action' => 'discover',
				'area'   => 'database',
			)
		);

		$this->assertSame( 2, $out['total'] );

		foreach ( $out['abilities'] as $row ) {
			$this->assertStringStartsWith( 'database/', $row['name'] );
		}
	}

	/**
	 * Rows are name and label by default, not full descriptions.
	 *
	 * Measured on the author's site: the inherited default costs 10,299 tokens for fifty rows and
	 * this costs 1,093. A model asking what is in an area wants to pick one, not read fifty essays,
	 * and will not think to ask for less.
	 *
	 * @return void
	 */
	public function test_a_drill_is_lean_by_default(): void {
		$this->given_a_two_area_site();

		$out = $this->call(
			array(
				'action' => 'discover',
				'area'   => 'database',
			)
		);

		foreach ( $out['abilities'] as $row ) {
			$this->assertSame(
				array( 'name', 'label' ),
				array_keys( $row ),
				'An unprompted caller should get the cheap answer and pay for detail deliberately.'
			);
		}
	}

	/**
	 * Descriptions are one parameter away.
	 *
	 * @return void
	 */
	public function test_include_fields_restores_the_full_rows(): void {
		$this->given_a_two_area_site();

		$out = $this->call(
			array(
				'action'         => 'discover',
				'area'           => 'database',
				'include_fields' => array( 'name', 'description' ),
			)
		);

		$this->assertSame( array( 'name', 'description' ), array_keys( $out['abilities'][0] ) );
		$this->assertNotEmpty( $out['abilities'][0]['description'] );
	}

	/**
	 * `info` still returns schemas — the trap the lean default sets.
	 *
	 * `requested_fields()` feeds info as well as discover. Defaulting there too would strip the
	 * schemas off every info call, which is the one thing info exists to provide.
	 *
	 * @return void
	 */
	public function test_info_still_returns_the_schemas(): void {
		$this->given_a_two_area_site();

		$out = $this->call(
			array(
				'action'  => 'info',
				'ability' => 'database/get-db-prefix',
			)
		);

		$this->assertTrue( $out['success'] );
		$this->assertArrayHasKey( 'input_schema', $out['abilities'][0] );
		$this->assertArrayHasKey( 'output_schema', $out['abilities'][0] );
	}

	/**
	 * An ability in any area runs through the gateway.
	 *
	 * The whole point: a client on Elementor's server running a database ability.
	 *
	 * @return void
	 */
	public function test_it_executes_an_ability_from_any_area(): void {
		$this->given_a_two_area_site();

		$out = $this->call(
			array(
				'action'     => 'execute',
				'ability'    => 'database/get-db-prefix',
				'parameters' => array(),
			)
		);

		$this->assertTrue( $out['success'] );
		$this->assertSame( array( 'ran' => 'database/get-db-prefix' ), $out['data'] );
	}

	/**
	 * Search crosses every area at once.
	 *
	 * @return void
	 */
	public function test_search_crosses_every_area(): void {
		$this->given_a_two_area_site();

		$out = $this->call(
			array(
				'action' => 'discover',
				'search' => 'audit',
			)
		);

		$this->assertSame( 1, $out['total'] );
		$this->assertSame( 'database/audit-health', $out['abilities'][0]['name'] );
	}

	/* ---------------------------------------------------------------------
	 * Kept off our own servers.
	 * ------------------------------------------------------------------ */

	/**
	 * Neither tool is offered to an AcrossAI server.
	 *
	 * Both are written for Elementor's audience. Offered in the transport's Tools picker they read
	 * as a curation choice an operator could usefully make, and they are not; added to the
	 * `acrossai` type they would arrive on every AcrossAI server at Reset to Type Defaults.
	 *
	 * The callbacks are invoked DIRECTLY rather than through `apply_filters()`. This harness stubs
	 * `add_filter()` to a no-op and `apply_filters()` to return its value unchanged, so the filter
	 * form of this test passed while asserting nothing at all — it could not have failed. The
	 * first-party control below is what gives the assertions teeth: `toolset/database` declares
	 * none of this and must appear in every list these two are absent from.
	 *
	 * @return void
	 */
	public function test_neither_tool_is_offered_to_an_acrossai_server(): void {
		$control = new Database();
		$empty   = array( 'acrossai' => array( 'tools' => array() ) );

		$this->assertContains(
			'toolset/database',
			$control->declare_tool_level_ability( array() ),
			'Control: an ordinary Toolset IS offered, so absence below means something.'
		);
		$this->assertContains( 'toolset/database', $control->declare_server_type_tool( $empty )['acrossai']['tools'] );

		foreach ( array( new Site_Toolset(), new Site_Guide() ) as $host_only ) {
			$this->assertSame(
				array(),
				$host_only->declare_tool_level_ability( array() ),
				get_class( $host_only ) . ' must not reach the Tools picker.'
			);
			$this->assertSame(
				array(),
				$host_only->declare_server_type_tool( $empty )['acrossai']['tools'],
				get_class( $host_only ) . ' must not become an acrossai type default.'
			);
		}

		$this->assertSame(
			array(),
			( new Site_Toolset() )->declare_integration_group( array() ),
			'toolset/integrations must not span the gateway, or it would name it to our own clients.'
		);
	}

	/**
	 * Both tools are hidden from the sitewide abilities screen.
	 *
	 * The one declaration a host-only Toolset keeps, and the asymmetry worth testing: everything
	 * else above is withheld, this is not. It is still a dispatcher routing to hundreds of
	 * abilities, so an operator must not be invited to override or disable it.
	 *
	 * @return void
	 */
	public function test_both_tools_are_protected_from_the_abilities_screen(): void {
		$this->assertContains( Site_Toolset::SLUG, ( new Site_Toolset() )->protect_own_slug( array() ) );
		$this->assertContains( Site_Guide::SLUG, ( new Site_Guide() )->protect_own_slug( array() ) );
	}

	/**
	 * The guide does not hijack the AcrossAI connect message.
	 *
	 * AcrossAI servers have their own guide; being told to call Elementor's would send a client at
	 * a tool it does not have.
	 *
	 * @return void
	 */
	public function test_the_guide_leaves_the_acrossai_connect_message_alone(): void {
		$guide = new Site_Guide();

		$this->assertSame(
			'original',
			$guide->declare_server_instructions( 'original', 'acrossai' )
		);
	}

	/**
	 * The guide never tells this client to call a tool it does not have.
	 *
	 * The flaw a live end-to-end check caught after the unit tests were already green. The
	 * inherited guide answers every row with "call it directly — it is in the default set, so your
	 * tool list has it", which is true on an AcrossAI server and false on Elementor's: a client
	 * there holds Elementor's own tools plus these two, so thirteen rows were pointing at twelve
	 * tools that do not exist. Naming an uncallable tool is the precise fault the guide exists to
	 * prevent, and it had reintroduced it.
	 *
	 * @return void
	 */
	public function test_the_guide_routes_everything_through_the_gateway(): void {
		$this->given_a_two_area_site();
		$GLOBALS['acrossai_test_abilities']['toolset/database'] = new Fixture_Site_Ability(
			'toolset/database',
			array(
				'label'       => 'Database',
				'description' => 'Work with the database.',
				'category'    => 'acrossai-toolset',
				'meta'        => array( 'acrossai' => array( 'toolset' => true ) ),
			)
		);
		AcrossAI_Ability_Group::flush();

		$out = ( new Site_Guide() )->execute();

		$this->assertNotEmpty( $out['toolsets'] );

		foreach ( $out['toolsets'] as $row ) {
			$this->assertStringContainsString(
				Site_Toolset::SLUG,
				$row['call'],
				$row['name'] . ': every route must go through the gateway.'
			);
			$this->assertStringNotContainsString(
				'your tool list has it',
				$row['call'],
				$row['name'] . ': this client\'s tool list does NOT have it.'
			);
		}

		$this->assertSame(
			array( Site_Toolset::SLUG ),
			array_keys( $out['special'] ),
			'The inherited text points at toolset/integrations, which is not on this server either.'
		);
	}

	/**
	 * The gateway is marked host-only in its meta, which is how the server guide skips it.
	 *
	 * Spanning every group gives it members while owning no group of its own, so the guide's
	 * "registered but empty" pass would otherwise name it.
	 *
	 * @return void
	 */
	public function test_the_gateway_is_marked_host_only_for_the_server_guide(): void {
		$toolset = new Site_Toolset();
		$this->given_a_two_area_site();
		$toolset->register();

		$registered = $GLOBALS['acrossai_test_abilities'][ Site_Toolset::SLUG ] ?? null;

		$this->assertInstanceOf( WP_Ability::class, $registered );

		$meta = $registered->get_meta_item( 'acrossai' );

		$this->assertTrue( $meta['toolset'] );
		$this->assertTrue( $meta['host_only'] );
	}
}
