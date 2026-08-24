<?php
/**
 * Structural tests for Feature 064 cache/delete-expired-transients.
 *
 * Source-inspection tests, mirroring the Feature 059 pattern.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.23
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_Delete_Expired_Transients.
 */
class Test_Delete_Expired_Transients extends WP_UnitTestCase {

	/**
	 * The Delete_Expired_Transients source, loaded once per test.
	 *
	 * @var string
	 */
	private string $src = '';

	protected function setUp(): void {
		parent::setUp();
		$plugin_root = dirname( __DIR__, 3 );
		$this->src   = (string) file_get_contents(
			$plugin_root . '/includes/Abilities/Cache/Delete_Expired_Transients.php'
		);
	}

	public function test_extends_ability_definition(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
	}

	public function test_registers_correct_slug_and_category(): void {
		$this->assertStringContainsString( "'cache/delete-expired-transients'", $this->src );
		$this->assertStringContainsString( "'acrossai-abilities-manager-cache'", $this->src );
	}

	public function test_permission_callback_gates_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\(\s*'manage_options'\s*\)/",
			$this->src
		);
	}

	public function test_annotations_are_destructive_true(): void {
		$this->assertStringContainsString( "'readonly'    => false", $this->src );
		$this->assertStringContainsString( "'destructive' => true", $this->src );
		$this->assertStringContainsString( "'idempotent'  => true", $this->src );
	}

	public function test_captures_count_before_core_call(): void {
		// The core function returns void, so the count must be snapshotted first.
		// Verify that the $count assignment appears in the source BEFORE the
		// delete_expired_transients() call. Feature 086 hardening added an
		// early-return dry-run branch between the two — the ordering assertion
		// still holds (the assignment happens before the core call in the
		// non-dry-run path), so we assert file-order rather than adjacency.
		$src           = $this->src;
		$count_pos     = strpos( $src, '$count' );
		// The actual runtime call is the LAST mention of the function in the
		// file; earlier mentions are inside docblocks.
		$core_call_pos = strrpos( $src, 'delete_expired_transients(' );
		$this->assertNotFalse( $count_pos );
		$this->assertNotFalse( $core_call_pos );
		$this->assertLessThan(
			$core_call_pos,
			$count_pos,
			'$count assignment must appear before the delete_expired_transients() call in execute().'
		);
	}

	public function test_calls_wp_core_delete_expired_transients(): void {
		$this->assertStringContainsString( 'delete_expired_transients(', $this->src );
	}

	public function test_uses_prepared_queries_with_esc_like(): void {
		$this->assertStringContainsString( '$wpdb->prepare(', $this->src );
		$this->assertStringContainsString( "esc_like( '_transient_timeout_' )", $this->src );
		$this->assertStringContainsString( "esc_like( '_site_transient_timeout_' )", $this->src );
	}

	public function test_returns_deleted_integer_and_message(): void {
		$this->assertStringContainsString( "'deleted'", $this->src );
		$this->assertStringContainsString( "'message'", $this->src );
	}
}
