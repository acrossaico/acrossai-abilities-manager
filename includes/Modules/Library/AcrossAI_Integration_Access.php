<?php
/**
 * Turns an integration's off switch into a refusal, not a disappearance.
 *
 * The Integrations screen switch covers an integration's whole group. Most of those abilities are
 * ours and already registered, so "off" has to mean something other than "never created".
 *
 * It means refused. `AcrossAI_Ability_Override_Processor::user_has_ability_access()` is the single
 * gate every surface passes through — it is injected as each ability's `permission_callback` at
 * `wp_register_ability_args` P100000 — and it ends by asking
 * `acrossai_ability_access_refused`. One callback there covers the admin table, REST and every MCP
 * dispatcher at once, which is why this is a class and not a method repeated per integration.
 *
 * **Why not skip registration.** Feature 102 removed a per-category switch that did exactly that,
 * and the decision it left behind forbids the shape:
 *
 * > An access control MUST NOT work by preventing the controlled object from existing. It must let
 * > the object register and then deny access to it, so the object stays listable with its state
 * > visible.
 *
 * The reasons were practical. A switched-off category vanished from the abilities table; searching
 * it for one of those abilities returned "No abilities found", indistinguishable from the ability
 * not existing; and REST and the MCP catalogue lost them too, with nothing on screen explaining
 * why. `tests/phpunit/Modules/Library/Test_Library_Processor_No_Gate.php` is a mutation-verified
 * guard against that returning, and this class is how the same outcome is reached legitimately.
 *
 * So a disabled integration's abilities stay registered, stay in the table, stay in `discover()`,
 * and refuse when called.
 *
 * Opt-in integrations are a separate case this never sees. Switching one of those off means we do
 * not ask its host to turn its own abilities on, so they are never registered by anyone — the host's
 * mechanism, not a gate of ours. See `AcrossAI_Integration_Ability_Base::maybe_enable()`.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Library
 * @since      0.0.42
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Includes\Modules\Library;

use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\AcrossAI_Toolset_Integrations;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Group;

defined( 'ABSPATH' ) || exit;

/**
 * Refuses abilities belonging to a switched-off integration.
 */
class AcrossAI_Integration_Access {

	/**
	 * Singleton instance.
	 *
	 * @since 0.0.42
	 * @var   self|null
	 */
	private static $instance = null;

	/**
	 * Group decision per request, keyed by group. Avoids re-reading the option per ability.
	 *
	 * An access check runs for every ability on a discover call, so this is read tens of times a
	 * request. The option itself is cached by WordPress, but the filter behind the absent-key default
	 * is not, and memoising keeps that off the hot path.
	 *
	 * @since 0.0.42
	 * @var   array<string, bool>
	 */
	private $decided = array();

	/**
	 * Shared instance.
	 *
	 * @since  0.0.42
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Not constructed directly — see {@see self::instance()}.
	 *
	 * @since 0.0.42
	 */
	private function __construct() {}

	/**
	 * Attach the refusal.
	 *
	 * @since  0.0.42
	 * @return void
	 */
	public function boot(): void {
		add_filter( 'acrossai_ability_access_refused', array( $this, 'refuse_disabled_integration' ), 10, 2 );
	}

	/**
	 * Refuse when the ability belongs to an integration that is switched off.
	 *
	 * Deny-only. An already-refused ability is returned refused without further work, so this can
	 * never turn another callback's deny into an allow — the same direction the filter itself is
	 * documented to run in.
	 *
	 * @since  0.0.42
	 * @param  mixed  $refused Whether access is already refused.
	 * @param  string $slug    Ability slug.
	 * @return bool
	 */
	public function refuse_disabled_integration( $refused, $slug = '' ): bool {
		if ( (bool) $refused ) {
			return true;
		}

		$group = $this->group_of( (string) $slug );

		if ( '' === $group ) {
			return false;
		}

		return ! $this->integration_enabled( $group );
	}

	/**
	 * Forget memoised decisions. Tests, and anything that changes the option mid-request.
	 *
	 * @since  0.0.42
	 * @return void
	 */
	public function flush(): void {
		$this->decided = array();
	}

	/**
	 * The integration group an ability belongs to, or '' when it belongs to none.
	 *
	 * Returns '' for our curated groups — content, blocks, appearance and the rest. Those are not
	 * integrations, have no switch, and must never be refused by this class.
	 *
	 * @since  0.0.42
	 * @param  string $slug Ability slug.
	 * @return string
	 */
	private function group_of( string $slug ): string {
		if ( '' === $slug || ! function_exists( 'wp_get_ability' ) ) {
			return '';
		}

		$ability = wp_get_ability( $slug );

		if ( ! $ability instanceof \WP_Ability ) {
			return '';
		}

		$group = AcrossAI_Ability_Group::of( $ability );

		if ( '' === $group || AcrossAI_Toolset_Integrations::CATCH_ALL_GROUP === $group ) {
			return '';
		}

		// Only a group an integration actually owns. A group key that matches no integration is one
		// of ours, and the switch does not reach it.
		return null === AcrossAI_Toolset_Integrations::for_group( $group ) ? '' : $group;
	}

	/**
	 * Whether the integration owning a group is switched on.
	 *
	 * @since  0.0.42
	 * @param  string $group Group key.
	 * @return bool
	 */
	private function integration_enabled( string $group ): bool {
		if ( ! isset( $this->decided[ $group ] ) ) {
			$this->decided[ $group ] = AcrossAI_Integration_Settings::is_enabled( $group );
		}

		return $this->decided[ $group ];
	}
}
