<?php
/**
 * Tests: publishing Toolsets onto an MCP server somebody else owns.
 *
 * Behavioural throughout. Source-string assertions have twice passed in this feature's work while
 * the behaviour was wrong, so these drive the two filter callbacks and read what comes back.
 *
 * The failure mode this class has to be held to is **silence**, not error. Nothing here can throw
 * on a host that is absent, switched off, or running an adapter whose contract has moved — it
 * simply contributes nothing. That is correct behaviour and it is also indistinguishable from a
 * bug, which is how the first working draft shipped tools onto Elementor's server with the connect
 * message silently missing: the adapter that won class resolution was 0.7.x, whose records are
 * rebuilt through the schema rather than through a static `fromArray()`, and the guard bailed
 * exactly as written. Both contracts are covered below, by fixtures shaped like each.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.42
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Tests\Abilities\Mcp;

use AcrossAI_Abilities_Manager\Includes\Abilities\Mcp\Host_Mcp_Server;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Integration_Settings;
use PHPUnit\Framework\TestCase;
use WP_Ability;

/**
 * A host publisher whose every answer the test controls.
 */
final class Fixture_Host extends Host_Mcp_Server {

	/** @var bool Whether the host plugin is present. */
	public static bool $present = true;

	/** @var string[] Toolset slugs to contribute. */
	public static array $contribute = array( 'toolset/fixture-site', 'toolset/fixture-guide' );

	/** @var string Connect-time copy. */
	public static string $copy = 'Fixture briefing.';

	/** @return string */
	protected function integration_slug(): string {
		return 'rank-math';
	}

	/** @return bool */
	protected function host_available(): bool {
		return self::$present;
	}

	/** @return string */
	protected function tools_filter(): string {
		return 'fixture/host/tools';
	}

	/** @return string */
	protected function server_id(): string {
		return 'fixture-host-server';
	}

	/** @return string[] */
	protected function toolsets(): array {
		return self::$contribute;
	}

	/** @return string */
	protected function instructions(): string {
		return self::$copy;
	}
}

/**
 * A second host publisher, sharing the first one's toolsets.
 *
 * Exists to pin the one-pair-many-hosts property; see
 * `test_two_hosts_contribute_the_same_toolsets()`.
 */
final class Fixture_Second_Host extends Host_Mcp_Server {

	/** @return string */
	protected function integration_slug(): string {
		return 'rank-math';
	}

	/** @return bool */
	protected function host_available(): bool {
		return true;
	}

	/** @return string */
	protected function tools_filter(): string {
		return 'fixture/second-host/tools';
	}

	/** @return string */
	protected function server_id(): string {
		return 'second-host-server';
	}

	/** @return string[] */
	protected function toolsets(): array {
		return Fixture_Host::$contribute;
	}

	/** @return string */
	protected function instructions(): string {
		return 'Second briefing.';
	}
}

/**
 * A registered ability, so a contributed slug resolves.
 */
final class Fixture_Toolset_Ability extends WP_Ability {

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
		return array();
	}
}

/**
 * Stands in for the host's McpServer.
 */
final class Fixture_Server {

	/** @var string */
	private string $id;

	/**
	 * @param string $id Server id.
	 */
	public function __construct( string $id ) {
		$this->id = $id;
	}

	/** @return string */
	public function get_server_id(): string {
		return $this->id;
	}
}

/**
 * An adapter 0.5.x–0.6.x initialize DTO: hydrates itself.
 */
final class Fixture_Dto_Result {

	/** @var array<string, mixed> */
	public array $data;

	/**
	 * @param array<string, mixed> $data Result data.
	 */
	public function __construct( array $data ) {
		$this->data = $data;
	}

	/** @return array<string, mixed> */
	public function toArray(): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- mirrors the adapter DTO's own method name.
		return $this->data;
	}

	/**
	 * @param  array<string, mixed> $data Result data.
	 * @return self
	 */
	public static function fromArray( array $data ): self { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- mirrors the adapter DTO's own method name.
		return new self( $data );
	}
}

/**
 * An adapter 0.7.x initialize record: read-only, rebuilt through the schema.
 *
 * Deliberately has NO static `fromArray()` and no `toArray()`, like the real one — that absence is
 * what the older code path tripped over.
 */
final class Fixture_Record_Result {

	/** @var array<string, mixed> */
	private array $data;

	/**
	 * @param array<string, mixed> $data Result data.
	 */
	public function __construct( array $data ) {
		$this->data = $data;
	}

	/** @return array<string, mixed> */
	public function jsonSerialize(): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- mirrors the adapter record's own method name.
		return $this->data;
	}
}

/**
 * Stands in for the 0.7.x schema that owns hydration.
 */
final class Fixture_Schema {

	/** @var bool Whether hydration rejects the data. */
	public static bool $rejects = false;

	/**
	 * @param  string               $root_class Class to hydrate.
	 * @param  array<string, mixed> $value      Data.
	 * @return object
	 * @throws \RuntimeException When configured to reject.
	 */
	public function fromArray( string $root_class, array $value ): object { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- mirrors the adapter schema's own method name.
		if ( self::$rejects ) {
			throw new \RuntimeException( 'validation failed' );
		}

		return new $root_class( $value );
	}
}

/**
 * Contribution and scoping.
 */
class Test_Host_Mcp_Server extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Fixture_Host::$present    = true;
		Fixture_Host::$contribute = array( 'toolset/fixture-site', 'toolset/fixture-guide' );
		Fixture_Host::$copy       = 'Fixture briefing.';
		Fixture_Schema::$rejects  = false;

		$GLOBALS['acrossai_test_abilities'] = array();

		foreach ( array( 'toolset/fixture-site', 'toolset/fixture-guide' ) as $slug ) {
			$GLOBALS['acrossai_test_abilities'][ $slug ] = new Fixture_Toolset_Ability(
				$slug,
				array(
					'label'    => $slug,
					'category' => 'acrossai-toolset',
				)
			);
		}

		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array() );
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['acrossai_test_abilities'] = array();

		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array() );

		parent::tearDown();
	}

	/**
	 * Store a decision for one integration.
	 *
	 * @param  string $slug    Integration slug.
	 * @param  bool   $enabled Whether it is on.
	 * @return void
	 */
	private function switch_integration( string $slug, bool $enabled ): void {
		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array( $slug => $enabled ) );
	}

	/* ---------------------------------------------------------------------
	 * Tools.
	 * ------------------------------------------------------------------ */

	/**
	 * The Toolsets are appended to the host's list, in order, after the host's own.
	 *
	 * @return void
	 */
	public function test_it_appends_its_toolsets_to_the_hosts_list(): void {
		$tools = ( new Fixture_Host() )->contribute_tools( array( 'host/one', 'host/two' ) );

		$this->assertSame(
			array( 'host/one', 'host/two', 'toolset/fixture-site', 'toolset/fixture-guide' ),
			$tools,
			"The host's own tools are the reason a client connected there; ours go after them."
		);
	}

	/**
	 * An absent host gets nothing.
	 *
	 * @return void
	 */
	public function test_an_absent_host_is_not_contributed_to(): void {
		Fixture_Host::$present = false;

		$this->assertSame(
			array( 'host/one' ),
			( new Fixture_Host() )->contribute_tools( array( 'host/one' ) )
		);
	}

	/**
	 * A switched-off integration contributes nothing.
	 *
	 * The requirement this feature was gated on: switching the row off at
	 * `admin.php?page=acrossai-integrations` must take the tools off the host's server, not merely
	 * empty them.
	 *
	 * @return void
	 */
	public function test_a_switched_off_integration_contributes_nothing(): void {
		$this->switch_integration( 'rank-math', false );

		$this->assertSame(
			array( 'host/one' ),
			( new Fixture_Host() )->contribute_tools( array( 'host/one' ) )
		);
	}

	/**
	 * An integration nobody has decided about contributes normally.
	 *
	 * An unseeded key is not a decision. Reading it as "off" would make a fresh site contribute
	 * nothing while the Integrations screen showed the row switched on.
	 *
	 * @return void
	 */
	public function test_an_unseeded_integration_contributes(): void {
		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array() );

		$this->assertContains(
			'toolset/fixture-site',
			( new Fixture_Host() )->contribute_tools( array() )
		);
	}

	/**
	 * A slug that never registered is not advertised.
	 *
	 * The host passes these straight to `create_server()`, which logs a miss and carries on — so an
	 * unregistered slug leaves a client with a tool list that is quietly short and nothing anywhere
	 * a site owner would look.
	 *
	 * @return void
	 */
	public function test_an_unregistered_toolset_is_not_advertised(): void {
		Fixture_Host::$contribute = array( 'toolset/fixture-site', 'toolset/never-registered' );

		$this->assertSame(
			array( 'toolset/fixture-site' ),
			( new Fixture_Host() )->contribute_tools( array() )
		);
	}

	/**
	 * Contributing twice does not duplicate a slug.
	 *
	 * @return void
	 */
	public function test_it_does_not_duplicate_a_slug_already_present(): void {
		$host  = new Fixture_Host();
		$tools = $host->contribute_tools( $host->contribute_tools( array() ) );

		$this->assertSame( array( 'toolset/fixture-site', 'toolset/fixture-guide' ), $tools );
	}

	/**
	 * A filter value that is not a list is handed straight back.
	 *
	 * @return void
	 */
	public function test_a_non_array_filter_value_is_returned_untouched(): void {
		$this->assertSame( 'junk', ( new Fixture_Host() )->contribute_tools( 'junk' ) );
	}

	/**
	 * Two hosts contribute the SAME pair of slugs, and that is the design.
	 *
	 * The reason there is one `Site_Toolset` and one `Site_Guide` rather than a pair per host. An
	 * ability slug is not owned by a server: each host's filter appends it and the adapter builds
	 * that server's tool list from slugs, so contributing one ability to N servers costs N filter
	 * callbacks and no duplication. On this machine nine installed plugins create their own MCP
	 * servers — a pair each would have been nine copies of one class to change a slug and three
	 * sentences.
	 *
	 * This asserts the property directly, because it is the thing a future "let's give each host its
	 * own toolset" refactor would quietly break.
	 *
	 * @return void
	 */
	public function test_two_hosts_contribute_the_same_toolsets(): void {
		$first  = new Fixture_Host();
		$second = new Fixture_Second_Host();

		$this->assertSame(
			array( 'toolset/fixture-site', 'toolset/fixture-guide' ),
			$first->contribute_tools( array() )
		);
		$this->assertSame(
			array( 'toolset/fixture-site', 'toolset/fixture-guide' ),
			$second->contribute_tools( array() ),
			'A second host reuses the shared pair rather than declaring its own.'
		);

		// And each still scopes its connect message to its own server.
		$result = new Fixture_Record_Result( array( 'instructions' => 'Host.' ) );

		$this->assertStringContainsString(
			'Second briefing.',
			$second->contribute_instructions( $result, new Fixture_Server( 'second-host-server' ), new Fixture_Schema() )->jsonSerialize()['instructions']
		);
		$this->assertSame(
			$result,
			$first->contribute_instructions( $result, new Fixture_Server( 'second-host-server' ), new Fixture_Schema() ),
			"The first host must not describe itself on the second host's server."
		);
	}

	/* ---------------------------------------------------------------------
	 * Instructions — both adapter contracts.
	 * ------------------------------------------------------------------ */

	/**
	 * 0.7.x: the record is rebuilt through the schema.
	 *
	 * The contract the first draft did not handle. It bailed on the missing `toArray()` and
	 * returned the record untouched — tools landed, the briefing did not, and no error was raised
	 * anywhere.
	 *
	 * @return void
	 */
	public function test_it_appends_instructions_on_the_schema_hydrated_contract(): void {
		$result = new Fixture_Record_Result( array( 'instructions' => 'Host sentence.' ) );

		$out = ( new Fixture_Host() )->contribute_instructions(
			$result,
			new Fixture_Server( 'fixture-host-server' ),
			new Fixture_Schema()
		);

		$this->assertInstanceOf( Fixture_Record_Result::class, $out );
		$this->assertSame(
			"Host sentence.\n\nFixture briefing.",
			$out->jsonSerialize()['instructions'],
			"The host's own sentence stays first — it names the server the client thinks it reached."
		);
	}

	/**
	 * 0.5.x–0.6.x: the DTO rebuilds itself.
	 *
	 * @return void
	 */
	public function test_it_appends_instructions_on_the_self_hydrating_contract(): void {
		$result = new Fixture_Dto_Result( array( 'instructions' => 'Host sentence.' ) );

		$out = ( new Fixture_Host() )->contribute_instructions(
			$result,
			new Fixture_Server( 'fixture-host-server' )
		);

		$this->assertInstanceOf( Fixture_Dto_Result::class, $out );
		$this->assertSame( "Host sentence.\n\nFixture briefing.", $out->toArray()['instructions'] );
	}

	/**
	 * A host with no instructions of its own gets ours alone, with no leading blank lines.
	 *
	 * @return void
	 */
	public function test_an_empty_host_message_yields_ours_alone(): void {
		$out = ( new Fixture_Host() )->contribute_instructions(
			new Fixture_Record_Result( array( 'instructions' => '' ) ),
			new Fixture_Server( 'fixture-host-server' ),
			new Fixture_Schema()
		);

		$this->assertSame( 'Fixture briefing.', $out->jsonSerialize()['instructions'] );
	}

	/**
	 * Another server's initialize is untouched.
	 *
	 * The filter fires for EVERY server the adapter holds, ours included. An unscoped callback
	 * would describe Elementor's gateway to a client connected to an AcrossAI server, which has its
	 * own guide and does not have that tool.
	 *
	 * @return void
	 */
	public function test_another_servers_instructions_are_left_alone(): void {
		$result = new Fixture_Record_Result( array( 'instructions' => 'Someone else.' ) );

		$out = ( new Fixture_Host() )->contribute_instructions(
			$result,
			new Fixture_Server( 'acrossai-mcp-server' ),
			new Fixture_Schema()
		);

		$this->assertSame( $result, $out, 'A different server id must come back as the same object.' );
		$this->assertSame( 'Someone else.', $out->jsonSerialize()['instructions'] );
	}

	/**
	 * A switched-off integration does not alter the connect message either.
	 *
	 * @return void
	 */
	public function test_a_switched_off_integration_leaves_instructions_alone(): void {
		$this->switch_integration( 'rank-math', false );

		$result = new Fixture_Record_Result( array( 'instructions' => 'Host sentence.' ) );

		$this->assertSame(
			$result,
			( new Fixture_Host() )->contribute_instructions(
				$result,
				new Fixture_Server( 'fixture-host-server' ),
				new Fixture_Schema()
			)
		);
	}

	/**
	 * No server argument means no scoping is possible, so nothing is changed.
	 *
	 * @return void
	 */
	public function test_a_missing_server_argument_changes_nothing(): void {
		$result = new Fixture_Record_Result( array( 'instructions' => 'Host sentence.' ) );

		$this->assertSame( $result, ( new Fixture_Host() )->contribute_instructions( $result ) );
	}

	/**
	 * A result shaped like neither contract is handed back rather than guessed at.
	 *
	 * This is what keeps an adapter copy this was never run against from fataling: eight versions
	 * between 0.1.0 and 0.7.1 are installed on the author's machine alone, and whichever loads
	 * first wins class resolution.
	 *
	 * @return void
	 */
	public function test_an_unrecognised_result_shape_is_returned_untouched(): void {
		$result = new \stdClass();
		$result->instructions = 'Host sentence.';

		$this->assertSame(
			$result,
			( new Fixture_Host() )->contribute_instructions( $result, new Fixture_Server( 'fixture-host-server' ) )
		);
	}

	/**
	 * Hydration that rejects the amended data costs the briefing and nothing else.
	 *
	 * @return void
	 */
	public function test_rejected_hydration_falls_back_to_the_original_result(): void {
		Fixture_Schema::$rejects = true;

		$result = new Fixture_Record_Result( array( 'instructions' => 'Host sentence.' ) );

		$out = ( new Fixture_Host() )->contribute_instructions(
			$result,
			new Fixture_Server( 'fixture-host-server' ),
			new Fixture_Schema()
		);

		$this->assertSame( $result, $out );
		$this->assertSame( 'Host sentence.', $out->jsonSerialize()['instructions'] );
	}

	/**
	 * Empty copy is not appended as blank lines.
	 *
	 * @return void
	 */
	public function test_empty_copy_is_not_appended(): void {
		Fixture_Host::$copy = '   ';

		$result = new Fixture_Record_Result( array( 'instructions' => 'Host sentence.' ) );

		$this->assertSame(
			$result,
			( new Fixture_Host() )->contribute_instructions(
				$result,
				new Fixture_Server( 'fixture-host-server' ),
				new Fixture_Schema()
			)
		);
	}

	/**
	 * Nested members survive the round trip.
	 *
	 * Both contracts hand back a structure whose members are themselves objects — capabilities,
	 * serverInfo — and hydration expects arrays all the way down, so a shallow cast rebuilds into a
	 * validation failure.
	 *
	 * @return void
	 */
	public function test_nested_result_members_survive_the_round_trip(): void {
		$capabilities          = new \stdClass();
		$capabilities->tools   = new \stdClass();
		$capabilities->tools->listChanged = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- MCP wire format.

		$out = ( new Fixture_Host() )->contribute_instructions(
			new Fixture_Record_Result(
				array(
					'instructions' => 'Host sentence.',
					'capabilities' => $capabilities,
				)
			),
			new Fixture_Server( 'fixture-host-server' ),
			new Fixture_Schema()
		);

		$data = $out->jsonSerialize();

		$this->assertSame( array( 'tools' => array( 'listChanged' => false ) ), $data['capabilities'] );
		$this->assertStringContainsString( 'Fixture briefing.', $data['instructions'] );
	}
}
