<?php
/**
 * Feature 127 — one backup suite per plugin, as every other integration is arranged.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.35
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Backup_Suites extends WP_UnitTestCase {

	/**
	 * @return array<string, array<string, string>> suite => class => slug
	 */
	private static function suites(): array {
		return array(
			'UpdraftPlus' => array(
				'Get_Status'          => 'updraftplus/get-status',
				'List_Backups'        => 'updraftplus/list-backups',
				'Get_Backup'          => 'updraftplus/get-backup',
				'Check_Exposure'      => 'updraftplus/check-exposure',
				'Get_Backup_Progress' => 'updraftplus/get-backup-progress',
				'Start_Backup'        => 'updraftplus/start-backup',
				'Delete_Backup'       => 'updraftplus/delete-backup',
				'Restore_Backup'      => 'updraftplus/restore-backup',
			),
			'AllInOne'    => array(
				'Get_Status'          => 'all-in-one/get-status',
				'List_Backups'        => 'all-in-one/list-backups',
				'Get_Backup'          => 'all-in-one/get-backup',
				'Check_Exposure'      => 'all-in-one/check-exposure',
				'Get_Export_Progress' => 'all-in-one/get-export-progress',
				'Start_Export'        => 'all-in-one/start-export',
				'Set_Backup_Label'    => 'all-in-one/set-backup-label',
				'Delete_Backup'       => 'all-in-one/delete-backup',
				'Restore_Backup'      => 'all-in-one/restore-backup',
			),
		);
	}

	/**
	 * Each suite declares its own slugs and goes through its own base.
	 */
	public function test_every_ability_declares_its_slug(): void {
		$bases = array( 'UpdraftPlus' => 'Base_UpdraftPlus_Ability', 'AllInOne' => 'Base_All_In_One_Ability' );

		foreach ( self::suites() as $suite => $abilities ) {
			foreach ( $abilities as $class => $slug ) {
				$src = self::read( self::dir( $suite ) . $class . '.php' );

				$this->assertNotSame( '', $src, "{$suite}/{$class}.php is missing." );
				$this->assertStringContainsString( "return '{$slug}';", $src, "{$suite}/{$class} must declare {$slug}." );
				$this->assertStringContainsString( "extends {$bases[ $suite ]}", $src, "{$suite}/{$class} must use its own base." );
			}
		}
	}

	/**
	 * One plugin per suite, and the suites never reach into each other.
	 *
	 * This is the whole point of the rework. The previous shape put both plugins behind one
	 * interface and one "backups" tab, which made two genuinely different plugins look
	 * interchangeable and forced every real difference to be reported as a capability flag.
	 */
	public function test_the_suites_are_independent(): void {
		foreach ( array_keys( self::suites() ) as $suite ) {
			$other = 'UpdraftPlus' === $suite ? 'AllInOne' : 'UpdraftPlus';

			foreach ( glob( self::dir( $suite ) . '*.php' ) as $file ) {
				$code = self::code_only( self::read( $file ) );
				$name = basename( $file );

				$this->assertStringNotContainsString(
					"Abilities\\{$other}",
					$code,
					"{$suite}/{$name} must not reach into the other backup suite."
				);
			}
		}
	}

	/**
	 * The shared provider layer is gone, not merely unused.
	 */
	public function test_the_provider_layer_no_longer_exists(): void {
		foreach ( array( 'Backups/Backup_Provider.php', 'Backups/Provider_Registry.php', 'Backups/UpdraftPlus_Provider.php', 'Backups/All_In_One_Provider.php', 'Backups/Backup_Guard.php' ) as $gone ) {
			$this->assertFileDoesNotExist(
				dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/' . $gone,
				"{$gone} belonged to the retired shared layer and must not come back."
			);
		}

		$this->assertFileDoesNotExist( dirname( __DIR__, 3 ) . '/includes/Abilities/Backups', 'The unified backups suite must be gone.' );
		$this->assertFileDoesNotExist( dirname( __DIR__, 3 ) . '/includes/Abilities/Toolset/Backups.php', 'toolset/backups must stay retired.' );
	}

	/**
	 * No ability still advertises the retired backups/* slugs.
	 *
	 * 0.0.34 shipped those names. They are replaced outright rather than aliased, so a stale
	 * reference in a description would point a caller at something that no longer exists.
	 */
	public function test_no_retired_slug_survives_in_user_facing_text(): void {
		foreach ( array_keys( self::suites() ) as $suite ) {
			foreach ( glob( self::dir( $suite ) . '*.php' ) as $file ) {
				$this->assertDoesNotMatchRegularExpression(
					'#\bbackups/[a-z-]+#',
					self::read( $file ),
					basename( $file ) . ' still names a retired backups/* slug.'
				);
			}

			foreach ( glob( self::util( $suite ) . '*.php' ) as $file ) {
				// All-in-One's OWN REST route is /ai1wm/v1/backups/{name}/restore and is not a slug.
				$text = str_replace( 'ai1wm/v1/backups/', '', self::read( $file ) );

				$this->assertDoesNotMatchRegularExpression(
					'#\bbackups/[a-z-]+#',
					$text,
					basename( $file ) . ' still names a retired backups/* slug.'
				);
			}
		}
	}

	/**
	 * Each suite sits in its own tab and its own category.
	 */
	public function test_each_suite_has_its_own_tab_and_category(): void {
		$expected = array(
			'UpdraftPlus' => array( 'Base_UpdraftPlus_Ability', 'updraftplus', 'acrossai-updraftplus' ),
			'AllInOne'    => array( 'Base_All_In_One_Ability', 'all-in-one-wp-migration', 'acrossai-all-in-one' ),
		);

		foreach ( $expected as $suite => list( $base, $tab, $category ) ) {
			$src = self::code_only( self::read( self::dir( $suite ) . $base . '.php' ) );

			$this->assertStringContainsString( "TAB_GROUP = '{$tab}'", $src, "{$suite} must sit in its own tab." );
			$this->assertStringContainsString( "CATEGORY = '{$category}'", $src, "{$suite} must have its own category." );
			$this->assertMatchesRegularExpression(
				'/final protected function permission_floor\(\): string \{\s*return \'manage_options\';/',
				$src,
				"{$suite} must require an administrator, with no way for a subclass to widen it."
			);
		}
	}

	/**
	 * Each plugin gets a Toolset named after it, like every other integration.
	 */
	public function test_each_plugin_has_its_own_toolset(): void {
		$expected = array(
			'UpdraftPlus.php' => array( 'updraftplus', 'toolset/updraftplus' ),
			'All_In_One.php'  => array( 'all-in-one-wp-migration', 'toolset/all-in-one-wp-migration' ),
		);

		foreach ( $expected as $file => list( $group, $slug ) ) {
			$src = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/Toolset/' . $file );

			$this->assertNotSame( '', $src, "Toolset/{$file} is missing." );
			$this->assertStringContainsString( "return '{$group}';", $src );
			$this->assertStringContainsString( "return '{$slug}';", $src );
		}
	}

	/**
	 * A per-plugin Toolset must opt out of the server type's default set.
	 *
	 * Two different lists: what is REGISTERED as an ability, and what is DECLARED into the
	 * `acrossai` server type. `declare_server_type_tool()` deliberately never consults the ability
	 * registry — an earlier version did, and on REST requests the transport resolved its type
	 * registry before `wp_abilities_api_init`, so every Toolset dropped out. It answers from a
	 * per-class constant instead.
	 *
	 * Which means a per-plugin Toolset has to say so itself. Measured: with UpdraftPlus and
	 * All-in-One deactivated, both were still being declared into the type and offered in the Tools
	 * picker, while Elementor's and Rank Math's correctly were not — because those two override
	 * this and these two, generated from the always-present Cache Toolset, inherited `true`.
	 *
	 * A default set that changes when a plugin is activated is the specific harm: a connected MCP
	 * client caches tools/list and has no way to be told it moved.
	 */
	public function test_per_plugin_toolsets_are_not_server_type_defaults(): void {
		foreach ( array( 'UpdraftPlus.php', 'All_In_One.php' ) as $file ) {
			$src = self::code_only( self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/Toolset/' . $file ) );

			$this->assertMatchesRegularExpression(
				'/protected function is_server_type_default\(\): bool \{\s*return false;/',
				$src,
				"Toolset/{$file} drives one plugin, so it must not be in the default set — the same override Elementor and Rank Math carry."
			);
		}
	}

	/**
	 * Only what each plugin can actually do is offered.
	 *
	 * UpdraftPlus stores no label against a backup set; All-in-One does. Offering an ability that
	 * cannot work and reporting that at call time is worse than not offering it.
	 */
	public function test_a_suite_offers_only_what_its_plugin_can_do(): void {
		$this->assertFileDoesNotExist(
			self::dir( 'UpdraftPlus' ) . 'Set_Backup_Label.php',
			'UpdraftPlus stores no label, so the suite must not offer one.'
		);
		$this->assertFileExists(
			self::dir( 'AllInOne' ) . 'Set_Backup_Label.php',
			'All-in-One stores labels, so the suite must offer it.'
		);
	}

	/**
	 * All-in-One's restore is refused by the plugin itself, and says so in its own words.
	 *
	 * Restoring belongs to their paid Unlimited Extension. The ability still exists, asks the
	 * plugin rather than asserting on its behalf, and passes the answer through with the upgrade
	 * route — so a site that HAS the extension is not refused by us.
	 */
	public function test_all_in_one_restore_asks_the_plugin_rather_than_assuming(): void {
		$body = self::code_only( self::method_body( self::read( self::util( 'AllInOne' ) . 'Archive_Repository.php' ), 'restore_backup' ) );

		$this->assertStringContainsString( 'rest_do_request(', $body, 'It must ask the plugin.' );
		$this->assertMatchesRegularExpression(
			'/if \( ! \$response->is_error\(\) \) \{/',
			$body,
			'A success must be honoured — the paid extension makes restore work.'
		);
		$this->assertStringContainsString( "'restore_requires_extension'", $body );
	}

	/**
	 * Both suites share the exposure check rather than duplicating it.
	 *
	 * The question it answers — will the web server hand the archive out — has nothing to do with
	 * which plugin wrote it, and the nginx/.htaccess reasoning should exist once.
	 */
	public function test_the_exposure_check_is_shared_and_takes_its_directories(): void {
		$shared = dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Backup_Exposure.php';

		$this->assertFileExists( $shared );

		$src = self::code_only( self::read( $shared ) );

		$this->assertMatchesRegularExpression(
			'/public static function scan\( string \$label, array \$paths \): array/',
			$src,
			'It must be handed the directories, not discover them through a registry.'
		);
		$this->assertStringNotContainsString( 'Provider_Registry', $src, 'The registry is gone.' );

		foreach ( array( 'UpdraftPlus', 'AllInOne' ) as $suite ) {
			$this->assertStringContainsString(
				'Backup_Exposure::scan(',
				self::read( self::dir( $suite ) . 'Check_Exposure.php' ),
				"{$suite} must use the shared checker."
			);
		}
	}

	/**
	 * An unrecognised server is assumed NOT to honour .htaccess.
	 *
	 * Measured on nginx: both plugins' guard files are present, look correct, and do nothing.
	 * Assuming protection that may not exist is the dangerous direction to be wrong in.
	 */
	public function test_an_unrecognised_server_is_assumed_not_to_honour_htaccess(): void {
		$body = self::code_only( self::method_body( self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Backup_Exposure.php' ), 'htaccess_honoured' ) );

		$this->assertStringContainsString( 'return false !== strpos', $body );
		$this->assertStringNotContainsString( 'return true;', $body );
	}

	/**
	 * Destroying something is gated; reading is not.
	 */
	public function test_only_the_destructive_abilities_demand_confirmation(): void {
		foreach ( self::suites() as $suite => $abilities ) {
			foreach ( array_keys( $abilities ) as $class ) {
				$src   = self::code_only( self::read( self::dir( $suite ) . $class . '.php' ) );
				$gated = in_array( $class, array( 'Delete_Backup', 'Restore_Backup' ), true );

				if ( $gated ) {
					$this->assertMatchesRegularExpression(
						'/protected function requires_confirmation\(\): bool \{\s*return true;/',
						$src,
						"{$suite}/{$class} destroys something and must require confirmation."
					);
				} else {
					$this->assertStringNotContainsString(
						'requires_confirmation',
						$src,
						"{$suite}/{$class} destroys nothing, so a confirmation would only cost a round trip."
					);
				}
			}
		}
	}

	/**
	 * Restore states its irreversibility in the gate itself, in both suites.
	 */
	public function test_restore_states_its_irreversibility(): void {
		foreach ( array_keys( self::suites() ) as $suite ) {
			$gate = self::method_body( self::read( self::dir( $suite ) . 'Restore_Backup.php' ), 'confirmation_message' );

			$this->assertMatchesRegularExpression( '/IRREVERSIBLE/', $gate, "{$suite} must say so plainly." );
			$this->assertMatchesRegularExpression( '/permanently lost/', $gate );
			$this->assertMatchesRegularExpression( '/no undo/', $gate );
		}
	}

	/**
	 * Starting a backup is queued, never run inline.
	 *
	 * Measured: UpdraftPlus's request_backupnow() echoes its own JSON, detaches the client and
	 * then runs the whole backup in the remainder of the process — it replaced the ability's
	 * response wholesale.
	 */
	public function test_starting_a_backup_is_queued(): void {
		$body = self::code_only( self::method_body( self::read( self::util( 'UpdraftPlus' ) . 'Backup_Repository.php' ), 'start_backup' ) );

		$this->assertStringContainsString( 'wp_schedule_single_event(', $body );
		$this->assertStringNotContainsString( 'request_backupnow', $body );
	}

	/**
	 * Restore checks the filesystem before touching anything.
	 */
	public function test_restore_checks_the_filesystem_first(): void {
		$body = self::code_only( self::method_body( self::read( self::util( 'UpdraftPlus' ) . 'Backup_Repository.php' ), 'restore_backup' ) );

		$check   = strpos( $body, "'direct' !== get_filesystem_method()" );
		$restore = strpos( $body, 'perform_restore(' );

		$this->assertNotFalse( $check );
		$this->assertNotFalse( $restore );
		$this->assertLessThan( $restore, $check, 'The check must come before anything is modified.' );
	}

	/**
	 * The filesystem helpers are loaded before they are called.
	 *
	 * get_filesystem_method(), request_filesystem_credentials() and WP_Filesystem() live in
	 * wp-admin/includes/file.php, which WordPress does not load for a REST request — and every call
	 * into this suite is a REST request. Measured: restore died with "Call to undefined function
	 * get_filesystem_method()" before checking anything, for every input rather than only a bad one.
	 */
	public function test_restore_loads_the_admin_filesystem_helpers(): void {
		$body = self::code_only( self::method_body( self::read( self::util( 'UpdraftPlus' ) . 'Backup_Repository.php' ), 'restore_backup' ) );

		$require = strpos( $body, "require_once ABSPATH . 'wp-admin/includes/file.php'" );
		$use     = strpos( $body, 'get_filesystem_method()' );

		$this->assertNotFalse( $require, 'wp-admin/includes/file.php must be loaded; REST requests do not have it.' );
		$this->assertNotFalse( $use );
		$this->assertLessThan( $use, $require, 'It must be loaded BEFORE the first call into it.' );
		$this->assertMatchesRegularExpression(
			"/if \\( ! function_exists\\( 'get_filesystem_method' \\) \\) \\{/",
			$body,
			'Guarded, so a context that already has it is not re-required.'
		);
	}

	/**
	 * Remote storage is named and never described.
	 */
	public function test_remote_storage_credentials_are_never_returned(): void {
		$body = self::code_only( self::method_body( self::read( self::util( 'UpdraftPlus' ) . 'Backup_Repository.php' ), 'remote_storage' ) );

		foreach ( array( 'updraft_s3', 'updraft_dropbox', 'updraft_googledrive', 'secret', 'token' ) as $forbidden ) {
			$this->assertStringNotContainsString( $forbidden, $body, "No credential may be read here ({$forbidden})." );
		}
	}

	/**
	 * Components come from the plugin's own entity list, not from guesswork.
	 */
	public function test_backup_components_are_read_from_the_entity_list(): void {
		$src = self::read( self::util( 'UpdraftPlus' ) . 'Backup_Repository.php' );

		$this->assertStringContainsString( 'get_backupable_file_entities(', self::code_only( self::method_body( $src, 'entity_keys' ) ) );
		$this->assertMatchesRegularExpression(
			'/foreach \( self::entity_keys\(\) as \$entity \)/',
			self::code_only( self::method_body( $src, 'shape' ) )
		);
	}

	/**
	 * The utilities are final and static-only (DEC-UTILITY-STATIC-ONLY).
	 */
	public function test_utilities_are_final_and_static_only(): void {
		$classes = array(
			self::util( 'UpdraftPlus' ) . 'UpdraftPlus_Guard.php'  => 'UpdraftPlus_Guard',
			self::util( 'UpdraftPlus' ) . 'Backup_Repository.php'  => 'Backup_Repository',
			self::util( 'AllInOne' ) . 'All_In_One_Guard.php'      => 'All_In_One_Guard',
			self::util( 'AllInOne' ) . 'Archive_Repository.php'    => 'Archive_Repository',
			dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Backup_Exposure.php' => 'Backup_Exposure',
		);

		foreach ( $classes as $path => $class ) {
			$src = self::read( $path );

			$this->assertStringContainsString( "final class {$class}", $src, "{$class} must be final." );
			$this->assertStringContainsString( 'private function __construct() {}', $src, "{$class} must not be instantiable." );
		}
	}

	/**
	 * Each guard identifies its own plugin by two stable symbols (SEC-002).
	 */
	public function test_each_guard_detects_its_own_plugin(): void {
		$up = self::code_only( self::method_body( self::read( self::util( 'UpdraftPlus' ) . 'UpdraftPlus_Guard.php' ), 'is_available' ) );
		$this->assertStringContainsString( "defined( 'UPDRAFTPLUS_DIR' )", $up );
		$this->assertStringContainsString( "class_exists( 'UpdraftPlus_Backup_History' )", $up );

		$ai = self::code_only( self::method_body( self::read( self::util( 'AllInOne' ) . 'All_In_One_Guard.php' ), 'is_available' ) );
		$this->assertStringContainsString( "defined( 'AI1WM_BACKUPS_PATH' )", $ai );
		$this->assertStringContainsString( "class_exists( 'Ai1wm_Backups' )", $ai );
	}

	/**
	 * Each suite's permission filter can tighten access and never widen it.
	 */
	public function test_the_permission_filters_cannot_widen_access(): void {
		foreach ( array( 'UpdraftPlus' => 'UpdraftPlus_Guard', 'AllInOne' => 'All_In_One_Guard' ) as $suite => $guard ) {
			$body = self::code_only( self::method_body( self::read( self::util( $suite ) . $guard . '.php' ), 'can' ) );

			$cap    = strpos( $body, 'current_user_can( $floor )' );
			$filter = strpos( $body, 'apply_filters(' );

			$this->assertNotFalse( $cap, "{$guard} must check a capability." );
			$this->assertNotFalse( $filter, "{$guard} must expose a filter." );
			$this->assertLessThan( $filter, $cap, "{$guard}: the capability must pass before the filter is consulted." );
		}

		$this->assertNotSame(
			self::read( self::util( 'UpdraftPlus' ) . 'UpdraftPlus_Guard.php' ),
			self::read( self::util( 'AllInOne' ) . 'All_In_One_Guard.php' ),
			'The two guards must be distinct, with their own filter names.'
		);
	}

	// ---------------------------------------------------------------- helpers

	private static function dir( string $suite ): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/' . $suite . '/';
	}

	private static function util( string $suite ): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/' . $suite . '/';
	}

	private static function read( string $path ): string {
		return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	}

	private static function code_only( string $src ): string {
		if ( '' === $src ) {
			return '';
		}

		$out = '';

		foreach ( token_get_all( 0 === strpos( $src, '<?php' ) ? $src : '<?php ' . $src ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}

			$out .= is_array( $token ) ? $token[1] : $token;
		}

		return $out;
	}

	private static function method_body( string $src, string $method ): string {
		$start = strpos( $src, 'function ' . $method . '(' );

		if ( false === $start ) {
			return '';
		}

		$brace = strpos( $src, '{', $start );

		if ( false === $brace ) {
			return '';
		}

		$depth = 0;
		$len   = strlen( $src );

		for ( $i = $brace; $i < $len; $i++ ) {
			if ( '{' === $src[ $i ] ) {
				++$depth;
			} elseif ( '}' === $src[ $i ] ) {
				--$depth;

				if ( 0 === $depth ) {
					return substr( $src, $brace, $i - $brace + 1 );
				}
			}
		}

		return substr( $src, $brace );
	}
}
