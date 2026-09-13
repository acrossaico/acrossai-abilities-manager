<?php
/**
 * Feature 106 — architectural invariants across the whole Yoast SEO suite.
 *
 * Every assertion here was written because live execution found the failure first. Where that is
 * the case the docblock says so, because the alternative reading — that these are speculative
 * rules — invites someone to delete them.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.38
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Yoast_Architecture extends WP_UnitTestCase {

	private const NON_ABILITY_FILES = array(
		'Category_Registrar.php',
		'Base_Yoast_Ability.php',
		'Base_Settings_Write_Ability.php',
	);

	private static function abilities_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Yoast/';
	}

	private static function utilities_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Yoast/';
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
	 * Strip comments and our own namespace segments.
	 *
	 * Our namespace contains the vendor string (`...\Abilities\Utilities\Yoast`), so without the
	 * replacement every file in the suite looks like a leak — the trap Feature 104 hit.
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
			array( 'Abilities\\Utilities\\Yoast', 'Abilities\\Yoast', 'Utilities\\Yoast' ),
			'',
			$stripped
		);
	}

	/**
	 * Comments and string literals stripped, for the tests that search for Yoast symbol names.
	 *
	 * Descriptions legitimately name the API they wrap, so searching raw source flags the
	 * documentation as a violation.
	 */
	private static function code_no_strings( string $src ): string {
		$stripped = '';

		foreach ( token_get_all( $src ) as $token ) {
			if ( is_array( $token ) ) {
				if ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE ), true ) ) {
					continue;
				}

				$stripped .= $token[1];
				continue;
			}

			$stripped .= $token;
		}

		return str_replace(
			array( 'Abilities\\Utilities\\Yoast', 'Abilities\\Yoast', 'Utilities\\Yoast' ),
			'',
			$stripped
		);
	}

	public function test_suite_is_not_empty(): void {
		$this->assertGreaterThan( 50, count( self::ability_files() ), 'Yoast ability files vanished — every other test here would pass vacuously.' );
	}

	/**
	 * No Yoast symbol outside Utilities/Yoast/.
	 *
	 * Yoast's API is a mix of `WPSEO_*` classes and `Yoast\WP\SEO\*` namespaced ones, so both
	 * spellings are covered.
	 */
	public function test_no_yoast_symbols_in_ability_classes(): void {
		foreach ( self::ability_files() as $file ) {
			$code = self::code_no_strings( (string) file_get_contents( $file ) );

			$this->assertDoesNotMatchRegularExpression(
				'/\bWPSEO_[A-Za-z_]+/',
				$code,
				basename( $file ) . ' names a WPSEO_* symbol. Yoast\'s API belongs behind Utilities/Yoast/.'
			);

			$this->assertStringNotContainsString(
				'Yoast\\WP\\SEO',
				$code,
				basename( $file ) . ' names a Yoast\\WP\\SEO symbol. Yoast\'s API belongs behind Utilities/Yoast/.'
			);

			$this->assertStringNotContainsString(
				'YoastSEO(',
				$code,
				basename( $file ) . ' calls YoastSEO(). Yoast\'s API belongs behind Utilities/Yoast/.'
			);
		}
	}

	/**
	 * The suite must not inherit Yoast's production-only gate.
	 *
	 * Measured before this suite existed: Yoast disables its own abilities whenever
	 * `wp_get_environment_type()` is not "production", via Should_Index_Indexables_Conditional.
	 * That is right for indexables and wrong for settings, terms, sitemaps and tools. Copying it by
	 * reflex would make all 64 abilities vanish on every staging site — invisible in CI, because CI
	 * is not production either.
	 */
	public function test_suite_does_not_gate_on_environment(): void {
		foreach ( self::suite_files() as $file ) {
			$code = self::code_no_strings( (string) file_get_contents( $file ) );

			foreach ( array( 'is_production_mode', 'should_index_indexables' ) as $forbidden ) {
				$this->assertStringNotContainsString(
					$forbidden,
					$code,
					basename( $file ) . " references {$forbidden}. The suite must work on staging; only Yoast's own abilities are production-gated."
				);
			}

			// wp_get_environment_type() is fine to REPORT — Tools_Repository::status() surfaces it,
			// which is exactly what an operator debugging a staging install wants. It must never
			// decide control flow.
			$this->assertDoesNotMatchRegularExpression(
				'/\b(?:if|while|switch)\s*\([^)]*wp_get_environment_type/',
				$code,
				basename( $file ) . ' branches on wp_get_environment_type(). Report the environment; never gate on it.'
			);
		}
	}

	/**
	 * Exactly one call site persists a setting.
	 *
	 * Anything reaching past Settings_Repository::save() skips Yoast's per-group validation — and
	 * the read-back that catches a rejected value.
	 */
	public function test_no_ability_writes_a_yoast_option_directly(): void {
		foreach ( self::ability_files() as $file ) {
			$this->assertStringNotContainsString(
				'WPSEO_Options::set',
				self::code_no_strings( (string) file_get_contents( $file ) ),
				basename( $file ) . ' writes a Yoast option directly. Go through Settings_Repository::write().'
			);
		}
	}

	/**
	 * A repository writing outside Settings_Repository must not touch a settings-map key.
	 *
	 * Tools_Repository::reset_indexing() legitimately writes `indexing_*` bookkeeping, which is
	 * deliberately excluded from the settings map and has no area to route through. The danger is a
	 * future write of a real setting from there, which would skip Yoast's validation and the
	 * read-back that catches a rejected value.
	 */
	public function test_repository_writes_outside_the_settings_map_touch_no_mapped_key(): void {
		$repo = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Utilities\\Yoast\\Settings_Repository';

		if ( ! class_exists( $repo ) ) {
			$this->markTestSkipped( 'Settings_Repository not loaded.' );
		}

		$mapped = array();

		foreach ( $repo::areas() as $keys ) {
			$mapped = array_merge( $mapped, array_keys( $keys ) );
		}

		$this->assertNotEmpty( $mapped, 'The settings map is empty — this test would pass vacuously.' );

		foreach ( glob( self::utilities_dir() . '*.php' ) ?: array() as $file ) {
			if ( 'Settings_Repository.php' === basename( $file ) ) {
				continue;
			}

			$src = (string) file_get_contents( $file );

			if ( ! preg_match_all( "/WPSEO_Options::set\(\s*'([^']+)'/", $src, $hits ) ) {
				continue;
			}

			foreach ( $hits[1] as $key ) {
				$this->assertNotContains(
					$key,
					$mapped,
					basename( $file ) . " writes \"{$key}\", which IS in the settings map. Route it through Settings_Repository::write() so Yoast validates it and the value is read back."
				);
			}
		}
	}

	/**
	 * Every settings area declares the ability that writes it, and that ability exists.
	 *
	 * Found live: List_Settings_Areas built these slugs by concatenating "seo/update-" onto the
	 * area name. That resolved for 5 of 14 areas and advertised nine abilities that do not exist,
	 * while two areas (integrations, advanced) had no writer at all and were silently read-only.
	 */
	public function test_every_settings_area_has_a_real_writer(): void {
		$repo = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Utilities\\Yoast\\Settings_Repository';

		if ( ! class_exists( $repo ) ) {
			$this->markTestSkipped( 'Settings_Repository not loaded.' );
		}

		$slugs = array();

		foreach ( self::ability_files() as $file ) {
			if ( preg_match( "/function slug\(\): string \{\s*return '([^']+)'/", (string) file_get_contents( $file ), $m ) ) {
				$slugs[] = $m[1];
			}
		}

		$this->assertNotEmpty( $slugs, 'No ability slugs parsed.' );

		foreach ( array_keys( $repo::areas() ) as $area ) {
			$writer = $repo::writer_for( (string) $area );

			$this->assertNotSame( '', $writer, "Settings area \"{$area}\" has no writer. Add one, or remove the area." );
			$this->assertContains( $writer, $slugs, "Settings area \"{$area}\" names writer \"{$writer}\", which is not an ability in this suite." );
		}
	}

	/**
	 * Keys Yoast's own settings screen refuses must not be readable or writable here.
	 *
	 * Two of them — semrush_tokens and wincher_tokens — hold live OAuth access and refresh tokens.
	 * The reader returned every key in an area, so before this was fixed `seo/get-seo-settings`
	 * handed those credentials to any caller, which on an MCP install means off-site. Asserted
	 * against Yoast's live constant so an upstream addition fails here rather than leaking.
	 */
	public function test_disallowed_keys_are_absent_from_every_area(): void {
		$repo = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Utilities\\Yoast\\Settings_Repository';

		if ( ! class_exists( $repo ) ) {
			$this->markTestSkipped( 'Settings_Repository not loaded.' );
		}

		$blocked = $repo::disallowed();

		$this->assertContains( 'semrush_tokens', $blocked, 'The OAuth token keys must stay blocked.' );
		$this->assertContains( 'wincher_tokens', $blocked, 'The OAuth token keys must stay blocked.' );

		foreach ( $repo::areas() as $area => $keys ) {
			foreach ( $blocked as $key ) {
				$this->assertArrayNotHasKey(
					$key,
					$keys,
					"Area \"{$area}\" exposes \"{$key}\", which Yoast's own settings screen refuses to render or save."
				);
			}
		}

		$live = 'Yoast\\WP\\SEO\\Integrations\\Settings_Integration';

		if ( class_exists( $live ) && defined( $live . '::DISALLOWED_SETTINGS' ) ) {
			foreach ( (array) constant( $live . '::DISALLOWED_SETTINGS' ) as $group_keys ) {
				foreach ( (array) $group_keys as $key ) {
					$this->assertContains(
						(string) $key,
						$blocked,
						"Yoast now disallows \"{$key}\" and this suite does not. Add it to Settings_Repository::disallowed()."
					);
				}
			}
		}
	}

	/**
	 * A write must be verified by reading it back.
	 *
	 * Found live: Yoast validates per option group and silently keeps the old value when the new one
	 * fails — enum keys like schema-article-type-* and llms_txt_selection_mode do exactly this. The
	 * writer reported "Updated: <key>" for a write that never happened, which is worse than an error
	 * because the caller stops checking.
	 */
	public function test_write_path_reads_back_before_reporting_success(): void {
		$src = (string) file_get_contents( self::utilities_dir() . 'Settings_Repository.php' );

		$this->assertMatchesRegularExpression(
			'/self::save\([^;]*\);\s*(?:\/\/[^\n]*\n\s*)*\$stored\s*=\s*self::cast\(\s*self::value\(/',
			self::code_only( $src ),
			'Settings_Repository::write() must re-read each key after save() and only report keys whose stored value matches the request.'
		);

		$this->assertStringContainsString(
			'setting_rejected',
			$src,
			'write() must surface the keys Yoast refused, naming the value it kept.'
		);
	}

	/**
	 * Per-post-type and per-taxonomy keys must be derived at runtime, not frozen.
	 *
	 * Found live: the literal map was machine-derived from one install's defaults, so it froze the
	 * types that existed that day (post, page, attachment, category, post_tag). Every custom post
	 * type and taxonomy was unwritable, and because no post type on that install had an archive the
	 * map held no `*-ptarchive-*` key at all — seo/update-post-type-archive-seo could not write
	 * anything on any site.
	 */
	public function test_dynamic_option_keys_are_derived_from_the_live_install(): void {
		$src = self::code_only( (string) file_get_contents( self::utilities_dir() . 'Settings_Repository.php' ) );

		$this->assertStringContainsString(
			'dynamic_keys',
			$src,
			'Settings_Repository must derive per-post-type keys at runtime.'
		);

		$this->assertStringContainsString(
			"WPSEO_Options::get_option( 'wpseo_titles' )",
			$src,
			'The dynamic keys must come from the live wpseo_titles option, which Yoast enriches per registered post type and taxonomy.'
		);

		$repo = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Utilities\\Yoast\\Settings_Repository';

		if ( ! class_exists( $repo ) || ! class_exists( '\\WPSEO_Options' ) ) {
			return;
		}

		$keys = array();

		foreach ( $repo::areas() as $area_keys ) {
			$keys = array_merge( $keys, array_keys( $area_keys ) );
		}

		register_post_type(
			'yoast_arch_probe',
			array( 'public' => true, 'has_archive' => true, 'label' => 'Probe' )
		);

		$repo::flush();

		$after = array();

		foreach ( $repo::areas() as $area_keys ) {
			$after = array_merge( $after, array_keys( $area_keys ) );
		}

		unregister_post_type( 'yoast_arch_probe' );
		$repo::flush();

		$this->assertContains(
			'title-ptarchive-yoast_arch_probe',
			$after,
			'A post type registered with an archive must become writable. A frozen key map silently drops every custom type.'
		);
		$this->assertNotContains( 'title-ptarchive-yoast_arch_probe', $keys );
	}

	/**
	 * Our slugs must never collide with Yoast's own namespace.
	 *
	 * Integrations/Yoast_Seo.php claims the `yoast-seo` prefix so Yoast's own abilities are adopted
	 * into the tab. An ability of ours under that prefix would shadow one of theirs.
	 */
	public function test_no_ability_uses_the_yoast_seo_slug_namespace(): void {
		foreach ( self::ability_files() as $file ) {
			$this->assertDoesNotMatchRegularExpression(
				"/return '(yoast-seo\/)/",
				(string) file_get_contents( $file ),
				basename( $file ) . " uses the yoast-seo/ slug namespace, which belongs to Yoast. It would shadow one of Yoast's own abilities."
			);
		}
	}

	public function test_every_ability_is_final(): void {
		foreach ( self::ability_files() as $file ) {
			$this->assertMatchesRegularExpression(
				'/\bfinal class\b/',
				(string) file_get_contents( $file ),
				basename( $file ) . ' must be declared final.'
			);
		}
	}
}
