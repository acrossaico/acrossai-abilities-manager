<?php
/**
 * Third-party integration opt-ins on the AcrossAI settings screen.
 *
 * Feature 102 retired the Ability Integrations page. Most of what that page did — switching whole
 * categories of first-party abilities off — is gone, replaced by the per-ability access setting on
 * the abilities list. One control was not a duplicate and could not be replaced: the opt-in that
 * asks a third-party plugin to register its abilities at all. It lives here now.
 *
 * Server-rendered through the Settings API, in the existing Abilities tab, following
 * Core_Settings_Menu. No new tab and no JS bundle: this is a short list of checkboxes, and the
 * retired page's React app existed for the category cards that no longer exist.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/admin/Partials
 * @since      0.0.34
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Admin\Partials;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Integration_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and persists the integration opt-ins.
 */
class Integrations_Settings_Menu {

	/**
	 * Settings tab this section renders inside.
	 *
	 * Its own tab rather than a section on Abilities: the Abilities tab is about how this plugin's
	 * own abilities behave, while this is about asking *other* plugins to switch theirs on. Two
	 * different questions, and the list grows as integrations are added.
	 *
	 * @var string
	 */
	public const TAB_SLUG = 'integrations';

	/**
	 * Register the "Integrations" tab on the shared AcrossAI Settings page.
	 *
	 * Hooked to the `acrossai_settings_tabs` filter from `acrossai-co/main-menu`, following
	 * File_Manager_Settings_Menu. Priority 20 places it between Abilities (10) and File
	 * Manager (30).
	 *
	 * @since  0.0.34
	 * @param  mixed $tabs Tabs collected so far.
	 * @return array<int, array<string, mixed>>
	 */
	public function register_tab( $tabs ): array {
		if ( ! is_array( $tabs ) ) {
			$tabs = array();
		}

		$tabs[] = array(
			'slug'     => self::TAB_SLUG,
			'label'    => __( 'Integrations', 'acrossai-abilities-manager' ),
			'priority' => 20,
		);

		return $tabs;
	}

	/**
	 * Capability every opt-in change requires, whatever the filter returns.
	 *
	 * The Settings API already gates the host page at `manage_options`, but `sanitize_option()` is a
	 * public `sanitize_option_{$option}` callback: any code that writes the option directly runs it
	 * without that gate. So the floor is layered here too, ahead of the filtered check, per
	 * PATTERN-FILTERABLE-CAPABILITY-RAISE-ONLY — the filter may raise the bar, never lower it.
	 *
	 * @var string
	 */
	public const CAPABILITY_FLOOR = 'manage_options';

	/**
	 * Singleton instance.
	 *
	 * @var Integrations_Settings_Menu|null
	 */
	protected static $_instance = null; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore -- plugin-wide singleton convention.

	/**
	 * Retrieve the singleton.
	 *
	 * @since  0.0.34
	 * @return Integrations_Settings_Menu
	 */
	public static function instance(): self {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Private constructor.
	 *
	 * @since 0.0.34
	 */
	private function __construct() {}

	/**
	 * Register the section and its fields.
	 *
	 * Bails when the shared menu package is absent, exactly as Core_Settings_Menu does — the
	 * settings host belongs to acrossai-co/main-menu and the plugin must degrade rather than fatal
	 * when it is not there (Constitution §V).
	 *
	 * @since  0.0.34
	 * @return void
	 */
	public function register_settings(): void {
		if ( ! class_exists( '\AcrossAI_Main_Menu\SettingsPage' ) ) {
			return;
		}

		$renderer = \AcrossAI_Main_Menu\SettingsPage::get_settings_renderer();

		if ( ! $renderer ) {
			return;
		}

		$page_slug = $renderer->tab_page_slug( self::TAB_SLUG );

		register_setting(
			$page_slug,
			AcrossAI_Integration_Settings::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_option' ),
				'default'           => array(),
			)
		);

		add_settings_section(
			'acrossai_third_party_integrations',
			__( 'Third-Party Integrations', 'acrossai-abilities-manager' ),
			array( $this, 'render_section' ),
			$page_slug
		);

		add_settings_field(
			'acrossai_third_party_integrations_field',
			__( 'Available integrations', 'acrossai-abilities-manager' ),
			array( $this, 'render_field' ),
			$page_slug,
			'acrossai_third_party_integrations'
		);
	}

	/**
	 * Section description.
	 *
	 * @since  0.0.34
	 * @return void
	 */
	public function render_section(): void {
		echo '<p>';
		esc_html_e(
			'A few plugins ship their own abilities behind their own master switch. Enabling one here asks that plugin to turn its abilities on; until then it contributes nothing. This list is only for those plugins — most supported plugins need nothing switched on, because AcrossAI provides their abilities directly and they appear on the Abilities screen as soon as the plugin is active.',
			'acrossai-abilities-manager'
		);
		echo '</p>';
	}

	/**
	 * Checkbox per available integration.
	 *
	 * @since  0.0.34
	 * @return void
	 */
	public function render_field(): void {
		$integrations = AcrossAI_Integration_Settings::discover();

		if ( array() === $integrations ) {
			echo '<p class="description">';
			esc_html_e(
				'None of the active plugins on this site have their own ability switch, so there is nothing to enable here. This is not the list of supported plugins — see the toolsets on the Abilities screen for what is available.',
				'acrossai-abilities-manager'
			);
			echo '</p>';

			return;
		}

		$name = AcrossAI_Integration_Settings::OPTION_KEY;

		echo '<fieldset>';

		foreach ( $integrations as $slug => $meta ) {
			$enabled  = AcrossAI_Integration_Settings::is_enabled( (string) $slug );
			$field_id = 'acrossai-integration-' . sanitize_key( (string) $slug );

			printf(
				'<p><label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s[%3$s]" value="1" %4$s /> %5$s</label>'
				. '<br /><span class="description">%6$s</span></p>',
				esc_attr( $field_id ),
				esc_attr( $name ),
				esc_attr( (string) $slug ),
				checked( $enabled, true, false ),
				esc_html( (string) $meta['label'] ),
				esc_html(
					sprintf(
						/* translators: %d is a count of abilities. */
						_n(
							'Enables %d ability from this plugin.',
							'Enables %d abilities from this plugin.',
							(int) $meta['count'],
							'acrossai-abilities-manager'
						),
						(int) $meta['count']
					)
				)
			);
		}

		echo '</fieldset>';
	}

	/**
	 * Sanitize and authorise the submitted opt-ins.
	 *
	 * The per-integration capability check sits here, behind the Settings API page — which is
	 * already gated at `manage_options` — so the filtered capability can only raise the bar
	 * (PATTERN-FILTERABLE-CAPABILITY-RAISE-ONLY). A denied change leaves the stored value untouched
	 * and fires `acrossai_integration_toggle_denied` so sites can audit it, matching the REST
	 * behaviour this replaces.
	 *
	 * An unchecked box submits nothing, so the absent-means-off default does the work: any
	 * integration the operator did not tick is written as false.
	 *
	 * @since  0.0.34
	 * @param  mixed $input Raw value from the settings POST.
	 * @return array<string, bool>
	 */
	public function sanitize_option( $input ): array {
		$submitted = is_array( $input ) ? $input : array();
		$stored    = get_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array() );
		$stored    = is_array( $stored ) ? $stored : array();
		$clean     = array();

		// Unconditional floor, ahead of the per-integration filtered capability. Fail closed by
		// handing back exactly what is stored, so a denied request changes nothing.
		if ( ! current_user_can( self::CAPABILITY_FLOOR ) ) {
			return $stored;
		}

		foreach ( array_keys( AcrossAI_Integration_Settings::discover() ) as $slug ) {
			$slug    = (string) $slug;
			$desired = ! empty( $submitted[ $slug ] );
			$current = ! empty( $stored[ $slug ] );

			if ( $desired === $current ) {
				$clean[ $slug ] = $current;

				continue;
			}

			$required_cap = AcrossAI_Integration_Settings::required_capability( $slug );

			if ( '' === $required_cap || ! current_user_can( $required_cap ) ) {
				/**
				 * Fires when a user is denied permission to change an integration opt-in.
				 *
				 * @since 0.1.0
				 * @param string $integration_slug Integration slug.
				 * @param string $required_cap     Capability that would have been required.
				 * @param int    $user_id          Current user ID (0 for guests).
				 */
				do_action(
					'acrossai_integration_toggle_denied',
					$slug,
					$required_cap,
					get_current_user_id()
				);

				add_settings_error(
					AcrossAI_Integration_Settings::OPTION_KEY,
					'acrossai_integration_forbidden_' . sanitize_key( $slug ),
					sprintf(
						/* translators: %s is an integration name. */
						esc_html__( 'You are not allowed to change the %s integration.', 'acrossai-abilities-manager' ),
						esc_html( $slug )
					),
					'error'
				);

				$clean[ $slug ] = $current;

				continue;
			}

			$clean[ $slug ] = $desired;
		}

		// Preserve opt-ins for integrations whose plugin is inactive right now, so deactivating a
		// plugin does not silently discard the operator's choice for when it returns.
		foreach ( $stored as $slug => $value ) {
			if ( ! array_key_exists( (string) $slug, $clean ) ) {
				$clean[ (string) $slug ] = (bool) $value;
			}
		}

		return $clean;
	}
}
