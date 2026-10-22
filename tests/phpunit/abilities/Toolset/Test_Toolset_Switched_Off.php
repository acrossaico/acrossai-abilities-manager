<?php
/**
 * Tests: what a Toolset does when its integration is switched off.
 *
 * Behavioural, deliberately. The first version of this coverage asserted strings against the source
 * file, which is how an earlier bug in this very feature survived a passing test — the assertion
 * matched a comment rather than the behaviour. These drive `execute()` and read what comes back.
 *
 * The switch withdraws *this plugin's* contribution to an integration. It does not reach into the
 * host plugin, and several integrations sit on software that publishes abilities under the same
 * namespace whether we exist or not: WooCommerce reserves `woocommerce/`, Akismet ships its own,
 * Elementor and Jetpack have their own switches. So there are two switched-off cases, and the
 * difference between them is the whole behaviour:
 *
 * - **Nothing left in the group.** Our abilities were never registered and the host is contributing
 *   none, so the group is genuinely empty. The Toolset stays registered — vanishing would tell an
 *   assistant the tool does not exist, which is indistinguishable from "this site has no
 *   Elementor" — and every action answers with the reason and the screen to undo it on.
 * - **The host is still contributing.** Those abilities work. The Toolset serves them normally and
 *   attaches a notice saying what is missing, rather than refusing a group it could serve.
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Abilities\Toolset;

use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\AcrossAI_Toolset_Integrations;
use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\Rank_Math;
use AcrossAI_Abilities_Manager\Includes\Abilities\Toolset\Base_Toolset_Ability;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Integration_Settings;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Group;
use PHPUnit\Framework\TestCase;
use WP_Ability;

/**
 * A Toolset fronting one integration group.
 */
final class Fixture_Integration_Toolset extends Base_Toolset_Ability {

	/** @var string */
	public static string $for_group = 'rank-math';

	/** @var string[] Groups this Toolset spans; empty means just `$for_group`. */
	public static array $spans = array();

	/** @return bool */
	protected function is_server_type_default(): bool {
		return false;
	}

	/** @return string */
	protected function group(): string {
		return self::$for_group;
	}

	/** @return string[] */
	protected function groups(): array {
		return array() === self::$spans ? array( self::$for_group ) : self::$spans;
	}

	/** @return string */
	protected function slug(): string {
		return 'toolset/' . self::$for_group;
	}

	/** @return string */
	protected function toolset_label(): string {
		return 'Rank Math';
	}

	/** @return string */
	protected function toolset_description(): string {
		return 'Fixture integration toolset.';
	}
}

/**
 * A member whose execution outcome the test controls.
 */
final class Fixture_Host_Ability extends WP_Ability {

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
 * Switched-off behaviour.
 */
class Test_Toolset_Switched_Off extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['acrossai_test_abilities']    = array();
		$GLOBALS['acrossai_test_capabilities'] = array( 'read', 'manage_options' );
		$GLOBALS['acrossai_test_current_user'] = 1;

		// `rank-math` must be a group an integration actually owns, or the Toolset correctly
		// declines to treat a stored decision for it as meaningful.
		$GLOBALS['acrossai_test_filter_values']['acrossai_toolset_integrations'] = array( new Rank_Math() );

		Fixture_Integration_Toolset::$for_group = 'rank-math';
		Fixture_Integration_Toolset::$spans     = array();

		AcrossAI_Toolset_Integrations::flush();
		AcrossAI_Ability_Group::flush();

		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array() );
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['acrossai_test_abilities'] = array();

		unset( $GLOBALS['acrossai_test_filter_values'] );

		AcrossAI_Toolset_Integrations::flush();
		AcrossAI_Ability_Group::flush();

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

	/**
	 * Put an ability in a group, as the host plugin would.
	 *
	 * @param  string $name  Ability name.
	 * @param  string $group Its group.
	 * @return void
	 */
	private function given_member( string $name, string $group = 'rank-math' ): void {
		$GLOBALS['acrossai_test_abilities'][ $name ] = new Fixture_Host_Ability(
			$name,
			array(
				'label'         => $name,
				'description'   => 'Host ability ' . $name,
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

	/* ---------------------------------------------------------------------
	 * Switched on — the baseline.
	 * ------------------------------------------------------------------ */

	/**
	 * An enabled integration dispatches normally and carries no notice.
	 *
	 * @return void
	 */
	public function test_an_enabled_integration_dispatches_without_a_notice(): void {
		$this->switch_integration( 'rank-math', true );
		$this->given_member( 'rank-math/get-settings' );

		$result = ( new Fixture_Integration_Toolset() )->execute( array( 'action' => 'discover' ) );

		$this->assertTrue( $result['success'] );
		$this->assertArrayNotHasKey( 'notice', $result, 'A working integration must not be annotated.' );
		$this->assertCount( 1, $result['abilities'] );
	}

	/**
	 * An integration nobody has decided about behaves as enabled.
	 *
	 * An unseeded key is not a decision. If it read as off, a fresh site would answer every Toolset
	 * call with a message telling the owner to switch on something they never switched off.
	 *
	 * @return void
	 */
	public function test_an_unseeded_integration_dispatches_normally(): void {
		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array() );
		$this->given_member( 'rank-math/get-settings' );

		$result = ( new Fixture_Integration_Toolset() )->execute( array( 'action' => 'discover' ) );

		$this->assertTrue( $result['success'] );
		$this->assertArrayNotHasKey( 'notice', $result );
	}

	/* ---------------------------------------------------------------------
	 * Switched off, group empty.
	 * ------------------------------------------------------------------ */

	/**
	 * With nothing left in the group, every action explains itself.
	 *
	 * @dataProvider provide_actions
	 * @param  string $action One of discover, info, execute.
	 * @return void
	 */
	public function test_an_empty_switched_off_group_explains_itself( string $action ): void {
		$this->switch_integration( 'rank-math', false );

		$result = ( new Fixture_Integration_Toolset() )->execute( array( 'action' => $action ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'integration_disabled', $result['error_code'] );
		$this->assertStringContainsString( 'Rank Math', $result['error_message'] );
		$this->assertStringContainsString(
			'page=acrossai-integrations',
			$result['error_message'],
			'The message must name the screen that switches it back on.'
		);
	}

	/**
	 * @return array<string, array<int, string>>
	 */
	public static function provide_actions(): array {
		return array(
			'discover' => array( 'discover' ),
			'info'     => array( 'info' ),
			'execute'  => array( 'execute' ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Switched off, host still contributing — the case this exists for.
	 * ------------------------------------------------------------------ */

	/**
	 * The host's abilities are still listed.
	 *
	 * @return void
	 */
	public function test_a_hosts_abilities_are_still_listed_when_the_switch_is_off(): void {
		$this->switch_integration( 'rank-math', false );
		$this->given_member( 'rank-math/get-settings' );
		$this->given_member( 'rank-math/get-redirections' );

		$result = ( new Fixture_Integration_Toolset() )->execute( array( 'action' => 'discover' ) );

		$this->assertTrue( $result['success'], 'A group the host is still serving must not hard-fail.' );
		$this->assertCount( 2, $result['abilities'] );
	}

	/**
	 * …and are still executable.
	 *
	 * Listing them while refusing to run them would be the worst of both: the assistant sees a tool,
	 * calls it, and fails on use.
	 *
	 * @return void
	 */
	public function test_a_hosts_ability_still_executes_when_the_switch_is_off(): void {
		$this->switch_integration( 'rank-math', false );
		$this->given_member( 'rank-math/get-settings' );

		$result = ( new Fixture_Integration_Toolset() )->execute(
			array(
				'action'     => 'execute',
				'ability'    => 'rank-math/get-settings',
				'parameters' => array(),
			)
		);

		$this->assertTrue( $result['success'], 'The host\'s own ability must still run.' );
	}

	/**
	 * Every answer says what is missing.
	 *
	 * @dataProvider provide_actions
	 * @param  string $action One of discover, info, execute.
	 * @return void
	 */
	public function test_every_answer_is_annotated_while_the_switch_is_off( string $action ): void {
		$this->switch_integration( 'rank-math', false );
		$this->given_member( 'rank-math/get-settings' );

		$input = array( 'action' => $action );

		if ( 'discover' !== $action ) {
			$input['ability'] = 'rank-math/get-settings';
		}

		$result = ( new Fixture_Integration_Toolset() )->execute( $input );

		$this->assertArrayHasKey( 'notice', $result, $action . ' must carry the notice.' );
		$this->assertStringContainsString( 'Rank Math', $result['notice'] );
		$this->assertStringContainsString( 'page=acrossai-integrations', $result['notice'] );
	}

	/**
	 * The notice is a notice, not a failure.
	 *
	 * `success` must stay true so a client surfaces the message without treating the call as broken,
	 * and `error_code` must stay clear so nothing branches on it.
	 *
	 * @return void
	 */
	public function test_the_notice_does_not_make_the_call_a_failure(): void {
		$this->switch_integration( 'rank-math', false );
		$this->given_member( 'rank-math/get-settings' );

		$result = ( new Fixture_Integration_Toolset() )->execute( array( 'action' => 'discover' ) );

		$this->assertTrue( $result['success'] );
		$this->assertArrayNotHasKey( 'error_code', $result );
	}

	/**
	 * `notice` survives the Toolset's own output schema.
	 *
	 * The schema is `additionalProperties: false`, so a key the response carries but the schema does
	 * not name fails the entire call with `ability_invalid_output` rather than being dropped. That
	 * is exactly what happened the first time this was built.
	 *
	 * @return void
	 */
	public function test_notice_is_declared_in_the_output_schema(): void {
		$schema = Base_Toolset_Ability::output_schema();

		$this->assertArrayHasKey(
			'notice',
			$schema['properties'],
			'An undeclared key fails the whole call — see the comment beside it in the schema.'
		);
		$this->assertFalse( $schema['additionalProperties'] ?? true );
	}

	/* ---------------------------------------------------------------------
	 * Registration.
	 * ------------------------------------------------------------------ */

	/**
	 * A switched-off integration's Toolset still registers, with nothing in its group.
	 *
	 * A Toolset normally suppresses itself when its group is empty. Left to do that here it would
	 * vanish exactly when someone needs to be told why — so this is the one case where an empty
	 * group still registers.
	 *
	 * @return void
	 */
	public function test_an_empty_switched_off_group_still_registers_its_toolset(): void {
		$this->switch_integration( 'rank-math', false );

		$toolset = new Fixture_Integration_Toolset();
		$toolset->register();

		$this->assertTrue(
			\wp_has_ability( 'toolset/rank-math' ),
			'The Toolset must survive so it can explain itself.'
		);
	}

	/**
	 * An enabled integration with an empty group still suppresses itself.
	 *
	 * The pre-existing rule, asserted so the exception above did not quietly become the rule.
	 *
	 * @return void
	 */
	public function test_an_empty_enabled_group_still_suppresses_its_toolset(): void {
		$this->switch_integration( 'rank-math', true );

		$toolset = new Fixture_Integration_Toolset();
		$toolset->register();

		$this->assertFalse(
			\wp_has_ability( 'toolset/rank-math' ),
			'An integration that is simply absent should contribute no tool at all.'
		);
	}

	/* ---------------------------------------------------------------------
	 * Reach.
	 * ------------------------------------------------------------------ */

	/**
	 * A Toolset spanning several groups never reports itself switched off.
	 *
	 * `toolset/integrations` fronts many integrations at once. One of them being off is no reason to
	 * replace the whole tool with a message about it.
	 *
	 * @return void
	 */
	public function test_a_multi_group_toolset_is_never_switched_off(): void {
		$this->switch_integration( 'rank-math', false );

		Fixture_Integration_Toolset::$spans = array( 'rank-math', 'content' );

		$this->given_member( 'content/create-post', 'content' );

		$result = ( new Fixture_Integration_Toolset() )->execute( array( 'action' => 'discover' ) );

		$this->assertTrue( $result['success'] );
		$this->assertArrayNotHasKey( 'notice', $result );
	}

	/**
	 * A first-party group is never switched off, even with a stored decision.
	 *
	 * `content`, `blocks` and `appearance` have no integration behind them and no switch, so nothing
	 * should ever store a decision for one. This reads a site option a filter can rewrite, and the
	 * failure mode if one did is every first-party Toolset on the site replaced by a message telling
	 * the owner to switch on something that was never switchable.
	 *
	 * @return void
	 */
	public function test_a_first_party_group_is_never_switched_off(): void {
		$this->switch_integration( 'content', false );

		Fixture_Integration_Toolset::$for_group = 'content';

		$this->given_member( 'content/create-post', 'content' );

		$result = ( new Fixture_Integration_Toolset() )->execute( array( 'action' => 'discover' ) );

		$this->assertTrue( $result['success'], 'A first-party group has no switch to be off.' );
		$this->assertArrayNotHasKey( 'notice', $result );
	}

	/**
	 * The catch-all group is never switched off either.
	 *
	 * `other` holds plugins we deliberately do not adopt. It has no row and no switch.
	 *
	 * @return void
	 */
	public function test_the_catch_all_group_is_never_switched_off(): void {
		$this->switch_integration( AcrossAI_Toolset_Integrations::CATCH_ALL_GROUP, false );

		Fixture_Integration_Toolset::$for_group = AcrossAI_Toolset_Integrations::CATCH_ALL_GROUP;

		$this->given_member( 'some-plugin/do-a-thing', AcrossAI_Toolset_Integrations::CATCH_ALL_GROUP );

		$result = ( new Fixture_Integration_Toolset() )->execute( array( 'action' => 'discover' ) );

		$this->assertTrue( $result['success'] );
		$this->assertArrayNotHasKey( 'notice', $result );
	}
}
