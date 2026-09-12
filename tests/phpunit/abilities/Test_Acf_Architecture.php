<?php
/**
 * Feature 105 — architectural invariants across the whole ACF suite.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.37
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Acf_Architecture extends WP_UnitTestCase {

	private const NON_ABILITY_FILES = array( 'Category_Registrar.php', 'Base_Acf_Ability.php' );

	private static function abilities_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Acf/';
	}

	private static function utilities_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Acf/';
	}

	/**
	 * @return string[]
	 */
	private static function ability_files(): array {
		$files = glob( self::abilities_dir() . '*.php' );

		return array_values(
			array_filter(
				is_array( $files ) ? $files : array(),
				static fn( string $f ): bool => ! in_array( basename( $f ), self::NON_ABILITY_FILES, true )
			)
		);
	}

	/**
	 * Strip comments AND our own namespace segments.
	 *
	 * Both matter. A docblock may name a symbol while explaining why it must not be called, and our
	 * own namespace contains the vendor string (`...\Abilities\Utilities\Acf`) — the exact trap
	 * Feature 104 hit, where every file in the suite looked like a leak.
	 */
	private static function code_only( string $src ): string {
		$stripped = '';

		foreach ( token_get_all( $src ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}

			$stripped .= is_array( $token ) ? $token[1] : $token;
		}

		return str_replace( array( 'Abilities\\Utilities\\Acf', 'Abilities\\Acf' ), '', $stripped );
	}

	/**
	 * Comments AND string literals stripped.
	 *
	 * Required by the two tests that search for ACF function names. Every ability's description
	 * legitimately names the API it wraps — "goes through ACF update_field()" is exactly the sentence
	 * a client needs — so searching raw source flags the documentation as a violation. Stripping
	 * literals leaves only real calls, in either the namespaced or the global-fallback form.
	 */
	private static function code_no_strings( string $src ): string {
		$stripped = '';

		foreach ( token_get_all( $src ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE ), true ) ) {
				continue;
			}

			$stripped .= is_array( $token ) ? $token[1] : $token;
		}

		// Remove qualified static calls WHOLE. Our repository methods deliberately share names with
		// the ACF functions they wrap — Field_Repository::add_row() wraps add_row() — so removing
		// only the `Foo::` qualifier would turn our own call into what looks like ACF's. Removing the
		// whole call leaves a genuine unqualified `add_row(` still detectable.
		$stripped = preg_replace( '/\b[A-Za-z_][A-Za-z_0-9]*::[a-z_][a-z_0-9]*\s*\(/', '', $stripped ) ?? $stripped;

		return str_replace( array( 'Abilities\\Utilities\\Acf', 'Abilities\\Acf' ), '', $stripped );
	}

	public function test_there_is_at_least_one_ability(): void {
		$this->assertNotEmpty( self::ability_files() );
	}

	/**
	 * All ACF access is confined to Utilities/Acf.
	 *
	 * Both spellings: ACF's API is lowercase functions (`get_field`, `acf_get_field_type`) with no
	 * class namespace, so the check is on the function names rather than a `\Vendor\` prefix.
	 */
	public function test_no_ability_class_calls_acf_directly(): void {
		$acf_functions = array(
			'get_field(', 'get_fields(', 'update_field(', 'delete_field(', 'get_field_object(',
			'add_row(', 'update_row(', 'delete_row(', 'acf_get_field_type(', 'acf_is_pro(',
			'acf_register_block_type(', 'acf_get_block_type', 'acf_get_field_groups(', 'acf_get_fields(',
		);

		foreach ( self::ability_files() as $file ) {
			$code = self::code_no_strings( (string) file_get_contents( $file ) );

			foreach ( $acf_functions as $fn ) {
				$this->assertStringNotContainsString(
					$fn,
					$code,
					basename( $file ) . " calls {$fn} directly; route it through Utilities/Acf."
				);
			}
		}
	}

	public function test_the_category_registrar_probes_through_the_guard(): void {
		$code = self::code_only( (string) file_get_contents( self::abilities_dir() . 'Category_Registrar.php' ) );

		$this->assertStringContainsString( 'Acf_Guard::is_available()', $code );
		$this->assertStringNotContainsString( "defined( 'ACF_VERSION' )", $code );
	}

	public function test_every_ability_extends_the_suite_base(): void {
		foreach ( self::ability_files() as $file ) {
			$this->assertMatchesRegularExpression(
				'/extends Base_Acf_Ability\b/',
				(string) file_get_contents( $file ),
				basename( $file ) . ' must extend Base_Acf_Ability.'
			);
		}
	}

	public function test_no_ability_overrides_the_contract_methods(): void {
		foreach ( self::ability_files() as $file ) {
			$src = (string) file_get_contents( $file );

			$this->assertStringNotContainsString( 'function ability()', $src, basename( $file ) . ' must not override ability().' );
			$this->assertStringNotContainsString( 'function execute(', $src, basename( $file ) . ' must not override execute().' );
		}
	}

	public function test_the_permission_floor_is_final_and_never_overridden(): void {
		$base = self::code_only( (string) file_get_contents( self::abilities_dir() . 'Base_Acf_Ability.php' ) );

		$this->assertMatchesRegularExpression( '/final protected function permission_floor\(\)/', $base );
		$this->assertStringContainsString( "return 'manage_options';", $base );

		foreach ( self::ability_files() as $file ) {
			$this->assertStringNotContainsString(
				'function permission_floor(',
				(string) file_get_contents( $file ),
				basename( $file ) . ' must not restate the capability floor.'
			);
		}
	}

	public function test_confirm_is_never_schema_required(): void {
		foreach ( self::ability_files() as $file ) {
			$src = self::code_only( (string) file_get_contents( $file ) );

			if ( ! preg_match( '/function required_input\(\): array \{(.*?)\}/s', $src, $matches ) ) {
				continue;
			}

			$this->assertStringNotContainsString(
				"'confirm'",
				$matches[1],
				basename( $file ) . " lists 'confirm' as schema-required, which suppresses confirmation_required."
			);
		}
	}

	public function test_base_strips_confirm_from_the_required_list(): void {
		$this->assertStringContainsString(
			"array_diff( \$required, array( 'confirm' ) )",
			(string) file_get_contents( self::abilities_dir() . 'Base_Acf_Ability.php' )
		);
	}

	public function test_every_ability_declares_the_full_annotation_triple(): void {
		foreach ( self::ability_files() as $file ) {
			$src = (string) file_get_contents( $file );

			foreach ( array( 'readonly', 'destructive', 'idempotent' ) as $key ) {
				$this->assertMatchesRegularExpression(
					"/'{$key}'\s*=>\s*(true|false)/",
					$src,
					basename( $file ) . " is missing the '{$key}' annotation."
				);
			}
		}
	}

	public function test_readonly_abilities_are_not_destructive(): void {
		foreach ( self::ability_files() as $file ) {
			$src = (string) file_get_contents( $file );

			if ( ! preg_match( "/'readonly'    => true/", $src ) ) {
				continue;
			}

			$this->assertDoesNotMatchRegularExpression(
				"/'destructive' => true/",
				$src,
				basename( $file ) . ' is both readonly and destructive.'
			);
		}
	}

	/**
	 * ARCHITECTURE RULE — never write ACF data through post meta.
	 *
	 * This is the reason the suite exists. ACF stores a value row plus a `_`-prefixed field-key
	 * reference row, and a repeater additionally stores one row per index per sub-field. A bare
	 * `update_post_meta()` writes the value row alone, leaving ACF unable to read its own data back
	 * — corruption that reports success. An ability that reached for post meta here would silently
	 * reintroduce the exact bug the feature was built to fix.
	 */
	public function test_no_ability_or_repository_touches_post_meta(): void {
		foreach ( self::suite_files() as $file ) {
			$code = self::code_only( (string) file_get_contents( $file ) );

			foreach ( array( 'update_post_meta(', 'add_post_meta(', 'delete_post_meta(', 'get_post_meta(', '$wpdb' ) as $forbidden ) {
				$this->assertStringNotContainsString(
					$forbidden,
					$code,
					basename( $file ) . " uses {$forbidden}; ACF data must go through ACF's own API."
				);
			}
		}
	}

	/**
	 * An `array`-typed output property must never be fed a repository method that returns a map.
	 *
	 * BUG-ARRAY-TYPED-OUTPUT-IS-A-JSON-OBJECT has bitten in 103 and again in 104. Indirection-aware,
	 * because the 104 version missed the case where the value passes through a variable first.
	 */
	public function test_array_typed_output_is_never_fed_a_map(): void {
		$map_returning = array( 'Acf_Target::types', 'Block_Repository::get', 'Block_Repository::update_data' );

		foreach ( self::ability_files() as $file ) {
			$code = self::code_only( (string) file_get_contents( $file ) );

			if ( ! preg_match( '/function output_properties\(\): array \{(.*?)\n\t\}/s', $code, $declared ) ) {
				continue;
			}

			preg_match_all( "/'([a-z_0-9]+)' => array\( 'type' => 'array' \)/", $declared[1], $keys );

			if ( array() === $keys[1] ) {
				continue;
			}

			$tainted = array();

			foreach ( $map_returning as $method ) {
				if ( preg_match_all( '/\$([a-z_0-9]+)\s*=\s*' . preg_quote( $method, '/' ) . '\(/', $code, $vars ) ) {
					foreach ( $vars[1] as $var ) {
						$tainted[ $var ] = $method;
					}
				}
			}

			foreach ( $keys[1] as $key ) {
				foreach ( $tainted as $var => $method ) {
					$this->assertDoesNotMatchRegularExpression(
						"/'{$key}'\s*=>\s*\\\$" . preg_quote( $var, '/' ) . '(?!\s*\[)\b/',
						$code,
						basename( $file ) . ": output['{$key}'] is declared array but fed \${$var} from {$method}()."
					);
				}
			}
		}
	}

	/**
	 * Only the field repository may call ACF's write functions, and only in one place each.
	 */
	public function test_writes_are_centralised_in_the_repositories(): void {
		$callers = array();

		foreach ( self::suite_files() as $file ) {
			$code = self::code_no_strings( (string) file_get_contents( $file ) );

			if ( preg_match( '/\\bupdate_field\\(|\\bdelete_field\\(|\\badd_row\\(|\\bupdate_row\\(|\\bdelete_row\\(/', $code ) ) {
				$callers[] = basename( $file );
			}
		}

		sort( $callers );

		$this->assertSame( array( 'Field_Repository.php' ), $callers );
	}

	/**
	 * DEC-UTILITY-STATIC-ONLY — helpers are final, static-only, never singletons.
	 */
	public function test_utilities_are_final_and_static_only(): void {
		$files = glob( self::utilities_dir() . '*.php' );

		$this->assertNotEmpty( $files );

		foreach ( (array) $files as $file ) {
			$src  = (string) file_get_contents( (string) $file );
			$name = basename( (string) $file, '.php' );

			$this->assertStringContainsString( "final class {$name}", $src, "{$name} must be final." );
			$this->assertStringContainsString( 'private function __construct()', $src, "{$name} must have a private constructor." );
			$this->assertStringNotContainsString( 'public static function instance()', $src, "{$name} must not be a singleton." );
		}
	}

	public function test_every_ability_declares_a_sub_group(): void {
		foreach ( self::ability_files() as $file ) {
			$this->assertMatchesRegularExpression(
				'/function sub_group\(\): string/',
				(string) file_get_contents( $file ),
				basename( $file ) . ' must declare a sub_group.'
			);
		}
	}

	/**
	 * @return string[]
	 */
	private static function suite_files(): array {
		return array_map(
			'strval',
			array_merge(
				(array) glob( self::abilities_dir() . '*.php' ),
				(array) glob( self::utilities_dir() . '*.php' )
			)
		);
	}
}
