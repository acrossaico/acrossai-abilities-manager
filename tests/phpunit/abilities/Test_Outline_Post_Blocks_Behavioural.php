<?php
/**
 * Feature 066 follow-up — behavioural tests for the outline builder.
 *
 * The most important test in this file is the path-parity guard: a path
 * returned by Outline_Post_Blocks::build_outline() MUST resolve to the
 * same block via Block_Tree::get_at_path(), so a caller can hand the
 * path straight to add-block / remove-block / update-post-block. A
 * future refactor that starts skipping whitespace nodes during traversal
 * would break the pairing silently — this suite fails loudly instead.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.32
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Content\Outline_Post_Blocks;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Block_Tree;
use WP_UnitTestCase;

class Test_Outline_Post_Blocks_Behavioural extends WP_UnitTestCase {

	/**
	 * Build a fixture with whitespace nodes (parse_blocks emits null-blockName
	 * entries between real blocks) at every level. Raw parse_blocks indices:
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
	 *   [5] core/paragraph      "Final paragraph"
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function fixture(): array {
		$whitespace = static function (): array {
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

		$paragraph_in_col_a = $named( 'core/paragraph', '<p>In first column</p>' );
		$sub_heading        = $named( 'core/heading', '<h3>Sub-heading</h3>' );

		$col_a = $named(
			'core/column',
			'',
			array(
				$paragraph_in_col_a,
			)
		);
		$col_b = $named(
			'core/column',
			'',
			array(
				$sub_heading,
			)
		);

		$columns = $named(
			'core/columns',
			'',
			array(
				$whitespace(),        // [3][0]
				$col_a,               // [3][1]
				$whitespace(),        // [3][2]
				$col_b,               // [3][3]
			)
		);

		return array(
			$whitespace(),                                   // [0]
			$named( 'core/heading', '<h2>Section A</h2>' ),  // [1]
			$whitespace(),                                   // [2]
			$columns,                                        // [3]
			$whitespace(),                                   // [4]
			$named( 'core/paragraph', '<p>Final paragraph</p>' ), // [5]
		);
	}

	// -----------------------------------------------------------------------
	// The must-not-break-this test: path parity with Block_Tree::get_at_path.
	// -----------------------------------------------------------------------

	public function test_every_returned_path_resolves_via_block_tree_to_the_same_block(): void {
		$blocks = $this->fixture();
		$out    = Outline_Post_Blocks::build_outline( $blocks, array(), -1, 80, array(), '', false, 500 );

		$this->assertFalse( $out['truncated'] );
		$this->assertNotEmpty( $out['blocks'], 'Fixture yields at least one named entry' );

		foreach ( $out['blocks'] as $entry ) {
			$resolved = Block_Tree::get_at_path( $blocks, $entry['path'] );
			$this->assertIsArray(
				$resolved,
				sprintf( 'Path %s must resolve via Block_Tree::get_at_path — a divergent path scheme silently breaks add-block / remove-block / update-post-block', wp_json_encode( $entry['path'] ) )
			);
			$this->assertSame(
				$entry['blockName'],
				(string) ( $resolved['blockName'] ?? '' ),
				sprintf( 'Path %s must resolve to the same block type — outline said %s but Block_Tree returned a different node', wp_json_encode( $entry['path'] ), $entry['blockName'] )
			);
		}
	}

	// -----------------------------------------------------------------------
	// Whitespace nodes: excluded from output but consume index positions.
	// -----------------------------------------------------------------------

	public function test_null_blockname_entries_are_excluded_from_output(): void {
		$blocks = $this->fixture();
		$out    = Outline_Post_Blocks::build_outline( $blocks, array(), -1, 80, array(), '', false, 500 );

		foreach ( $out['blocks'] as $entry ) {
			$this->assertNotEmpty(
				$entry['blockName'],
				'A whitespace / null-blockName entry appeared in the output — the ability contract says these must be skipped'
			);
		}
	}

	public function test_raw_indices_include_whitespace_positions(): void {
		$blocks = $this->fixture();
		$out    = Outline_Post_Blocks::build_outline( $blocks, array(), -1, 80, array(), '', false, 500 );

		// The first top-level named block is at raw index 1 (index 0 is whitespace).
		$paths = array_map( static fn( $e ) => $e['path'], $out['blocks'] );

		$this->assertContains( array( 1 ), $paths, 'core/heading at raw index 1 must be reported' );
		$this->assertContains( array( 3 ), $paths, 'core/columns at raw index 3 must be reported' );
		$this->assertContains( array( 5 ), $paths, 'core/paragraph at raw index 5 must be reported' );

		// Nested: core/column at raw index 1 inside core/columns (index 0 is whitespace).
		$this->assertContains( array( 3, 1 ), $paths, 'core/column at [3,1] must be reported' );
		$this->assertContains( array( 3, 3 ), $paths, 'core/column at [3,3] must be reported' );

		// And deeper still, retaining raw indices.
		$this->assertContains( array( 3, 1, 0 ), $paths );
		$this->assertContains( array( 3, 3, 0 ), $paths );

		// Any collapsed-past-whitespace path should NOT be present.
		$this->assertNotContains( array( 0 ), $paths, 'Compacted named-only index 0 must not be produced — raw indices only' );
		$this->assertNotContains( array( 2 ), $paths, 'Compacted named-only index 2 must not be produced' );
	}

	// -----------------------------------------------------------------------
	// Response content contract: no markup ever appears in the output.
	// -----------------------------------------------------------------------

	public function test_response_never_contains_inner_html_or_inner_content_keys(): void {
		$blocks = $this->fixture();
		$out    = Outline_Post_Blocks::build_outline( $blocks, array(), -1, 500, array(), '', true, 500 );

		$serialized = wp_json_encode( $out );
		$this->assertIsString( $serialized );
		$this->assertStringNotContainsString( 'innerHTML', $serialized );
		$this->assertStringNotContainsString( 'innerContent', $serialized );
		$this->assertStringNotContainsString( 'wp:paragraph', $serialized, 'No serialized block markup may leak' );
	}

	// -----------------------------------------------------------------------
	// depth: 0 → exactly one entry (the subtree root).
	// -----------------------------------------------------------------------

	public function test_depth_zero_returns_exactly_one_entry(): void {
		$blocks = $this->fixture();
		$out    = Outline_Post_Blocks::build_outline( $blocks, array( 3 ), 0, 80, array(), '', false, 500 );

		$this->assertSame( 1, count( $out['blocks'] ) );
		$this->assertSame( array( 3 ), $out['blocks'][0]['path'] );
		$this->assertSame( 'core/columns', $out['blocks'][0]['blockName'] );
	}

	public function test_depth_zero_from_root_still_visits_named_top_level_only(): void {
		// depth=0 with start_path=[] means "just the root list", i.e. no
		// entries at relative-depth 1+. The root itself isn't a block, so
		// count() - count([]) === 1 for every top-level entry and depth=0
		// filters them all out.
		$blocks = $this->fixture();
		$out    = Outline_Post_Blocks::build_outline( $blocks, array(), 0, 80, array(), '', false, 500 );
		$this->assertSame( array(), $out['blocks'] );
	}

	public function test_depth_one_from_root_returns_only_top_level_named_blocks(): void {
		$blocks = $this->fixture();
		$out    = Outline_Post_Blocks::build_outline( $blocks, array(), 1, 80, array(), '', false, 500 );

		$paths = array_map( static fn( $e ) => $e['path'], $out['blocks'] );
		$this->assertSame(
			array( array( 1 ), array( 3 ), array( 5 ) ),
			$paths,
			'depth=1 from root must return the three top-level named blocks in document order'
		);
	}

	// -----------------------------------------------------------------------
	// Acceptance criterion #5: a 48 KB block yields <200 bytes for that entry.
	// -----------------------------------------------------------------------

	public function test_a_48kb_block_yields_a_small_outline_entry(): void {
		$big_html = str_repeat( 'x', 48 * 1024 );
		$blocks   = array(
			array(
				'blockName'    => 'core/paragraph',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => $big_html,
				'innerContent' => array( $big_html ),
			),
		);

		$out = Outline_Post_Blocks::build_outline( $blocks, array(), -1, 80, array(), '', false, 500 );
		$this->assertCount( 1, $out['blocks'] );

		$entry = $out['blocks'][0];
		$this->assertSame( strlen( $big_html ), $entry['bytes'], 'bytes must equal strlen(innerHTML)' );

		$serialized = wp_json_encode( $entry );
		$this->assertIsString( $serialized );
		$this->assertLessThan(
			200,
			strlen( $serialized ),
			'A 48 KB block must produce an outline entry that serializes to under 200 bytes — the point of this ability'
		);
	}

	// -----------------------------------------------------------------------
	// max_results truncation sets truncated:true, never silently drops.
	// -----------------------------------------------------------------------

	public function test_max_results_truncation_flags_truncated_true(): void {
		$blocks = array();
		for ( $i = 0; $i < 25; $i++ ) {
			$blocks[] = array(
				'blockName'    => 'core/paragraph',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => "<p>Row {$i}</p>",
				'innerContent' => array( "<p>Row {$i}</p>" ),
			);
		}
		$out = Outline_Post_Blocks::build_outline( $blocks, array(), -1, 80, array(), '', false, 10 );

		$this->assertTrue( $out['truncated'], 'Reaching max_results must set truncated:true' );
		$this->assertSame( 10, count( $out['blocks'] ), 'Never over-return past max_results' );
	}

	public function test_max_results_not_reached_leaves_truncated_false(): void {
		$blocks = $this->fixture();
		$out    = Outline_Post_Blocks::build_outline( $blocks, array(), -1, 80, array(), '', false, 500 );

		$this->assertFalse( $out['truncated'] );
	}

	// -----------------------------------------------------------------------
	// block_names filter — output-only, does not affect traversal.
	// -----------------------------------------------------------------------

	public function test_block_names_filter_still_descends_into_excluded_containers(): void {
		$blocks = $this->fixture();
		$out    = Outline_Post_Blocks::build_outline(
			$blocks,
			array(),
			-1,
			80,
			array( 'core/paragraph' ),
			'',
			false,
			500
		);

		$paths = array_map( static fn( $e ) => $e['path'], $out['blocks'] );

		// Even though core/columns and core/column are filtered out of the
		// OUTPUT, the walk still had to descend into them to find the
		// paragraph at [3,1,0]. That entry appearing here proves it.
		$this->assertContains(
			array( 3, 1, 0 ),
			$paths,
			'block_names filter must not stop traversal into excluded containers'
		);
		$this->assertContains( array( 5 ), $paths );

		foreach ( $out['blocks'] as $entry ) {
			$this->assertSame( 'core/paragraph', $entry['blockName'] );
		}
	}

	// -----------------------------------------------------------------------
	// contains filter — case-insensitive, ANDed with block_names.
	// -----------------------------------------------------------------------

	public function test_contains_filter_is_case_insensitive(): void {
		$blocks = $this->fixture();

		$out = Outline_Post_Blocks::build_outline( $blocks, array(), -1, 80, array(), 'SECTION', false, 500 );
		$this->assertCount( 1, $out['blocks'], 'contains "SECTION" (upper) should match "Section A" (mixed case)' );
		$this->assertSame( array( 1 ), $out['blocks'][0]['path'] );
	}

	public function test_contains_and_block_names_are_anded(): void {
		$blocks = $this->fixture();

		$out = Outline_Post_Blocks::build_outline(
			$blocks,
			array(),
			-1,
			80,
			array( 'core/paragraph' ),
			'final',
			false,
			500
		);
		$this->assertCount( 1, $out['blocks'], 'AND of block_names=paragraph and contains=final leaves only [5]' );
		$this->assertSame( array( 5 ), $out['blocks'][0]['path'] );
	}

	// -----------------------------------------------------------------------
	// Text preview extraction.
	// -----------------------------------------------------------------------

	public function test_preview_strips_tags_and_collapses_whitespace(): void {
		$blocks = array(
			array(
				'blockName'    => 'core/paragraph',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => "<p>Hello   <strong>world</strong>\n&amp; hi</p>",
				'innerContent' => array(),
			),
		);
		$out    = Outline_Post_Blocks::build_outline( $blocks, array(), -1, 80, array(), '', false, 500 );

		$this->assertSame( 'Hello world & hi', $out['blocks'][0]['text'] );
	}

	public function test_max_text_zero_omits_the_text_key_entirely(): void {
		$blocks = array(
			array(
				'blockName'    => 'core/paragraph',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '<p>hello</p>',
				'innerContent' => array(),
			),
		);
		$out    = Outline_Post_Blocks::build_outline( $blocks, array(), -1, 0, array(), '', false, 500 );

		$this->assertArrayNotHasKey( 'text', $out['blocks'][0] );
	}

	public function test_preview_uses_multibyte_safe_truncation(): void {
		$blocks = array(
			array(
				'blockName'    => 'core/paragraph',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '<p>αβγδε ζηθικ λμνξο πρστυ</p>',
				'innerContent' => array(),
			),
		);
		$out    = Outline_Post_Blocks::build_outline( $blocks, array(), -1, 5, array(), '', false, 500 );

		$this->assertSame( 'αβγδε', $out['blocks'][0]['text'], 'Truncation must count characters, not bytes' );
	}

	public function test_preview_ignores_children_content(): void {
		$blocks = array(
			array(
				'blockName'    => 'core/columns',
				'attrs'        => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
				'innerBlocks'  => array(
					array(
						'blockName'    => 'core/paragraph',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '<p>child text that should NOT be in parent preview</p>',
						'innerContent' => array(),
					),
				),
			),
		);
		$out    = Outline_Post_Blocks::build_outline( $blocks, array(), -1, 500, array(), '', false, 500 );

		$columns_entry = $out['blocks'][0];
		$this->assertSame( 'core/columns', $columns_entry['blockName'] );
		$this->assertSame( '', $columns_entry['text'], 'Container preview must come from its own innerHTML only' );
	}

	// -----------------------------------------------------------------------
	// childCount: named children only.
	// -----------------------------------------------------------------------

	public function test_child_count_ignores_whitespace_children(): void {
		$blocks = $this->fixture();
		$out    = Outline_Post_Blocks::build_outline( $blocks, array( 3 ), 0, 0, array(), '', false, 500 );

		$this->assertCount( 1, $out['blocks'] );
		$this->assertSame( 'core/columns', $out['blocks'][0]['blockName'] );
		// core/columns has 4 raw children (whitespace, column, whitespace, column) but only 2 named.
		$this->assertSame( 2, $out['blocks'][0]['childCount'] );
	}

	// -----------------------------------------------------------------------
	// include_attrs toggle.
	// -----------------------------------------------------------------------

	public function test_include_attrs_toggle(): void {
		$blocks = array(
			array(
				'blockName'    => 'core/heading',
				'attrs'        => array( 'level' => 2, 'anchor' => 'section-a' ),
				'innerBlocks'  => array(),
				'innerHTML'    => '<h2>Section A</h2>',
				'innerContent' => array(),
			),
		);

		$without = Outline_Post_Blocks::build_outline( $blocks, array(), -1, 80, array(), '', false, 500 );
		$this->assertArrayNotHasKey( 'attrs', $without['blocks'][0] );

		$with = Outline_Post_Blocks::build_outline( $blocks, array(), -1, 80, array(), '', true, 500 );
		$this->assertArrayHasKey( 'attrs', $with['blocks'][0] );
		$this->assertSame( array( 'level' => 2, 'anchor' => 'section-a' ), $with['blocks'][0]['attrs'] );
	}

	// -----------------------------------------------------------------------
	// bytes field is accurate — proves callers can use it as a cost estimate.
	// -----------------------------------------------------------------------

	public function test_bytes_field_equals_strlen_inner_html(): void {
		$html   = '<h2 class="wp-block-heading">Hello there</h2>';
		$blocks = array(
			array(
				'blockName'    => 'core/heading',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => $html,
				'innerContent' => array( $html ),
			),
		);
		$out    = Outline_Post_Blocks::build_outline( $blocks, array(), -1, 80, array(), '', false, 500 );

		$this->assertSame( strlen( $html ), $out['blocks'][0]['bytes'] );
	}

	// -----------------------------------------------------------------------
	// Empty post yields success, not error.
	// -----------------------------------------------------------------------

	public function test_empty_tree_is_success_with_zero_entries(): void {
		$out = Outline_Post_Blocks::build_outline( array(), array(), -1, 80, array(), '', false, 500 );

		$this->assertFalse( $out['truncated'] );
		$this->assertSame( array(), $out['blocks'] );
	}
}
