<?php
/**
 * Feature 103 — architectural invariants across the whole Contact Form 7 suite.
 *
 * These sweep every file in includes/Abilities/ContactForm7/ and
 * includes/Abilities/Utilities/ContactForm7/, so each new ability is covered the moment it lands
 * rather than needing a per-file assertion.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.35
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Contact_Form_7_Architecture extends WP_UnitTestCase {

	/** Files in the abilities directory that are not themselves abilities. */
	private const NON_ABILITY_FILES = array(
		'Category_Registrar.php',
		'Base_Contact_Form_7_Ability.php',
	);

	private static function abilities_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/ContactForm7/';
	}

	private static function utilities_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/ContactForm7/';
	}

	/**
	 * @return string[] Absolute paths.
	 */
	private static function ability_files(): array {
		$files = glob( self::abilities_dir() . '*.php' );
		$files = is_array( $files ) ? $files : array();

		return array_values(
			array_filter(
				$files,
				static fn( string $f ): bool => ! in_array( basename( $f ), self::NON_ABILITY_FILES, true )
			)
		);
	}

	/**
	 * Strip comments, so only real symbol references remain. A docblock may legitimately name a
	 * symbol while explaining why it must not be called here — the base does exactly that.
	 */
	private static function code_only( string $src ): string {
		$stripped = '';

		foreach ( token_get_all( $src ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}

			$stripped .= is_array( $token ) ? $token[1] : $token;
		}

		return $stripped;
	}

	public function test_there_is_at_least_one_ability(): void {
		$this->assertNotEmpty( self::ability_files() );
	}

	/**
	 * All Contact Form 7 access is confined to Utilities/ContactForm7. That is what gives one place
	 * to absorb a CF7 API change, one place for a PHPStan ignore, and one place to test the bridge.
	 *
	 * Both spellings are checked: CF7's classes are `WPCF7_*` and its functions are `wpcf7_*`, and
	 * an ability reaching for `wpcf7_save_contact_form()` is exactly as much of a leak as one
	 * reaching for `WPCF7_ContactForm`.
	 */
	public function test_no_ability_class_references_a_contact_form_7_symbol(): void {
		foreach ( self::ability_files() as $file ) {
			$code = self::code_only( (string) file_get_contents( $file ) );

			foreach ( array( 'WPCF7', 'wpcf7_' ) as $symbol ) {
				$this->assertStringNotContainsString(
					$symbol,
					$code,
					basename( $file ) . " names a {$symbol}* symbol in code; move it into Utilities/ContactForm7."
				);
			}
		}
	}

	/**
	 * The registrar is not an ability, but it is still suite code, so its own CF7 probe goes through
	 * the guard rather than naming the class a second time.
	 */
	public function test_the_category_registrar_probes_through_the_guard(): void {
		$code = self::code_only( (string) file_get_contents( self::abilities_dir() . 'Category_Registrar.php' ) );

		$this->assertStringContainsString( 'Contact_Form_7_Guard::is_available()', $code );
		$this->assertStringNotContainsString( "class_exists( 'WPCF7_ContactForm' )", $code );
	}

	/**
	 * Every ability must extend the base, which is the sole assembler of ability() and the sole
	 * enforcer of the guard ordering.
	 */
	public function test_every_ability_extends_the_suite_base(): void {
		foreach ( self::ability_files() as $file ) {
			$this->assertMatchesRegularExpression(
				'/extends Base_Contact_Form_7_Ability\b/',
				(string) file_get_contents( $file ),
				basename( $file ) . ' must extend Base_Contact_Form_7_Ability.'
			);
		}
	}

	/**
	 * No ability may override the two methods that carry the shared contract.
	 */
	public function test_no_ability_overrides_the_contract_methods(): void {
		foreach ( self::ability_files() as $file ) {
			$src = (string) file_get_contents( $file );

			$this->assertStringNotContainsString( 'function ability()', $src, basename( $file ) . ' must not override ability().' );
			$this->assertStringNotContainsString( 'function execute(', $src, basename( $file ) . ' must not override execute().' );
		}
	}

	/**
	 * The capability floor is declared final in the base and no subclass may restate it.
	 *
	 * CF7 maps its own capabilities onto `publish_pages` and `edit_posts`, so gating on the CF7
	 * capability alone would let an Editor drive every ability here. `final` is what makes that
	 * unreachable rather than merely discouraged.
	 */
	public function test_the_permission_floor_is_final_and_never_overridden(): void {
		$base = self::code_only( (string) file_get_contents( self::abilities_dir() . 'Base_Contact_Form_7_Ability.php' ) );

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

	/**
	 * Destructive and requires_confirmation() must agree. One without the other is the likely bug: a
	 * destructive ability with no confirm gate, or a confirm gate on something the annotations call
	 * safe.
	 *
	 * Note this covers only unconditional gates. Update_Mail and Update_Additional_Settings confirm
	 * conditionally — a recipient change, or a setting that stops the form delivering mail — and are
	 * correctly annotated destructive => false, because the ordinary call destroys nothing.
	 */
	public function test_destructive_and_confirmation_agree(): void {
		foreach ( self::ability_files() as $file ) {
			$src         = (string) file_get_contents( $file );
			$destructive = (bool) preg_match( "/'destructive'\s*=>\s*true/", $src );
			$confirms    = (bool) preg_match( '/function requires_confirmation\(\)\s*:\s*bool\s*\{\s*return true;/s', $src );

			$this->assertSame(
				$destructive,
				$confirms,
				basename( $file ) . ': destructive=' . var_export( $destructive, true )
					. ' but requires_confirmation=' . var_export( $confirms, true )
					. '. Both or neither.'
			);
		}
	}

	/**
	 * 'confirm' must never be schema-required.
	 *
	 * WP core validates input_schema BEFORE execute() runs, so a required confirm makes an
	 * unconfirmed call fail with a generic ability_invalid_input ("confirm is a required property")
	 * and assert_confirmed() never fires — the caller never sees confirmation_required or the
	 * message naming the flag, which defeats the entire gate.
	 */
	public function test_confirm_is_never_schema_required(): void {
		foreach ( self::ability_files() as $file ) {
			$src = self::code_only( (string) file_get_contents( $file ) );

			if ( ! preg_match( '/function required_input\(\): array \{(.*?)\}/s', $src, $matches ) ) {
				continue;
			}

			$this->assertStringNotContainsString(
				"'confirm'",
				$matches[1],
				basename( $file ) . " lists 'confirm' as schema-required, which suppresses the confirmation_required error."
			);
		}
	}

	/**
	 * The base must strip it defensively too, so no future subclass can reintroduce the bug.
	 */
	public function test_base_strips_confirm_from_the_required_list(): void {
		$this->assertStringContainsString(
			"array_diff( \$required, array( 'confirm' ) )",
			(string) file_get_contents( self::abilities_dir() . 'Base_Contact_Form_7_Ability.php' )
		);
	}

	/**
	 * Every ability must declare the full annotation triple — a missing key would silently default
	 * and misdescribe the ability to clients.
	 */
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

	/**
	 * A readonly ability must not be destructive — that combination is incoherent and would mislead
	 * a client into refusing a safe call or allowing a harmful one.
	 */
	public function test_readonly_abilities_are_not_destructive(): void {
		foreach ( self::ability_files() as $file ) {
			$src = (string) file_get_contents( $file );

			if ( ! preg_match( "/'readonly'\s*=>\s*true/", $src ) ) {
				continue;
			}

			$this->assertDoesNotMatchRegularExpression(
				"/'destructive'\s*=>\s*true/",
				$src,
				basename( $file ) . ' is both readonly and destructive.'
			);
		}
	}

	/**
	 * A readonly ability must not write. `wpcf7_save_contact_form()` is confined to
	 * Form_Repository::save(), so calling that repository method is the machine-checkable proxy for
	 * "this ability persists something".
	 */
	public function test_readonly_abilities_do_not_save(): void {
		foreach ( self::ability_files() as $file ) {
			$src = (string) file_get_contents( $file );

			if ( ! preg_match( "/'readonly'\s*=>\s*true/", $src ) ) {
				continue;
			}

			$code = self::code_only( $src );

			foreach ( array( 'Form_Repository::save(', 'Form_Repository::delete(', 'Form_Repository::duplicate(' ) as $writer ) {
				$this->assertStringNotContainsString(
					$writer,
					$code,
					basename( $file ) . " is annotated readonly but calls {$writer}."
				);
			}
		}
	}

	/**
	 * ARCHITECTURE RULE — go through Contact Form 7's PHP API, never its database.
	 *
	 * CF7 owns its storage format: forms are a custom post type whose five properties live in post
	 * meta under keys it chooses. Reading or writing that directly gives up its sanitisation, its
	 * hooks and its per-request instance cache, and breaks silently whenever it changes a key.
	 */
	public function test_no_direct_database_access(): void {
		$patterns = array(
			'/\$wpdb\b/'                         => 'uses $wpdb',
			'/[\'"]\s*SELECT\s/i'                => 'contains a SELECT statement',
			'/[\'"]\s*DELETE\s+FROM/i'           => 'contains a DELETE statement',
			'/[\'"]\s*INSERT\s+INTO/i'           => 'contains an INSERT statement',
			'/[\'"]\s*(TRUNCATE|DROP)\s+TABLE/i' => 'contains a TRUNCATE/DROP statement',
		);

		foreach ( self::suite_files() as $file ) {
			$code = self::code_only( (string) file_get_contents( $file ) );

			foreach ( $patterns as $pattern => $what ) {
				$this->assertDoesNotMatchRegularExpression(
					$pattern,
					$code,
					basename( $file ) . " {$what}; call Contact Form 7's API instead of its database."
				);
			}
		}
	}

	/**
	 * Corollary — CF7 stores each form property in post meta, so a bare *_post_meta() call on a
	 * `_form`/`_mail`/`_messages` key bypasses the same sanitisation the SQL rule protects.
	 */
	public function test_form_properties_are_not_read_from_post_meta(): void {
		foreach ( self::suite_files() as $file ) {
			$code = self::code_only( (string) file_get_contents( $file ) );

			$this->assertDoesNotMatchRegularExpression(
				'/(get|update|delete)_post_meta\(/',
				$code,
				basename( $file ) . " reaches into a form's post meta; use Form_Repository."
			);
		}
	}

	/**
	 * Only Form_Repository::save() may persist a form. Centralising it is what guarantees every
	 * write runs wpcf7_kses() — CF7 skips its own sanitisation for users holding `unfiltered_html`,
	 * which on a single-site install is every administrator.
	 */
	public function test_only_the_repository_calls_the_contact_form_7_save_function(): void {
		$callers = array();

		foreach ( self::suite_files() as $file ) {
			$code = self::code_only( (string) file_get_contents( $file ) );

			if ( str_contains( $code, 'wpcf7_save_contact_form' ) ) {
				$callers[] = basename( $file );
			}
		}

		$this->assertSame( array( 'Form_Repository.php' ), $callers );
	}

	/**
	 * And that single call site must run wpcf7_kses() on the two fields CF7 exempts.
	 */
	public function test_the_repository_sanitises_the_fields_contact_form_7_exempts(): void {
		$code = self::code_only( (string) file_get_contents( self::utilities_dir() . 'Form_Repository.php' ) );

		$this->assertStringContainsString( "wpcf7_kses( (string) \$data['form'], 'form' )", $code );
		$this->assertStringContainsString( "wpcf7_kses( (string) \$data[ \$which ]['body'], 'text' )", $code );
	}

	/**
	 * DEC-UTILITY-STATIC-ONLY — helper classes are final, static-only, and never singletons.
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

	/**
	 * Every ability must belong to a declared sub-group, so the admin groups it rather than dropping
	 * it under a bare category.
	 */
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
	 * An output property declared `type => array` must never be fed an associative map.
	 *
	 * PHP makes no distinction, but JSON does: an associative array encodes as an object, so the
	 * ability fails its OWN output schema with ability_invalid_output — after the write has already
	 * persisted. Found live: update-additional-settings returned
	 * Form_Repository::parse_additional_settings() for a key it declared as an array, so every
	 * successful settings write reported a validation error to the caller while quietly succeeding.
	 *
	 * The fix is a shared row-shaping helper, so the read and the write of the same data return the
	 * same shape. This test pins the map-returning methods so a future ability cannot reach for one
	 * of them again.
	 */
	public function test_array_typed_output_is_never_fed_an_associative_map(): void {
		$map_returning = array( 'parse_additional_settings', 'default_messages' );

		foreach ( self::ability_files() as $file ) {
			$code = self::code_only( (string) file_get_contents( $file ) );

			if ( ! preg_match( '/function output_properties\(\): array \{(.*?)\n\t\}/s', $code, $declared ) ) {
				continue;
			}

			preg_match_all( "/'([a-z_0-9]+)'\s*=>\s*array\(\s*'type'\s*=>\s*'array'/", $declared[1], $keys );

			foreach ( $keys[1] as $key ) {
				foreach ( $map_returning as $method ) {
					$this->assertDoesNotMatchRegularExpression(
						"/'{$key}'\s*=>\s*Form_Repository::{$method}\(/",
						$code,
						basename( $file ) . ": output['{$key}'] is declared type=array but is fed Form_Repository::{$method}(),"
							. ' which returns an associative map and encodes as a JSON object.'
					);
				}
			}
		}
	}

	/**
	 * Both settings abilities must describe `additional_settings` through the one shared helper, so
	 * a caller sees the same row shape before and after a change.
	 */
	public function test_the_settings_abilities_share_one_row_shape(): void {
		foreach ( array( 'Get_Additional_Settings.php', 'Update_Additional_Settings.php' ) as $name ) {
			$code = self::code_only( (string) file_get_contents( self::abilities_dir() . $name ) );

			$this->assertStringContainsString(
				'Form_Repository::describe_additional_settings(',
				$code,
				"{$name} must shape settings rows through Form_Repository::describe_additional_settings()."
			);
		}
	}

	/**
	 * @return string[] Every PHP file in the suite, abilities and utilities alike.
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
