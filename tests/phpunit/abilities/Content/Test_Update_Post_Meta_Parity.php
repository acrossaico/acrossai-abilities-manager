<?php
/**
 * Tests: content/update-post persists the same meta content/create-post accepts (#274).
 *
 * The issue's requested test: create a post with meta K, then update with K set to a new value;
 * update must persist it OR return a warning naming K — never success:true with K silently
 * unchanged and no warning.
 *
 * Asserted on update-post's execute() RESULT, which fully determines the outcome: a key absent
 * from `dropped_meta_keys` was passed to wp_update_post's meta_input (written); a dropped key
 * carries a matching `warnings` entry. (The unit stub's wp_update_post does not itself persist
 * meta_input, so asserting the result is both sufficient and robust; the end-to-end persistence
 * was verified live against a real site on the PR.)
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.42
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Tests\Abilities\Content;

use AcrossAI_Abilities_Manager\Includes\Abilities\Content\Update_Post;
use PHPUnit\Framework\TestCase;

/**
 * Parity and honest reporting on update-post.
 */
class Test_Update_Post_Meta_Parity extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['acrossai_test_capabilities'] = array( 'manage_options' );
		$GLOBALS['acrossai_test_current_user'] = 1;

		// Seed a plain post so get_post()/wp_update_post() resolve it.
		$GLOBALS['__acrossai_test_posts'] = array(
			123 => array(
				'ID'          => 123,
				'post_type'   => 'post',
				'post_status' => 'draft',
				'post_title'  => 'Seed',
			),
		);
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['__acrossai_test_posts'], $GLOBALS['__acrossai_test_post_meta'] );
		parent::tearDown();
	}

	/**
	 * The issue's scenario: updating with rank_math_* keeps them — not dropped, not warned.
	 *
	 * @return void
	 */
	public function test_rank_math_meta_is_not_dropped_on_update(): void {
		$result = ( new Update_Post() )->execute(
			array(
				'id'   => 123,
				'meta' => array(
					'rank_math_description'   => 'New description',
					'rank_math_focus_keyword' => 'WordPress MCP,WordPress MCP Adapter',
					'rank_math_title'         => 'WordPress MCP in 2027',
				),
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame(
			array(),
			$result['dropped_meta_keys'],
			'rank_math_* must be written, so dropped_meta_keys is empty.'
		);
	}

	/**
	 * An internal `_` key is refused AND warned — never silently dropped.
	 *
	 * This is the honest-success guarantee: #274's core complaint was success:true with a populated
	 * dropped list and an EMPTY warnings array.
	 *
	 * @return void
	 */
	public function test_an_internal_key_is_dropped_with_a_matching_warning(): void {
		$result = ( new Update_Post() )->execute(
			array(
				'id'   => 123,
				'meta' => array(
					'rank_math_title' => 'kept',
					'_edit_lock'      => '1700000000:1',
				),
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( array( '_edit_lock' ), $result['dropped_meta_keys'] );

		// The guarantee: a dropped key never comes with an empty warnings array.
		$this->assertNotEmpty( $result['warnings'], 'A dropped key must carry a warning.' );
		$matched = array_filter(
			$result['warnings'],
			static fn( string $w ): bool => false !== strpos( $w, '_edit_lock' )
		);
		$this->assertNotEmpty( $matched, 'The warning must name the dropped key.' );
	}

	/**
	 * A clean update (no meta refused) carries no spurious meta warnings.
	 *
	 * @return void
	 */
	public function test_a_clean_update_has_no_dropped_keys(): void {
		$result = ( new Update_Post() )->execute(
			array(
				'id'   => 123,
				'meta' => array( 'rank_math_title' => 'x' ),
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( array(), $result['dropped_meta_keys'] );
	}
}
