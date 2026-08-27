<?php
/**
 * Coverage for the return_content:false default across the nine
 * block-editor write abilities: Create Block Pattern, Create/Update
 * Block Template, Create/Update Block Template Part, Create/Update
 * Block Style Variation, Create/Update Global Style.
 *
 * (Update_Block_Pattern already returns a location descriptor without
 * echoing content, so it's not in scope.)
 *
 * Mirrors the shape of Test_Return_Content_Default_False.php from
 * PR #152 — WP-less bootstrap can't drive wp_update_post round-trips
 * for the file-write side-effects, so structural pattern checks stand
 * in. Behavioural coverage of the include_content flag lives in
 * Test_Block_Db_Helpers_Include_Content_Flag.php.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Block_Writers_Return_Content_Default_False.
 */
class Test_Block_Writers_Return_Content_Default_False extends WP_UnitTestCase {

	/** @var string */
	private string $plugin_root = '';

	protected function setUp(): void {
		parent::setUp();
		$this->plugin_root = dirname( __DIR__, 3 );
	}

	private function read( string $rel ): string {
		return (string) file_get_contents( $this->plugin_root . '/' . $rel );
	}

	/**
	 * @return array<string, array{0:string,1:string,2:string,3:string}>
	 *   [rel_path, object_key, family, content_field]
	 *   family = "inline_strip" (Pattern/Template/Template_Part) or "flag_flip" (Variation/Global_Style)
	 *   content_field = "content" or "data" (only meaningful for family)
	 */
	public static function block_writer_provider(): array {
		return array(
			'create-block-pattern'         => array( 'includes/Abilities/Block/Create_Block_Pattern.php', 'pattern', 'inline_strip', 'content' ),
			'create-block-template'        => array( 'includes/Abilities/Block/Create_Block_Template.php', 'template', 'inline_strip', 'content' ),
			'update-block-template'        => array( 'includes/Abilities/Block/Update_Block_Template.php', 'template', 'inline_strip', 'content' ),
			'create-block-template-part'   => array( 'includes/Abilities/Block/Create_Block_Template_Part.php', 'part', 'inline_strip', 'content' ),
			'update-block-template-part'   => array( 'includes/Abilities/Block/Update_Block_Template_Part.php', 'part', 'inline_strip', 'content' ),
			'create-block-style-variation' => array( 'includes/Abilities/Block/Create_Block_Style_Variation.php', 'variation', 'flag_flip', 'data' ),
			'update-block-style-variation' => array( 'includes/Abilities/Block/Update_Block_Style_Variation.php', 'variation', 'flag_flip', 'data' ),
			'create-global-style'          => array( 'includes/Abilities/Block/Create_Global_Style.php', 'record', 'flag_flip', 'data' ),
			'update-global-style'          => array( 'includes/Abilities/Block/Update_Global_Style.php', 'record', 'flag_flip', 'data' ),
		);
	}

	/**
	 * @dataProvider block_writer_provider
	 */
	public function test_input_schema_declares_return_content_default_false( string $relative_path ): void {
		$src = $this->read( $relative_path );
		$this->assertMatchesRegularExpression(
			"/'return_content'\\s*=>\\s*array\\(\\s*'type'\\s*=>\\s*'boolean'\\s*,\\s*'default'\\s*=>\\s*false/",
			$src,
			"{$relative_path} input_schema must declare return_content:{type:boolean, default:false}"
		);
	}

	/**
	 * @dataProvider block_writer_provider
	 */
	public function test_output_schema_declares_content_bytes( string $relative_path ): void {
		$src = $this->read( $relative_path );
		$this->assertMatchesRegularExpression(
			"/'content_bytes'\\s*=>\\s*array\\(\\s*'type'\\s*=>\\s*'integer'/",
			$src,
			"{$relative_path} output_schema must declare content_bytes:integer"
		);
	}

	/**
	 * @dataProvider block_writer_provider
	 */
	public function test_execute_reads_return_content_from_input( string $relative_path ): void {
		$src = $this->read( $relative_path );
		$this->assertMatchesRegularExpression(
			"/\\\$return_content\\s*=\\s*!\\s*empty\\(\\s*\\\$input\\[\\s*'return_content'\\s*\\]\\s*\\)/",
			$src,
			"{$relative_path} must read \$input['return_content'] and gate on it"
		);
	}

	/**
	 * Family-specific: Pattern/Template/Template_Part unset the content field
	 * inline; Variation/Global_Style pass $return_content into to_row().
	 *
	 * @dataProvider block_writer_provider
	 */
	public function test_execute_strips_content_when_return_content_falsy( string $relative_path, string $object_key, string $family, string $content_field ): void {
		$src = $this->read( $relative_path );

		if ( 'inline_strip' === $family ) {
			$this->assertStringContainsString(
				"unset( \$row['content'] )",
				$src,
				"{$relative_path} must strip \$row['content'] inside the return_content gate"
			);
			// Guardrail: no hardcoded to_row($post, true) — irrelevant for these helpers, but
			// double-check that content is genuinely gated (not always included).
			$this->assertMatchesRegularExpression(
				"/if\\s*\\(\\s*!\\s*\\\$return_content\\s*\\)\\s*\\{/",
				$src,
				"{$relative_path} must have `if ( ! \$return_content ) {` gate before the unset"
			);
		} else {
			// flag_flip: helper signature is to_row( \WP_Post, bool $include_content )
			$this->assertMatchesRegularExpression(
				"/(Variation_Db|Global_Styles_Db)::to_row\\(\\s*\\\$\\w+\\s*,\\s*\\\$return_content\\s*\\)/",
				$src,
				"{$relative_path} must call to_row( \$post, \$return_content ) — no hardcoded true"
			);
			$this->assertDoesNotMatchRegularExpression(
				"/(Variation_Db|Global_Styles_Db)::to_row\\(\\s*\\\$\\w+\\s*,\\s*true\\s*\\)/",
				$src,
				"{$relative_path} must NOT contain a hardcoded to_row(\$post, true) — that's the pre-fix bug"
			);
		}
	}

	/**
	 * Guardrail: content_bytes must be computed BEFORE any content strip.
	 * For flag_flip family this means the bytes count must not read $row['data']
	 * (which would be null when return_content is false); we require the byte
	 * count to be computed from $post->post_content (the raw JSON on disk).
	 *
	 * @dataProvider block_writer_provider
	 */
	public function test_content_bytes_is_computed_from_post_content( string $relative_path ): void {
		$src = $this->read( $relative_path );

		// content_bytes must be assigned from strlen((string)$post->post_content) — for the
		// flag_flip family this avoids a phantom 0 when the flag is false; for the
		// inline_strip family it avoids depending on the strip-order.
		$this->assertMatchesRegularExpression(
			"/\\\$content_bytes\\s*=\\s*\\\$\\w+\\s*\\?\\s*strlen\\(\\s*\\(\\s*string\\s*\\)\\s*\\\$\\w+->post_content\\s*\\)\\s*:\\s*0/",
			$src,
			"{$relative_path}: \$content_bytes must be computed from \$post->post_content (with null-guard)"
		);
	}

	/**
	 * Every response includes the content_bytes field, wired to $content_bytes.
	 *
	 * @dataProvider block_writer_provider
	 */
	public function test_execute_returns_content_bytes_in_response( string $relative_path, string $object_key ): void {
		$src = $this->read( $relative_path );
		$this->assertMatchesRegularExpression(
			"/'content_bytes'\\s*=>\\s*\\\$content_bytes/",
			$src,
			"{$relative_path}: response array must include content_bytes wired to \$content_bytes"
		);
	}

	/**
	 * Regression guard: for the flag_flip family, no residual `to_row( $post, true )`
	 * calls should remain anywhere in the file — proves every DB call site was
	 * flipped, not just some.
	 *
	 * @dataProvider block_writer_provider
	 */
	public function test_no_hardcoded_include_content_true_in_flag_flip_files( string $relative_path, string $object_key, string $family ): void {
		if ( 'flag_flip' !== $family ) {
			$this->addToAssertionCount( 1 );
			return;
		}
		$src = $this->read( $relative_path );
		$this->assertDoesNotMatchRegularExpression(
			"/::to_row\\(\\s*\\\$\\w+\\s*,\\s*true\\s*\\)/",
			$src,
			"{$relative_path}: NO to_row(\$post, true) call may remain — all must pass \$return_content"
		);
	}
}
