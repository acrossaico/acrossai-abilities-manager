<?php
/**
 * Feature 071 — source-inspection suite for reusable-block writes + wp_navigation entities.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.31
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Feature_071_Suite_Contract extends WP_UnitTestCase {

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function class_slug_provider(): array {
		return array(
			'Read_Reusable_Block'            => array( 'Read_Reusable_Block', 'blocks/read-reusable-block' ),
			'Create_Reusable_Block'          => array( 'Create_Reusable_Block', 'blocks/create-reusable-block' ),
			'Update_Reusable_Block'          => array( 'Update_Reusable_Block', 'blocks/update-reusable-block' ),
			'Extract_Reusable_Block'         => array( 'Extract_Reusable_Block', 'blocks/extract-reusable-block' ),
			'Insert_Reusable_Block_Into_Post' => array( 'Insert_Reusable_Block_Into_Post', 'blocks/insert-reusable-block-into-post' ),
			'List_Navigations'               => array( 'List_Navigations', 'blocks/list-navigations' ),
			'Read_Navigation'                => array( 'Read_Navigation', 'blocks/read-navigation' ),
			'Create_Navigation'              => array( 'Create_Navigation', 'blocks/create-navigation' ),
			'Update_Navigation'              => array( 'Update_Navigation', 'blocks/update-navigation' ),
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

	public function test_bootstrap_registers_all_nine_classes(): void {
		$bootstrap = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php'
		);
		foreach ( array_column( self::class_slug_provider(), 0 ) as $class_name ) {
			$this->assertStringContainsString( "new Block\\{$class_name}();", $bootstrap );
		}
	}

	public function test_reusable_block_writes_use_wp_block_post_type(): void {
		foreach ( array( 'Create_Reusable_Block', 'Update_Reusable_Block', 'Extract_Reusable_Block' ) as $c ) {
			$this->assertStringContainsString( "'wp_block'", $this->src( $c ), $c );
		}
	}

	public function test_navigation_writes_use_wp_navigation_post_type(): void {
		foreach ( array( 'Create_Navigation', 'Update_Navigation', 'List_Navigations', 'Read_Navigation' ) as $c ) {
			$this->assertStringContainsString( "'wp_navigation'", $this->src( $c ), $c );
		}
	}

	public function test_extract_and_insert_use_block_tree_helpers(): void {
		foreach ( array( 'Extract_Reusable_Block', 'Insert_Reusable_Block_Into_Post' ) as $c ) {
			$src = $this->src( $c );
			$this->assertStringContainsString( 'Block_Tree::', $src, $c );
			$this->assertStringContainsString( 'wp_update_post(', $src, $c );
		}
	}

	public function test_extract_rolls_back_on_failure(): void {
		$src = $this->src( 'Extract_Reusable_Block' );
		$this->assertStringContainsString( 'wp_delete_post(', $src );
	}

	private function src( string $class_name ): string {
		return (string) file_get_contents(
			dirname( __DIR__, 3 ) . "/includes/Abilities/Block/{$class_name}.php"
		);
	}
}
