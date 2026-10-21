<?php
/**
 * The Integrations switch refuses abilities; it does not remove them.
 *
 * This suite exists to keep one decision from being undone. Feature 102 deleted a per-category
 * switch that worked by skipping `wp_register_ability()`, and recorded why:
 *
 * > An access control MUST NOT work by preventing the controlled object from existing. It must let
 * > the object register and then deny access to it, so the object stays listable with its state
 * > visible.
 *
 * A global on/off per integration is the same feature asked for again, so it is the obvious place
 * for that shape to come back — a `continue` in a registration loop is smaller and more direct than
 * a refusal, and it would pass any test that only checked "a disabled integration cannot be used".
 * The assertions here are deliberately about what must STILL be true when an integration is off.
 *
 * `Test_Library_Processor_No_Gate` guards the registration loop itself. This guards the switch.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.42
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Integration_Access;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Integration_Settings;
use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\AcrossAI_Toolset_Integrations;
use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\Rank_Math;
use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\AcrossAI_Catch_All_Integration;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Group;

/**
 * Refusal behaviour of the per-integration switch.
 */
class Test_Integration_Access extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		AcrossAI_Toolset_Integrations::flush();
		AcrossAI_Integration_Access::instance()->flush();

		$GLOBALS['acrossai_test_filter_values']['acrossai_toolset_integrations'] = array(
			new Rank_Math(),
			new AcrossAI_Catch_All_Integration(),
		);

		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array() );

		$GLOBALS['acrossai_test_abilities'] = array();

		// The switch reads an ability's group off the ability itself, so each needs to exist.
		$this->given_ability( 'rank-math/get-settings', 'rank-math' );
		$this->given_ability( 'content/create-post', 'content' );
		$this->given_ability( 'blocks/get-post-blocks', 'blocks' );
		$this->given_ability( 'appearance/anything', 'appearance' );
		$this->given_ability( 'some-unknown-plugin/do-a-thing', AcrossAI_Toolset_Integrations::CATCH_ALL_GROUP );
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		AcrossAI_Toolset_Integrations::flush();
		AcrossAI_Integration_Access::instance()->flush();
		AcrossAI_Ability_Group::flush();

		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array() );

		unset( $GLOBALS['acrossai_test_filter_values'], $GLOBALS['acrossai_test_abilities'] );

		parent::tearDown();
	}

	/**
	 * Register a stub ability carrying a group, the way the group reader expects to find one.
	 *
	 * @param  string $name  Ability name.
	 * @param  string $group Its `meta.acrossai.tab_group`.
	 * @return void
	 */
	private function given_ability( string $name, string $group ): void {
		$GLOBALS['acrossai_test_abilities'][ $name ] = new WP_Ability(
			$name,
			array(
				'label'    => $name,
				'category' => 'acrossai-content',
				'meta'     => array(
					'mcp'      => array( 'type' => 'tool' ),
					'acrossai' => array( 'tab_group' => $group ),
				),
			)
		);

		AcrossAI_Ability_Group::flush();
	}

	/**
	 * A file's source with comments removed.
	 *
	 * The class explains the Feature 102 decision in its docblock, which means it names the very
	 * functions this test forbids. Asserting against raw source would therefore fail on the
	 * explanation rather than on any code — so the comments come out first.
	 *
	 * @param  string $path File path.
	 * @return string
	 */
	private static function code_only( string $path ): string {
		$code = '';

		foreach ( token_get_all( (string) file_get_contents( $path ) ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}

			$code .= is_array( $token ) ? $token[1] : $token;
		}

		return $code;
	}

	/**
	 * Store a decision for one integration and forget any memoised answer.
	 *
	 * @param  string $slug    Integration slug.
	 * @param  bool   $enabled Whether it is on.
	 * @return void
	 */
	private function set_integration( string $slug, bool $enabled ): void {
		$stored          = (array) get_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array() );
		$stored[ $slug ] = $enabled;

		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, $stored );

		AcrossAI_Integration_Access::instance()->flush();
	}

	/**
	 * The refusal reads the switch.
	 *
	 * @return void
	 */
	public function test_an_ability_of_a_disabled_integration_is_refused(): void {
		$this->set_integration( 'rank-math', false );

		$this->assertTrue(
			AcrossAI_Integration_Access::instance()->refuse_disabled_integration( false, 'rank-math/get-settings' )
		);
	}

	/**
	 * An enabled integration is not touched.
	 *
	 * @return void
	 */
	public function test_an_ability_of_an_enabled_integration_is_not_refused(): void {
		$this->set_integration( 'rank-math', true );

		$this->assertFalse(
			AcrossAI_Integration_Access::instance()->refuse_disabled_integration( false, 'rank-math/get-settings' )
		);
	}

	/**
	 * Our own groups have no switch and must never be refused by this.
	 *
	 * `content`, `blocks` and `appearance` are not integrations. If the switch could reach them, one
	 * absent option key would take the whole plugin offline.
	 *
	 * @return void
	 */
	public function test_a_first_party_ability_is_never_refused(): void {
		foreach ( array( 'content/create-post', 'blocks/get-post-blocks', 'appearance/anything' ) as $slug ) {
			$this->assertFalse(
				AcrossAI_Integration_Access::instance()->refuse_disabled_integration( false, $slug ),
				$slug . ' belongs to no integration and must be out of this switch\'s reach.'
			);
		}
	}

	/**
	 * Abilities in the catch-all are never refused either.
	 *
	 * `other` holds plugins we deliberately do not adopt. They have no row, so there is nothing to
	 * read, and refusing them would disable a third party's abilities on a decision nobody made.
	 *
	 * @return void
	 */
	public function test_a_catch_all_ability_is_never_refused(): void {
		$this->assertFalse(
			AcrossAI_Integration_Access::instance()->refuse_disabled_integration( false, 'some-unknown-plugin/do-a-thing' )
		);
	}

	/**
	 * Deny-only: an existing refusal is never overturned.
	 *
	 * The filter runs after access has already been granted, and is documented as only able to turn
	 * an allow into a deny. Returning false here for an enabled integration would widen access that
	 * something else had already closed.
	 *
	 * @return void
	 */
	public function test_an_existing_refusal_is_never_overturned(): void {
		$this->set_integration( 'rank-math', true );

		$this->assertTrue(
			AcrossAI_Integration_Access::instance()->refuse_disabled_integration( true, 'rank-math/get-settings' ),
			'An enabled integration must not re-allow an ability another callback refused.'
		);

		$this->assertTrue(
			AcrossAI_Integration_Access::instance()->refuse_disabled_integration( true, 'content/create-post' ),
			'A first-party ability must not be re-allowed either.'
		);
	}

	/**
	 * The switch is a refusal, not a registration gate.
	 *
	 * Asserted against the source because the shape is the thing being protected: this class must
	 * reach the registry only to ask what group an ability is in, and must never unregister or skip
	 * a registration. If someone "simplifies" it into a gate, this fails.
	 *
	 * @return void
	 */
	public function test_the_switch_never_prevents_registration(): void {
		$source = self::code_only(
			dirname( __DIR__, 4 ) . '/includes/Modules/Library/AcrossAI_Integration_Access.php'
		);

		$this->assertStringContainsString(
			"add_filter( 'acrossai_ability_access_refused'",
			$source,
			'The switch must act through the access filter — the one gate every surface passes through.'
		);

		foreach ( array( 'wp_unregister_ability', 'wp_register_ability', 'wp_abilities_api_init' ) as $forbidden ) {
			$this->assertStringNotContainsString(
				$forbidden,
				$source,
				'Feature 102: an access control must not touch registration. Found ' . $forbidden . '.'
			);
		}
	}
}
