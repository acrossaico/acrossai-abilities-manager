<?php
/**
 * Tests for SettingsMenu::sanitize_per_page().
 *
 * Covers boundary values, out-of-range inputs, and default fallback.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;
use AcrossAI_Abilities_Manager\Admin\Partials\SettingsMenu;

/**
 * Class SettingsMenuTest
 *
 * @since 0.1.0
 */
class SettingsMenuTest extends WP_UnitTestCase {

	// =========================================================================
	// sanitize_per_page — in-range values
	// =========================================================================

	/**
	 * Accepts the minimum valid value (1).
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_accepts_minimum(): void {
		$this->assertSame( 1, SettingsMenu::instance()->sanitize_per_page( 1 ) );
	}

	/**
	 * Accepts the maximum valid value, whatever the shared bound currently is.
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_accepts_maximum(): void {
		$this->assertSame(
			SettingsMenu::MAX_PER_PAGE,
			SettingsMenu::instance()->sanitize_per_page( SettingsMenu::MAX_PER_PAGE )
		);
	}

	/**
	 * The advertised maximum must be the one the REST layer honours.
	 *
	 * These two drifted: the field offered 200, the sanitiser accepted it, and the REST `per_page`
	 * argument capped at 100 and clamped silently — no error, so an operator who set 150 kept
	 * seeing 100 rows with nothing indicating the setting had been ignored (issue #185). Three
	 * places have to agree, and only a test can keep them agreeing.
	 *
	 * @return void
	 */
	public function test_max_per_page_matches_the_rest_argument(): void {
		$source = file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Modules/Abilities/Rest/AcrossAI_Abilities_Read_Controller.php'
		);

		$this->assertIsString( $source );
		$this->assertSame(
			1,
			preg_match(
				"/'per_page'\s*=>\s*array\((?:[^)]*?)'maximum'\s*=>\s*(\d+)/s",
				(string) $source,
				$m
			),
			'Could not read the REST per_page maximum — has the argument been restructured?'
		);
		$this->assertSame(
			SettingsMenu::MAX_PER_PAGE,
			(int) $m[1],
			'SettingsMenu::MAX_PER_PAGE must equal the REST per_page maximum.'
		);
	}

	/**
	 * The JS constant must agree too.
	 *
	 * `AbilitiesList` clamps the stored setting before sending it, and computes nothing from a
	 * bound the server will not honour.
	 *
	 * @return void
	 */
	public function test_max_per_page_matches_the_js_constant(): void {
		$source = file_get_contents( dirname( __DIR__, 3 ) . '/src/js/abilities/constants.js' );

		$this->assertIsString( $source );
		$this->assertSame(
			1,
			preg_match( '/export const MAX_PER_PAGE = (\d+);/', (string) $source, $m ),
			'src/js/abilities/constants.js must export MAX_PER_PAGE.'
		);
		$this->assertSame( SettingsMenu::MAX_PER_PAGE, (int) $m[1] );
	}

	/**
	 * Accepts a mid-range value (50).
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_accepts_midrange(): void {
		$this->assertSame( 50, SettingsMenu::instance()->sanitize_per_page( 50 ) );
	}

	/**
	 * Accepts the default value (20).
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_accepts_default(): void {
		$this->assertSame( 20, SettingsMenu::instance()->sanitize_per_page( 20 ) );
	}

	// =========================================================================
	// sanitize_per_page — out-of-range → 20
	// =========================================================================

	/**
	 * Returns 20 when value is 0 (below minimum).
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_rejects_zero(): void {
		$this->assertSame( 20, SettingsMenu::instance()->sanitize_per_page( 0 ) );
	}

	/**
	 * Returns 20 when value is 201 (above maximum).
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_rejects_above_max(): void {
		$this->assertSame( 20, SettingsMenu::instance()->sanitize_per_page( 201 ) );
	}

	/**
	 * Returns 20 for a large out-of-range integer.
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_rejects_large_value(): void {
		$this->assertSame( 20, SettingsMenu::instance()->sanitize_per_page( 99999 ) );
	}

	/**
	 * absint(-5) = 5 which is in range — returns 5.
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_negative_converts_via_absint(): void {
		$this->assertSame( 5, SettingsMenu::instance()->sanitize_per_page( -5 ) );
	}

	/**
	 * Returns 20 for a very negative value (absint(-300) = 300, out-of-range → 20).
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_large_negative_returns_default(): void {
		$this->assertSame( 20, SettingsMenu::instance()->sanitize_per_page( -300 ) );
	}

	/**
	 * Returns 20 for an empty string.
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_rejects_empty_string(): void {
		$this->assertSame( 20, SettingsMenu::instance()->sanitize_per_page( '' ) );
	}

	/**
	 * Returns 20 for a non-numeric string.
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_rejects_string(): void {
		$this->assertSame( 20, SettingsMenu::instance()->sanitize_per_page( 'many' ) );
	}

	/**
	 * String-formatted numeric value within range is accepted.
	 *
	 * @return void
	 */
	public function test_sanitize_per_page_accepts_numeric_string(): void {
		$this->assertSame( 10, SettingsMenu::instance()->sanitize_per_page( '10' ) );
	}
}
