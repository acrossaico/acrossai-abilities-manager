<?php
/**
 * Feature 126 — which backup plugins are present, and how to reach them.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Utilities\Backups
 * @since      0.0.34
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Backups;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The one place that knows which providers exist.
 *
 * @since 0.0.34
 */
final class Provider_Registry {

	/**
	 * Filter for third parties to add a provider.
	 *
	 * @since 0.0.34
	 * @var   string
	 */
	public const FILTER = 'acrossai_backup_providers';

	/**
	 * Private constructor — static utility (DEC-UTILITY-STATIC-ONLY).
	 */
	private function __construct() {}

	/**
	 * Every provider this plugin knows about, active or not.
	 *
	 * @since  0.0.34
	 * @return array<int, class-string<Backup_Provider>>
	 */
	public static function all(): array {
		$providers = array(
			UpdraftPlus_Provider::class,
			All_In_One_Provider::class,
		);

		/**
		 * Filters the backup providers this suite can reach.
		 *
		 * @since 0.0.34
		 * @param array<int, class-string<Backup_Provider>> $providers Provider class names.
		 */
		$declared = apply_filters( self::FILTER, $providers );

		if ( ! is_array( $declared ) ) {
			return $providers;
		}

		$valid = array();

		foreach ( $declared as $provider ) {
			// Dropping rather than throwing: this runs on every request, and one bad entry from a
			// third-party filter must not take the suite down with it.
			if ( is_string( $provider ) && is_subclass_of( $provider, Backup_Provider::class ) ) {
				$valid[] = $provider;
			}
		}

		return empty( $valid ) ? $providers : array_values( array_unique( $valid ) );
	}

	/**
	 * Providers whose plugin is actually installed and running.
	 *
	 * @since  0.0.34
	 * @return array<int, class-string<Backup_Provider>>
	 */
	public static function active(): array {
		$active = array();

		foreach ( self::all() as $provider ) {
			if ( $provider::is_active() ) {
				$active[] = $provider;
			}
		}

		return $active;
	}

	/**
	 * Resolve the `provider` input to one active provider.
	 *
	 * With no id given and exactly one provider active, that one is used. With more than one active
	 * the caller must say which, because "back up the site" is ambiguous when two plugins could do
	 * it and picking one silently would be a guess about which the operator trusts.
	 *
	 * @since  0.0.34
	 * @param  string $id Provider id, or empty to infer.
	 * @return class-string<Backup_Provider>|WP_Error
	 */
	public static function resolve( string $id = '' ) {
		$active = self::active();

		if ( empty( $active ) ) {
			return new WP_Error( 'no_backup_plugin', self::absent_message() );
		}

		if ( '' === $id ) {
			if ( 1 === count( $active ) ) {
				return $active[0];
			}

			return new WP_Error(
				'provider_required',
				sprintf(
					/* translators: %s: comma-separated list of provider ids. */
					__( 'More than one backup plugin is active on this site, so which one to use has to be stated. Pass provider as one of: %s.', 'acrossai-abilities-manager' ),
					implode( ', ', self::ids( $active ) )
				)
			);
		}

		foreach ( $active as $provider ) {
			if ( $provider::id() === $id ) {
				return $provider;
			}
		}

		foreach ( self::all() as $provider ) {
			if ( $provider::id() === $id ) {
				return new WP_Error(
					'provider_inactive',
					sprintf(
						/* translators: 1: provider label, 2: comma-separated list of active provider ids. */
						__( '%1$s is not active on this site. Active backup plugins: %2$s.', 'acrossai-abilities-manager' ),
						$provider::label(),
						implode( ', ', self::ids( $active ) )
					)
				);
			}
		}

		return new WP_Error(
			'unknown_provider',
			sprintf(
				/* translators: 1: the id that was asked for, 2: comma-separated list of known ids. */
				__( 'There is no backup provider called %1$s. Known providers: %2$s.', 'acrossai-abilities-manager' ),
				$id,
				implode( ', ', self::ids( self::all() ) )
			)
		);
	}

	/**
	 * Resolve for an operation, refusing when the provider cannot do it.
	 *
	 * @since  0.0.34
	 * @param  string $id         Provider id, or empty to infer.
	 * @param  string $capability Capability key.
	 * @return class-string<Backup_Provider>|WP_Error
	 */
	public static function resolve_for( string $id, string $capability ) {
		$provider = self::resolve( $id );

		if ( is_wp_error( $provider ) ) {
			return $provider;
		}

		if ( ! $provider::supports( $capability ) ) {
			// Prefer the provider's own account of why. "Cannot do this" leaves a caller stuck;
			// "that is in their paid extension, and here is the other route" does not.
			$reason = $provider::unsupported_reason( $capability );

			if ( '' !== $reason ) {
				return new WP_Error( 'unsupported_by_provider', $reason );
			}

			return new WP_Error(
				'unsupported_by_provider',
				sprintf(
					/* translators: 1: provider label, 2: the operation asked for. */
					__( '%1$s cannot do this from here (%2$s). backups/get-status reports what each active backup plugin supports.', 'acrossai-abilities-manager' ),
					$provider::label(),
					$capability
				)
			);
		}

		return $provider;
	}

	/**
	 * @since  0.0.34
	 * @param  array<int, class-string<Backup_Provider>> $providers Providers.
	 * @return array<int, string>
	 */
	public static function ids( array $providers ): array {
		$ids = array();

		foreach ( $providers as $provider ) {
			$ids[] = $provider::id();
		}

		return $ids;
	}

	/**
	 * @since  0.0.34
	 * @return string
	 */
	public static function absent_message(): string {
		return __( 'No backup plugin is active on this site, so there is nothing to report and no way to take a backup. This plugin can work with UpdraftPlus or All-in-One WP Migration; install and activate one, then ask again.', 'acrossai-abilities-manager' );
	}
}
