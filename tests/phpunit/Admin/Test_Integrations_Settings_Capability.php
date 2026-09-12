<?php
/**
 * Feature 102 — the capability gate on the relocated integration opt-ins.
 *
 * `acrossai_integration_toggle_capability` was part of the retired Ability Integrations page and
 * moved here with the switch. It exists so a site can *raise* the bar — require
 * `manage_network_options`, say — and the relocation must not turn it into a way to lower one.
 * Losing that property would widen who can ask a third-party plugin to register its abilities
 * (SEC-003); see PATTERN-FILTERABLE-CAPABILITY-RAISE-ONLY.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.34
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Admin\Partials\Integrations_Settings_Menu;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Integration_Settings;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Registry;

require_once dirname( __DIR__, 3 ) . '/includes/Modules/Library/AcrossAI_Ability_Library_Registry.php';
require_once dirname( __DIR__, 3 ) . '/includes/Modules/Library/AcrossAI_Integration_Settings.php';
require_once dirname( __DIR__, 3 ) . '/admin/Partials/Integrations_Settings_Menu.php';

/**
 * Covers Integrations_Settings_Menu::sanitize_option() authorization.
 */
class Test_Integrations_Settings_Capability extends TestCase {

	/**
	 * Reset the fixtures every test so capability state never leaks between cases.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['acrossai_test_capabilities']    = array();
		$GLOBALS['acrossai_test_filter_values']   = array();
		$GLOBALS['acrossai_test_settings_errors'] = array();

		acrossai_test_site_options( array() );

		// One discoverable integration. discover() reads the registry's cached definitions, which
		// are private and static, so the fixture goes in through Reflection rather than by firing
		// the collection filter — no hook registry exists in the unit bootstrap.
		$this->seed_definitions(
			array(
				array(
					'category'       => 'acf',
					'category_label' => 'ACF',
					'slug'           => 'acf-field',
					'name'           => 'acf/get-field',
					'card_variant'   => 'integration',
				),
			)
		);
	}

	/**
	 * Clear the injected definitions so other suites see a pristine registry.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->seed_definitions( null );

		unset(
			$GLOBALS['acrossai_test_capabilities'],
			$GLOBALS['acrossai_test_filter_values'],
			$GLOBALS['acrossai_test_settings_errors']
		);

		parent::tearDown();
	}

	/**
	 * Write the registry's private static definition cache.
	 *
	 * @param  array<int, array<string, mixed>>|null $definitions Rows, or null to clear.
	 * @return void
	 */
	private function seed_definitions( ?array $definitions ): void {
		$property = new \ReflectionProperty( AcrossAI_Ability_Library_Registry::class, 'definitions' );
		$property->setAccessible( true );
		$property->setValue( null, $definitions );
	}

	/**
	 * Run the sanitizer.
	 *
	 * @param  array<string, mixed> $submitted Posted checkbox values.
	 * @return array<string, bool>
	 */
	private function save( array $submitted ): array {
		return Integrations_Settings_Menu::instance()->sanitize_option( $submitted );
	}

	/**
	 * A filter returning a weaker capability must not grant a subscriber access.
	 *
	 * This is the SEC-003 case: the subscriber holds `read` and the filter asks for `read`, so the
	 * filtered check alone would pass. The unconditional `manage_options` floor is what refuses it.
	 *
	 * @return void
	 */
	public function test_weak_filter_capability_does_not_let_a_subscriber_enable_an_integration(): void {
		$GLOBALS['acrossai_test_capabilities']                                            = array( 'read' );
		$GLOBALS['acrossai_test_filter_values']['acrossai_integration_toggle_capability'] = 'read';

		$clean = $this->save( array( 'acf' => '1' ) );

		$this->assertSame(
			array(),
			$clean,
			'A user below the floor must not be able to write an opt-in, whatever the filter returns.'
		);
		$this->assertFalse(
			AcrossAI_Integration_Settings::is_enabled( 'acf' ),
			'The integration must stay off.'
		);
	}

	/**
	 * The same weak filter must not let a subscriber switch an enabled integration off, either.
	 *
	 * Denial has to be symmetric — a one-directional gate would still hand an unprivileged user a
	 * way to change what the site exposes.
	 *
	 * @return void
	 */
	public function test_weak_filter_capability_does_not_let_a_subscriber_disable_an_integration(): void {
		acrossai_test_site_options(
			array(
				AcrossAI_Integration_Settings::OPTION_KEY => array( 'acf' => true ),
			)
		);

		$GLOBALS['acrossai_test_capabilities']                                            = array( 'read' );
		$GLOBALS['acrossai_test_filter_values']['acrossai_integration_toggle_capability'] = 'read';

		$clean = $this->save( array() );

		$this->assertSame(
			array( 'acf' => true ),
			$clean,
			'The stored value must come back untouched.'
		);
	}

	/**
	 * An administrator with the floor capability can still toggle, so the gate is not just "off".
	 *
	 * @return void
	 */
	public function test_administrator_can_enable_an_integration(): void {
		$GLOBALS['acrossai_test_capabilities'] = array( 'read', 'manage_options' );

		$clean = $this->save( array( 'acf' => '1' ) );

		$this->assertSame( array( 'acf' => true ), $clean );
	}

	/**
	 * A filter raising the bar above the floor still blocks a plain administrator.
	 *
	 * The floor is a minimum, not the whole check — the filtered capability is evaluated on its own
	 * afterwards, never OR-ed with the default.
	 *
	 * @return void
	 */
	public function test_filter_can_still_raise_the_required_capability(): void {
		$GLOBALS['acrossai_test_capabilities']                                            = array( 'manage_options' );
		$GLOBALS['acrossai_test_filter_values']['acrossai_integration_toggle_capability'] = 'manage_network_options';

		$clean = $this->save( array( 'acf' => '1' ) );

		$this->assertSame(
			array( 'acf' => false ),
			$clean,
			'An administrator without the raised capability must be refused.'
		);
		$this->assertNotEmpty(
			$GLOBALS['acrossai_test_settings_errors'],
			'The refusal must be surfaced to the operator rather than failing silently.'
		);
	}

	/**
	 * An empty capability from a broken filter fails closed.
	 *
	 * @return void
	 */
	public function test_empty_filter_capability_fails_closed(): void {
		$GLOBALS['acrossai_test_capabilities']                                            = array( 'manage_options' );
		$GLOBALS['acrossai_test_filter_values']['acrossai_integration_toggle_capability'] = '';

		$this->assertSame( array( 'acf' => false ), $this->save( array( 'acf' => '1' ) ) );
	}
}
