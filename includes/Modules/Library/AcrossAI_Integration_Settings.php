<?php
/**
 * Third-party integration opt-ins.
 *
 * An integration opt-in is not an access setting and is not interchangeable with one. It asks a
 * third-party plugin to register its abilities in the first place — `ACF::enable_filter()` is
 * `add_filter( 'acf/settings/enable_acf_ai', '__return_true' )` — so without it there is nothing for
 * the abilities table to allow or block. That is why Feature 102 removed the per-category
 * registration gate but kept this, and moved it to the settings screen rather than deleting it with
 * the Integrations page.
 *
 * **The default is off.** An absent entry means disabled, which is the opposite of the retired
 * category config, where an absent entry meant permitted. That asymmetry is the reason
 * BUG-SPARSE-STORAGE-UNIFORM-DEFAULT-ASSUMPTION exists, and it is contained here: this class is the
 * only PHP code path that reads integration opt-in state.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Modules/Library
 * @since      0.0.34
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Includes\Modules\Library;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and describes third-party integration opt-ins.
 *
 * **Deliberately static, not a singleton.** Constitution Module Contract #1/#2 require the
 * `instance()` pattern of every *feature* class, so that dependencies are reachable without
 * constructor injection. This holds no state and has no dependencies to reach: it reads one option
 * and one filter. A singleton would add a lifecycle to something that has none, and the shape it
 * replaces (`AcrossAI_Ability_Library_Config`) was static for the same reason.
 *
 * It stays in the Library module rather than moving to `includes/Utilities/` because `discover()`
 * reads the Library definition registry — relocating it would invert the dependency and put a
 * module reference inside Utilities, which is worse than the deviation it would cure.
 *
 * Sealed with a private constructor so the static intent is enforced rather than merely implied.
 * Recorded as an accepted deviation in `specs/102-abilities-toolset-tabs/plan.md`.
 */
class AcrossAI_Integration_Settings {

	/**
	 * Option holding the opt-in map, keyed by integration slug.
	 *
	 * @var string
	 */
	public const OPTION_KEY = 'acrossai_integrations';

	/**
	 * Not instantiable — every member is static.
	 *
	 * @since 0.0.34
	 */
	private function __construct() {}

	/**
	 * Whether the given integration is switched on.
	 *
	 * Absent means off. Callers must not re-implement this test — the inverted default is the whole
	 * reason this helper exists.
	 *
	 * @since  0.0.34
	 * @param  string $slug Integration slug.
	 * @return bool
	 */
	public static function is_enabled( string $slug ): bool {
		$stored = get_site_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) || ! array_key_exists( $slug, $stored ) ) {
			return false;
		}

		return (bool) $stored[ $slug ];
	}

	/**
	 * Integrations available on this site.
	 *
	 * Derived from the definitions registry rather than from the integration classes: their
	 * slug/label/abilities accessors are `protected`, and widening them purely to build a settings
	 * screen would be a worse trade than reading data that already exists. Synthetic integration
	 * rows are only pushed when the target plugin is active, so an integration whose plugin is
	 * missing does not appear here at all — which is the behaviour the retired page had.
	 *
	 * @since  0.0.34
	 * @return array<string, array{label: string, count: int}> Keyed by integration slug.
	 */
	public static function discover(): array {
		$found = array();

		foreach ( AcrossAI_Ability_Library_Registry::instance()->get_definitions() as $definition ) {
			$variant = isset( $definition['card_variant'] ) ? (string) $definition['card_variant'] : '';

			if ( 'integration' !== $variant ) {
				continue;
			}

			$slug = isset( $definition['category'] ) ? (string) $definition['category'] : '';

			if ( '' === $slug ) {
				continue;
			}

			if ( ! isset( $found[ $slug ] ) ) {
				$label = isset( $definition['category_label'] ) ? (string) $definition['category_label'] : $slug;

				$found[ $slug ] = array(
					'label' => $label,
					'count' => 0,
				);
			}

			++$found[ $slug ]['count'];
		}

		ksort( $found );

		return $found;
	}

	/**
	 * Capability required to change a given integration's opt-in.
	 *
	 * A SINGLE filtered `current_user_can()` behind a surface already gated at `manage_options` is
	 * inherently raise-only: a filter can demand more, never less, because the outer gate still
	 * applies (PATTERN-FILTERABLE-CAPABILITY-RAISE-ONLY). Splitting this into two checks, or making
	 * it the only gate, would let a filter returning a weaker capability lower the requirement.
	 *
	 * @since  0.0.34
	 * @param  string $slug Integration slug.
	 * @return string Capability name.
	 */
	public static function required_capability( string $slug ): string {
		/**
		 * Filters the capability required to toggle a third-party integration.
		 *
		 * Raise-only in practice — see the note above. Returning a weaker capability does not grant
		 * access, because the settings page itself is registered at `manage_options`.
		 *
		 * @since 0.1.0
		 * @param string $capability Default 'manage_options'.
		 * @param string $slug       Integration slug.
		 */
		return (string) apply_filters( 'acrossai_integration_toggle_capability', 'manage_options', $slug );
	}
}
