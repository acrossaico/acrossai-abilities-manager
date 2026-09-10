<?php
/**
 * Structural tests for Feature 064 plugins/search-wp-plugin-directory.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.23
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Search_Wp_Plugin_Directory.
 */
class Test_Search_Wp_Plugin_Directory extends WP_UnitTestCase {

	/**
	 * The Search_Wp_Plugin_Directory source, loaded once per test.
	 *
	 * @var string
	 */
	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Plugins/Search_Wp_Plugin_Directory.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'plugins/search-wp-plugin-directory'", $this->src );
		$this->assertStringContainsString( "'acrossai-plugins'", $this->src );
	}

	public function test_permission_callback_gates_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\)/",
			$this->src
		);
	}

	public function test_annotations_readonly_true(): void {
		$this->assertStringContainsString( "'readonly'    => true", $this->src );
	}

	public function test_requires_plugin_install_before_plugins_api(): void {
		$this->assertStringContainsString(
			"require_once ABSPATH . 'wp-admin/includes/plugin-install.php'",
			$this->src,
			'Must load plugin-install.php before calling plugins_api() (REST context does not preload it).'
		);
	}

	public function test_calls_plugins_api_with_query_plugins(): void {
		$this->assertStringContainsString( "plugins_api(", $this->src );
		$this->assertStringContainsString( "'query_plugins'", $this->src );
	}

	public function test_requests_bounded_field_set(): void {
		$this->assertStringContainsString( "'short_description'", $this->src );
		$this->assertStringContainsString( "'sections'          => false", $this->src );
		$this->assertStringContainsString( "'banners'           => false", $this->src );
	}

	public function test_sanitizes_short_description_via_wp_kses_post(): void {
		$this->assertStringContainsString( 'wp_kses_post(', $this->src );
	}

	public function test_returns_success_true_and_empty_plugins_on_wp_error(): void {
		$this->assertStringContainsString( 'is_wp_error(', $this->src );
		$this->assertMatchesRegularExpression(
			"/is_wp_error\s*\(\s*\\\$result\s*\)/",
			$this->src
		);
	}

	public function test_input_schema_requires_query(): void {
		$this->assertStringContainsString( "'required'             => array( 'query' )", $this->src );
	}

	public function test_sanitizes_query_input(): void {
		$this->assertStringContainsString( 'sanitize_text_field(', $this->src );
	}
}
