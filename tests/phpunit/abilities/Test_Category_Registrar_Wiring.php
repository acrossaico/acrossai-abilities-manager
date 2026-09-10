<?php
/**
 * Tests: every ability Category_Registrar is wired into the bootstrap.
 *
 * WordPress refuses to register an ability whose category is not registered —
 * `WP_Abilities_Registry::register()` emits `_doing_it_wrong()` and returns
 * null. Shipping a Category_Registrar without adding it to
 * `AcrossAI_Core_Abilities_Bootstrap::register_category_callbacks()` therefore
 * does not fail loudly: the abilities are instantiated, they attempt to
 * register, WordPress rejects every one, and the only trace is a debug-log
 * notice on sites that happen to have WP_DEBUG_LOG enabled.
 *
 * That is exactly what happened to the Debugging category (Feature 061). Its
 * registrar existed from the start and was never wired, so all seven
 * conflict-testing abilities were absent at runtime for the feature's entire
 * life — long enough that a hand-maintained inventory doc recorded the plugin
 * as having 24 namespaces rather than 25, because whoever wrote it was reading
 * runtime data.
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Abilities;

use PHPUnit\Framework\TestCase;

/**
 * Guards the registrar-to-bootstrap wiring.
 */
class Test_Category_Registrar_Wiring extends TestCase {

	/**
	 * Plugin root.
	 *
	 * @return string
	 */
	private function root(): string {
		return dirname( __DIR__, 3 );
	}

	/**
	 * Folder names under includes/Abilities that ship a Category_Registrar.
	 *
	 * @return string[]
	 */
	private function folders_with_registrar(): array {
		$out = array();

		foreach ( glob( $this->root() . '/includes/Abilities/*/Category_Registrar.php' ) as $file ) {
			$out[] = basename( dirname( $file ) );
		}

		sort( $out );
		return $out;
	}

	/**
	 * The bootstrap source.
	 *
	 * @return string
	 */
	private function bootstrap_src(): string {
		return (string) file_get_contents(
			$this->root() . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php'
		);
	}

	/**
	 * Sanity: the scan finds registrars at all.
	 *
	 * Without this, a broken glob would make every other assertion here pass
	 * against an empty set.
	 */
	public function test_scan_finds_registrars(): void {
		$this->assertGreaterThanOrEqual(
			25,
			count( $this->folders_with_registrar() ),
			'Expected at least 25 Category_Registrar files; the scan found ' . count( $this->folders_with_registrar() ) . '.'
		);
	}

	/**
	 * Every registrar that exists is hooked to wp_abilities_api_categories_init.
	 */
	public function test_every_registrar_is_wired_into_the_bootstrap(): void {
		$src       = $this->bootstrap_src();
		$unwired   = array();

		foreach ( $this->folders_with_registrar() as $folder ) {
			$needle = $folder . '\\Category_Registrar::instance()';

			if ( ! str_contains( $src, $needle ) ) {
				$unwired[] = $folder;
			}
		}

		$this->assertSame(
			array(),
			$unwired,
			"These folders ship a Category_Registrar that is never wired into the bootstrap, so\n"
				. "their category is never registered and WordPress rejects every ability claiming it:\n  "
				. implode( "\n  ", $unwired )
		);
	}

	/**
	 * Debugging specifically — the category this test was written for.
	 */
	public function test_debugging_category_is_registered(): void {
		$this->assertStringContainsString(
			'Debugging\\Category_Registrar::instance()',
			$this->bootstrap_src(),
			'The Debugging category must be registered; without it all seven conflict-testing abilities are rejected.'
		);
	}

	/**
	 * Every folder whose abilities are instantiated also has a registrar.
	 *
	 * The mirror of the check above: instantiating abilities from a folder
	 * that has no category registrar fails the same silent way.
	 */
	public function test_every_instantiated_folder_has_a_registrar(): void {
		$src = $this->bootstrap_src();

		// Folder names appearing as `new Folder\Class_Name();` in the bootstrap.
		preg_match_all( '/new\s+([A-Z][A-Za-z]*)\\\\[A-Z][A-Za-z_]*\s*\(/', $src, $m );
		$instantiated = array_unique( $m[1] );

		$registrars = $this->folders_with_registrar();
		$missing    = array();

		foreach ( $instantiated as $folder ) {
			// Utilities and Integrations hold helpers and synthetic display
			// rows respectively; neither registers abilities of its own.
			if ( in_array( $folder, array( 'Utilities', 'Integrations' ), true ) ) {
				continue;
			}
			if ( ! in_array( $folder, $registrars, true ) ) {
				$missing[] = $folder;
			}
		}

		$this->assertSame(
			array(),
			array_values( $missing ),
			'Abilities are instantiated from folders with no Category_Registrar: ' . implode( ', ', $missing )
		);
	}
}
