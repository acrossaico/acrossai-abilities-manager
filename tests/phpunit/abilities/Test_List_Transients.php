<?php
/**
 * Structural tests for Feature 064 cache/list-transients.
 *
 * Source-inspection tests, mirroring the Feature 059 pattern.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.23
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_List_Transients.
 */
class Test_List_Transients extends WP_UnitTestCase {

	/**
	 * The List_Transients source, loaded once per test.
	 *
	 * @var string
	 */
	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Cache/List_Transients.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'cache/list-transients'", $this->src );
		$this->assertStringContainsString( "'acrossai-cache'", $this->src );
	}

	public function test_permission_callback_gates_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\)/",
			$this->src
		);
	}

	public function test_annotations_are_readonly_true(): void {
		$this->assertStringContainsString( "'readonly'    => true", $this->src );
		$this->assertStringContainsString( "'destructive' => false", $this->src );
	}

	public function test_uses_prepared_like_with_esc_like(): void {
		$this->assertStringContainsString( '$wpdb->prepare(', $this->src );
		$this->assertStringContainsString( "esc_like( '_transient_' )", $this->src );
		$this->assertStringContainsString( "esc_like( '_site_transient_' )", $this->src );
	}

	public function test_filters_out_timeout_companion_rows(): void {
		$this->assertStringContainsString( '_transient_timeout_', $this->src );
		$this->assertStringContainsString( '_site_transient_timeout_', $this->src );
		$this->assertStringContainsString( 'continue', $this->src, 'Must skip timeout companion rows.' );
	}

	public function test_computes_is_expired_against_time(): void {
		$this->assertStringContainsString( 'time()', $this->src );
		$this->assertStringContainsString( 'is_expired', $this->src );
	}

	public function test_honours_search_and_include_expired_and_site_only(): void {
		$this->assertStringContainsString( "'search'", $this->src );
		$this->assertStringContainsString( "'include_expired'", $this->src );
		$this->assertStringContainsString( "'site_only'", $this->src );
	}

	public function test_pagination_bounded_by_limit_and_offset(): void {
		$this->assertStringContainsString( "'limit'", $this->src );
		$this->assertStringContainsString( "'offset'", $this->src );
		$this->assertStringContainsString( "'maximum' => 500", $this->src );
	}

	public function test_reports_total_separate_from_returned_count(): void {
		$this->assertStringContainsString( "'total'", $this->src );
		$this->assertStringContainsString( "'count'", $this->src );
		$this->assertStringContainsString( 'array_slice(', $this->src );
	}

	public function test_sanitizes_search_input(): void {
		$this->assertStringContainsString( 'sanitize_text_field(', $this->src );
	}
}
