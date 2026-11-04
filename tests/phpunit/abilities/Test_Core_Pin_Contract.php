<?php
/**
 * Feature 136 — the core version-pinning suite.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.45
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\WpDowngrade\Wp_Downgrade_Guard;

require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/WpDowngrade/Wp_Downgrade_Guard.php';
require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Versions/Get_Core_Pin.php';
require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Versions/Set_Core_Pin.php';
require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Versions/Clear_Core_Pin.php';

final class Test_Core_Pin_Contract extends TestCase {

	/**
	 * @param string $class Ability class.
	 * @return array<string,mixed>
	 */
	private function spec( string $class ): array {
		$o = new $class();
		$m = new ReflectionMethod( $o, 'ability' );
		$m->setAccessible( true );

		return (array) $m->invoke( $o );
	}

	/**
	 * @param string $class  Ability class.
	 * @param string $method Protected method.
	 * @return mixed
	 */
	private function call( string $class, string $method ) {
		$o = new $class();
		$m = new ReflectionMethod( $o, $method );
		$m->setAccessible( true );

		return $m->invoke( $o );
	}

	/**
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function suite_provider(): array {
		$ns = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Versions\\';

		return array(
			'get-core-pin'   => array( $ns . 'Get_Core_Pin', 'versions/get-core-pin' ),
			'set-core-pin'   => array( $ns . 'Set_Core_Pin', 'versions/set-core-pin' ),
			'clear-core-pin' => array( $ns . 'Clear_Core_Pin', 'versions/clear-core-pin' ),
		);
	}

	/**
	 * @dataProvider suite_provider
	 * @param string $class Ability class.
	 * @param string $name  Expected name.
	 */
	public function test_registers_under_versions_with_the_shared_category( string $class, string $name ): void {
		$spec = $this->spec( $class );

		$this->assertSame( $name, $spec['name'] );
		$this->assertSame( 'acrossai-versions', $spec['args']['category'] );
	}

	/**
	 * @dataProvider suite_provider
	 * @param string $class Ability class.
	 */
	public function test_lands_in_the_wp_downgrade_toolset( string $class ): void {
		$meta = $this->spec( $class )['args']['meta']['acrossai'];

		// Must equal Integrations\Wp_Downgrade::TAB_GROUP, and must NOT be the rollback group — the
		// two host plugins cannot do each other's job.
		$this->assertSame( 'wp-downgrade', $meta['tab_group'] );
		$this->assertNotSame( 'wp-rollback', $meta['tab_group'] );
		$this->assertSame( 'versions-core-pin', $meta['sub_group'] );
	}

	/* ------------------------------------------------------------------ */
	/* Regression: the base guarded the WRONG host plugin                 */
	/* ------------------------------------------------------------------ */

	/**
	 * @dataProvider suite_provider
	 * @param string $class Ability class.
	 */
	public function test_the_host_reported_is_wp_downgrade_not_wp_rollback( string $class ): void {
		// Base_Version_Ability originally hardcoded the WP Rollback guard, so set-core-pin refused
		// with "WP Rollback is not active" — naming a plugin with nothing to do with pinning core
		// and sending the caller to install the wrong thing. Caught by running it.
		$this->assertSame( 'wp_downgrade_active', $this->call( $class, 'host_active_key' ) );

		$properties = $this->spec( $class )['args']['output_schema']['properties'];

		$this->assertArrayHasKey( 'wp_downgrade_active', $properties );
		$this->assertArrayNotHasKey( 'wp_rollback_active', $properties, 'A pinning response must not carry a WP Rollback field.' );
	}

	public function test_the_writes_require_the_host_and_the_read_does_not(): void {
		$ns = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Versions\\';

		$this->assertFalse( $this->call( $ns . 'Get_Core_Pin', 'requires_host' ), 'Reporting there is no pin cannot require the plugin.' );
		$this->assertTrue( $this->call( $ns . 'Set_Core_Pin', 'requires_host' ) );
		$this->assertTrue( $this->call( $ns . 'Clear_Core_Pin', 'requires_host' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Regression: the confirm prompt described the wrong operation       */
	/* ------------------------------------------------------------------ */

	public function test_set_core_pin_is_confirm_gated(): void {
		$ns = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Versions\\';

		$this->assertTrue( $this->call( $ns . 'Set_Core_Pin', 'requires_confirmation' ) );

		// confirm must never be schema-required: core validates input_schema before execute() runs,
		// so a required confirm fails as a generic ability_invalid_input and the caller never sees
		// confirmation_required or the message naming the flag.
		$schema = $this->spec( $ns . 'Set_Core_Pin' )['args']['input_schema'];

		$this->assertArrayHasKey( 'confirm', $schema['properties'] );
		$this->assertNotContains( 'confirm', $schema['required'] );
	}

	public function test_the_confirm_prompt_does_not_claim_files_are_replaced(): void {
		// The inherited default said "replaces files on disk", which is true of a rollback and
		// false of a pin — it writes one option. A prompt that misdescribes the operation trains a
		// caller to pass confirm without reading.
		$ns      = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Versions\\';
		$message = (string) $this->call( $ns . 'Set_Core_Pin', 'confirmation_message' );

		$this->assertStringNotContainsString( 'replaces files on disk', $message );
		$this->assertStringContainsString( 'update checks offer', $message );
		$this->assertStringContainsString( 'clear-core-pin', $message, 'The prompt should say how to undo it.' );
	}

	public function test_clear_is_not_confirm_gated(): void {
		// Restoring WordPress's default behaviour should not need ceremony; the pin is the
		// committing act.
		$ns = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Versions\\';

		$this->assertFalse( $this->call( $ns . 'Clear_Core_Pin', 'requires_confirmation' ) );
	}

	/* ------------------------------------------------------------------ */
	/* The host plugin's own validation, which rejects a real version     */
	/* ------------------------------------------------------------------ */

	/**
	 * @return array<string,array{0:string,1:bool}>
	 */
	public static function host_ui_provider(): array {
		return array(
			'7.1.4 accepted'            => array( '7.1.4', true ),
			'7.1 accepted'              => array( '7.1', true ),
			'7.1.10 accepted'           => array( '7.1.10', true ),
			'10.2.3 accepted'           => array( '10.2.3', true ),
			// The finding: WP Downgrade's own sanitiser is
			// /^[-+]?[0-9]*[.]?[0-9]?[.]?[0-9]+$/ and the middle group allows a SINGLE digit, so a
			// legitimate WordPress version is rejected by its settings form.
			'6.10.2 rejected by theirs' => array( '6.10.2', false ),
		);
	}

	/**
	 * @dataProvider host_ui_provider
	 * @param string $version  Version.
	 * @param bool   $accepted Whether their form would accept it.
	 */
	public function test_we_can_tell_when_the_host_ui_would_reject_a_version( string $version, bool $accepted ): void {
		$this->assertSame( $accepted, Wp_Downgrade_Guard::host_ui_accepts( $version ) );
	}

	public function test_option_names_match_the_released_plugin(): void {
		// Read from wp-downgrade 1.2.9's register_setting() calls. If these drift the pin silently
		// stops working, because the plugin reads its own names.
		$this->assertSame( 'wpdg_specific_version_name', Wp_Downgrade_Guard::PIN_OPTION );
		$this->assertSame( 'wpdg_download_url', Wp_Downgrade_Guard::CUSTOM_URL_OPTION );
		$this->assertSame( 'wpdg_edit_download_url', Wp_Downgrade_Guard::CUSTOM_URL_TOGGLE );
	}

	public function test_set_declares_that_core_did_not_move(): void {
		// The single most important field in the response. Without it a caller reads a success as
		// "core is now on that version", which is false — WP Downgrade only changes what update
		// checks offer.
		$ns         = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Versions\\';
		$properties = $this->spec( $ns . 'Set_Core_Pin' )['args']['output_schema']['properties'];

		$this->assertArrayHasKey( 'core_moved', $properties );
		$this->assertArrayHasKey( 'next_step', $properties );
		$this->assertStringContainsString(
			'does not move core',
			$this->spec( $ns . 'Set_Core_Pin' )['args']['description'],
			'An agent reading only the description must learn the pin does not apply itself.'
		);
	}
}
