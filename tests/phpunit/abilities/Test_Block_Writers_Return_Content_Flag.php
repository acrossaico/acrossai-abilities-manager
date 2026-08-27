<?php
/**
 * Structural coverage for the return_content:false default on the two
 * block-tree writers — blocks/update-post-block and blocks/add-block.
 *
 * Mirrors the shape of Test_Return_Content_Default_False.php (six content
 * writers) and Test_Block_Writers_Return_Content_Default_False.php (nine
 * block-editor writers). Same rules: source-inspection sweep, no WP install
 * needed.
 *
 *   1. Every ability declares `return_content:{type:boolean, default:false}`
 *      in its input_schema.
 *   2. Every ability declares `content_bytes:integer` in its output_schema.
 *   3. Every ability's execute() unsets innerHTML/innerContent/innerBlocks
 *      when return_content is falsy.
 *   4. Every ability's execute() computes content_bytes BEFORE that strip.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.32
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Block_Writers_Return_Content_Flag extends WP_UnitTestCase {

	private string $plugin_root = '';

	protected function setUp(): void {
		parent::setUp();
		$this->plugin_root = dirname( __DIR__, 3 );
	}

	private function read( string $rel ): string {
		return (string) file_get_contents( $this->plugin_root . '/' . $rel );
	}

	/**
	 * @return array<string, array{0:string,1:string}>
	 */
	public static function block_writer_provider(): array {
		return array(
			'add-block'         => array( 'includes/Abilities/Content/Add_Block.php', 'blocks/add-block' ),
			'update-post-block' => array( 'includes/Abilities/Content/Update_Post_Block.php', 'blocks/update-post-block' ),
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
	public function test_execute_reads_return_content_and_gates_on_it( string $relative_path ): void {
		$src = $this->read( $relative_path );
		$this->assertMatchesRegularExpression(
			"/\\\$return_content\\s*=\\s*!\\s*empty\\(\\s*\\\$input\\[\\s*'return_content'\\s*\\]\\s*\\)/",
			$src,
			"{$relative_path} must read \$input['return_content'] and gate on it"
		);
	}

	/**
	 * @dataProvider block_writer_provider
	 */
	public function test_execute_strips_inner_html_content_and_blocks_when_gated( string $relative_path ): void {
		$src = $this->read( $relative_path );

		// The strip must unset all three fields — innerHTML, innerContent, innerBlocks —
		// inside the return_content gate. Missing any one of them lets bytes leak.
		$this->assertStringContainsString( "'innerHTML'", $src );
		$this->assertStringContainsString( "'innerContent'", $src );
		$this->assertStringContainsString( "'innerBlocks'", $src );

		// Presence check for the actual strip block. Match "unset(" with all three fields.
		$this->assertMatchesRegularExpression(
			"/unset\\([^)]*\\['innerHTML'\\][^)]*\\['innerContent'\\][^)]*\\['innerBlocks'\\][^)]*\\)/s",
			$src,
			"{$relative_path} must unset all three fields (innerHTML, innerContent, innerBlocks) in one unset() call"
		);
	}

	/**
	 * Guardrail: content_bytes MUST be computed from innerHTML BEFORE the
	 * unset — otherwise it always reports 0.
	 *
	 * @dataProvider block_writer_provider
	 */
	public function test_content_bytes_computed_before_strip( string $relative_path ): void {
		$src = $this->read( $relative_path );

		// Locate a `$content_bytes = ... strlen(... innerHTML ...)` assignment.
		$strlen_pos = false;
		if ( preg_match( "/\\\$content_bytes\\s*=[^;]*strlen[^;]*innerHTML/", $src, $matches, PREG_OFFSET_CAPTURE ) ) {
			$strlen_pos = $matches[0][1];
		}
		$this->assertNotFalse( $strlen_pos, "{$relative_path}: content_bytes assignment reading innerHTML not found" );

		// Locate the unset() call.
		$unset_pos = false;
		if ( preg_match( "/unset\\([^)]*'innerHTML'/", $src, $matches, PREG_OFFSET_CAPTURE ) ) {
			$unset_pos = $matches[0][1];
		}
		$this->assertNotFalse( $unset_pos, "{$relative_path}: unset(innerHTML) not found" );

		$this->assertLessThan(
			$unset_pos,
			$strlen_pos,
			"{$relative_path}: content_bytes must be computed BEFORE the unset — otherwise it reports 0"
		);
	}

	/**
	 * Every success response includes both content_bytes and the block object.
	 *
	 * @dataProvider block_writer_provider
	 */
	public function test_response_shape_includes_content_bytes( string $relative_path ): void {
		$src = $this->read( $relative_path );
		$this->assertMatchesRegularExpression(
			"/'content_bytes'\\s*=>\\s*\\\$content_bytes/",
			$src,
			"{$relative_path}: success response must include content_bytes wired to \$content_bytes"
		);
	}
}
