<?php
/**
 * Feature 099 — tab-group label rule.
 *
 * The Integrations admin page derives its tabs in JavaScript at render time
 * (PATTERN-ABILITY-LIBRARY-TAB-AUTO-DERIVE). Spec SC-004 requires the Quick
 * Connect wizard's server-derived groups to match that page exactly, so the PHP
 * and JS labelling rules must agree character-for-character.
 *
 * The fixture list below is duplicated verbatim in
 * tests/jest/quick-connect/titleCaseTabLabel.test.js. Changing one without the
 * other fails the pair — that is the point.
 *
 * @package AcrossAI_Abilities_Manager
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Tab_Group_Label;

require_once dirname( __DIR__, 3 ) . '/includes/Utilities/AcrossAI_Tab_Group_Label.php';

/**
 * Covers AcrossAI_Tab_Group_Label::format().
 */
class Test_Tab_Group_Label extends TestCase {

	/**
	 * Shared PHP/JS fixture: key => expected label.
	 *
	 * @return array<string, string>
	 */
	public static function shared_fixture(): array {
		return array(
			// Feature 101 families. Every key must title-case cleanly, because
			// spec 037 FR-007 forbids a separate display-label field — the key
			// IS the label. This fixture is what stops a family being named
			// something the formatter renders badly.
			'content'          => 'Content',
			'blocks'           => 'Blocks',
			'appearance'       => 'Appearance',
			'configuration'    => 'Configuration',
			'users'            => 'Users',
			'updates'          => 'Updates',
			'cron'             => 'Cron',
			'cache'            => 'Cache',
			'database'         => 'Database',
			'files'            => 'Files',
			'diagnostics'      => 'Diagnostics',
			'elementor'        => 'Elementor',
			'rank-math'        => 'Rank Math',
			// Multi-word derivation and a third-party key.
			'content-search'   => 'Content Search',
			'file-manager'     => 'File Manager',
			'site-health'      => 'Site Health',
			'mailerpress-pro'  => 'Mailerpress Pro',
		);
	}

	/**
	 * Data provider built from the shared fixture.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function label_provider(): array {
		$cases = array();

		foreach ( self::shared_fixture() as $key => $expected ) {
			$cases[ $key ] = array( $key, $expected );
		}

		return $cases;
	}

	/**
	 * Each fixture key formats to its expected label.
	 *
	 * @dataProvider label_provider
	 *
	 * @param string $key      Tab-group key.
	 * @param string $expected Expected display label.
	 */
	public function test_format_matches_shared_fixture( string $key, string $expected ): void {
		$this->assertSame( $expected, AcrossAI_Tab_Group_Label::format( $key ) );
	}

	/**
	 * An empty key yields an empty label rather than a stray space.
	 */
	public function test_empty_key_returns_empty_string(): void {
		$this->assertSame( '', AcrossAI_Tab_Group_Label::format( '' ) );
	}

	/**
	 * Formatting is idempotent — an already-formatted label survives a second pass.
	 */
	public function test_format_is_idempotent_for_single_words(): void {
		$once  = AcrossAI_Tab_Group_Label::format( 'core' );
		$twice = AcrossAI_Tab_Group_Label::format( $once );

		$this->assertSame( $once, $twice );
	}
}
