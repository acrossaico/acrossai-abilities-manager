<?php
/**
 * Feature 069 follow-up — score provenance and id-scoped content audits.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.39
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\RankMath\Content_Audit_Repository;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\RankMath\Post_Meta_Repository;
use WP_Error;
use WP_UnitTestCase;

/**
 * Two behaviours ship together because they are two halves of one answer: write a
 * score with a recorded origin, then read those exact posts back and see it.
 */
class Test_Rank_Math_Score_Provenance extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();

		global $__acrossai_test_posts, $__acrossai_test_post_meta;
		$__acrossai_test_posts = array(
			11 => array( 'ID' => 11, 'post_title' => 'Eleven', 'post_type' => 'post' ),
			22 => array( 'ID' => 22, 'post_title' => 'Twenty-two', 'post_type' => 'page' ),
		);
		$__acrossai_test_post_meta = array();

		// update_seo_scores() checks edit_post per row; without the grant every row is
		// skipped and the provenance assertions would pass vacuously.
		$GLOBALS['acrossai_test_capabilities'] = array( 'edit_post', 'edit_others_posts' );
	}

	protected function tearDown(): void {
		global $__acrossai_test_posts, $__acrossai_test_post_meta;
		$__acrossai_test_posts                 = array();
		$__acrossai_test_post_meta             = array();
		$GLOBALS['acrossai_test_capabilities'] = array();
		parent::tearDown();
	}

	/* ---------------------------------------------------------------- writes */

	public function test_a_written_score_records_when_and_where_it_came_from(): void {
		$result = Post_Meta_Repository::update_seo_scores( array( 11 => 64 ) );

		$this->assertIsArray( $result );
		$this->assertSame( 64, get_post_meta( 11, 'rank_math_seo_score', true ) );
		$this->assertSame( 'agent', get_post_meta( 11, Post_Meta_Repository::SCORE_SOURCE_KEY, true ) );
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
			(string) get_post_meta( 11, Post_Meta_Repository::SCORE_AT_KEY, true )
		);
	}

	/**
	 * The default matters more than it looks: 'agent' is the honest label for the
	 * common path, and silently defaulting to the analyzer would claim a fidelity the
	 * number does not have.
	 */
	public function test_source_defaults_to_agent(): void {
		$result = Post_Meta_Repository::update_seo_scores( array( 11 => 10 ) );
		$this->assertSame( 'agent', $result['source'] );
		$this->assertSame( 'agent', $result['updated'][0]['source'] );
	}

	public function test_the_analyzer_source_is_recorded_when_claimed(): void {
		Post_Meta_Repository::update_seo_scores( array( 11 => 81 ), 'rank-math-analyzer' );
		$this->assertSame(
			'rank-math-analyzer',
			get_post_meta( 11, Post_Meta_Repository::SCORE_SOURCE_KEY, true )
		);
	}

	public function test_an_unknown_source_is_refused_rather_than_stored(): void {
		$result = Post_Meta_Repository::update_seo_scores( array( 11 => 81 ), 'yoast' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_input', $result->get_error_code() );
		// Nothing may be written on a rejected batch.
		$this->assertSame( '', get_post_meta( 11, 'rank_math_seo_score', true ) );
	}

	/**
	 * One run, one timestamp — otherwise a batch spanning a second boundary looks
	 * like two separate scoring runs when read back.
	 */
	public function test_a_batch_shares_one_timestamp(): void {
		$result = Post_Meta_Repository::update_seo_scores( array( 11 => 40, 22 => 90 ) );

		$this->assertCount( 2, $result['updated'] );
		$this->assertSame( $result['scored_at'], $result['updated'][0]['scored_at'] );
		$this->assertSame( $result['scored_at'], $result['updated'][1]['scored_at'] );
	}

	public function test_a_skipped_row_records_no_provenance(): void {
		$result = Post_Meta_Repository::update_seo_scores( array( 11 => 200, 22 => 55 ) );

		$this->assertSame( array( array( 'id' => 11, 'reason' => 'out_of_range' ) ), $result['skipped'] );
		$this->assertSame( '', get_post_meta( 11, Post_Meta_Repository::SCORE_SOURCE_KEY, true ) );
		$this->assertSame( 'agent', get_post_meta( 22, Post_Meta_Repository::SCORE_SOURCE_KEY, true ) );
	}

	/* ---------------------------------------------------------------- reads */

	public function test_provenance_reads_back_as_null_for_a_score_we_never_wrote(): void {
		global $__acrossai_test_post_meta;
		// A score Rank Math's own analyzer left behind: number, no origin.
		$__acrossai_test_post_meta[11]['rank_math_seo_score'] = 77;

		$this->assertSame(
			array( 'scored_at' => null, 'source' => null ),
			Post_Meta_Repository::get_score_provenance( 11 )
		);
	}

	/* ------------------------------------------------- id-scoped audit query */

	public function test_named_ids_are_queried_in_the_order_given(): void {
		$args = Content_Audit_Repository::build_query_args( array(), array( 22, 11 ) );

		$this->assertSame( array( 22, 11 ), $args['post__in'] );
		$this->assertSame( 'post__in', $args['orderby'] );
		$this->assertArrayNotHasKey( 'order', $args );
	}

	/**
	 * Naming ids names the posts. Keeping the post/page + publish defaults would drop
	 * a named draft or CPT and report it as simply absent.
	 */
	public function test_named_ids_widen_type_and_status_but_lose_to_explicit_ones(): void {
		$wide = Content_Audit_Repository::build_query_args( array(), array( 11 ) );
		$this->assertSame( 'any', $wide['post_type'] );
		$this->assertSame( 'any', $wide['post_status'] );

		$narrow = Content_Audit_Repository::build_query_args(
			array( 'post_types' => array( 'page' ), 'post_statuses' => array( 'draft' ) ),
			array( 11 )
		);
		$this->assertSame( array( 'page' ), $narrow['post_type'] );
		$this->assertSame( array( 'draft' ), $narrow['post_status'] );
	}

	public function test_an_id_list_is_returned_whole_rather_than_paginated(): void {
		$args = Content_Audit_Repository::build_query_args(
			array( 'per_page' => 1, 'page' => 3 ),
			array( 11, 22 )
		);

		$this->assertSame( 2, $args['posts_per_page'] );
		$this->assertSame( 1, $args['paged'] );
	}

	public function test_a_sweep_is_unchanged_by_the_new_branch(): void {
		$args = Content_Audit_Repository::build_query_args( array(), array() );

		$this->assertSame( array( 'post', 'page' ), $args['post_type'] );
		$this->assertSame( array( 'publish' ), $args['post_status'] );
		$this->assertSame( 50, $args['posts_per_page'] );
		$this->assertSame( 'modified', $args['orderby'] );
		$this->assertSame( 'DESC', $args['order'] );
		$this->assertArrayNotHasKey( 'post__in', $args );
	}
}
