<?php
/**
 * Issue #202 — the Integrations tab must describe abilities that actually exist.
 *
 * The rows an integration declares in abilities() are display-only: they never reach
 * wp_get_abilities(), so nothing at runtime forces them to match what the host plugin registers.
 * That is exactly how they drifted — ACF declared `acf/post-types` and `acf/taxonomies`, which no
 * plugin has ever registered, while ACF's three `register-*` writers were absent. An operator could
 * block a slug that resolves to nothing and see no effect.
 *
 * These assertions run without the host plugins installed, so they check shape and self-consistency.
 * The live half — that every declared slug resolves against a running plugin — is asserted by
 * test_declared_slugs_resolve_when_the_host_is_active, which skips when the host is absent.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.44
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Integration_Row_Accuracy extends WP_UnitTestCase {

	private static function dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/';
	}

	private static function read( string $file ): string {
		$path = self::dir() . $file;

		return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	}

	/**
	 * Integrations that declare display-only rows, and the prefix those rows must sit under.
	 *
	 * @return array<string,string>
	 */
	private static function row_declaring_integrations(): array {
		return array(
			'ACF.php'              => 'acf/',
			'Yoast_Seo_Opt_In.php' => 'yoast-seo/',
		);
	}

	/**
	 * Slugs declared by one integration's abilities() block.
	 *
	 * @param  string $file Integration file name.
	 * @return array<int,string>
	 */
	private static function declared_slugs( string $file ): array {
		$src = self::read( $file );

		$start = strpos( $src, 'protected function abilities(): array' );

		if ( false === $start ) {
			return array();
		}

		preg_match_all( "/'slug'\s*=>\s*'([^']+)'/", substr( $src, $start ), $matches );

		return $matches[1];
	}

	public function test_every_declared_row_sits_under_the_claimed_prefix(): void {
		foreach ( self::row_declaring_integrations() as $file => $prefix ) {
			$slugs = self::declared_slugs( $file );

			$this->assertNotEmpty( $slugs, "{$file} declares no rows." );

			foreach ( $slugs as $slug ) {
				$this->assertStringStartsWith(
					$prefix,
					$slug,
					"{$file} declares {$slug}, which is outside the prefix it claims."
				);
			}
		}
	}

	public function test_declared_rows_are_unique(): void {
		foreach ( array_keys( self::row_declaring_integrations() ) as $file ) {
			$slugs = self::declared_slugs( $file );

			$this->assertSame(
				count( $slugs ),
				count( array_unique( $slugs ) ),
				"{$file} declares the same slug twice."
			);
		}
	}

	/**
	 * The two slugs that were wrong must not come back.
	 *
	 * Named explicitly rather than left to the live check, because the live check skips wherever ACF
	 * is not installed — which includes CI. A regression would otherwise ship unnoticed and only
	 * surface on a site that happens to run ACF.
	 */
	public function test_the_acf_slugs_that_drifted_stay_corrected(): void {
		$slugs = self::declared_slugs( 'ACF.php' );

		$this->assertNotContains( 'acf/post-types', $slugs, 'ACF registers acf/custom-post-types.' );
		$this->assertNotContains( 'acf/taxonomies', $slugs, 'ACF registers acf/custom-taxonomies.' );

		foreach (
			array(
				'acf/field-groups',
				'acf/register-field-group',
				'acf/custom-post-types',
				'acf/register-custom-post-type',
				'acf/custom-taxonomies',
				'acf/register-custom-taxonomy',
			) as $expected
		) {
			$this->assertContains( $expected, $slugs, "ACF registers {$expected} and it must be listed." );
		}
	}

	/**
	 * Against a real host plugin, every declared slug must resolve.
	 *
	 * This is the assertion that would have caught the drift at the moment ACF renamed its abilities.
	 * It skips when the host is absent so the suite stays runnable everywhere, which is also why the
	 * explicit test above exists.
	 */
	public function test_declared_slugs_resolve_when_the_host_is_active(): void {
		if ( ! function_exists( 'wp_get_abilities' ) || ! defined( 'ACF_VERSION' ) ) {
			$this->markTestSkipped( 'ACF is not installed, so its abilities cannot be resolved here.' );
		}

		$registered = array_keys( (array) wp_get_abilities() );

		if ( empty( preg_grep( '#^acf/#', $registered ) ) ) {
			$this->markTestSkipped( 'ACF is installed but its AI abilities are switched off.' );
		}

		foreach ( self::declared_slugs( 'ACF.php' ) as $slug ) {
			$this->assertContains(
				$slug,
				$registered,
				"The Integrations tab advertises {$slug}, but no plugin registers it."
			);
		}
	}

	/**
	 * An opt-in integration must not be constructed inside built_in().
	 *
	 * built_in() runs behind the `acrossai_toolset_integrations` filter and may be called more than
	 * once per request. These classes hook from their constructor, so a second construction attaches
	 * the enable filter twice and pushes the display rows twice. ACF's absence from that list is
	 * already documented for this reason; the same has to hold for every sibling.
	 */
	public function test_opt_in_integrations_are_constructed_once(): void {
		$registry = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/AcrossAI_Toolset_Integrations.php'
		);
		$main     = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Main.php' );

		foreach ( array( 'ACF', 'Yoast_Seo_Opt_In' ) as $class ) {
			$this->assertStringNotContainsString(
				'new ' . $class . '()',
				$registry,
				"{$class} hooks from its constructor and must not be built inside built_in()."
			);
			$this->assertStringContainsString(
				'Integrations\\' . $class . '()',
				$main,
				"{$class} must be constructed exactly once, from Main::define_public_hooks()."
			);
		}
	}

	/**
	 * Display-only rows must never be registered as abilities.
	 *
	 * This is the whole of #204. Registering them occupies the real name, and the registry refuses
	 * duplicates — so our placeholder decided, by hook order alone, whether an operator got the real
	 * ability. Measured before the fix: six ACF names contested on every request, and a Yoast opt-in
	 * that registered five dead placeholders and PREVENTED Yoast's real abilities existing, because
	 * Yoast registers on init, later than the Processor's P5.
	 */
	public function test_integration_rows_are_never_registered(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Modules/Library/AcrossAI_Ability_Library_Processor.php'
		);

		$this->assertMatchesRegularExpression(
			"/'integration' === \\\$definition\\['card_variant'\\][^;]*\\)[^;]*\\{\\s*continue;/",
			$src,
			'The Processor must skip display-only integration rows before registering.'
		);
	}

	/**
	 * Yoast's opt-in flips the conditional its abilities are actually gated on.
	 *
	 * Not an invented switch: Yoast registers behind Should_Index_Indexables_Conditional, which
	 * resolves to is_production_mode(). Measured on a WP_ENVIRONMENT_TYPE=local install — zero
	 * abilities under `yoast-seo/` until the filter is applied, then its own appear and execute.
	 */
	public function test_the_yoast_opt_in_targets_the_real_gate(): void {
		$src = self::read( 'Yoast_Seo_Opt_In.php' );

		$this->assertStringContainsString( "Yoast\\WP\\SEO\\should_index_indexables", $src );
		$this->assertStringContainsString( 'WPSEO_VERSION', $src );
		$this->assertStringContainsString( '\WPSEO_Options', $src, 'Two symbols, not one (SEC-002).' );
	}

	/**
	 * The count the tab shows is derived, never hand-maintained.
	 *
	 * discover() counts Library definitions rather than a literal, so the number cannot be edited out
	 * of step with the rows. Keeping it that way is the point.
	 */
	public function test_the_tab_count_is_derived_from_the_rows(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Modules/Library/AcrossAI_Integration_Settings.php'
		);

		$this->assertStringContainsString( "++\$found[ \$slug ]['count'];", $src );
		$this->assertDoesNotMatchRegularExpression(
			"/'count'\s*=>\s*[1-9]/",
			$src,
			'The ability count must be counted, never written as a literal.'
		);
	}
}
