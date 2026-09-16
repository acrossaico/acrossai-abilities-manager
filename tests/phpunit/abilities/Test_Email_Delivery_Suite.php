<?php
/**
 * Feature 119 — invariants across the email delivery suite and the adopted anti-spam group.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.49
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Email_Delivery_Suite extends WP_UnitTestCase {

	/**
	 * @return array<string,string>
	 */
	private static function inventory(): array {
		return array(
			'Get_Delivery_Settings'    => 'email/get-delivery-settings',
			'Get_Delivery_Status'      => 'email/get-delivery-status',
			'Send_Test_Email'          => 'email/send-test-email',
			'Update_Delivery_Settings' => 'email/update-delivery-settings',
		);
	}

	private static function dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Email/';
	}

	private static function util(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Email/';
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
					array( 'Base_Email_Ability.php', 'Category_Registrar.php' ),
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
		$this->assertCount( 4, $found );
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
	 * A credential is never read through the mail plugin's own accessor.
	 *
	 * `Options::get()` ends in `Crypto::decrypt()` (src/Options.php:439), so asking it for the SMTP
	 * password returns the SMTP password. Presence is judged from the raw stored value instead, so no
	 * decrypt ever happens. This is the Feature 106 lesson about stored tokens with a sharper edge:
	 * here the plugin decrypts on your behalf.
	 */
	public function test_credentials_are_never_decrypted(): void {
		$repo  = self::code_only( self::read( self::util() . 'Email_Repository.php' ) );
		$guard = self::code_only( self::read( self::util() . 'Email_Guard.php' ) );

		$this->assertStringContainsString( 'function raw_options', $guard );
		$this->assertStringContainsString( "get_option( self::OPTION", $guard );
		$this->assertStringContainsString( 'CREDENTIAL_KEYS', $guard );

		foreach ( array( 'Options::init()', 'Crypto::decrypt', '->get( \'smtp\'' ) as $forbidden ) {
			$this->assertStringNotContainsString(
				$forbidden,
				$repo,
				"The repository must not reach a credential through {$forbidden}."
			);
		}
	}

	/**
	 * No ability returns a credential value; presence only.
	 */
	public function test_no_ability_returns_a_credential(): void {
		$repo = self::code_only( self::read( self::util() . 'Email_Repository.php' ) );

		$this->assertMatchesRegularExpression(
			"/\\\$credentials\\[ \\\$group \\. '\\.' \\. \\\$key \\] = '' !== \\(string\\) \\\$value;/",
			$repo,
			'A credential must be reported as set or not set, never by value.'
		);
		$this->assertStringContainsString( "'withheld'", $repo, 'What was withheld must be reported.' );

		foreach ( self::ability_files() as $file ) {
			$this->assertStringNotContainsString( 'Crypto', self::code_only( self::read( $file ) ) );
		}
	}

	/**
	 * Acceptance by the mailer is never reported as delivery.
	 *
	 * Two measured reasons. `is_successful()` returns true for SUCCESS **or FAILED_DOMAIN_CHECK**
	 * (TestEmail.php:189-192), so a failed domain check reads as a pass. And with the `mail` mailer
	 * it means only that PHP's mail() accepted the message: sending to `probe@example.invalid`, a
	 * domain that cannot exist, returned success. An earlier version of this ability reported
	 * `delivered: true` for exactly that send — the failure this suite exists to expose, committed by
	 * the suite itself.
	 */
	public function test_acceptance_is_not_reported_as_delivery(): void {
		$repo = self::code_only( self::read( self::util() . 'Email_Repository.php' ) );

		$this->assertStringContainsString( "'accepted_by_mailer'", $repo );
		$this->assertStringContainsString( "'proves_delivery'", $repo );
		$this->assertStringContainsString( "'domain_check_passed'", $repo );
		$this->assertStringNotContainsString(
			"'delivered'",
			$repo,
			'"delivered" claims something the mailer cannot tell us.'
		);
		$this->assertMatchesRegularExpression(
			"/'proves_delivery'\\s*=> 'mail' !== \\\$mailer/",
			$repo,
			'PHP mail() acceptance must never count as proof.'
		);
	}

	/**
	 * FAILED_DOMAIN_CHECK is distinguished, not swallowed.
	 */
	public function test_a_failed_domain_check_is_surfaced(): void {
		$repo = self::code_only( self::read( self::util() . 'Email_Repository.php' ) );

		$this->assertStringContainsString( 'FAILED_DOMAIN_CHECK', $repo );
	}

	/**
	 * Every key the repository returns is declared in the ability's output schema.
	 *
	 * Undeclared keys are rejected by `additionalProperties: false` AFTER the work is done. Measured:
	 * adding the honest result keys without declaring them made the test send go out and then fail
	 * with `ability_invalid_output` — the email left the site and the caller got an error. Sibling of
	 * BUG-ARRAY-TYPED-OUTPUT-IS-A-JSON-OBJECT.
	 */
	public function test_returned_keys_are_declared(): void {
		$repo   = self::read( self::util() . 'Email_Repository.php' );
		$schema = self::read( self::dir() . 'Send_Test_Email.php' );

		foreach ( array( 'recipient', 'mailer', 'accepted_by_mailer', 'domain_check_passed', 'proves_delivery', 'notes' ) as $key ) {
			$this->assertStringContainsString(
				"'" . $key . "'",
				$repo,
				"The repository is expected to return {$key}."
			);
			$this->assertStringContainsString(
				"'" . $key . "'",
				$schema,
				"{$key} is returned but not declared in the output schema; the response is rejected after the send."
			);
		}
	}

	/**
	 * The test send asks first, and never guesses a recipient.
	 */
	public function test_the_test_send_is_gated_and_explicit(): void {
		$src  = self::read( self::dir() . 'Send_Test_Email.php' );
		$repo = self::code_only( self::read( self::util() . 'Email_Repository.php' ) );

		$this->assertStringContainsString( 'requires_confirmation', $src );
		$this->assertStringContainsString( "array( 'recipient' )", $src, 'The recipient must be required.' );
		$this->assertStringNotContainsString(
			'get_option( \'admin_email\'',
			$repo,
			'The recipient must never be defaulted to the administrator.'
		);
	}

	/**
	 * The mailer and its credentials cannot be written.
	 *
	 * Switching mailer without the matching credentials stops all email on the site, password resets
	 * included.
	 */
	public function test_the_mailer_and_credentials_are_not_writable(): void {
		$repo = self::code_only( self::read( self::util() . 'Email_Repository.php' ) );
		$src  = self::code_only( self::read( self::dir() . 'Update_Delivery_Settings.php' ) );

		$start = strpos( $repo, 'function update_settings(' );
		$this->assertNotFalse( $start );
		$body = substr( $repo, $start, strpos( $repo, 'function send_test(' ) - $start );

		foreach ( array( "'mailer'", "'pass'", "'api_key'", "'client_secret'" ) as $forbidden ) {
			$this->assertStringNotContainsString(
				$forbidden,
				$body,
				"update_settings() must not write {$forbidden}."
			);
		}

		foreach ( array( "'mailer'", "'pass'", "'api_key'" ) as $forbidden ) {
			$this->assertStringNotContainsString( $forbidden, $src );
		}
	}

	/**
	 * Both groups adopt with a BARE prefix.
	 *
	 * The tagger matches the segment before the first slash, so a trailing slash never matches (#209).
	 */
	public function test_prefixes_are_bare(): void {
		$email = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/Email_Delivery.php' );
		$spam  = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/Anti_Spam.php' );

		$this->assertStringContainsString( "return array( 'wp-mail-smtp' );", $email );
		$this->assertStringContainsString( "return array( 'akismet' );", $spam );
		$this->assertStringContainsString( "TAB_GROUP = 'email'", $email );
		$this->assertStringContainsString( "TAB_GROUP = 'anti-spam'", $spam );
	}

	/**
	 * The anti-spam group is adopt-only.
	 */
	public function test_the_anti_spam_group_registers_nothing(): void {
		$bootstrap = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );

		$this->assertStringNotContainsString( 'new Anti_Spam\\', $bootstrap );
		$this->assertStringNotContainsString(
			'akismet/',
			self::code_only( self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/Anti_Spam.php' ) ),
			'Adoption is tagging; no ability name is declared here.'
		);
	}

	public function test_the_bootstrap_instantiates_every_ability(): void {
		$bootstrap = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );

		foreach ( array_keys( self::inventory() ) as $class ) {
			$this->assertStringContainsString( 'new Email\\' . $class . '();', $bootstrap, "{$class} is never instantiated." );
		}

		$this->assertStringNotContainsString( 'new Email\\Category_Registrar();', $bootstrap );
		$this->assertStringContainsString( "Email\\Category_Registrar::instance(), 'register'", $bootstrap );
	}

	public function test_permission_floor_is_final_and_admin(): void {
		$base = self::read( self::dir() . 'Base_Email_Ability.php' );

		$this->assertMatchesRegularExpression(
			'/final protected function permission_floor\(\): string \{\s*return \'manage_options\';/',
			$base
		);

		foreach ( self::ability_files() as $file ) {
			$this->assertStringNotContainsString( 'function permission_floor', self::read( $file ) );
		}
	}

	public function test_permission_filter_is_raise_only(): void {
		$guard = self::code_only( self::read( self::util() . 'Email_Guard.php' ) );

		$this->assertMatchesRegularExpression(
			'/if\s*\(\s*!\s*current_user_can\(\s*\$floor\s*\)\s*\)\s*\{\s*return false;\s*\}/',
			$guard
		);
		$this->assertStringContainsString( 'apply_filters( self::PERMISSION_FILTER, true, $floor )', $guard );
	}

	public function test_repositories_are_final_and_static_only(): void {
		foreach ( array( 'Email_Guard', 'Email_Repository' ) as $class ) {
			$src = self::read( self::util() . $class . '.php' );

			$this->assertStringContainsString( 'final class ' . $class, $src );
			$this->assertStringContainsString( 'private function __construct()', $src );
		}
	}

	public function test_input_is_not_slashed(): void {
		foreach ( array_merge( self::ability_files(), array( self::util() . 'Email_Repository.php' ) ) as $file ) {
			$this->assertStringNotContainsString( 'Slash_Input', self::read( $file ) );
		}
	}
}
