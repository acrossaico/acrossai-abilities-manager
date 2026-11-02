<?php
/**
 * Every test file phpunit.xml.dist names must exist, matching case.
 *
 * macOS is case-insensitive, Linux is not. A `<file>` entry written as
 * `abilities/Debugging/X.php` when the directory on disk is `abilities/debugging/` runs perfectly
 * on a developer machine and fails CI on every PHP version with "Test file not found" — which is
 * exactly what happened while fixing #302, because `mkdir -p .../Debugging` silently did nothing
 * against an existing lowercase directory.
 *
 * Nothing else catches this before CI does, so this does.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.45
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

final class Test_Phpunit_Config_Integrity extends TestCase {

	/**
	 * Resolve a relative path one segment at a time, comparing case exactly.
	 *
	 * `file_exists()` is useless here: on a case-insensitive filesystem it returns true for the
	 * wrong case, which is the whole problem. Each segment is matched against the real directory
	 * listing instead.
	 *
	 * @param string $root     Plugin root.
	 * @param string $relative Path from phpunit.xml.dist.
	 * @return bool
	 */
	private function exists_with_exact_case( string $root, string $relative ): bool {
		$current = $root;

		foreach ( explode( '/', $relative ) as $segment ) {
			if ( '' === $segment ) {
				continue;
			}

			$entries = @scandir( $current );

			if ( false === $entries || ! in_array( $segment, $entries, true ) ) {
				return false;
			}

			$current .= '/' . $segment;
		}

		return true;
	}

	public function test_every_referenced_test_file_exists_with_matching_case(): void {
		$root   = dirname( __DIR__, 3 );
		$config = (string) file_get_contents( $root . '/phpunit.xml.dist' );

		preg_match_all( '#<file>([^<]+)</file>#', $config, $m );

		$this->assertNotEmpty( $m[1], 'phpunit.xml.dist must reference at least one test file.' );

		$mismatched = array();

		foreach ( $m[1] as $relative ) {
			if ( ! $this->exists_with_exact_case( $root, trim( $relative ) ) ) {
				$mismatched[] = trim( $relative );
			}
		}

		$this->assertSame(
			array(),
			$mismatched,
			"phpunit.xml.dist names files that do not exist with this exact case. These pass on macOS and fail CI on Linux:\n  - " . implode( "\n  - ", $mismatched )
		);
	}

	public function test_every_referenced_directory_exists_with_matching_case(): void {
		$root   = dirname( __DIR__, 3 );
		$config = (string) file_get_contents( $root . '/phpunit.xml.dist' );

		preg_match_all( '#<directory[^>]*>([^<]+)</directory>#', $config, $m );

		$mismatched = array();

		foreach ( $m[1] as $relative ) {
			$relative = trim( $relative );

			// Coverage sections reference source directories too; both must resolve.
			if ( '' !== $relative && ! $this->exists_with_exact_case( $root, $relative ) ) {
				$mismatched[] = $relative;
			}
		}

		$this->assertSame( array(), $mismatched, 'phpunit.xml.dist names directories that do not exist with this exact case.' );
	}
}
