<?php
/**
 * Feature 102 — the translation rules for the retired registration gate.
 *
 * These tests are the only control on this translation's correctness. It runs once, unattended,
 * reports nothing (spec clarification Q1) and is never retried (Q2), so a mis-translation is
 * silent and permanent: abilities an operator switched off would become reachable with nothing
 * anywhere indicating it. That is why the decision logic is injectable and tested here rather than
 * left to an integration run.
 *
 * Scope: `build_plan()` only — the pure decision step. Persistence (never overwriting an explicit
 * override, idempotency, the atomic claim) needs a database and lives in the wp-env suite.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.34
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Library_Gate_Migration;

require_once dirname( __DIR__, 4 ) . '/includes/Modules/Abilities/AcrossAI_Library_Gate_Migration.php';

/**
 * Covers AcrossAI_Library_Gate_Migration::build_plan().
 */
class Test_Library_Gate_Migration_Rules extends TestCase {

	/**
	 * Build a definition row.
	 *
	 * @param  string $category Card category.
	 * @param  string $sub_key  Sub-key within the category (what sub_keys is keyed by).
	 * @param  string $name     Full ability name (what an override row is keyed by).
	 * @param  string $variant  Optional card_variant.
	 * @return array<string, string>
	 */
	private function def( string $category, string $sub_key, string $name, string $variant = '' ): array {
		$row = array(
			'category' => $category,
			'slug'      => $sub_key,
			'name'      => $name,
		);

		if ( '' !== $variant ) {
			$row['card_variant'] = $variant;
		}

		return $row;
	}

	/**
	 * Run the rules.
	 *
	 * @param  array<string, mixed>             $config Config under test.
	 * @param  array<int, array<string, mixed>> $defs   Definitions under test.
	 * @return array{block: array<int, string>, integrations: array<string, bool>}
	 */
	private function plan( array $config, array $defs ): array {
		return AcrossAI_Library_Gate_Migration::instance()->build_plan( $config, $defs );
	}

	/**
	 * A switched-off category blocks every ability in it.
	 *
	 * @return void
	 */
	public function test_disabled_category_blocks_all_its_abilities(): void {
		$plan = $this->plan(
			array( 'acrossai-database' => array( 'enabled' => false, 'mode' => 'all' ) ),
			array(
				$this->def( 'acrossai-database', 'run-select-query', 'database/run-select-query' ),
				$this->def( 'acrossai-database', 'insert-row', 'database/insert-row' ),
				$this->def( 'acrossai-cache', 'flush-transients', 'cache/flush-transients' ),
			)
		);

		$this->assertSame(
			array( 'database/run-select-query', 'database/insert-row' ),
			$plan['block'],
			'Every ability in a switched-off category must be blocked, and only those.'
		);
	}

	/**
	 * Specific mode blocks exactly the unticked abilities.
	 *
	 * @return void
	 */
	public function test_specific_mode_blocks_only_unticked_abilities(): void {
		$plan = $this->plan(
			array(
				'acrossai-content' => array(
					'enabled'  => true,
					'mode'     => 'specific',
					'sub_keys' => array( 'get-post' => true, 'delete-post' => false ),
				),
			),
			array(
				$this->def( 'acrossai-content', 'get-post', 'content/get-post' ),
				$this->def( 'acrossai-content', 'delete-post', 'content/delete-post' ),
				$this->def( 'acrossai-content', 'update-post', 'content/update-post' ),
			)
		);

		$this->assertSame(
			array( 'content/delete-post', 'content/update-post' ),
			$plan['block'],
			'An explicit false and an absent sub-key must both count as unticked.'
		);
	}

	/**
	 * A category absent from the config was permitted, so nothing is written.
	 *
	 * @return void
	 */
	public function test_absent_category_is_left_alone(): void {
		$plan = $this->plan(
			array( 'acrossai-content' => array( 'enabled' => true, 'mode' => 'all' ) ),
			array( $this->def( 'acrossai-cache', 'flush-transients', 'cache/flush-transients' ) )
		);

		$this->assertSame( array(), $plan['block'] );
	}

	/**
	 * An enabled all-mode category is left alone.
	 *
	 * @return void
	 */
	public function test_enabled_all_mode_category_is_left_alone(): void {
		$plan = $this->plan(
			array( 'acrossai-content' => array( 'enabled' => true, 'mode' => 'all' ) ),
			array( $this->def( 'acrossai-content', 'get-post', 'content/get-post' ) )
		);

		$this->assertSame( array(), $plan['block'] );
	}

	/**
	 * A first-party entry with no `enabled` flag defaults to permitted.
	 *
	 * This is the first half of the opposite-defaults hazard: for a category, absent means allowed.
	 *
	 * @return void
	 */
	public function test_first_party_entry_without_enabled_flag_defaults_to_permitted(): void {
		$plan = $this->plan(
			array( 'acrossai-content' => array( 'mode' => 'all' ) ),
			array( $this->def( 'acrossai-content', 'get-post', 'content/get-post' ) )
		);

		$this->assertSame( array(), $plan['block'] );
	}

	/**
	 * Integration rows are carried across, never converted to blocks.
	 *
	 * Blocking them would be meaningless: without the opt-in the third-party plugin registers
	 * nothing for an override to act on.
	 *
	 * @return void
	 */
	public function test_integration_rows_are_carried_not_blocked(): void {
		$plan = $this->plan(
			array( 'acf' => array( 'enabled' => true, 'mode' => 'all' ) ),
			array(
				$this->def( 'acf', 'field-groups', 'acf/field-groups', 'integration' ),
				$this->def( 'acf', 'post-types', 'acf/post-types', 'integration' ),
			)
		);

		$this->assertSame( array(), $plan['block'], 'Integration rows must never be blocked.' );
		$this->assertSame( array( 'acf' => true ), $plan['integrations'] );
	}

	/**
	 * An integration absent from the config is off.
	 *
	 * This is the other half of the opposite-defaults hazard: for an integration, absent means
	 * disabled. Reading the store with one uniform default mis-translates one of the two kinds.
	 *
	 * @return void
	 */
	public function test_absent_integration_defaults_to_disabled(): void {
		$plan = $this->plan(
			array( 'acrossai-content' => array( 'enabled' => true, 'mode' => 'all' ) ),
			array( $this->def( 'acf', 'field-groups', 'acf/field-groups', 'integration' ) )
		);

		$this->assertSame( array( 'acf' => false ), $plan['integrations'] );
		$this->assertSame( array(), $plan['block'] );
	}

	/**
	 * A disabled integration is carried across as disabled, not blocked.
	 *
	 * @return void
	 */
	public function test_disabled_integration_is_carried_as_false(): void {
		$plan = $this->plan(
			array( 'acf' => array( 'enabled' => false, 'mode' => 'all' ) ),
			array( $this->def( 'acf', 'field-groups', 'acf/field-groups', 'integration' ) )
		);

		$this->assertSame( array( 'acf' => false ), $plan['integrations'] );
		$this->assertSame( array(), $plan['block'] );
	}

	/**
	 * Category membership comes from the definitions, not from `sub_keys`.
	 *
	 * The retired store capped itself at 50 sub-keys, so its tick list is incomplete for any
	 * larger category. An ability missing from `sub_keys` because of truncation must be treated as
	 * unticked — failing toward blocking — never as allowed.
	 *
	 * @return void
	 */
	public function test_membership_comes_from_definitions_not_truncated_sub_keys(): void {
		$defs     = array();
		$sub_keys = array();

		for ( $i = 0; $i < 60; $i++ ) {
			$defs[] = $this->def( 'acrossai-block', "ability-$i", "blocks/ability-$i" );

			// Only the first 50 made it into storage, mirroring the retired MAX_SUB_KEYS cap.
			if ( $i < 50 ) {
				$sub_keys[ "ability-$i" ] = true;
			}
		}

		$plan = $this->plan(
			array(
				'acrossai-block' => array(
					'enabled'  => true,
					'mode'     => 'specific',
					'sub_keys' => $sub_keys,
				),
			),
			$defs
		);

		$this->assertCount( 10, $plan['block'], 'The 10 abilities lost to truncation must block, not slip through.' );
		$this->assertContains( 'blocks/ability-59', $plan['block'] );
		$this->assertNotContains( 'blocks/ability-0', $plan['block'] );
	}

	/**
	 * A truthy sub-key counts as ticked, exactly as the retired gate had it.
	 *
	 * The gate's own test was `isset( $entry['sub_keys'][ $slug ] ) && (bool) $value`, so 1 and
	 * 'yes' permitted an ability. SEC-04 would argue for a strict `true ===` here and it is
	 * deliberately not applied: FR-010 requires effective access to be identical across the
	 * upgrade, and tightening the rule would silently block abilities that used to work.
	 *
	 * Falsy values must still block — that half is what stops a truncated or half-written store
	 * from being read as permissive.
	 *
	 * @return void
	 */
	public function test_truthy_sub_key_counts_as_ticked_matching_the_retired_gate(): void {
		$plan = $this->plan(
			array(
				'acrossai-content' => array(
					'enabled'  => true,
					'mode'     => 'specific',
					'sub_keys' => array( 'a' => 1, 'b' => 'yes', 'c' => true, 'd' => 0, 'e' => '' ),
				),
			),
			array(
				$this->def( 'acrossai-content', 'a', 'content/a' ),
				$this->def( 'acrossai-content', 'b', 'content/b' ),
				$this->def( 'acrossai-content', 'c', 'content/c' ),
				$this->def( 'acrossai-content', 'd', 'content/d' ),
				$this->def( 'acrossai-content', 'e', 'content/e' ),
			)
		);

		// Truthy: permitted by the gate, so permitted by the translation.
		$this->assertNotContains( 'content/a', $plan['block'] );
		$this->assertNotContains( 'content/b', $plan['block'] );
		$this->assertNotContains( 'content/c', $plan['block'] );

		// Falsy: blocked by the gate, so blocked by the translation.
		$this->assertContains( 'content/d', $plan['block'] );
		$this->assertContains( 'content/e', $plan['block'] );
	}

	/**
	 * Malformed rows are skipped rather than producing a bogus override key.
	 *
	 * @return void
	 */
	public function test_rows_without_a_name_or_category_are_skipped(): void {
		$plan = $this->plan(
			array( 'acrossai-content' => array( 'enabled' => false ) ),
			array(
				array( 'category' => 'acrossai-content', 'slug' => 'x' ),
				array( 'name' => 'content/y', 'slug' => 'y' ),
				$this->def( 'acrossai-content', 'z', 'content/z' ),
			)
		);

		$this->assertSame( array( 'content/z' ), $plan['block'] );
	}

	/**
	 * The plan never repeats an ability.
	 *
	 * @return void
	 */
	public function test_plan_is_deduplicated(): void {
		$plan = $this->plan(
			array( 'acrossai-content' => array( 'enabled' => false ) ),
			array(
				$this->def( 'acrossai-content', 'get-post', 'content/get-post' ),
				$this->def( 'acrossai-content', 'get-post', 'content/get-post' ),
			)
		);

		$this->assertSame( array( 'content/get-post' ), $plan['block'] );
	}

	/**
	 * An empty config produces an empty plan.
	 *
	 * @return void
	 */
	public function test_empty_config_blocks_nothing(): void {
		$plan = $this->plan(
			array(),
			array( $this->def( 'acrossai-content', 'get-post', 'content/get-post' ) )
		);

		$this->assertSame( array(), $plan['block'] );
		$this->assertSame( array(), $plan['integrations'] );
	}

	/**
	 * FR-008 — an explicit site-access decision is never overwritten.
	 *
	 * The persistence proof needs a database and runs under wp-env only, so the rule itself is
	 * asserted here, in the suite that actually gates CI. The distinction that matters: a row
	 * existing is not a decision — only a non-null `site_allowed` is. A row carrying just an MCP
	 * or user-access override must still be blocked, or those abilities stay reachable after the
	 * upgrade (BUG-SPARSE-STORAGE-UNIFORM-DEFAULT-ASSUMPTION is the same family of mistake).
	 *
	 * @param  object|null $existing Row under test.
	 * @param  bool        $expected Whether the migration must leave it alone.
	 * @return void
	 *
	 * @dataProvider provide_existing_rows
	 */
	public function test_explicit_site_access_is_never_overwritten( ?object $existing, bool $expected ): void {
		$this->assertSame(
			$expected,
			AcrossAI_Library_Gate_Migration::is_already_decided( $existing )
		);
	}

	/**
	 * Override-row shapes and whether they shield the ability.
	 *
	 * @return array<string, array{0: object|null, 1: bool}>
	 */
	public static function provide_existing_rows(): array {
		$row = static function ( $site_allowed ): object {
			$o               = new \stdClass();
			$o->site_allowed = $site_allowed;

			return $o;
		};

		return array(
			'no row at all'                  => array( null, false ),
			'row with no site-access set'    => array( $row( null ), false ),
			'explicit Force Block'           => array( $row( false ), true ),
			'explicit Force Allow'           => array( $row( true ), true ),
			'Force Block as stored int 0'    => array( $row( 0 ), true ),
			'Force Allow as stored int 1'    => array( $row( 1 ), true ),
			'Force Block as stored string'   => array( $row( '0' ), true ),
		);
	}
}
