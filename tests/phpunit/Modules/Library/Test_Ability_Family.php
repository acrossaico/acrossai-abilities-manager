<?php
/**
 * Tests: AcrossAI_Ability_Family — family membership lookup.
 *
 * The lookup exists so family membership is derived in exactly one place.
 * Two properties matter more than the rest and are pinned here: that the
 * order is stable, because callers page the result and a registration-order
 * answer would shift under them; and that nothing request-dependent is
 * applied, because visibility varies per connection and belongs to the
 * caller.
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Modules\Library;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Family;
use PHPUnit\Framework\TestCase;
use WP_Ability;

/**
 * Family membership behaviour.
 */
class Test_Ability_Family extends TestCase {

	/**
	 * Reset registered abilities and the per-request memo.
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['acrossai_test_abilities'] = array();
		AcrossAI_Ability_Family::flush();
	}

	/**
	 * Tear down so a leaked fixture cannot reach the next suite.
	 */
	protected function tearDown(): void {
		$GLOBALS['acrossai_test_abilities'] = array();
		AcrossAI_Ability_Family::flush();
		parent::tearDown();
	}

	/**
	 * Register a fixture ability in a family.
	 *
	 * @param  string $name   Ability name.
	 * @param  string $family Family identifier, or '' to declare none.
	 * @param  string $card   Category slug.
	 * @return void
	 */
	private function given_ability( string $name, string $family, string $card = 'acrossai-content' ): void {
		$meta = array( 'mcp' => array( 'type' => 'tool' ) );

		if ( '' !== $family ) {
			$meta['acrossai'] = array( 'tab_group' => $family );
		}

		$GLOBALS['acrossai_test_abilities'][ $name ] = new WP_Ability(
			$name,
			array(
				'label'    => ucwords( str_replace( array( '/', '-' ), ' ', $name ) ),
				'category' => $card,
				'meta'     => $meta,
			)
		);

		AcrossAI_Ability_Family::flush();
	}

	/**
	 * Members are returned for the requested family only.
	 */
	public function test_returns_only_the_requested_family(): void {
		$this->given_ability( 'content/get-post', 'content' );
		$this->given_ability( 'comments/list-comments', 'content' );
		$this->given_ability( 'cron/list-cron-jobs', 'cron' );

		$this->assertSame(
			array( 'comments/list-comments', 'content/get-post' ),
			AcrossAI_Ability_Family::member_names( 'content' )
		);
		$this->assertSame(
			array( 'cron/list-cron-jobs' ),
			AcrossAI_Ability_Family::member_names( 'cron' )
		);
	}

	/**
	 * A family drawing on several cards returns all of them.
	 *
	 * This is the case category-based grouping could not express: five cards
	 * feed the content family.
	 */
	public function test_family_spanning_several_cards(): void {
		$this->given_ability( 'content/get-post', 'content', 'acrossai-content' );
		$this->given_ability( 'comments/get-comment', 'content', 'acrossai-comments' );
		$this->given_ability( 'media/list-media', 'content', 'acrossai-media' );

		$this->assertCount( 3, AcrossAI_Ability_Family::members( 'content' ) );
	}

	/**
	 * One card feeding two families splits correctly.
	 *
	 * Five real categories do this; getting it wrong would put an ability in
	 * both Toolsets or neither.
	 */
	public function test_one_card_feeding_two_families(): void {
		$this->given_ability( 'media/list-media', 'content', 'acrossai-media' );
		$this->given_ability( 'media/update-upload-mime-types', 'configuration', 'acrossai-media' );

		$this->assertSame( array( 'media/list-media' ), AcrossAI_Ability_Family::member_names( 'content' ) );
		$this->assertSame(
			array( 'media/update-upload-mime-types' ),
			AcrossAI_Ability_Family::member_names( 'configuration' )
		);
	}

	/**
	 * Order is alphabetical by name, not registration order.
	 *
	 * Callers page the result; a registration-order answer would shift entries
	 * between pages when an unrelated plugin registers something.
	 */
	public function test_order_is_stable_regardless_of_registration_order(): void {
		$this->given_ability( 'content/zebra', 'content' );
		$this->given_ability( 'content/alpha', 'content' );
		$this->given_ability( 'content/mango', 'content' );

		$this->assertSame(
			array( 'content/alpha', 'content/mango', 'content/zebra' ),
			AcrossAI_Ability_Family::member_names( 'content' )
		);
	}

	/**
	 * An ability declaring no family belongs to none.
	 */
	public function test_ability_without_a_family_is_excluded(): void {
		$this->given_ability( 'orphan/thing', '' );

		$this->assertSame( array(), AcrossAI_Ability_Family::member_names( '' ) );
		$this->assertSame( array(), AcrossAI_Ability_Family::counts() );
	}

	/**
	 * An unknown family is empty rather than an error.
	 */
	public function test_unknown_family_returns_empty(): void {
		$this->given_ability( 'content/get-post', 'content' );

		$this->assertSame( array(), AcrossAI_Ability_Family::members( 'no-such-family' ) );
	}

	/**
	 * An empty family identifier returns empty without scanning.
	 */
	public function test_empty_family_identifier_returns_empty(): void {
		$this->given_ability( 'content/get-post', 'content' );

		$this->assertSame( array(), AcrossAI_Ability_Family::members( '' ) );
	}

	/**
	 * of() reports the family an ability declares.
	 */
	public function test_of_reports_the_declared_family(): void {
		$this->given_ability( 'content/get-post', 'content' );
		$ability = $GLOBALS['acrossai_test_abilities']['content/get-post'];

		$this->assertSame( 'content', AcrossAI_Ability_Family::of( $ability ) );
	}

	/**
	 * of() returns an empty string rather than null for an undeclared family.
	 */
	public function test_of_returns_empty_string_when_undeclared(): void {
		$this->given_ability( 'orphan/thing', '' );
		$ability = $GLOBALS['acrossai_test_abilities']['orphan/thing'];

		$this->assertSame( '', AcrossAI_Ability_Family::of( $ability ) );
	}

	/**
	 * Counts are ordered by size descending, then name ascending.
	 */
	public function test_counts_are_ordered_by_size_then_name(): void {
		$this->given_ability( 'content/a', 'content' );
		$this->given_ability( 'content/b', 'content' );
		$this->given_ability( 'content/c', 'content' );
		$this->given_ability( 'users/a', 'users' );
		$this->given_ability( 'users/b', 'users' );
		$this->given_ability( 'cron/a', 'cron' );
		$this->given_ability( 'cache/a', 'cache' );

		$this->assertSame(
			array(
				'content' => 3,
				'users'   => 2,
				'cache'   => 1,
				'cron'    => 1,
			),
			AcrossAI_Ability_Family::counts()
		);
	}

	/**
	 * Protected system abilities are excluded from every family.
	 *
	 * Dispatchers and diagnostics are not what a family is meant to contain,
	 * and a Toolset that listed them could dispatch to itself.
	 */
	public function test_protected_abilities_are_excluded(): void {
		$this->given_ability( 'content/get-post', 'content' );
		$this->given_ability( 'mcp-adapter/discover-abilities', 'content' );

		$this->assertSame(
			array( 'content/get-post' ),
			AcrossAI_Ability_Family::member_names( 'content' )
		);
	}

	/**
	 * The result carries no visibility or permission decision.
	 *
	 * Both vary between requests — the connected transport's equivalent filter
	 * resolves from which connection is handling the call — so applying either
	 * here would let a caller store one connection's view and serve it to
	 * another. The lookup returns registration facts; filtering is the
	 * caller's job.
	 */
	public function test_returns_registration_facts_without_filtering(): void {
		$GLOBALS['acrossai_test_capabilities'] = array();

		$this->given_ability( 'content/delete-post', 'content' );

		// No capabilities held, yet the ability is still reported as a member.
		$this->assertSame(
			array( 'content/delete-post' ),
			AcrossAI_Ability_Family::member_names( 'content' )
		);
	}

	/**
	 * Repeated calls in one request agree with each other.
	 */
	public function test_repeated_calls_agree(): void {
		$this->given_ability( 'content/get-post', 'content' );
		$this->given_ability( 'content/list-posts', 'content' );

		$this->assertSame(
			AcrossAI_Ability_Family::member_names( 'content' ),
			AcrossAI_Ability_Family::member_names( 'content' )
		);
	}

	/**
	 * With no abilities registered, every accessor is empty rather than fatal.
	 */
	public function test_no_registered_abilities_is_not_fatal(): void {
		$this->assertSame( array(), AcrossAI_Ability_Family::members( 'content' ) );
		$this->assertSame( array(), AcrossAI_Ability_Family::member_names( 'content' ) );
		$this->assertSame( array(), AcrossAI_Ability_Family::counts() );
	}
}
