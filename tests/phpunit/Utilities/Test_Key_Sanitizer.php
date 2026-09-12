<?php
/**
 * Feature 102 — the extracted key sanitizer.
 *
 * `sanitize_key_field()` had no direct test while it lived on the config class: it was covered only
 * through `save_config()` round-trips, which T078 deletes along with that suite. Six retained call
 * sites now depend on it — the definition registry sanitises category, slug, tab_group and
 * card_variant through it, so a regression here silently renames abilities rather than failing.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.34
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Key_Sanitizer;

require_once dirname( __DIR__, 3 ) . '/includes/Utilities/AcrossAI_Key_Sanitizer.php';

/**
 * Covers AcrossAI_Key_Sanitizer::key().
 */
class Test_Key_Sanitizer extends TestCase {

	/**
	 * An already-clean key is returned unchanged.
	 *
	 * @return void
	 */
	public function test_clean_key_passes_through(): void {
		$this->assertSame( 'site-health', AcrossAI_Key_Sanitizer::key( 'site-health' ) );
	}

	/**
	 * Characters outside the key alphabet are dropped, and case is folded.
	 *
	 * @param  string $raw      Input.
	 * @param  string $expected Expected output.
	 * @return void
	 *
	 * @dataProvider provide_dirty_keys
	 */
	public function test_dirty_keys_are_reduced_to_the_key_alphabet( string $raw, string $expected ): void {
		$this->assertSame( $expected, AcrossAI_Key_Sanitizer::key( $raw ) );
	}

	/**
	 * Inputs and their canonical form.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function provide_dirty_keys(): array {
		return array(
			'uppercase folded'   => array( 'Site-Health', 'site-health' ),
			'spaces dropped'     => array( 'site health', 'sitehealth' ),
			'slashes dropped'    => array( 'blocks/insert', 'blocksinsert' ),
			'dots dropped'       => array( '../etc/passwd', 'etcpasswd' ),
			'markup dropped'     => array( '<script>x</script>', 'scriptxscript' ),
			'underscores kept'   => array( 'rank_math', 'rank_math' ),
			'digits kept'        => array( 'wp2024', 'wp2024' ),
			'empty stays empty'  => array( '', '' ),
			'only junk empties'  => array( '!!!', '' ),
		);
	}

	/**
	 * The length guard truncates at MAX_KEY_LENGTH.
	 *
	 * These keys become array keys in stored options and fragments of ability names, so an
	 * unbounded value from a third-party definition would be persisted verbatim.
	 *
	 * @return void
	 */
	public function test_long_keys_are_truncated(): void {
		$clean = AcrossAI_Key_Sanitizer::key( str_repeat( 'a', AcrossAI_Key_Sanitizer::MAX_KEY_LENGTH + 50 ) );

		$this->assertSame( AcrossAI_Key_Sanitizer::MAX_KEY_LENGTH, strlen( $clean ) );
	}

	/**
	 * A key exactly at the limit is not shortened.
	 *
	 * @return void
	 */
	public function test_key_at_the_limit_is_untouched(): void {
		$exact = str_repeat( 'b', AcrossAI_Key_Sanitizer::MAX_KEY_LENGTH );

		$this->assertSame( $exact, AcrossAI_Key_Sanitizer::key( $exact ) );
	}

	/**
	 * Truncation happens after sanitisation, not before.
	 *
	 * Otherwise a long run of characters that sanitise away would eat the budget and return a key
	 * shorter than the limit while a valid tail was discarded.
	 *
	 * @return void
	 */
	public function test_truncation_applies_to_the_sanitised_value(): void {
		$raw = str_repeat( '/', 60 ) . str_repeat( 'c', AcrossAI_Key_Sanitizer::MAX_KEY_LENGTH );

		$this->assertSame(
			str_repeat( 'c', AcrossAI_Key_Sanitizer::MAX_KEY_LENGTH ),
			AcrossAI_Key_Sanitizer::key( $raw )
		);
	}
}
