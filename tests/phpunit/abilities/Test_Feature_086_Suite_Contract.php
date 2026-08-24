<?php
/**
 * Feature 086 — source-inspection suite for database health + safe writes.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.32
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Feature_086_Suite_Contract extends WP_UnitTestCase {

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function class_slug_provider(): array {
		return array(
			'Audit_Health'               => array( 'Audit_Health', 'database/audit-health' ),
			'Audit_Index_Health'         => array( 'Audit_Index_Health', 'database/audit-index-health' ),
			'Audit_Options_Health'       => array( 'Audit_Options_Health', 'database/audit-options-health' ),
			'Cleanup_Expired_Transients' => array( 'Cleanup_Expired_Transients', 'database/cleanup-expired-transients' ),
			'Set_Option_Autoload'        => array( 'Set_Option_Autoload', 'database/set-option-autoload' ),
		);
	}

	/**
	 * @dataProvider class_slug_provider
	 */
	public function test_class_registers_expected_slug( string $class_name, string $slug ): void {
		$src = $this->src( $class_name );
		$this->assertStringContainsString( "'name' => '{$slug}'", $src );
		$this->assertStringContainsString( "'acrossai-abilities-manager-database'", $src );
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

	public function test_audits_are_readonly(): void {
		foreach ( array( 'Audit_Health', 'Audit_Index_Health', 'Audit_Options_Health' ) as $c ) {
			$src = $this->src( $c );
			$this->assertStringContainsString( "'readonly'    => true", $src, $c );
			$this->assertStringContainsString( "'destructive' => false", $src, $c );
		}
	}

	public function test_safe_writes_are_dry_run_first(): void {
		foreach ( array( 'Cleanup_Expired_Transients', 'Set_Option_Autoload' ) as $c ) {
			$src = $this->src( $c );
			$this->assertStringContainsString( "'dry_run'", $src, $c );
			$this->assertStringContainsString( "'confirm'", $src, $c );
			$this->assertStringContainsString( "'default' => true", $src, $c );
		}
	}

	public function test_index_and_options_are_bounded(): void {
		$this->assertStringContainsString( "const MAX_LIMIT     = 100", $this->src( 'Audit_Index_Health' ) );
		$this->assertStringContainsString( "const MAX_LIMIT               = 50", $this->src( 'Audit_Options_Health' ) );
		$this->assertStringContainsString( "const MAX_LIMIT     = 500", $this->src( 'Cleanup_Expired_Transients' ) );
		$this->assertStringContainsString( "const MAX_NAMES  = 25", $this->src( 'Set_Option_Autoload' ) );
		$this->assertStringContainsString( "'maximum' => self::MAX_LIMIT", $this->src( 'Audit_Index_Health' ) );
		$this->assertStringContainsString( "'maxItems' => self::MAX_NAMES", $this->src( 'Set_Option_Autoload' ) );
	}

	public function test_options_audit_never_returns_values(): void {
		$src = $this->src( 'Audit_Options_Health' );
		$this->assertStringNotContainsString( 'option_value AS value', $src );
		$this->assertStringContainsString( 'OCTET_LENGTH(option_value) AS value_bytes', $src );
	}

	public function test_transient_cleanup_never_returns_names_or_values(): void {
		$src = $this->src( 'Cleanup_Expired_Transients' );
		$out_start = strpos( $src, "'output_schema'" );
		$this->assertNotFalse( $out_start );
		$out_section = substr( $src, $out_start, 2000 );
		$this->assertStringNotContainsString( "'names'", $out_section );
		$this->assertStringNotContainsString( "'values'", $out_section );
	}

	public function test_set_option_autoload_rejects_transient_names(): void {
		$src = $this->src( 'Set_Option_Autoload' );
		$this->assertStringContainsString( "strpos( \$name, '_transient_' )", $src );
		$this->assertStringContainsString( "strpos( \$name, '_site_transient_' )", $src );
	}

	public function test_bootstrap_registers_all_five_classes(): void {
		$bootstrap = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php'
		);
		foreach ( array_column( self::class_slug_provider(), 0 ) as $class_name ) {
			$this->assertStringContainsString( "new Database\\{$class_name}();", $bootstrap );
		}
	}

	public function test_core_table_allowlist_utility_exists(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Database_Core_Table_Allowlist.php'
		);
		$this->assertStringContainsString( 'class Database_Core_Table_Allowlist', $src );
		$this->assertStringContainsString( 'public static function resolve(', $src );
		$this->assertStringContainsString( 'public static function partition(', $src );
	}

	public function test_cache_delete_expired_transients_hardened_additively(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Cache/Delete_Expired_Transients.php'
		);
		$this->assertStringContainsString( "'dry_run'", $src );
		$this->assertStringContainsString( "'limit'", $src );
		$this->assertStringContainsString( "'default' => false", $src );
	}

	private function src( string $class_name ): string {
		return (string) file_get_contents(
			dirname( __DIR__, 3 ) . "/includes/Abilities/Database/{$class_name}.php"
		);
	}
}
