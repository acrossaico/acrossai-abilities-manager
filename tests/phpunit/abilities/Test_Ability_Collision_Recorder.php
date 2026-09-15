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
	 * Cheap by construction.
	 *
	 * This runs for every ability on every request. A backtrace per attempt would identify the
	 * losing plugin precisely and cost far more than a diagnostic nobody reads most days is worth.
	 */
	public function test_recording_stays_cheap(): void {
		$src = self::read( self::util() );

		$this->assertStringNotContainsString( 'debug_backtrace', $src );
		$this->assertStringNotContainsString( 'get_plugin_data', $src );
	}
}
