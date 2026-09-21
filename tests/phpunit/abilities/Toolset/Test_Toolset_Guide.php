<?php
/**
 * Tests: the server guide names every toolset the caller can actually call.
 *
 * The guide is the first thing a model reads, and its whole value is that the
 * list it prints matches the tool list the client is holding. Two ways to break
 * that, and this file pins both:
 *
 *   - naming a toolset that is not registered, sending a model at a tool that
 *     does not exist;
 *   - omitting one that is, leaving a tool in the caller's list that the guide
 *     claiming to name every toolset never mentions.
 *
 * The second became possible when default Toolsets started registering while
 * empty: `AcrossAI_Ability_Group::counts()` is built by walking abilities, so a
 * group holding none has no key there at all.
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Abilities\Toolset;

use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\AcrossAI_Catch_All_Integration;
use AcrossAI_Abilities_Manager\Includes\Abilities\Toolset\Guide;
use AcrossAI_Abilities_Manager\Includes\Abilities\Toolset\Integration_Toolset;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Group;
use PHPUnit\Framework\TestCase;

/**
 * What the guide lists, and what it refuses to.
 */
class Test_Toolset_Guide extends TestCase {

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
		AcrossAI_Ability_Group::flush();
		parent::tearDown();
	}

	/**
	 * Register a member of the `content` group.
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

	/**
	 * Find one toolset row in the guide's output.
	 *
	 * @param  array<string, mixed> $out  Guide output.
	 * @param  string               $name Toolset name.
	 * @return array<string, mixed>|null
	 */
	private function row( array $out, string $name ): ?array {
		foreach ( $out['toolsets'] as $row ) {
			if ( $name === $row['name'] ) {
				return $row;
			}
		}

		return null;
	}

	/* ----------------------------------------------------------------- */

	/**
	 * A registered toolset holding nothing is listed, and says so.
	 *
	 * Without this the caller holds a tool the guide never mentions, and has no
	 * way to tell whether it is real.
	 */
	public function test_an_empty_registered_default_is_listed_with_a_zero_count(): void {
		( new Fixture_Toolset() )->register();

		$row = $this->row( ( new Guide() )->execute(), 'toolset/content' );

		$this->assertNotNull( $row, 'A registered toolset must be named even while empty.' );
		$this->assertSame( 0, $row['abilities'] );
	}

	/**
	 * …and it tells the caller the emptiness is not the end of the matter.
	 */
	public function test_the_empty_row_says_to_call_discover_anyway(): void {
		( new Fixture_Toolset() )->register();

		$row = $this->row( ( new Guide() )->execute(), 'toolset/content' );

		$this->assertStringContainsString( 'discover', $row['call'] );
	}

	/**
	 * A toolset with members still reports its real size.
	 *
	 * The zero-count path is an addition, not a replacement.
	 */
	public function test_a_populated_toolset_reports_its_count(): void {
		$this->given_member( 'content/get-post' );
		$this->given_member( 'content/update-post' );
		( new Fixture_Toolset() )->register();

		$row = $this->row( ( new Guide() )->execute(), 'toolset/content' );

		$this->assertSame( 2, $row['abilities'] );
	}

	/**
	 * A group with no registered dispatcher is never named.
	 *
	 * This is the original guarantee and the reason the new listing is keyed on
	 * registered abilities rather than on the groups the site knows about.
	 */
	public function test_a_group_without_a_dispatcher_is_not_named(): void {
		$this->given_member( 'content/get-post' );

		$this->assertNull(
			$this->row( ( new Guide() )->execute(), 'toolset/content' ),
			'Naming an unregistered toolset sends a model at a tool that does not exist.'
		);
	}

	/**
	 * A foreign `toolset/` ability is never mistaken for one of ours.
	 *
	 * `toolset/` is a shared namespace. The transport registers
	 * `toolset/setup-required` in it — a diagnostic it serves only while this
	 * add-on is missing, and strips from every healthy server. Listing it as an
	 * empty default told a model to call a tool it will never be given.
	 *
	 * Caught on a live site, not here: the first cut of `empty_groups()` matched
	 * on the slug prefix.
	 */
	public function test_a_foreign_toolset_slug_is_not_listed(): void {
		$GLOBALS['acrossai_test_abilities']['toolset/setup-required'] = new Fixture_Ability(
			'toolset/setup-required',
			array(
				'label'       => 'Setup required',
				'description' => 'Registered by the transport, not by this plugin.',
				// No meta.acrossai.toolset — it is not one of our dispatchers.
				'meta'        => array( 'mcp' => array( 'type' => 'tool' ) ),
			)
		);
		AcrossAI_Ability_Group::flush();

		$this->assertNull(
			$this->row( ( new Guide() )->execute(), 'toolset/setup-required' ),
			'Only abilities carrying the dispatcher marker are ours to name.'
		);
	}

	/**
	 * Neither the guide itself nor Integrations is listed as an ability group.
	 *
	 * `server-guide` is this ability, and `integrations` is described under
	 * `special` — listing either among the groups would double-count it.
	 */
	public function test_the_guide_and_integrations_are_not_listed_as_groups(): void {
		( new Guide() )->register();

		$out = ( new Guide() )->execute();

		$this->assertNull( $this->row( $out, 'toolset/server-guide' ) );
		$this->assertNull( $this->row( $out, 'toolset/integrations' ) );
	}

	/**
	 * The catch-all is described once it is registered, empty or not.
	 *
	 * It used to be described only when it had members, which is the opposite of
	 * when a caller most needs to be told what it is for.
	 */
	public function test_the_empty_catch_all_is_still_described(): void {
		( new Integration_Toolset( new AcrossAI_Catch_All_Integration() ) )->register();

		$special = ( new Guide() )->execute()['special'];

		$this->assertArrayHasKey( 'toolset/other', $special );
		$this->assertStringContainsString( 'plugins', $special['toolset/other'] );
	}

	/**
	 * Both special entries warn that their contents move.
	 *
	 * These are the two whose membership tracks plugin activation, so an answer
	 * a caller keeps goes stale without anything telling it.
	 */
	public function test_both_special_entries_warn_the_listing_moves(): void {
		( new Integration_Toolset( new AcrossAI_Catch_All_Integration() ) )->register();

		$special = ( new Guide() )->execute()['special'];

		$this->assertStringContainsString( 'activated', $special['toolset/other'] );
		$this->assertStringContainsString( 'discover again', $special['toolset/integrations'] );
	}

	/**
	 * An unregistered catch-all is still not described.
	 *
	 * It registers on every healthy site now, but another plugin can take the
	 * slug first — and describing a tool the caller cannot call is the fault
	 * this guide exists to prevent.
	 */
	public function test_an_unregistered_catch_all_is_not_described(): void {
		$special = ( new Guide() )->execute()['special'];

		$this->assertArrayNotHasKey( 'toolset/other', $special );
	}
}
