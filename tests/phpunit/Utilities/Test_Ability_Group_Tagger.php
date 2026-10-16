<?php
/**
 * Issue #184 — abilities registered by another plugin get a toolset.
 *
 * The tagger fills `meta.acrossai.tab_group` at registration for abilities whose author had no reason
 * to set a key in this plugin's namespace. Everything downstream — the tab strip, the counts, the
 * `?tab=` filter, the Toolset column and the MCP dispatchers — reads that one value, so these tests are
 * the contract for all of them.
 *
 * The rule that matters most is the first one: it only ever fills a gap. That is what lets an
 * integration be supplied by its host plugin, by us, or by both at once without either side knowing.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.35
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Group_Tagger;
use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\AcrossAI_Toolset_Integrations;
use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\ACF;
use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\Rank_Math;
use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\Elementor;
use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\AcrossAI_Catch_All_Integration;

require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/AcrossAI_Toolset_Integration.php';
require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/AcrossAI_Catch_All_Integration.php';
require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/Rank_Math.php';
require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/AcrossAI_Toolset_Integrations.php';
require_once dirname( __DIR__, 3 ) . '/includes/Modules/Library/Integrations/AcrossAI_Integration_Ability_Base.php';
require_once dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/ACF.php';
require_once dirname( __DIR__, 3 ) . '/includes/Utilities/AcrossAI_Ability_Group_Tagger.php';

/**
 * Covers AcrossAI_Ability_Group_Tagger.
 */
class Test_Ability_Group_Tagger extends TestCase {

	/**
	 * Drop memoised integrations and any filter fixtures.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		AcrossAI_Toolset_Integrations::flush();

		$GLOBALS['acrossai_test_filter_values']    = array();
		$GLOBALS['acrossai_test_filter_callbacks'] = array();

		// In production ACF adds itself to this filter from its constructor, so that the one instance
		// Main creates serves both the opt-in switch and the toolset. The unit bootstrap stubs
		// add_filter() to a no-op, so the list is seeded here instead — with the real classes, so a
		// change to what ACF or Rank Math claims still fails these tests.
		$GLOBALS['acrossai_test_filter_values']['acrossai_toolset_integrations'] = array(
			new ACF(),
			new Rank_Math(),
			// The only seeded integration that declares sub-group rules, so the sub-group tests below
			// exercise the real rules rather than a fixture that could drift from them.
			new Elementor(),
			new AcrossAI_Catch_All_Integration(),
		);
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		AcrossAI_Toolset_Integrations::flush();
		AcrossAI_Ability_Group_Tagger::reset();

		unset(
			$GLOBALS['acrossai_test_filter_values'],
			$GLOBALS['acrossai_test_filter_callbacks']
		);

		parent::tearDown();
	}

	/**
	 * The group the tagger would write into the args.
	 *
	 * @param  array<string, mixed> $args Registration args.
	 * @param  string               $name Ability name.
	 * @return string|null
	 */
	private function tagged( array $args, string $name ): ?string {
		$out = AcrossAI_Ability_Group_Tagger::tag( $args, $name );

		return $out['meta']['acrossai']['tab_group'] ?? null;
	}

	/**
	 * An ability that declares its own group is never re-tagged.
	 *
	 * This single rule is what makes the mixed case work: our 61 Rank Math abilities declare
	 * `rank-math` and must pass through untouched while Rank Math's own 26 get tagged.
	 *
	 * @return void
	 */
	public function test_a_declared_group_is_never_overwritten(): void {
		$args = array( 'meta' => array( 'acrossai' => array( 'tab_group' => 'content' ) ) );

		$this->assertSame( 'content', $this->tagged( $args, 'rank-math/get-settings' ) );
	}

	/**
	 * A Toolset dispatcher is left alone.
	 *
	 * Dispatchers carry `meta.acrossai.toolset` and deliberately no group — tagging one would place it
	 * inside the toolset it serves, and a Toolset that can dispatch to itself is a loop.
	 *
	 * @return void
	 */
	public function test_a_dispatcher_is_not_tagged(): void {
		$args = array( 'meta' => array( 'acrossai' => array( 'toolset' => true ) ) );

		$this->assertNull( $this->tagged( $args, 'toolset/content' ) );
	}

	/**
	 * A host plugin's abilities land in their integration's group.
	 *
	 * @param  string $name     Ability name.
	 * @param  string $expected Group.
	 * @return void
	 *
	 * @dataProvider provide_prefixed_abilities
	 */
	public function test_prefix_places_third_party_abilities( string $name, string $expected ): void {
		$this->assertSame( $expected, $this->tagged( array(), $name ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function provide_prefixed_abilities(): array {
		return array(
			'ACF field group'   => array( 'acf/register-field-group', 'acf' ),
			'ACF post types'    => array( 'acf/custom-post-types', 'acf' ),
			'Rank Math schema'  => array( 'rank-math/get-post-schema', 'rank-math' ),
			'Rank Math redirects' => array( 'rank-math/get-redirections', 'rank-math' ),
		);
	}

	/**
	 * Individually placed abilities beat their prefix.
	 *
	 * `core/*` in particular: a prefix rule would create a `core` group, and Feature 101 retired that
	 * deliberately after it became a 105-ability catch-all. WordPress core is the only thing placed
	 * this way — a third-party ability with no toolset goes to the catch-all, not into one of ours.
	 *
	 * @param  string $name     Ability name.
	 * @param  string $expected Group.
	 * @return void
	 *
	 * @dataProvider provide_individually_placed
	 */
	public function test_per_ability_placement_wins( string $name, string $expected ): void {
		$this->assertSame( $expected, $this->tagged( array(), $name ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function provide_individually_placed(): array {
		return array(
			'site info'   => array( 'core/get-site-info', 'diagnostics' ),
			'environment' => array( 'core/get-environment-info', 'diagnostics' ),
			'user info'   => array( 'core/get-user-info', 'users' ),
		);
	}

	/**
	 * No `core` group is ever created.
	 *
	 * @return void
	 */
	public function test_the_retired_core_group_is_never_recreated(): void {
		$this->assertNotSame( 'core', $this->tagged( array(), 'core/get-site-info' ) );
	}

	/**
	 * Anything unrecognised falls to the catch-all rather than vanishing.
	 *
	 * @return void
	 */
	public function test_an_unmapped_ability_falls_to_the_catch_all(): void {
		$this->assertSame(
			AcrossAI_Toolset_Integrations::CATCH_ALL_GROUP,
			$this->tagged( array(), 'some-plugin/do-a-thing' )
		);
	}

	/**
	 * A third-party ability is never absorbed into one of this plugin's curated groups.
	 *
	 * MCP Tracker's single ability is the live example. Filing it under Diagnostics would read as
	 * though this plugin provided it, and would start Diagnostics down the road `core` took — a
	 * curated group slowly becoming the place unclassified things land.
	 *
	 * @return void
	 */
	public function test_a_third_party_ability_is_not_absorbed_into_a_curated_group(): void {
		$this->assertSame(
			AcrossAI_Toolset_Integrations::CATCH_ALL_GROUP,
			$this->tagged( array(), 'mcp-tracker/get-activity' )
		);
	}

	/**
	 * Malformed input is returned as it arrived.
	 *
	 * This runs on every ability registration on every request, so it must never be the thing that
	 * breaks one.
	 *
	 * @return void
	 */
	public function test_malformed_input_is_passed_through(): void {
		$this->assertSame( 'not-an-array', AcrossAI_Ability_Group_Tagger::tag( 'not-an-array', 'x/y' ) );
		$this->assertSame( array(), AcrossAI_Ability_Group_Tagger::tag( array(), '' ) );

		// A slug with no namespace separator: treated as its own prefix, matches nothing, catch-all.
		$this->assertSame(
			AcrossAI_Toolset_Integrations::CATCH_ALL_GROUP,
			$this->tagged( array(), 'nonamespace' )
		);

		// A non-array `meta.acrossai` must not fatal, and must not be trusted as a declared group.
		$this->assertSame(
			AcrossAI_Toolset_Integrations::CATCH_ALL_GROUP,
			$this->tagged( array( 'meta' => array( 'acrossai' => 'garbage' ) ), 'x/y' )
		);
	}

	/**
	 * Existing meta is preserved, not replaced.
	 *
	 * @return void
	 */
	public function test_other_meta_survives_tagging(): void {
		$args = array(
			'label' => 'Keep me',
			'meta'  => array(
				'show_in_rest' => true,
				'acrossai'     => array( 'sub_group' => 'acf-fields' ),
			),
		);

		$out = AcrossAI_Ability_Group_Tagger::tag( $args, 'acf/field-groups' );

		$this->assertSame( 'acf', $out['meta']['acrossai']['tab_group'] );
		$this->assertSame( 'acf-fields', $out['meta']['acrossai']['sub_group'] );
		$this->assertTrue( $out['meta']['show_in_rest'] );
		$this->assertSame( 'Keep me', $out['label'] );
	}

	/**
	 * A site can place an ability without touching this class.
	 *
	 * @return void
	 */
	public function test_the_map_filter_can_place_an_ability(): void {
		$GLOBALS['acrossai_test_filter_values']['acrossai_ability_group_tagger_map'] = array(
			'acf/register-field-group' => 'configuration',
		);

		$this->assertSame( 'configuration', $this->tagged( array(), 'acf/register-field-group' ) );
	}

	/**
	 * The sub-group and label the tagger would write into the args.
	 *
	 * @param  array<string, mixed> $args Registration args.
	 * @param  string               $name Ability name.
	 * @return array{0: string|null, 1: string|null}
	 */
	private function tagged_sub( array $args, string $name ): array {
		$out = AcrossAI_Ability_Group_Tagger::tag( $args, $name );

		return array(
			$out['meta']['acrossai']['sub_group'] ?? null,
			$out['meta']['acrossai']['sub_group_label'] ?? null,
		);
	}

	/**
	 * A foreign ability gets a sub-group from its integration's rules, with the declared label.
	 *
	 * Without this the ability reports `sub_group: ""` and is unreachable through
	 * `action=discover&sub_group=…` — the gap #255 left behind once the toolset itself was fixed.
	 *
	 * @return void
	 */
	public function test_sub_group_is_filled_from_the_integration_rules(): void {
		list( $sub, $label ) = $this->tagged_sub( array(), 'elementor/manage-classes' );

		$this->assertSame( 'elementor-kits', $sub );
		$this->assertSame( 'Kits & Global Styles', $label );
	}

	/**
	 * A declared sub-group is never overwritten.
	 *
	 * The same rule the group half has always had. Our own 81 Elementor abilities declare theirs and
	 * must pass through untouched while Elementor's 25 get placed.
	 *
	 * @return void
	 */
	public function test_a_declared_sub_group_is_never_overwritten(): void {
		$args = array(
			'meta' => array(
				'acrossai' => array(
					'tab_group'       => 'elementor',
					'sub_group'       => 'elementor-system',
					'sub_group_label' => 'Keep Me',
				),
			),
		);

		$this->assertSame( array( 'elementor-system', 'Keep Me' ), $this->tagged_sub( $args, 'elementor/manage-classes' ) );
	}

	/**
	 * Gap-fill is per key: a declared group does not block a missing sub-group.
	 *
	 * Before sub-groups existed the tagger returned early on any declared group. Keeping that would
	 * have left an ability that declares only `tab_group` unreachable by sub-group forever.
	 *
	 * @return void
	 */
	public function test_a_declared_group_still_gets_a_missing_sub_group(): void {
		$args = array( 'meta' => array( 'acrossai' => array( 'tab_group' => 'elementor' ) ) );

		list( $sub ) = $this->tagged_sub( $args, 'elementor/manage-classes' );

		$this->assertSame( 'elementor-kits', $sub );
		$this->assertSame( 'elementor', $this->tagged( $args, 'elementor/manage-classes' ) );
	}

	/**
	 * An ability no rule recognises still lands somewhere.
	 *
	 * This is the point of the whole mechanism. Elementor shipped 27 abilities in 4.3 and will ship
	 * more; a name this plugin has never seen must be placed without a code change here, or the next
	 * release recreates #255 one level down.
	 *
	 * @return void
	 */
	public function test_an_unrecognised_ability_falls_to_the_default_sub_group(): void {
		list( $sub ) = $this->tagged_sub( array(), 'elementor/zzz-not-written-yet' );

		$this->assertSame( 'elementor-guidance', $sub );
	}

	/**
	 * Rule order decides, and the resource rule is first.
	 *
	 * `global-classes-resource` matches both the resource rule and the global-design keywords. It is
	 * read-only context rather than the write path, so it belongs with the other resources — which
	 * only holds while the resource rule stays above the keyword rule.
	 *
	 * @return void
	 */
	public function test_the_resource_rule_beats_a_later_keyword_rule(): void {
		$args = array( 'meta' => array( 'mcp' => array( 'type' => 'resource' ) ) );

		list( $sub ) = $this->tagged_sub( $args, 'elementor/global-classes-resource' );

		$this->assertSame( 'elementor-guidance', $sub );

		// The same name as a tool, not a resource, takes the keyword rule instead.
		list( $as_tool ) = $this->tagged_sub( array(), 'elementor/global-classes-resource' );

		$this->assertSame( 'elementor-kits', $as_tool );
	}

	/**
	 * The catch-all never assigns a sub-group.
	 *
	 * A plugin with no toolset of its own is left in `other` on purpose — see the `ABILITY_GROUPS`
	 * docblock. Giving its abilities one of our sub-groups would imply we own them.
	 *
	 * @return void
	 */
	public function test_the_catch_all_never_assigns_a_sub_group(): void {
		$this->assertSame( array( null, null ), $this->tagged_sub( array(), 'some-unknown-plugin/do-a-thing' ) );
		$this->assertSame( 'other', $this->tagged( array(), 'some-unknown-plugin/do-a-thing' ) );
	}

	/**
	 * A dispatcher gets no sub-group either.
	 *
	 * @return void
	 */
	public function test_a_dispatcher_is_not_given_a_sub_group(): void {
		$args = array( 'meta' => array( 'acrossai' => array( 'toolset' => true ) ) );

		$this->assertSame( array( null, null ), $this->tagged_sub( $args, 'toolset/elementor' ) );
	}

	/**
	 * An integration that declares no rules leaves the key unset.
	 *
	 * Most integrations do not implement the sub-group interface, and must keep behaving exactly as
	 * they did before it existed.
	 *
	 * @return void
	 */
	public function test_an_integration_without_rules_assigns_no_sub_group(): void {
		$this->assertSame( array( null, null ), $this->tagged_sub( array(), 'rank-math/get-settings' ) );
		$this->assertSame( 'rank-math', $this->tagged( array(), 'rank-math/get-settings' ) );
	}

	/**
	 * A sibling plugin can correct one placement without owning the descriptor.
	 *
	 * @return void
	 */
	public function test_the_sub_group_filter_can_place_an_ability(): void {
		$GLOBALS['acrossai_test_filter_values']['acrossai_ability_sub_group_tagger'] = 'elementor-system';

		list( $sub ) = $this->tagged_sub( array(), 'elementor/manage-classes' );

		$this->assertSame( 'elementor-system', $sub );
	}

	/**
	 * An ability placed by the fallback is recorded; one placed by a rule is not.
	 *
	 * This is what `diagnostics/list-unclassified-abilities` reads, and the only signal that a host
	 * plugin has shipped an ability no rule recognises. It earned itself immediately: on the first
	 * run against Elementor 4.3 it named `list-resources`, `read-resource` and `list-posts`, which
	 * the rules then grew to cover.
	 *
	 * @return void
	 */
	public function test_only_fallback_placements_are_recorded(): void {
		AcrossAI_Ability_Group_Tagger::reset();

		// Matches rule 2 on the `variable` keyword.
		AcrossAI_Ability_Group_Tagger::tag( array(), 'elementor/manage-global-variable' );

		$this->assertSame( array(), AcrossAI_Ability_Group_Tagger::defaulted(), 'A rule match must not be recorded as a fallback.' );

		AcrossAI_Ability_Group_Tagger::tag( array(), 'elementor/zzz-not-written-yet' );

		$this->assertSame(
			array( 'elementor/zzz-not-written-yet' => 'elementor' ),
			AcrossAI_Ability_Group_Tagger::defaulted()
		);

		AcrossAI_Ability_Group_Tagger::reset();

		$this->assertSame( array(), AcrossAI_Ability_Group_Tagger::defaulted() );
	}

	/**
	 * An ability left in the catch-all is not recorded as a fallback placement.
	 *
	 * The two are different findings: `other` is where a plugin with no toolset belongs, while a
	 * fallback placement means an integration owns the ability but nobody classified it. Conflating
	 * them would bury the second under 70 rows of the first.
	 *
	 * @return void
	 */
	public function test_a_catch_all_ability_is_not_recorded_as_a_fallback(): void {
		AcrossAI_Ability_Group_Tagger::reset();

		AcrossAI_Ability_Group_Tagger::tag( array(), 'some-unknown-plugin/do-a-thing' );

		$this->assertSame( array(), AcrossAI_Ability_Group_Tagger::defaulted() );
	}
}
