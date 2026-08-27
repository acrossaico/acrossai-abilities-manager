<?php
/**
 * Coverage for the return_content:false default across the six content
 * write abilities (Create/Update Page/Post/Cpt_Item).
 *
 * Behavioural round-trip tests would need a full WP install to drive
 * wp_update_post / wp_insert_post / get_post — out of scope for the
 * WP-less bootstrap. What we CAN cover statically:
 *
 *   1. Every ability declares `return_content:{type:boolean, default:false}`
 *      in its input_schema.
 *   2. Every ability declares `content_bytes:{type:integer}` in its output_schema.
 *   3. Every ability's execute() strips the three large fields
 *      (post_content, post_content_filtered, post_excerpt) when
 *      return_content is falsy.
 *   4. Every ability's execute() computes content_bytes BEFORE stripping.
 *
 * Together these prove the wiring is uniform across the six abilities —
 * a "one-word edit" round-trip via any of them will not echo the full
 * body back through the tunnel unless the caller opts in.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Return_Content_Default_False.
 */
class Test_Return_Content_Default_False extends WP_UnitTestCase {

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
	 * @return array<string, array{0:string,1:string}>
	 */
	public static function content_writer_provider(): array {
		return array(
			'update-page'     => array( 'includes/Abilities/Content/Update_Page.php', 'page' ),
			'update-post'     => array( 'includes/Abilities/Content/Update_Post.php', 'post' ),
			'create-page'     => array( 'includes/Abilities/Content/Create_Page.php', 'page' ),
			'create-post'     => array( 'includes/Abilities/Content/Create_Post.php', 'post' ),
			'update-cpt-item' => array( 'includes/Abilities/Content/Update_Cpt_Item.php', 'item' ),
			'create-cpt-item' => array( 'includes/Abilities/Content/Create_Cpt_Item.php', 'item' ),
		);
	}

	/**
	 * @dataProvider content_writer_provider
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
	 * @dataProvider content_writer_provider
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
	 * @dataProvider content_writer_provider
	 */
	public function test_execute_strips_three_large_fields_when_return_content_falsy( string $relative_path ): void {
		$src = $this->read( $relative_path );

		// Reads $input['return_content'] and gates on it.
		$this->assertMatchesRegularExpression(
			"/\\\$return_content\\s*=\\s*!\\s*empty\\(\\s*\\\$input\\[\\s*'return_content'\\s*\\]\\s*\\)/",
			$src,
			"{$relative_path} must read \$input['return_content'] and gate on it"
		);

		// Unsets the three large fields inside the gate.
		$this->assertStringContainsString( "unset(", $src );
		$this->assertStringContainsString( "\$fetched['post_content']", $src );
		$this->assertStringContainsString( "\$fetched['post_content_filtered']", $src );
		$this->assertStringContainsString( "\$fetched['post_excerpt']", $src );
	}

	/**
	 * Guardrail: content_bytes MUST be computed from $fetched['post_content']
	 * BEFORE the unset — otherwise it always reports 0.
	 *
	 * @dataProvider content_writer_provider
	 */
	public function test_content_bytes_computed_before_strip( string $relative_path ): void {
		$src = $this->read( $relative_path );

		$strlen_pos = strpos( $src, "\$content_bytes  = strlen( (string) ( \$fetched['post_content']" );
		$unset_pos  = strpos( $src, "unset(\n\t\t\t\t\$fetched['post_content']" );

		// The plain-position check is enough for the pattern we ship; if
		// either sentinel is missing the test fails informatively.
		$this->assertNotFalse( $strlen_pos, "{$relative_path}: strlen(...post_content...) sentinel not found" );
		$this->assertNotFalse( $unset_pos, "{$relative_path}: unset(post_content...) sentinel not found" );
		$this->assertLessThan(
			$unset_pos,
			$strlen_pos,
			"{$relative_path}: content_bytes must be computed BEFORE the unset, otherwise it reports 0"
		);
	}

	/**
	 * Every response includes the content_bytes field.
	 *
	 * @dataProvider content_writer_provider
	 */
	public function test_execute_returns_content_bytes_in_response( string $relative_path, string $object_key ): void {
		$src = $this->read( $relative_path );
		$this->assertMatchesRegularExpression(
			"/'content_bytes'\\s*=>\\s*\\\$content_bytes/",
			$src,
			"{$relative_path}: response array must include content_bytes"
		);
		$this->assertMatchesRegularExpression(
			"/'{$object_key}'\\s*=>\\s*\\\$fetched/",
			$src,
			"{$relative_path}: response array must return the (possibly-stripped) \$fetched under '{$object_key}'"
		);
	}
}
