<?php
/**
 * Feature 075 — source-inspection suite for generation + recipes abilities.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.31
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Feature_075_Suite_Contract extends WP_UnitTestCase {

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function class_slug_provider(): array {
		return array(
			'Get_Block_Guidance'         => array( 'Get_Block_Guidance', 'blocks/get-block-guidance' ),
			'List_Page_Recipes'          => array( 'List_Page_Recipes', 'blocks/list-page-recipes' ),
			'List_Section_Recipes'       => array( 'List_Section_Recipes', 'blocks/list-section-recipes' ),
			'List_Query_Section_Recipes' => array( 'List_Query_Section_Recipes', 'blocks/list-query-section-recipes' ),
			'Generate_Landing_Page'      => array( 'Generate_Landing_Page', 'blocks/generate-landing-page' ),
			'Generate_Section'           => array( 'Generate_Section', 'blocks/generate-section' ),
			'Generate_Query_Section'     => array( 'Generate_Query_Section', 'blocks/generate-query-section' ),
			'Create_Page_From_Blocks'    => array( 'Create_Page_From_Blocks', 'blocks/create-page-from-blocks' ),
			'Create_Page_From_Pattern'   => array( 'Create_Page_From_Pattern', 'blocks/create-page-from-pattern' ),
			'Create_Landing_Page'        => array( 'Create_Landing_Page', 'blocks/create-landing-page' ),
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

	public function test_bootstrap_registers_all_ten_classes(): void {
		$bootstrap = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php'
		);
		foreach ( array_column( self::class_slug_provider(), 0 ) as $class_name ) {
			$this->assertStringContainsString( "new Block\\{$class_name}();", $bootstrap );
		}
	}

	public function test_recipe_utilities_exist(): void {
		$registry = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Block_Recipe_Registry.php'
		);
		$renderer = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Block_Recipe_Renderer.php'
		);
		$this->assertStringContainsString( 'class Block_Recipe_Registry', $registry );
		$this->assertStringContainsString( "apply_filters( 'acrossai_block_recipes'", $registry );
		$this->assertStringContainsString( 'class Block_Recipe_Renderer', $renderer );
	}

	public function test_list_recipe_abilities_query_registry(): void {
		$page    = $this->src( 'List_Page_Recipes' );
		$section = $this->src( 'List_Section_Recipes' );
		$query   = $this->src( 'List_Query_Section_Recipes' );
		$this->assertStringContainsString( 'Block_Recipe_Registry::all( Block_Recipe_Registry::KIND_PAGE )', $page );
		$this->assertStringContainsString( 'Block_Recipe_Registry::all( Block_Recipe_Registry::KIND_SECTION )', $section );
		$this->assertStringContainsString( 'Block_Recipe_Registry::all( Block_Recipe_Registry::KIND_QUERY_SECTION )', $query );
	}

	public function test_generators_are_readonly_and_call_renderer(): void {
		foreach ( array( 'Generate_Section', 'Generate_Query_Section' ) as $c ) {
			$src = $this->src( $c );
			$this->assertStringContainsString( "'readonly'    => true", $src, $c );
			$this->assertStringContainsString( 'Block_Recipe_Renderer::render(', $src, $c );
		}
	}

	public function test_page_creators_persist_via_wp_insert_post(): void {
		foreach ( array( 'Create_Page_From_Blocks', 'Create_Page_From_Pattern', 'Create_Landing_Page' ) as $c ) {
			$src = $this->src( $c );
			$this->assertStringContainsString( 'wp_insert_post(', $src, $c );
			$this->assertStringContainsString( "'post_type'    => 'page'", $src, $c );
		}
	}

	public function test_page_creators_return_edit_and_view_urls(): void {
		foreach ( array( 'Create_Page_From_Blocks', 'Create_Page_From_Pattern', 'Create_Landing_Page' ) as $c ) {
			$src = $this->src( $c );
			$this->assertStringContainsString( 'get_edit_post_link(', $src, $c );
			$this->assertStringContainsString( 'get_permalink(', $src, $c );
		}
	}

	public function test_create_from_pattern_guards_registry(): void {
		$src = $this->src( 'Create_Page_From_Pattern' );
		$this->assertStringContainsString( "class_exists( '\\WP_Block_Patterns_Registry' )", $src );
		$this->assertStringContainsString( 'get_registered(', $src );
	}

	public function test_guidance_is_filter_extensible(): void {
		$src = $this->src( 'Get_Block_Guidance' );
		$this->assertStringContainsString( "apply_filters( 'acrossai_block_guidance_rules'", $src );
	}

	private function src( string $class_name ): string {
		return (string) file_get_contents(
			dirname( __DIR__, 3 ) . "/includes/Abilities/Block/{$class_name}.php"
		);
	}
}
