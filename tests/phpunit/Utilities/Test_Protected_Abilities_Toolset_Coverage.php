<?php
/**
 * Tests: the protected list covers every Toolset.
 *
 * `AcrossAI_Protected_Abilities::TOOLSET_SLUGS` holds literals so the list
 * carries no load-order dependency — it is read long before the Toolsets
 * construct. The cost of literals is drift: a fourteenth group would add a
 * `Toolset/*.php` class and nothing would point at this file.
 *
 * A missed slug fails quietly and badly. The dispatcher would appear in the
 * sitewide Abilities UI as an ordinary ability, where disabling or overriding
 * it takes the whole group behind it offline, and
 * `AcrossAI_Abilities_Write_Controller` would accept writes to it.
 *
 * So this reads the real `slug()` methods and pins the constant against them.
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Utilities;

use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Protected_Abilities;
use PHPUnit\Framework\TestCase;

/**
 * Coverage of the Toolset slugs in the protected list.
 */
class Test_Protected_Abilities_Toolset_Coverage extends TestCase {

	/**
	 * Files in includes/Abilities/Toolset that declare a Toolset.
	 *
	 * @var string[]
	 */
	private const NOT_A_TOOLSET = array(
		'Base_Toolset_Ability.php',
		'Category_Registrar.php',
	);

	/**
	 * Every `toolset/...` slug declared by a concrete Toolset class.
	 *
	 * @return string[]
	 */
	private function declared_slugs(): array {
		$dir   = dirname( __DIR__, 3 ) . '/includes/Abilities/Toolset';
		$slugs = array();

		foreach ( (array) glob( $dir . '/*.php' ) as $file ) {
			if ( in_array( basename( (string) $file ), self::NOT_A_TOOLSET, true ) ) {
				continue;
			}

			$src = (string) file_get_contents( (string) $file );

			// The slug lives in the subclass's slug() method, which returns a
			// single literal — the only 'toolset/...' string in these files.
			if ( preg_match( "/'(toolset\/[a-z0-9-]+)'/", $src, $m ) ) {
				$slugs[] = $m[1];
			}
		}

		sort( $slugs );

		return $slugs;
	}

	/**
	 * Sanity: the scan finds Toolsets at all.
	 *
	 * Without this the comparison below passes vacuously if the glob breaks or
	 * the folder moves — two empty arrays match.
	 */
	public function test_scan_finds_the_toolset_classes(): void {
		$this->assertGreaterThanOrEqual(
			13,
			count( $this->declared_slugs() ),
			'Expected at least the 13 shipped Toolsets; the scan is probably looking in the wrong place.'
		);
	}

	/**
	 * The constant names exactly the Toolsets that exist.
	 */
	public function test_every_toolset_is_protected(): void {
		$declared  = $this->declared_slugs();
		$protected = AcrossAI_Protected_Abilities::TOOLSET_SLUGS;
		sort( $protected );

		$this->assertSame(
			$declared,
			$protected,
			"AcrossAI_Protected_Abilities::TOOLSET_SLUGS must name exactly the Toolsets in includes/Abilities/Toolset. "
				. 'Missing: ' . implode( ', ', array_diff( $declared, $protected ) ) . '. '
				. 'Stale: ' . implode( ', ', array_diff( $protected, $declared ) ) . '.'
		);
	}

	/**
	 * The resolved list carries the Toolsets alongside the vendor meta tools.
	 */
	public function test_resolved_list_contains_both_families(): void {
		$slugs = AcrossAI_Protected_Abilities::get_protected_slugs();

		$this->assertContains( 'mcp-adapter/discover-abilities', $slugs );
		$this->assertContains( 'toolset/content', $slugs );
		$this->assertContains( 'toolset/rank-math', $slugs );
	}

	/**
	 * A conditional Toolset is protected whether or not its plugin is active.
	 *
	 * Gating these on plugin state would make the protected set depend on load
	 * order, and naming an unregistered slug is inert — nothing can look it up.
	 */
	public function test_conditional_toolsets_are_protected_unconditionally(): void {
		$slugs = AcrossAI_Protected_Abilities::get_protected_slugs();

		$this->assertContains( 'toolset/elementor', $slugs );
		$this->assertContains( 'toolset/rank-math', $slugs );
	}

	/**
	 * A Toolset reports as protected through the public predicate.
	 */
	public function test_is_protected_reports_true_for_a_toolset(): void {
		$this->assertTrue( AcrossAI_Protected_Abilities::is_protected( 'toolset/users' ) );
		$this->assertFalse( AcrossAI_Protected_Abilities::is_protected( 'users/list-users' ) );
	}
}
