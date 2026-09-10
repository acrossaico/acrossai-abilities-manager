<?php
/**
 * Structural tests for the Feature 062 Search_Replace ability.
 *
 * Covers the database/search-replace ability under
 * includes/Abilities/Database/Search_Replace.php including the
 * dry-run default, the empty_old and unknown_table guards, the
 * serialized-value walk, the guid protection, the skip_columns
 * behaviour, and bootstrap wiring.
 *
 * Source-inspection only, mirroring Test_Feature_057_Core_Reinstall — the
 * suite's established pattern for absorbed-tier abilities (fixture-free).
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Search_Replace.
 */
class Test_Search_Replace extends WP_UnitTestCase {

	/**
	 * Absolute paths to every source file exercised by these tests.
	 *
	 * @var array<string,string>
	 */
	private array $sources = array();

	/**
	 * Load every source file once per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$plugin_root   = dirname( __DIR__, 3 );
		$this->sources = array(
			'search_replace' => (string) file_get_contents( $plugin_root . '/includes/Abilities/Database/Search_Replace.php' ),
			'bootstrap'      => (string) file_get_contents( $plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' ),
		);
	}

	public function test_class_extends_ability_definition(): void {
		$src = $this->sources['search_replace'];
		$this->assertStringContainsString(
			'namespace AcrossAI_Abilities_Manager\\Includes\\Abilities\\Database;',
			$src
		);
		$this->assertStringContainsString( 'class Search_Replace extends Ability_Definition', $src );
		$this->assertStringContainsString( "defined( 'ABSPATH' ) || exit;", $src );
	}

	public function test_ability_name_and_category(): void {
		$src = $this->sources['search_replace'];
		$this->assertStringContainsString( "'database/search-replace'", $src );
		$this->assertStringContainsString( "'acrossai-database'", $src );
	}

	public function test_permission_callback_gates_on_manage_options(): void {
		$src = $this->sources['search_replace'];
		$this->assertMatchesRegularExpression(
			"/'permission_callback'\s*=>\s*static function\s*\(\s*\)\s*:\s*bool\s*\{\s*return current_user_can\(\s*'manage_options'\s*\);\s*\}/",
			$src
		);
	}

	public function test_input_schema_requires_old_and_new(): void {
		$src = $this->sources['search_replace'];
		$this->assertStringContainsString(
			"'required'             => array( 'old', 'new' )",
			$src
		);
	}

	public function test_input_schema_defaults_dry_run_true(): void {
		$src = $this->sources['search_replace'];
		$this->assertMatchesRegularExpression(
			"/'dry_run'\s*=>\s*array\(\s*'type'\s*=>\s*'boolean',\s*'default'\s*=>\s*true/",
			$src,
			'dry_run must default to true (safe-by-default per Decision 2 in research.md).'
		);
	}

	public function test_input_schema_defaults_include_guids_false(): void {
		$src = $this->sources['search_replace'];
		$this->assertMatchesRegularExpression(
			"/'include_guids'\s*=>\s*array\(\s*'type'\s*=>\s*'boolean',\s*'default'\s*=>\s*false/",
			$src,
			'include_guids must default to false (stricter than WP-CLI per Decision 5 in research.md).'
		);
	}

	public function test_annotations_declare_destructive_non_idempotent(): void {
		$src = $this->sources['search_replace'];
		$this->assertStringContainsString( "'readonly'    => false", $src );
		$this->assertStringContainsString( "'destructive' => true", $src );
		$this->assertStringContainsString( "'idempotent'  => false", $src );
	}

	// =========================================================================
	// Execute — dry-run default + input handling
	// =========================================================================

	public function test_execute_defaults_dry_run_to_true(): void {
		$src = $this->sources['search_replace'];
		$this->assertMatchesRegularExpression(
			"/array_key_exists\(\s*'dry_run',\s*\\\$input\s*\)\s*\?\s*\(bool\)\s*\\\$input\['dry_run'\]\s*:\s*true/",
			$src,
			'execute() must resolve dry_run to true when the caller omits the field.'
		);
	}

	public function test_execute_sanitizes_old_string(): void {
		$src = $this->sources['search_replace'];
		$this->assertMatchesRegularExpression(
			"/sanitize_text_field\(\s*\(string\)\s*\(\s*\\\$input\['old'\]/",
			$src
		);
	}

	public function test_execute_refuses_empty_old_with_correct_reason(): void {
		$src = $this->sources['search_replace'];
		$this->assertStringContainsString(
			"'blocked_reason' => 'empty_old'",
			$src
		);
	}

	// =========================================================================
	// Table allowlist — mirrors Update_Db_Rows.php pattern
	// =========================================================================

	public function test_execute_fetches_available_tables_via_show_tables(): void {
		$src = $this->sources['search_replace'];
		$this->assertStringContainsString(
			"\$wpdb->get_col( 'SHOW TABLES' )",
			$src,
			'Must fetch the table allowlist via $wpdb->get_col(SHOW TABLES) — mirrors Update_Db_Rows.php:158.'
		);
	}

	public function test_execute_refuses_unknown_table_without_writes(): void {
		$src = $this->sources['search_replace'];
		$this->assertStringContainsString(
			"'blocked_reason' => 'unknown_table'",
			$src
		);
		$this->assertMatchesRegularExpression(
			"/!\s*in_array\(\s*\\\$requested,\s*\\\$tables_available,\s*true\s*\)/",
			$src,
			'Guard must reject any requested table missing from $tables_available.'
		);
	}

	public function test_execute_defaults_to_prefix_scoped_tables(): void {
		$src = $this->sources['search_replace'];
		$this->assertStringContainsString(
			'$wpdb->prefix',
			$src,
			'When tables and all_tables are omitted, scan must be prefix-scoped.'
		);
		$this->assertStringContainsString(
			'str_starts_with',
			$src,
			'Prefix check must use str_starts_with (PHP 8.0+).'
		);
	}

	// =========================================================================
	// guid protection — Decision 5 in research.md
	// =========================================================================

	public function test_execute_skips_guid_by_default(): void {
		$src = $this->sources['search_replace'];
		$this->assertMatchesRegularExpression(
			"/!\s*\\\$include_guids\s*&&\s*\\\$wpdb->posts\s*===\s*\\\$table\s*&&\s*'guid'\s*===\s*\\\$column/",
			$src,
			'guid protection: must AND !include_guids with the wp_posts / guid identity checks.'
		);
	}

	// =========================================================================
	// skip_columns
	// =========================================================================

	public function test_execute_honors_skip_columns(): void {
		$src = $this->sources['search_replace'];
		$this->assertMatchesRegularExpression(
			"/in_array\(\s*\\\$column,\s*\\\$skip_columns,\s*true\s*\)/",
			$src
		);
	}

	// =========================================================================
	// Serialized-value walk — Decision 4 in research.md
	// =========================================================================

	public function test_execute_uses_maybe_unserialize_maybe_serialize(): void {
		$src = $this->sources['search_replace'];
		$this->assertStringContainsString( 'maybe_unserialize( $raw )', $src );
		$this->assertStringContainsString( 'maybe_serialize( $walked )', $src );
	}

	public function test_execute_walks_arrays_and_objects_recursively(): void {
		$src = $this->sources['search_replace'];
		$this->assertStringContainsString( 'private function walk_and_replace(', $src );
		$this->assertMatchesRegularExpression(
			"/is_array\(\s*\\\$data\s*\)/",
			$src
		);
		$this->assertMatchesRegularExpression(
			"/is_object\(\s*\\\$data\s*\)/",
			$src
		);
		$this->assertMatchesRegularExpression(
			"/str_replace\(\s*\\\$old,\s*\\\$new_value,\s*\\\$data\s*\)/",
			$src,
			'String leaves must be rewritten via str_replace inside the recursive walker.'
		);
	}

	// =========================================================================
	// dry_run gates the write
	// =========================================================================

	public function test_execute_only_writes_when_dry_run_false(): void {
		$src = $this->sources['search_replace'];
		$this->assertMatchesRegularExpression(
			"/if\s*\(\s*\\\$dry_run\s*\)\s*\{\s*continue;\s*\}/",
			$src,
			'Write branch must be gated behind an if(dry_run){continue;}.'
		);
		$this->assertMatchesRegularExpression(
			"/\\\$wpdb->update\(\s*\\\$table,\s*array\(\s*\\\$column\s*=>\s*\\\$new_row_value\s*\),\s*array\(\s*\\\$primary_key\s*=>\s*\\\$row\['pk'\]\s*\)\s*\)/",
			$src,
			'Actual write must use $wpdb->update( table, [col=>new], [pk=>id] ).'
		);
	}

	// =========================================================================
	// Bootstrap wiring
	// =========================================================================

	public function test_bootstrap_instantiates_search_replace(): void {
		$src = $this->sources['bootstrap'];
		$this->assertStringContainsString(
			'new Database\\Search_Replace()',
			$src
		);
	}
}
