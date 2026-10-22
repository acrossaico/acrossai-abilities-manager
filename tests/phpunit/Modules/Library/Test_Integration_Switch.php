<?php
/**
 * What the Integrations switch does, and what it must never do.
 *
 * The switch governs **this plugin's** contribution to an integration, and nothing else. It is
 * applied at registration: a switched-off integration's suite is never instantiated, so there is
 * nothing to refuse afterwards and no second mechanism that could disagree with the first.
 *
 * Three properties are worth protecting, and each has cost something to learn:
 *
 * 1. **An unseeded key is not a decision.** Default-on is a seeded `true` written at `init` P1,
 *    while `wp_abilities_api_init` fires lazily on whatever first touches the registry — which can
 *    be earlier. A gate asking "is this switched on" therefore reads absent-so-no on a fresh site
 *    and registers nothing at all, silently. It asks for an explicit `false` instead.
 * 2. **The switch does not reach first-party groups.** `content`, `blocks` and `appearance` have no
 *    integration behind them. If the gate could reach them, one absent option key would take the
 *    whole plugin offline.
 * 3. **It does not overrule the host plugin.** Elementor publishes 27 abilities of its own when a
 *    site owner enables them on Elementor's screen. Switching our integration off withdraws our
 *    contribution; it does not reach into another plugin's and switch that off too. The Toolset
 *    keeps serving them and says what is missing.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.42
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Integration_Settings;

/**
 * Covers the registration gate, its defaults, and the Toolset's switched-off behaviour.
 */
class Test_Integration_Switch extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array() );
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array() );

		parent::tearDown();
	}

	/**
	 * Read one of the plugin's files.
	 *
	 * @param  string $relative Path from the plugin root.
	 * @return string
	 */
	private static function source( string $relative ): string {
		return (string) file_get_contents( dirname( __DIR__, 4 ) . '/' . $relative );
	}

	/* ---------------------------------------------------------------------
	 * 1. Defaults.
	 * ------------------------------------------------------------------ */

	/**
	 * An unseeded integration is not "switched off".
	 *
	 * The assertion that keeps a fresh site working — see the file header.
	 *
	 * @return void
	 */
	public function test_an_unseeded_integration_is_not_switched_off(): void {
		$this->assertFalse(
			AcrossAI_Integration_Settings::is_switched_off( 'rank-math' ),
			'An absent key must never read as switched off, or a fresh site registers nothing.'
		);

		$this->assertFalse(
			AcrossAI_Integration_Settings::is_enabled( 'rank-math' ),
			'…while is_enabled() still reads absent as not-yet-on. The two differ on purpose.'
		);
	}

	/**
	 * Only a stored `false` switches an integration off.
	 *
	 * @return void
	 */
	public function test_only_an_explicit_false_switches_an_integration_off(): void {
		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array( 'rank-math' => false ) );
		$this->assertTrue( AcrossAI_Integration_Settings::is_switched_off( 'rank-math' ) );

		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array( 'rank-math' => true ) );
		$this->assertFalse( AcrossAI_Integration_Settings::is_switched_off( 'rank-math' ) );
	}

	/**
	 * A corrupt option never switches anything off.
	 *
	 * The option is a site option a filter can replace, so it is not guaranteed to be an array.
	 * Reading a scalar as "everything is off" would be the fresh-site failure by another route.
	 *
	 * @return void
	 */
	public function test_a_corrupt_option_switches_nothing_off(): void {
		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, 'not-an-array' );

		$this->assertFalse( AcrossAI_Integration_Settings::is_switched_off( 'rank-math' ) );
	}

	/**
	 * The two host-gating opt-ins default on, like every other integration.
	 *
	 * They drive a third-party plugin's own setting, which is why they were once the exception.
	 * They are not any more: a row reporting an integration as enabled must not be withholding part
	 * of it, and the two halves of one switch must not have opposite defaults.
	 *
	 * @return void
	 */
	public function test_host_gating_opt_ins_are_seeded_on(): void {
		foreach ( array( 'Elementor_Opt_In', 'Jetpack_Opt_In' ) as $class ) {
			$this->assertMatchesRegularExpression(
				'/function seeds_enabled\(\): bool \{\s*return true;/',
				self::source( 'includes/Abilities/Integrations/' . $class . '.php' ),
				$class . ' must seed on, or one switch gives two different answers.'
			);
		}
	}

	/**
	 * …and early enough to be right on the first request.
	 *
	 * The opt-in base reads its toggle at `plugins_loaded` P20; the discovery seed runs at `init`
	 * P1, which is later. Left to discovery these two would be correct from the second request
	 * only, so a fresh site would show 81 Elementor abilities and then 106.
	 *
	 * @return void
	 */
	public function test_host_gating_opt_ins_are_seeded_before_the_base_reads_them(): void {
		$source = self::source( 'includes/Modules/Library/AcrossAI_Integration_Default_Opt_Ins.php' );

		foreach ( array( 'elementor', 'jetpack' ) as $slug ) {
			$this->assertMatchesRegularExpression(
				"/DEFAULT_ON = array\([^)]*'" . $slug . "'[^)]*\)/",
				$source,
				$slug . ' must be in the early pass, not left to discovery.'
			);
		}
	}

	/* ---------------------------------------------------------------------
	 * 2. The gate.
	 * ------------------------------------------------------------------ */

	/**
	 * Every integration suite the bootstrap registers sits behind the switch.
	 *
	 * The gate is 17 hand-written call sites, each naming its group as a string literal. Nothing
	 * about adding an 18th integration forces you to add the gate with it, and forgetting is
	 * invisible: the suite works, the Integrations screen offers a switch for it, and the switch
	 * does nothing.
	 *
	 * Reads the whole file deliberately. An earlier version took a fixed-size window from
	 * `register_abilities()` and captured 6 of the 17, so deleting a gate still compared equal and
	 * this passed. Nothing else in the file writes `$this->integration_on( '...' )`.
	 *
	 * @return void
	 */
	public function test_every_registered_suite_is_behind_the_switch(): void {
		$source = self::source( 'includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );

		preg_match_all( '/\$this->register_([a-z0-9_]+)_abilities\(\);/', $source, $calls );
		preg_match_all( '/\$this->integration_on\( \'([a-z0-9-]+)\' \)/', $source, $gates );

		$this->assertNotEmpty( $calls[1], 'No suite registrations found — the pattern moved.' );

		// Every suite except the Elementor Pro subset, which sits inside the already-gated free
		// block and must not carry a second gate of its own.
		$suites = array_values( array_diff( $calls[1], array( 'elementor_pro' ) ) );

		$this->assertSame(
			count( $suites ),
			count( $gates[1] ),
			sprintf(
				'%d suites register but %d are gated. A new integration was added without putting '
					. 'it behind the Integrations switch.',
				count( $suites ),
				count( $gates[1] )
			)
		);
	}

	/**
	 * The gate asks the safe question.
	 *
	 * @return void
	 */
	public function test_the_gate_asks_whether_the_integration_was_switched_off(): void {
		$source = self::source( 'includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );

		$this->assertStringContainsString(
			'AcrossAI_Integration_Settings::is_switched_off( $group )',
			$source,
			'Asking is_enabled() here registers nothing on a fresh site. See the file header.'
		);
	}

	/**
	 * The host-presence checks survive alongside the switch.
	 *
	 * Switching an integration on cannot conjure the plugin it integrates with, so the gate must
	 * have been added to the presence test rather than replacing it.
	 *
	 * @return void
	 */
	public function test_the_switch_did_not_replace_the_host_presence_checks(): void {
		$source = self::source( 'includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );

		foreach ( array( '\Elementor\Plugin', '\RankMath\Helper', 'WPCF7_ContactForm' ) as $symbol ) {
			$this->assertStringContainsString(
				"class_exists( '" . $symbol . "' ) && \$this->integration_on(",
				$source,
				'The host check for ' . $symbol . ' must still gate registration.'
			);
		}
	}

	/* ---------------------------------------------------------------------
	 * 3. The Toolset.
	 * ------------------------------------------------------------------ */

	/**
	 * Nothing refuses or hides a host's abilities any more.
	 *
	 * There was briefly a second mechanism — a refusal on `acrossai_ability_access_refused` and a
	 * matching Toolset-visibility filter — written when the switch worked by refusing rather than
	 * by not registering. Once the gate landed, those only ever applied to abilities the gate had
	 * not removed, which is exactly the host's own: a site owner who enabled Elementor's MCP on
	 * Elementor's screen found them registered, listed in the admin, and refused on use.
	 *
	 * @return void
	 */
	public function test_no_second_mechanism_refuses_a_hosts_abilities(): void {
		$this->assertFileDoesNotExist(
			dirname( __DIR__, 4 ) . '/includes/Modules/Library/AcrossAI_Integration_Access.php',
			'The refusal layer is gone; the gate is the whole switch.'
		);

		$this->assertStringNotContainsString(
			'acrossai_integration_access',
			strtolower( self::source( 'includes/Main.php' ) ),
			'…and nothing should still be booting it.'
		);
	}
}
