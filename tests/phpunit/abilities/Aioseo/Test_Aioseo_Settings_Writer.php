<?php
/**
 * Feature 132 — the settings write path, which is where this suite can do real damage.
 *
 * AIOSEO's `sanitizeAndSave()` deletes any key absent from the array it is handed, so the merge that
 * precedes it is the difference between "write three fields" and "erase every AIOSEO setting on the
 * site and report success". These assertions pin the merge semantics directly.
 *
 * Unit-level and host-free: the merge, the nesting and the list-replacement rules are pure array
 * work, and testing them through a live AIOSEO would make the one thing worth guarding depend on a
 * plugin being installed.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.44
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities\Aioseo;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Aioseo\Settings_Registry;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Aioseo\Settings_Writer;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class Test_Aioseo_Settings_Writer extends TestCase {

	/**
	 * Call one of the writer's private helpers.
	 *
	 * @param  string       $method Method name.
	 * @param  array<mixed> $args   Arguments.
	 * @return mixed
	 */
	private function call( string $method, array $args ) {
		$ref = new ReflectionMethod( Settings_Writer::class, $method );
		$ref->setAccessible( true );

		return $ref->invokeArgs( null, $args );
	}

	public function test_nest_places_a_subtree_at_a_path(): void {
		$this->assertSame(
			array( 'sitemap' => array( 'general' => array( 'enable' => true ) ) ),
			$this->call( 'nest', array( array( 'sitemap', 'general' ), array( 'enable' => true ) ) )
		);
	}

	/**
	 * THE assertion this file exists for. A patch naming one key must leave every sibling intact —
	 * at every depth. If this fails, a one-field write wipes the site's AIOSEO configuration.
	 */
	public function test_merge_preserves_every_key_the_patch_does_not_name(): void {
		$tree = array(
			'breadcrumbs' => array(
				'separator'     => '&raquo;',
				'homepageLabel' => 'Home',
			),
			'sitemap'     => array(
				'general' => array(
					'enable'        => true,
					'linksPerIndex' => 1000,
					'postTypes'     => array( 'all' => true ),
				),
			),
			'social'      => array( 'profiles' => array( 'urls' => array( 'facebookPageUrl' => 'x' ) ) ),
		);

		$merged = $this->call(
			'merge',
			array( $tree, array( 'sitemap' => array( 'general' => array( 'linksPerIndex' => 500 ) ) ) )
		);

		$this->assertSame( 500, $merged['sitemap']['general']['linksPerIndex'] );
		$this->assertTrue( $merged['sitemap']['general']['enable'] );
		$this->assertSame( array( 'all' => true ), $merged['sitemap']['general']['postTypes'] );
		$this->assertSame( 'Home', $merged['breadcrumbs']['homepageLabel'] );
		$this->assertSame( '&raquo;', $merged['breadcrumbs']['separator'] );
		$this->assertSame( 'x', $merged['social']['profiles']['urls']['facebookPageUrl'] );
	}

	/**
	 * A list on the patch side REPLACES the target wholesale.
	 *
	 * `array_merge_recursive` would append, so writing `included: [post]` over
	 * `included: [post, page, attachment]` would store all four. `array_replace_recursive` would
	 * merge by index, leaving `page` and `attachment` in place. Both are wrong for a setting that
	 * means "exactly these".
	 */
	public function test_a_list_value_replaces_rather_than_appends_or_merges(): void {
		$merged = $this->call(
			'merge',
			array(
				array( 'postTypes' => array( 'included' => array( 'post', 'page', 'attachment' ) ) ),
				array( 'postTypes' => array( 'included' => array( 'post' ) ) ),
			)
		);

		$this->assertSame( array( 'post' ), $merged['postTypes']['included'] );
	}

	/**
	 * An empty list clears a setting rather than being ignored as "nothing given".
	 */
	public function test_an_empty_list_clears_the_stored_list(): void {
		$merged = $this->call(
			'merge',
			array(
				array( 'postTypes' => array( 'included' => array( 'post', 'page' ) ) ),
				array( 'postTypes' => array( 'included' => array() ) ),
			)
		);

		$this->assertSame( array(), $merged['postTypes']['included'] );
	}

	/**
	 * A patch key absent from the target is added, not dropped — AIOSEO's defaults tree can be ahead
	 * of a stored option row that was written by an older version.
	 */
	public function test_merge_adds_a_key_the_target_lacks(): void {
		$merged = $this->call( 'merge', array( array( 'a' => 1 ), array( 'b' => 2 ) ) );

		$this->assertSame( array( 'a' => 1, 'b' => 2 ), $merged );
	}

	/**
	 * A scalar on the patch side replaces a map on the target side rather than being merged into it.
	 */
	public function test_a_scalar_patch_replaces_a_map(): void {
		$merged = $this->call( 'merge', array( array( 'a' => array( 'b' => 1 ) ), array( 'a' => 'x' ) ) );

		$this->assertSame( 'x', $merged['a'] );
	}

	public function test_dotted_field_ids_expand_to_the_nested_shape(): void {
		$this->assertSame(
			array(
				'postTypes' => array(
					'all'      => true,
					'included' => array( 'post' ),
				),
				'enable'    => false,
			),
			Settings_Registry::expand(
				array(
					'postTypes.all'      => true,
					'postTypes.included' => array( 'post' ),
					'enable'             => false,
				)
			)
		);
	}

	/**
	 * A numeric-string-keyed map is a map, not a list, so it is descended into rather than replaced.
	 * AIOSEO option values arrive from JSON, where a sparse list decodes that way.
	 */
	public function test_a_numeric_string_keyed_map_is_not_treated_as_a_list(): void {
		$ref = new ReflectionMethod( Settings_Writer::class, 'is_list' );
		$ref->setAccessible( true );

		$this->assertTrue( $ref->invoke( null, array( 'a', 'b' ) ) );
		$this->assertTrue( $ref->invoke( null, array() ) );
		$this->assertFalse( $ref->invoke( null, array( 2 => 'a', 5 => 'b' ) ) );
		$this->assertFalse( $ref->invoke( null, array( 'k' => 'v' ) ) );
	}
}
