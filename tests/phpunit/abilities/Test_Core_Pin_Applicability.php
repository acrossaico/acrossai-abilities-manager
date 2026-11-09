<?php
/**
 * The contract that makes a WP Downgrade pin actually applicable.
 *
 * Every assertion here traces to something measured on a pinned site, because the whole feature
 * was unreachable and every unit test passed anyway. With a 7.0.8 pin on a 7.1.4 site:
 *
 * - the offer read `version 7.1.4`, `response latest`, `current 7.0.8`,
 *   `packages->full wordpress-7.0.8.zip` — WP Downgrade rewrites the package and `current`, and
 *   never touches `version` or `response`;
 * - `core/update-wp-core` refused with "Core offer is not upgradable (response=latest)", and
 *   past that, `Core_Upgrader` itself refuses `latest` at class-core-upgrader.php:85;
 * - `core/reinstall-wp-core` refused with a message blaming a stale transient, which was not the
 *   cause — find_core_update() matches on `current`, which the pin had rewritten;
 * - `versions/clear-core-pin` rejected `confirm: true` outright, so the escape hatch refused the
 *   flag `set-core-pin` had just required.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.45
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Core/Update_Wp_Core.php';
require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Core/Reinstall_Wp_Core.php';
require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Versions/Clear_Core_Pin.php';
require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Versions/Set_Core_Pin.php';

final class Test_Core_Pin_Applicability extends TestCase {

	private const UPDATE    = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Core\\Update_Wp_Core';
	private const REINSTALL = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Core\\Reinstall_Wp_Core';
	private const SET_PIN   = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Versions\\Set_Core_Pin';
	private const CLEAR_PIN = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Versions\\Clear_Core_Pin';

	/**
	 * @param string $class Ability class.
	 * @return array<string,mixed>
	 */
	private function spec( string $class ): array {
		$object = new $class();
		$method = new ReflectionMethod( $object, 'ability' );
		$method->setAccessible( true );

		return (array) $method->invoke( $object );
	}

	/**
	 * @param string $class  Ability class.
	 * @param string $method Protected method.
	 * @return mixed
	 */
	private function call( string $class, string $method ) {
		$object = new $class();
		$ref    = new ReflectionMethod( $object, $method );
		$ref->setAccessible( true );

		return $ref->invoke( $object );
	}

	/* ------------------------------------------------------------------ */
	/* update-wp-core can now apply a pin                                  */
	/* ------------------------------------------------------------------ */

	public function test_update_accepts_a_confirm_flag(): void {
		// Needed only when the resolved target is older than the running version, which a pin or
		// an explicit version can make it. Without this the downgrade path had no gate at all.
		$properties = $this->spec( self::UPDATE )['args']['input_schema']['properties'];

		$this->assertArrayHasKey( 'confirm', $properties );
		$this->assertFalse( $properties['confirm']['default'] );
	}

	public function test_update_never_schema_requires_confirm(): void {
		// Core validates input_schema before execute() runs, so a schema-required flag surfaces as
		// a generic ability_invalid_input and the caller never learns which flag or why. And a
		// normal forward upgrade must not need it at all.
		$this->assertNotContains(
			'confirm',
			$this->spec( self::UPDATE )['args']['input_schema']['required'] ?? array()
		);
	}

	public function test_update_declares_every_field_the_downgrade_path_returns(): void {
		$properties = $this->spec( self::UPDATE )['args']['output_schema']['properties'];

		foreach ( array( 'target_version', 'php_version', 'compatibility', 'warnings', 'error_code', 'blocked_reason' ) as $key ) {
			$this->assertArrayHasKey( $key, $properties, $key . ' is returned but not declared' );
		}
	}

	public function test_update_description_names_the_pin_it_applies(): void {
		// set-core-pin points the caller here. If this description does not mention the pin, an
		// agent reading only this ability has no reason to believe it is the right next call.
		$this->assertStringContainsString( 'set-core-pin', $this->spec( self::UPDATE )['args']['description'] );
	}

	public function test_update_description_states_the_downgrade_guard(): void {
		$description = $this->spec( self::UPDATE )['args']['description'];

		$this->assertStringContainsString( 'confirm: true', $description );
		$this->assertStringContainsString( 'older', $description );
	}

	/* ------------------------------------------------------------------ */
	/* reinstall refuses under a pin, for the real reason                  */
	/* ------------------------------------------------------------------ */

	public function test_reinstall_declares_the_pinned_version_it_reports(): void {
		// additionalProperties is false on this schema, so an undeclared pinned_version would be
		// stripped and the refusal would name no version at all.
		$properties = $this->spec( self::REINSTALL )['args']['output_schema']['properties'];

		$this->assertArrayHasKey( 'pinned_version', $properties );
		$this->assertArrayHasKey( 'error_code', $properties );
	}

	/* ------------------------------------------------------------------ */
	/* clear-core-pin must not reject the flag set-core-pin requires       */
	/* ------------------------------------------------------------------ */

	public function test_clear_pin_accepts_confirm_even_though_it_needs_none(): void {
		// The escape hatch. set-core-pin requires confirm: true; clear-core-pin had
		// additionalProperties false and no confirm property, so passing it was rejected as
		// ability_invalid_input — while the pin it would have removed was actively blocking
		// core/reinstall-wp-core. Hit for real mid-test, which left the site pinned.
		$this->assertArrayHasKey( 'confirm', $this->spec( self::CLEAR_PIN )['args']['input_schema']['properties'] );
	}

	public function test_clear_pin_still_does_not_require_confirmation(): void {
		// Accepted, not demanded: clearing a pin restores WordPress's default behaviour, so
		// gating it would make recovery harder than getting into the state.
		$this->assertFalse( $this->call( self::CLEAR_PIN, 'requires_confirmation' ) );
	}

	public function test_set_pin_still_requires_confirmation(): void {
		$this->assertTrue( $this->call( self::SET_PIN, 'requires_confirmation' ) );
	}

	/* ------------------------------------------------------------------ */
	/* set-core-pin must name a route that works                           */
	/* ------------------------------------------------------------------ */

	public function test_set_pin_description_warns_that_reinstall_cannot_run(): void {
		// Because find_core_update() matches on `current`, which the pin rewrites, there is no
		// offer for the running version while a pin is set. A caller should learn that when
		// setting the pin, not when reinstall fails.
		$this->assertStringContainsString( 'reinstall-wp-core', $this->spec( self::SET_PIN )['args']['description'] );
	}

	public function test_set_pin_description_names_the_confirm_the_next_step_needs(): void {
		// Applying a pin is a downgrade, so update-wp-core asks for confirm. Telling the caller
		// to "run core/update-wp-core" without that sends them into a refusal.
		$description = $this->spec( self::SET_PIN )['args']['description'];

		$this->assertStringContainsString( 'core/update-wp-core', $description );
		$this->assertStringContainsString( 'confirm: true', $description );
	}
}
