<?php
/**
 * Issue #184 — every group that can exist has a dispatcher.
 *
 * The two halves of a toolset are built by different mechanisms that nothing reconciles. Groups are
 * *derived*: `AcrossAI_Ability_Group::resolve()` creates one the first time any ability claims it.
 * Dispatchers are *declared*: a hardcoded list of `new` calls in
 * `AcrossAI_Core_Abilities_Bootstrap::register_toolsets()`.
 *
 * A group with no dispatcher is not an error anywhere. It gets an admin tab, a count and a working
 * REST filter, and is simply unreachable over MCP — and `Base_Toolset_Ability` will then tell a caller
 * that an ability "belongs to the X group, use that tool instead", naming a tool that was never
 * created. Nothing logs it, no test fails, and the admin screen looks correct.
 *
 * This suite is that missing reconciliation. It is the reason adding an integration is now safe: the
 * declaration alone is enough, and forgetting the dispatcher half fails here instead of shipping.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.35
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\AcrossAI_Toolset_Integrations;

require_once dirname( __DIR__, 4 ) . '/includes/Abilities/Integrations/AcrossAI_Toolset_Integration.php';
require_once dirname( __DIR__, 4 ) . '/includes/Abilities/Integrations/AcrossAI_Catch_All_Integration.php';
require_once dirname( __DIR__, 4 ) . '/includes/Abilities/Integrations/Rank_Math.php';
require_once dirname( __DIR__, 4 ) . '/includes/Abilities/Integrations/Contact_Form_7.php';
require_once dirname( __DIR__, 4 ) . '/includes/Abilities/Integrations/LiteSpeed_Cache.php';
require_once dirname( __DIR__, 4 ) . '/includes/Abilities/Integrations/AcrossAI_Toolset_Integrations.php';
require_once dirname( __DIR__, 4 ) . '/includes/Utilities/AcrossAI_Ability_Group_Tagger.php';

/**
 * Every producible group is served by a dispatcher.
 */
class Test_Toolset_Group_Coverage extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		AcrossAI_Toolset_Integrations::flush();
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		AcrossAI_Toolset_Integrations::flush();

		parent::tearDown();
	}

	/**
	 * Groups served by a hand-written `Toolset/` class.
	 *
	 * Read from source rather than by instantiating: the dispatchers hook WordPress in their
	 * constructors, and this suite has no WordPress.
	 *
	 * @return array<string, string> group => class file basename.
	 */
	private function hand_written_groups(): array {
		$dir    = dirname( __DIR__, 4 ) . '/includes/Abilities/Toolset';
		$groups = array();

		foreach ( (array) glob( $dir . '/*.php' ) as $file ) {
			$base = basename( (string) $file );

			if ( in_array( $base, array( 'Base_Toolset_Ability.php', 'Category_Registrar.php', 'Integration_Toolset.php' ), true ) ) {
				continue;
			}

			$source = (string) file_get_contents( (string) $file );

			if ( 1 === preg_match( "/function group\(\): string\s*\{\s*return '([^']+)'/", $source, $m ) ) {
				$groups[ $m[1] ] = $base;
			}
		}

		return $groups;
	}

	/**
	 * The bootstrap source.
	 *
	 * @return string
	 */
	private function bootstrap_source(): string {
		return (string) file_get_contents(
			dirname( __DIR__, 4 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php'
		);
	}

	/**
	 * Groups the bootstrap generates a dispatcher for.
	 *
	 * Deriving this as "every integration group minus the hand-written ones" would be a tautology —
	 * it would assert the arithmetic rather than the behaviour, and pass even with the generation
	 * loop deleted. (It did: that was the first version of this suite.) So generation is confirmed
	 * from the source first, and only then is the set computed.
	 *
	 * @return string[]
	 */
	private function generated_groups(): array {
		if ( ! $this->bootstrap_generates_dispatchers() ) {
			return array();
		}

		$hand = array_keys( $this->hand_written_groups() );

		return array_values(
			array_diff( array_keys( AcrossAI_Toolset_Integrations::all() ), $hand )
		);
	}

	/**
	 * Whether the bootstrap still creates a dispatcher per unclaimed integration.
	 *
	 * @return bool
	 */
	private function bootstrap_generates_dispatchers(): bool {
		$source = $this->bootstrap_source();

		return 1 === preg_match( '/register_integration_toolsets\(/', $source )
			&& 1 === preg_match(
				'/foreach \(\s*Integrations\\\\AcrossAI_Toolset_Integrations::all\(\).*?new Toolset\\\\Integration_Toolset\(/s',
				$source
			);
	}

	/**
	 * The generation loop itself must exist.
	 *
	 * Everything else here reasons about which groups are covered; this is the one assertion that
	 * fails if the mechanism doing the covering is removed.
	 *
	 * @return void
	 */
	public function test_the_bootstrap_still_generates_integration_dispatchers(): void {
		$this->assertTrue(
			$this->bootstrap_generates_dispatchers(),
			'register_toolsets() must loop the integration registry and construct an Integration_Toolset '
				. 'for each unclaimed group, or integrations get a tab and no MCP tool.'
		);
	}

	/**
	 * Every group an integration declares is dispatchable.
	 *
	 * @return void
	 */
	public function test_every_integration_group_has_a_dispatcher(): void {
		$hand      = $this->hand_written_groups();
		$generated = $this->generated_groups();

		foreach ( array_keys( AcrossAI_Toolset_Integrations::all() ) as $group ) {
			$this->assertTrue(
				isset( $hand[ $group ] ) || in_array( $group, $generated, true ),
				sprintf(
					'The "%s" group has no Toolset dispatcher, so its abilities get a tab but no MCP tool.',
					$group
				)
			);
		}
	}

	/**
	 * The catch-all is dispatchable too.
	 *
	 * It is empty on a fully-mapped site and its dispatcher then suppresses itself — but the moment
	 * some plugin registers something unmapped it must be reachable, which is the entire reason it
	 * exists.
	 *
	 * @return void
	 */
	public function test_the_catch_all_group_has_a_dispatcher(): void {
		$group = AcrossAI_Toolset_Integrations::CATCH_ALL_GROUP;

		$this->assertContains(
			$group,
			array_merge( array_keys( $this->hand_written_groups() ), $this->generated_groups() ),
			'Anything unmapped lands in the catch-all, so it must be dispatchable.'
		);
	}

	/**
	 * No group is served twice.
	 *
	 * The bootstrap skips generating for a group a hand-written class already claims. If that skip
	 * broke, two dispatchers would register the same `toolset/{group}` slug and the second would lose
	 * the collision check — a silent half-registration.
	 *
	 * @return void
	 */
	public function test_no_group_is_served_by_two_dispatchers(): void {
		$overlap = array_intersect(
			array_keys( $this->hand_written_groups() ),
			$this->generated_groups()
		);

		$this->assertSame( array(), $overlap );
	}

	/**
	 * The bootstrap's `$claimed` list matches the classes on disk.
	 *
	 * That list is hand-maintained. If a dispatcher file is added or removed without updating it, a
	 * group is either generated twice or not at all.
	 *
	 * @return void
	 */
	public function test_the_bootstrap_claimed_list_matches_the_dispatcher_files(): void {
		$source = $this->bootstrap_source();

		$this->assertSame(
			1,
			preg_match( '/register_integration_toolsets\(\s*array\((.*?)\)\s*\);/s', $source, $m ),
			'register_integration_toolsets() must be called with an inline array of claimed groups.'
		);

		preg_match_all( "/'([^']+)'/", $m[1], $found );

		$claimed = $found[1];
		$on_disk = array_keys( $this->hand_written_groups() );

		sort( $claimed );
		sort( $on_disk );

		$this->assertSame(
			$on_disk,
			$claimed,
			'Every hand-written dispatcher must appear in the claimed list, and nothing else.'
		);
	}
}
