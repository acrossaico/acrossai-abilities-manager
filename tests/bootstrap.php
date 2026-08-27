<?php
/**
 * PHPUnit bootstrap — loads Composer autoloader and minimal WP stubs.
 *
 * This bootstrap provides just enough WordPress function stubs to run
 * unit tests for pure-logic classes that do not require a full WP install.
 *
 * @package AcrossAI_Abilities_Manager
 */

// Define ABSPATH so files protected by `defined('ABSPATH') || exit` don't bail.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// ---------------------------------------------------------------------------
// Minimal WordPress function / class stubs
// ---------------------------------------------------------------------------

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Stub: mirrors WP sanitize_text_field behaviour for unit tests.
	 *
	 * @param  string $str Input string.
	 * @return string
	 */
	function sanitize_text_field( string $str ): string {
		return trim( wp_check_invalid_utf8( strip_tags( $str ) ) );
	}
}

if ( ! function_exists( 'wp_check_invalid_utf8' ) ) {
	/**
	 * Stub: returns the string unchanged (valid UTF-8 assumption in tests).
	 *
	 * @param  string $string Input string.
	 * @return string
	 */
	function wp_check_invalid_utf8( string $string ): string {
		return $string;
	}
}

if ( ! function_exists( '__' ) ) {
	/** Stub: returns the string unchanged (i18n not needed in unit tests). */
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/** Stub: simple HTML escaping. */
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/** Stub: simple attribute escaping. */
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/** Stub: pass-through for unit tests (no DB filtering needed). */
	function esc_url_raw( string $url, array $protocols = array() ): string {
		return $url;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/** Stub: delegates to json_encode. */
	function wp_json_encode( mixed $data, int $options = 0, int $depth = 512 ): string|false {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/** Stub: checks if value is a WP_Error instance. */
	function is_wp_error( mixed $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/** Stub: no-op hook registration for unit tests. */
	function add_action( string $hook, mixed $callback, int $priority = 10, int $args = 1 ): bool {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/** Stub: no-op filter registration for unit tests. */
	function add_filter( string $hook, mixed $callback, int $priority = 10, int $args = 1 ): bool {
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/** Stub: returns the first value unchanged (no filters registered). */
	function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/** Stub: no-op action dispatch. */
	function do_action( string $hook, mixed ...$args ): void {}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * Stub: mirrors WP wp_parse_url() for unit tests.
	 *
	 * @param  string $url       URL to parse.
	 * @param  int    $component Optional PHP_URL_* component.
	 * @return mixed
	 */
	function wp_parse_url( string $url, int $component = -1 ): mixed {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'maybe_unserialize' ) ) {
	/**
	 * Stub: mirrors WP maybe_unserialize() for unit tests.
	 *
	 * @param  mixed $data Value that may be serialized.
	 * @return mixed
	 */
	function maybe_unserialize( mixed $data ): mixed {
		if ( is_string( $data ) ) {
			$trimmed = trim( $data );
			// Cheap serialized-string sniff, matching core's intent.
			if ( preg_match( '/^([adObis]):/', $trimmed ) ) {
				return @unserialize( $trimmed ); // phpcs:ignore
			}
		}
		return $data;
	}
}

if ( ! function_exists( 'maybe_serialize' ) ) {
	/**
	 * Stub: mirrors WP maybe_serialize() for unit tests.
	 *
	 * @param  mixed $data Value to serialize when not scalar.
	 * @return mixed
	 */
	function maybe_serialize( mixed $data ): mixed {
		return ( is_array( $data ) || is_object( $data ) ) ? serialize( $data ) : $data;
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	/** Stub: strips trailing slashes. */
	function untrailingslashit( string $value ): string {
		return rtrim( $value, '/\\' );
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	/** Stub: merges args with defaults similar to WP. */
	function wp_parse_args( mixed $args, mixed $defaults = array() ): array {
		if ( is_object( $args ) ) {
			$r = get_object_vars( $args );
		} elseif ( is_array( $args ) ) {
			$r = $args;
		} else {
			parse_str( $args, $r );
		}
		return array_merge( (array) $defaults, $r );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Stub: reads from the test-owned $__acrossai_test_options global if the
	 * caller has seeded it; otherwise returns $default. Lets test files
	 * simulate specific option values (e.g. active_plugins for Feature 061
	 * Overrides_Store tests) without a full WP install.
	 */
	function get_option( string $option, mixed $default = false ): mixed {
		global $__acrossai_test_options;
		if ( is_array( $__acrossai_test_options ) && array_key_exists( $option, $__acrossai_test_options ) ) {
			return $__acrossai_test_options[ $option ];
		}
		return $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Stub: writes to the test-owned $__acrossai_test_options global so
	 * a subsequent get_option() sees the new value. Returns true when the
	 * value changed, false otherwise, mirroring WP core.
	 */
	function update_option( string $option, mixed $value, string|bool $autoload = 'yes' ): bool {
		global $__acrossai_test_options;
		if ( ! is_array( $__acrossai_test_options ) ) {
			$__acrossai_test_options = array();
		}
		$existed_before = array_key_exists( $option, $__acrossai_test_options );
		$prior          = $existed_before ? $__acrossai_test_options[ $option ] : null;
		$__acrossai_test_options[ $option ] = $value;
		return ! $existed_before || $prior !== $value;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Stub: removes the entry from $__acrossai_test_options and returns
	 * true if it was present, false otherwise.
	 */
	function delete_option( string $option ): bool {
		global $__acrossai_test_options;
		if ( ! is_array( $__acrossai_test_options ) || ! array_key_exists( $option, $__acrossai_test_options ) ) {
			return false;
		}
		unset( $__acrossai_test_options[ $option ] );
		return true;
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	/**
	 * Stub: mirrors WP sanitize_file_name() closely enough for Feature 093
	 * hardening tests. Replaces disallowed characters with `-` and collapses
	 * repeats. Real WP also normalises Unicode, but the test surface only
	 * needs the ASCII space + colon + control-char behaviours.
	 *
	 * @param  string $filename Raw filename.
	 * @return string
	 */
	function sanitize_file_name( string $filename ): string {
		$special_chars = array( '?', '[', ']', '/', '\\', '=', '<', '>', ':', ';', ',', "'", '"', '&', '$', '#', '*', '(', ')', '|', '~', '`', '!', '{', '}', '%', '+', chr( 0 ) );
		$filename      = str_replace( $special_chars, '', $filename );
		$filename      = str_replace( array( '%20', '+' ), '-', $filename );
		$filename      = preg_replace( '/[\r\n\t -]+/', '-', $filename );
		$filename      = trim( $filename, '.-_' );
		return $filename;
	}
}

if ( ! function_exists( 'wp_check_filetype' ) ) {
	/**
	 * Stub: minimal MIME-type check for Feature 093 tests. Returns the
	 * matching MIME for a hardcoded subset of common extensions and empty
	 * for anything else. Test callers should not rely on this reflecting
	 * a live wp_check_filetype()'s full mime map.
	 *
	 * @param  string $filename Basename with extension.
	 * @param  mixed  $mimes    Ignored in the stub.
	 * @return array{ext:string|false, type:string|false}
	 */
	function wp_check_filetype( string $filename, $mimes = null ): array {
		$ext_lower = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
		$map       = array(
			'txt'  => 'text/plain',
			'md'   => 'text/markdown',
			'json' => 'application/json',
			'log'  => 'text/plain',
			'css'  => 'text/css',
			'js'   => 'application/javascript',
			'html' => 'text/html',
			'htm'  => 'text/html',
			'xml'  => 'application/xml',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'php'  => 'application/x-httpd-php',
			'htaccess' => 'text/plain',
		);
		if ( isset( $map[ $ext_lower ] ) ) {
			return array( 'ext' => $ext_lower, 'type' => $map[ $ext_lower ] );
		}
		return array( 'ext' => false, 'type' => false );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Stub: lowercases and strips to [a-z0-9_-] only.
	 *
	 * Mirrors the WordPress core behavior closely enough for unit tests of
	 * key-shape sanitizers (Feature 033 Library Registry sub_group helper).
	 *
	 * @param  string $key Raw key string.
	 * @return string
	 */
	function sanitize_key( string $key ): string {
		$key = strtolower( $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'acrossai_test_site_options' ) ) {
	/**
	 * The shared site-option store for get_site_option / update_site_option
	 * stubs. Pass an associative array to merge writes; pass nothing to read
	 * the current store. Pass array() to reset.
	 *
	 * @param  array<string,mixed>|null $write Optional writes to merge; empty array clears.
	 * @return array<string,mixed>
	 */
	function acrossai_test_site_options( ?array $write = null ): array {
		static $store = array();
		if ( null !== $write ) {
			if ( array() === $write ) {
				$store = array();
			} else {
				$store = array_merge( $store, $write );
			}
		}
		return $store;
	}
}

if ( ! function_exists( 'get_site_option' ) ) {
	/**
	 * Stub: reads from the shared acrossai_test_site_options store.
	 *
	 * @param  string $option  Option name.
	 * @param  mixed  $default Default value if not set.
	 * @return mixed
	 */
	function get_site_option( string $option, mixed $default = false ): mixed {
		$store = acrossai_test_site_options();
		return array_key_exists( $option, $store ) ? $store[ $option ] : $default;
	}
}

if ( ! function_exists( 'update_site_option' ) ) {
	/**
	 * Stub: writes to the shared acrossai_test_site_options store.
	 *
	 * @param  string $option Option name.
	 * @param  mixed  $value  New value.
	 * @return bool
	 */
	function update_site_option( string $option, mixed $value ): bool {
		acrossai_test_site_options( array( $option => $value ) );
		return true;
	}
}

if ( ! function_exists( 'esc_sql' ) ) {
	/** Stub: basic SQL escaping (no DB in unit tests). */
	function esc_sql( mixed $sql ): string|array {
		return is_array( $sql )
			? array_map( 'esc_sql', $sql )
			: addslashes( (string) $sql );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	/** Stub: returns current timestamp. */
	function current_time( string $type, bool $gmt = false ): string|int {
		return 'mysql' === $type ? gmdate( 'Y-m-d H:i:s' ) : time();
	}
}

if ( ! function_exists( 'remove_accents' ) ) {
	/** Stub: passes through unchanged (transliteration not needed in unit tests). */
	function remove_accents( string $string ): string {
		return $string;
	}
}

if ( ! function_exists( 'wp_set_current_user' ) ) {
	/** Stub: no-op user context (no auth in unit tests). */
	function wp_set_current_user( int $id, string $name = '' ): mixed {
		return null;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	/** Stub: always returns 0 (no logged-in user in unit tests). */
	function get_current_user_id(): int {
		return 0;
	}
}

if ( ! function_exists( 'user_can' ) ) {
	/** Stub: returns false (no capability checks in unit tests). */
	function user_can( mixed $user, string $capability, mixed ...$args ): bool {
		return false;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/** Stub: returns false. */
	function current_user_can( string $capability, mixed ...$args ): bool {
		return false;
	}
}

if ( ! function_exists( 'wp_using_ext_object_cache' ) ) {
	/** Stub: no external object cache in unit tests. */
	function wp_using_ext_object_cache(): bool {
		return false;
	}
}

if ( ! function_exists( 'wp_cache_get' ) ) {
	/** Stub: always misses. */
	function wp_cache_get( mixed $key, string $group = '', bool $force = false, mixed &$found = null ): mixed {
		$found = false;
		return false;
	}
}

if ( ! function_exists( 'wp_cache_set' ) ) {
	/** Stub: no-op cache set. */
	function wp_cache_set( mixed $key, mixed $data, string $group = '', int $expire = 0 ): bool {
		return false;
	}
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
	/** Stub: no-op cache delete. */
	function wp_cache_delete( mixed $key, string $group = '' ): bool {
		return false;
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	/** Stub: returns false (no session in unit tests). */
	function is_user_logged_in(): bool {
		return false;
	}
}

if ( ! function_exists( 'wp_kses_data' ) ) {
	/** Stub: strips HTML entities — minimal safe pass-through for unit tests. */
	function wp_kses_data( string $data ): string {
		return strip_tags( $data );
	}
}

if ( ! function_exists( 'wp_kses' ) ) {
	/** Stub: strips all HTML. */
	function wp_kses( string $string, array $allowed_html, array $allowed_protocols = array() ): string {
		return strip_tags( $string );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	/** Stub: strips all HTML except basic post HTML. */
	function wp_kses_post( string $data ): string {
		return strip_tags( $data, '<p><a><strong><em><ul><ol><li><h1><h2><h3><h4><h5><h6><br><hr>' );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/** Stub: converts to absolute integer. */
	function absint( mixed $maybeint ): int {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	/** Stub: delegates to PHP number_format. */
	function number_format_i18n( float $number, int $decimals = 0 ): string {
		return number_format( $number, $decimals );
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	/** Stub: ensures trailing slash. */
	function trailingslashit( string $string ): string {
		return rtrim( $string, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	/** Stub: removes trailing slash. */
	function untrailingslashit( string $string ): string {
		return rtrim( $string, '/\\' );
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stub sufficient for unit tests.
	 */
	class WP_Error {
		/** @var array<string,array<mixed>> */
		private array $errors = array();
		/** @var array<string,mixed> */
		private array $error_data = array();

		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Optional data.
		 */
		public function __construct( string $code = '', string $message = '', $data = '' ) {
			if ( '' !== $code ) {
				$this->errors[ $code ][]      = $message;
				$this->error_data[ $code ]    = $data;
			}
		}

		/** @return array<string,array<mixed>> */
		public function get_error_codes(): array {
			return array_keys( $this->errors );
		}

		/** @param string $code */
		public function get_error_messages( string $code = '' ): array {
			return $code ? ( $this->errors[ $code ] ?? array() ) : array_merge( ...array_values( $this->errors ) );
		}

		/** @param string $code */
		public function get_error_data( string $code = '' ): mixed {
			return $code ? ( $this->error_data[ $code ] ?? null ) : reset( $this->error_data );
		}

		/** Returns the first error code as a string (singular form). */
		public function get_error_code(): string {
			$codes = $this->get_error_codes();
			return $codes[0] ?? '';
		}

		/**
		 * Returns the first error message as a string (singular form).
		 *
		 * Mirrors WP_Error::get_error_message() in core. Production code that
		 * unwraps a WP_Error into a response envelope calls this, so the stub
		 * needs it for those paths to be unit-testable.
		 *
		 * @param string $code Optional. Error code to retrieve the message for.
		 */
		public function get_error_message( string $code = '' ): string {
			$messages = $this->get_error_messages( $code );
			return isset( $messages[0] ) ? (string) $messages[0] : '';
		}

		/** @param string $message */
		public function add( string $code, string $message, mixed $data = '' ): void {
			$this->errors[ $code ][]   = $message;
			$this->error_data[ $code ] = $data;
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * Minimal WP_REST_Request stub: exposes set_param/get_param/has_param/get_method/get_route.
	 */
	class WP_REST_Request {
		/** @var array<string,mixed> */
		private array $params = array();
		private string $method;
		private string $route;

		public function __construct( string $method = 'GET', string $route = '' ) {
			$this->method = $method;
			$this->route  = $route;
		}

		public function set_param( string $key, mixed $value ): void {
			$this->params[ $key ] = $value;
		}

		public function get_param( string $key ): mixed {
			return $this->params[ $key ] ?? null;
		}

		public function has_param( string $key ): bool {
			return array_key_exists( $key, $this->params );
		}

		public function get_method(): string {
			return $this->method;
		}

		public function get_route(): string {
			return $this->route;
		}
	}
}

if ( ! function_exists( 'wp_rand' ) ) {
	/** Feature 067 stub — mirrors WordPress wp_rand. */
	function wp_rand( int $min = 0, int $max = 0 ): int {
		if ( 0 === $max ) {
			$max = mt_getrandmax();
		}
		return mt_rand( $min, $max );
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/** Feature 067 stub — in-memory transient store. */
	function get_transient( string $key ) {
		global $acrossai_transients;
		return $acrossai_transients[ $key ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/** Feature 067 stub — in-memory transient store. */
	function set_transient( string $key, $value, int $expiration = 0 ): bool {
		global $acrossai_transients;
		$acrossai_transients[ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/** Feature 067 stub — in-memory transient store. */
	function delete_transient( string $key ): bool {
		global $acrossai_transients;
		unset( $acrossai_transients[ $key ] );
		return true;
	}
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 60 * 60 );
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/** Feature 094 stub — strips leading slashes WP core adds via magic-quotes. */
	function wp_unslash( $value ) {
		if ( is_string( $value ) ) {
			return stripslashes( $value );
		}
		return $value;
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	/**
	 * Feature 094 stub — recursive mkdir with mode 0755. Returns true on
	 * success (or if the directory already exists), false on failure.
	 */
	function wp_mkdir_p( string $target ): bool {
		if ( is_dir( $target ) ) {
			return true;
		}
		return @mkdir( $target, 0755, true ) || is_dir( $target );
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	/** Feature 094 stub — native unlink wrapped. */
	function wp_delete_file( string $file ): void {
		if ( is_file( $file ) ) {
			@unlink( $file );
		}
	}
}

if ( ! function_exists( 'wp_get_current_user' ) ) {
	/**
	 * Feature 094 stub — returns a fake current user with the fields
	 * Audit_Trail reads (user_email, ID). Tests can override by seeding
	 * $__acrossai_test_current_user before invoking the SUT.
	 */
	function wp_get_current_user(): object {
		global $__acrossai_test_current_user;
		if ( is_object( $__acrossai_test_current_user ) ) {
			return $__acrossai_test_current_user;
		}
		return (object) array( 'ID' => 0, 'user_email' => '' );
	}
}

if ( ! class_exists( 'WP_Filesystem_Base' ) ) {
	/**
	 * Feature 094 stub — minimal abstract for `instanceof` checks the
	 * plugin's Wp_Filesystem_Init::get() uses. The concrete
	 * Test_Fake_WP_Filesystem below is the actual test-time transport.
	 */
	abstract class WP_Filesystem_Base {}
}

if ( ! class_exists( 'Test_Fake_WP_Filesystem' ) ) {
	/**
	 * Native-PHP-backed WP_Filesystem shim for behavioural tests. Implements
	 * the subset of the WP_Filesystem interface that Audit_Trail and the
	 * ability classes actually call. Tests seed $wp_filesystem with an
	 * instance of this before calling the SUT — Wp_Filesystem_Init::get()
	 * then short-circuits at its `instanceof` check.
	 */
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- test helper co-located with WP shims
	class Test_Fake_WP_Filesystem extends WP_Filesystem_Base {
		public function exists( string $file ): bool {
			return file_exists( $file );
		}
		public function is_file( string $file ): bool {
			return is_file( $file );
		}
		public function is_dir( string $path ): bool {
			return is_dir( $path );
		}
		public function size( string $file ) {
			return is_file( $file ) ? filesize( $file ) : 0;
		}
		public function get_contents( string $file ) {
			return is_file( $file ) ? file_get_contents( $file ) : false;
		}
		public function put_contents( string $file, string $contents, $mode = false ): bool {
			$ok = false !== file_put_contents( $file, $contents );
			if ( $ok && is_int( $mode ) ) {
				@chmod( $file, $mode );
			}
			return $ok;
		}
		public function copy( string $source, string $destination, $overwrite = false, $mode = false ): bool {
			if ( ! $overwrite && file_exists( $destination ) ) {
				return false;
			}
			$ok = copy( $source, $destination );
			if ( $ok && is_int( $mode ) ) {
				@chmod( $destination, $mode );
			}
			return $ok;
		}
		public function mkdir( string $path, $chmod = false, $chown = false, $chgrp = false ): bool {
			return @mkdir( $path, is_int( $chmod ) ? $chmod : 0755, true );
		}
		public function rmdir( string $path, bool $recursive = false ): bool {
			if ( ! is_dir( $path ) ) {
				return false;
			}
			if ( $recursive ) {
				$iter = new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ),
					\RecursiveIteratorIterator::CHILD_FIRST
				);
				foreach ( $iter as $entry ) {
					if ( $entry->isDir() ) {
						@rmdir( $entry->getPathname() );
					} else {
						@unlink( $entry->getPathname() );
					}
				}
			}
			return @rmdir( $path );
		}
		public function delete( string $file, bool $recursive = false, $type = false ): bool {
			if ( is_dir( $file ) ) {
				return $this->rmdir( $file, $recursive );
			}
			return @unlink( $file );
		}
		public function dirlist( string $path, bool $include_hidden = true, bool $recursive = false ): array {
			if ( ! is_dir( $path ) ) {
				return array();
			}
			$out     = array();
			$entries = @scandir( $path );
			foreach ( (array) $entries as $name ) {
				if ( '.' === $name || '..' === $name ) {
					continue;
				}
				if ( ! $include_hidden && '.' === $name[0] ) {
					continue;
				}
				$full     = $path . DIRECTORY_SEPARATOR . $name;
				$out[ $name ] = array(
					'name' => $name,
					'type' => is_dir( $full ) ? 'd' : 'f',
					'size' => is_file( $full ) ? filesize( $full ) : 0,
				);
			}
			return $out;
		}
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Stub: mirrors WP wp_strip_all_tags — strip every tag and also drop any
	 * script/style content. Feature 066 outline uses this for text previews;
	 * unit tests need the same behaviour without a WP install.
	 *
	 * @param string $text          Raw text/HTML.
	 * @param bool   $remove_breaks If true, collapse newlines/tabs to spaces.
	 * @return string
	 */
	function wp_strip_all_tags( string $text, bool $remove_breaks = false ): string {
		$text = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
		$text = strip_tags( $text );
		if ( $remove_breaks ) {
			$text = (string) preg_replace( '/[\r\n\t ]+/', ' ', $text );
		}
		return trim( $text );
	}
}

if ( ! function_exists( 'wp_get_object_terms' ) ) {
	/**
	 * Stub for the block-editor Db helper tests. Returns an empty term list
	 * so `to_row()` implementations that call `wp_get_object_terms` to
	 * resolve a theme term don't require a live taxonomy layer.
	 *
	 * @param int|int[]   $object_ids Ignored.
	 * @param string      $taxonomy   Ignored.
	 * @param array<mixed> $args      Ignored.
	 * @return array<mixed>
	 */
	function wp_get_object_terms( $object_ids, $taxonomy, array $args = array() ): array {
		return array();
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * Stub for helpers that read post meta (pattern sync status, active
	 * variation marker). Returns '' for any lookup. Tests that need a specific
	 * value can override by defining $__acrossai_test_post_meta[$post_id][$key].
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Whether to return a single scalar (mirrored, but ignored in stub).
	 * @return mixed
	 */
	function get_post_meta( int $post_id, string $key = '', bool $single = false ): mixed {
		global $__acrossai_test_post_meta;
		if ( is_array( $__acrossai_test_post_meta )
			&& isset( $__acrossai_test_post_meta[ $post_id ][ $key ] ) ) {
			return $__acrossai_test_post_meta[ $post_id ][ $key ];
		}
		return '';
	}
}

if ( ! function_exists( 'get_stylesheet' ) ) {
	/** Stub: return an empty stylesheet so is_active_theme comparisons resolve to false. */
	function get_stylesheet(): string {
		return '';
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	/** Stub: returns an empty array (no seeded DB posts in unit-test bootstrap). */
	function get_posts( array $args = array() ): array {
		return array();
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Data-bag stub for the four block-editor Db helpers' `to_row()` methods.
	 * Only public properties — no behaviour, no factory. Tests instantiate this
	 * directly and set the fields the SUT reads.
	 */
	class WP_Post {
		public int $ID = 0;
		public string $post_content = '';
		public string $post_title = '';
		public string $post_name = '';
		public string $post_status = 'publish';
		public string $post_type = '';
		public string $post_excerpt = '';
		public string $post_modified_gmt = '';
		public int $post_parent = 0;
		public int $post_author = 0;
	}
}

if ( ! defined( 'FS_CHMOD_FILE' ) ) {
	define( 'FS_CHMOD_FILE', 0644 );
}
if ( ! defined( 'FS_CHMOD_DIR' ) ) {
	define( 'FS_CHMOD_DIR', 0755 );
}
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	// Point tests' WP_CONTENT_DIR into a unique temp workspace so
	// Audit_Trail's real backup + log paths land inside the test sandbox.
	// A single per-test-run dir keeps the ability × trail matrix consistent.
	define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/acrossai-094-tests-' . getmypid() );
	if ( ! is_dir( WP_CONTENT_DIR ) ) {
		mkdir( WP_CONTENT_DIR, 0777, true );
	}
}

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
	/**
	 * Alias: in unit-only mode WP_UnitTestCase is a plain PHPUnit TestCase.
	 */
	class WP_UnitTestCase extends \PHPUnit\Framework\TestCase {
		/**
		 * Assert that $value is a WP_Error instance.
		 *
		 * @param mixed  $value   Value to check.
		 * @param string $message Optional failure message.
		 */
		public static function assertWPError( $value, string $message = '' ): void {
			static::assertInstanceOf( WP_Error::class, $value, $message );
		}
	}
}
