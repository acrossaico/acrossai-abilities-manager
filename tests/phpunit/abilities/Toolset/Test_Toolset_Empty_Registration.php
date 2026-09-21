<?php
/**
 * Tests: a default Toolset is a tool even when it holds nothing.
 *
 * An MCP client is handed `tools/list` once, when it connects, and there is no
 * way to tell it the list changed — the adapter advertises
 * `tools.listChanged: false`, and a client handed the notification anyway
 * ignored it (#129). So a tool missing at connect time is missing for the life
 * of that connection, including after the plugin that would have filled it is
 * installed.
 *
 * That made emptiness permanent. `toolset/other` vanished from a site whose
 * abilities all had a home, and `toolset/integrations` — the one route to a
 * plugin a cached list predates — could vanish with it. The second is the
 * sharper failure: it is the escape hatch, so it is reached for precisely when
 * the tool list is already wrong.
 *
 * These tests pin both halves of the rule. A DEFAULT registers empty; a
 * per-plugin Toolset still does not, because it is reachable through
 * Integrations either way and registering every one of them would put a dozen
 * dead tools on every site.
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Abilities\Toolset;

use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\AcrossAI_Catch_All_Integration;
use AcrossAI_Abilities_Manager\Includes\Abilities\Toolset\Base_Toolset_Ability;
use AcrossAI_Abilities_Manager\Includes\Abilities\Toolset\Integration_Toolset;
use AcrossAI_Abilities_Manager\Includes\Abilities\Toolset\Integrations;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Group;
use PHPUnit\Framework\TestCase;

/**
 * Registration of empty Toolsets, and how they describe themselves.
 */
class Test_Toolset_Empty_Registration extends TestCase {

	/**
	 * Signed in, capable, with an empty registry.
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['acrossai_test_abilities']    = array();
		$GLOBALS['acrossai_test_capabilities'] = array( 'read' );
		$GLOBALS['acrossai_test_logged_in']    = true;
		$GLOBALS['acrossai_test_current_user'] = 1;
		Fixture_Ability::$calls                = array();
		Fixture_Ability::$permissions          = array();
		Fixture_Ability::$throws               = array();
		Fixture_Toolset::$for_group            = 'content';
		Fixture_Toolset::$is_default           = true;
		Fixture_Toolset::$is_volatile          = false;
		AcrossAI_Ability_Group::flush();
	}

	/**
	 * Tear down so a leaked fixture cannot reach the next suite.
	 */
	protected function tearDown(): void {
		$GLOBALS['acrossai_test_abilities'] = array();
		unset( $GLOBALS['acrossai_test_logged_in'] );
		$GLOBALS['acrossai_test_hooks'] = array();
		Fixture_Toolset::$is_default    = true;
		Fixture_Toolset::$is_volatile   = false;
		AcrossAI_Ability_Group::flush();
		parent::tearDown();
	}

	/**
	 * Register a member of the fixture's group.
	 *
	 * @param  string $name Ability name.
	 * @return void
	 */
	private function given_member( string $name ): void {
		$GLOBALS['acrossai_test_abilities'][ $name ] = new Fixture_Ability(
			$name,
			array(
				'label'       => $name,
				'description' => 'Fixture ' . $name,
				'category'    => 'acrossai-content',
				'meta'        => array(
					'acrossai' => array( 'tab_group' => 'content' ),
					'mcp'      => array( 'type' => 'tool' ),
				),
			)
		);

		Fixture_Ability::$permissions[ $name ] = true;

		AcrossAI_Ability_Group::flush();
	}

	/* ----------------------------------------------------------------- *
	 * The rule.
	 * ----------------------------------------------------------------- */

	/**
	 * A default Toolset is a tool whether or not it currently holds anything.
	 *
	 * This is the whole feature. Before it, a site where every ability found a
	 * group served thirteen tools while its own Tools tab listed fourteen, and
	 * reconnecting could not close the gap.
	 */
	public function test_a_default_toolset_with_an_empty_group_registers(): void {
		$toolset = new Fixture_Toolset();
		$toolset->register();

		$this->assertTrue(
			wp_has_ability( 'toolset/content' ),
			'A default Toolset must register even with no members.'
		);
	}

	/**
	 * A per-plugin Toolset with nothing in it is still left out.
	 *
	 * Registering these unconditionally would undo the stable default set: a
	 * dozen tools naming plugins the site does not have. They are reachable
	 * through `toolset/integrations` the moment their plugin arrives.
	 */
	public function test_a_non_default_toolset_with_an_empty_group_does_not_register(): void {
		Fixture_Toolset::$is_default = false;

		$toolset = new Fixture_Toolset();
		$toolset->register();

		$this->assertFalse(
			wp_has_ability( 'toolset/content' ),
			'An empty non-default Toolset must stay out of the tool list.'
		);
	}

	/**
	 * …and appears as soon as its group has something in it.
	 */
	public function test_a_non_default_toolset_registers_once_its_group_fills(): void {
		Fixture_Toolset::$is_default = false;
		$this->given_member( 'content/get-post' );

		$toolset = new Fixture_Toolset();
		$toolset->register();

		$this->assertTrue( wp_has_ability( 'toolset/content' ) );
	}

	/**
	 * A slug someone else holds is still never clobbered.
	 *
	 * The new exemption is about emptiness only. It must not reach the
	 * collision guard, which returns before membership is ever considered.
	 */
	public function test_a_default_does_not_claim_a_slug_another_plugin_holds(): void {
		$GLOBALS['acrossai_test_abilities']['toolset/content'] = new Fixture_Ability(
			'toolset/content',
			array(
				'label'       => 'Someone else',
				'description' => 'Registered first by another plugin.',
			)
		);

		$toolset = new Fixture_Toolset();
		$toolset->register();

		$this->assertSame(
			'Someone else',
			wp_get_ability( 'toolset/content' )->get_label(),
			'The collision guard outranks the empty-default exemption.'
		);
	}

	/* ----------------------------------------------------------------- *
	 * The two that must never disappear.
	 * ----------------------------------------------------------------- */

	/**
	 * `toolset/integrations` registers even when it spans nothing.
	 *
	 * Its own docblock promised a client "always has it", but it inherited the
	 * empty-group bail and could not override the private `has_any_member()`.
	 * Its membership comes only from non-default Toolsets, so blocking the two
	 * ability families that feed it removed the site's only route to a plugin
	 * installed after a client connected.
	 */
	public function test_integrations_registers_when_it_spans_no_groups(): void {
		$toolset = new Integrations();
		$toolset->register();

		$this->assertTrue(
			wp_has_ability( 'toolset/integrations' ),
			'The escape hatch must survive having nothing to reach.'
		);
	}

	/**
	 * `toolset/other` registers on a site where every ability found a group.
	 *
	 * The catch-all is empty in the normal, healthy case — which is exactly the
	 * case that used to drop it.
	 */
	public function test_the_catch_all_registers_when_nothing_fell_through(): void {
		$toolset = new Integration_Toolset( new AcrossAI_Catch_All_Integration() );
		$toolset->register();

		$this->assertTrue(
			wp_has_ability( 'toolset/other' ),
			'The catch-all is a default and must register while empty.'
		);
	}

	/* ----------------------------------------------------------------- *
	 * What an empty tool says when called.
	 * ----------------------------------------------------------------- */

	/**
	 * An empty Toolset answers discover, rather than failing.
	 *
	 * Registering one would be a poor trade if calling it were an error.
	 */
	public function test_an_empty_default_answers_discover_with_a_message(): void {
		$toolset = new Fixture_Toolset();
		$toolset->register();

		$out = $toolset->execute( array( 'action' => 'discover' ) );

		$this->assertTrue( $out['success'] );
		$this->assertSame( array(), $out['abilities'] );
		$this->assertSame( 0, $out['total'] );
		$this->assertNotEmpty( $out['message'] );
	}

	/**
	 * A stable Toolset makes no claim about freshness.
	 *
	 * `content` holds the same abilities on every site, so one discover stays
	 * true and saying otherwise would be noise on every call.
	 */
	public function test_a_stable_toolset_does_not_mark_its_payload(): void {
		$this->given_member( 'content/get-post' );

		$out = ( new Fixture_Toolset() )->execute( array( 'action' => 'discover' ) );

		$this->assertArrayNotHasKey( 'volatile', $out );
		$this->assertArrayNotHasKey( 'note', $out );
	}

	/**
	 * A volatile Toolset says its listing can move under the caller.
	 *
	 * The tool DESCRIPTION is cached when the client connects, so it cannot
	 * carry this. The discover payload is rebuilt on every call, which makes it
	 * the only channel that reaches a model mid-session.
	 */
	public function test_a_volatile_toolset_marks_its_payload(): void {
		Fixture_Toolset::$is_volatile = true;
		$this->given_member( 'content/get-post' );

		$out = ( new Fixture_Toolset() )->execute( array( 'action' => 'discover' ) );

		$this->assertTrue( $out['volatile'] );
		$this->assertStringContainsString( 'discover again', $out['note'] );
	}

	/**
	 * An empty volatile listing carries the warning too.
	 *
	 * This is the case most likely to be mistaken for settled: a caller told
	 * "nothing here" has every reason to stop asking, and is then wrong from
	 * the next plugin activation onwards.
	 */
	public function test_an_empty_volatile_listing_is_marked_and_not_final(): void {
		Fixture_Toolset::$is_volatile = true;

		$out = ( new Fixture_Toolset() )->execute( array( 'action' => 'discover' ) );

		$this->assertTrue( $out['success'] );
		$this->assertTrue( $out['volatile'] );
		$this->assertStringContainsString( 'right now', $out['message'] );
		$this->assertStringNotContainsString(
			'No abilities in this group are currently available to you.',
			$out['message'],
			'The settled wording tells a caller to stop asking.'
		);
	}

	/**
	 * Every key a discover response carries is declared in the output schema.
	 *
	 * The schema is `additionalProperties: false`, so an undeclared key does not
	 * degrade — WordPress rejects the entire response and the caller gets
	 * "volatile is not a valid property of Object" in place of its listing.
	 *
	 * Caught on a live site rather than here, because the unit harness does not
	 * validate a response against its own ability's schema. This test closes
	 * that gap for the whole shape, not just the two keys that exposed it.
	 *
	 * @dataProvider provide_discover_states
	 * @param bool $volatile Whether the Toolset is volatile.
	 * @param bool $populated Whether its group has a member.
	 */
	public function test_every_discover_key_is_declared_in_the_output_schema(
		bool $volatile,
		bool $populated
	): void {
		Fixture_Toolset::$is_volatile = $volatile;

		if ( $populated ) {
			$this->given_member( 'content/get-post' );
		}

		$out      = ( new Fixture_Toolset() )->execute( array( 'action' => 'discover' ) );
		$declared = Base_Toolset_Ability::output_schema()['properties'];

		foreach ( array_keys( $out ) as $key ) {
			$this->assertArrayHasKey(
				$key,
				$declared,
				sprintf(
					'"%s" is returned but undeclared; additionalProperties:false fails the whole response.',
					$key
				)
			);
		}
	}

	/**
	 * The four discover shapes: volatile or not, populated or not.
	 *
	 * @return array<string, array{bool, bool}>
	 */
	public static function provide_discover_states(): array {
		return array(
			'stable, populated'   => array( false, true ),
			'stable, empty'       => array( false, false ),
			'volatile, populated' => array( true, true ),
			'volatile, empty'     => array( true, false ),
		);
	}

	/**
	 * Integrations and every per-plugin dispatcher are volatile by declaration.
	 *
	 * Asserted on the real classes rather than the fixture: these are the ones
	 * whose contents actually track plugin activation, and a future edit that
	 * drops the override should fail here.
	 */
	public function test_the_plugin_fed_toolsets_declare_themselves_volatile(): void {
		$integrations = new Integrations();
		$integrations->register();
		$catch_all = new Integration_Toolset( new AcrossAI_Catch_All_Integration() );
		$catch_all->register();

		$this->assertTrue(
			$integrations->execute( array( 'action' => 'discover' ) )['volatile'] ?? false,
			'toolset/integrations tracks what is installed.'
		);
		$this->assertTrue(
			$catch_all->execute( array( 'action' => 'discover' ) )['volatile'] ?? false,
			'toolset/other tracks what is installed.'
		);
	}
}
