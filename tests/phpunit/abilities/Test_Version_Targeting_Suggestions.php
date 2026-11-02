<?php
/**
 * Feature 136 — the update and install abilities point at the plugin that can do what they cannot.
 *
 * suggested_plugins() has existed since Feature 088 and no ability had ever used it. These drive the
 * method directly rather than asserting on source text, so they fail if the declaration is wrong
 * rather than merely absent.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.45
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Plugins/Install_Plugin.php';
require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Plugins/Update_Plugin.php';
require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Themes/Install_Theme.php';
require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Themes/Update_Theme.php';
require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Core/Rollback_Wp_Core.php';
require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Core/Update_Wp_Core.php';

final class Test_Version_Targeting_Suggestions extends TestCase {

	/**
	 * Drive the protected template method on one ability.
	 *
	 * @param string $class Fully-qualified ability class.
	 * @return array<int,array<string,mixed>>
	 */
	private function suggestions( string $class ): array {
		$object = new $class();
		$method = new ReflectionMethod( $object, 'suggested_plugins' );
		$method->setAccessible( true );

		return (array) $method->invoke( $object );
	}

	/**
	 * Every ability that cannot target a version, and the host plugin that can.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function suggestion_provider(): array {
		$ns = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\';

		return array(
			'install-plugin installs latest only'  => array( $ns . 'Plugins\\Install_Plugin', 'wp-rollback' ),
			'update-plugin only moves forward'    => array( $ns . 'Plugins\\Update_Plugin', 'wp-rollback' ),
			'install-theme installs latest only'  => array( $ns . 'Themes\\Install_Theme', 'wp-rollback' ),
			'update-theme only moves forward'     => array( $ns . 'Themes\\Update_Theme', 'wp-rollback' ),
			'rollback-wp-core does not pin'       => array( $ns . 'Core\\Rollback_Wp_Core', 'wp-downgrade' ),
			'update-wp-core pins one run only'    => array( $ns . 'Core\\Update_Wp_Core', 'wp-downgrade' ),
		);
	}

	/**
	 * @dataProvider suggestion_provider
	 * @param string $class    Ability class.
	 * @param string $expected Expected plugin slug.
	 */
	public function test_each_ability_suggests_the_right_host_plugin( string $class, string $expected ): void {
		$suggestions = $this->suggestions( $class );

		$this->assertCount( 1, $suggestions, 'One suggestion each; more than one is noise.' );
		$this->assertSame( $expected, $suggestions[0]['slug'] );
	}

	/**
	 * @dataProvider suggestion_provider
	 * @param string $class Ability class.
	 */
	public function test_required_keys_are_present( string $class ): void {
		// Feature 088's registry drops malformed entries silently, so a missing key is invisible
		// rather than loud. slug, name and reason are the three it requires.
		foreach ( $this->suggestions( $class ) as $entry ) {
			foreach ( array( 'slug', 'name', 'reason' ) as $key ) {
				$this->assertArrayHasKey( $key, $entry, $class );
				$this->assertNotSame( '', trim( (string) $entry[ $key ] ), $key . ' must not be empty on ' . $class );
			}
		}
	}

	/**
	 * @dataProvider suggestion_provider
	 * @param string $class Ability class.
	 */
	public function test_every_value_is_scalar( string $class ): void {
		// The framework's contract is array<int,array<string,scalar>>. A nested array would be
		// dropped by the registry without complaint.
		foreach ( $this->suggestions( $class ) as $entry ) {
			foreach ( $entry as $key => $value ) {
				$this->assertIsScalar( $value, $key . ' on ' . $class . ' must be scalar.' );
			}
		}
	}

	/**
	 * @dataProvider suggestion_provider
	 * @param string $class Ability class.
	 */
	public function test_neither_plugin_is_claimed_to_ship_abilities( string $class ): void {
		// Measured, not assumed: activating WP Rollback left the registry at 388 abilities, and WP
		// Downgrade defines no classes at all. Claiming otherwise would tell a caller that
		// installing the plugin gives an agent new tools, which it does not.
		foreach ( $this->suggestions( $class ) as $entry ) {
			$this->assertArrayHasKey( 'plugin_provides_abilities', $entry );
			$this->assertFalse( $entry['plugin_provides_abilities'], $class );
		}
	}

	/* ------------------------------------------------------------------ */
	/* The correctness tests: each host plugin's real scope               */
	/* ------------------------------------------------------------------ */

	public function test_core_abilities_never_suggest_wp_rollback(): void {
		// WP Rollback's own route enum is ['plugin','theme'] and it has no CoreRollback subsystem.
		// Suggesting it for a core operation would send a caller to install a plugin that cannot
		// do the thing they asked for.
		$ns = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Core\\';

		foreach ( array( 'Rollback_Wp_Core', 'Update_Wp_Core' ) as $class ) {
			foreach ( $this->suggestions( $ns . $class ) as $entry ) {
				$this->assertNotSame(
					'wp-rollback',
					$entry['slug'],
					'WP Rollback cannot touch core; its type enum is plugin|theme only.'
				);
			}
		}
	}

	public function test_plugin_and_theme_abilities_never_suggest_wp_downgrade(): void {
		// WP Downgrade is core-only — "Specific Core Version" — and touches no plugins or themes.
		$ns = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\';

		foreach ( array( 'Plugins\\Install_Plugin', 'Plugins\\Update_Plugin', 'Themes\\Install_Theme', 'Themes\\Update_Theme' ) as $class ) {
			foreach ( $this->suggestions( $ns . $class ) as $entry ) {
				$this->assertNotSame(
					'wp-downgrade',
					$entry['slug'],
					'WP Downgrade only pins WordPress core; it cannot re-version a plugin or theme.'
				);
			}
		}
	}

	public function test_no_integration_is_claimed_before_one_exists(): void {
		// Flipped to true by the WP Rollback and WP Downgrade suites. Until those land, claiming an
		// integration exists would be false.
		foreach ( array_keys( self::suggestion_provider() ) as $label ) {
			[ $class ] = self::suggestion_provider()[ $label ];

			foreach ( $this->suggestions( $class ) as $entry ) {
				$this->assertFalse(
					$entry['acrossai_provides_integration'],
					'No suite drives these plugins yet (' . $label . ').'
				);
			}
		}
	}

	public function test_the_reason_explains_this_abilitys_own_limit(): void {
		// A suggestion that does not say why is an advert. Each reason must name the constraint the
		// caller just hit.
		$ns = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\';

		$this->assertStringContainsString(
			'forward',
			$this->suggestions( $ns . 'Plugins\\Update_Plugin' )[0]['reason'],
			'update-plugin cannot move backwards; the reason should say so.'
		);
		$this->assertStringContainsString(
			'latest',
			$this->suggestions( $ns . 'Themes\\Install_Theme' )[0]['reason'],
			'install-theme takes no version; the reason should say so.'
		);
		$this->assertStringContainsString(
			'again',
			$this->suggestions( $ns . 'Core\\Rollback_Wp_Core' )[0]['reason'],
			'rollback-wp-core is undone by the next update check; the reason should say so.'
		);
	}
}
