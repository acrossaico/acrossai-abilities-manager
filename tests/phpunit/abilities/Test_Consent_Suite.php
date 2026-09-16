<?php
/**
 * Feature 118 — invariants across the cookie consent suite.
 *
 * The consent plugin is not loaded in the test harness, so these assert the suite's shape and the
 * decisions that must not be edited away. The behavioural half runs live against a real install.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.48
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Consent_Suite extends WP_UnitTestCase {

	/**
	 * Class => slug, for all 22.
	 *
	 * @return array<string,string>
	 */
	private static function inventory(): array {
		return array(
			'List_Cookies'                   => 'consent/list-cookies',
			'Get_Cookie'                     => 'consent/get-cookie',
			'Add_Cookie'                     => 'consent/add-cookie',
			'Update_Cookie'                  => 'consent/update-cookie',
			'Delete_Cookie'                  => 'consent/delete-cookie',
			'List_Categories'                => 'consent/list-categories',
			'Get_Category'                   => 'consent/get-category',
			'Update_Category'                => 'consent/update-category',
			'List_Banners'                   => 'consent/list-banners',
			'Get_Banner'                     => 'consent/get-banner',
			'Get_Banner_Status'              => 'consent/get-banner-status',
			'Rebuild_Banner'                 => 'consent/rebuild-banner',
			'Get_Consent_Settings'           => 'consent/get-consent-settings',
			'Update_Consent_Settings'        => 'consent/update-consent-settings',
			'Get_Google_Consent_Mode'        => 'consent/get-google-consent-mode',
			'Update_Google_Consent_Mode'     => 'consent/update-google-consent-mode',
			'List_Languages'                 => 'consent/list-languages',
			'Set_Languages'                  => 'consent/set-languages',
			'Get_Consent_Log_Statistics'     => 'consent/get-consent-log-statistics',
			'Get_Pageview_Statistics'        => 'consent/get-pageview-statistics',
			'Get_Scan_Status'                => 'consent/get-scan-status',
			'Get_Google_Consent_Mode_Status' => 'consent/get-google-consent-mode-status',
		);
	}

	/**
	 * The four that need the account, and nothing else.
	 *
	 * @return string[]
	 */
	private static function account_gated(): array {
		return array(
			'Get_Consent_Log_Statistics',
			'Get_Pageview_Statistics',
			'Get_Scan_Status',
			'Get_Google_Consent_Mode_Status',
		);
	}

	private static function dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Consent/';
	}

	private static function util(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Consent/';
	}

	private static function read( string $path ): string {
		return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	}

	/**
	 * @return string[]
	 */
	private static function ability_files(): array {
		$files = glob( self::dir() . '*.php' );

		return array_values(
			array_filter(
				is_array( $files ) ? $files : array(),
				static fn( string $f ): bool => ! in_array(
					basename( $f ),
					array( 'Base_Consent_Ability.php', 'Category_Registrar.php' ),
					true
				)
			)
		);
	}

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

	public function test_inventory_matches_the_directory(): void {
		$found = array_map( static fn( string $f ): string => basename( $f, '.php' ), self::ability_files() );

		sort( $found );
		$expected = array_keys( self::inventory() );
		sort( $expected );

		$this->assertSame( $expected, $found );
		$this->assertCount( 22, $found );
	}

	public function test_every_slug_is_declared_and_unique(): void {
		$slugs = array();

		foreach ( self::inventory() as $class => $slug ) {
			$this->assertStringContainsString(
				"return '" . $slug . "';",
				self::read( self::dir() . $class . '.php' ),
				"{$class} does not declare {$slug}."
			);
			$slugs[] = $slug;
		}

		$this->assertSame( count( $slugs ), count( array_unique( $slugs ) ) );
	}

	/**
	 * Nothing writes the consent tables except the repository.
	 *
	 * The banner a visitor receives is cached HTML, refreshed only when the plugin's own save hooks
	 * fire. A direct write inserts the row, reads it back correctly, and leaves every visitor on the
	 * previous banner. Measured: a direct insert left the rendered banner byte-identical at 30,909
	 * bytes, while the same write through an ability cleared it.
	 */
	public function test_no_ability_touches_the_database_directly(): void {
		foreach ( self::ability_files() as $file ) {
			$code = self::code_only( self::read( $file ) );

			foreach ( array( '$wpdb', 'update_option(', 'delete_option(' ) as $forbidden ) {
				$this->assertStringNotContainsString(
					$forbidden,
					$code,
					basename( $file ) . " uses {$forbidden} directly. Consent data belongs behind Consent_Repository."
				);
			}
		}
	}

	/**
	 * Every write proves the banner actually moved.
	 *
	 * Writing the row is not the proof — the rendered HTML is what a visitor sees.
	 */
	public function test_writes_prove_the_banner_changed(): void {
		$repo = self::code_only( self::read( self::util() . 'Consent_Repository.php' ) );

		$this->assertStringContainsString( 'function prove_template_moved', $repo );
		$this->assertStringContainsString( 'banner_not_refreshed', $repo );

		foreach ( array( 'create_cookie', 'update_cookie', 'delete_cookie', 'update_category' ) as $writer ) {
			$this->assertStringContainsString(
				'prove_template_moved(',
				self::method_body( $repo, $writer ),
				"{$writer}() must prove the banner refreshed before reporting success."
			);
		}
	}

	/**
	 * One method's body, bounded by the next method.
	 *
	 * A lazy `.*?` across the whole file is not enough: it happily runs past the closing brace and
	 * matches the next method's body, so a writer that lost its proof still passes. That is the same
	 * shape as the WPCode prefix assertion that certified its own bug — a test has to be bounded by
	 * the thing it claims to be testing.
	 */
	private static function method_body( string $src, string $method ): string {
		$start = strpos( $src, 'function ' . $method . '(' );

		if ( false === $start ) {
			return '';
		}

		$next = preg_match( '/\n\t(?:public|private|protected) static function /', $src, $m, PREG_OFFSET_CAPTURE, $start + 1 )
			? (int) $m[0][1]
			: strlen( $src );

		return substr( $src, $start, $next - $start );
	}

	/**
	 * A cleared template counts as proof, and is checked FIRST.
	 *
	 * The plugin empties the option on its save hooks and rebuilds on next need, so "pending rebuild"
	 * means the hook fired. Order matters: the first write clears it, and a second write in the same
	 * request then compares empty against empty. A fingerprint-only test calls that a failure —
	 * measured, create-then-update reported `banner_not_refreshed` on a perfectly good update.
	 */
	public function test_a_cleared_template_is_accepted_before_comparing(): void {
		$repo = self::code_only( self::read( self::util() . 'Consent_Repository.php' ) );

		$cleared = strpos( $repo, 'true === $state[\'pending_rebuild\']' );
		$compare = strpos( $repo, '$before === self::template_fingerprint()' );

		$this->assertNotFalse( $cleared, 'A cleared template must be accepted as proof.' );
		$this->assertNotFalse( $compare );
		$this->assertLessThan(
			$compare,
			$cleared,
			'The cleared-template check must come first, or consecutive writes report a false failure.'
		);
	}

	/**
	 * Multilingual fields are merged, never replaced.
	 *
	 * The plugin's setters rebuild these against the selected-language list and fill a missing
	 * language with an empty string, so writing one language blanks the others.
	 */
	public function test_multilingual_fields_are_merged(): void {
		$repo = self::code_only( self::read( self::util() . 'Consent_Repository.php' ) );

		$this->assertStringContainsString( 'function merge_multilingual', $repo );
		$this->assertStringContainsString( 'self::merge_multilingual(', $repo );
	}

	/**
	 * Credentials are never returned.
	 *
	 * The API token and the website key sit in the same option as ordinary settings, so a reader
	 * that returns the option wholesale hands them out. The Feature 106 lesson about Yoast's stored
	 * tokens, applied before it could bite.
	 */
	public function test_credentials_are_redacted(): void {
		$guard = self::code_only( self::read( self::util() . 'Consent_Guard.php' ) );

		$this->assertStringContainsString( "'token'", $guard );
		$this->assertStringContainsString( "'website_key'", $guard );
		$this->assertStringContainsString( 'function redact', $guard );

		$snapshot = self::code_only( self::read( self::util() . 'Consent_Repository.php' ) );
		$this->assertStringContainsString(
			'Consent_Guard::redact(',
			$snapshot,
			'The settings reader must pass through the redactor.'
		);
	}

	/**
	 * The settings writer is an allow-list.
	 *
	 * A passthrough would let a caller set the API token, and a naive merge would let one blank it.
	 */
	public function test_the_settings_writer_is_an_allow_list(): void {
		$repo = self::code_only( self::read( self::util() . 'Consent_Repository.php' ) );

		$this->assertMatchesRegularExpression(
			'/function update_settings\(.*?consent_log_status.*?function /s',
			$repo
		);
		$this->assertDoesNotMatchRegularExpression(
			'/function update_settings\(.*?update\( \$fields/s',
			$repo,
			'The supplied input must never be written wholesale.'
		);
	}

	/**
	 * Exactly four abilities need the account, and none of them writes.
	 */
	public function test_only_the_reporting_abilities_need_an_account(): void {
		$gated = array();

		foreach ( self::inventory() as $class => $slug ) {
			$src = self::code_only( self::read( self::dir() . $class . '.php' ) );

			if ( false !== strpos( $src, 'function requires_account' ) ) {
				$gated[] = $class;

				$this->assertStringContainsString(
					"'readonly'    => true",
					$src,
					"{$class} needs an account, so it must be a read: a write that cannot run is a trap."
				);
			}
		}

		sort( $gated );
		$expected = self::account_gated();
		sort( $expected );

		$this->assertSame( $expected, $gated );
	}

	/**
	 * The refusal is an instruction, not a dead end.
	 *
	 * An assistant handed a vague failure either gives up or silently retries the same call.
	 */
	public function test_the_account_refusal_says_what_to_do(): void {
		$guard = self::read( self::util() . 'Consent_Guard.php' );

		$this->assertStringContainsString( 'consent_account_not_connected', $guard );
		$this->assertStringContainsString( 'function connect_url', $guard );
		$this->assertStringContainsString( 'Connect button', $guard );
		$this->assertStringContainsString( 'tell me once it is done', $guard );
		$this->assertStringContainsString( "'connect_url' => self::connect_url()", $guard );
	}

	/**
	 * Nothing here links the account, and nothing reads the token.
	 *
	 * Linking authorises an external account, which is a person's decision. There is deliberately no
	 * "is it connected" ability either — that question is only asked once something has already
	 * failed for want of it, so the failing call answers it.
	 */
	public function test_nothing_connects_the_account(): void {
		foreach ( self::ability_files() as $file ) {
			$code = self::code_only( self::read( $file ) );

			foreach ( array( 'get_token', 'website_key', 'api.token', 'set_api_key' ) as $forbidden ) {
				$this->assertStringNotContainsString(
					$forbidden,
					$code,
					basename( $file ) . " touches {$forbidden}; credentials are never read or written here."
				);
			}
		}

		foreach ( array_values( self::inventory() ) as $slug ) {
			$this->assertStringNotContainsString( 'connect-account', $slug );
			$this->assertStringNotContainsString( 'get-connection', $slug );
		}
	}

	/**
	 * The account gate runs before the confirmation gate.
	 *
	 * Being asked to confirm an operation that cannot run either way wastes a round trip and reads
	 * as though confirming would help.
	 */
	public function test_the_account_gate_precedes_confirmation(): void {
		$base = self::code_only( self::read( self::dir() . 'Base_Consent_Ability.php' ) );

		// Scoped to execute(), not the whole file: the abstract declarations appear far earlier, so
		// a file-wide strpos finds those and tests nothing about the order things actually run in.
		$start = strpos( $base, 'public function execute(' );
		$this->assertNotFalse( $start, 'execute() must exist.' );

		$body = substr( $base, $start );

		$available = strpos( $body, 'assert_available' );
		$account   = strpos( $body, '$this->requires_account()' );
		$confirm   = strpos( $body, '$this->needs_confirmation_for( $input )' );
		$run       = strpos( $body, '$this->run( $input )' );

		foreach ( array( $available, $account, $confirm, $run ) as $offset ) {
			$this->assertNotFalse( $offset );
		}

		$this->assertLessThan( $account, $available, 'Availability is checked first.' );
		$this->assertLessThan( $confirm, $account, 'The account gate runs before the confirmation gate.' );
		$this->assertLessThan( $run, $confirm, 'Both gates run before the ability does.' );
	}

	/**
	 * Destructive and irreversible operations ask first.
	 */
	public function test_confirmation_is_on_the_right_abilities(): void {
		foreach ( array( 'Delete_Cookie', 'Set_Languages' ) as $class ) {
			$this->assertStringContainsString(
				'requires_confirmation',
				self::read( self::dir() . $class . '.php' ),
				"{$class} must be confirm-gated."
			);
		}

		foreach ( array( 'Add_Cookie', 'Update_Cookie', 'Update_Category', 'Rebuild_Banner' ) as $class ) {
			$this->assertStringNotContainsString(
				'protected function requires_confirmation',
				self::read( self::dir() . $class . '.php' ),
				"{$class} is reversible and should not ask for confirmation."
			);
		}
	}

	/**
	 * Whether a category is strictly necessary is not writable.
	 *
	 * It decides whether the category loads before any consent is given. Flipping it turns a real
	 * choice into a non-choice, which belongs to a person on the settings screen.
	 */
	public function test_prior_consent_cannot_be_changed(): void {
		$repo = self::code_only( self::read( self::util() . 'Consent_Repository.php' ) );

		$this->assertDoesNotMatchRegularExpression(
			'/\$payload\[.prior_consent.\]\s*=/',
			$repo,
			'prior_consent must not be writable.'
		);
		$this->assertStringNotContainsString(
			"'prior_consent'",
			self::code_only( self::read( self::dir() . 'Update_Category.php' ) )
		);
	}

	/**
	 * No compliance claims anywhere.
	 *
	 * Whether a site complies is a legal judgement about its situation. An ability may report the
	 * configuration and change it on request; it must never imply the result is compliance.
	 */
	public function test_nothing_claims_compliance(): void {
		$files = array_merge( self::ability_files(), array( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/Consent_Banner.php' ) );

		foreach ( $files as $file ) {
			$src = strtolower( self::read( $file ) );

			foreach ( array( 'makes your site compliant', 'is compliant', 'ensures compliance', 'gdpr compliant' ) as $claim ) {
				$this->assertStringNotContainsString(
					$claim,
					$src,
					basename( $file ) . ' claims compliance, which is a legal judgement and not ours to make.'
				);
			}
		}
	}

	/**
	 * Regenerating the banner cannot take the request down with it.
	 *
	 * Re-rendering needs the front-end context; on a REST request the plugin's own code fatals with
	 * "Call to a member function get_contents() on null". Measured before this was guarded.
	 */
	public function test_regeneration_is_caught(): void {
		$repo = self::code_only( self::read( self::util() . 'Consent_Repository.php' ) );

		$this->assertMatchesRegularExpression(
			'/try \{\s*\$template::get_instance\(\)->generate\(\);\s*\} catch \( \\\\Throwable/',
			$repo,
			'generate() fatals outside a front-end request and must be caught.'
		);
	}

	/**
	 * A string plan is kept, not discarded.
	 *
	 * `account.plan` is a bare string on a free account and an array on others. Coercing a non-array
	 * to `array()` advertised the field and returned nothing on exactly the accounts most likely to
	 * ask about it. Caught by connecting a real account and reading the answer.
	 */
	public function test_a_string_plan_is_not_thrown_away(): void {
		$repo = self::code_only( self::read( self::util() . 'Consent_Repository.php' ) );

		$this->assertStringContainsString( 'function shape_plan', $repo );
		$this->assertDoesNotMatchRegularExpression(
			'/is_array\( \$settings->get_plan\(\) \) \? \$settings->get_plan\(\) : array\(\)/',
			$repo,
			'A non-array plan must be kept, not flattened to an empty array.'
		);
		$this->assertStringContainsString( "array( 'slug' => \$slug )", $repo );
	}

	public function test_the_bootstrap_instantiates_every_ability(): void {
		$bootstrap = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );

		foreach ( array_keys( self::inventory() ) as $class ) {
			$this->assertStringContainsString( 'new Consent\\' . $class . '();', $bootstrap, "{$class} is never instantiated." );
		}

		$this->assertStringNotContainsString( 'new Consent\\Category_Registrar();', $bootstrap );
		$this->assertStringContainsString( "Consent\\Category_Registrar::instance(), 'register'", $bootstrap );
	}

	public function test_permission_floor_is_final_and_admin(): void {
		$base = self::read( self::dir() . 'Base_Consent_Ability.php' );

		$this->assertMatchesRegularExpression(
			'/final protected function permission_floor\(\): string \{\s*return \'manage_options\';/',
			$base
		);

		foreach ( self::ability_files() as $file ) {
			$this->assertStringNotContainsString( 'function permission_floor', self::read( $file ) );
		}
	}

	public function test_permission_filter_is_raise_only(): void {
		$guard = self::code_only( self::read( self::util() . 'Consent_Guard.php' ) );

		$this->assertMatchesRegularExpression(
			'/if\s*\(\s*!\s*current_user_can\(\s*\$floor\s*\)\s*\)\s*\{\s*return false;\s*\}/',
			$guard
		);
		$this->assertStringContainsString( 'apply_filters( self::PERMISSION_FILTER, true, $floor )', $guard );
	}

	public function test_repositories_are_final_and_static_only(): void {
		foreach ( array( 'Consent_Guard', 'Consent_Repository' ) as $class ) {
			$src = self::read( self::util() . $class . '.php' );

			$this->assertStringContainsString( 'final class ' . $class, $src );
			$this->assertStringContainsString( 'private function __construct()', $src );
		}
	}

	public function test_the_integration_claims_no_prefix(): void {
		$src = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/Consent_Banner.php' );

		$this->assertStringContainsString( "TAB_GROUP = 'cookieyes'", $src );
		$this->assertMatchesRegularExpression(
			'/function ability_prefixes\(\): array \{\s*return array\(\);/',
			$src,
			'The consent plugin registers nothing, so claiming a prefix would capture unrelated future abilities.'
		);
	}

	/**
	 * Slash_Input is deliberately absent.
	 *
	 * The consent plugin's own setters sanitise on the way in and none of them unslash, so slashing
	 * here would add a level nothing removes — the mistake Feature 116 measured twice.
	 */
	public function test_input_is_not_slashed(): void {
		foreach ( array_merge( self::ability_files(), array( self::util() . 'Consent_Repository.php' ) ) as $file ) {
			$this->assertStringNotContainsString( 'Slash_Input', self::read( $file ) );
		}
	}
}
