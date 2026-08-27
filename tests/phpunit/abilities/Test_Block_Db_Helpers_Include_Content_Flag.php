<?php
/**
 * Behavioural coverage for the four block-editor Db `to_row()` helpers.
 * Exercises each helper with a synthetic WP_Post stub to prove:
 *
 *   1. Pattern_Db / Template_Db / Template_Part_Db always return `content`
 *      (helpers have no include_content flag; the ability layer strips).
 *   2. Variation_Db::to_row($post, false) omits `data`.
 *   3. Variation_Db::to_row($post, true) includes `data`.
 *   4. Global_Styles_Db::to_row($post, false) omits `data`.
 *   5. Global_Styles_Db::to_row($post, true) includes `data`.
 *   6. Cheap metadata (id/slug/title/status/theme/modified) is preserved
 *      regardless of the flag — regression guard against over-stripping.
 *   7. Payload-size guarantee: `serialize(to_row(..., false))` on a 100 KB
 *      seeded post is < 4 KB — proves the strip really strips.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Block_Style_Variations\Variation_Db;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Global_Styles\Global_Styles_Db;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Pattern\Pattern_Db;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Template\Template_Db;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Template_Part\Template_Part_Db;
use WP_Post;
use WP_UnitTestCase;

/**
 * Class Test_Block_Db_Helpers_Include_Content_Flag.
 */
class Test_Block_Db_Helpers_Include_Content_Flag extends WP_UnitTestCase {

	private function make_post( int $id, string $content, string $slug = 'test-slug', string $title = 'Test' ): WP_Post {
		$post                    = new WP_Post();
		$post->ID                = $id;
		$post->post_content      = $content;
		$post->post_title        = $title;
		$post->post_name         = $slug;
		$post->post_status       = 'publish';
		$post->post_excerpt      = 'Desc';
		$post->post_modified_gmt = '2026-08-27 12:00:00';
		return $post;
	}

	// -----------------------------------------------------------------------
	// Pattern_Db + Template_Db + Template_Part_Db — helpers do NOT strip.
	// -----------------------------------------------------------------------

	public function test_pattern_db_to_row_always_includes_content_byte_identical(): void {
		$body = '<!-- wp:paragraph -->hello<!-- /wp:paragraph -->';
		$post = $this->make_post( 42, $body, 'my-pattern', 'My Pattern' );

		$row = Pattern_Db::to_row( $post );

		$this->assertSame( $body, $row['content'], 'Pattern_Db::to_row must return the raw post_content under "content"' );
		$this->assertSame( 'my-pattern', $row['slug'], 'cheap field: slug' );
		$this->assertSame( 42, $row['post_id'], 'cheap field: post_id' );
		$this->assertSame( 'My Pattern', $row['title'], 'cheap field: title' );
		$this->assertSame( 'publish', $row['status'], 'cheap field: status' );
		$this->assertSame( '2026-08-27 12:00:00', $row['modified'], 'cheap field: modified' );
	}

	public function test_template_db_to_row_always_includes_content_byte_identical(): void {
		$body = '<!-- wp:template-part {"slug":"header"} /-->';
		$post = $this->make_post( 88, $body, 'single', 'Single Post Template' );

		$row = Template_Db::to_row( $post );

		$this->assertSame( $body, $row['content'], 'Template_Db::to_row must return the raw post_content under "content"' );
		$this->assertSame( 'single', $row['slug'] );
		$this->assertSame( 88, $row['post_id'] );
		$this->assertSame( 'Single Post Template', $row['title'] );
		$this->assertSame( 'publish', $row['status'] );
	}

	public function test_template_part_db_to_row_always_includes_content_byte_identical(): void {
		$body = '<!-- wp:site-title /-->';
		$post = $this->make_post( 101, $body, 'header', 'Header' );

		$row = Template_Part_Db::to_row( $post );

		$this->assertSame( $body, $row['content'], 'Template_Part_Db::to_row must return the raw post_content under "content"' );
		$this->assertSame( 'header', $row['slug'] );
		$this->assertSame( 101, $row['post_id'] );
		$this->assertSame( 'Header', $row['title'] );
	}

	// -----------------------------------------------------------------------
	// Variation_Db — flag flip verified.
	// -----------------------------------------------------------------------

	public function test_variation_db_to_row_omits_data_when_include_content_false(): void {
		$json = wp_json_encode( array( 'version' => 3, 'styles' => array( 'color' => array( 'background' => '#000' ) ) ) );
		$post = $this->make_post( 202, (string) $json, 'dark-variation', 'Dark' );

		$row = Variation_Db::to_row( $post, false );

		$this->assertArrayNotHasKey( 'data', $row, 'include_content=false must omit the "data" field' );
		$this->assertSame( 'dark-variation', $row['slug'], 'cheap field: slug preserved' );
		$this->assertSame( 202, $row['post_id'], 'cheap field: post_id preserved' );
		$this->assertSame( 'Dark', $row['title'], 'cheap field: title preserved' );
	}

	public function test_variation_db_to_row_includes_data_when_include_content_true(): void {
		$decoded = array( 'version' => 3, 'styles' => array( 'color' => array( 'background' => '#000' ) ) );
		$json    = (string) wp_json_encode( $decoded );
		$post    = $this->make_post( 203, $json, 'dark-variation', 'Dark' );

		$row = Variation_Db::to_row( $post, true );

		$this->assertArrayHasKey( 'data', $row, 'include_content=true must include the "data" field' );
		$this->assertSame( $decoded, $row['data'], 'include_content=true must decode the JSON into an array' );
	}

	// -----------------------------------------------------------------------
	// Global_Styles_Db — flag flip verified.
	// -----------------------------------------------------------------------

	public function test_global_styles_db_to_row_omits_data_when_include_content_false(): void {
		$json = wp_json_encode( array( 'version' => 3, 'styles' => array( 'typography' => array( 'fontSize' => '16px' ) ) ) );
		$post = $this->make_post( 301, (string) $json, 'wp-global-styles-twentytwentyfive', 'Twenty Twenty-Five' );

		$row = Global_Styles_Db::to_row( $post, false );

		$this->assertArrayNotHasKey( 'data', $row, 'include_content=false must omit the "data" field' );
		$this->assertArrayHasKey( 'title', $row, 'cheap field: title preserved' );
		$this->assertArrayHasKey( 'post_id', $row, 'cheap field: post_id preserved' );
		$this->assertArrayHasKey( 'modified', $row, 'cheap field: modified preserved' );
		$this->assertSame( 301, $row['post_id'] );
	}

	public function test_global_styles_db_to_row_includes_data_when_include_content_true(): void {
		$decoded = array( 'version' => 3, 'styles' => array( 'typography' => array( 'fontSize' => '16px' ) ) );
		$json    = (string) wp_json_encode( $decoded );
		$post    = $this->make_post( 302, $json, 'wp-global-styles-twentytwentyfive', 'Twenty Twenty-Five' );

		$row = Global_Styles_Db::to_row( $post, true );

		$this->assertArrayHasKey( 'data', $row, 'include_content=true must include the "data" field' );
		$this->assertSame( $decoded, $row['data'], 'include_content=true must decode the JSON into an array' );
	}

	// -----------------------------------------------------------------------
	// Payload-size guarantee — proves the strip really strips.
	// -----------------------------------------------------------------------

	public function test_global_styles_db_stripped_row_is_small_even_when_source_is_huge(): void {
		$blob = str_repeat( 'x', 100 * 1024 ); // 100 KB
		$json = (string) wp_json_encode( array( 'huge' => $blob ) );
		$post = $this->make_post( 401, $json, 'wp-global-styles-huge', 'Huge Record' );

		$row  = Global_Styles_Db::to_row( $post, false );
		$size = strlen( serialize( $row ) );

		$this->assertLessThan(
			4096,
			$size,
			'A stripped row (include_content=false) for a 100 KB post must serialize to <4 KB — proves the payload really is gone'
		);
	}

	public function test_variation_db_stripped_row_is_small_even_when_source_is_huge(): void {
		$blob = str_repeat( 'y', 100 * 1024 );
		$json = (string) wp_json_encode( array( 'huge' => $blob ) );
		$post = $this->make_post( 402, $json, 'huge-variation', 'Huge Variation' );

		$row  = Variation_Db::to_row( $post, false );
		$size = strlen( serialize( $row ) );

		$this->assertLessThan(
			4096,
			$size,
			'A stripped row (include_content=false) for a 100 KB variation post must serialize to <4 KB'
		);
	}
}
