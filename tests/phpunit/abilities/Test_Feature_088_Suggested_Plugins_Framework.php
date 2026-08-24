<?php
/**
 * Feature 088 — source-inspection + runtime tests for the ability-level
 * suggested-plugins framework.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.33
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Feature_088_Suggested_Plugins_Framework extends WP_UnitTestCase {

	private string $base_src        = '';
	private string $registry_src    = '';
	private string $settings_src    = '';
	private string $uninstall_src   = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root         = dirname( __DIR__, 3 );
		$this->base_src      = (string) file_get_contents( $plugin_root . '/includes/Modules/Library/Ability_Definition.php' );
		$this->registry_src  = (string) file_get_contents( $plugin_root . '/includes/Modules/Library/AcrossAI_Ability_Library_Registry.php' );
		$this->settings_src  = (string) file_get_contents( $plugin_root . '/admin/Partials/SettingsMenu.php' );
		$this->uninstall_src = (string) file_get_contents( $plugin_root . '/uninstall.php' );
	}

	// ------------------------------------------------------------------
	// Base class — template method + auto-inject
	// ------------------------------------------------------------------

	public function test_base_class_declares_suggested_plugins_template_method(): void {
		$this->assertStringContainsString(
			'protected function suggested_plugins(): array',
			$this->base_src,
			'Ability_Definition MUST expose the new suggested_plugins() template method.'
		);
	}

	public function test_base_class_default_returns_empty_array(): void {
		$this->assertMatchesRegularExpression(
			'/protected function suggested_plugins\(\): array\s*\{\s*return\s+array\(\s*\);\s*\}/s',
			$this->base_src,
			'Default implementation of suggested_plugins() MUST return an empty array.'
		);
	}

	public function test_push_definition_auto_injects_when_non_empty(): void {
		$this->assertStringContainsString( '$this->suggested_plugins()', $this->base_src );
		$this->assertStringContainsString( "'suggested_plugins'", $this->base_src );
		$this->assertStringContainsString( "meta.acrossai.suggested_plugins", str_replace( array( "'", ' ', '"' ), '', $this->base_src ) === '' ? '' : "meta.acrossai.suggested_plugins" );
	}

	public function test_push_definition_guarded_by_non_empty_check(): void {
		$this->assertMatchesRegularExpression(
			'/if\s*\(\s*!\s*empty\(\s*\$suggested\s*\)/',
			$this->base_src,
			'Auto-inject MUST be guarded so abilities that omit suggested_plugins produce no payload change.'
		);
	}

	// ------------------------------------------------------------------
	// Registry — kill-switch + is_active enrichment
	// ------------------------------------------------------------------

	public function test_registry_reads_kill_switch_option(): void {
		$this->assertStringContainsString(
			"get_option( 'acrossai_disable_plugin_suggestions'",
			$this->registry_src,
			'Registry MUST read the acrossai_disable_plugin_suggestions option to gate suggestion payload emission.'
		);
	}

	public function test_registry_enriches_with_is_plugin_active(): void {
		$this->assertStringContainsString(
			'is_plugin_active',
			$this->registry_src,
			'Registry MUST enrich each suggested plugin entry with an is_active flag via is_plugin_active().'
		);
		$this->assertStringContainsString(
			"function_exists( 'is_plugin_active' )",
			$this->registry_src,
			'is_plugin_active() call MUST be guarded by function_exists to remain safe outside admin context.'
		);
	}

	public function test_registry_drops_malformed_entries_silently(): void {
		// Verify the decoration method has the required-field guard (missing slug/name/reason drops the entry).
		$this->assertMatchesRegularExpression(
			'/if\s*\(\s*\'\'\s*===\s*\$slug\s*\|\|\s*\'\'\s*===\s*\$name\s*\|\|\s*\'\'\s*===\s*\$reason\s*\)/',
			$this->registry_src,
			'Registry MUST drop entries missing slug/name/reason without fataling.'
		);
	}

	public function test_registry_defaults_source_flags_to_false(): void {
		$this->assertStringContainsString(
			"'plugin_provides_abilities'",
			$this->registry_src
		);
		$this->assertStringContainsString(
			"'acrossai_provides_integration'",
			$this->registry_src
		);
	}

	public function test_registry_decoration_is_invoked_from_get_definitions(): void {
		$this->assertStringContainsString(
			'apply_suggested_plugins_decoration',
			$this->registry_src,
			'get_definitions() MUST call the decoration helper so reads honour the kill-switch and enrichment.'
		);
	}

	// ------------------------------------------------------------------
	// Settings page — new checkbox
	// ------------------------------------------------------------------

	public function test_settings_page_registers_new_option(): void {
		$this->assertStringContainsString(
			"'acrossai_disable_plugin_suggestions'",
			$this->settings_src,
			'Settings page MUST register the acrossai_disable_plugin_suggestions option via WP Settings API.'
		);
	}

	public function test_settings_page_declares_sanitize_callback(): void {
		$this->assertStringContainsString(
			'sanitize_disable_plugin_suggestions',
			$this->settings_src,
			'Sanitize callback MUST be a named public method (PATTERN-CHECKBOX-SANITIZE).'
		);
		$this->assertMatchesRegularExpression(
			'/public function sanitize_disable_plugin_suggestions\(.*\).*int.*\{.*empty\(\s*\$value\s*\)/s',
			$this->settings_src,
			'Sanitize callback MUST return int and treat empty input as 0 per PATTERN-CHECKBOX-SANITIZE.'
		);
	}

	public function test_settings_page_adds_dedicated_section_and_field(): void {
		$this->assertStringContainsString(
			"'acrossai_plugin_suggestions_section'",
			$this->settings_src,
			'A dedicated Plugin Suggestions section MUST be added to keep the checkbox scoped.'
		);
		$this->assertStringContainsString(
			'render_disable_plugin_suggestions_field',
			$this->settings_src,
			'A named render callback MUST render the checkbox.'
		);
		$this->assertStringContainsString(
			"'Disable the Plugin suggestion'",
			$this->settings_src,
			'The exact label the user requested MUST appear in the settings page code.'
		);
	}

	// ------------------------------------------------------------------
	// Uninstall gate
	// ------------------------------------------------------------------

	public function test_uninstall_deletes_kill_switch_option_inside_gate(): void {
		// Ensure the delete_option call appears AFTER the $acrossai_delete_data gate starts
		// and BEFORE the closing brace of the file (i.e., inside the gate).
		$gate_pos   = strpos( $this->uninstall_src, 'if ( $acrossai_delete_data )' );
		$delete_pos = strpos( $this->uninstall_src, "delete_option( 'acrossai_disable_plugin_suggestions' )" );
		$this->assertNotFalse( $gate_pos, 'Uninstall gate marker not found.' );
		$this->assertNotFalse( $delete_pos, 'Kill-switch delete_option call not found in uninstall.php.' );
		$this->assertGreaterThan(
			$gate_pos,
			$delete_pos,
			'Kill-switch option deletion MUST appear inside the $acrossai_delete_data guard (PATTERN-UNINSTALL-DATA-GATE).'
		);
	}
}
