<?php
/**
 * Tests: the protected-meta policy shared by the generic post writers (#274).
 *
 * `content/update-post` used to strip every `is_protected_meta()` key, which caught writable
 * plugin meta like `rank_math_*` and lost it silently while reporting success. The policy now
 * refuses only `_`-prefixed internal keys (minus the allowlist) and reports anything refused. These
 * tests pin that, independently of the ability wiring.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.43
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Tests\Abilities\Content;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Post_Meta_Policy;
use PHPUnit\Framework\TestCase;

/**
 * Partition and warning behaviour.
 */
class Test_Post_Meta_Policy extends TestCase {

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['acrossai_test_filter_values']['acrossai_allowed_protected_meta'] );
		parent::tearDown();
	}

	/**
	 * The reported keys — all non-underscore plugin meta — are written, not dropped.
	 *
	 * This is the #274 regression: these were lost before.
	 *
	 * @return void
	 */
	public function test_rank_math_keys_are_written(): void {
		$result = Post_Meta_Policy::partition(
			array(
				'rank_math_title'         => 'WordPress MCP in 2027',
				'rank_math_description'   => 'New description',
				'rank_math_focus_keyword' => 'WordPress MCP',
			)
		);

		$this->assertSame( array(), $result['dropped'], 'No rank_math_* key may be dropped.' );
		$this->assertSame(
			array( 'rank_math_title', 'rank_math_description', 'rank_math_focus_keyword' ),
			array_keys( $result['write'] )
		);
	}

	/**
	 * `_`-prefixed internal keys are refused.
	 *
	 * The genuine footgun Feature 065 guards and this keeps: writing `_edit_lock` or serialized
	 * builder state through a bulk update corrupts editor/revision state.
	 *
	 * @return void
	 */
	public function test_underscore_keys_are_dropped(): void {
		$result = Post_Meta_Policy::partition(
			array(
				'_edit_lock'   => '1700000000:1',
				'_thumbnail_id' => 42,
				'public_key'   => 'kept',
			)
		);

		$this->assertSame( array( '_edit_lock', '_thumbnail_id' ), $result['dropped'] );
		$this->assertSame( array( 'public_key' => 'kept' ), $result['write'] );
	}

	/**
	 * A mix writes the plugin keys and drops only the internal one — the exact reported shape.
	 *
	 * @return void
	 */
	public function test_mixed_meta_writes_plugin_keys_and_drops_internal(): void {
		$result = Post_Meta_Policy::partition(
			array(
				'rank_math_title' => 'kept',
				'_edit_lock'      => 'dropped',
			)
		);

		$this->assertSame( array( '_edit_lock' ), $result['dropped'] );
		$this->assertArrayHasKey( 'rank_math_title', $result['write'] );
	}

	/**
	 * The allowlist filter lets a `_`-prefixed key through — Feature 065's escape hatch, preserved.
	 *
	 * @return void
	 */
	public function test_allowlisted_underscore_key_is_written(): void {
		$GLOBALS['acrossai_test_filter_values']['acrossai_allowed_protected_meta'] = array( '_my_allowed_key' );

		$result = Post_Meta_Policy::partition(
			array(
				'_my_allowed_key' => 'let me through',
				'_edit_lock'      => 'still dropped',
			)
		);

		$this->assertSame( array( '_edit_lock' ), $result['dropped'] );
		$this->assertArrayHasKey( '_my_allowed_key', $result['write'] );
	}

	/**
	 * The drop warning names the key and points at the ability that can write it.
	 *
	 * The honest-reporting half of #274: a dropped key must come with a reason, not an empty
	 * warnings array.
	 *
	 * @return void
	 */
	public function test_drop_warning_names_key_and_escape_hatch(): void {
		$warning = Post_Meta_Policy::drop_warning( '_edit_lock' );

		$this->assertStringContainsString( '_edit_lock', $warning );
		$this->assertStringContainsString( 'content/update-post-meta', $warning );
	}

	/**
	 * Empty input partitions to empty, writing nothing and dropping nothing.
	 *
	 * @return void
	 */
	public function test_empty_meta_is_inert(): void {
		$result = Post_Meta_Policy::partition( array() );

		$this->assertSame( array(), $result['write'] );
		$this->assertSame( array(), $result['dropped'] );
	}
}
