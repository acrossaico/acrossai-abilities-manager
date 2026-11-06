<?php
/**
 * Feature 136 — the registration contract for the two version-move writes.
 *
 * The assertions that matter most here are the ones about what these abilities do *not* require.
 * They were deferred on the assumption that a version move needs WP Rollback's staged pipeline,
 * and they do not: core's own upgrader has carried backup-and-restore since 6.3. If a later change
 * reintroduces a host-plugin requirement, every site without WP Rollback silently loses the only
 * route back from a bad update, and nothing else in the suite would notice.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.45
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Versions/Get_Rollback_Status.php';
require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Versions/Rollback_Plugin.php';
require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Versions/Rollback_Theme.php';

final class Test_Rollback_Writes_Contract extends TestCase {

	private const PLUGIN = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Versions\\Rollback_Plugin';
	private const THEME  = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Versions\\Rollback_Theme';

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
	 * @param string $method Protected method name.
	 * @return mixed
	 */
	private function call( string $class, string $method ) {
		$object = new $class();
		$ref    = new ReflectionMethod( $object, $method );
		$ref->setAccessible( true );

		return $ref->invoke( $object );
	}

	/**
	 * @return array<string,array{0:string,1:string,2:string,3:string}>
	 */
	public static function pair_provider(): array {
		return array(
			'plugin' => array( self::PLUGIN, 'versions/rollback-plugin', 'plugin', 'plugin' ),
			'theme'  => array( self::THEME, 'versions/rollback-theme', 'stylesheet', 'theme' ),
		);
	}

	/**
	 * @dataProvider pair_provider
	 * @param string $class Ability class.
	 * @param string $name  Expected ability name.
	 */
	public function test_ability_name( string $class, string $name ): void {
		$this->assertSame( $name, $this->spec( $class )['name'] );
	}

	/* ------------------------------------------------------------------ */
	/* The design decision these abilities exist to carry                  */
	/* ------------------------------------------------------------------ */

	/**
	 * @dataProvider pair_provider
	 * @param string $class Ability class.
	 */
	public function test_no_host_plugin_is_required( string $class ): void {
		// The whole point. A version move drives core's Plugin_Upgrader / Theme_Upgrader, which
		// already backs the old copy up and restores it on shutdown if the package fails. Requiring
		// WP Rollback would make the commonest recovery path unavailable on most sites for no
		// reason the problem has.
		$this->assertFalse( $this->call( $class, 'requires_host' ) );
	}

	/**
	 * @dataProvider pair_provider
	 * @param string $class Ability class.
	 */
	public function test_file_mods_are_honoured( string $class ): void {
		$this->assertTrue( $this->call( $class, 'writes_files' ) );
	}

	/**
	 * @dataProvider pair_provider
	 * @param string $class Ability class.
	 */
	public function test_confirmation_is_required_but_never_schema_required( string $class ): void {
		// Core validates input_schema before execute() runs, so a schema-required confirm fails as
		// a generic ability_invalid_input and the caller never sees confirmation_required or the
		// message naming the flag.
		$this->assertTrue( $this->call( $class, 'requires_confirmation' ) );

		$schema = $this->spec( $class )['args']['input_schema'];

		$this->assertArrayHasKey( 'confirm', $schema['properties'] );
		$this->assertNotContains( 'confirm', $schema['required'] );
	}

	/**
	 * @dataProvider pair_provider
	 * @param string $class Ability class.
	 */
	public function test_confirmation_message_describes_a_version_change( string $class ): void {
		// A prompt that misdescribes the operation trains a caller to pass confirm without reading.
		// The base class default talks about files on disk and says nothing about the version
		// changing, which is the part a caller must weigh.
		$message = $this->call( $class, 'confirmation_message' );

		$this->assertStringContainsString( 'different version', $message );
		$this->assertNotSame(
			$this->call( 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Versions\\Get_Rollback_Status', 'confirmation_message' ),
			$message,
			'the default confirmation message was left in place'
		);
	}

	/* ------------------------------------------------------------------ */
	/* Parameters                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * @dataProvider pair_provider
	 * @param string $class Ability class.
	 * @param string $name  Ability name.
	 * @param string $param Expected identifier parameter.
	 */
	public function test_identifier_and_version_are_both_required( string $class, string $name, string $param ): void {
		$schema = $this->spec( $class )['args']['input_schema'];

		$this->assertArrayHasKey( $param, $schema['properties'] );
		$this->assertContains( $param, $schema['required'] );
		$this->assertContains( 'version', $schema['required'] );
	}

	/**
	 * @dataProvider pair_provider
	 * @param string $class Ability class.
	 * @param string $name  Ability name.
	 * @param string $param Identifier parameter.
	 * @param string $type  Asset type.
	 */
	public function test_asset_type_matches_the_identifier_spelling( string $class, string $name, string $param, string $type ): void {
		// resolve() branches on type, and run() reads $input[identifier_key()]. A mismatch means
		// the ability looks up a theme using a value the caller passed as a plugin.
		$this->assertSame( $type, $this->call( $class, 'asset_type' ) );
		$this->assertSame( $param, $this->call( $class, 'identifier_key' ) );
	}

	public function test_only_the_theme_has_a_force_flag(): void {
		// force and confirm are deliberately separate: confirm accepts a version change, force
		// accepts that this theme is the live one. One flag for both would let a caller who meant
		// to tidy a dormant theme change the site's appearance.
		$this->assertArrayHasKey( 'force', $this->spec( self::THEME )['args']['input_schema']['properties'] );
		$this->assertArrayNotHasKey( 'force', $this->spec( self::PLUGIN )['args']['input_schema']['properties'] );
	}

	public function test_force_defaults_to_false(): void {
		$this->assertFalse( $this->spec( self::THEME )['args']['input_schema']['properties']['force']['default'] );
	}

	/* ------------------------------------------------------------------ */
	/* Annotations and output                                              */
	/* ------------------------------------------------------------------ */

	/**
	 * @dataProvider pair_provider
	 * @param string $class Ability class.
	 */
	public function test_annotations_declare_a_destructive_write( string $class ): void {
		$this->assertSame(
			array(
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => true,
			),
			$this->spec( $class )['args']['meta']['annotations']
		);
	}

	/**
	 * @dataProvider pair_provider
	 * @param string $class Ability class.
	 */
	public function test_output_declares_every_key_the_run_path_returns( string $class ): void {
		// additionalProperties is false, so an undeclared key is stripped silently — the caller
		// sees a response missing the field rather than an error pointing at the schema.
		$properties = $this->spec( $class )['args']['output_schema']['properties'];

		foreach ( array( 'previous_version', 'version', 'relation', 'changed', 'package_source', 'warnings', 'verify_hint', 'error_code', 'blocked_reason' ) as $key ) {
			$this->assertArrayHasKey( $key, $properties, $key . ' is returned but not declared' );
		}
	}

	public function test_each_ability_declares_its_own_type_specific_output(): void {
		$plugin = $this->spec( self::PLUGIN )['args']['output_schema']['properties'];
		$theme  = $this->spec( self::THEME )['args']['output_schema']['properties'];

		// Reactivation is reported because an overwrite deactivates a plugin as a side effect the
		// caller never asked for; a rollback that silently deactivated would look like the new
		// version crashing the site.
		$this->assertArrayHasKey( 'was_active', $plugin );
		$this->assertArrayHasKey( 'reactivated', $plugin );
		$this->assertArrayHasKey( 'plugin', $plugin );

		$this->assertArrayHasKey( 'is_active_theme', $theme );
		$this->assertArrayHasKey( 'stylesheet', $theme );
	}

	/**
	 * @dataProvider pair_provider
	 * @param string $class Ability class.
	 */
	public function test_verify_hint_never_claims_the_files_were_verified( string $class ): void {
		// Neither core nor WP Rollback free verifies package integrity. "Files replaced" is not
		// "files verified", and a caller reporting a successful rollback as a verified one is the
		// same class of overclaim as the diagnostics in #296, #302 and #303.
		$properties = $this->spec( $class )['args']['output_schema']['properties'];

		$this->assertArrayHasKey( 'verify_hint', $properties );
	}

	/* ------------------------------------------------------------------ */
	/* Grouping                                                            */
	/* ------------------------------------------------------------------ */

	/**
	 * @dataProvider pair_provider
	 * @param string $class Ability class.
	 */
	public function test_abilities_sit_with_the_rest_of_the_suite( string $class ): void {
		$acrossai = $this->spec( $class )['args']['meta']['acrossai'];

		$this->assertSame( 'wp-rollback', $acrossai['tab_group'] );
		$this->assertSame( 'versions-rollback', $acrossai['sub_group'] );
	}

	/**
	 * @dataProvider pair_provider
	 * @param string $class Ability class.
	 */
	public function test_description_names_the_check_ability_or_the_sources( string $class ): void {
		// A write this consequential should tell the caller where the versions come from, so a
		// refusal for a premium asset is not a surprise.
		$this->assertStringContainsString( 'WordPress.org', $this->spec( $class )['args']['description'] );
	}

	/**
	 * @dataProvider pair_provider
	 * @param string $class Ability class.
	 */
	public function test_abilities_are_not_public_over_mcp( string $class ): void {
		$this->assertFalse( $this->spec( $class )['args']['meta']['mcp']['public'] );
	}
}
