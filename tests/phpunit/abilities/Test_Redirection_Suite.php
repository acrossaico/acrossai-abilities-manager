<?php
/**
 * Feature 130 — invariants across the Redirection ability suite.
 *
 * Redirection is not loaded in the test harness, so these assert the suite's shape and the decisions
 * that must not be edited away. The behavioural half runs live against a real Redirection install.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.43
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Redirection_Suite extends WP_UnitTestCase {

	/**
	 * Class => slug, for all 8.
	 *
	 * @return array<string,string>
	 */
	private static function inventory(): array {
		return array(
			'List_Redirects'   => 'redirects/list-redirects',
			'List_Groups'      => 'redirects/list-groups',
			'List_404_Errors'  => 'redirects/list-404-errors',
			'List_Hit_Log'     => 'redirects/list-hit-log',
			'Get_Settings'     => 'redirects/get-settings',
			'Create_Redirect'  => 'redirects/create-redirect',
			'Update_Redirect'  => 'redirects/update-redirect',
			'Delete_Redirects' => 'redirects/delete-redirects',
		);
	}

	private static function dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Redirection/';
	}

	private static function util(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Redirection/';
	}

	private static function read( string $path ): string {
		return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	}

	/**
	 * Strip comments so an assertion cannot match a docblock that merely names a forbidden symbol.
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
					array( 'Base_Redirection_Ability.php', 'Category_Registrar.php' ),
					true
				)
			)
		);
	}

	public function test_inventory_matches_the_directory(): void {
		$found = array_map( static fn( string $f ): string => basename( $f, '.php' ), self::ability_files() );

		sort( $found );
		$expected = array_keys( self::inventory() );
		sort( $expected );

		$this->assertSame( $expected, $found );
		$this->assertCount( 8, $found );
	}

	public function test_every_slug_is_declared_and_unique(): void {
		$slugs = array();

		foreach ( self::inventory() as $class => $slug ) {
			$suffix = substr( $slug, strlen( 'redirects/' ) );
			$this->assertStringContainsString(
				"return '" . $suffix . "';",
				self::read( self::dir() . $class . '.php' ),
				"{$class} does not declare {$slug}."
			);
			$slugs[] = $slug;
		}

		$this->assertSame( count( $slugs ), count( array_unique( $slugs ) ) );
	}

	/**
	 * Only the repository names a \Red_* symbol or red_get_options().
	 *
	 * Redirection's model shapes are not a public contract. Keeping every reference in one file is
	 * what lets the suite degrade to a WP_Error instead of fataling when the plugin restructures.
	 */
	public function test_only_the_repository_touches_redirection_internals(): void {
		$forbidden = array( 'Red_Item', 'Red_Group', 'Red_404_Log', 'Red_Redirect_Log', 'Red_Log', 'red_get_options' );

		foreach ( self::ability_files() as $file ) {
			$code = self::code_only( self::read( $file ) );
			foreach ( $forbidden as $symbol ) {
				$this->assertStringNotContainsString(
					$symbol,
					$code,
					basename( $file ) . " names {$symbol} directly. All Redirection access belongs in Redirects_Repository."
				);
			}
		}
	}

	/**
	 * No ability writes the redirect tables directly.
	 *
	 * A redirect is not one row: it is a sanitized source, match flags, an action payload and a
	 * module cache flush, all of which Red_Item::create() does and a raw INSERT does not. A direct
	 * write produces a redirect that is stored but never fires.
	 */
	public function test_no_ability_touches_the_database_directly(): void {
		foreach ( self::ability_files() as $file ) {
			$code = self::code_only( self::read( $file ) );

			foreach ( array( '$wpdb', '->query(', '->insert(', '->update(', 'INSERT INTO', 'DELETE FROM' ) as $needle ) {
				$this->assertStringNotContainsString(
					$needle,
					$code,
					basename( $file ) . " reaches the database directly via {$needle}; redirects go through Redirects_Repository."
				);
			}
		}
	}

	/**
	 * Only the irreversible delete asks first; reads and the reversible update do not.
	 */
	public function test_confirmation_is_on_the_right_abilities(): void {
		$this->assertStringContainsString(
			'protected function requires_confirmation',
			self::read( self::dir() . 'Delete_Redirects.php' ),
			'Delete_Redirects must be confirm-gated — Redirection has no trash for redirects.'
		);

		foreach ( array( 'List_Redirects', 'Get_Settings', 'Create_Redirect', 'Update_Redirect', 'List_404_Errors' ) as $class ) {
			$this->assertStringNotContainsString(
				'protected function requires_confirmation',
				self::read( self::dir() . $class . '.php' ),
				"{$class} is a read or a reversible write and should not ask for confirmation."
			);
		}
	}

	/**
	 * delete-redirects declares destructive; the reversible update does not.
	 */
	public function test_annotations_are_honest(): void {
		$this->assertMatchesRegularExpression(
			"/'readonly'\s*=>\s*false,\s*'destructive'\s*=>\s*true/",
			self::read( self::dir() . 'Delete_Redirects.php' )
		);
		$this->assertMatchesRegularExpression(
			"/'readonly'\s*=>\s*false,\s*'destructive'\s*=>\s*false/",
			self::read( self::dir() . 'Update_Redirect.php' )
		);
	}

	/**
	 * create-redirect validates that the action type and HTTP code agree.
	 *
	 * A url action needs a 3xx code and a target; an error action needs a 4xx/5xx code and no target.
	 * Without this a caller can write a redirect that is stored but can never fire.
	 */
	public function test_create_validates_action_and_code(): void {
		$src = self::code_only( self::read( self::dir() . 'Create_Redirect.php' ) );

		$this->assertStringContainsString( 'REDIRECT_CODES', $src );
		$this->assertStringContainsString( 'ERROR_CODES', $src );
		$this->assertStringContainsString( "'invalid_input'", $src );
	}

	/**
	 * The settings reader never returns the REST API token.
	 */
	public function test_settings_redact_the_token(): void {
		$repo = self::code_only( self::read( self::util() . 'Redirects_Repository.php' ) );

		$this->assertMatchesRegularExpression(
			"/unset\(\s*\\\$safe\['token'\]/",
			$repo,
			'The API token authenticates write access to the whole redirect table and must never be returned.'
		);
	}

	public function test_permission_floor_is_admin(): void {
		$base = self::read( self::dir() . 'Base_Redirection_Ability.php' );

		$this->assertMatchesRegularExpression(
			'/function permission_floor\(\): string \{\s*return \'manage_options\';/',
			$base
		);

		foreach ( self::ability_files() as $file ) {
			$this->assertStringNotContainsString( 'function permission_floor', self::read( $file ) );
		}
	}

	public function test_guard_and_repository_are_final_and_static_only(): void {
		foreach ( array( 'Redirection_Guard', 'Redirects_Repository' ) as $class ) {
			$src = self::read( self::util() . $class . '.php' );

			$this->assertStringContainsString( 'final class ' . $class, $src );
			$this->assertStringContainsString( 'private function __construct()', $src );
		}
	}

	public function test_the_bootstrap_instantiates_every_ability(): void {
		$bootstrap = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );

		foreach ( array_keys( self::inventory() ) as $class ) {
			$this->assertStringContainsString( 'new Redirection\\' . $class . '();', $bootstrap, "{$class} is never instantiated." );
		}

		$this->assertStringNotContainsString( 'new Redirection\\Category_Registrar();', $bootstrap );
		$this->assertStringContainsString( "Redirection\\Category_Registrar::instance(), 'register'", $bootstrap );
	}

	public function test_the_suite_is_gated_on_redirection(): void {
		$bootstrap = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );

		$this->assertStringContainsString(
			"class_exists( '\\Red_Item' ) && \$this->integration_on( 'redirection' )",
			$bootstrap
		);
	}

	public function test_the_integration_claims_no_prefix(): void {
		$src = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/Redirection.php' );

		$this->assertStringContainsString( "TAB_GROUP = 'redirection'", $src );
		$this->assertMatchesRegularExpression(
			'/function ability_prefixes\(\): array \{\s*return array\(\);/',
			$src,
			'Redirection registers nothing, so claiming a prefix would capture unrelated future abilities.'
		);
	}
}
