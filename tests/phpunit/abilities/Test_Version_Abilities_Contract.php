<?php
/**
 * Feature 136 — the version-targeting suite's registration contract.
 *
 * Drives each ability's own ability() output rather than asserting on source text, so a wrong
 * declaration fails and not merely a missing one.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.45
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Versions/Get_Rollback_Status.php';
require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Versions/List_Asset_Versions.php';
require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Versions/Check_Asset_Rollback.php';

final class Test_Version_Abilities_Contract extends TestCase {

	/**
	 * The assembled spec for one ability.
	 *
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
	 * Read one protected method's return value.
	 *
	 * @param string $class  Ability class.
	 * @param string $method Method name.
	 * @return mixed
	 */
	private function call( string $class, string $method ) {
		$object = new $class();
		$ref    = new ReflectionMethod( $object, $method );
		$ref->setAccessible( true );

		return $ref->invoke( $object );
	}

	/**
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function suite_provider(): array {
		$ns = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Versions\\';

		return array(
			'get-rollback-status'  => array( $ns . 'Get_Rollback_Status', 'versions/get-rollback-status' ),
			'list-asset-versions'  => array( $ns . 'List_Asset_Versions', 'versions/list-asset-versions' ),
			'check-asset-rollback' => array( $ns . 'Check_Asset_Rollback', 'versions/check-asset-rollback' ),
		);
	}

	/**
	 * @dataProvider suite_provider
	 * @param string $class Ability class.
	 * @param string $name  Expected ability name.
	 */
	public function test_each_ability_registers_under_the_versions_namespace( string $class, string $name ): void {
		$this->assertSame( $name, $this->spec( $class )['name'] );
	}

	/**
	 * @dataProvider suite_provider
	 * @param string $class Ability class.
	 */
	public function test_each_claims_the_versions_category( string $class ): void {
		// The category is registered unconditionally in the bootstrap. If an ability claimed a
		// category that does not exist, WordPress would reject it with no runtime signal — the
		// failure that lost all 17 backup abilities in #258.
		$this->assertSame( 'acrossai-versions', $this->spec( $class )['args']['category'] );
	}

	/**
	 * @dataProvider suite_provider
	 * @param string $class Ability class.
	 */
	public function test_each_lands_in_the_wp_rollback_toolset( string $class ): void {
		$meta = $this->spec( $class )['args']['meta']['acrossai'];

		// Must equal Integrations\Wp_Rollback::TAB_GROUP. If the two drift, the abilities land in
		// one group and the generated dispatcher serves another.
		$this->assertSame( 'wp-rollback', $meta['tab_group'] );
		$this->assertSame( 'versions-read', $meta['sub_group'] );
	}

	/**
	 * @dataProvider suite_provider
	 * @param string $class Ability class.
	 */
	public function test_all_three_are_read_only( string $class ): void {
		$annotations = $this->spec( $class )['args']['meta']['annotations'];

		$this->assertTrue( $annotations['readonly'] );
		$this->assertFalse( $annotations['destructive'] );
		$this->assertTrue( $annotations['idempotent'] );
	}

	/**
	 * @dataProvider suite_provider
	 * @param string $class Ability class.
	 */
	public function test_none_of_the_reads_require_the_host_plugin( string $class ): void {
		// The design decision this suite rests on. Version history is plugins_api()/themes_api(),
		// which is WordPress core — gating a read on a plugin it does not use would be a
		// restriction invented by this code rather than one the problem has. Verified live: with
		// WP Rollback deactivated, both reads still answered.
		$this->assertFalse( $this->call( $class, 'requires_host' ), $class . ' must not require WP Rollback.' );
	}

	/**
	 * @dataProvider suite_provider
	 * @param string $class Ability class.
	 */
	public function test_each_reports_whether_the_host_plugin_was_present( string $class ): void {
		// Every response says so, so a refusal is never ambiguous about whether installing the
		// plugin would have helped.
		$properties = $this->spec( $class )['args']['output_schema']['properties'];

		$this->assertArrayHasKey( 'wp_rollback_active', $properties );
		$this->assertSame( 'boolean', $properties['wp_rollback_active']['type'] );
	}

	/**
	 * @dataProvider suite_provider
	 * @param string $class Ability class.
	 */
	public function test_schemas_are_closed( string $class ): void {
		$args = $this->spec( $class )['args'];

		$this->assertFalse( $args['input_schema']['additionalProperties'] );
		$this->assertFalse( $args['output_schema']['additionalProperties'] );
	}

	/**
	 * @dataProvider suite_provider
	 * @param string $class Ability class.
	 */
	public function test_every_returned_key_is_declared( string $class ): void {
		// additionalProperties is false, so a key the ability returns but does not declare is
		// stripped by output validation and the caller silently loses it — the trap that cost two
		// earlier features (#294's redaction key, #302's breakdown).
		$properties = array_keys( $this->spec( $class )['args']['output_schema']['properties'] );

		foreach ( array( 'success', 'message', 'error_code', 'blocked_reason' ) as $key ) {
			$this->assertContains( $key, $properties, $key . ' must be declared on ' . $class );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Asset-type inputs stay consistent with the rest of the suite        */
	/* ------------------------------------------------------------------ */

	public function test_the_asset_parameters_use_the_established_names(): void {
		// The existing family is already inconsistent — plugin vs slugs, theme vs stylesheet vs
		// stylesheets — and three wrong guesses in one test sweep came of it. This suite uses the
		// singular `plugin` and `stylesheet` and must not add a fourth spelling.
		$ns = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Versions\\';

		foreach ( array( 'List_Asset_Versions', 'Check_Asset_Rollback' ) as $class ) {
			$properties = $this->spec( $ns . $class )['args']['input_schema']['properties'];

			$this->assertArrayHasKey( 'plugin', $properties, $class );
			$this->assertArrayHasKey( 'stylesheet', $properties, $class );
			$this->assertArrayNotHasKey( 'slugs', $properties, $class );
			$this->assertArrayNotHasKey( 'theme', $properties, $class );
			$this->assertArrayNotHasKey( 'stylesheets', $properties, $class );
		}
	}

	public function test_the_type_enum_excludes_core(): void {
		// WP Rollback's own route enum is ['plugin','theme'] and it ships no CoreRollback
		// subsystem. Accepting 'core' here would take a request it cannot fulfil.
		$ns = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Versions\\';

		foreach ( array( 'List_Asset_Versions', 'Check_Asset_Rollback' ) as $class ) {
			$enum = $this->spec( $ns . $class )['args']['input_schema']['properties']['type']['enum'];

			$this->assertSame( array( 'plugin', 'theme' ), $enum, $class );
			$this->assertNotContains( 'core', $enum, $class );
		}
	}

	public function test_status_says_plainly_that_core_is_not_covered(): void {
		$ns = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Versions\\';

		$this->assertStringContainsString(
			'never WordPress core',
			$this->spec( $ns . 'Get_Rollback_Status' )['args']['description'],
			'An agent reading only the description must learn core is out of scope.'
		);
	}
}
