<?php
/**
 * Feature 107 — the whole-suite contract.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.39
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Classic_Editor_Suite_Contract extends WP_UnitTestCase {

	private const DIR = '/includes/Abilities/ClassicEditor/';

	/**
	 * Class => slug.
	 *
	 * The suite is four abilities because the rest of Classic Editor's surface is already
	 * reachable: the two settings through `options/*`, the per-post choice through
	 * `content/*-post-meta`, and the per-user choice through `users/get-user` and
	 * `users/update-user`. What is left is the derived state, which lives behind three private
	 * methods in the plugin, plus one validated writer.
	 *
	 * @return array<string,string>
	 */
	private static function inventory(): array {
		return array(
			// editor-settings (2).
			'Get_Editor_Settings'          => 'editor/get-editor-settings',
			'Update_Editor_Settings'       => 'editor/update-editor-settings',
			// editor-resolution (2).
			'Get_Post_Editor'              => 'editor/get-post-editor',
			'Get_Post_Type_Editor_Support' => 'editor/get-post-type-editor-support',
		);
	}

	/**
	 * @return array<string,int>
	 */
	private static function sub_group_counts(): array {
		return array(
			'editor-resolution' => 2,
			'editor-settings'   => 2,
		);
	}

	private static function dir(): string {
		return dirname( __DIR__, 3 ) . self::DIR;
	}

	private static function source( string $class ): string {
		$file = self::dir() . $class . '.php';

		return is_readable( $file ) ? (string) file_get_contents( $file ) : '';
	}

	public function test_inventory_matches_the_directory(): void {
		$files = glob( self::dir() . '*.php' );
		$found = array();

		foreach ( is_array( $files ) ? $files : array() as $file ) {
			$base = basename( $file, '.php' );

			if ( in_array( $base, array( 'Category_Registrar', 'Base_Classic_Editor_Ability' ), true ) ) {
				continue;
			}

			$found[] = $base;
		}

		sort( $found );
		$expected = array_keys( self::inventory() );
		sort( $expected );

		$this->assertSame( $expected, $found );
	}

	public function test_every_slug_is_declared_and_unique(): void {
		$slugs = array();

		foreach ( self::inventory() as $class => $slug ) {
			$src = self::source( $class );

			$this->assertNotSame( '', $src, "{$class}.php is missing." );
			$this->assertMatchesRegularExpression(
				"/function slug\(\): string \{\s*return '" . preg_quote( $slug, '/' ) . "'/",
				$src,
				"{$class} does not declare slug {$slug}."
			);

			$slugs[] = $slug;
		}

		$this->assertSame( count( $slugs ), count( array_unique( $slugs ) ) );
	}

	/**
	 * The slug namespace is `editor/`, and it must not collide with an existing one.
	 */
	public function test_slugs_use_the_editor_namespace(): void {
		foreach ( self::inventory() as $class => $slug ) {
			$this->assertStringStartsWith( 'editor/', $slug, "{$class}" );
		}
	}

	public function test_sub_group_counts_hold(): void {
		$counts = array();

		foreach ( array_keys( self::inventory() ) as $class ) {
			$this->assertMatchesRegularExpression(
				"/function sub_group\(\): string \{\s*return '([a-z-]+)'/",
				self::source( $class ),
				"{$class} declares no sub_group."
			);

			preg_match( "/function sub_group\(\): string \{\s*return '([a-z-]+)'/", self::source( $class ), $m );
			$counts[ $m[1] ] = ( $counts[ $m[1] ] ?? 0 ) + 1;
		}

		ksort( $counts );
		$expected = self::sub_group_counts();
		ksort( $expected );

		$this->assertSame( $expected, $counts );
		$this->assertSame( count( self::inventory() ), array_sum( $counts ) );
	}

	/**
	 * Only the settings writer is a writer, and it is the only one that can ask for confirmation.
	 */
	public function test_only_the_settings_writer_mutates(): void {
		foreach ( self::inventory() as $class => $slug ) {
			$src      = self::source( $class );
			$readonly = (bool) preg_match( "/'readonly'\s*=> true/", $src );

			if ( 'Update_Editor_Settings' === $class ) {
				$this->assertFalse( $readonly, 'The settings writer must not be annotated readonly.' );
				continue;
			}

			$this->assertTrue( $readonly, "{$class} must be annotated readonly." );
			$this->assertStringNotContainsString(
				'function requires_confirmation',
				$src,
				"{$class} is read-only and has nothing to confirm."
			);
		}
	}

	/**
	 * Confirmation is asked for the change that warrants it and no other.
	 *
	 * `requires_confirmation()` puts `confirm` in the schema; `needs_confirmation_for()` decides
	 * whether this particular call is gated. Collapsing the two would block a call that only flips
	 * the allow-users switch, which changes nothing about what any post opens in.
	 */
	public function test_the_confirmation_gate_is_narrowed_to_a_real_default_change(): void {
		$src = self::source( 'Update_Editor_Settings' );

		$this->assertStringContainsString( 'function requires_confirmation', $src );
		$this->assertStringContainsString( 'function needs_confirmation_for', $src );
		$this->assertMatchesRegularExpression(
			"/if \(\s*! array_key_exists\( 'editor', \\\$input \)\s*\) \{\s*return false;/",
			$src,
			'A call that does not touch the site default must not be gated.'
		);
	}

	/**
	 * No `Slash_Input`, and no `is_writer()` flag.
	 *
	 * Every input in this suite is an enum or an integer, so nothing accepts caller free text.
	 * Declaring a slashing flag that slashes nothing advertises a control that does not exist.
	 */
	public function test_the_suite_declares_no_slash_handling(): void {
		$files = array_merge(
			glob( self::dir() . '*.php' ) ?: array(),
			glob( dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/ClassicEditor/*.php' ) ?: array()
		);

		foreach ( $files as $file ) {
			// Comments stripped: the base class's docblock explains WHY there is no Slash_Input
			// here, and searching raw source would flag that explanation as the violation.
			$code = '';

			foreach ( token_get_all( (string) file_get_contents( $file ) ) as $token ) {
				if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
					continue;
				}

				$code .= is_array( $token ) ? $token[1] : $token;
			}

			$this->assertStringNotContainsString( 'Slash_Input', $code, basename( $file ) );
			$this->assertStringNotContainsString( 'function is_writer', $code, basename( $file ) );
		}
	}

	/**
	 * Every suggested slug resolves.
	 *
	 * These point almost entirely at OTHER suites — that is the design, since the raw state is
	 * already reachable there — so a rename elsewhere breaks them silently.
	 */
	public function test_every_suggested_slug_resolves(): void {
		$own     = array_values( self::inventory() );
		$checked = 0;

		foreach ( array_keys( self::inventory() ) as $class ) {
			$src = self::source( $class );

			if ( ! preg_match( '/function suggested_abilities\(\): array \{(.*?)\n\t\}/s', $src, $m ) ) {
				continue;
			}

			preg_match_all( "/'slug'\s*=> '([a-z0-9-]+\/[a-z0-9-]+)'/", $m[1], $hits );

			foreach ( $hits[1] as $slug ) {
				++$checked;

				if ( function_exists( 'wp_get_ability' ) && null !== wp_get_ability( $slug ) ) {
					continue;
				}

				$this->assertContains(
					$slug,
					array_merge( $own, self::known_foreign_slugs() ),
					"{$class} suggests \"{$slug}\", which is neither registered nor a slug this suite knows about."
				);
			}
		}

		$this->assertGreaterThan( 0, $checked );
	}

	/**
	 * Slugs in other suites this one deliberately points at.
	 *
	 * Listed rather than inferred so that removing one of them upstream fails here loudly.
	 *
	 * @return string[]
	 */
	private static function known_foreign_slugs(): array {
		return array(
			'content/update-post-meta',
			'content/delete-post-meta',
			'content/list-posts',
			'content/list-post-types',
			'users/get-user',
			'users/update-user',
		);
	}

	/**
	 * Those foreign slugs must actually exist in the tree.
	 */
	public function test_the_foreign_slugs_still_exist(): void {
		$root = dirname( __DIR__, 3 ) . '/includes/Abilities/';
		$all  = '';

		foreach ( array( 'Content', 'Users' ) as $folder ) {
			foreach ( glob( $root . $folder . '/*.php' ) ?: array() as $file ) {
				$all .= (string) file_get_contents( $file );
			}
		}

		foreach ( self::known_foreign_slugs() as $slug ) {
			$this->assertStringContainsString( "'" . $slug . "'", $all, "Suggested slug {$slug} no longer exists." );
		}
	}
}
