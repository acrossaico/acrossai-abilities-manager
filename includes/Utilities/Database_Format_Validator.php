<?php
/**
 * Normalize and validate formats passed to WordPress database write helpers.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Utilities
 */

namespace AcrossAI_Abilities_Manager\Includes\Utilities;

defined( 'ABSPATH' ) || exit;

/**
 * Format handling shared by the database row write abilities.
 */
class Database_Format_Validator {

	private const FORMATS = array( '%s', '%d', '%f' );

	/**
	 * Schema for legacy positional formats and column-keyed format overrides.
	 *
	 * @param string $description Schema description.
	 * @return array<string, mixed>
	 */
	public static function schema( string $description ): array {
		return array(
			'anyOf'       => array(
				array(
					'type'  => 'array',
					'items' => array(
						'type' => 'string',
						'enum' => self::FORMATS,
					),
				),
				array(
					'type'                 => 'object',
					'additionalProperties' => array(
						'type' => 'string',
						'enum' => self::FORMATS,
					),
				),
			),
			'description' => $description,
		);
	}

	/**
	 * Return a positional format list, or false when a format could coerce a value.
	 *
	 * @param array<string|int, mixed> $values  Column/value map.
	 * @param mixed                    $formats Positional list or column-keyed map.
	 * @return array<int, string>|false|null
	 */
	public static function normalize( array $values, mixed $formats ): array|false|null {
		if ( null === $formats ) {
			return null;
		}

		if ( ! is_array( $formats ) ) {
			return false;
		}

		$value_keys  = array_keys( $values );
		$value_list  = array_values( $values );
		$format_list = array();

		if ( array_is_list( $formats ) ) {
			if ( count( $formats ) > count( $values ) ) {
				return false;
			}

			$format_list = array_pad( array_values( $formats ), count( $values ), '%s' );
		} else {
			if ( array_diff_key( $formats, $values ) ) {
				return false;
			}

			foreach ( $value_keys as $key ) {
				$format_list[] = array_key_exists( $key, $formats ) ? $formats[ $key ] : '%s';
			}
		}

		foreach ( $format_list as $index => $format ) {
			if ( ! is_string( $format ) || ! in_array( $format, self::FORMATS, true ) ) {
				return false;
			}

			$value = $value_list[ $index ];
			if ( null === $value ) {
				continue;
			}

			if ( '%d' === $format && ! self::is_integer_compatible( $value ) ) {
				return false;
			}

			if ( '%f' === $format && ! self::is_float_compatible( $value ) ) {
				return false;
			}
		}

		return array_values( $format_list );
	}

	/**
	 * Whether a value can be passed to %d without a lossy conversion.
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	private static function is_integer_compatible( mixed $value ): bool {
		if ( is_int( $value ) || is_bool( $value ) ) {
			return true;
		}

		if ( ! is_string( $value ) ) {
			return false;
		}

		$number = trim( $value );
		if ( ! preg_match( '/^[+-]?\d+$/D', $number ) ) {
			return false;
		}

		$negative = str_starts_with( $number, '-' );
		$digits   = ltrim( $number, '+-' );
		$digits   = ltrim( $digits, '0' );
		if ( '' === $digits ) {
			return true;
		}

		$limit = $negative ? substr( (string) PHP_INT_MIN, 1 ) : (string) PHP_INT_MAX;
		if ( strlen( $digits ) < strlen( $limit ) ) {
			return true;
		}

		if ( strlen( $digits ) > strlen( $limit ) ) {
			return false;
		}

		return strcmp( $digits, $limit ) <= 0;
	}

	/**
	 * Whether a value remains finite when passed to %f.
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	private static function is_float_compatible( mixed $value ): bool {
		if ( is_int( $value ) || is_bool( $value ) ) {
			return true;
		}

		if ( is_float( $value ) ) {
			return is_finite( $value );
		}

		if ( ! is_string( $value ) ) {
			return false;
		}

		$number = trim( $value );
		return is_numeric( $number ) && is_finite( (float) $number );
	}
}
