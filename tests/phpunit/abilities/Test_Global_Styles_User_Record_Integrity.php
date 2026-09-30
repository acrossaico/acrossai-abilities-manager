<?php
/**
 * Global Styles DB record integrity.
 *
 * Covers the two defects that made `blocks/*-global-style` report success while WordPress ignored
 * or destroyed what was saved:
 *
 *  1. Records were written without `isGlobalStylesUserThemeJSON` / `version`, so
 *     WP_Theme_JSON_Resolver::get_user_data() discarded them and the site kept rendering theme
 *     defaults. Adding the flags by hand was refused as an unknown top-level key.
 *  2. The encoded JSON was handed to wp_insert_post()/wp_update_post() unslashed. Core unslashes
 *     every field, so any value containing an escaped double quote — a font stack, typically —
 *     produced stored content that was no longer JSON. It decoded to null, which the read path
 *     reported as an empty record, so the next merge-write started from empty and dropped every
 *     section saved before it.
 *
 * These run against the in-memory post store in tests/bootstrap.php, whose wp_insert_post() /
 * wp_update_post() stubs deliberately reproduce core's wp_unslash() pass over every field. Without
 * that the slashing test would pass against the unfixed code. The parts that need a real
 * WP_Theme_JSON_Resolver — that WordPress applies the palette, that wp_get_global_stylesheet()
 * carries the CSS variable — live in Test_Global_Styles_Applied_By_WordPress, which needs a full
 * WordPress install and is excluded from the default suite alongside the other integration tests.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.41
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Block\Create_Global_Style;
use AcrossAI_Abilities_Manager\Includes\Abilities\Block\Read_Global_Style;
use AcrossAI_Abilities_Manager\Includes\Abilities\Block\Update_Global_Style;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Global_Styles\Global_Styles_Db;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Global_Styles\Global_Styles_Flag_Migration;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Global_Styles\Global_Styles_Writer;
use WP_Post;
use WP_Theme_JSON;
use WP_UnitTestCase;

/**
 * Class Test_Global_Styles_User_Record_Integrity.
 */
class Test_Global_Styles_User_Record_Integrity extends WP_UnitTestCase {

	private const THEME = 'twentytwentyfive';

	/**
	 * A font stack that only round-trips if the JSON is slashed on the way in.
	 *
	 * @var string
	 */
	private const QUOTED_STACK = '"EB Garamond", Georgia, serif';

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['__acrossai_test_posts']                = array();
		$GLOBALS['__acrossai_test_terms']                = array();
		$GLOBALS['__acrossai_test_options']              = array();
		$GLOBALS['__acrossai_test_next_post_id']         = 100;
		$GLOBALS['__acrossai_test_get_posts_from_store'] = true;
		$GLOBALS['__acrossai_test_stylesheet']           = self::THEME;
		$GLOBALS['acrossai_test_filter_callbacks']       = array();
	}

	protected function tearDown(): void {
		$GLOBALS['__acrossai_test_get_posts_from_store'] = false;
		$GLOBALS['__acrossai_test_stylesheet']           = '';
		$GLOBALS['acrossai_test_filter_callbacks']       = array();

		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Seed a record straight into the store, bypassing the production writer.
	 *
	 * Used to stage the "written by an earlier version" state the fixes have to cope with.
	 *
	 * @param  array<string, mixed> $data Decoded record content.
	 * @param  string               $name post_name; the default is core's own naming.
	 * @return WP_Post
	 */
	private function seed_record( array $data, string $name = 'wp-global-styles-' . self::THEME ): WP_Post {
		global $__acrossai_test_posts;

		$id                             = 7;
		$__acrossai_test_posts[ $id ]   = array(
			'ID'                => $id,
			'post_type'         => Global_Styles_Db::POST_TYPE,
			'post_status'       => 'publish',
			'post_name'         => $name,
			'post_title'        => 'Custom Styles',
			'post_content'      => (string) wp_json_encode( $data ),
			'post_modified_gmt' => '2026-01-01 00:00:00',
		);
		$GLOBALS['__acrossai_test_terms'][ $id ]['wp_theme'] = array( self::THEME );

		return get_post( $id );
	}

	/**
	 * Decode what is actually stored for a post.
	 *
	 * @param  int $post_id Post ID.
	 * @return array<string, mixed>|null
	 */
	private function stored( int $post_id ): ?array {
		$post = get_post( $post_id );
		$this->assertNotNull( $post, 'The record should still exist.' );

		$decoded = json_decode( (string) $post->post_content, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Assert a stored record carries both keys WordPress requires.
	 *
	 * @param  int    $post_id Post ID.
	 * @param  string $context What was being written, for the failure message.
	 * @return array<string, mixed>
	 */
	private function assertFlagged( int $post_id, string $context ): array {
		$decoded = $this->stored( $post_id );

		$this->assertIsArray( $decoded, "post_content must decode after {$context}" );
		$this->assertTrue(
			$decoded[ Global_Styles_Writer::USER_FLAG ] ?? null,
			"isGlobalStylesUserThemeJSON must be true after {$context}, or WordPress ignores the record"
		);
		$this->assertSame(
			WP_Theme_JSON::LATEST_SCHEMA,
			$decoded['version'] ?? null,
			"version must equal WP_Theme_JSON::LATEST_SCHEMA after {$context}"
		);

		return $decoded;
	}

	/**
	 * Run blocks/update-global-style against the DB record.
	 *
	 * @param  array<string, mixed> $input Ability input.
	 * @return array<string, mixed>
	 */
	private function update( array $input ): array {
		return ( new Update_Global_Style() )->execute(
			array_merge( array( 'source' => 'db' ), $input )
		);
	}

	// -----------------------------------------------------------------------
	// 1. Flags are present after every kind of DB write.
	// -----------------------------------------------------------------------

	public function test_create_stamps_the_flags_without_being_asked(): void {
		$id = Global_Styles_Db::create(
			self::THEME,
			array( 'settings' => array( 'color' => array( 'palette' => array( array( 'slug' => 'accent-1', 'color' => '#3858e9', 'name' => 'Accent 1' ) ) ) ) )
		);

		$this->assertIsInt( $id, 'create() should return a post ID' );
		$decoded = $this->assertFlagged( $id, 'create' );
		$this->assertArrayHasKey( 'settings', $decoded, 'the caller payload must survive alongside the flags' );
	}

	public function test_full_update_stamps_the_flags(): void {
		$post = $this->seed_record( array( 'settings' => array( 'color' => array( 'palette' => array() ) ) ) );

		$result = Global_Styles_Db::update( $post, array( 'styles' => array( 'color' => array( 'background' => '#fff' ) ) ), true );

		$this->assertIsInt( $result );
		$this->assertFlagged( (int) $result, 'a full update' );
	}

	public function test_replace_update_stamps_the_flags(): void {
		$post = $this->seed_record( array( 'settings' => array( 'color' => array( 'palette' => array() ) ) ) );

		$result = Global_Styles_Db::update( $post, array( 'styles' => array( 'color' => array( 'background' => '#fff' ) ) ), false );

		$this->assertIsInt( $result );
		$decoded = $this->assertFlagged( (int) $result, 'a replace' );
		$this->assertArrayNotHasKey( 'settings', $decoded, 'merge=false must replace, not merge' );
	}

	public function test_section_write_stamps_the_flags(): void {
		$post = $this->seed_record( array( 'settings' => array( 'color' => array( 'palette' => array() ) ) ) );

		$result = Global_Styles_Db::update_section(
			$post,
			'layout',
			array( 'settings' => array( 'layout' => array( 'contentSize' => '720px' ) ) )
		);

		$this->assertIsInt( $result );
		$decoded = $this->assertFlagged( (int) $result, 'a section write' );
		$this->assertSame( '720px', $decoded['settings']['layout']['contentSize'] );
	}

	public function test_section_delete_stamps_the_flags(): void {
		$post = $this->seed_record(
			array(
				'settings' => array(
					'color'  => array( 'palette' => array( array( 'slug' => 'accent-1', 'color' => '#3858e9' ) ) ),
					'layout' => array( 'contentSize' => '720px' ),
				),
			)
		);

		$result = Global_Styles_Db::delete_section( $post, 'layout' );

		$this->assertIsInt( $result );
		$decoded = $this->assertFlagged( (int) $result, 'a section delete' );
		$this->assertArrayNotHasKey( 'layout', $decoded['settings'], 'the deleted section must be gone' );
		$this->assertArrayHasKey( 'color', $decoded['settings'], 'the other sections must remain' );
	}

	public function test_deleting_the_last_section_leaves_the_seed_record_core_would_write(): void {
		$post = $this->seed_record( array( 'settings' => array( 'layout' => array( 'contentSize' => '720px' ) ) ) );

		$result = Global_Styles_Db::delete_section( $post, 'layout' );

		$this->assertIsInt( $result, 'removing the only section must not trip the empty-content guard' );
		$decoded = $this->assertFlagged( (int) $result, 'deleting the last section' );
		$this->assertSame(
			array( 'version', Global_Styles_Writer::USER_FLAG, 'settings' ),
			array_keys( $decoded ),
			'what is left should be core\'s own seed shape'
		);
	}

	public function test_version_tracks_core_rather_than_a_hardcoded_number(): void {
		$this->assertSame(
			WP_Theme_JSON::LATEST_SCHEMA,
			Global_Styles_Writer::latest_schema(),
			'the writer must read the schema version from core, not carry its own copy'
		);
	}

	// -----------------------------------------------------------------------
	// 2. Quotes survive. 3. Earlier sections survive a quoted write.
	// -----------------------------------------------------------------------

	public function test_font_stack_with_escaped_quotes_round_trips_exactly(): void {
		$post = $this->seed_record( array( 'settings' => array( 'color' => array( 'palette' => array() ) ) ) );

		$result = Global_Styles_Db::update_section(
			$post,
			'typography',
			array(
				'settings' => array(
					'typography' => array(
						'fontFamilies' => array(
							array(
								'slug'       => 'eb-garamond',
								'name'       => 'EB Garamond',
								'fontFamily' => self::QUOTED_STACK,
							),
						),
					),
				),
			)
		);

		$this->assertIsInt( $result, 'a quoted font stack must not fail the write' );

		$decoded = $this->stored( (int) $result );
		$this->assertIsArray( $decoded, 'stored content must still be valid JSON — this is the wipe' );
		$this->assertSame(
			self::QUOTED_STACK,
			$decoded['settings']['typography']['fontFamilies'][0]['fontFamily'],
			'the font stack must come back byte-identical'
		);
	}

	public function test_quoted_typography_write_leaves_colors_intact(): void {
		$palette = array( array( 'slug' => 'accent-1', 'color' => '#3858e9', 'name' => 'Accent 1' ) );

		$id = Global_Styles_Db::create( self::THEME, array( 'settings' => array( 'color' => array( 'palette' => $palette ) ) ) );
		$this->assertIsInt( $id );

		$result = Global_Styles_Db::update(
			get_post( $id ),
			array(
				'settings' => array(
					'typography' => array(
						'fontFamilies' => array( array( 'slug' => 'eb-garamond', 'fontFamily' => self::QUOTED_STACK ) ),
					),
				),
			),
			true
		);

		$this->assertIsInt( $result );
		$decoded = $this->stored( (int) $result );

		$this->assertSame( $palette, $decoded['settings']['color']['palette'], 'the colors saved earlier must still be there' );
		$this->assertSame( self::QUOTED_STACK, $decoded['settings']['typography']['fontFamilies'][0]['fontFamily'] );
	}

	public function test_customized_sections_reports_both_after_a_quoted_write(): void {
		$id = Global_Styles_Db::create( self::THEME, array( 'settings' => array( 'color' => array( 'palette' => array( array( 'slug' => 'accent-1', 'color' => '#3858e9' ) ) ) ) ) );

		Global_Styles_Db::update(
			get_post( $id ),
			array( 'settings' => array( 'typography' => array( 'fontFamilies' => array( array( 'slug' => 'eb', 'fontFamily' => self::QUOTED_STACK ) ) ) ) ),
			true
		);

		$sections = Global_Styles_Db::get_customized_sections( get_post( $id ) );

		$this->assertContains( 'colors', $sections, 'colors must not vanish from the summary' );
		$this->assertContains( 'typography', $sections );
	}

	// -----------------------------------------------------------------------
	// 4. A merge into an undecodable record is refused, never treated as empty.
	// -----------------------------------------------------------------------

	public function test_merging_into_a_corrupt_record_is_refused(): void {
		global $__acrossai_test_posts;

		$this->seed_record( array( 'settings' => array( 'color' => array( 'palette' => array() ) ) ) );
		$__acrossai_test_posts[7]['post_content'] = '{"settings": {"color": ' . "\n" . 'not json';

		$result = Global_Styles_Db::update( get_post( 7 ), array( 'styles' => array( 'color' => array( 'background' => '#fff' ) ) ), true );

		$this->assertInstanceOf( 'WP_Error', $result, 'a merge into unparseable content must error, not start from empty' );
		$this->assertSame( 'corrupt_record', $result->get_error_code() );
	}

	public function test_replacing_a_corrupt_record_is_allowed_as_the_repair_path(): void {
		global $__acrossai_test_posts;

		$this->seed_record( array( 'settings' => array() ) );
		$__acrossai_test_posts[7]['post_content'] = 'not json at all';

		$result = Global_Styles_Db::update( get_post( 7 ), array( 'settings' => array( 'color' => array( 'palette' => array() ) ) ), false );

		$this->assertIsInt( $result, 'merge=false is how a broken record gets replaced' );
		$this->assertFlagged( (int) $result, 'a replace over corrupt content' );
	}

	// -----------------------------------------------------------------------
	// 5. A write that does not survive is never reported as success.
	// -----------------------------------------------------------------------

	public function test_a_write_that_gets_mangled_fails_and_restores_the_previous_content(): void {
		$original = array(
			'version'                        => WP_Theme_JSON::LATEST_SCHEMA,
			Global_Styles_Writer::USER_FLAG  => true,
			'settings'                       => array( 'color' => array( 'palette' => array( array( 'slug' => 'accent-1', 'color' => '#3858e9' ) ) ) ),
		);
		$this->seed_record( $original );
		$before = (string) get_post( 7 )->post_content;

		// Simulate a storage layer that breaks exactly the write being attempted — the shape of the
		// original bug, where a quoted value was the thing that came back mangled. Scoped to content
		// carrying the marker so the rollback write itself can succeed, which is what makes
		// "previous content intact" a meaningful assertion rather than an accident.
		$GLOBALS['acrossai_test_filter_callbacks']['content_save_pre'] = static function ( $content ) {
			return false !== strpos( (string) $content, 'eb-garamond' )
				? str_replace( '\\"', '"', (string) $content )
				: $content;
		};

		$result = Global_Styles_Db::update(
			get_post( 7 ),
			array( 'settings' => array( 'typography' => array( 'fontFamilies' => array( array( 'slug' => 'eb-garamond', 'fontFamily' => self::QUOTED_STACK ) ) ) ) ),
			true
		);

		$this->assertInstanceOf( 'WP_Error', $result, 'a record that does not decode must never be reported as saved' );
		$this->assertSame( 'write_verification_failed', $result->get_error_code() );

		$data = $result->get_error_data();
		$this->assertTrue( $data['restored'] ?? false, 'the previous content should have been put back' );
		$this->assertSame( $before, (string) get_post( 7 )->post_content, 'the previous record must be byte-identical' );
	}

	public function test_the_ability_surfaces_a_failed_write_as_success_false(): void {
		$this->seed_record(
			array(
				'version'                       => WP_Theme_JSON::LATEST_SCHEMA,
				Global_Styles_Writer::USER_FLAG => true,
				'settings'                      => array( 'color' => array( 'palette' => array() ) ),
			)
		);

		$GLOBALS['acrossai_test_filter_callbacks']['content_save_pre'] = static function ( $content ) {
			return false !== strpos( (string) $content, 'eb-garamond' ) ? 'mangled, not json' : $content;
		};

		$response = $this->update(
			array(
				'section' => 'typography',
				'data'    => array( 'settings' => array( 'typography' => array( 'fontFamilies' => array( array( 'slug' => 'eb-garamond' ) ) ) ) ),
			)
		);

		$this->assertFalse( $response['success'], 'the ability must not report success for a record that does not decode' );
		$this->assertStringContainsString( 'Nothing was kept', $response['message'] );
	}

	// -----------------------------------------------------------------------
	// 6. The DB source accepts the flags rather than rejecting them.
	// -----------------------------------------------------------------------

	public function test_db_source_accepts_the_user_flags_in_content(): void {
		$valid = Global_Styles_Db::validate_data(
			array(
				'version'                       => 3,
				Global_Styles_Writer::USER_FLAG => true,
				'settings'                      => array(),
				'styles'                        => array(),
				'title'                         => 'Custom Styles',
			),
			Global_Styles_Db::ORIGIN_USER
		);

		$this->assertTrue( $valid, 'isGlobalStylesUserThemeJSON is the key this source exists to store' );
	}

	public function test_db_source_rejects_file_only_keys_by_name(): void {
		$result = Global_Styles_Db::validate_data(
			array(
				'settings'      => array(),
				'templateParts' => array( array( 'name' => 'header' ) ),
			),
			Global_Styles_Db::ORIGIN_USER
		);

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertStringContainsString( 'templateParts', $result->get_error_message() );
		$this->assertStringContainsString( 'theme.json file', $result->get_error_message(), 'the error should say where the key does belong' );
	}

	public function test_file_source_still_accepts_template_parts(): void {
		$this->assertTrue(
			Global_Styles_Db::validate_data(
				array(
					'version'       => 3,
					'templateParts' => array( array( 'name' => 'header' ) ),
				)
			),
			'theme.json files legitimately carry templateParts; the default must stay file semantics'
		);
	}

	// -----------------------------------------------------------------------
	// 7. Repair.
	// -----------------------------------------------------------------------

	public function test_repair_adds_the_flags_and_keeps_every_value(): void {
		$styles = array(
			'settings' => array( 'color' => array( 'palette' => array( array( 'slug' => 'accent-1', 'color' => '#3858e9' ) ) ) ),
			'styles'   => array( 'typography' => array( 'fontSize' => '18px' ) ),
		);
		$post   = $this->seed_record( $styles );

		$result = Global_Styles_Db::repair( $post );

		$this->assertIsArray( $result );
		$this->assertTrue( $result['changed'] );

		$decoded = $this->assertFlagged( (int) $result['post_id'], 'a repair' );
		$this->assertSame( $styles['settings'], $decoded['settings'], 'repair must not alter the styles' );
		$this->assertSame( $styles['styles'], $decoded['styles'] );
	}

	public function test_repair_is_a_no_op_on_an_already_valid_record(): void {
		$post = $this->seed_record(
			array(
				'version'                       => WP_Theme_JSON::LATEST_SCHEMA,
				Global_Styles_Writer::USER_FLAG => true,
				'settings'                      => array(),
			)
		);

		$result = Global_Styles_Db::repair( $post );

		$this->assertIsArray( $result );
		$this->assertFalse( $result['changed'], 'nothing to repair should say so rather than rewriting' );
	}

	public function test_an_ordinary_write_repairs_a_flagless_record(): void {
		$this->seed_record( array( 'settings' => array( 'color' => array( 'palette' => array( array( 'slug' => 'accent-1', 'color' => '#3858e9' ) ) ) ) ) );

		$result = Global_Styles_Db::update(
			get_post( 7 ),
			array( 'settings' => array( 'layout' => array( 'contentSize' => '720px' ) ) ),
			true
		);

		$this->assertIsInt( $result );
		$decoded = $this->assertFlagged( (int) $result, 'an ordinary write over a flagless record' );
		$this->assertArrayHasKey( 'color', $decoded['settings'], 'repairing must not cost the existing styles' );
	}

	// -----------------------------------------------------------------------
	// 8. Read and list are honest about a record WordPress is ignoring.
	// -----------------------------------------------------------------------

	public function test_a_flagless_record_is_not_reported_as_applied(): void {
		$post = $this->seed_record( array( 'settings' => array( 'color' => array( 'palette' => array() ) ) ) );

		$this->assertFalse( Global_Styles_Db::is_applied_by_wordpress( $post ) );

		$warnings = Global_Styles_Db::record_warnings( $post );
		$this->assertNotEmpty( $warnings );
		$this->assertStringContainsString( 'isGlobalStylesUserThemeJSON', $warnings[0] );
		$this->assertStringContainsString( 'WordPress is ignoring it', $warnings[0] );
	}

	public function test_to_row_carries_the_applied_flag_and_the_warning(): void {
		$row = Global_Styles_Db::to_row( $this->seed_record( array( 'settings' => array() ) ) );

		$this->assertFalse( $row['applied_by_wordpress'] );
		$this->assertNotEmpty( $row['warnings'] ?? array() );
	}

	public function test_a_corrupt_record_is_not_reported_as_applied(): void {
		global $__acrossai_test_posts;

		$this->seed_record( array( 'settings' => array() ) );
		$__acrossai_test_posts[7]['post_content'] = 'not json';

		$post = get_post( 7 );
		$this->assertFalse( Global_Styles_Db::is_applied_by_wordpress( $post ) );
		$this->assertStringContainsString( 'not contain valid JSON', Global_Styles_Db::record_warnings( $post )[0] );
	}

	public function test_read_reports_origin_db_ignored_for_a_flagless_record(): void {
		$this->seed_record( array( 'settings' => array( 'color' => array( 'palette' => array( array( 'slug' => 'accent-1', 'color' => '#3858e9' ) ) ) ) ) );

		$response = ( new Read_Global_Style() )->execute( array( 'source' => 'db' ) );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'db:ignored', $response['origin'], 'origin "db" is a claim about what the site is serving' );
		$this->assertNotEmpty( $response['warnings'] );
		$this->assertStringContainsString( 'isGlobalStylesUserThemeJSON', implode( ' ', $response['warnings'] ) );
	}

	public function test_read_reports_origin_db_once_the_record_is_valid(): void {
		$this->seed_record(
			array(
				'version'                       => WP_Theme_JSON::LATEST_SCHEMA,
				Global_Styles_Writer::USER_FLAG => true,
				'settings'                      => array( 'color' => array( 'palette' => array( array( 'slug' => 'accent-1', 'color' => '#3858e9' ) ) ) ),
			)
		);

		$response = ( new Read_Global_Style() )->execute( array( 'source' => 'db' ) );

		$this->assertSame( 'db', $response['origin'] );
		$this->assertSame( array(), $response['warnings'] );
	}

	// -----------------------------------------------------------------------
	// Section writes: no more silent dropping.
	// -----------------------------------------------------------------------

	public function test_typography_section_reports_the_element_styles_it_did_not_save(): void {
		$this->seed_record(
			array(
				'version'                       => WP_Theme_JSON::LATEST_SCHEMA,
				Global_Styles_Writer::USER_FLAG => true,
				'settings'                      => array(),
			)
		);

		$response = $this->update(
			array(
				'section' => 'typography',
				'data'    => array(
					'settings' => array( 'typography' => array( 'fontSizes' => array( array( 'slug' => 'large', 'size' => '2rem' ) ) ) ),
					'styles'   => array( 'elements' => array( 'h1' => array( 'typography' => array( 'fontSize' => '3rem' ) ) ) ),
				),
			)
		);

		$this->assertTrue( $response['success'], 'the typography half is still saved' );
		$this->assertContains(
			'ignored: styles.elements — not part of section "typography", so it was not saved.',
			$response['warnings'],
			'dropped input must be named, not discarded in silence'
		);
	}

	public function test_a_section_write_that_saves_nothing_is_refused(): void {
		$this->seed_record(
			array(
				'version'                       => WP_Theme_JSON::LATEST_SCHEMA,
				Global_Styles_Writer::USER_FLAG => true,
				'settings'                      => array(),
			)
		);
		$before = (string) get_post( 7 )->post_content;

		$response = $this->update(
			array(
				'section' => 'blockStyles',
				'data'    => array( 'styles' => array( 'elements' => array( 'button' => array( 'color' => array( 'background' => '#000' ) ) ) ) ),
			)
		);

		$this->assertFalse( $response['success'], 'a write that stored nothing used to report success' );
		$this->assertStringContainsString( 'styles.elements', $response['message'] );
		$this->assertSame( $before, (string) get_post( 7 )->post_content, 'and it must not have touched the record' );
	}

	public function test_elements_is_a_section_of_its_own(): void {
		$this->seed_record(
			array(
				'version'                       => WP_Theme_JSON::LATEST_SCHEMA,
				Global_Styles_Writer::USER_FLAG => true,
				'settings'                      => array(),
			)
		);

		$response = $this->update(
			array(
				'section' => 'elements',
				'data'    => array( 'styles' => array( 'elements' => array( 'button' => array( 'color' => array( 'background' => '#3858e9' ) ) ) ) ),
			)
		);

		$this->assertTrue( $response['success'] );
		$this->assertSame( array(), $response['warnings'], 'nothing was dropped, so nothing should be reported' );

		$decoded = $this->stored( 7 );
		$this->assertSame( '#3858e9', $decoded['styles']['elements']['button']['color']['background'] );
	}

	public function test_dropped_paths_names_every_unsaved_branch(): void {
		$sent = array(
			'settings' => array( 'typography' => array( 'fontSizes' => array() ) ),
			'styles'   => array(
				'elements'   => array( 'h1' => array() ),
				'typography' => array( 'fontSize' => '18px' ),
			),
		);
		$kept = array(
			'settings' => array( 'typography' => array( 'fontSizes' => array() ) ),
			'styles'   => array( 'typography' => array( 'fontSize' => '18px' ) ),
		);

		$this->assertSame( array( 'styles.elements' ), Update_Global_Style::dropped_paths( $sent, $kept ) );
	}

	// -----------------------------------------------------------------------
	// repair=true on the ability.
	// -----------------------------------------------------------------------

	public function test_repair_option_flags_the_record_without_other_changes(): void {
		$this->seed_record( array( 'settings' => array( 'color' => array( 'palette' => array( array( 'slug' => 'accent-1', 'color' => '#3858e9' ) ) ) ) ) );

		$response = $this->update( array( 'repair' => true ) );

		$this->assertTrue( $response['success'] );
		$this->assertTrue( $response['record']['applied_by_wordpress'] );

		$decoded = $this->assertFlagged( 7, 'repair=true' );
		$this->assertSame( '#3858e9', $decoded['settings']['color']['palette'][0]['color'] );
	}

	public function test_repair_refuses_to_be_combined_with_an_edit(): void {
		$this->seed_record( array( 'settings' => array() ) );

		$response = $this->update(
			array(
				'repair'  => true,
				'content' => array( 'settings' => array( 'layout' => array( 'contentSize' => '720px' ) ) ),
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertStringContainsString( 'cannot be combined', $response['message'] );
	}

	// -----------------------------------------------------------------------
	// Create through the ability.
	// -----------------------------------------------------------------------

	public function test_create_ability_writes_a_record_wordpress_will_use(): void {
		$response = ( new Create_Global_Style() )->execute(
			array(
				'source'  => 'db',
				'theme'   => self::THEME,
				'content' => array( 'settings' => array( 'color' => array( 'palette' => array( array( 'slug' => 'accent-1', 'color' => '#3858e9' ) ) ) ) ),
			)
		);

		$this->assertTrue( $response['success'], $response['message'] ?? '' );
		$this->assertTrue( $response['record']['applied_by_wordpress'] );
		$this->assertFlagged( (int) $response['record']['post_id'], 'create through the ability' );
	}

	// -----------------------------------------------------------------------
	// Migration selection rule.
	// -----------------------------------------------------------------------

	public function test_migration_selects_a_flagless_record(): void {
		$this->assertTrue(
			Global_Styles_Flag_Migration::needs_repair( (string) wp_json_encode( array( 'settings' => array( 'color' => array() ) ) ) )
		);
	}

	public function test_migration_leaves_a_valid_record_alone(): void {
		$content = (string) wp_json_encode(
			array(
				'version'                       => WP_Theme_JSON::LATEST_SCHEMA,
				Global_Styles_Writer::USER_FLAG => true,
				'settings'                      => array(),
			)
		);

		$this->assertFalse( Global_Styles_Flag_Migration::needs_repair( $content ) );
	}

	public function test_migration_refuses_to_rewrite_content_it_cannot_parse(): void {
		$this->assertFalse(
			Global_Styles_Flag_Migration::needs_repair( '{"settings": broken' ),
			'rewriting unparseable bytes would destroy a record a human could still recover'
		);
	}

	public function test_migration_leaves_empty_content_alone(): void {
		$this->assertFalse( Global_Styles_Flag_Migration::needs_repair( '' ) );
	}

	public function test_migration_repairs_the_record_and_skips_block_style_variations(): void {
		global $__acrossai_test_posts;

		$this->seed_record( array( 'settings' => array( 'color' => array( 'palette' => array( array( 'slug' => 'accent-1', 'color' => '#3858e9' ) ) ) ) ) );

		// A block style variation lives in the same post type but must never be flagged.
		$__acrossai_test_posts[8] = array(
			'ID'           => 8,
			'post_type'    => Global_Styles_Db::POST_TYPE,
			'post_status'  => 'publish',
			'post_name'    => 'evening',
			'post_content' => (string) wp_json_encode( array( 'version' => 3, 'title' => 'Evening', 'styles' => array() ) ),
		);

		$counts = Global_Styles_Flag_Migration::instance()->repair_all();

		$this->assertSame( 1, $counts['examined'], 'only the per-theme user record is in scope' );
		$this->assertSame( 1, $counts['repaired'] );
		$this->assertFlagged( 7, 'the migration' );

		$variation = json_decode( (string) get_post( 8 )->post_content, true );
		$this->assertArrayNotHasKey(
			Global_Styles_Writer::USER_FLAG,
			$variation,
			'a block style variation is read by a different core path and must not be flagged'
		);
	}

	public function test_migration_runs_once_per_site(): void {
		$this->seed_record( array( 'settings' => array( 'color' => array() ) ) );

		Global_Styles_Flag_Migration::instance()->maybe_migrate();
		$first = (string) get_post( 7 )->post_content;

		// Second call must be a no-op: the claim option is already held.
		Global_Styles_Flag_Migration::instance()->maybe_migrate();

		$this->assertSame( '1', get_option( Global_Styles_Flag_Migration::DONE_OPTION ) );
		$this->assertSame( $first, (string) get_post( 7 )->post_content );
	}
}
