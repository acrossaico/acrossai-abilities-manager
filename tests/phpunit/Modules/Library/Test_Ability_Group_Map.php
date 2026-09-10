<?php
/**
 * Tests: Feature 101 — the ability group map.
 *
 * Every bundled ability declares a `tab_group` naming the group it belongs
 * to on the Ability Integrations screen. There is no allow-list, no
 * server-side validation and no registration call for tab_group values — a
 * wrong or half-applied value produces a plausible-looking tab, no error and
 * no other failing test. This suite is the only thing standing between a
 * mistyped sweep and a silently misfiled ability.
 *
 * It reads the source files directly rather than booting WordPress, so it
 * runs in the same stub bootstrap as the rest of the suite.
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Modules\Library;

use PHPUnit\Framework\TestCase;

/**
 * Pins every ability's tab_group to the Feature 101 group map.
 */
class Test_Ability_Group_Map extends TestCase {

	/**
	 * Folders whose every ability shares one group.
	 *
	 * @var array<string, string>
	 */
	private const WHOLE_FOLDER = array(
		'Content'       => 'content',
		'Comments'      => 'content',
		'Taxonomies'    => 'content',
		'ContentSearch' => 'content',
		'Menus'         => 'appearance',
		'Fonts'         => 'appearance',
		'Widgets'       => 'appearance',
		'Options'       => 'configuration',
		'AdminMenu'     => 'configuration',
		'Plugins'       => 'updates',
		'Themes'        => 'updates',
		'Core'          => 'updates',
		'Cron'          => 'cron',
		'FileManager'   => 'files',
		'Debugging'     => 'diagnostics',
		'Recovery'      => 'diagnostics',
		'SiteHealth'    => 'diagnostics',
		'Users'         => 'users',
	);

	/**
	 * Block sub_groups that are theme design rather than authoring.
	 *
	 * @var string[]
	 */
	private const BLOCK_DESIGN_SUB_GROUPS = array(
		'global-styles',
		'theme-json-settings',
		'templates',
		'template-parts',
		'block-style-variations',
		'site-editor',
	);

	/**
	 * Settings sub_group => group.
	 *
	 * @var array<string, string>
	 */
	private const SETTINGS_SUB_GROUPS = array(
		'site-identity' => 'appearance',
		'permalinks'    => 'configuration',
	);

	/**
	 * Abilities whose group is not derivable from their folder or sub_group.
	 *
	 * Each is a multi-purpose ability whose slug correctly matches its folder —
	 * it is filed by the job it does, not by where the file lives.
	 *
	 * @var array<string, string>
	 */
	private const SLUG_OVERRIDES = array(
		// Change what the whole site accepts on upload: security configuration.
		'media/list-upload-mime-types'    => 'configuration',
		'media/update-upload-mime-types'  => 'configuration',
		// A permalinks operation; near-duplicate of settings/flush-permalink-structure.
		'cache/flush-rewrite-rules'       => 'configuration',
		// Duplicate of cache/delete-expired-transients.
		'database/cleanup-expired-transients' => 'cache',
	);

	/**
	 * Expected ability count per group, excluding the conditional
	 * integrations (Elementor, Rank Math) which are not swept.
	 *
	 * @var array<string, int>
	 */
	private const EXPECTED_COUNTS = array(
		'content'       => 71,
		'appearance'    => 64,
		'blocks'        => 52,
		'files'         => 23,
		'updates'       => 23,
		'diagnostics'   => 20,
		'configuration' => 19,
		'database'      => 17,
		'cron'          => 16,
		'users'         => 16,
		'cache'         => 7,
	);

	/**
	 * Absolute path to includes/Abilities.
	 *
	 * @return string
	 */
	private function abilities_dir(): string {
		// tests/phpunit/Modules/Library -> plugin root is four levels up.
		return dirname( __DIR__, 4 ) . '/includes/Abilities';
	}

	/**
	 * Every swept ability file as [ folder, slug, sub_group, tab_group ].
	 *
	 * @return array<int, array{0:string,1:string,2:string,3:string}>
	 */
	private function abilities(): array {
		$skip = array( 'Elementor', 'RankMath', 'Integrations', 'Utilities', 'Rest' );
		$out  = array();

		foreach ( glob( $this->abilities_dir() . '/*', GLOB_ONLYDIR ) as $dir ) {
			$folder = basename( $dir );
			if ( in_array( $folder, $skip, true ) ) {
				continue;
			}
			foreach ( glob( $dir . '/*.php' ) as $file ) {
				$src = (string) file_get_contents( $file );
				if ( ! preg_match( "/'tab_group'\s*=>\s*'([a-z0-9-]+)'/", $src, $tg ) ) {
					continue;
				}
				preg_match( "/'name'\s*=>\s*'([a-z0-9\/_-]+)'/", $src, $slug );
				preg_match( "/'sub_group'\s*=>\s*'([a-z0-9-]+)'/", $src, $sg );
				$out[] = array(
					$folder,
					$slug[1] ?? '',
					$sg[1] ?? '',
					$tg[1],
				);
			}
		}

		return $out;
	}

	/**
	 * The group an ability is required to declare.
	 *
	 * @param string $folder    Enclosing folder name.
	 * @param string $slug      Ability slug.
	 * @param string $sub_group Declared sub_group.
	 * @return string|null Group, or null when no rule covers it.
	 */
	private function expected_group( string $folder, string $slug, string $sub_group ): ?string {
		if ( isset( self::SLUG_OVERRIDES[ $slug ] ) ) {
			return self::SLUG_OVERRIDES[ $slug ];
		}
		if ( 'Block' === $folder ) {
			return in_array( $sub_group, self::BLOCK_DESIGN_SUB_GROUPS, true ) ? 'appearance' : 'blocks';
		}
		if ( 'Settings' === $folder ) {
			return self::SETTINGS_SUB_GROUPS[ $sub_group ] ?? null;
		}
		if ( 'Media' === $folder ) {
			return 'content';
		}
		if ( 'Cache' === $folder ) {
			return 'cache';
		}
		if ( 'Database' === $folder ) {
			return 'database';
		}

		return self::WHOLE_FOLDER[ $folder ] ?? null;
	}

	/**
	 * Every ability declares the group the map assigns it.
	 */
	public function test_every_ability_declares_its_mapped_group(): void {
		$wrong = array();

		foreach ( $this->abilities() as list( $folder, $slug, $sub_group, $actual ) ) {
			$expected = $this->expected_group( $folder, $slug, $sub_group );

			if ( null === $expected ) {
				$wrong[] = "{$folder}/{$slug}: no group rule (sub_group '{$sub_group}')";
				continue;
			}
			if ( $expected !== $actual ) {
				$wrong[] = "{$slug}: expected '{$expected}', declared '{$actual}'";
			}
		}

		$this->assertSame(
			array(),
			$wrong,
			"Abilities filed under the wrong group:\n  " . implode( "\n  ", $wrong )
		);
	}

	/**
	 * `core` is retired — it was a bucket, not a subject.
	 */
	public function test_core_tab_group_is_fully_retired(): void {
		$offenders = array();

		foreach ( $this->abilities() as list( , $slug, , $tab_group ) ) {
			if ( 'core' === $tab_group ) {
				$offenders[] = $slug;
			}
		}

		$this->assertSame(
			array(),
			$offenders,
			'Feature 101 retired the `core` tab_group; these still declare it: ' . implode( ', ', $offenders )
		);
	}

	/**
	 * Group sizes match the map, so a whole folder cannot go missing.
	 */
	public function test_group_sizes_match_the_map(): void {
		$counts = array();

		foreach ( $this->abilities() as list( , , , $tab_group ) ) {
			$counts[ $tab_group ] = ( $counts[ $tab_group ] ?? 0 ) + 1;
		}

		ksort( $counts );
		$expected = self::EXPECTED_COUNTS;
		ksort( $expected );

		$this->assertSame( $expected, $counts );
	}

	/**
	 * Every group key title-cases into a usable label.
	 *
	 * Spec 037 FR-007 forbids a separate display-label field — the key IS the
	 * label — so a key containing anything the formatter mangles is a bug.
	 */
	public function test_every_group_key_is_label_safe(): void {
		foreach ( array_keys( self::EXPECTED_COUNTS ) as $key ) {
			$this->assertMatchesRegularExpression(
				'/^[a-z]+(-[a-z]+)*$/',
				$key,
				"Group key '{$key}' must be lowercase words separated by single hyphens."
			);
		}
	}

	/**
	 * The eight relocated block abilities live in Block/ and say so.
	 *
	 * Their slug was always `blocks/*` while their category said `content`.
	 * Feature 101 moved the files; the slugs deliberately did not change, so
	 * no MCP client was affected.
	 */
	public function test_relocated_block_abilities_are_consistent(): void {
		$moved = array(
			'Add_Block',
			'Duplicate_Block',
			'Get_Post_Blocks',
			'Insert_Pattern',
			'Move_Block',
			'Outline_Post_Blocks',
			'Remove_Block',
			'Update_Post_Block',
		);

		foreach ( $moved as $class ) {
			$path = $this->abilities_dir() . "/Block/{$class}.php";
			$this->assertFileExists( $path, "{$class} should have moved to Block/." );
			$this->assertFileDoesNotExist(
				$this->abilities_dir() . "/Content/{$class}.php",
				"{$class} should no longer be in Content/."
			);

			$src = (string) file_get_contents( $path );
			$this->assertStringContainsString( "'acrossai-block'", $src );
			$this->assertStringContainsString( "'post-blocks'", $src );
			$this->assertMatchesRegularExpression(
				"/'name'\s*=>\s*'blocks\//",
				$src,
				"{$class} must keep its blocks/ slug — renaming it breaks MCP clients."
			);
		}
	}
}
