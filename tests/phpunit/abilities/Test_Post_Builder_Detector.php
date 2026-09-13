<?php
/**
 * Feature 108 — Post_Builder_Detector.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.39
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Post_Builder_Detector;
use WP_UnitTestCase;

class Test_Post_Builder_Detector extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['__acrossai_test_posts']     = array();
		$GLOBALS['__acrossai_test_post_meta'] = array();
	}

	protected function tearDown(): void {
		$GLOBALS['__acrossai_test_posts']     = array();
		$GLOBALS['__acrossai_test_post_meta'] = array();

		parent::tearDown();
	}

	/**
	 * Register a fake post plus its meta.
	 *
	 * @param int                  $id      Post ID.
	 * @param string               $content post_content.
	 * @param array<string, mixed> $meta    Post meta.
	 * @param string               $type    Post type.
	 */
	private function post( int $id, string $content = '', array $meta = array(), string $type = 'page' ): void {
		$GLOBALS['__acrossai_test_posts'][ $id ] = array(
			'ID'           => $id,
			'post_type'    => $type,
			'post_content' => $content,
		);

		$GLOBALS['__acrossai_test_post_meta'][ $id ] = $meta;
	}

	public function test_unknown_post_returns_null(): void {
		$this->assertNull( Post_Builder_Detector::detect( 4242 ) );
	}

	public function test_block_markup_is_the_block_editor(): void {
		$this->post( 1, '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' );

		$row = Post_Builder_Detector::detect( 1 );

		$this->assertSame( 'block-editor', $row['builder'] );
		$this->assertSame( Post_Builder_Detector::WRITES_APPLY, $row['post_content_writes'] );
	}

	public function test_plain_html_is_classic(): void {
		$this->post( 2, '<p>Just HTML</p>' );

		$row = Post_Builder_Detector::detect( 2 );

		$this->assertSame( 'classic', $row['builder'] );
		$this->assertSame( Post_Builder_Detector::WRITES_APPLY, $row['post_content_writes'] );
	}

	/**
	 * Empty is its own answer, not classic.
	 *
	 * `has_blocks()` cannot tell them apart — it is a substring test, so it returns false for both.
	 * The distinction matters to a caller: writing to an empty post is always safe, writing over
	 * classic content replaces someone's markup.
	 */
	public function test_empty_is_distinguished_from_classic(): void {
		$this->post( 3, '' );
		$this->post( 4, "   \n\t " );

		$this->assertSame( 'empty', Post_Builder_Detector::detect( 3 )['builder'] );
		$this->assertSame( 'empty', Post_Builder_Detector::detect( 4 )['builder'], 'Whitespace-only content is empty.' );
	}

	public function test_elementor_is_detected_from_its_ownership_flag(): void {
		$this->post( 5, 'stale derived text', array( '_elementor_edit_mode' => 'builder' ) );

		$row = Post_Builder_Detector::detect( 5 );

		$this->assertSame( 'elementor', $row['builder'] );
		$this->assertSame( Post_Builder_Detector::WRITES_IGNORED, $row['post_content_writes'] );
		$this->assertStringContainsString( '_elementor_data', $row['content_stored_in'] );
	}

	/**
	 * A builder flag beats block markup.
	 *
	 * Elementor leaves derived text in post_content and a Divi post keeps shortcodes there, so a
	 * detector that checked has_blocks() first would mislabel any builder page whose stale body
	 * happened to contain a block comment.
	 */
	public function test_a_builder_flag_wins_over_block_markup(): void {
		$this->post( 6, '<!-- wp:paragraph --><p>left over</p><!-- /wp:paragraph -->', array( '_elementor_edit_mode' => 'builder' ) );

		$this->assertSame( 'elementor', Post_Builder_Detector::detect( 6 )['builder'] );
	}

	/**
	 * The wrong flag value is not ownership.
	 *
	 * Elementor deletes the meta rather than setting it falsy when a page stops being
	 * Elementor-built, but an empty string survives some migrations.
	 */
	public function test_an_empty_elementor_flag_is_not_ownership(): void {
		$this->post( 7, '<p>plain</p>', array( '_elementor_edit_mode' => '' ) );

		$this->assertSame( 'classic', Post_Builder_Detector::detect( 7 )['builder'] );
	}

	/**
	 * Shortcode builders invert the risk and must say so.
	 *
	 * Divi and WPBakery render FROM post_content, so a rewrite lands and destroys the layout
	 * rather than doing nothing. Reporting them the same way as Elementor would be wrong in the
	 * dangerous direction.
	 */
	public function test_shortcode_builders_report_writes_as_destructive(): void {
		$this->post( 8, '[et_pb_section][/et_pb_section]', array( '_et_pb_use_builder' => 'on' ) );
		$this->post( 9, '[vc_row][vc_column][/vc_column][/vc_row]' );

		$divi = Post_Builder_Detector::detect( 8 );
		$vc   = Post_Builder_Detector::detect( 9 );

		$this->assertSame( 'divi', $divi['builder'] );
		$this->assertSame( Post_Builder_Detector::WRITES_DESTRUCTIVE, $divi['post_content_writes'] );

		$this->assertSame( 'wpbakery', $vc['builder'], 'WPBakery has no ownership flag; the content marker is the only signal.' );
		$this->assertSame( Post_Builder_Detector::WRITES_DESTRUCTIVE, $vc['post_content_writes'] );

		foreach ( array( $divi, $vc ) as $row ) {
			$this->assertStringContainsString( 'DOES take effect', $row['guidance'], 'The guidance must not read like the silent-no-op case.' );
		}
	}

	/**
	 * Ownership and availability are separate facts, and the advice differs.
	 */
	public function test_an_orphaned_builder_page_is_reported_as_inactive(): void {
		$this->post( 10, 'left behind', array( '_elementor_edit_mode' => 'builder' ) );

		$row = Post_Builder_Detector::detect( 10 );

		$this->assertSame( 'elementor', $row['builder'] );

		if ( class_exists( '\\Elementor\\Plugin' ) ) {
			$this->assertTrue( $row['builder_active'] );
			return;
		}

		$this->assertFalse( $row['builder_active'] );
		$this->assertStringContainsString( 'not active', $row['guidance'] );
		$this->assertStringContainsString( 'reactivated', $row['guidance'], 'An orphaned page needs the warning that the edit disappears if the builder returns.' );
	}

	/**
	 * Signals are a LIST of rows.
	 *
	 * A name-keyed map encodes as a JSON object and fails the ability's own `array`-typed output
	 * schema after the work is done (BUG-ARRAY-TYPED-OUTPUT-IS-A-JSON-OBJECT).
	 */
	public function test_signals_are_rows_not_a_map(): void {
		$this->post( 11, '<p>x</p>' );

		$signals = Post_Builder_Detector::detect( 11 )['signals'];

		$this->assertNotEmpty( $signals );
		$this->assertSame( range( 0, count( $signals ) - 1 ), array_keys( $signals ), 'signals must be a list.' );

		foreach ( $signals as $signal ) {
			$this->assertSame( array( 'source', 'value', 'kind' ), array_keys( $signal ) );
		}
	}

	/**
	 * Every row carries the keys the ability's consumers read.
	 */
	public function test_every_result_has_the_full_shape(): void {
		$this->post( 12, '<p>x</p>' );

		$expected = array(
			'post_id',
			'post_type',
			'builder',
			'builder_label',
			'builder_active',
			'content_stored_in',
			'post_content_writes',
			'post_content_bytes',
			'signals',
			'guidance',
		);

		$this->assertSame( $expected, array_keys( Post_Builder_Detector::detect( 12 ) ) );
	}

	/**
	 * The verdict is always one of the three declared constants.
	 *
	 * A free-form string here would be read by callers branching on it.
	 */
	public function test_the_write_verdict_is_always_one_of_three(): void {
		$allowed = array(
			Post_Builder_Detector::WRITES_APPLY,
			Post_Builder_Detector::WRITES_IGNORED,
			Post_Builder_Detector::WRITES_DESTRUCTIVE,
		);

		$this->post( 20, '' );
		$this->post( 21, '<p>x</p>' );
		$this->post( 22, '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->' );
		$this->post( 23, 'x', array( '_elementor_edit_mode' => 'builder' ) );
		$this->post( 24, '[vc_row][/vc_row]' );
		$this->post( 25, 'x', array( '_fl_builder_enabled' => '1' ) );

		foreach ( range( 20, 25 ) as $id ) {
			$this->assertContains( Post_Builder_Detector::detect( $id )['post_content_writes'], $allowed, "Post {$id}" );
		}
	}

	/**
	 * Guidance is never empty — it is the field a caller is meant to act on.
	 */
	public function test_guidance_is_always_present(): void {
		$this->post( 30, '' );
		$this->post( 31, '<p>x</p>' );
		$this->post( 32, 'x', array( '_elementor_edit_mode' => 'builder' ) );

		foreach ( range( 30, 32 ) as $id ) {
			$this->assertNotSame( '', trim( Post_Builder_Detector::detect( $id )['guidance'] ), "Post {$id}" );
		}
	}
}
