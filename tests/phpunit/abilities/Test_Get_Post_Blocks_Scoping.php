<?php
/**
 * Behavioural coverage for blocks/get-post-blocks scoping inputs added in
 * the Feature 095 follow-up (path, depth, include_html).
 *
 * The primary correctness guard is path parity: whatever path
 * get-post-blocks annotates a block with must resolve to the same block
 * via Block_Tree::get_at_path() — so paths from get-post-blocks
 * interchange with add-block / update-post-block / remove-block. Same
 * guarantee outline-post-blocks already carries.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.32
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Block_Tree;
use ReflectionClass;
use ReflectionMethod;
use WP_UnitTestCase;

/**
 * Behavioural tests for the private static helpers Get_Post_Blocks uses to
 * implement scoping. These helpers are the interesting logic; the outer
 * execute() wrapper is thin (permission gate + parse + delegate) and already
 * covered by the source-inspection suite Test_Get_Post_Blocks.
 */
class Test_Get_Post_Blocks_Scoping extends WP_UnitTestCase {

	/**
	 * Fixture with whitespace nodes at every level — mirrors the shape
	 * parse_blocks() emits from a real post.
	 *
	 *   [0] whitespace
	 *   [1] core/heading      "Section A"
	 *   [2] whitespace
	 *   [3] core/columns
	 *       [0] whitespace
	 *       [1] core/column
	 *           [0] core/paragraph "In first column"
	 *       [2] whitespace
	 *       [3] core/column
	 *           [0] core/heading "Sub-heading"
	 *   [4] whitespace
	 *   [5] core/paragraph      "Final"
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function fixture(): array {
		$ws = static function (): array {
			return array(
				'blockName'    => null,
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => "\n\n",
				'innerContent' => array( "\n\n" ),
			);
		};
		$named = static function ( string $name, string $html, array $inner = array() ): array {
			return array(
				'blockName'    => $name,
				'attrs'        => array(),
				'innerBlocks'  => $inner,
				'innerHTML'    => $html,
				'innerContent' => array( $html ),
			);
		};

		return array(
			$ws(),                                        // [0]
			$named( 'core/heading', '<h2>Section A</h2>' ),  // [1]
			$ws(),                                        // [2]
			$named(                                       // [3]
				'core/columns',
				'',
				array(
					$ws(),                                // [3][0]
					$named(                               // [3][1]
						'core/column',
						'',
						array(
							$named( 'core/paragraph', '<p>In first column</p>' ), // [3][1][0]
						)
					),
					$ws(),                                // [3][2]
					$named(                               // [3][3]
						'core/column',
						'',
						array(
							$named( 'core/heading', '<h3>Sub-heading</h3>' ), // [3][3][0]
						)
					),
				)
			),
			$ws(),                                        // [4]
			$named( 'core/paragraph', '<p>Final</p>' ),   // [5]
		);
	}

	/**
	 * Invoke a private static helper via reflection so we can exercise it
	 * without a WP install.
	 *
	 * @param string $method
	 * @param array<int,mixed> $args
	 * @return mixed
	 */
	private function invoke( string $method, array $args ) {
		$class  = 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Content\\Get_Post_Blocks';
		$refl   = new ReflectionMethod( $class, $method );
		$refl->setAccessible( true );
		return $refl->invokeArgs( null, $args );
	}

	// -----------------------------------------------------------------------
	// sanitize_path — reuses the Add_Block / Outline convention.
	// -----------------------------------------------------------------------

	public function test_sanitize_path_accepts_ints(): void {
		$this->assertSame( array( 3, 1, 0 ), $this->invoke( 'sanitize_path', array( array( 3, 1, 0 ) ) ) );
	}

	public function test_sanitize_path_accepts_digit_strings(): void {
		$this->assertSame( array( 3, 1, 0 ), $this->invoke( 'sanitize_path', array( array( '3', '1', '0' ) ) ) );
	}

	public function test_sanitize_path_rejects_negative_or_non_numeric(): void {
		$this->assertSame( array(), $this->invoke( 'sanitize_path', array( array( 3, -1 ) ) ) );
		$this->assertSame( array(), $this->invoke( 'sanitize_path', array( array( 3, 'x' ) ) ) );
		$this->assertSame( array(), $this->invoke( 'sanitize_path', array( 'not-an-array' ) ) );
	}

	// -----------------------------------------------------------------------
	// truncate_depth — verifies innerBlocks pruning without touching HTML.
	// -----------------------------------------------------------------------

	public function test_depth_minus_one_is_passthrough(): void {
		$blocks = $this->fixture();
		$out    = $this->invoke( 'truncate_depth', array( $blocks, -1 ) );
		$this->assertSame( $blocks, $out, 'depth=-1 must return the input verbatim' );
	}

	public function test_depth_zero_clears_inner_blocks_on_root_list(): void {
		$blocks = $this->fixture();
		$out    = $this->invoke( 'truncate_depth', array( $blocks, 0 ) );

		// core/columns at index 3 had 4 innerBlocks; now empty.
		$this->assertSame( array(), $out[3]['innerBlocks'] );
		// core/heading at index 1 had no innerBlocks; still empty.
		$this->assertSame( array(), $out[1]['innerBlocks'] );
		// innerHTML preserved — truncate does not touch HTML.
		$this->assertSame( '<h2>Section A</h2>', $out[1]['innerHTML'] );
	}

	public function test_depth_one_keeps_direct_children_but_prunes_grandchildren(): void {
		$blocks = $this->fixture();
		$out    = $this->invoke( 'truncate_depth', array( $blocks, 1 ) );

		// core/columns [3] retains its 4 direct children (including whitespace).
		$this->assertCount( 4, $out[3]['innerBlocks'] );
		// The core/column at [3][1] retained but its innerBlocks pruned.
		$this->assertSame( 'core/column', $out[3]['innerBlocks'][1]['blockName'] );
		$this->assertSame( array(), $out[3]['innerBlocks'][1]['innerBlocks'] );
	}

	// -----------------------------------------------------------------------
	// strip_html — removes markup fields without touching structure.
	// -----------------------------------------------------------------------

	public function test_strip_html_removes_inner_html_and_inner_content_recursively(): void {
		$blocks = $this->fixture();
		$out    = $this->invoke( 'strip_html', array( $blocks ) );

		// Top level.
		$this->assertArrayNotHasKey( 'innerHTML', $out[1] );
		$this->assertArrayNotHasKey( 'innerContent', $out[1] );
		// Second level.
		$this->assertArrayNotHasKey( 'innerHTML', $out[3]['innerBlocks'][1] );
		// Third level.
		$this->assertArrayNotHasKey( 'innerHTML', $out[3]['innerBlocks'][1]['innerBlocks'][0] );
		// blockName preserved — strip only removes HTML fields.
		$this->assertSame( 'core/paragraph', $out[3]['innerBlocks'][1]['innerBlocks'][0]['blockName'] );
	}

	public function test_strip_html_leaves_whitespace_nodes_but_strips_their_content(): void {
		$blocks = $this->fixture();
		$out    = $this->invoke( 'strip_html', array( $blocks ) );

		// Whitespace nodes still appear (path indexing depends on their presence)
		$this->assertNull( $out[0]['blockName'] );
		$this->assertArrayNotHasKey( 'innerHTML', $out[0] );
	}

	// -----------------------------------------------------------------------
	// normalise_scoped_root — the sparse-padding trick that makes scoped
	// annotate_with_paths produce absolute paths.
	// -----------------------------------------------------------------------

	public function test_normalise_scoped_root_leaves_leaf_index_zero_untouched(): void {
		$fake_subtree = array( 'blockName' => 'core/x' );
		$out          = $this->invoke( 'normalise_scoped_root', array( array( $fake_subtree ), 0 ) );
		$this->assertSame( array( $fake_subtree ), $out );
	}

	public function test_normalise_scoped_root_pads_sparse_nulls_up_to_leaf_index(): void {
		$fake_subtree = array( 'blockName' => 'core/x' );
		$out          = $this->invoke( 'normalise_scoped_root', array( array( $fake_subtree ), 3 ) );

		$this->assertCount( 4, $out );
		$this->assertNull( $out[0] );
		$this->assertNull( $out[1] );
		$this->assertNull( $out[2] );
		$this->assertSame( $fake_subtree, $out[3] );
	}

	// -----------------------------------------------------------------------
	// Integration between the helpers and Block_Tree — path parity guard.
	// This is the MOST IMPORTANT test: get-post-blocks paths MUST resolve
	// via Block_Tree::get_at_path() to the same block.
	// -----------------------------------------------------------------------

	public function test_annotated_paths_resolve_via_block_tree_get_at_path(): void {
		$blocks    = $this->fixture();
		$annotated = Block_Tree::annotate_with_paths( $blocks );

		// Named entries at every level — pick the deep one at [3, 1, 0] to prove
		// nesting works.
		$deep = $annotated[3]['innerBlocks'][1]['innerBlocks'][0];
		$this->assertSame( array( 3, 1, 0 ), $deep['path'] );
		$this->assertSame( 'core/paragraph', $deep['blockName'] );

		$resolved = Block_Tree::get_at_path( $blocks, array( 3, 1, 0 ) );
		$this->assertIsArray( $resolved );
		$this->assertSame( 'core/paragraph', $resolved['blockName'] );
	}

	public function test_scoped_wrapping_produces_absolute_path_via_annotate_with_paths(): void {
		$blocks       = $this->fixture();
		$start_path   = array( 3, 1, 0 );

		$subtree      = Block_Tree::get_at_path( $blocks, $start_path );
		$leaf_index   = (int) end( $start_path );
		$parent_path  = $start_path;
		array_pop( $parent_path );

		$scoped_root  = $this->invoke( 'normalise_scoped_root', array( array( $subtree ), $leaf_index ) );
		$annotated    = Block_Tree::annotate_with_paths( $scoped_root, $parent_path );

		$this->assertCount( 1, $annotated );
		$this->assertSame(
			array( 3, 1, 0 ),
			$annotated[0]['path'],
			'Scoped annotation must produce absolute paths so callers can reuse them with add-block / update-post-block / remove-block'
		);
	}

	public function test_reflection_confirms_all_new_helpers_are_private_static(): void {
		$refl = new ReflectionClass( 'AcrossAI_Abilities_Manager\\Includes\\Abilities\\Content\\Get_Post_Blocks' );
		foreach ( array( 'sanitize_path', 'parent_path', 'normalise_scoped_root', 'truncate_depth', 'truncate_recursive', 'strip_html', 'format_path_error' ) as $name ) {
			$this->assertTrue( $refl->hasMethod( $name ), "Get_Post_Blocks::{$name}() must exist" );
			$m = $refl->getMethod( $name );
			$this->assertTrue( $m->isPrivate(), "{$name}() must be private" );
			$this->assertTrue( $m->isStatic(), "{$name}() must be static" );
		}
	}
}
