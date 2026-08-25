<?php
/**
 * Secret redactor for file-manager read responses (Feature 092).
 *
 * Scrubs sensitive content from `read-file` and `read-debug-log` responses
 * before returning them to the caller. Ships eight built-in pattern classes;
 * admins toggle each on/off from the File Manager settings tab and may add
 * custom literal strings.
 *
 * Pattern regexes are hardcoded — only their on/off state and the custom
 * literal list are user-editable. This keeps a bad regex from breaking the
 * redactor.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Utilities
 * @since      0.1.0
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Utilities;

defined( 'ABSPATH' ) || exit;

/**
 * Secret_Redactor utility.
 */
final class Secret_Redactor {

	/**
	 * Option name.
	 */
	public const OPTION = 'acrossai_file_manager_redaction_config';

	/**
	 * The literal replacement token used everywhere.
	 */
	public const TOKEN = '***REDACTED***';

	/**
	 * WordPress core secret constant names covered by the wp_credentials pattern.
	 *
	 * @var array<int,string>
	 */
	private const WP_SECRET_CONSTANTS = array(
		'DB_PASSWORD',
		'DB_USER',
		'AUTH_KEY',
		'SECURE_AUTH_KEY',
		'LOGGED_IN_KEY',
		'NONCE_KEY',
		'AUTH_SALT',
		'SECURE_AUTH_SALT',
		'LOGGED_IN_SALT',
		'NONCE_SALT',
		'SECRET_KEY',
	);

	/**
	 * Default redaction config applied on activation.
	 *
	 * @return array{patterns: array<string,bool>, custom_literals: array<int,string>}
	 */
	public static function default_config(): array {
		return array(
			'patterns'        => array(
				'wp_credentials' => true,
				'stripe'         => true,
				'aws_access_key' => true,
				'openai'         => true,
				'anthropic'      => true,
				'github'         => true,
				'sendgrid'       => true,
				'jwt'            => false,
			),
			'custom_literals' => array(),
		);
	}

	/* -------------------------------------------------------------------- */
	/* Config accessors                                                      */
	/* -------------------------------------------------------------------- */

	/**
	 * Read current config, merged over the defaults so any missing keys are
	 * always populated with the shipping default.
	 *
	 * @return array{patterns: array<string,bool>, custom_literals: array<int,string>}
	 */
	public static function get_config(): array {
		$raw     = get_option( self::OPTION, array() );
		$default = self::default_config();

		if ( ! is_array( $raw ) ) {
			return $default;
		}

		$patterns = isset( $raw['patterns'] ) && is_array( $raw['patterns'] ) ? $raw['patterns'] : array();
		$literals = isset( $raw['custom_literals'] ) && is_array( $raw['custom_literals'] ) ? $raw['custom_literals'] : array();

		// Merge over the default map so a newly-added pattern gets its
		// shipping default until the admin saves.
		$merged_patterns = $default['patterns'];
		foreach ( $patterns as $id => $enabled ) {
			if ( isset( $default['patterns'][ $id ] ) ) {
				$merged_patterns[ $id ] = (bool) $enabled;
			}
		}

		return array(
			'patterns'        => $merged_patterns,
			'custom_literals' => self::sanitize_literals( $literals ),
		);
	}

	/**
	 * Persist a config.
	 *
	 * @param array{patterns?: array<string,bool>, custom_literals?: array<int,string>} $config New config.
	 * @return bool
	 */
	public static function set_config( array $config ): bool {
		$default = self::default_config();
		$out     = array(
			'patterns'        => $default['patterns'],
			'custom_literals' => array(),
		);
		if ( isset( $config['patterns'] ) && is_array( $config['patterns'] ) ) {
			foreach ( $config['patterns'] as $id => $enabled ) {
				if ( isset( $default['patterns'][ $id ] ) ) {
					$out['patterns'][ $id ] = (bool) $enabled;
				}
			}
		}
		if ( isset( $config['custom_literals'] ) && is_array( $config['custom_literals'] ) ) {
			$out['custom_literals'] = self::sanitize_literals( $config['custom_literals'] );
		}
		return (bool) update_option( self::OPTION, $out );
	}

	/**
	 * Enumerate every built-in pattern class for the admin UI.
	 *
	 * @return array<string,array{label: string, description: string, default_enabled: bool}>
	 */
	public static function available_patterns(): array {
		return array(
			'wp_credentials' => array(
				'label'           => __( 'WordPress credentials (wp-config.php)', 'acrossai-abilities-manager' ),
				'description'     => __( "Replaces the value of define('DB_PASSWORD', …), define('DB_USER', …) and all eight WordPress auth keys/salts.", 'acrossai-abilities-manager' ),
				'default_enabled' => true,
			),
			'stripe'         => array(
				'label'           => __( 'Stripe API keys', 'acrossai-abilities-manager' ),
				'description'     => __( 'Detects sk_live_, sk_test_, rk_live_, rk_test_ keys anywhere in the content.', 'acrossai-abilities-manager' ),
				'default_enabled' => true,
			),
			'aws_access_key' => array(
				'label'           => __( 'AWS access key IDs', 'acrossai-abilities-manager' ),
				'description'     => __( 'Detects AKIA-prefixed 20-character access key IDs.', 'acrossai-abilities-manager' ),
				'default_enabled' => true,
			),
			'openai'         => array(
				'label'           => __( 'OpenAI API keys', 'acrossai-abilities-manager' ),
				'description'     => __( "Detects sk- prefixed OpenAI-style keys (48+ characters).", 'acrossai-abilities-manager' ),
				'default_enabled' => true,
			),
			'anthropic'      => array(
				'label'           => __( 'Anthropic API keys', 'acrossai-abilities-manager' ),
				'description'     => __( 'Detects sk-ant- prefixed Anthropic keys.', 'acrossai-abilities-manager' ),
				'default_enabled' => true,
			),
			'github'         => array(
				'label'           => __( 'GitHub tokens', 'acrossai-abilities-manager' ),
				'description'     => __( 'Detects ghp_, gho_, ghu_, ghs_, ghr_ prefixed 40-character tokens.', 'acrossai-abilities-manager' ),
				'default_enabled' => true,
			),
			'sendgrid'       => array(
				'label'           => __( 'SendGrid API keys', 'acrossai-abilities-manager' ),
				'description'     => __( 'Detects SG.xxx.yyy formatted keys.', 'acrossai-abilities-manager' ),
				'default_enabled' => true,
			),
			'jwt'            => array(
				'label'           => __( 'JWT tokens', 'acrossai-abilities-manager' ),
				'description'     => __( 'Detects three-segment base64url JWTs. Off by default because legitimate token display content can match.', 'acrossai-abilities-manager' ),
				'default_enabled' => false,
			),
		);
	}

	/* -------------------------------------------------------------------- */
	/* Scrubbing                                                             */
	/* -------------------------------------------------------------------- */

	/**
	 * Scrub sensitive content from arbitrary text.
	 *
	 * @param string $content Original file content (text; do not call on binary bytes).
	 * @return array{text: string, redacted: bool, redaction_count: int}
	 */
	public static function scrub( string $content ): array {
		if ( '' === $content ) {
			return array(
				'text'            => '',
				'redacted'        => false,
				'redaction_count' => 0,
			);
		}

		$config = self::get_config();
		$count  = 0;
		$text   = $content;

		if ( ! empty( $config['patterns']['wp_credentials'] ) ) {
			$text = self::apply_wp_credentials( $text, $count );
		}
		foreach ( self::third_party_patterns() as $id => $pattern_info ) {
			if ( empty( $config['patterns'][ $id ] ) ) {
				continue;
			}
			$text = self::apply_regex( $text, $pattern_info['regex'], $pattern_info['replacement'], $count );
		}

		foreach ( $config['custom_literals'] as $literal ) {
			if ( '' === $literal ) {
				continue;
			}
			$replaced = str_replace( $literal, self::TOKEN, $text, $lit_count );
			if ( $lit_count > 0 ) {
				$text  = $replaced;
				$count = $count + (int) $lit_count;
			}
		}

		return array(
			'text'            => $text,
			'redacted'        => $count > 0,
			'redaction_count' => $count,
		);
	}

	/* -------------------------------------------------------------------- */
	/* Internal helpers                                                      */
	/* -------------------------------------------------------------------- */

	/**
	 * Apply the WordPress-credentials pattern. Replaces the value in
	 * define('NAME', 'value') for every SECRET_CONSTANT.
	 *
	 * @param string $content Content to scrub.
	 * @param int    $count   Running total (by reference).
	 * @return string Scrubbed content.
	 */
	private static function apply_wp_credentials( string $content, int &$count ): string {
		$names = implode( '|', array_map( 'preg_quote', self::WP_SECRET_CONSTANTS ) );
		$regex = '/(define\s*\(\s*[\'"](?:' . $names . ')[\'"]\s*,\s*)([\'"])([^\'"\r\n]*)\2(\s*[,\)])/';
		return self::apply_regex(
			$content,
			$regex,
			'$1$2' . self::TOKEN . '$2$4',
			$count
		);
	}

	/**
	 * The third-party API-key pattern map.
	 *
	 * @return array<string,array{regex: string, replacement: string}>
	 */
	private static function third_party_patterns(): array {
		return array(
			'stripe'         => array(
				'regex'       => '/\b(?:sk|rk)_(?:live|test)_[A-Za-z0-9]{24,}/',
				'replacement' => self::TOKEN,
			),
			'aws_access_key' => array(
				'regex'       => '/\bAKIA[0-9A-Z]{16}\b/',
				'replacement' => 'AKIA' . self::TOKEN,
			),
			// Anthropic must run BEFORE openai because sk-ant-... would also match sk-...{48+}.
			'anthropic'      => array(
				'regex'       => '/\bsk-ant-[A-Za-z0-9_\-]{80,}/',
				'replacement' => 'sk-ant-' . self::TOKEN,
			),
			'openai'         => array(
				'regex'       => '/\bsk-[A-Za-z0-9]{48,}/',
				'replacement' => 'sk-' . self::TOKEN,
			),
			'github'         => array(
				'regex'       => '/\bgh[posru]_[A-Za-z0-9]{36}\b/',
				'replacement' => 'gh_' . self::TOKEN,
			),
			'sendgrid'       => array(
				'regex'       => '/\bSG\.[A-Za-z0-9_\-]{22}\.[A-Za-z0-9_\-]{43}\b/',
				'replacement' => 'SG.' . self::TOKEN,
			),
			'jwt'            => array(
				'regex'       => '/\beyJ[A-Za-z0-9_\-]+\.eyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+/',
				'replacement' => 'eyJ' . self::TOKEN,
			),
		);
	}

	/**
	 * Apply a regex replacement, incrementing $count by the number of
	 * substitutions made.
	 *
	 * @param string $content     Content to scrub.
	 * @param string $regex       PCRE pattern.
	 * @param string $replacement Replacement string.
	 * @param int    $count       Running total (by reference).
	 * @return string
	 */
	private static function apply_regex( string $content, string $regex, string $replacement, int &$count ): string {
		$replaced = preg_replace( $regex, $replacement, $content, -1, $hits );
		if ( null === $replaced ) {
			// PCRE error — return the original content untouched.
			return $content;
		}
		if ( $hits > 0 ) {
			$count = $count + (int) $hits;
		}
		return $replaced;
	}

	/**
	 * Sanitize the custom-literals list: string-only entries, trimmed, non-empty, deduped.
	 *
	 * @param array<int|string,mixed> $raw Raw input.
	 * @return array<int,string>
	 */
	private static function sanitize_literals( array $raw ): array {
		$out = array();
		foreach ( $raw as $entry ) {
			if ( ! is_string( $entry ) ) {
				continue;
			}
			$trimmed = trim( $entry );
			if ( '' === $trimmed ) {
				continue;
			}
			$out[] = $trimmed;
		}
		return array_values( array_unique( $out ) );
	}
}
