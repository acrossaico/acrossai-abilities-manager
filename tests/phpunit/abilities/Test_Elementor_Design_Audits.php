<?php
/**
 * Feature 067 — coverage test for the 29 design-audit abilities.
 *
 * Rather than one file per audit (they share the Base_Audit_Ability
 * skeleton), this test iterates every audit class and asserts the
 * common invariants: file exists, extends the right base, declares
 * the expected slug + category, and — for destructive audits —
 * declares the right annotation.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.25
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Elementor_Design_Audits extends WP_UnitTestCase {

	/**
	 * @return array<int, array{class:string, slug:string, destructive:bool}>
	 */
	private static function audit_manifest(): array {
		return array(
			// Aggregators (evaluate-design + suggest-design-fixes are self-contained,
			// not Base_Audit_Ability-based — tested separately).
			array( 'class' => 'Score_Distinctiveness',              'slug' => 'score-distinctiveness',              'destructive' => false ),
			array( 'class' => 'Extract_Design_Tokens',              'slug' => 'extract-design-tokens',              'destructive' => false ),
			// 14 audit-* abilities.
			array( 'class' => 'Audit_Column_Alignment_Rhythm',      'slug' => 'audit-column-alignment-rhythm',      'destructive' => false ),
			array( 'class' => 'Audit_Column_Balance',               'slug' => 'audit-column-balance',               'destructive' => false ),
			array( 'class' => 'Audit_Column_Dominance',             'slug' => 'audit-column-dominance',             'destructive' => false ),
			array( 'class' => 'Audit_Column_Necessity',             'slug' => 'audit-column-necessity',             'destructive' => false ),
			array( 'class' => 'Audit_Column_Patterns',              'slug' => 'audit-column-patterns',              'destructive' => false ),
			array( 'class' => 'Audit_Composition_Rhythm',           'slug' => 'audit-composition-rhythm',           'destructive' => false ),
			array( 'class' => 'Audit_Emphasis_Drift',               'slug' => 'audit-emphasis-drift',               'destructive' => false ),
			array( 'class' => 'Audit_Generic_Component_Repetition', 'slug' => 'audit-generic-component-repetition', 'destructive' => false ),
			array( 'class' => 'Audit_Generic_Layout_Patterns',      'slug' => 'audit-generic-layout-patterns',      'destructive' => false ),
			array( 'class' => 'Audit_Layout_Mechanism_Fit',         'slug' => 'audit-layout-mechanism-fit',         'destructive' => false ),
			array( 'class' => 'Audit_Native_Widget_Opportunities',  'slug' => 'audit-native-widget-opportunities',  'destructive' => false ),
			array( 'class' => 'Audit_Section_Rivalry',              'slug' => 'audit-section-rivalry',              'destructive' => false ),
			array( 'class' => 'Audit_Separator_Discipline',         'slug' => 'audit-separator-discipline',         'destructive' => false ),
			array( 'class' => 'Audit_Surface_Overuse',              'slug' => 'audit-surface-overuse',              'destructive' => false ),
			// 7 subtree operations (destructive).
			array( 'class' => 'Apply_Text_Hierarchy',               'slug' => 'apply-text-hierarchy',               'destructive' => true ),
			array( 'class' => 'Enforce_Boundary_Coherence',         'slug' => 'enforce-boundary-coherence',         'destructive' => true ),
			array( 'class' => 'Fix_Visible_Gap_Rhythm',             'slug' => 'fix-visible-gap-rhythm',             'destructive' => true ),
			array( 'class' => 'Normalize_Responsive_Values',        'slug' => 'normalize-responsive-values',        'destructive' => true ),
			array( 'class' => 'Normalize_Section_Spacing_Rhythm',   'slug' => 'normalize-section-spacing-rhythm',   'destructive' => true ),
			array( 'class' => 'Reset_Negative_Margins_Subtree',     'slug' => 'reset-negative-margins-subtree',     'destructive' => true ),
			array( 'class' => 'Zero_Container_Padding_Subtree',     'slug' => 'zero-container-padding-subtree',     'destructive' => true ),
			// 4 copy/sync/convert helpers (destructive).
			array( 'class' => 'Copy_Lane_Settings',                 'slug' => 'copy-lane-settings',                 'destructive' => true ),
			array( 'class' => 'Copy_Row_Balance',                   'slug' => 'copy-row-balance',                   'destructive' => true ),
			array( 'class' => 'Image_Widget_To_Background_Container', 'slug' => 'image-widget-to-background-container', 'destructive' => true ),
			array( 'class' => 'Sync_Component_Variant',             'slug' => 'sync-component-variant',             'destructive' => true ),
		);
	}

	public function test_all_audit_files_exist(): void {
		$dir = dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/';
		foreach ( self::audit_manifest() as $audit ) {
			$path = $dir . $audit['class'] . '.php';
			$this->assertFileExists( $path, "Missing audit file: {$audit['class']}.php" );
		}
	}

	public function test_all_audits_extend_base(): void {
		$dir = dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/';
		foreach ( self::audit_manifest() as $audit ) {
			$src = (string) file_get_contents( $dir . $audit['class'] . '.php' );
			$this->assertStringContainsString( 'extends Base_Audit_Ability', $src, "{$audit['class']} does not extend Base_Audit_Ability" );
		}
	}

	public function test_all_audits_declare_expected_slug(): void {
		$dir = dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/';
		foreach ( self::audit_manifest() as $audit ) {
			$src = (string) file_get_contents( $dir . $audit['class'] . '.php' );
			$this->assertStringContainsString( "return '{$audit['slug']}';", $src, "{$audit['class']} does not declare slug {$audit['slug']}" );
		}
	}

	public function test_destructive_audits_override_is_destructive(): void {
		$dir = dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/';
		foreach ( self::audit_manifest() as $audit ) {
			if ( ! $audit['destructive'] ) {
				continue;
			}
			$src = (string) file_get_contents( $dir . $audit['class'] . '.php' );
			// Matched as a pattern rather than a literal one-liner: the implementations
			// use the house multi-line form, and this test is about the override
			// existing, not about how it is laid out.
			$this->assertMatchesRegularExpression(
				'/function is_destructive\(\): bool \{\s*return true;\s*\}/',
				$src,
				"{$audit['class']} should override is_destructive() to return true"
			);
		}
	}

	public function test_evaluate_design_is_self_contained(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Evaluate_Design.php'
		);
		$this->assertStringContainsString( "'elementor/evaluate-design'", $src );
		$this->assertStringContainsString( 'Design_Audit_Runner::run_all(', $src );
	}

	public function test_suggest_design_fixes_is_self_contained(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Suggest_Design_Fixes.php'
		);
		$this->assertStringContainsString( "'elementor/suggest-design-fixes'", $src );
		$this->assertStringContainsString( 'Design_Audit_Runner::run_all(', $src );
	}

	public function test_base_audit_ability_provides_shared_execute(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Base_Audit_Ability.php'
		);
		$this->assertStringContainsString( 'abstract class Base_Audit_Ability', $src );
		$this->assertStringContainsString( 'abstract protected function audit_slug(): string;', $src );
		$this->assertStringContainsString( 'abstract protected function analyze(', $src );
		$this->assertStringContainsString( 'assert_elementor_available()', $src );
	}

	/**
	 * Issue #243 — a skeleton audit must not be registered.
	 *
	 * Every one of the 27 returns a fabricated score with no findings, wrapped by the
	 * base class in "Ran audit: <slug>." and a claim to be grounded in Elementor's
	 * official documentation. Verified live: audit-generic-layout-patterns scored a
	 * page of four identical 50/50 sections 100/100. A confidently wrong answer is
	 * worse than a missing tool, so they stay out of the bootstrap until implemented.
	 */
	public function test_no_skeleton_audit_is_registered(): void {
		$bootstrap = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php'
		);

		$skeletons = 0;

		foreach ( self::audit_manifest() as $audit ) {
			$class = $audit['class'];
			$src   = (string) file_get_contents(
				dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/' . $class . '.php'
			);

			if ( ! str_contains( $src, 'Skeleton implementation' ) ) {
				continue;
			}

			++$skeletons;

			$this->assertStringNotContainsString(
				"new Elementor\\{$class}()",
				$bootstrap,
				"{$class} still declares itself a skeleton but is registered. Implement analyze() "
					. '(and a confirm gate if it mutates) before restoring its bootstrap line.'
			);
		}

		// All 27 are implemented now, so the loop above is empty. Asserted rather than
		// left implicit: if a skeleton is ever reintroduced this number moves and the
		// assertion inside the loop takes over.
		$this->assertSame( 0, $skeletons, 'A design audit reverted to a skeleton.' );
	}

	/**
	 * The converse, so re-enabling one cannot be half-done: anything registered must
	 * have stopped calling itself a skeleton.
	 */
	public function test_no_registered_audit_still_calls_itself_a_skeleton(): void {
		$bootstrap = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php'
		);

		$registered = 0;

		foreach ( self::audit_manifest() as $audit ) {
			$class = $audit['class'];

			if ( ! str_contains( $bootstrap, "new Elementor\\{$class}()" ) ) {
				continue;
			}

			++$registered;

			$src = (string) file_get_contents(
				dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/' . $class . '.php'
			);

			$this->assertStringNotContainsString(
				'Skeleton implementation',
				$src,
				"{$class} is registered but its description still says it is a skeleton."
			);
		}

		// All 27 are implemented and registered. Asserting the count as well as the
		// per-file check catches the other way this can go wrong: an audit silently
		// dropped from the bootstrap would leave the loop passing while the ability
		// quietly stopped existing.
		$this->assertSame(
			count( self::audit_manifest() ),
			$registered,
			'Every implemented design audit should be registered in the bootstrap.'
		);
	}

	/**
	 * Issue #243 — evaluate-design failed output validation on EVERY call.
	 *
	 * Design_Audit_Runner::run_all() returns subtree_id and audit_count; the output
	 * schema declared neither while setting additionalProperties:false, so the
	 * ability errored for every post on every site. It had never worked.
	 */
	public function test_evaluate_design_declares_everything_the_runner_returns(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Evaluate_Design.php'
		);

		// Scoped to the OUTPUT schema on purpose. Matching the whole file would pass on
		// keys that only appear in the INPUT schema — subtree_id is in both, which is
		// exactly how the original bug hid.
		$start = strpos( $src, "'output_schema'" );
		$this->assertNotFalse( $start, 'evaluate-design declares no output schema.' );

		// Bounded at 'meta', the next key in the spec. Reading to end of file would
		// sweep in execute(), where $input['subtree_id'] appears — and that is the
		// second way this test could pass while the schema is still wrong.
		$end = strpos( $src, "'meta'", (int) $start );
		$this->assertNotFalse( $end, 'evaluate-design declares no meta block to bound the schema at.' );

		$output = substr( $src, (int) $start, (int) $end - (int) $start );

		foreach ( array( 'post_id', 'subtree_id', 'audit_count', 'findings', 'recommendations', 'score', 'source_policy', 'guidance_basis', 'audits_run' ) as $key ) {
			$this->assertStringContainsString(
				"'{$key}'",
				$output,
				"evaluate-design's OUTPUT schema omits '{$key}', which run_all() returns — "
					. 'with additionalProperties:false that fails every call.'
			);
		}
	}

	/**
	 * Every key analyze() can return must be declared on the base output schema.
	 *
	 * Found live: audits returning 'extras' failed output validation because the schema
	 * sets additionalProperties:false and did not declare it — the same fault that made
	 * evaluate-design error on every call. Schema and payload drifting apart is the
	 * recurring failure in this suite, so it is pinned in both places.
	 */
	public function test_the_base_output_schema_declares_every_key_analyze_returns(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Base_Audit_Ability.php'
		);

		$start = strpos( $src, "'output_schema'" );
		$this->assertNotFalse( $start, 'Base_Audit_Ability declares no output schema.' );
		$end = strpos( $src, "'meta'", (int) $start );
		$this->assertNotFalse( $end );
		$schema = substr( $src, (int) $start, (int) $end - (int) $start );

		foreach ( array( 'findings', 'recommendations', 'score', 'extras', 'changed', 'applied', 'source_policy', 'guidance_basis', 'message', 'error_code' ) as $key ) {
			$this->assertStringContainsString(
				"'{$key}'",
				$schema,
				"Base_Audit_Ability's output schema omits '{$key}', which an audit can return."
			);
		}
	}

	/**
	 * The icon-list suggestion must count per LANE, not per row.
	 *
	 * Found by building a well-made page rather than a bad one: three icon-boxes spread
	 * one per lane across a feature row is the standard 3-up grid, and counting per row
	 * flagged it as an Icon List — which it is not, since Icon List is a vertical list
	 * inside a single lane. Advice that is wrong on correct work is how a tool teaches
	 * people to ignore it.
	 */
	public function test_icon_list_suggestion_counts_per_lane(): void {
		$src = (string) file_get_contents(
			dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/Audit_Native_Widget_Opportunities.php'
		);

		$this->assertStringContainsString(
			'$lane_types',
			$src,
			'The icon-list check must read each lane separately.'
		);
		$this->assertStringContainsString(
			"foreach ( \$lanes as \$lane )",
			$src,
			'The icon-list check must iterate lanes rather than summing across the row.'
		);
	}

	/**
	 * An empty audit registry must not be reported as a clean page.
	 */
	public function test_the_aggregators_say_when_no_audit_ran(): void {
		foreach ( array( 'Evaluate_Design', 'Suggest_Design_Fixes' ) as $class ) {
			$src = (string) file_get_contents(
				dirname( __DIR__, 3 ) . '/includes/Abilities/Elementor/' . $class . '.php'
			);

			$this->assertStringContainsString(
				"audit_count",
				$src,
				"{$class} must branch on how many audits actually ran."
			);
			$this->assertStringContainsString(
				'No Elementor design audits are registered',
				$src,
				"{$class} must say plainly that nothing was inspected when nothing ran."
			);
		}
	}

	public function test_manifest_covers_all_27_base_derived_audits_plus_2_aggregators(): void {
		$this->assertCount( 27, self::audit_manifest(), 'Manifest should list 27 Base_Audit_Ability subclasses (plus 2 self-contained aggregators = 29 total).' );
	}
}
