<?php
/**
 * Integration coverage: WordPress actually applies what the Global Styles abilities save.
 *
 * The unit suite (Test_Global_Styles_User_Record_Integrity) proves the record is *written*
 * correctly — flags present, quotes intact, corrupt writes refused. It cannot prove the thing that
 * actually went wrong on the demo site, which is whether WordPress *uses* the record: that needs
 * WP_Theme_JSON_Resolver, a real theme, and the object cache, none of which exist under the unit
 * bootstrap.
 *
 * This file therefore asserts the end of the chain: after a write, `wp_get_global_settings()`
 * returns the custom palette and `wp_get_global_stylesheet()` carries the CSS variable the front end
 * renders from. That is the assertion that would have failed against the old code while every
 * ability still reported `success: true`.
 *
 * Excluded from phpunit.xml.dist, like the other tests here that need a full WordPress install.
 * Run with wp-env:
 *
 *     npx wp-env start
 *     npx wp-env run tests-cli --env-cwd=wp-content/plugins/acrossai-abilities-manager \
 *         ./vendor/bin/phpunit tests/phpunit/abilities/Test_Global_Styles_Applied_By_WordPress.php
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.41
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Block\Update_Global_Style;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Global_Styles\Global_Styles_Db;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Global_Styles\Global_Styles_Writer;
use WP_Theme_JSON;
use WP_UnitTestCase;

/**
 * Class Test_Global_Styles_Applied_By_WordPress.
 */
class Test_Global_Styles_Applied_By_WordPress extends WP_UnitTestCase {

	private const ACCENT = '#3858e9';

	/**
	 * The record under test.
	 *
	 * @var int
	 */
	private int $post_id = 0;

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'wp_get_global_settings' ) || ! class_exists( '\WP_Theme_JSON_Resolver' ) ) {
			$this->markTestSkipped( 'Requires a WordPress install with the Global Styles resolver.' );
		}

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->reset_global_styles();
	}

	public function tearDown(): void {
		if ( $this->post_id > 0 ) {
			wp_delete_post( $this->post_id, true );
		}

		$this->reset_global_styles();

		parent::tearDown();
	}

	/**
	 * Drop every cached layer between the post row and the rendered stylesheet.
	 *
	 * Without this the resolver answers from the copy it read before the write, and the test would
	 * pass or fail on cache state rather than on what was stored.
	 *
	 * @return void
	 */
	private function reset_global_styles(): void {
		\WP_Theme_JSON_Resolver::clean_cached_data();
		wp_clean_theme_json_cache();
	}

	/**
	 * Create the per-theme record the way core does, then hand it to the abilities.
	 *
	 * @param  array<string, mixed> $content Initial content.
	 * @return int
	 */
	private function seed_record( array $content ): int {
		$theme = (string) get_stylesheet();

		$this->post_id = (int) wp_insert_post(
			array(
				'post_type'    => Global_Styles_Db::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Custom Styles',
				'post_name'    => 'wp-global-styles-' . $theme,
				'post_content' => wp_slash( (string) wp_json_encode( $content ) ),
			),
			true
		);

		wp_set_object_terms( $this->post_id, $theme, Global_Styles_Db::THEME_TAX );
		$this->reset_global_styles();

		return $this->post_id;
	}

	/**
	 * Run blocks/update-global-style against the DB record.
	 *
	 * @param  array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	private function update( array $input ): array {
		$response = ( new Update_Global_Style() )->execute( array_merge( array( 'source' => 'db' ), $input ) );
		$this->reset_global_styles();

		return $response;
	}

	// -----------------------------------------------------------------------
	// The acceptance criterion the old code failed silently.
	// -----------------------------------------------------------------------

	public function test_a_written_palette_reaches_wp_get_global_settings_and_the_stylesheet(): void {
		$this->seed_record(
			array(
				'version'                       => WP_Theme_JSON::LATEST_SCHEMA,
				Global_Styles_Writer::USER_FLAG => true,
			)
		);

		$response = $this->update(
			array(
				'section' => 'colors',
				'data'    => array(
					'settings' => array(
						'color' => array(
							'palette' => array(
								array(
									'slug'  => 'accent-1',
									'name'  => 'Accent 1',
									'color' => self::ACCENT,
								),
							),
						),
					),
				),
			)
		);

		$this->assertTrue( $response['success'], $response['message'] ?? '' );

		$palette = wp_get_global_settings( array( 'color', 'palette' ) );
		$slugs   = wp_list_pluck( $palette['custom'] ?? array(), 'color', 'slug' );

		$this->assertSame(
			self::ACCENT,
			$slugs['accent-1'] ?? null,
			'WordPress must read the saved palette back as a custom preset'
		);

		$this->assertStringContainsString(
			'--wp--preset--color--accent-1: ' . self::ACCENT,
			wp_get_global_stylesheet(),
			'and render it as the CSS variable the front end uses'
		);
	}

	public function test_a_record_without_the_flag_is_ignored_by_wordpress_and_repaired_by_a_write(): void {
		// Exactly what pre-0.0.41 writes produced: settings, no flag.
		$this->seed_record(
			array(
				'settings' => array(
					'color' => array(
						'palette' => array(
							array(
								'slug'  => 'accent-1',
								'name'  => 'Accent 1',
								'color' => self::ACCENT,
							),
						),
					),
				),
			)
		);

		$this->assertStringNotContainsString(
			'--wp--preset--color--accent-1: ' . self::ACCENT,
			wp_get_global_stylesheet(),
			'baseline: without the flag WordPress ignores the record entirely — this is the bug'
		);

		$response = $this->update( array( 'repair' => true ) );
		$this->assertTrue( $response['success'], $response['message'] ?? '' );

		$this->assertStringContainsString(
			'--wp--preset--color--accent-1: ' . self::ACCENT,
			wp_get_global_stylesheet(),
			'after the repair the styles that were always stored finally apply'
		);
	}

	public function test_a_quoted_font_stack_survives_the_real_post_api(): void {
		$stack = '"EB Garamond", Georgia, serif';

		$this->seed_record(
			array(
				'version'                       => WP_Theme_JSON::LATEST_SCHEMA,
				Global_Styles_Writer::USER_FLAG => true,
				'settings'                      => array(
					'color' => array(
						'palette' => array(
							array(
								'slug'  => 'accent-1',
								'name'  => 'Accent 1',
								'color' => self::ACCENT,
							),
						),
					),
				),
			)
		);

		$response = $this->update(
			array(
				'section' => 'typography',
				'data'    => array(
					'settings' => array(
						'typography' => array(
							'fontFamilies' => array(
								array(
									'slug'       => 'eb-garamond',
									'name'       => 'EB Garamond',
									'fontFamily' => $stack,
								),
							),
						),
					),
				),
			)
		);

		$this->assertTrue( $response['success'], $response['message'] ?? '' );

		$stored = json_decode( (string) get_post( $this->post_id )->post_content, true );

		$this->assertIsArray( $stored, 'the record must still be JSON after a quoted value' );
		$this->assertSame( $stack, $stored['settings']['typography']['fontFamilies'][0]['fontFamily'] );
		$this->assertSame(
			self::ACCENT,
			$stored['settings']['color']['palette'][0]['color'],
			'and the colors written beforehand must survive it'
		);

		$families = wp_get_global_settings( array( 'typography', 'fontFamilies' ) );
		$stacks   = wp_list_pluck( $families['custom'] ?? array(), 'fontFamily', 'slug' );

		$this->assertSame( $stack, $stacks['eb-garamond'] ?? null, 'WordPress must read the quoted stack back intact' );
	}
}
