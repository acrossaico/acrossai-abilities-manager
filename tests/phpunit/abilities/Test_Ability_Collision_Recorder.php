<?php
/**
 * Issue #202 — a name clash between two plugins must stop being silent.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.44
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Ability_Collision_Recorder;
use WP_UnitTestCase;

class Test_Ability_Collision_Recorder extends WP_UnitTestCase {

	public function tear_down(): void {
		Ability_Collision_Recorder::reset();
		parent::tear_down();
	}

	private static function util(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Ability_Collision_Recorder.php';
	}

	private static function ability_file(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Recovery/List_Ability_Collisions.php';
	}

	private static function read( string $path ): string {
		return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	}

	/**
	 * A single claim is not a collision.
	 */
	public function test_one_registration_is_not_reported(): void {
		Ability_Collision_Recorder::reset();
		Ability_Collision_Recorder::record( array( 'label' => 'Only One' ), 'demo/only-one' );

		$this->assertSame( array(), Ability_Collision_Recorder::collisions() );
		$this->assertSame( 1, Ability_Collision_Recorder::attempted_count() );
	}

	/**
	 * Two claims on one name are reported, with the losing label named.
	 */
	public function test_a_contested_name_is_reported(): void {
		Ability_Collision_Recorder::reset();
		Ability_Collision_Recorder::record( array( 'label' => 'Theirs' ), 'demo/contested' );
		Ability_Collision_Recorder::record( array( 'label' => 'Ours' ), 'demo/contested' );

		$rows = Ability_Collision_Recorder::collisions();

		$this->assertCount( 1, $rows );
		$this->assertSame( 'demo/contested', $rows[0]['ability'] );
		$this->assertSame( 2, $rows[0]['attempts'] );
		$this->assertContains( 'Theirs', $rows[0]['losers'] );
		$this->assertContains( 'Ours', $rows[0]['losers'] );
	}

	/**
	 * Identical labels must not look like "no loser".
	 *
	 * Measured live on acf/register-custom-post-type, where our placeholder label matched ACF's
	 * exactly. Reporting an empty loser list on a contested name reads as "two attempts, nothing
	 * discarded", which is the opposite of what happened.
	 */
	public function test_identical_labels_are_explained_rather_than_shown_as_no_loser(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'The Abilities API is not loaded.' );
		}

		// The identical-label path only exists once a winner is actually registered; without one
		// there is no label to compare against and every attempt is a loser by default.
		wp_register_ability(
			'demo/twins',
			array(
				'label'               => 'Same Name',
				'description'         => 'Fixture.',
				'category'            => 'acrossai-recovery',
				'execute_callback'    => static function () {
					return array();
				},
				'permission_callback' => '__return_false',
			)
		);

		Ability_Collision_Recorder::reset();
		Ability_Collision_Recorder::record( array( 'label' => 'Same Name' ), 'demo/twins' );
		Ability_Collision_Recorder::record( array( 'label' => 'Same Name' ), 'demo/twins' );

		$rows = Ability_Collision_Recorder::collisions();

		$this->assertCount( 1, $rows );
		$this->assertSame( array(), $rows[0]['losers'] );
		$this->assertArrayHasKey(
			'note',
			$rows[0],
			'A contested name with no distinguishable loser must say so, not imply nothing was lost.'
		);

		if ( function_exists( 'wp_unregister_ability' ) ) {
			wp_unregister_ability( 'demo/twins' );
		}
	}

	/**
	 * A contested name that nothing holds must not read as a fight nobody won.
	 *
	 * Real scenario: a per-ability Force Block unregisters the ability at
	 * wp_abilities_api_init P100001, after both registrations were attempted. Without a winner to
	 * compare against, every label lands in the loser list.
	 */
	public function test_a_contested_name_with_no_winner_is_explained(): void {
		Ability_Collision_Recorder::reset();
		Ability_Collision_Recorder::record( array( 'label' => 'A' ), 'demo/gone' );
		Ability_Collision_Recorder::record( array( 'label' => 'B' ), 'demo/gone' );

		$rows = Ability_Collision_Recorder::collisions();

		$this->assertCount( 1, $rows );
		$this->assertSame( '', $rows[0]['winner'] );
		$this->assertArrayHasKey( 'note', $rows[0] );
		$this->assertStringContainsString( 'Force Block', $rows[0]['note'] );
	}

	/**
	 * Recording never alters the args it inspects.
	 */
	public function test_the_recorder_is_a_pass_through(): void {
		$args = array( 'label' => 'Untouched', 'category' => 'demo' );

		$this->assertSame( $args, Ability_Collision_Recorder::record( $args, 'demo/pass-through' ) );
	}

	/**
	 * It must hook before the duplicate check, and before our own arg rewriting.
	 *
	 * `wp_register_ability_args` fires for every attempt and ahead of the registry's duplicate
	 * refusal, which is the only point where both winner and loser are visible. Priority 1 also puts
	 * it ahead of the override processor at P100000, which may legitimately change a label.
	 */
	public function test_it_records_at_the_only_point_both_sides_are_visible(): void {
		$src = self::read( self::util() );

		$this->assertStringContainsString( "add_filter( 'wp_register_ability_args'", $src );
		$this->assertMatchesRegularExpression(
			"/'wp_register_ability_args',\s*array\( __CLASS__, 'record' \),\s*1,\s*2/",
			$src,
			'Must record at priority 1.'
		);

		// #260 — the args filter alone cannot see a duplicate, because core bails before firing it.
		// The second listener is the only place a refused registration is reported.
		$this->assertMatchesRegularExpression(
			"/'doing_it_wrong_run',\s*array\( __CLASS__, 'record_rejection' \),\s*1,\s*3/",
			$src,
			'Must also listen for refused registrations at priority 1.'
		);
	}

	/**
	 * A refused duplicate is reported, and the plugin that lost is named.
	 *
	 * This is the case #260 was filed for: with its MCP exposure enabled, Elementor loses
	 * `elementor/create-page` to this plugin on every request. Before this the report said nothing
	 * about it while naming three unrelated rows.
	 *
	 * Driven through `record_refusal()` for the same reason the tests above drive `record()`
	 * directly — a backtrace cannot be synthesised, so the extraction is covered by the source
	 * assertions and the behaviour is covered here.
	 */
	public function test_a_refused_duplicate_is_reported_with_the_losing_plugin(): void {
		Ability_Collision_Recorder::reset();
		Ability_Collision_Recorder::record( array( 'label' => 'The Winner' ), 'demo/refused' );
		Ability_Collision_Recorder::record_refusal( 'demo/refused', 'The Loser', 'their-plugin' );

		$rows = Ability_Collision_Recorder::collisions();

		$this->assertCount( 1, $rows );
		$this->assertSame( 'demo/refused', $rows[0]['ability'] );
		$this->assertSame( 2, $rows[0]['attempts'], 'A refusal counts as an attempt; it is why the total was wrong before.' );
		$this->assertSame( 1, $rows[0]['refused'] );
		$this->assertContains( 'The Loser', $rows[0]['losers'] );
		$this->assertSame( array( 'their-plugin' ), $rows[0]['lost_by'] );
		$this->assertSame( 1, Ability_Collision_Recorder::refused_count() );
		$this->assertStringContainsString( 'refused a registration', (string) ( $rows[0]['note'] ?? '' ) );
	}

	/**
	 * Identical labels no longer hide the loser.
	 *
	 * Two plugins implementing the same ability usually label it the same way — Elementor and this
	 * plugin both say "Create Elementor Page" — so inference by label returned an empty loser list
	 * and a note apologising for it. A refusal is recorded as fact, so the row is definite now.
	 */
	public function test_a_refusal_identifies_the_loser_even_when_labels_match(): void {
		Ability_Collision_Recorder::reset();
		Ability_Collision_Recorder::record( array( 'label' => 'Same Label' ), 'demo/same-label' );
		Ability_Collision_Recorder::record_refusal( 'demo/same-label', 'Same Label', 'their-plugin' );

		$rows = Ability_Collision_Recorder::collisions();

		$this->assertCount( 1, $rows );
		$this->assertContains( 'Same Label', $rows[0]['losers'], 'The refused label is recorded as fact, not inferred by comparison.' );
		$this->assertSame( array( 'their-plugin' ), $rows[0]['lost_by'] );
	}

	/**
	 * A name only ever refused is still reported.
	 *
	 * Possible when the winner registered before the recorder attached. Keying the report off the
	 * args-filter attempts alone, as it did before, dropped these rows entirely.
	 *
	 * @return void
	 */
	public function test_a_name_seen_only_as_a_refusal_is_reported(): void {
		Ability_Collision_Recorder::reset();
		Ability_Collision_Recorder::record_refusal( 'demo/only-refused', 'Loser A', 'plugin-a' );
		Ability_Collision_Recorder::record_refusal( 'demo/only-refused', 'Loser B', 'plugin-b' );

		$rows = Ability_Collision_Recorder::collisions();

		$this->assertCount( 1, $rows );
		$this->assertSame( 2, $rows[0]['refused'] );
		$this->assertSame( array( 'plugin-a', 'plugin-b' ), $rows[0]['lost_by'] );
		$this->assertSame( 2, Ability_Collision_Recorder::refused_count() );
	}

	/**
	 * One refusal on its own is a collision; one plain attempt is not.
	 *
	 * A refusal always implies a second registrant — WordPress only refuses because something else
	 * holds the name — so unlike a lone attempt it is reportable by itself.
	 *
	 * @return void
	 */
	public function test_a_single_refusal_counts_as_contested(): void {
		Ability_Collision_Recorder::reset();
		Ability_Collision_Recorder::record( array( 'label' => 'Alone' ), 'demo/alone' );
		Ability_Collision_Recorder::record_refusal( 'demo/contested-once', 'Loser', 'their-plugin' );

		$names = array_column( Ability_Collision_Recorder::collisions(), 'ability' );

		$this->assertNotContains( 'demo/alone', $names );
		$this->assertSame( 2, Ability_Collision_Recorder::attempted_count(), 'Both names were seen, by different listeners.' );
	}

	/**
	 * Only the duplicate path may be recorded.
	 *
	 * `register()` emits `_doing_it_wrong` on five paths — an invalid name, an unregistered
	 * category, a bad `ability_class`, a constructor throw, and the duplicate. Recording them all
	 * would report a bad category as a contested name. The discriminator is whether the registry
	 * holds the name at the moment of the notice, which only the duplicate path can be.
	 *
	 * @return void
	 */
	public function test_only_the_duplicate_path_is_recorded(): void {
		$src  = self::read( self::util() );
		$body = self::method_body( $src, 'record_rejection' );

		$this->assertStringContainsString( "'WP_Abilities_Registry::register' !== (string) \$function_name", $body, 'Must ignore every other function.' );
		$this->assertStringContainsString( 'name_is_held', $body, 'Must tell a duplicate refusal apart from the other failure paths.' );
		$this->assertStringNotContainsString( 'already registered', $src, 'Must not depend on the wording of a translated message.' );
	}

	/**
	 * Zero collisions and zero visibility are different answers.
	 *
	 * If the recorder never attached, "no collisions" is not a finding — it is the absence of one.
	 * Reporting them the same way would make the ability reassuring exactly when it is blind.
	 */
	public function test_an_unwatched_request_is_not_reported_as_clean(): void {
		$src = self::read( self::ability_file() );

		$this->assertStringContainsString( 'is_listening()', $src );
		$this->assertStringContainsString( 'recorder_inactive', $src );

		$inactive = strpos( $src, 'recorder_inactive' );
		$clean    = strpos( $src, "'count'      => 0," );

		$this->assertNotFalse( $inactive );
		$this->assertNotFalse( $clean );
		$this->assertLessThan(
			$clean,
			$inactive,
			'The inactive check must run before the clean-result path, or a blind request reports as clean.'
		);
	}

	/**
	 * The ability is registered under a category that exists.
	 *
	 * WP drops an ability whose category was never registered, without a word
	 * (BUG-WP-CORE-ABILITY-CATEGORY-PRE-REGISTRATION) — which is why this lives with Recovery rather
	 * than in a folder with a category of its own.
	 */
	public function test_it_uses_a_registered_category(): void {
		$this->assertStringContainsString( "'category'            => 'acrossai-recovery'", self::read( self::ability_file() ) );

		$bootstrap = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );

		$this->assertStringContainsString( 'new Recovery\\List_Ability_Collisions();', $bootstrap );
	}

	/**
	 * Recording starts before anything registers.
	 */
	public function test_recording_starts_early_enough(): void {
		$main = self::read( dirname( __DIR__, 3 ) . '/includes/Main.php' );

		$this->assertStringContainsString( 'Ability_Collision_Recorder::listen();', $main );
	}

	/**
	 * The per-attempt path stays cheap.
	 *
	 * `record()` runs for every ability on every request — 487 times on one measured site. A
	 * backtrace there would identify the losing plugin precisely and cost far more than a diagnostic
	 * nobody reads most days is worth, which is why the original version of this class took none.
	 *
	 * #260 changed that only for the rejection path. A refused duplicate is the one case the args
	 * filter cannot see, it is rare (twice per request on an affected site, against 487 attempts),
	 * and the backtrace is the only place the ability name and the losing plugin can be read without
	 * parsing a translated message. So the invariant is now about WHERE the backtrace may appear,
	 * not whether it appears at all.
	 */
	public function test_the_per_attempt_path_stays_cheap(): void {
		$src  = self::read( self::util() );
		$body = self::method_body( $src, 'record' );

		$this->assertNotSame( '', $body, 'record() must still exist; this test is meaningless otherwise.' );
		$this->assertStringNotContainsString( 'debug_backtrace', $body, 'record() runs per attempt and must not take a backtrace.' );
		$this->assertStringNotContainsString( 'get_plugin_data', $body );
	}

	/**
	 * Every backtrace the class does take is bounded.
	 *
	 * An unbounded trace copies every frame above the one we want, and for the args-bearing call that
	 * means every ability's full registration array.
	 */
	public function test_backtraces_are_bounded(): void {
		$src = self::read( self::util() );

		preg_match_all( '/debug_backtrace\(([^)]*)\)/', $src, $matches );

		$this->assertNotEmpty( $matches[1], 'Expected the rejection path to take a bounded backtrace.' );

		foreach ( $matches[1] as $call_args ) {
			$this->assertMatchesRegularExpression(
				'/,\s*\d+\s*$/',
				trim( $call_args ),
				'Every debug_backtrace() call must pass a frame limit.'
			);
		}
	}

	/**
	 * The body of a named method, so a per-attempt guarantee can be asserted on the hot path alone.
	 *
	 * @param  string $src    Class source.
	 * @param  string $method Method name.
	 * @return string
	 */
	private static function method_body( string $src, string $method ): string {
		$start = strpos( $src, ' function ' . $method . '(' );

		if ( false === $start ) {
			return '';
		}

		$open = strpos( $src, '{', $start );

		if ( false === $open ) {
			return '';
		}

		$depth = 0;

		for ( $i = $open, $len = strlen( $src ); $i < $len; $i++ ) {
			if ( '{' === $src[ $i ] ) {
				++$depth;
			} elseif ( '}' === $src[ $i ] ) {
				--$depth;

				if ( 0 === $depth ) {
					return substr( $src, $open, $i - $open + 1 );
				}
			}
		}

		return '';
	}
}
