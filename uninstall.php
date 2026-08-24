<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Drops custom database tables and removes
 * plugin options when the delete-data setting is enabled. Data is preserved by default.
 *
 * @package    AcrossAI_Abilities_Manager
 * @since      0.0.1
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Respect the user's "delete data on uninstall" setting.
$acrossai_delete_data = (bool) get_option( 'acrossai_abilities_uninstall_delete_data', 0 );

if ( $acrossai_delete_data ) {
	$acrossai_abilities_table = $wpdb->prefix . 'acrossai_abilities';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query(
		$wpdb->prepare( 'DROP TABLE IF EXISTS %i', $acrossai_abilities_table )
	);
	\delete_option( 'acrossai_abilities_db_version' );

	// Per-consumer access-control table (wpb-access-control v2+, Feature 039).
	// Slug 'abilities' matches AcrossAI_Abilities_Access_Control::TABLE_SLUG — hardcoded here
	// because uninstall.php runs before the plugin autoloader and cannot reference the constant.
	// The legacy {prefix}wpb_access_control table and wpb_access_control_db_version option are
	// intentionally left orphaned on existing installs (no backward-compat migration).
	$acrossai_access_control_table = $wpdb->prefix . 'abilities_access_control';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query(
		$wpdb->prepare( 'DROP TABLE IF EXISTS %i', $acrossai_access_control_table )
	);
	\delete_option( 'wpb_ac_abilities_db_version' );

	// Legacy Logger table (Feature 006 → removed in Feature 040).
	// The plugin no longer creates this table on activation, but existing installs
	// may still have it and its schema-version option. Drop both on opt-in uninstall.
	$acrossai_logs_table = $wpdb->prefix . 'acrossai_ability_logs';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query(
		$wpdb->prepare( 'DROP TABLE IF EXISTS %i', $acrossai_logs_table )
	);
	\delete_option( 'acrossai_ability_logs_db_version' );

	// Action Scheduler cleanup — the Logger scheduled recurring cleanup actions
	// on the acrossai_ability_logger_cleanup hook (removed in Feature 040).
	// Drain any pending queue entries. Guarded — safe when AS is inactive.
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'acrossai_ability_logger_cleanup' );
	}

	// Remove plugin settings options on uninstall.
	\delete_option( 'acrossai_abilities_log_retention_days' );
	\delete_option( 'acrossai_abilities_uninstall_delete_data' );
	\delete_option( 'acrossai_abilities_per_page' );

	// Remove Library config on uninstall (Feature 027).
	\delete_site_option( 'acrossai_library_config' );

	// Feature 046 — Absorbed Core Settings option (extra MIME types).
	// Sits inside the existing $acrossai_delete_data gate per
	// PATTERN-UNINSTALL-DATA-GATE / BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE.
	\delete_option( 'acrossai_abilities_manager_extra_mimes' );

	// Feature 088 — Ability-level suggested-plugins kill-switch.
	\delete_option( 'acrossai_disable_plugin_suggestions' );
}
