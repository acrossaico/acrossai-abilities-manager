<?php
/**
 * Feature 072 — source-inspection suite for usage lookups + advanced mutations.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.31
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Feature_072_Suite_Contract extends WP_UnitTestCase {

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function class_slug_provider(): array {
		return array(
			'Find_Navigation_Usage'     => array( 'Find_Navigation_Usage', 'blocks/find-navigation-usage' ),
			'Find_Template_Part_Usage'  => array( 'Find_Template_Part_Usage', 'blocks/find-template-part-usage' ),
			'Find_Reusable_Block_Usage' => array( 'Find_Reusable_Block_Usage', 'blocks/find-reusable-block-usage' ),
			'Mutate_Block_Tree'         => array( 'Mutate_Block_Tree', 'blocks/mutate-block-tree' ),
			'Transform_Blocks'          => array( 'Transform_Blocks', 'blocks/transform-blocks' ),
			'Replace_Block_Text'        => array( 'Replace_Block_Text', 'blocks/replace-block-text' ),
			'Normalize_Heading_Levels'  => array( 'Normalize_Heading_Levels', 'blocks/normalize-heading-levels' ),
		);
	}

	/**
	 * @dataProvider class_slug_provider
	 */
	public function test_class_registers_expected_slug( string $class_name, string $slug ): void {
		$src = $this->src( $class_name );
		$this->assertStringContainsString( "'name' => '{$slug}'", $src );
		$this->assertStringContainsString( "'acrossai-abilities-manager-block'", $src );
	}

	/**
	 * @dataProvider class_slug_provider
	 */
	public function test_class_extends_ability_definition( string $class_name ): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src( $class_name ) );
	}

	/**
	 * @dataProvider class_slug_provider
	 */
	public function test_class_gates_on_manage_options( string $class_name ): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\)/",
			$this->src( $class_name )
		);
	}

	public function test_bootstrap_registers_all_seven_classes(): void {
		$bootstrap = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php'
		);
		foreach ( array_column( self::class_slug_provider(), 0 ) as $class_name ) {
			$this->assertStringContainsString( "new Block\\{$class_name}();", $bootstrap );
		}
	}

	public function test_usage_lookups_walk_block_templates(): void {
		foreach ( array( 'Find_Navigation_Usage', 'Find_Template_Part_Usage', 'Find_Reusable_Block_Usage' ) as $c ) {
			$src = $this->src( $c );
			$this->assertStringContainsString( 'get_block_templates(', $src, $c );
			$this->assertStringContainsString( "'wp_template'", $src, $c );
			$this->assertStringContainsString( 'parse_blocks(', $src, $c );
		}
	}

	public function test_usage_lookups_include_result_cap(): void {
		foreach ( array( 'Find_Navigation_Usage', 'Find_Template_Part_Usage', 'Find_Reusable_Block_Usage' ) as $c ) {
			$this->assertStringContainsString( 'RESULT_CAP', $this->src( $c ), $c );
		}
	}

	public function test_advanced_mutations_use_block_tree_and_persist(): void {
		foreach ( array( 'Mutate_Block_Tree', 'Transform_Blocks', 'Replace_Block_Text', 'Normalize_Heading_Levels' ) as $c ) {
			$src = $this->src( $c );
			$this->assertStringContainsString( 'Block_Tree::', $src, $c );
			$this->assertStringContainsString( 'wp_update_post(', $src, $c );
		}
	}

	public function test_mutate_block_tree_enforces_op_allowlist(): void {
		$src = $this->src( 'Mutate_Block_Tree' );
		$this->assertStringContainsString( "'insert'", $src );
		$this->assertStringContainsString( "'remove'", $src );
		$this->assertStringContainsString( "'move'", $src );
		$this->assertStringContainsString( "'update'", $src );
		$this->assertStringContainsString( "'duplicate'", $src );
	}

	public function test_transform_blocks_enforces_transform_enum(): void {
		$src = $this->src( 'Transform_Blocks' );
		$this->assertStringContainsString( "'paragraph-to-heading'", $src );
		$this->assertStringContainsString( "'heading-to-paragraph'", $src );
	}

	public function test_replace_block_text_supports_plain_and_regex(): void {
		$src = $this->src( 'Replace_Block_Text' );
		$this->assertStringContainsString( "'plain'", $src );
		$this->assertStringContainsString( "'regex'", $src );
	}

	private function src( string $class_name ): string {
		return (string) file_get_contents(
			dirname( __DIR__, 3 ) . "/includes/Abilities/Block/{$class_name}.php"
		);
	}
}
