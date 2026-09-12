<?php
/**
 * Feature 104 — architectural invariants across the whole LiteSpeed Cache suite.
 *
 * These sweep every file in includes/Abilities/LiteSpeed/ and
 * includes/Abilities/Utilities/LiteSpeed/, so each new ability is covered the moment it lands rather
 * than needing a per-file assertion.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.36
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_LiteSpeed_Architecture extends WP_UnitTestCase {

	/** Files in the abilities directory that are not themselves abilities. */
	private const NON_ABILITY_FILES = array(
		'Category_Registrar.php',
		'Base_LiteSpeed_Ability.php',
		'Base_Settings_Read_Ability.php',
		'Base_Settings_Write_Ability.php',
	);

	private static function abilities_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/LiteSpeed/';
	}

	private static function utilities_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/LiteSpeed/';
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
	 * Strip comments AND our own namespace segments.
	 *
	 * Both matter here. A docblock may legitimately name a host symbol while explaining why it must
	 * not be called; and our own namespace literally contains the string `LiteSpeed`
	 * (`...\Abilities\Utilities\LiteSpeed`), so without stripping it every file in the suite would
	 * look like a leak.
	 */
	private static function code_only( string $src ): string {
		$stripped = '';

		foreach ( token_get_all( $src ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}

			$stripped .= is_array( $token ) ? $token[1] : $token;
		}

		return str_replace(
			array( 'Abilities\\Utilities\\LiteSpeed', 'Abilities\\LiteSpeed' ),
			'',
			$stripped
		);
	}

	public function test_there_is_at_least_one_ability(): void {
		$this->assertNotEmpty( self::ability_files() );
	}

	/**
	 * All LiteSpeed access is confined to Utilities/LiteSpeed. That is what gives one place to absorb
	 * a LiteSpeed API change, one place for a PHPStan ignore, and one place to test the bridge.
	 */
	public function test_no_ability_class_references_a_litespeed_symbol(): void {
		foreach ( self::ability_files() as $file ) {
			$code = self::code_only( (string) file_get_contents( $file ) );

			$this->assertDoesNotMatchRegularExpression(
				'/\\\\LiteSpeed\\\\/',
				$code,
				basename( $file ) . ' names a \\LiteSpeed\\* symbol in code; move it into Utilities/LiteSpeed.'
			);
		}
	}

	/**
	 * The registrar is not an ability, but it is still suite code, so its own probe goes through the
	 * guard rather than naming the host class a second time.
	 */
	public function test_the_category_registrar_probes_through_the_guard(): void {
		$code = self::code_only( (string) file_get_contents( self::abilities_dir() . 'Category_Registrar.php' ) );

		$this->assertStringContainsString( 'LiteSpeed_Guard::is_available()', $code );
		$this->assertDoesNotMatchRegularExpression( '/class_exists\(\s*[\'"]\\\\?\\\\?LiteSpeed/', $code );
	}

	/**
	 * Every ability must extend one of the three suite bases, so ability() and the guard ordering are
	 * assembled in exactly one place.
	 */
	public function test_every_ability_extends_a_suite_base(): void {
		foreach ( self::ability_files() as $file ) {
			$this->assertMatchesRegularExpression(
				'/extends (Base_LiteSpeed_Ability|Base_Settings_Read_Ability|Base_Settings_Write_Ability)\b/',
				(string) file_get_contents( $file ),
				basename( $file ) . ' must extend a LiteSpeed suite base.'
			);
		}
	}

	/**
	 * The two intermediate bases must themselves extend the root base, or they would bypass it.
	 */
	public function test_the_intermediate_bases_extend_the_root_base(): void {
		foreach ( array( 'Base_Settings_Read_Ability.php', 'Base_Settings_Write_Ability.php' ) as $name ) {
			$this->assertMatchesRegularExpression(
				'/extends Base_LiteSpeed_Ability\b/',
				(string) file_get_contents( self::abilities_dir() . $name ),
				$name . ' must extend Base_LiteSpeed_Ability.'
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
	 * The capability floor is declared final on the root base and no subclass may restate it.
	 */
	public function test_the_permission_floor_is_final_and_never_overridden(): void {
		$base = self::code_only( (string) file_get_contents( self::abilities_dir() . 'Base_LiteSpeed_Ability.php' ) );

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
	 * 'confirm' must never be schema-required.
	 *
	 * WP core validates input_schema BEFORE execute() runs, so a required confirm makes an
	 * unconfirmed call fail with a generic ability_invalid_input and the gate never fires — the
	 * caller never sees confirmation_required or the message naming the flag.
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
			(string) file_get_contents( self::abilities_dir() . 'Base_LiteSpeed_Ability.php' )
		);
	}

	/**
	 * Every ability must declare the full annotation triple, directly or through an intermediate base.
	 */
	public function test_every_ability_declares_the_full_annotation_triple(): void {
		foreach ( self::ability_files() as $file ) {
			$src = (string) file_get_contents( $file );

			if ( preg_match( '/extends Base_Settings_(Read|Write)_Ability\b/', $src ) ) {
				continue;
			}

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
	 * And the bases the sweep above skips must declare it themselves.
	 */
	public function test_the_intermediate_bases_declare_the_full_annotation_triple(): void {
		foreach ( array( 'Base_Settings_Read_Ability.php', 'Base_Settings_Write_Ability.php' ) as $name ) {
			$src = (string) file_get_contents( self::abilities_dir() . $name );

			foreach ( array( 'readonly', 'destructive', 'idempotent' ) as $key ) {
				$this->assertMatchesRegularExpression( "/'{$key}'\s*=>\s*(true|false)/", $src, "{$name} is missing '{$key}'." );
			}
		}
	}

	/**
	 * A readonly ability must not be destructive — that combination is incoherent.
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
	 * A readonly ability must not write. Settings_Repository::write() and the purge entry points are
	 * the machine-checkable proxy for "this ability changes something".
	 */
	public function test_readonly_abilities_do_not_write(): void {
		foreach ( self::ability_files() as $file ) {
			$src = (string) file_get_contents( $file );

			if ( ! preg_match( "/'readonly'\s*=>\s*true/", $src ) ) {
				continue;
			}

			$code = self::code_only( $src );

			foreach ( array( 'Settings_Repository::write(', 'Purge_Repository::purge', 'Toolbox_Repository::apply_preset(', 'Toolbox_Repository::restore_backup(', 'Crawler_Repository::run(', 'Crawler_Repository::reset(' ) as $writer ) {
				$this->assertStringNotContainsString(
					$writer,
					$code,
					basename( $file ) . " is annotated readonly but calls {$writer}."
				);
			}
		}
	}

	/**
	 * ARCHITECTURE RULE — go through LiteSpeed's PHP API, never its database or its options.
	 *
	 * LiteSpeed owns its storage. A bare update_option() stores the value and skips the type-casting
	 * and the side effects that make a change real — the conditional purge, cron cleanup, the
	 * .htaccess rewrite and the CDN sync — so the setting reads back correctly and the site behaves
	 * exactly as before. That is the defining silent failure of this integration.
	 */
	public function test_no_direct_database_or_option_access(): void {
		$patterns = array(
			'/\$wpdb\b/'                                => 'uses $wpdb',
			'/[\'"]\s*SELECT\s/i'                       => 'contains a SELECT statement',
			'/[\'"]\s*DELETE\s+FROM/i'                  => 'contains a DELETE statement',
			'/[\'"]\s*INSERT\s+INTO/i'                  => 'contains an INSERT statement',
			'/[\'"]\s*(TRUNCATE|DROP)\s+TABLE/i'        => 'contains a TRUNCATE/DROP statement',
			'/(get|update|delete)_option\(\s*[\'"]lite/i' => 'reads or writes a LiteSpeed option directly',
		);

		foreach ( self::suite_files() as $file ) {
			$code = self::code_only( (string) file_get_contents( $file ) );

			foreach ( $patterns as $pattern => $what ) {
				$this->assertDoesNotMatchRegularExpression(
					$pattern,
					$code,
					basename( $file ) . " {$what}; call LiteSpeed's API instead."
				);
			}
		}
	}

	/**
	 * Only Settings_Repository may persist a setting, and it does so in exactly one place.
	 */
	public function test_only_the_repository_calls_update_confs(): void {
		$callers = array();

		foreach ( self::suite_files() as $file ) {
			if ( str_contains( self::code_only( (string) file_get_contents( $file ) ), 'update_confs' ) ) {
				$callers[] = basename( $file );
			}
		}

		$this->assertSame( array( 'Settings_Repository.php' ), $callers );

		$code = self::code_only( (string) file_get_contents( self::utilities_dir() . 'Settings_Repository.php' ) );

		$this->assertSame(
			1,
			substr_count( $code, 'update_confs(' ),
			'Settings_Repository must persist through exactly one update_confs() call site.'
		);
	}

	/**
	 * Purges must suppress LiteSpeed's admin-notice side effect.
	 *
	 * LiteSpeed's purge methods call Admin_Display::success() unless LITESPEED_PURGE_SILENT is
	 * defined, which would surface a banner on whatever admin screen the operator loads next, caused
	 * by an API call they never saw.
	 */
	public function test_purges_suppress_the_admin_notice(): void {
		$code = self::code_only( (string) file_get_contents( self::utilities_dir() . 'Purge_Repository.php' ) );

		$this->assertStringContainsString( "define( 'LITESPEED_PURGE_SILENT', true )", $code );
	}

	/**
	 * Settings are described as ROWS, never as an associative map.
	 *
	 * An associative array encodes as a JSON object, so an output property declared `type => array`
	 * fails the ability's own output schema — after the work is done
	 * (BUG-ARRAY-TYPED-OUTPUT-IS-A-JSON-OBJECT, found live in Feature 103). Every settings read in
	 * this suite is naturally a map, which makes it the most likely place to reintroduce that bug.
	 */
	public function test_settings_are_described_as_rows(): void {
		$code = self::code_only( (string) file_get_contents( self::utilities_dir() . 'Settings_Repository.php' ) );

		$this->assertMatchesRegularExpression(
			'/\$rows\[\]\s*=\s*array\(\s*\'key\'/',
			$code,
			'Settings_Repository::describe() must build a list of rows keyed by "key", not an associative map.'
		);
		$this->assertStringNotContainsString(
			'$rows[ $key ] =',
			$code,
			'Settings_Repository must not build a key-indexed map; that encodes as a JSON object.'
		);
	}

	/**
	 * An output property declared `array` must never be fed a repository method that returns an
	 * associative map.
	 *
	 * PHP makes no distinction; JSON does. An associative array encodes as an object, so the ability
	 * fails its OWN output schema with ability_invalid_output — after the work is done. Found live
	 * TWICE now: once in Feature 103 (update-additional-settings), and again here, where
	 * get-crawler-status, run-crawler and reset-crawler all declared `status` as an array while
	 * feeding it Crawler_Repository::summary(), which is keyed by field name.
	 *
	 * The Feature 103 guard only inspected Settings_Repository, which is why it missed this. The list
	 * of map-returning methods below is therefore the thing to extend whenever a repository gains one.
	 */
	public function test_array_typed_output_is_never_fed_a_map(): void {
		$map_returning = array(
			'Crawler_Repository::summary',
			'Crawler_Repository::map',
			'Settings_Repository::areas',
			'Settings_Repository::keys_for',
			'Database_Repository::types',
			'Database_Repository::recommendations',
			'Toolbox_Repository::presets',
			'Purge_Repository::targets',
		);

		foreach ( self::ability_files() as $file ) {
			$code = self::code_only( (string) file_get_contents( $file ) );

			if ( ! preg_match( '/function output_properties\(\): array \{(.*?)\n\t\}/s', $code, $declared ) ) {
				continue;
			}

			preg_match_all( "/'([a-z_0-9]+)'\s*=> array\(\s*'type' => 'array'/", $declared[1], $keys );

			if ( array() === $keys[1] ) {
				continue;
			}

			// Resolve one level of indirection: `$x = Repo::map_returning();` then `'key' => $x`.
			// The negative lookahead below exempts `$x['list']` — subscripting a map yields whatever
			// that key holds, which is frequently a legitimate list (Crawler_Repository::map()['urls']).
			// Without this the check only catches the inline form, and every real case in this suite
			// assigns to a variable first — which is exactly how the crawler-status bug survived the
			// first version of this test.
			$tainted = array();

			foreach ( $map_returning as $method ) {
				if ( preg_match_all( '/\$([a-z_0-9]+)\s*=\s*' . preg_quote( $method, '/' ) . '\(/', $code, $vars ) ) {
					foreach ( $vars[1] as $var ) {
						$tainted[ $var ] = $method;
					}
				}
			}

			foreach ( $keys[1] as $key ) {
				foreach ( $map_returning as $method ) {
					$this->assertDoesNotMatchRegularExpression(
						"/'{$key}'\s*=>\s*" . preg_quote( $method, '/' ) . '\(/',
						$code,
						basename( $file ) . ": output['{$key}'] is declared type=array but is fed {$method}(),"
							. ' which returns an associative map and encodes as a JSON object.'
					);
				}

				foreach ( $tainted as $var => $method ) {
					$this->assertDoesNotMatchRegularExpression(
						"/'{$key}'\s*=>\s*\\\$" . preg_quote( $var, '/' ) . '(?!\s*\[)\b/',
						$code,
						basename( $file ) . ": output['{$key}'] is declared type=array but is fed \${$var},"
							. " assigned from {$method}(), which returns an associative map."
					);
				}
			}
		}
	}

	/**
	 * Every option key an exclusion ability can address must be owned by a settings area.
	 *
	 * These abilities map a friendly `type` onto an option key, then write it. Found live: the
	 * optimisation exclusions hardcoded the `optimize-css` area while their keys span `optimize-css`,
	 * `optimize-js` and `optimize-tuning`, so half the types returned setting_not_writable. The
	 * abilities now resolve the area from the key; this asserts every key they can name is actually
	 * owned by one, so a new type cannot be added without a home.
	 */
	public function test_exclusion_keys_all_belong_to_an_area(): void {
		$owned = array();

		foreach ( \AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\LiteSpeed\Settings_Repository::areas() as $keys ) {
			$owned = array_merge( $owned, array_keys( $keys ) );
		}

		$checked = 0;

		foreach ( array( 'Update_Cache_Exclusions.php', 'Update_Optimization_Exclusions.php', 'Update_Media_Exclusions.php' ) as $name ) {
			$code = self::code_only( (string) file_get_contents( self::abilities_dir() . $name ) );

			$this->assertStringContainsString(
				'Settings_Repository::area_for(',
				$code,
				$name . ' must resolve the area from the key rather than hardcoding one.'
			);

			preg_match( '/\$map\s*=\s*array\((.*?)\n\t\t\);/s', $code, $m );
			$this->assertNotEmpty( $m, $name . ': key map not found.' );

			preg_match_all( "/=>\s*'([a-z_0-9-]+)'/", $m[1], $keys );

			foreach ( $keys[1] as $key ) {
				++$checked;
				$this->assertContains(
					$key,
					$owned,
					$name . ": '{$key}' is not owned by any settings area, so writing it always fails."
				);
			}
		}

		$this->assertGreaterThan( 20, $checked, 'Expected the three exclusion maps to cover many keys.' );
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
	 * Every ability must belong to a declared sub-group.
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
