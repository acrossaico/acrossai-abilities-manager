<?php
/**
 * Feature 087 — source-inspection suite for engine audit + InnoDB conversion.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.32
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Feature_087_Suite_Contract extends WP_UnitTestCase {

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function class_slug_provider(): array {
		return array(
			'Audit_Core_Table_Engines'     => array( 'Audit_Core_Table_Engines', 'database/audit-core-table-engines' ),
			'Convert_Core_Tables_To_Innodb' => array( 'Convert_Core_Tables_To_Innodb', 'database/convert-core-tables-to-innodb' ),
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

	public function test_audit_is_readonly(): void {
		$src = $this->src( 'Audit_Core_Table_Engines' );
		$this->assertStringContainsString( "'readonly'    => true", $src );
		$this->assertStringContainsString( "'destructive' => false", $src );
	}

	public function test_convert_is_destructive_but_idempotent(): void {
		$src = $this->src( 'Convert_Core_Tables_To_Innodb' );
		$this->assertStringContainsString( "'readonly'    => false", $src );
		$this->assertStringContainsString( "'destructive' => true", $src );
		$this->assertStringContainsString( "'idempotent'  => true", $src );
	}

	public function test_convert_requires_dry_run_and_confirm(): void {
		$src = $this->src( 'Convert_Core_Tables_To_Innodb' );
		$this->assertStringContainsString( "'dry_run'", $src );
		$this->assertStringContainsString( "'confirm'", $src );
		$this->assertStringContainsString( "'default' => true", $src );
	}

	public function test_convert_uses_identifier_placeholder(): void {
		$src = $this->src( 'Convert_Core_Tables_To_Innodb' );
		$this->assertStringContainsString( "'ALTER TABLE %i ENGINE = InnoDB'", $src );
	}

	public function test_both_resolve_via_core_table_allowlist(): void {
		foreach ( array( 'Audit_Core_Table_Engines', 'Convert_Core_Tables_To_Innodb' ) as $c ) {
			$src = $this->src( $c );
			$this->assertStringContainsString( 'Database_Core_Table_Allowlist::', $src, $c );
			$this->assertStringContainsString( 'Database_Core_Table_Allowlist::resolve(', $src, $c );
		}
	}

	public function test_convert_uses_mutation_attribution(): void {
		$src = $this->src( 'Convert_Core_Tables_To_Innodb' );
		$this->assertStringContainsString( 'Database_Mutation_Attribution::classify(', $src );
		$this->assertStringContainsString( 'Database_Mutation_Attribution::aggregate(', $src );
	}

	public function test_convert_verifies_postcondition(): void {
		$src = $this->src( 'Convert_Core_Tables_To_Innodb' );
		$this->assertMatchesRegularExpression( "/SELECT ENGINE FROM information_schema\\.TABLES/", $src );
	}

	public function test_bootstrap_registers_both_classes(): void {
		$bootstrap = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php'
		);
		foreach ( array_column( self::class_slug_provider(), 0 ) as $class_name ) {
			$this->assertStringContainsString( "new Database\\{$class_name}();", $bootstrap );
		}
	}

	public function test_mutation_attribution_utility_exists(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Database_Mutation_Attribution.php'
		);
		$this->assertStringContainsString( 'class Database_Mutation_Attribution', $src );
		$this->assertStringContainsString( "const OUTCOME_CHANGED", $src );
		$this->assertStringContainsString( "const OUTCOME_UNKNOWN", $src );
		$this->assertStringContainsString( 'public static function classify(', $src );
		$this->assertStringContainsString( 'public static function aggregate(', $src );
	}

	private function src( string $class_name ): string {
		return (string) file_get_contents(
			dirname( __DIR__, 3 ) . "/includes/Abilities/Database/{$class_name}.php"
		);
	}
}
