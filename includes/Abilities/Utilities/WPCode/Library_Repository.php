<?php
/**
 * Feature 112 — the WPCode cloud library and snippet packs.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\Utilities\WPCode
 * @since      0.0.43
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\WPCode;

use WPCode_Snippet;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and installs from WPCode's hosted library.
 *
 * Everything installed here lands INACTIVE and stays that way. A library snippet is third-party code
 * that will execute on this site, so the decision to run it belongs to a human looking at it, not to
 * whatever asked for the install. WPCode's own `create_snippet_from_data()` already saves without
 * setting `active`, but that is its choice and could change; this enforces it rather than inheriting
 * it.
 *
 * @since 0.0.43
 */
final class Library_Repository {

	/**
	 * Private constructor — static utility (DEC-UTILITY-STATIC-ONLY).
	 */
	private function __construct() {}

	/**
	 * WPCode's library object, loading it if this request is not an admin one.
	 *
	 * WPCode only builds `$this->library` inside `if ( is_admin() || DOING_CRON )` (ihaf.php:435),
	 * and only requires the class file under the same condition. A REST or MCP request is neither,
	 * so without this every library ability would report the library as unavailable on a site where
	 * it works perfectly well in wp-admin. The constructor only adds hooks, so building it here is
	 * cheap and has no side effect beyond what wp-admin already does.
	 *
	 * @since  0.0.43
	 * @return object|null
	 */
	private static function library() {
		if ( ! function_exists( 'wpcode' ) ) {
			return null;
		}

		if ( isset( wpcode()->library ) && is_object( wpcode()->library ) ) {
			return wpcode()->library;
		}

		/*
		 * The library leans on two more components that sit behind the same admin gate, and each
		 * fatals on null rather than degrading: file_cache backs get_data(), and library_auth is
		 * reached through get_authenticated_headers() on every API call, so installing without it
		 * dies on has_auth(). Loaded in dependency order before the library itself.
		 */
		foreach (
			array(
				'file_cache'   => array( 'WPCode_File_Cache', 'includes/class-wpcode-file-cache.php' ),
				'library_auth' => array( 'WPCode_Library_Auth', 'includes/class-wpcode-library-auth.php' ),
			) as $property => $component
		) {
			if ( null === self::load_component( $property, $component[0], $component[1] ) ) {
				return null;
			}
		}

		return self::load_component( 'library', 'WPCode_Library', 'includes/class-wpcode-library.php' );
	}

	/**
	 * Build one of WPCode's admin-only components and hang it back on the main object.
	 *
	 * Assigning it back matters: WPCode's own code reads these through `wpcode()->…`, so a local
	 * instance would leave the next internal call still facing null.
	 *
	 * @since  0.0.43
	 * @param  string $property Property on the wpcode() object.
	 * @param  string $class    Global class name.
	 * @param  string $relative Path under WPCODE_PLUGIN_PATH.
	 * @return object|null
	 */
	private static function load_component( string $property, string $class, string $relative ) {
		if ( ! function_exists( 'wpcode' ) ) {
			return null;
		}

		if ( isset( wpcode()->{$property} ) && is_object( wpcode()->{$property} ) ) {
			return wpcode()->{$property};
		}

		if ( ! class_exists( $class ) ) {
			if ( ! defined( 'WPCODE_PLUGIN_PATH' ) ) {
				return null;
			}

			$file = WPCODE_PLUGIN_PATH . $relative;

			if ( ! is_readable( $file ) ) {
				return null;
			}

			require_once $file;
		}

		if ( ! class_exists( $class ) ) {
			return null;
		}

		wpcode()->{$property} = new $class();

		return wpcode()->{$property};
	}

	/**
	 * WPCode's packs helper.
	 *
	 * A singleton reached through `WPCode_Packs::get_instance()`, NOT a property on `wpcode()` —
	 * there is no `wpcode()->packs` at all. Its class file is required only in the admin branch, so
	 * the same on-demand load applies.
	 *
	 * @since  0.0.43
	 * @return object|null
	 */
	private static function packs_helper() {
		/*
		 * get_packs() reads wpcode()->library->get_data() internally, so the packs helper is useless
		 * without the library loaded first - it fatals on null rather than returning no packs.
		 */
		if ( null === self::library() ) {
			return null;
		}

		if ( ! class_exists( 'WPCode_Packs' ) ) {
			if ( ! defined( 'WPCODE_PLUGIN_PATH' ) ) {
				return null;
			}

			$file = WPCODE_PLUGIN_PATH . 'includes/class-wpcode-packs.php';

			if ( ! is_readable( $file ) ) {
				return null;
			}

			require_once $file;
		}

		if ( ! class_exists( 'WPCode_Packs' ) || ! method_exists( 'WPCode_Packs', 'get_instance' ) ) {
			return null;
		}

		return \WPCode_Packs::get_instance();
	}

	/**
	 * Whether the library is reachable and has data.
	 *
	 * @since  0.0.43
	 * @return true|WP_Error
	 */
	public static function assert_reachable() {
		$library = self::library();

		if ( null === $library ) {
			return new WP_Error(
				'library_unavailable',
				__( 'WPCode\'s snippet library could not be loaded on this site.', 'acrossai-abilities-manager' )
			);
		}

		$data = $library->get_data();

		if ( empty( $data ) || empty( $data['snippets'] ) ) {
			return new WP_Error(
				'library_unavailable',
				__( 'WPCode returned no library data. The site may be offline, or the library request may have failed and been cached as empty.', 'acrossai-abilities-manager' )
			);
		}

		return true;
	}

	/**
	 * Search the library.
	 *
	 * @since  0.0.43
	 * @param  string $search   Free-text search, empty for everything.
	 * @param  int    $per_page Maximum rows to return.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public static function search( string $search, int $per_page ) {
		$reachable = self::assert_reachable();

		if ( is_wp_error( $reachable ) ) {
			return $reachable;
		}

		$data     = self::library()->get_data();
		$needle   = strtolower( trim( $search ) );
		$results  = array();

		foreach ( (array) $data['snippets'] as $snippet ) {
			if ( ! is_array( $snippet ) ) {
				continue;
			}

			$row = self::shape( $snippet );

			if ( '' !== $needle ) {
				$haystack = strtolower( $row['title'] . ' ' . $row['note'] . ' ' . implode( ' ', $row['categories'] ) );

				if ( false === strpos( $haystack, $needle ) ) {
					continue;
				}
			}

			$results[] = $row;

			if ( count( $results ) >= $per_page ) {
				break;
			}
		}

		return $results;
	}

	/**
	 * One library snippet by its library id.
	 *
	 * @since  0.0.43
	 * @param  int $library_id Library id.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function find( int $library_id ) {
		$reachable = self::assert_reachable();

		if ( is_wp_error( $reachable ) ) {
			return $reachable;
		}

		$data = self::library()->get_data();

		foreach ( (array) $data['snippets'] as $snippet ) {
			if ( is_array( $snippet ) && (int) ( $snippet['library_id'] ?? 0 ) === $library_id ) {
				return self::shape( $snippet );
			}
		}

		return new WP_Error(
			'unknown_library_snippet',
			sprintf(
				/* translators: %d: library snippet id. */
				__( 'No snippet with library id %d. Use search-library to find one.', 'acrossai-abilities-manager' ),
				$library_id
			)
		);
	}

	/**
	 * Install a library snippet, inactive.
	 *
	 * @since  0.0.43
	 * @param  int $library_id Library id.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function install( int $library_id ) {
		$reachable = self::assert_reachable();

		if ( is_wp_error( $reachable ) ) {
			return $reachable;
		}

		$snippet = self::library()->create_new_snippet( $library_id );

		if ( ! $snippet instanceof WPCode_Snippet ) {
			return new WP_Error(
				'install_failed',
				sprintf(
					/* translators: %d: library snippet id. */
					__( 'WPCode could not install library snippet %d. It may not exist, or the library request failed.', 'acrossai-abilities-manager' ),
					$library_id
				)
			);
		}

		return self::force_inactive( $snippet );
	}

	/**
	 * Apply a snippet pack, leaving everything it created inactive.
	 *
	 * @since  0.0.43
	 * @param  string $slug Pack slug.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function apply_pack( string $slug ) {
		$packs = self::packs_helper();

		if ( null === $packs ) {
			return new WP_Error(
				'library_unavailable',
				__( 'WPCode snippet packs could not be loaded on this site.', 'acrossai-abilities-manager' )
			);
		}

		if ( empty( $packs->find_pack( $slug ) ) ) {
			return new WP_Error(
				'unknown_pack',
				sprintf(
					/* translators: %s: pack slug. */
					__( 'No WPCode snippet pack with slug "%s". Use list-packs to see what is available.', 'acrossai-abilities-manager' ),
					$slug
				)
			);
		}

		$result = (array) $packs->install_pack( $slug );

		/*
		 * install_pack() returns its own envelope and reports success => false for every refusal,
		 * including "the current user cannot edit snippets" and "the library is not connected",
		 * without distinguishing them. Created ids are the only unambiguous signal that work happened.
		 */
		$created = array_map( 'intval', (array) ( $result['created_ids'] ?? array() ) );

		if ( empty( $created ) && empty( $result['success'] ) ) {
			return new WP_Error(
				'pack_install_failed',
				__( 'WPCode installed nothing from this pack. The library may not be connected, or every snippet in it is already installed.', 'acrossai-abilities-manager' )
			);
		}

		$installed = array();

		foreach ( $created as $id ) {
			$snippet = Snippet_Repository::find( $id );

			if ( is_wp_error( $snippet ) ) {
				continue;
			}

			$shaped = self::force_inactive( $snippet );

			if ( ! is_wp_error( $shaped ) ) {
				$installed[] = $shaped;
			}
		}

		return array(
			'pack'             => $slug,
			'installed'        => $installed,
			'installed_count'  => count( $installed ),
			'skipped_count'    => count( (array) ( $result['skipped_options'] ?? array() ) ),
			'failed_count'     => count( (array) ( $result['failed_options'] ?? array() ) ),
		);
	}

	/**
	 * Every pack, with whether it is installed.
	 *
	 * @since  0.0.43
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public static function packs() {
		$packs = self::packs_helper();

		if ( null === $packs ) {
			return new WP_Error(
				'library_unavailable',
				__( 'WPCode snippet packs could not be loaded on this site.', 'acrossai-abilities-manager' )
			);
		}

		$installed = (array) $packs->get_installed_state();
		$rows      = array();

		foreach ( (array) $packs->get_packs() as $slug => $pack ) {
			if ( ! is_array( $pack ) ) {
				continue;
			}

			$key = is_string( $slug ) ? $slug : (string) ( $pack['slug'] ?? '' );

			$rows[] = array(
				// 'name', not 'title' - that is the key get_packs() builds and the key WPCode's own
				// pack view reads. Asking for 'title' returns an empty string for every pack.
				'title'         => (string) ( $pack['name'] ?? '' ),
				'slug'          => $key,
				'description'   => (string) ( $pack['description'] ?? '' ),
				'group'         => (string) ( $pack['group'] ?? '' ),
				'snippet_count' => count( (array) ( $pack['snippets'] ?? array() ) ),
				'installed'     => ! empty( $pack['installed'] ) || ! empty( $installed[ $key ] ),
			);
		}

		return $rows;
	}

	/**
	 * Guarantee a freshly installed snippet is not running.
	 *
	 * @since  0.0.43
	 * @param  WPCode_Snippet $snippet Snippet.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function force_inactive( WPCode_Snippet $snippet ) {
		if ( $snippet->is_active() ) {
			$snippet->deactivate();
			Snippet_Repository::rebuild_cache();
		}

		$saved = Snippet_Repository::find( (int) $snippet->get_id() );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return Snippet_Repository::shape( $saved );
	}

	/**
	 * Shape one library row. Rows, never maps.
	 *
	 * @since  0.0.43
	 * @param  array<string, mixed> $snippet Library payload.
	 * @return array<string, mixed>
	 */
	private static function shape( array $snippet ): array {
		$categories = array();

		foreach ( (array) ( $snippet['categories'] ?? array() ) as $category ) {
			if ( is_scalar( $category ) ) {
				$categories[] = (string) $category;
			} elseif ( is_array( $category ) && isset( $category['slug'] ) ) {
				$categories[] = (string) $category['slug'];
			}
		}

		return array(
			'library_id' => (int) ( $snippet['library_id'] ?? 0 ),
			'title'      => (string) ( $snippet['title'] ?? '' ),
			'note'       => (string) ( $snippet['note'] ?? '' ),
			'code_type'  => (string) ( $snippet['code_type'] ?? '' ),
			'categories' => array_values( $categories ),
		);
	}
}
