<?php
/**
 * DependencyResolverTest — unit coverage for the Debugging Dependency_Resolver helper.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.21
 */

declare(strict_types=1);

use AcrossAI_Abilities_Manager\Includes\Abilities\Debugging\Dependency_Resolver;
use PHPUnit\Framework\TestCase;

// ---------------------------------------------------------------------------
// Global test stubs. The get_plugins() stub in OverridesStoreTest.php is
// guarded with function_exists — this file re-guards to be safe when run
// standalone.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'get_plugins' ) ) {
	function get_plugins( string $plugin_folder = '' ): array {
		global $__acrossai_debug_test_get_plugins;
		return is_array( $__acrossai_debug_test_get_plugins ) ? $__acrossai_debug_test_get_plugins : array();
	}
}

/**
 * @coversDefaultClass \AcrossAI_Abilities_Manager\Includes\Abilities\Debugging\Dependency_Resolver
 */
final class DependencyResolverTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Dependency_Resolver::instance()->reset_cache();
	}

	protected function tearDown(): void {
		Dependency_Resolver::instance()->reset_cache();
		parent::tearDown();
	}

	/**
	 * Helper — seed the get_plugins() fixture with an adjacency map.
	 *
	 * @param array<string,string> $requires plugin_file => comma-separated slugs it requires
	 */
	private function seed( array $requires ): void {
		$plugins = array();
		foreach ( $requires as $file => $requires_header ) {
			$plugins[ $file ] = array(
				'Name'            => strtoupper( strtok( $file, '/' ) ),
				'Version'         => '1.0',
				'RequiresPlugins' => $requires_header,
			);
		}
		$GLOBALS['__acrossai_debug_test_get_plugins'] = $plugins;
		Dependency_Resolver::instance()->reset_cache();
	}

	// ---------------------------------------------------------------------
	// dependents_of
	// ---------------------------------------------------------------------

	/**
	 * Direct dependent — B requires A → dependents_of(A) = [B].
	 *
	 * Covers spec Story 4 AC-1: given B declares A required, overriding A
	 * to inactive with cascade produces an inactive override entry for B.
	 * Set_Override::execute() invokes Dependency_Resolver::dependents_of()
	 * and writes an override per returned entry.
	 */
	public function test_direct_dependent(): void {
		$this->seed(
			array(
				'plugin-a/main.php' => '',
				'plugin-b/main.php' => 'plugin-a',
			)
		);

		$this->assertSame(
			array( 'plugin-b/main.php' ),
			Dependency_Resolver::instance()->dependents_of( 'plugin-a/main.php' )
		);
	}

	/**
	 * Transitive dependents — C requires B, B requires A → dependents_of(A) = [B, C].
	 *
	 * Covers spec Story 4 AC-3: a chain of three plugins all active, overriding
	 * the root inactive with cascade produces override entries for the two
	 * transitively-dependent plugins.
	 */
	public function test_transitive_dependents(): void {
		$this->seed(
			array(
				'plugin-a/main.php' => '',
				'plugin-b/main.php' => 'plugin-a',
				'plugin-c/main.php' => 'plugin-b',
			)
		);

		$result = Dependency_Resolver::instance()->dependents_of( 'plugin-a/main.php' );
		sort( $result );
		$this->assertSame( array( 'plugin-b/main.php', 'plugin-c/main.php' ), $result );
	}

	/** Diamond — D requires B and C; B requires A; C requires A → dependents_of(A) has no dup. */
	public function test_diamond_no_duplicate_visits(): void {
		$this->seed(
			array(
				'plugin-a/main.php' => '',
				'plugin-b/main.php' => 'plugin-a',
				'plugin-c/main.php' => 'plugin-a',
				'plugin-d/main.php' => 'plugin-b, plugin-c',
			)
		);

		$result = Dependency_Resolver::instance()->dependents_of( 'plugin-a/main.php' );
		sort( $result );
		$this->assertSame(
			array( 'plugin-b/main.php', 'plugin-c/main.php', 'plugin-d/main.php' ),
			$result
		);
	}

	// ---------------------------------------------------------------------
	// requirements_of
	// ---------------------------------------------------------------------

	/**
	 * Direct requirement — B requires A → requirements_of(B) = [A].
	 *
	 * Covers spec Story 4 AC-2: given A and B both inactive with B declaring
	 * A required, overriding B to active with cascade produces an active
	 * override entry for A too. Set_Override::execute() invokes
	 * Dependency_Resolver::requirements_of() and writes an override per
	 * returned entry.
	 */
	public function test_direct_requirement(): void {
		$this->seed(
			array(
				'plugin-a/main.php' => '',
				'plugin-b/main.php' => 'plugin-a',
			)
		);

		$this->assertSame(
			array( 'plugin-a/main.php' ),
			Dependency_Resolver::instance()->requirements_of( 'plugin-b/main.php' )
		);
	}

	/** Transitive requirements — C requires B, B requires A → requirements_of(C) = [B, A]. */
	public function test_transitive_requirements(): void {
		$this->seed(
			array(
				'plugin-a/main.php' => '',
				'plugin-b/main.php' => 'plugin-a',
				'plugin-c/main.php' => 'plugin-b',
			)
		);

		$result = Dependency_Resolver::instance()->requirements_of( 'plugin-c/main.php' );
		sort( $result );
		$this->assertSame( array( 'plugin-a/main.php', 'plugin-b/main.php' ), $result );
	}

	// ---------------------------------------------------------------------
	// Edge cases
	// ---------------------------------------------------------------------

	/** Cycle — A requires B, B requires A → BFS terminates without infinite loop. */
	public function test_cycle_guard_dependents(): void {
		$this->seed(
			array(
				'plugin-a/main.php' => 'plugin-b',
				'plugin-b/main.php' => 'plugin-a',
			)
		);

		// dependents_of(A) should still return B (and BFS should not stack-overflow).
		$result = Dependency_Resolver::instance()->dependents_of( 'plugin-a/main.php' );
		$this->assertSame( array( 'plugin-b/main.php' ), $result );
	}

	/** Cycle — same as above but for requirements_of. */
	public function test_cycle_guard_requirements(): void {
		$this->seed(
			array(
				'plugin-a/main.php' => 'plugin-b',
				'plugin-b/main.php' => 'plugin-a',
			)
		);

		$result = Dependency_Resolver::instance()->requirements_of( 'plugin-a/main.php' );
		$this->assertSame( array( 'plugin-b/main.php' ), $result );
	}

	/** Unknown target returns an empty array (no dependents, no requirements). */
	public function test_unknown_target_returns_empty(): void {
		$this->seed(
			array(
				'plugin-a/main.php' => '',
				'plugin-b/main.php' => 'plugin-a',
			)
		);

		$this->assertSame( array(), Dependency_Resolver::instance()->dependents_of( 'unknown/main.php' ) );
		$this->assertSame( array(), Dependency_Resolver::instance()->requirements_of( 'unknown/main.php' ) );
	}

	/** Standalone plugin (no requires header, nothing requires it). */
	public function test_standalone_plugin(): void {
		$this->seed(
			array(
				'plugin-a/main.php' => '',
			)
		);

		$this->assertSame( array(), Dependency_Resolver::instance()->dependents_of( 'plugin-a/main.php' ) );
		$this->assertSame( array(), Dependency_Resolver::instance()->requirements_of( 'plugin-a/main.php' ) );
	}
}
