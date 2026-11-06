<?php
/**
 * The registration contract for the two reinstall abilities.
 *
 * Drives each ability's own ability() output rather than asserting on source text, so a wrong
 * declaration fails and not merely a missing one.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.45
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Plugins/Reinstall_Plugin.php';
require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Themes/Reinstall_Theme.php';

final class Test_Reinstall_Contract extends TestCase {

	private const PLUGIN = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Plugins\\Reinstall_Plugin';
	private const THEME  = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Themes\\Reinstall_Theme';

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
	 * @return array<string,array{0:string,1:string,2:string}>
	 */
	public static function pair_provider(): array {
		return array(
			'plugin' => array( self::PLUGIN, 'plugins/reinstall-plugin', 'plugin' ),
			'theme'  => array( self::THEME, 'themes/reinstall-theme', 'stylesheet' ),
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

	/**
	 * @dataProvider pair_provider
	 * @param string $class Ability class.
	 * @param string $name  Expected ability name.
	 * @param string $param Expected sole input parameter.
	 */
	public function test_the_parameter_uses_the_established_singular_spelling( string $class, string $name, string $param ): void {
		// The existing family is inconsistent — `plugin` vs `slugs`, `theme` vs `stylesheet` vs
		// `stylesheets` — and guessing wrong cost three failed calls in one test sweep. These two
		// take `plugin` and `stylesheet`, matching the rest of the version work. No fourth
		// spelling, and no aliases to widen the surface.
		$schema = $this->spec( $class )['args']['input_schema'];

		$this->assertSame( array( $param ), array_keys( $schema['properties'] ) );
		$this->assertSame( array( $param ), $schema['required'] );
		$this->assertFalse( $schema['additionalProperties'] );
	}

	/**
	 * @dataProvider pair_provider
	 * @param string $class Ability class.
	 */
	public function test_annotations_match_core_reinstall_wp_core( string $class ): void {
		// Deliberately the same shape as core/reinstall-wp-core: it replaces files, so destructive,
		// and re-applying the same version twice is the same as once, so idempotent. A caller that
		// has learned the core ability should not find a different contract here.
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
	public function test_no_confirm_flag_is_required( string $class ): void {
		// core/reinstall-wp-core overwrites the whole of WordPress without one, because the
		// operation does not change the version. Requiring confirm here would be stricter than the
		// core equivalent for a strictly smaller operation.
		$this->assertArrayNotHasKey( 'confirm', $this->spec( $class )['args']['input_schema']['properties'] );
	}

	/**
	 * @dataProvider pair_provider
	 * @param string $class Ability class.
	 */
	public function test_output_declares_every_key_the_execute_path_returns( string $class ): void {
		// output_schema sets additionalProperties false, which strips undeclared keys silently —
		// so an error_code the schema forgot is simply not delivered, and the caller sees a bare
		// failure with no reason.
		$properties = $this->spec( $class )['args']['output_schema']['properties'];

		foreach ( array( 'success', 'reinstalled', 'version', 'was_active', 'error_code', 'blocked_reason', 'message' ) as $key ) {
			$this->assertArrayHasKey( $key, $properties, $key . ' is returned by execute() but not declared' );
		}
	}

	/**
	 * @dataProvider pair_provider
	 * @param string $class Ability class.
	 */
	public function test_output_is_closed_and_requires_only_success( string $class ): void {
		$schema = $this->spec( $class )['args']['output_schema'];

		$this->assertFalse( $schema['additionalProperties'] );
		$this->assertSame( array( 'success' ), $schema['required'] );
	}

	public function test_each_ability_declares_the_identifier_it_actually_reads(): void {
		// The plugin ability reads $input['plugin'] and the theme ability reads
		// $input['stylesheet']. A schema naming the other one would be accepted by core's
		// validation and then arrive as a missing identifier.
		$this->assertArrayHasKey( 'plugin', $this->spec( self::PLUGIN )['args']['input_schema']['properties'] );
		$this->assertArrayHasKey( 'stylesheet', $this->spec( self::THEME )['args']['input_schema']['properties'] );
	}

	public function test_abilities_land_in_their_sibling_categories(): void {
		// Not the versions/ category: these need no host plugin and are not gated on one, so they
		// belong with the plugin and theme lifecycle abilities a caller already has.
		$this->assertSame( 'acrossai-plugins', $this->spec( self::PLUGIN )['args']['category'] );
		$this->assertSame( 'acrossai-themes', $this->spec( self::THEME )['args']['category'] );
	}

	public function test_toolset_groups_match_their_siblings(): void {
		// Both land in `updates`, not `appearance`: every existing themes/* ability declares
		// `updates`, because this group is asset lifecycle rather than visual design. Declaring
		// `appearance` here looked more natural and was wrong — Ability_Group_Map caught it.
		$this->assertSame( 'updates', $this->spec( self::PLUGIN )['args']['meta']['acrossai']['tab_group'] );
		$this->assertSame( 'updates', $this->spec( self::THEME )['args']['meta']['acrossai']['tab_group'] );
	}

	/**
	 * @dataProvider pair_provider
	 * @param string $class Ability class.
	 */
	public function test_abilities_are_not_exposed_publicly_over_mcp( string $class ): void {
		$mcp = $this->spec( $class )['args']['meta']['mcp'];

		$this->assertFalse( $mcp['public'] );
		$this->assertSame( 'tool', $mcp['type'] );
	}

	/**
	 * @dataProvider pair_provider
	 * @param string $class Ability class.
	 */
	public function test_permission_callback_requires_more_than_manage_options( string $class ): void {
		$callback = $this->spec( $class )['args']['permission_callback'];

		$this->assertIsCallable( $callback );
	}

	/**
	 * @dataProvider pair_provider
	 * @param string $class Ability class.
	 */
	public function test_description_states_that_the_version_does_not_change( string $class ): void {
		// The one thing a caller must not misread. Reinstall and rollback differ only in whether
		// the version moves, and an agent choosing between them reads this description.
		$this->assertStringContainsString(
			'without changing the version',
			$this->spec( $class )['args']['description']
		);
	}

	/**
	 * @dataProvider pair_provider
	 * @param string $class Ability class.
	 */
	public function test_description_promises_the_restore_on_failure( string $class ): void {
		$this->assertStringContainsString(
			'restored automatically',
			$this->spec( $class )['args']['description']
		);
	}
}
