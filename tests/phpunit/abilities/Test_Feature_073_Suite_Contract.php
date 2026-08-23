<?php
/**
 * Feature 073 — source-inspection suite for locking + bindings abilities.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.31
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Feature_073_Suite_Contract extends WP_UnitTestCase {

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function class_slug_provider(): array {
		return array(
			'Set_Block_Lock'      => array( 'Set_Block_Lock', 'blocks/set-block-lock' ),
			'Set_Allowed_Blocks'  => array( 'Set_Allowed_Blocks', 'blocks/set-allowed-blocks' ),
			'Set_Template_Lock'   => array( 'Set_Template_Lock', 'blocks/set-template-lock' ),
			'Read_Block_Bindings' => array( 'Read_Block_Bindings', 'blocks/read-block-bindings' ),
			'Set_Block_Bindings'  => array( 'Set_Block_Bindings', 'blocks/set-block-bindings' ),
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

	public function test_bootstrap_registers_all_five_classes(): void {
		$bootstrap = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php'
		);
		foreach ( array_column( self::class_slug_provider(), 0 ) as $class_name ) {
			$this->assertStringContainsString( "new Block\\{$class_name}();", $bootstrap );
		}
	}

	public function test_locking_targets_container_blocks(): void {
		foreach ( array( 'Set_Allowed_Blocks', 'Set_Template_Lock' ) as $c ) {
			$src = $this->src( $c );
			$this->assertStringContainsString( 'CONTAINERS', $src, $c );
			$this->assertStringContainsString( "'core/group'", $src, $c );
		}
	}

	public function test_bindings_guard_registry_availability(): void {
		foreach ( array( 'Read_Block_Bindings', 'Set_Block_Bindings' ) as $c ) {
			$this->assertStringContainsString( "class_exists( '\\WP_Block_Bindings_Registry' )", $this->src( $c ), $c );
		}
	}

	public function test_bindings_validate_source_against_registry(): void {
		$src = $this->src( 'Set_Block_Bindings' );
		$this->assertStringContainsString( 'get_registered(', $src );
		$this->assertStringContainsString( 'Unknown binding source', $src );
	}

	public function test_all_writes_use_block_tree_helpers_and_persist(): void {
		foreach ( array( 'Set_Block_Lock', 'Set_Allowed_Blocks', 'Set_Template_Lock', 'Set_Block_Bindings' ) as $c ) {
			$src = $this->src( $c );
			$this->assertStringContainsString( 'Block_Tree::', $src, $c );
			$this->assertStringContainsString( 'wp_update_post(', $src, $c );
		}
	}

	private function src( string $class_name ): string {
		return (string) file_get_contents(
			dirname( __DIR__, 3 ) . "/includes/Abilities/Block/{$class_name}.php"
		);
	}
}
