<?php
/**
 * Feature 107 — architectural invariants across the Classic Editor suite.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.39
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Classic_Editor_Architecture extends WP_UnitTestCase {

	private const NON_ABILITY_FILES = array( 'Category_Registrar.php', 'Base_Classic_Editor_Ability.php' );

	private static function abilities_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/ClassicEditor/';
	}

	private static function utilities_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/ClassicEditor/';
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
	 * @return string[]
	 */
	private static function suite_files(): array {
		$a = glob( self::abilities_dir() . '*.php' );
		$u = glob( self::utilities_dir() . '*.php' );

		return array_merge( is_array( $a ) ? $a : array(), is_array( $u ) ? $u : array() );
	}

	/**
	 * Comments and string literals stripped.
	 *
	 * Both matter: a docblock legitimately names the plugin class it wraps, and our own namespace
	 * ends in `\ClassicEditor`, which would make every file look like a leak.
	 */
	private static function code_no_strings( string $src ): string {
		$out = '';

		foreach ( token_get_all( $src ) as $token ) {
			if ( is_array( $token ) ) {
				if ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE ), true ) ) {
					continue;
				}

				$out .= $token[1];
				continue;
			}

			$out .= $token;
		}

		return str_replace(
			array( 'Abilities\\Utilities\\ClassicEditor', 'Abilities\\ClassicEditor', 'Utilities\\ClassicEditor' ),
			'',
			$out
		);
	}

	public function test_the_suite_is_not_empty(): void {
		$this->assertCount( 4, self::ability_files() );
	}

	/**
	 * No Classic_Editor symbol outside Utilities/ClassicEditor/.
	 */
	public function test_no_plugin_symbol_in_ability_classes(): void {
		foreach ( self::ability_files() as $file ) {
			$code = self::code_no_strings( (string) file_get_contents( $file ) );

			$this->assertDoesNotMatchRegularExpression(
				'/\bClassic_Editor::/',
				$code,
				basename( $file ) . ' calls the plugin directly. That belongs behind Utilities/ClassicEditor/.'
			);
			$this->assertStringNotContainsString(
				'CLASSIC_EDITOR_VERSION',
				$code,
				basename( $file ) . ' probes the plugin directly. Use the guard.'
			);
		}
	}

	/**
	 * The option, user-option and post-meta keys are named in exactly one place.
	 */
	public function test_storage_keys_live_only_in_the_repository(): void {
		foreach ( self::ability_files() as $file ) {
			$src = (string) file_get_contents( $file );

			foreach ( array( 'classic-editor-replace', 'classic-editor-allow-users', 'classic-editor-allow-sites' ) as $key ) {
				$this->assertStringNotContainsString(
					"'" . $key . "'",
					$src,
					basename( $file ) . " hardcodes the option key {$key}. Use Editor_Settings_Repository's constant."
				);
			}
		}
	}

	/**
	 * The per-user preference must go through get_user_option(), never get_user_meta().
	 *
	 * The plugin stores it with update_user_option(), so the real meta key is blog-prefixed.
	 * Reading it with get_user_meta() looks correct, passes review, and silently returns nothing on
	 * any site whose table prefix is not `wp_`.
	 */
	public function test_the_user_preference_is_read_as_a_user_option(): void {
		foreach ( self::suite_files() as $file ) {
			$code = self::code_no_strings( (string) file_get_contents( $file ) );

			$this->assertStringNotContainsString(
				'get_user_meta(',
				$code,
				basename( $file ) . ' reads the per-user preference with get_user_meta(). It is a user OPTION and the key is blog-prefixed.'
			);
		}

		$this->assertStringContainsString(
			'get_user_option(',
			(string) file_get_contents( self::utilities_dir() . 'Editor_Settings_Repository.php' )
		);
	}

	/**
	 * The three value vocabularies must not be mixed.
	 *
	 * Options and the user preference use classic/block; the post meta uses
	 * classic-editor/block-editor. Writing one where the other belongs is the most likely bug here
	 * and would be invisible — both are non-empty strings the plugin simply ignores.
	 */
	public function test_the_post_meta_vocabulary_is_kept_separate(): void {
		$repo = (string) file_get_contents( self::utilities_dir() . 'Editor_Settings_Repository.php' );

		$this->assertMatchesRegularExpression(
			"/const EDITORS\s*=\s*array\(\s*'classic',\s*'block',?\s*\)/",
			$repo,
			'The settings vocabulary must be exactly classic/block.'
		);
		$this->assertMatchesRegularExpression(
			"/const POST_EDITORS\s*=\s*array\(\s*'classic-editor',\s*'block-editor',?\s*\)/",
			$repo,
			'The post-meta vocabulary must be exactly classic-editor/block-editor.'
		);
	}

	/**
	 * Both legacy-normalisation asymmetries are reproduced, not "fixed".
	 *
	 * Single-site maps the legacy `no-replace` to block; the multisite path does not. Tidying that
	 * up would make this suite disagree with the plugin it is reporting on.
	 */
	public function test_the_single_site_legacy_normalisation_is_preserved(): void {
		$repo = (string) file_get_contents( self::utilities_dir() . 'Editor_Settings_Repository.php' );

		$this->assertStringContainsString(
			"'no-replace' === \$stored_editor",
			$repo,
			'The single-site path must still honour the legacy no-replace value.'
		);

		$multisite = substr( $repo, strpos( $repo, 'if ( $multisite ) {' ), strpos( $repo, '} else {' ) - strpos( $repo, 'if ( $multisite ) {' ) );

		$this->assertStringNotContainsString(
			'no-replace',
			$multisite,
			'The multisite path does NOT honour no-replace in the plugin; mirroring it means not adding it here.'
		);
	}

	/**
	 * Settings are written through the plugin's validators and then read back.
	 */
	public function test_writes_go_through_the_plugin_validators_and_are_verified(): void {
		$repo = (string) file_get_contents( self::utilities_dir() . 'Editor_Settings_Repository.php' );

		$this->assertStringContainsString( 'validate_option_editor', $repo );
		$this->assertStringContainsString( 'validate_option_allow_users', $repo );
		$this->assertMatchesRegularExpression(
			'/update_option\([^;]*\);\s*(?:\/\/[^\n]*\n\s*)*\$stored\s*=\s*\(string\) get_option\(/',
			$repo,
			'write() must re-read the option after saving; register_setting()\'s sanitize callback does not run on a plain update_option().'
		);
		$this->assertStringContainsString( 'setting_rejected', $repo );
	}

	public function test_every_ability_is_final_and_extends_the_base(): void {
		foreach ( self::ability_files() as $file ) {
			$src = (string) file_get_contents( $file );

			$this->assertMatchesRegularExpression( '/\bfinal class\b/', $src, basename( $file ) );
			$this->assertStringContainsString( 'extends Base_Classic_Editor_Ability', $src, basename( $file ) );
		}
	}

	/**
	 * The floor is declared once, final, on the base.
	 */
	public function test_the_capability_floor_is_final_and_not_overridden(): void {
		$base = (string) file_get_contents( self::abilities_dir() . 'Base_Classic_Editor_Ability.php' );

		$this->assertMatchesRegularExpression(
			"/final protected function permission_floor\(\): string \{\s*return 'manage_options';/",
			$base
		);

		foreach ( self::ability_files() as $file ) {
			$this->assertStringNotContainsString(
				'function permission_floor',
				(string) file_get_contents( $file ),
				basename( $file ) . ' overrides the floor.'
			);
		}
	}

	/**
	 * The tab group matches the integration, or the abilities and the dispatcher disagree.
	 */
	public function test_the_tab_group_matches_the_integration(): void {
		$base        = (string) file_get_contents( self::abilities_dir() . 'Base_Classic_Editor_Ability.php' );
		$integration = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/Classic_Editor.php' );

		$this->assertStringContainsString( "TAB_GROUP = 'classic-editor'", $base );
		$this->assertStringContainsString( "TAB_GROUP = 'classic-editor'", $integration );
	}

	/**
	 * Nothing is claimed by prefix.
	 *
	 * Classic Editor registers no abilities, so there is nothing to adopt; claiming `editor` would
	 * capture any future ability in that namespace.
	 */
	public function test_the_integration_claims_no_prefixes(): void {
		$src = (string) file_get_contents( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/Classic_Editor.php' );

		$this->assertMatchesRegularExpression(
			'/function ability_prefixes\(\): array \{\s*return array\(\);/',
			$src
		);
	}

	/**
	 * Utilities are final and static-only (DEC-UTILITY-STATIC-ONLY).
	 */
	public function test_utilities_are_final_and_static_only(): void {
		foreach ( glob( self::utilities_dir() . '*.php' ) ?: array() as $file ) {
			$src = (string) file_get_contents( $file );

			$this->assertMatchesRegularExpression( '/\bfinal class\b/', $src, basename( $file ) );
			$this->assertStringContainsString( 'private function __construct()', $src, basename( $file ) );
		}
	}
}
