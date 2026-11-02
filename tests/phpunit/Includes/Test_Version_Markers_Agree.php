<?php
/**
 * The plugin's version is written in three places; they have to agree.
 *
 * 0.0.44 shipped with the header and the readme at 0.0.44 and
 * ACROSSAI_ABILITIES_MANAGER_VERSION still at 0.0.43 — a public constant reporting the wrong
 * version to anything that reads it, including other plugins and support diagnostics. The release
 * checklist is clearly not enough on its own, so this test is the checklist.
 *
 * Deliberately reads the files as text rather than the loaded constant: the point is to catch a
 * literal that was not updated, and in the stub harness the constant is whatever a previous test
 * defined first.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.45
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

final class Test_Version_Markers_Agree extends TestCase {

	/**
	 * Plugin root.
	 *
	 * @var string
	 */
	private string $root = '';

	protected function setUp(): void {
		parent::setUp();
		$this->root = dirname( __DIR__, 3 );
	}

	/**
	 * The `Version:` header in the main plugin file — the value WordPress itself reports.
	 *
	 * @return string
	 */
	private function header_version(): string {
		$src = (string) file_get_contents( $this->root . '/acrossai-abilities-manager.php' );

		$this->assertSame(
			1,
			preg_match( '/^\s*\*\s*Version:\s*(\S+)\s*$/m', $src, $m ),
			'The main plugin file must carry exactly one Version: header.'
		);

		return $m[1];
	}

	public function test_the_version_constant_matches_the_plugin_header(): void {
		$src = (string) file_get_contents( $this->root . '/includes/Main.php' );

		$this->assertSame(
			1,
			preg_match( "/ACROSSAI_ABILITIES_MANAGER_VERSION',\s*'([^']+)'/", $src, $m ),
			'ACROSSAI_ABILITIES_MANAGER_VERSION must be defined with a literal version.'
		);

		$this->assertSame(
			$this->header_version(),
			$m[1],
			'ACROSSAI_ABILITIES_MANAGER_VERSION disagrees with the plugin header. Both must be bumped on release.'
		);
	}

	public function test_the_readme_stable_tag_matches_the_plugin_header(): void {
		$src = (string) file_get_contents( $this->root . '/README.txt' );

		$this->assertSame(
			1,
			preg_match( '/^Stable tag:\s*(\S+)\s*$/m', $src, $m ),
			'README.txt must carry exactly one Stable tag.'
		);

		$this->assertSame(
			$this->header_version(),
			$m[1],
			'README.txt Stable tag disagrees with the plugin header. Both must be bumped on release.'
		);
	}

	public function test_the_readme_has_a_dated_changelog_entry_for_this_version(): void {
		$version = $this->header_version();
		$src     = (string) file_get_contents( $this->root . '/README.txt' );

		$this->assertMatchesRegularExpression(
			'/^= ' . preg_quote( $version, '/' ) . ' - \d{4}-\d{2}-\d{2} =$/m',
			$src,
			sprintf(
				'README.txt needs a dated changelog heading for %s. An "unreleased" heading means the release was never dated.',
				$version
			)
		);
	}

	public function test_the_readme_has_an_upgrade_notice_for_this_version(): void {
		$version = $this->header_version();
		$src     = (string) file_get_contents( $this->root . '/README.txt' );
		$notices = '';

		if ( 1 === preg_match( '/^== Upgrade Notice ==\s*(.*?)(?=^== |\z)/ms', $src, $m ) ) {
			$notices = $m[1];
		}

		$this->assertStringContainsString(
			'= ' . $version . ' =',
			$notices,
			sprintf( 'README.txt needs an Upgrade Notice entry for %s.', $version )
		);
	}

	public function test_the_changelog_section_stays_within_the_wordpress_org_cap(): void {
		// WordPress.org truncates the readme's Changelog section at 5,000 words, silently and
		// visibly only to committers. It stood at 7,334 before 0.0.44 moved older entries into
		// changelog.txt; without a check it will drift back over.
		$src = (string) file_get_contents( $this->root . '/README.txt' );

		$this->assertSame(
			1,
			preg_match( '/^== Changelog ==\s*(.*?)(?=^== |\z)/ms', $src, $m ),
			'README.txt must carry a Changelog section.'
		);

		$words = str_word_count( wp_strip_all_tags( $m[1] ) );

		$this->assertLessThan(
			5000,
			$words,
			sprintf(
				'The Changelog section is %d words; WordPress.org caps it at 5,000 and truncates the rest. Move older entries into changelog.txt.',
				$words
			)
		);
	}

	public function test_every_release_dropped_from_the_readme_survives_in_changelog_txt(): void {
		// 0.0.44 trimmed the readme by moving entries to changelog.txt. The danger in doing that
		// again is dropping an entry that was never migrated — changelog.txt was missing 0.0.42 and
		// 0.0.43 at the time, so the trim could have lost them.
		$readme = (string) file_get_contents( $this->root . '/README.txt' );
		$clog   = (string) file_get_contents( $this->root . '/changelog.txt' );

		preg_match_all( '/^= (\d+\.\d+\.\d+) - \d{4}-\d{2}-\d{2} =$/m', $readme, $in_readme );
		preg_match_all( '/^= (\d+\.\d+\.\d+) - \d{4}-\d{2}-\d{2} =$/m', $clog, $in_clog );

		$this->assertNotEmpty( $in_readme[1], 'The readme must list at least one release.' );

		foreach ( $in_readme[1] as $version ) {
			$this->assertContains(
				$version,
				$in_clog[1],
				sprintf(
					'%s is in README.txt but not changelog.txt, which describes itself as the complete history. Migrate it before trimming the readme.',
					$version
				)
			);
		}
	}
}
