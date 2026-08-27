<?php
/**
 * Feature 066 follow-up — source-inspection tests for blocks/outline-post-blocks.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.32
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Outline_Post_Blocks extends WP_UnitTestCase {

	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Content/Outline_Post_Blocks.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'blocks/outline-post-blocks'", $this->src );
		$this->assertStringContainsString( "'acrossai-abilities-manager-content'", $this->src );
	}

	public function test_permission_callback_matches_sibling_abilities(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\\(\\s*'manage_options'\\s*\\)\\s*&&\\s*current_user_can\\(\\s*'edit_posts'\\s*\\)/",
			$this->src,
			'Must gate on both manage_options and edit_posts to match Get_Post_Blocks / Add_Block / Remove_Block'
		);
	}

	public function test_annotations_readonly_idempotent_nondestructive(): void {
		$this->assertStringContainsString( "'readonly'    => true", $this->src );
		$this->assertStringContainsString( "'destructive' => false", $this->src );
		$this->assertStringContainsString( "'idempotent'  => true", $this->src );
	}

	public function test_delegates_to_block_tree_read(): void {
		$this->assertStringContainsString(
			"Block_Tree::parse_post_blocks( \$post_id, 'read' )",
			$this->src,
			'Must reuse the same parse+guard chain Get_Post_Blocks uses'
		);
		$this->assertStringContainsString(
			'Block_Tree::walk_tree(',
			$this->src,
			'Must reuse Block_Tree traversal so paths match the raw-index convention'
		);
	}

	public function test_input_schema_requires_post_id_and_is_closed(): void {
		$this->assertStringContainsString( "'required'             => array( 'post_id' )", $this->src );
		$this->assertMatchesRegularExpression(
			"/'additionalProperties'\\s*=>\\s*false/",
			$this->src
		);
	}

	public function test_input_schema_field_defaults(): void {
		// max_text default 80, capped 0..500
		$this->assertMatchesRegularExpression( "/'max_text'\\s*=>\\s*array\\([^)]*'default'\\s*=>\\s*80/s", $this->src );
		$this->assertMatchesRegularExpression( "/'max_text'\\s*=>\\s*array\\([^)]*'maximum'\\s*=>\\s*500/s", $this->src );
		// depth default -1
		$this->assertMatchesRegularExpression( "/'depth'\\s*=>\\s*array\\([^)]*'default'\\s*=>\\s*-1/s", $this->src );
		// max_results default 500, cap 2000
		$this->assertMatchesRegularExpression( "/'max_results'\\s*=>\\s*array\\([^)]*'default'\\s*=>\\s*500/s", $this->src );
		$this->assertMatchesRegularExpression( "/'max_results'\\s*=>\\s*array\\([^)]*'maximum'\\s*=>\\s*2000/s", $this->src );
		// include_attrs default false
		$this->assertMatchesRegularExpression( "/'include_attrs'\\s*=>\\s*array\\([^)]*'default'\\s*=>\\s*false/s", $this->src );
	}

	public function test_output_schema_declares_staleness_marker(): void {
		$this->assertStringContainsString( "'post_modified_gmt'", $this->src );
		$this->assertStringContainsString( "'truncated'", $this->src );
	}

	public function test_output_schema_never_declares_inner_html_or_content(): void {
		// The response contract is content-free by construction. Isolate the
		// output_schema block and verify it never declares an innerHTML /
		// innerContent property, even though the execute() body reads
		// innerHTML from source blocks to compute bytes/preview.
		$this->assertSame(
			1,
			preg_match( "/'output_schema'\\s*=>\\s*array\\(.*?\\),\\s*'meta'/s", $this->src, $match ),
			'Could not locate output_schema block'
		);
		$output_schema = $match[0];
		$this->assertStringNotContainsString( "'innerHTML'", $output_schema );
		$this->assertStringNotContainsString( "'innerContent'", $output_schema );
		$this->assertStringNotContainsString( 'serialize_blocks', $this->src, 'The outline must never serialize blocks back into markup' );
	}

	public function test_description_calls_out_staleness_and_contains_scope(): void {
		$this->assertMatchesRegularExpression(
			'/post_modified_gmt/',
			$this->src,
			'Description must mention post_modified_gmt so LLM callers know how to check staleness'
		);
		$this->assertMatchesRegularExpression(
			'/re-outline|re-run the outline/i',
			$this->src,
			'Description must tell callers to re-outline after a write rather than caching paths'
		);
		$this->assertMatchesRegularExpression(
			'/max_text/',
			$this->src,
			'Description must mention that contains matches only within max_text'
		);
	}
}
