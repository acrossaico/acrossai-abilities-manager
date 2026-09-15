<?php
/**
 * Issue #202 — records ability names that more than one registrant tried to claim.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Utilities
 * @since      0.0.44
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Utilities;

defined( 'ABSPATH' ) || exit;

/**
 * Makes a silent name clash visible.
 *
 * `WP_Abilities_Registry::register()` refuses a duplicate name: the first registration wins, the
 * second gets `_doing_it_wrong()` and null (class-wp-abilities-registry.php:83-91). Nothing else
 * reports it. On a production site `_doing_it_wrong` goes nowhere, so a plugin can lose an ability
 * to another plugin and the only symptom is that the ability behaves like someone else's — or, where
 * the loser was the real implementation and the winner a placeholder, that it stops working at all.
 *
 * Which side wins is decided purely by hook order, so the same two plugins can resolve differently
 * on two sites. That is what makes it worth surfacing rather than reasoning about.
 *
 * Detection hooks `wp_register_ability_args`, which fires on EVERY registration attempt and,
 * critically, BEFORE the duplicate check — so both the winner and the loser pass through here. After
 * registration settles, the winner is whichever label the registry actually holds.
 *
 * Deliberately cheap: a name and a label per attempt, no backtrace. This runs for every ability on
 * every request, so anything heavier would be a real cost for a diagnostic nobody reads most days.
 *
 * @since 0.0.44
 */
final class Ability_Collision_Recorder {

	/**
	 * Attempted registrations: name => list of labels, in attempt order.
	 *
	 * @since 0.0.44
	 * @var   array<string, array<int, string>>
	 */
	private static $attempts = array();

	/**
	 * Whether the filter has been attached.
	 *
	 * @since 0.0.44
	 * @var   bool
	 */
	private static $listening = false;

	/**
	 * Private constructor — static utility (DEC-UTILITY-STATIC-ONLY).
	 */
	private function __construct() {}

	/**
	 * Start recording.
	 *
	 * Priority 1 so the record is taken before any other filter can rewrite the args — including our
	 * own override processor at P100000, which may legitimately change the label.
	 *
	 * @since  0.0.44
	 * @return void
	 */
	public static function listen(): void {
		if ( self::$listening ) {
			return;
		}

		self::$listening = true;

		add_filter( 'wp_register_ability_args', array( __CLASS__, 'record' ), 1, 2 );
	}

	/**
	 * Filter callback. Records and returns the args untouched.
	 *
	 * @since  0.0.44
	 * @param  array<string, mixed> $args Ability args.
	 * @param  string               $name Ability name.
	 * @return array<string, mixed>
	 */
	public static function record( $args, $name = '' ) {
		$name = (string) $name;

		if ( '' !== $name ) {
			$label = is_array( $args ) && isset( $args['label'] ) ? (string) $args['label'] : '';

			if ( ! isset( self::$attempts[ $name ] ) ) {
				self::$attempts[ $name ] = array();
			}

			self::$attempts[ $name ][] = $label;
		}

		return $args;
	}

	/**
	 * Names that were claimed more than once, with who won.
	 *
	 * @since  0.0.44
	 * @return array<int, array<string, mixed>>
	 */
	public static function collisions(): array {
		$rows = array();

		foreach ( self::$attempts as $name => $labels ) {
			if ( count( $labels ) < 2 ) {
				continue;
			}

			$registered = function_exists( 'wp_get_ability' ) ? wp_get_ability( $name ) : null;
			$winner     = ( null !== $registered && is_object( $registered ) && method_exists( $registered, 'get_label' ) )
				? (string) $registered->get_label()
				: '';

			/*
			 * The losers are every attempted label that is not the one the registry kept. Compared by
			 * label rather than by order: our own override processor can rewrite a label between the
			 * attempt and registration, so "the first attempt won" is not safe to assume.
			 */
			$losers = array();

			foreach ( $labels as $label ) {
				if ( $label !== $winner ) {
					$losers[] = $label;
				}
			}

			$losers = array_values( array_unique( $losers ) );

			$row = array(
				'ability'  => (string) $name,
				'attempts' => count( $labels ),
				'winner'   => $winner,
				'losers'   => $losers,
			);

			/*
			 * Two cases where the loser list alone would mislead, both real:
			 *
			 * - No winner at all. The name is contested but nothing holds it now, because something
			 *   unregistered it afterwards - our own Force Block does exactly that at
			 *   wp_abilities_api_init P100001. Every label then looks like a loser, implying a fight
			 *   nobody won rather than an ability deliberately removed.
			 * - Identical labels. Comparing labels cannot separate the registrants, so the list comes
			 *   back empty and the row reads as "two attempts, nothing discarded" - the opposite of
			 *   the truth. Measured on acf/register-custom-post-type, where our placeholder label
			 *   matched ACF's exactly.
			 */
			if ( '' === $winner ) {
				$row['note'] = __( 'This name is contested but nothing currently holds it, so no winner can be named. It was most likely unregistered after the fact - a per-ability Force Block does this.', 'acrossai-abilities-manager' );
			} elseif ( empty( $losers ) ) {
				$row['note'] = __( 'A registration was still discarded; the registrants used identical labels, so which one lost cannot be told apart from the label alone.', 'acrossai-abilities-manager' );
			}

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Every name seen, for the "nothing collided" case to mean something.
	 *
	 * @since  0.0.44
	 * @return int
	 */
	public static function attempted_count(): int {
		return count( self::$attempts );
	}

	/**
	 * Whether recording is active.
	 *
	 * A report of zero collisions is only meaningful if the recorder was listening before the
	 * registrations happened; otherwise it means "nothing was watched", which is a different claim.
	 *
	 * @since  0.0.44
	 * @return bool
	 */
	public static function is_listening(): bool {
		return self::$listening;
	}

	/**
	 * Reset — tests only.
	 *
	 * @since  0.0.44
	 * @return void
	 */
	public static function reset(): void {
		self::$attempts = array();
	}
}
