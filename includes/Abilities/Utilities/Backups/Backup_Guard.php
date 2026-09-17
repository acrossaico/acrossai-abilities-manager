<?php
/**
 * Feature 126 — availability, permission and envelope for the backup suite.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Utilities\Backups
 * @since      0.0.52
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Backups;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The one place this suite decides whether it may run, and the one shape it answers in.
 *
 * @since 0.0.52
 */
final class Backup_Guard {

	/**
	 * @since 0.0.52
	 * @var   string
	 */
	public const PERMISSION_FILTER = 'acrossai_abilities_manager_backup_permission';

	/**
	 * Private constructor — static utility (DEC-UTILITY-STATIC-ONLY).
	 */
	private function __construct() {}

	/**
	 * Whether any backup plugin is present.
	 *
	 * Unlike the single-vendor suites, availability here is not one plugin's symbols but whether the
	 * registry can find anything at all — the whole point of the provider layer.
	 *
	 * @since  0.0.52
	 * @return bool
	 */
	public static function is_available(): bool {
		return ! empty( Provider_Registry::active() );
	}

	/**
	 * @since  0.0.52
	 * @return true|WP_Error
	 */
	public static function assert_available() {
		if ( ! self::is_available() ) {
			return new WP_Error( 'no_backup_plugin', Provider_Registry::absent_message() );
		}

		return true;
	}

	/**
	 * @since  0.0.52
	 * @param  array<string, mixed> $input   Ability input.
	 * @param  string               $message What the caller is confirming.
	 * @return true|WP_Error
	 */
	public static function assert_confirmed( array $input, string $message = '' ) {
		if ( empty( $input['confirm'] ) ) {
			return new WP_Error(
				'confirmation_required',
				'' !== $message ? $message : __( 'Pass confirm: true to proceed.', 'acrossai-abilities-manager' )
			);
		}

		return true;
	}

	/**
	 * Permission callback factory.
	 *
	 * A single `current_user_can( $floor )`, and the filter is consulted only after it passes — so a
	 * filter can tighten access and never widen it (PATTERN-FILTERABLE-CAPABILITY-RAISE-ONLY).
	 *
	 * @since  0.0.52
	 * @param  string $floor WordPress capability floor.
	 * @return callable
	 */
	public static function can( string $floor = 'manage_options' ): callable {
		return static function () use ( $floor ): bool {
			if ( ! current_user_can( $floor ) ) {
				return false;
			}

			/**
			 * Filters whether the current user may use a backup ability.
			 *
			 * @since 0.0.52
			 * @param bool   $allowed Whether access is granted. Always true at this point.
			 * @param string $floor   WordPress capability floor.
			 */
			return (bool) apply_filters( self::PERMISSION_FILTER, true, $floor );
		};
	}

	/**
	 * @since  0.0.52
	 * @param  array<string, mixed> $payload Ability-specific keys.
	 * @param  string               $message Human-readable summary.
	 * @return array<string, mixed>
	 */
	public static function ok( array $payload, string $message ): array {
		unset( $payload['success'], $payload['error_code'], $payload['message'] );

		return array_merge( array( 'success' => true ), $payload, array( 'message' => $message ) );
	}

	/**
	 * @since  0.0.52
	 * @param  WP_Error $error Error.
	 * @return array<string, mixed>
	 */
	public static function fail( WP_Error $error ): array {
		return self::error( (string) $error->get_error_code(), (string) $error->get_error_message() );
	}

	/**
	 * @since  0.0.52
	 * @param  string $code    Machine-readable code.
	 * @param  string $message Human-readable message.
	 * @return array<string, mixed>
	 */
	public static function error( string $code, string $message ): array {
		return array(
			'success'    => false,
			'error_code' => $code,
			'message'    => $message,
		);
	}
}
