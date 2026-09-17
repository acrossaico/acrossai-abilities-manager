<?php
/**
 * Feature 126 — the contract every backup plugin is reached through.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Utilities\Backups
 * @since      0.0.52
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Backups;

defined( 'ABSPATH' ) || exit;

/**
 * One interface, two implementations, and room for a third.
 *
 * The abilities in this suite never name a backup plugin. They ask the registry which providers are
 * active and speak to each through here, so a site running either plugin — or both — gets the same
 * answers in the same shape. That is the whole reason this layer exists: "is this site backed up?"
 * is a question about the site, not about a vendor.
 *
 * Every method returning an array returns a SHAPE THIS SUITE OWNS, never the plugin's own structure.
 * UpdraftPlus keys its history by timestamp and All-in-One returns a flat file list; a caller must
 * not have to know that.
 *
 * `supports()` exists because the two plugins genuinely differ. Reporting a capability as absent is
 * honest; pretending to offer it and failing at the point of use is not.
 *
 * @since 0.0.52
 */
interface Backup_Provider {

	/**
	 * Stable machine identifier, used as the `provider` input value.
	 *
	 * @since  0.0.52
	 * @return string
	 */
	public static function id(): string;

	/**
	 * Human-readable name, as the plugin calls itself.
	 *
	 * @since  0.0.52
	 * @return string
	 */
	public static function label(): string;

	/**
	 * Whether this plugin is present and usable.
	 *
	 * Two stable symbols per SEC-002.
	 *
	 * @since  0.0.52
	 * @return bool
	 */
	public static function is_active(): bool;

	/**
	 * Whether this provider can do a given thing.
	 *
	 * @since  0.0.52
	 * @param  string $capability One of: start, progress, delete, restore, label, schedule.
	 * @return bool
	 */
	public static function supports( string $capability ): bool;

	/**
	 * Why a capability is unavailable, in terms the caller can act on.
	 *
	 * A generic "this provider cannot do that" is a dead end when a specific and actionable reason
	 * exists -- a paid extension, a missing file, a server setting. Return an empty string to accept
	 * the generic wording.
	 *
	 * @since  0.0.52
	 * @param  string $capability Capability key.
	 * @return string
	 */
	public static function unsupported_reason( string $capability ): string;

	/**
	 * The headline: when did this site last back up, and did it work.
	 *
	 * @since  0.0.52
	 * @return array<string, mixed>
	 */
	public static function status(): array;

	/**
	 * Backup sets held by this provider, newest first.
	 *
	 * @since  0.0.52
	 * @param  int $limit  Maximum rows.
	 * @param  int $offset Rows to skip.
	 * @return array<string, mixed>
	 */
	public static function list_backups( int $limit, int $offset ): array;

	/**
	 * One backup set in full.
	 *
	 * @since  0.0.52
	 * @param  string $id Backup identifier, as returned by list_backups().
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function get_backup( string $id );

	/**
	 * Absolute directories this provider writes archives into.
	 *
	 * Used by the exposure scan. A backup archive holds the whole database, so where it sits and
	 * whether the web server will serve it is a security question, not a housekeeping one.
	 *
	 * @since  0.0.52
	 * @return array<int, string>
	 */
	public static function storage_paths(): array;

	/**
	 * Begin a backup.
	 *
	 * @since  0.0.52
	 * @param  array<string, mixed> $options Provider-neutral options.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function start_backup( array $options );

	/**
	 * Report on a running or finished job.
	 *
	 * @since  0.0.52
	 * @param  string $job Job identifier from start_backup().
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function job_progress( string $job );

	/**
	 * Delete one backup set.
	 *
	 * @since  0.0.52
	 * @param  string $id Backup identifier.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function delete_backup( string $id );

	/**
	 * Restore the site from one backup set.
	 *
	 * @since  0.0.52
	 * @param  string               $id      Backup identifier.
	 * @param  array<string, mixed> $options Restore options.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function restore_backup( string $id, array $options );
}
