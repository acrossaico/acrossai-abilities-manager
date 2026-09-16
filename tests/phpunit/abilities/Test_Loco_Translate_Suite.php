<?php
/**
 * Feature 116 — invariants across the Loco Translate ability suite.
 *
 * Loco is not loaded in the test harness, so these assert the suite's shape and the decisions that
 * must not be edited away. The behavioural half runs live against a real Loco install.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.47
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Loco_Translate_Suite extends WP_UnitTestCase {

	/**
	 * Class => slug, for all 14.
	 *
	 * @return array<string,string>
	 */
	private static function inventory(): array {
		return array(
			'List_Bundles'             => 'translations/list-bundles',
			'Get_Bundle'               => 'translations/get-bundle',
			'Get_Translation_Status'   => 'translations/get-translation-status',
			'List_Locales'             => 'translations/list-locales',
			'List_Strings'             => 'translations/list-strings',
			'Get_String'               => 'translations/get-string',
			'Update_Strings'           => 'translations/update-strings',
			'Create_Translation_File'  => 'translations/create-translation-file',
			'Delete_Translation_File'  => 'translations/delete-translation-file',
			'Compile_Translations'     => 'translations/compile-translations',
			'Sync_Translations'        => 'translations/sync-translations',
			'Extract_Strings'          => 'translations/extract-strings',
			'List_Available_Languages' => 'translations/list-available-languages',
			'Fetch_Translations'       => 'translations/fetch-translations',
		);
	}

	private static function dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/LocoTranslate/';
	}

	private static function util(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/LocoTranslate/';
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
					array( 'Base_Loco_Ability.php', 'Category_Registrar.php' ),
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
		$this->assertCount( 14, $found );
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
	 * Nothing writes a translation file except the repository.
	 *
	 * A PO is not what WordPress reads: one save produces the PO, the MO, the .l10n.php cache that
	 * 6.5+ prefers, and a JSON fragment per JS reference. A direct write updates one of four and the
	 * site keeps rendering the old strings.
	 */
	public function test_no_ability_writes_a_file_directly(): void {
		foreach ( self::ability_files() as $file ) {
			$code = self::code_only( self::read( $file ) );

			foreach ( array( 'file_put_contents(', 'fwrite(', 'unlink(', 'putContents(' ) as $writer ) {
				$this->assertStringNotContainsString(
					$writer,
					$code,
					basename( $file ) . " calls {$writer} directly. Translation files belong behind Translation_Repository."
				);
			}
		}
	}

	/**
	 * Every write proves itself per artefact, read back from disk.
	 *
	 * Loco's compiler SWALLOWS a failed MO compile: it raises an admin notice, sets the byte count to
	 * zero and returns normally, and the .l10n.php is then skipped too. So a save can write nothing
	 * but the PO and report success. The byte counts come from disk, not from Loco's own counter.
	 */
	public function test_writes_are_proven_per_artefact(): void {
		$repo = self::code_only( self::read( self::util() . 'Translation_Repository.php' ) );

		foreach ( array( 'po_bytes', 'mo_bytes', 'php_bytes', 'json_files', 'json_bytes' ) as $key ) {
			$this->assertStringContainsString( "'" . $key . "'", $repo, "The write report must carry {$key}." );
		}

		$this->assertStringContainsString( 'compile_failed', $repo );
		$this->assertMatchesRegularExpression(
			'/0 === \$written\[.mo_bytes.\]/',
			$repo,
			'A zero MO must be treated as a failure, not reported as success.'
		);
		$this->assertGreaterThan( 0, substr_count( $repo, 'clearstatcache(' ) );
	}

	/**
	 * Every stat read that proves a write clears the cache first.
	 *
	 * PHP caches stat results per request, and these files were written moments earlier in that same
	 * request — so an uncleared read reports the state from BEFORE the write, which is precisely the
	 * reading these calls exist to make trustworthy. A zero-byte MO would look like a healthy one.
	 *
	 * Asserted per read rather than once for the file: there are two, proving a delete and proving a
	 * size, and a single presence-check stays green when either one loses its clear. The check is also
	 * anchored — `no_clearstatcache(` contains `clearstatcache(` and would satisfy a plain
	 * contains-check while doing nothing, the shape that let a broken assertion pass in Feature 111.
	 */
	public function test_every_verification_read_clears_the_stat_cache(): void {
		$repo = self::code_only( self::read( self::util() . 'Translation_Repository.php' ) );

		preg_match_all( '/(?<![A-Za-z0-9_])(?:filesize|file_exists)\s*\(/', $repo, $reads, PREG_OFFSET_CAPTURE );

		$this->assertGreaterThanOrEqual(
			2,
			count( $reads[0] ),
			'Both the delete proof and the size proof read the filesystem back; neither may be dropped.'
		);

		foreach ( $reads[0] as $read ) {
			$preceding = substr( $repo, max( 0, $read[1] - 500 ), min( 500, $read[1] ) );

			$this->assertMatchesRegularExpression(
				'/(?<![A-Za-z0-9_])clearstatcache\s*\(/',
				$preceding,
				sprintf( '%s at offset %d reads the filesystem without clearing the stat cache first.', $read[0], $read[1] )
			);
		}
	}

	/**
	 * The .l10n.php cache is only required where WordPress uses it.
	 */
	public function test_the_php_cache_is_conditional(): void {
		$repo  = self::code_only( self::read( self::util() . 'Translation_Repository.php' ) );
		$guard = self::code_only( self::read( self::util() . 'Loco_Guard.php' ) );

		$this->assertStringContainsString( 'WP_Translation_File_PHP', $guard );
		$this->assertMatchesRegularExpression(
			'/Loco_Guard::uses_php_cache\(\)\s*&&\s*0 === \$written\[.php_bytes.\]/',
			$repo,
			'A zero php_bytes is correct below WordPress 6.5 and a failure above it.'
		);
	}

	/**
	 * Translations are NOT slashed.
	 *
	 * The opposite of the post-writing abilities, and the distinction is about who unslashes.
	 * wp_insert_post() and update_post_meta() unslash internally so their input must be slashed;
	 * Loco writes through its own writer, ending in a raw putContents() with no unslashing anywhere.
	 * Measured: a slashed write turned one backslash into two.
	 */
	public function test_translations_are_not_slashed(): void {
		foreach ( self::ability_files() as $file ) {
			$this->assertStringNotContainsString(
				'Slash_Input',
				self::read( $file ),
				basename( $file ) . ' slashes its payload, but nothing in Loco unslashes it again.'
			);
		}

		$this->assertStringNotContainsString( 'Slash_Input', self::read( self::util() . 'Translation_Repository.php' ) );
	}

	/**
	 * Strings are addressed by source and context, never by index.
	 *
	 * A sync reorders and renumbers every entry, so a stored index would afterwards point at a
	 * different string and patch the wrong one.
	 */
	public function test_strings_are_addressed_by_source_not_index(): void {
		$repo = self::code_only( self::read( self::util() . 'Translation_Repository.php' ) );

		$this->assertStringContainsString( "\$entry['source']", $repo );
		$this->assertStringContainsString( "\\x04", $repo, 'Source and context are combined into one key: the gettext EOT separator.' );
	}

	/**
	 * apply() works on the raw array, not the iterator.
	 *
	 * LocoPoIterator::current() builds a fresh message object every call, so mutating what a foreach
	 * yields is discarded. Measured: an earlier version reported applied_count 1 and wrote a
	 * header-only MO — a false success, and exactly what this suite exists to prevent.
	 */
	public function test_apply_mutates_the_raw_array(): void {
		$repo = self::code_only( self::read( self::util() . 'Translation_Repository.php' ) );

		$this->assertStringContainsString( '$po->getArrayCopy()', $repo );
		$this->assertStringContainsString( 'new \\Loco_gettext_Data( $raw )', $repo );
		$this->assertMatchesRegularExpression(
			'/\$raw\[ \$i \]\[.target.\] =/',
			$repo,
			'The target must be written into the raw array, which is what survives into the file.'
		);
	}

	/**
	 * Loco's WP-CLI commands are never referenced.
	 *
	 * They call WP_CLI::log() directly, so invoking one from an ability is a fatal outside WP-CLI.
	 * A future contributor reaching for the obvious-looking API would ship that fatal.
	 */
	public function test_the_wp_cli_commands_are_not_used(): void {
		$files = array_merge(
			self::ability_files(),
			array( self::util() . 'Translation_Repository.php', self::util() . 'Bundle_Repository.php' )
		);

		foreach ( $files as $file ) {
			$code = self::code_only( self::read( $file ) );

			foreach ( array( 'WP_CLI', 'Loco_cli_' ) as $forbidden ) {
				$this->assertStringNotContainsString(
					$forbidden,
					$code,
					basename( $file ) . " references {$forbidden}, which fatals outside WP-CLI."
				);
			}
		}
	}

	/**
	 * A locale is validated by Loco, not accepted as free text.
	 */
	public function test_locales_are_parsed(): void {
		$guard = self::code_only( self::read( self::util() . 'Loco_Guard.php' ) );

		$this->assertStringContainsString( 'Loco_Locale::parse(', $guard );
		$this->assertStringContainsString( 'isValid()', $guard );
		$this->assertStringContainsString( 'invalid_locale', $guard );
	}

	/**
	 * Destructive and wide operations ask first; reads and repairs do not.
	 */
	public function test_confirmation_is_on_the_right_abilities(): void {
		foreach ( array( 'Delete_Translation_File', 'Extract_Strings' ) as $class ) {
			$this->assertStringContainsString(
				'requires_confirmation',
				self::read( self::dir() . $class . '.php' ),
				"{$class} must be confirm-gated."
			);
		}

		foreach ( array( 'List_Bundles', 'Get_String', 'Compile_Translations', 'Update_Strings' ) as $class ) {
			$this->assertStringNotContainsString(
				'protected function requires_confirmation',
				self::read( self::dir() . $class . '.php' ),
				"{$class} is a read or a reversible write and should not ask for confirmation."
			);
		}
	}

	/**
	 * A template is written without compiling artefacts that make no sense beside it.
	 */
	public function test_templates_are_written_separately(): void {
		$repo = self::code_only( self::read( self::util() . 'Translation_Repository.php' ) );

		$this->assertStringContainsString( 'function write_template', $repo );
		$this->assertStringContainsString( 'Extract_Strings', self::read( self::dir() . 'Extract_Strings.php' ) );
		$this->assertStringContainsString(
			'write_template(',
			self::read( self::dir() . 'Extract_Strings.php' ),
			'A POT has no locale, so nothing compiles from it.'
		);
	}

	/**
	 * Deleting removes every compiled artefact, not just the PO.
	 */
	public function test_delete_removes_the_whole_set(): void {
		$repo = self::code_only( self::read( self::util() . 'Translation_Repository.php' ) );

		$this->assertStringContainsString( 'expand()', $repo );
		$this->assertStringContainsString( 'delete_failed', $repo );
	}

	public function test_the_bootstrap_instantiates_every_ability(): void {
		$bootstrap = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );

		foreach ( array_keys( self::inventory() ) as $class ) {
			$this->assertStringContainsString( 'new LocoTranslate\\' . $class . '();', $bootstrap, "{$class} is never instantiated." );
		}

		$this->assertStringNotContainsString( 'new LocoTranslate\\Category_Registrar();', $bootstrap );
		$this->assertStringContainsString( "LocoTranslate\\Category_Registrar::instance(), 'register'", $bootstrap );
	}

	public function test_the_suite_is_gated_on_loco(): void {
		$bootstrap = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );

		$this->assertStringContainsString(
			"class_exists( 'Loco_package_Bundle' ) && class_exists( 'Loco_gettext_Compiler' )",
			$bootstrap
		);
	}

	public function test_permission_floor_is_final_and_admin(): void {
		$base = self::read( self::dir() . 'Base_Loco_Ability.php' );

		$this->assertMatchesRegularExpression(
			'/final protected function permission_floor\(\): string \{\s*return \'manage_options\';/',
			$base
		);

		foreach ( self::ability_files() as $file ) {
			$this->assertStringNotContainsString( 'function permission_floor', self::read( $file ) );
		}
	}

	public function test_permission_filter_is_raise_only(): void {
		$guard = self::code_only( self::read( self::util() . 'Loco_Guard.php' ) );

		$this->assertMatchesRegularExpression(
			'/if\s*\(\s*!\s*current_user_can\(\s*\$floor\s*\)\s*\)\s*\{\s*return false;\s*\}/',
			$guard
		);
		$this->assertStringContainsString( 'apply_filters( self::PERMISSION_FILTER, true, $floor )', $guard );
	}

	public function test_repositories_are_final_and_static_only(): void {
		foreach ( array( 'Loco_Guard', 'Bundle_Repository', 'Translation_Repository' ) as $class ) {
			$src = self::read( self::util() . $class . '.php' );

			$this->assertStringContainsString( 'final class ' . $class, $src );
			$this->assertStringContainsString( 'private function __construct()', $src );
		}
	}

	public function test_the_integration_claims_no_prefix(): void {
		$src = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/Loco_Translate.php' );

		$this->assertStringContainsString( "TAB_GROUP = 'translations'", $src );
		$this->assertMatchesRegularExpression(
			'/function ability_prefixes\(\): array \{\s*return array\(\);/',
			$src,
			'Loco registers nothing, so claiming a prefix would capture unrelated future abilities.'
		);
	}
}
