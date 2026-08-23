<?php
/**
 * Feature 074 — source-inspection suite for parse + analysis abilities.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.31
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Feature_074_Suite_Contract extends WP_UnitTestCase {

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function class_slug_provider(): array {
		return array(
			'Parse_Content'            => array( 'Parse_Content', 'blocks/parse-content' ),
			'Serialize_Blocks'         => array( 'Serialize_Blocks', 'blocks/serialize-blocks' ),
			'Validate_Content'         => array( 'Validate_Content', 'blocks/validate-content' ),
			'Audit_Content'            => array( 'Audit_Content', 'blocks/audit-content' ),
			'Analyze_Content'          => array( 'Analyze_Content', 'blocks/analyze-content' ),
			'Evaluate_Design'          => array( 'Evaluate_Design', 'blocks/evaluate-design' ),
			'Suggest_Design_Fixes'     => array( 'Suggest_Design_Fixes', 'blocks/suggest-design-fixes' ),
			'Evaluate_Copy'            => array( 'Evaluate_Copy', 'blocks/evaluate-copy' ),
			'Suggest_Copy_Fixes'       => array( 'Suggest_Copy_Fixes', 'blocks/suggest-copy-fixes' ),
			'Evaluate_Render_Context'  => array( 'Evaluate_Render_Context', 'blocks/evaluate-render-context' ),
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

	/**
	 * @dataProvider class_slug_provider
	 */
	public function test_all_abilities_are_readonly( string $class_name ): void {
		$this->assertStringContainsString( "'readonly'    => true", $this->src( $class_name ) );
	}

	public function test_bootstrap_registers_all_ten_classes(): void {
		$bootstrap = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php'
		);
		foreach ( array_column( self::class_slug_provider(), 0 ) as $class_name ) {
			$this->assertStringContainsString( "new Block\\{$class_name}();", $bootstrap );
		}
	}

	public function test_qa_rules_registry_exists_and_is_filter_extensible(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Block_QA_Rules.php'
		);
		$this->assertStringContainsString( 'class Block_QA_Rules', $src );
		$this->assertStringContainsString( "apply_filters( 'acrossai_block_qa_rules'", $src );
		foreach ( array( 'KIND_VALIDATE', 'KIND_AUDIT', 'KIND_DESIGN', 'KIND_COPY' ) as $kind ) {
			$this->assertStringContainsString( $kind, $src );
		}
	}

	public function test_analysis_abilities_call_qa_rules(): void {
		foreach ( array( 'Validate_Content', 'Audit_Content', 'Evaluate_Design', 'Evaluate_Copy' ) as $c ) {
			$this->assertStringContainsString( 'Block_QA_Rules::run(', $this->src( $c ), $c );
		}
	}

	public function test_evaluate_design_computes_0_to_100_score(): void {
		$src = $this->src( 'Evaluate_Design' );
		$this->assertStringContainsString( 'max( 0, min( 100', $src );
	}

	public function test_evaluate_render_context_uses_dom(): void {
		$src = $this->src( 'Evaluate_Render_Context' );
		$this->assertStringContainsString( 'DOMDocument', $src );
		$this->assertStringContainsString( 'DOMXPath', $src );
		$this->assertStringContainsString( "'the_content'", $src );
	}

	public function test_primitives_accept_content_and_blocks_inputs(): void {
		$parse     = $this->src( 'Parse_Content' );
		$serialize = $this->src( 'Serialize_Blocks' );
		$this->assertStringContainsString( 'parse_blocks(', $parse );
		$this->assertStringContainsString( 'serialize_blocks(', $serialize );
	}

	public function test_qa_analysis_accepts_post_id_blocks_or_content(): void {
		foreach ( array( 'Validate_Content', 'Audit_Content', 'Analyze_Content', 'Evaluate_Design', 'Evaluate_Copy' ) as $c ) {
			$src = $this->src( $c );
			$this->assertStringContainsString( "'post_id'", $src, $c );
			$this->assertStringContainsString( "'blocks'", $src, $c );
			$this->assertStringContainsString( "'content'", $src, $c );
		}
	}

	private function src( string $class_name ): string {
		return (string) file_get_contents(
			dirname( __DIR__, 3 ) . "/includes/Abilities/Block/{$class_name}.php"
		);
	}
}
