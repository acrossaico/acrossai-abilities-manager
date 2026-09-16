<?php
/**
 * Feature 117 — the WPForms integration: adoption, the write toggle, and the default-on seed.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.47
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\WPForms;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Integration_Default_Opt_Ins;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Integration_Settings;
use WP_UnitTestCase;

class Test_WPForms_Integration extends WP_UnitTestCase {

	/**
	 * The eight abilities WPForms registers, copied from its registration calls.
	 *
	 * @return string[]
	 */
	private static function wpforms_abilities(): array {
		return array(
			'wpforms/list-forms',
			'wpforms/get-form',
			'wpforms/describe-editing-schema',
			'wpforms/get-form-stats',
			'wpforms/create-form',
			'wpforms/update-form-settings',
			'wpforms/add-field',
			'wpforms/update-field',
		);
	}

	private static function src( string $relative ): string {
		$path = dirname( __DIR__, 3 ) . '/' . $relative;

		return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	}

	private function integration(): WPForms {
		return new WPForms();
	}

	/**
	 * Reset only the two keys this class writes.
	 *
	 * The stub store is shared and static across the whole run, so clearing it wholesale would take
	 * other suites' seeded site options with it. There is no delete_site_option() stub; an empty
	 * value is equivalent here because both reads test for truthiness or for a present key.
	 */
	protected function tearDown(): void {
		update_site_option( AcrossAI_Integration_Default_Opt_Ins::DONE_OPTION, '' );
		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array() );

		parent::tearDown();
	}

	/**
	 * Source with comments stripped.
	 *
	 * Required wherever the assertion is about what the CODE does: this file's own docblock names
	 * `wp_register_ability` while explaining that WPForms' registration is unconditional, and a raw
	 * substring check cannot tell that apart from a call.
	 */
	private static function code_only( string $src ): string {
		$out = '';

		foreach ( token_get_all( $src ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}

			$out .= is_array( $token ) ? $token[1] : $token;
		}

		return $out;
	}

	public function test_it_claims_the_wpforms_group(): void {
		$integration = $this->integration();

		$this->assertSame( 'wpforms', WPForms::TAB_GROUP );
		$this->assertSame( WPForms::TAB_GROUP, $integration->group() );
		$this->assertSame( 'WPForms', $integration->toolset_label() );
	}

	/**
	 * The prefix is bare.
	 *
	 * `AcrossAI_Ability_Group_Tagger` takes the segment BEFORE the first slash and looks THAT up, so
	 * a prefix containing a slash can never match and the abilities stay in the catch-all while
	 * appearing to be claimed. Issue #209 is exactly this, live.
	 */
	public function test_the_prefix_has_no_slash(): void {
		$this->assertSame( array( 'wpforms' ), $this->integration()->ability_prefixes() );
	}

	/**
	 * No integration declares a prefix that cannot match.
	 *
	 * The guard that would have caught #209 before it shipped. WPCode is excluded because it IS
	 * #209 and is being fixed separately — remove it from the exclusion list with that fix, do not
	 * relax the assertion.
	 */
	public function test_no_integration_declares_an_unmatchable_prefix(): void {
		$known_broken = array( 'WPCode.php' );
		$files        = glob( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/*.php' );
		$offenders    = array();

		foreach ( is_array( $files ) ? $files : array() as $file ) {
			if ( in_array( basename( $file ), $known_broken, true ) ) {
				continue;
			}

			$src = (string) file_get_contents( $file );

			if ( ! preg_match( '/function ability_prefixes\(\): array \{\s*return array\(([^)]*)\);/', $src, $m ) ) {
				continue;
			}

			if ( false !== strpos( $m[1], '/' ) ) {
				$offenders[] = basename( $file ) . ' => ' . trim( $m[1] );
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			'A prefix containing "/" never matches; the tagger looks up the segment before the slash: ' . implode( ', ', $offenders )
		);
	}

	public function test_it_declares_all_eight_abilities(): void {
		$src = self::src( 'includes/Abilities/Integrations/WPForms.php' );

		foreach ( self::wpforms_abilities() as $slug ) {
			$this->assertStringContainsString( "'" . $slug . "'", $src, "{$slug} is not declared." );
		}

		$this->assertSame(
			8,
			substr_count( $src, "'slug'        => 'wpforms/" ),
			'The display list must hold exactly the eight abilities WPForms registers.'
		);
	}

	/**
	 * Nothing here re-registers a WPForms name.
	 *
	 * Adoption is tagging. Registering a name WPForms already owns meets the duplicate refusal, and
	 * which side survives depends only on load order.
	 */
	public function test_it_registers_nothing(): void {
		$this->assertStringNotContainsString(
			'wp_register_ability',
			self::code_only( self::src( 'includes/Abilities/Integrations/WPForms.php' ) )
		);
	}

	/**
	 * The write filter is attached only through enable_filter().
	 *
	 * The base class calls that method solely when the toggle is on and WPForms is active, so it is
	 * the single place a write becomes permitted. Attaching the filter anywhere else would make the
	 * toggle decorative.
	 */
	public function test_the_write_filter_is_attached_only_when_enabled(): void {
		$src = self::src( 'includes/Abilities/Integrations/WPForms.php' );

		$this->assertSame( 'wpforms_integrations_abilities_allow_write', WPForms::WRITE_FILTER );
		$this->assertSame(
			1,
			substr_count( $src, 'add_filter( self::WRITE_FILTER' ),
			'The write filter must be attached exactly once, from enable_filter().'
		);
		$this->assertMatchesRegularExpression(
			'/protected function enable_filter\(\): void \{\s*add_filter\( self::WRITE_FILTER, \'__return_true\' \);\s*\}/',
			$src
		);
	}

	/**
	 * Presence is proven by two symbols, not one (SEC-002).
	 */
	public function test_presence_uses_two_symbols(): void {
		$src = self::src( 'includes/Abilities/Integrations/WPForms.php' );

		$this->assertStringContainsString(
			"defined( 'WPFORMS_VERSION' ) && function_exists( 'wpforms_setting' )",
			$src
		);
	}

	/**
	 * It must not be in built_in().
	 *
	 * Its constructor hooks plugins_loaded and acrossai_abilities_api_init, so a second instance
	 * would push every display row twice — the reason ACF is absent from that list too.
	 */
	public function test_it_is_constructed_once_and_not_in_the_registry(): void {
		$registry = self::src( 'includes/Abilities/Integrations/AcrossAI_Toolset_Integrations.php' );
		$main     = self::src( 'includes/Main.php' );

		$this->assertStringNotContainsString( 'new WPForms()', $registry );
		$this->assertSame( 1, substr_count( $main, 'Integrations\\WPForms();' ) );
		$this->assertStringContainsString( 'acrossai_toolset_integrations', self::src( 'includes/Abilities/Integrations/WPForms.php' ) );
	}

	public function test_the_seed_switches_wpforms_on(): void {
		AcrossAI_Integration_Default_Opt_Ins::maybe_seed();

		$this->assertTrue( AcrossAI_Integration_Settings::is_enabled( 'wpforms' ) );
	}

	/**
	 * A deliberate OFF survives the seed.
	 *
	 * The seed fills holes only. Overwriting a stored false would switch form writing back on behind
	 * the administrator who turned it off, which is the one outcome a default-on toggle must never
	 * produce.
	 */
	public function test_the_seed_never_overwrites_a_deliberate_off(): void {
		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array( 'wpforms' => false ) );

		AcrossAI_Integration_Default_Opt_Ins::maybe_seed();

		$this->assertFalse( AcrossAI_Integration_Settings::is_enabled( 'wpforms' ) );
	}

	/**
	 * The default is applied once, and the decision is not remade.
	 *
	 * Tested by removing the entry entirely rather than setting it to false: a stored false is
	 * already protected by the fill-holes-only rule, so that scenario passes even with the guard
	 * deleted and proves nothing about it. An ABSENT entry is the case only the guard can answer —
	 * without it, anything that clears the option (a restored backup, a reset tool) silently
	 * re-enables form writing on the next request.
	 */
	public function test_the_seed_decides_once_and_does_not_reseed(): void {
		AcrossAI_Integration_Default_Opt_Ins::maybe_seed();
		$this->assertTrue( AcrossAI_Integration_Settings::is_enabled( 'wpforms' ) );

		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array() );
		AcrossAI_Integration_Default_Opt_Ins::maybe_seed();

		$this->assertFalse(
			AcrossAI_Integration_Settings::is_enabled( 'wpforms' ),
			'The guard records that the default was already applied; a second run must not re-apply it.'
		);
	}

	/**
	 * Other integrations are left alone.
	 */
	public function test_the_seed_touches_only_declared_slugs(): void {
		AcrossAI_Integration_Default_Opt_Ins::maybe_seed();

		$this->assertFalse( AcrossAI_Integration_Settings::is_enabled( 'acf' ) );
		$this->assertFalse( AcrossAI_Integration_Settings::is_enabled( 'yoast-seo' ) );
	}

	/**
	 * A WPForms instance that believes the plugin is present.
	 *
	 * The unit harness has no WPForms, so `is_plugin_active()` is false and every refusal would
	 * short-circuit. Overriding the one protected predicate is narrower than defining WPFORMS_VERSION
	 * globally, which would leak into every later test in the run.
	 */
	private function active_integration(): WPForms {
		return new class() extends WPForms {
			protected function is_plugin_active(): bool {
				return true;
			}
		};
	}

	/**
	 * With the toggle off, the four writes are refused and the four reads are not.
	 *
	 * This is the switch actually working. Enforced here rather than through WPForms' own gate
	 * because that gate is unreachable: it lives in a `permission_callback` the override processor
	 * replaces, and WPForms does not re-check inside execute. Measured before this existed — toggle
	 * off, WPForms' filter false, `wpforms/create-form` still ALLOWED.
	 */
	public function test_writes_are_refused_while_the_toggle_is_off(): void {
		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array( 'wpforms' => false ) );

		$integration = $this->active_integration();

		foreach ( WPForms::WRITE_ABILITIES as $slug ) {
			$this->assertTrue(
				$integration->refuse_writes_when_disabled( false, $slug ),
				"{$slug} must be refused while form writing is switched off."
			);
		}

		foreach ( array( 'wpforms/list-forms', 'wpforms/get-form', 'wpforms/describe-editing-schema', 'wpforms/get-form-stats' ) as $slug ) {
			$this->assertFalse(
				$integration->refuse_writes_when_disabled( false, $slug ),
				"{$slug} only reads; the write switch must not touch it."
			);
		}
	}

	public function test_writes_are_permitted_while_the_toggle_is_on(): void {
		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array( 'wpforms' => true ) );

		$integration = $this->active_integration();

		foreach ( WPForms::WRITE_ABILITIES as $slug ) {
			$this->assertFalse( $integration->refuse_writes_when_disabled( false, $slug ) );
		}
	}

	/**
	 * It never touches another plugin's abilities.
	 */
	public function test_the_refusal_is_scoped_to_wpforms(): void {
		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array( 'wpforms' => false ) );

		$integration = $this->active_integration();

		foreach ( array( 'acf/register-field-group', 'core/get-site-info', 'content/update-post' ) as $slug ) {
			$this->assertFalse( $integration->refuse_writes_when_disabled( false, $slug ) );
		}
	}

	/**
	 * Nothing is refused when WPForms is absent.
	 */
	public function test_nothing_is_refused_without_wpforms(): void {
		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array( 'wpforms' => false ) );

		$this->assertFalse( ( new WPForms() )->refuse_writes_when_disabled( false, 'wpforms/create-form' ) );
	}

	/**
	 * The hook is deny-only.
	 *
	 * It runs only after access has been granted, and the result is negated — so a callback can turn
	 * an allow into a deny and never the reverse. A filter able to widen access would hand any
	 * plugin on the site the power to unlock every ability, which is the same reasoning as
	 * PATTERN-FILTERABLE-CAPABILITY-RAISE-ONLY.
	 */
	public function test_the_access_hook_cannot_grant(): void {
		$src = self::code_only( self::src( 'includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php' ) );

		$this->assertMatchesRegularExpression(
			'/if \( ! self::resolve_access\( \$slug, \$user_id \) \) \{\s*return false;\s*\}/',
			$src,
			'A denied decision must return before the filter runs, or the filter could grant access.'
		);
		$this->assertStringContainsString(
			'return ! (bool) apply_filters( \'acrossai_ability_access_refused\', false, $slug, $user_id );',
			$src
		);
	}

	/**
	 * The refusal is attached unconditionally.
	 *
	 * enable_filter() runs only when the toggle is ON. Refusal is the OFF behaviour, so hanging it
	 * off that hook would mean it could never fire — the switch would look wired and do nothing.
	 */
	public function test_the_refusal_is_attached_outside_enable_filter(): void {
		$src = self::code_only( self::src( 'includes/Abilities/Integrations/WPForms.php' ) );

		$this->assertMatchesRegularExpression(
			'/public function __construct\(\).*?acrossai_ability_access_refused.*?\n\t\}/s',
			$src,
			'The refusal filter must be attached from the constructor, not from enable_filter().'
		);
	}

	/**
	 * The toolset description names the switch.
	 *
	 * An assistant that meets `wpforms_writes_disabled` without knowing a switch exists either gives
	 * up or retries the same call.
	 */
	public function test_the_description_explains_the_write_switch(): void {
		$description = $this->integration()->toolset_description();

		$this->assertStringContainsString( 'wpforms_writes_disabled', $description );
		$this->assertStringContainsString( 'write switch', $description );
	}
}
