<?php
/**
 * Detects which MCP transport plugins are present on the site.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Utilities
 * @since      0.0.34
 */

namespace AcrossAI_Abilities_Manager\Includes\Utilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Reports the presence of the two supported MCP transports.
 *
 * Abilities registered by this plugin do nothing until a transport exposes them
 * to an AI client. Two transports are supported and they are detected
 * differently on purpose:
 *
 * - AcrossAI MCP Manager is a wordpress.org plugin, so a plugin-file check is
 *   authoritative.
 * - The WordPress MCP Adapter ships from GitHub and is frequently *bundled
 *   inside another plugin* rather than installed standalone. A plugin-file
 *   check therefore produces a false negative for a perfectly working install,
 *   which would strand an administrator on the wizard's instructions screen.
 *   Its class is probed first, and the plugin file only as a fallback.
 *
 * @since 0.0.34
 */
class AcrossAI_Mcp_Transport_Detector {

	/**
	 * Identifier for the recommended transport.
	 *
	 * @since 0.0.34
	 * @var   string
	 */
	public const TRANSPORT_MCP_MANAGER = 'mcp-manager';

	/**
	 * Identifier for the alternative transport.
	 *
	 * @since 0.0.34
	 * @var   string
	 */
	public const TRANSPORT_MCP_ADAPTER = 'mcp-adapter';

	/**
	 * Plugin basename of the recommended transport.
	 *
	 * @since 0.0.34
	 * @var   string
	 */
	public const MCP_MANAGER_BASENAME = 'acrossai-mcp-manager/acrossai-mcp-manager.php';

	/**
	 * State returned when the transport is not installed at all.
	 *
	 * @since 0.0.34
	 * @var   string
	 */
	public const STATE_MISSING = 'missing';

	/**
	 * State returned when the transport is installed but not active.
	 *
	 * @since 0.0.34
	 * @var   string
	 */
	public const STATE_INACTIVE = 'inactive';

	/**
	 * State returned when the transport is available and running.
	 *
	 * @since 0.0.34
	 * @var   string
	 */
	public const STATE_ACTIVE = 'active';

	/**
	 * Detect the state of a supported transport.
	 *
	 * @since  0.0.34
	 * @param  string $transport One of the TRANSPORT_* constants.
	 * @return string One of the STATE_* constants. Unknown transports report missing.
	 */
	public static function detect( string $transport ): string {
		if ( self::TRANSPORT_MCP_MANAGER === $transport ) {
			return self::detect_mcp_manager();
		}

		if ( self::TRANSPORT_MCP_ADAPTER === $transport ) {
			return self::detect_mcp_adapter();
		}

		return self::STATE_MISSING;
	}

	/**
	 * Detect every supported transport at once.
	 *
	 * @since  0.0.34
	 * @return array<string, string> Transport identifier => STATE_* constant.
	 */
	public static function detect_all(): array {
		return array(
			self::TRANSPORT_MCP_MANAGER => self::detect( self::TRANSPORT_MCP_MANAGER ),
			self::TRANSPORT_MCP_ADAPTER => self::detect( self::TRANSPORT_MCP_ADAPTER ),
		);
	}

	/**
	 * Detect the recommended transport by plugin file.
	 *
	 * @since  0.0.34
	 * @return string One of the STATE_* constants.
	 */
	private static function detect_mcp_manager(): string {
		self::require_plugin_api();

		if ( is_plugin_active( self::MCP_MANAGER_BASENAME ) ) {
			return self::STATE_ACTIVE;
		}

		if ( function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( self::MCP_MANAGER_BASENAME ) ) {
			return self::STATE_ACTIVE;
		}

		$installed = function_exists( 'get_plugins' ) ? get_plugins() : array();

		return array_key_exists( self::MCP_MANAGER_BASENAME, $installed )
			? self::STATE_INACTIVE
			: self::STATE_MISSING;
	}

	/**
	 * Detect the alternative transport, preferring a class probe.
	 *
	 * The class probe is authoritative because it is true whether the adapter is
	 * a standalone plugin or vendored inside another plugin. Only when no class
	 * is loaded do we fall back to looking for a standalone plugin file, which
	 * distinguishes "installed but switched off" from "not here at all".
	 *
	 * @since  0.0.34
	 * @return string One of the STATE_* constants.
	 */
	private static function detect_mcp_adapter(): string {
		foreach ( self::mcp_adapter_class_candidates() as $class_name ) {
			if ( class_exists( $class_name, false ) ) {
				return self::STATE_ACTIVE;
			}
		}

		self::require_plugin_api();

		$installed = function_exists( 'get_plugins' ) ? get_plugins() : array();

		foreach ( self::mcp_adapter_basename_candidates() as $basename ) {
			if ( array_key_exists( $basename, $installed ) ) {
				return self::STATE_INACTIVE;
			}
		}

		return self::STATE_MISSING;
	}

	/**
	 * Class names that indicate the MCP Adapter is loaded.
	 *
	 * @since  0.0.34
	 * @return string[] Fully-qualified class names, filterable.
	 */
	private static function mcp_adapter_class_candidates(): array {
		$candidates = array(
			'WP\\MCP\\Core\\McpAdapter',
			'WP\\MCP\\Core\\McpServer',
		);

		/**
		 * Filters the class names used to detect a loaded MCP Adapter.
		 *
		 * Allows sites that vendor the adapter under a different namespace to be
		 * recognised without patching the plugin.
		 *
		 * @since 0.0.34
		 * @param string[] $candidates Fully-qualified class names.
		 */
		return (array) apply_filters( 'acrossai_mcp_adapter_class_candidates', $candidates );
	}

	/**
	 * Plugin basenames that may hold a standalone MCP Adapter install.
	 *
	 * @since  0.0.34
	 * @return string[] Plugin basenames, filterable.
	 */
	private static function mcp_adapter_basename_candidates(): array {
		$candidates = array(
			'mcp-adapter/mcp-adapter.php',
			'wordpress-mcp-adapter/mcp-adapter.php',
		);

		/**
		 * Filters the plugin basenames checked for a standalone MCP Adapter.
		 *
		 * @since 0.0.34
		 * @param string[] $candidates Plugin basenames.
		 */
		return (array) apply_filters( 'acrossai_mcp_adapter_basename_candidates', $candidates );
	}

	/**
	 * Ensure the WordPress plugin API is loaded.
	 *
	 * Detection runs on front-end and REST requests too, where
	 * wp-admin/includes/plugin.php is not loaded by default.
	 *
	 * @since  0.0.34
	 * @return void
	 */
	private static function require_plugin_api(): void {
		if ( ! function_exists( 'is_plugin_active' ) && defined( 'ABSPATH' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}
}
