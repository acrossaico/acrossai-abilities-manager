<?php
/**
 * Feature 112 — invariants across the WPCode ability suite.
 *
 * WPCode is not loaded in the test harness, so these assert the suite's shape and the decisions that
 * must not be edited away. The behavioural half runs live against a real WPCode install.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.43
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_WPCode_Suite extends WP_UnitTestCase {

	/**
	 * Class => slug, for all 21.
	 *
	 * @return array<string,string>
	 */
	private static function inventory(): array {
		return array(
			'List_Snippets'           => 'snippets/list-snippets',
			'Get_Snippet'             => 'snippets/get-snippet',
			'Create_Snippet'          => 'snippets/create-snippet',
			'Update_Snippet'          => 'snippets/update-snippet',
			'Delete_Snippet'          => 'snippets/delete-snippet',
			'Activate_Snippet'        => 'snippets/activate-snippet',
			'Deactivate_Snippet'      => 'snippets/deactivate-snippet',
			'Duplicate_Snippet'       => 'snippets/duplicate-snippet',
			'Set_Location'            => 'snippets/set-location',
			'Set_Conditional_Logic'   => 'snippets/set-conditional-logic',
			'List_Locations'          => 'snippets/list-locations',
			'Get_Global_Scripts'      => 'snippets/get-global-scripts',
			'Update_Global_Scripts'   => 'snippets/update-global-scripts',
			'Get_Snippet_Status'      => 'snippets/get-snippet-status',
			'List_Snippet_Errors'     => 'snippets/list-snippet-errors',
			'Clear_Snippet_Errors'    => 'snippets/clear-snippet-errors',
			'Search_Library'          => 'snippets/search-library',
			'Get_Library_Snippet'     => 'snippets/get-library-snippet',
			'Install_Library_Snippet' => 'snippets/install-library-snippet',
			'List_Packs'              => 'snippets/list-packs',
			'Apply_Pack'              => 'snippets/apply-pack',
			'Get_Library_Connection'  => 'snippets/get-library-connection',
			'List_Snippet_Updates'    => 'snippets/list-snippet-updates',
			'Update_Snippet_From_Library' => 'snippets/update-snippet-from-library',
			'Install_Shared_Snippet'  => 'snippets/install-shared-snippet',
		);
	}

	private static function dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/WPCode/';
	}

	private static function util(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/WPCode/';
	}

	private static function read( string $path ): string {
		return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
	}

	/**
	 * @return string[]
	 */
	private static function ability_files(): array {
		$files = glob( self::dir() . '*.php' );

		return array_values(
			array_filter(
				is_array( $files ) ? $files : array(),
				static fn( string $f ): bool => ! in_array(
					basename( $f ),
					array( 'Base_WPCode_Ability.php', 'Category_Registrar.php' ),
					true
				)
			)
		);
	}

	private static function code_only( string $src ): string {
		$out = '';

		foreach ( token_get_all( $src ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}

			$out .= is_array( $token ) ? $token[1] : $token;
		}

		return $out;
	}

	public function test_inventory_matches_the_directory(): void {
		$found = array_map(
			static fn( string $f ): string => basename( $f, '.php' ),
			self::ability_files()
		);

		sort( $found );
		$expected = array_keys( self::inventory() );
		sort( $expected );

		$this->assertSame( $expected, $found );
		$this->assertCount( 25, $found );
	}

	public function test_every_slug_is_declared_and_unique(): void {
		$slugs = array();

		foreach ( self::inventory() as $class => $slug ) {
			$src = self::read( self::dir() . $class . '.php' );

			$this->assertStringContainsString( "return '" . $slug . "';", $src, "{$class} does not declare {$slug}." );
			$slugs[] = $slug;
		}

		$this->assertSame( count( $slugs ), count( array_unique( $slugs ) ) );
	}

	/**
	 * The suite owns `snippets/`, not `wpcode/`.
	 *
	 * `wpcode/` is WPCode's own namespace and holds their five read-only abilities. Registering into
	 * it would collide with a live registration rather than adopt it.
	 */
	public function test_the_suite_does_not_squat_wpcode_namespace(): void {
		foreach ( self::ability_files() as $file ) {
			$this->assertStringNotContainsString(
				"return 'wpcode/",
				self::read( $file ),
				basename( $file ) . ' registers into WPCode\'s own namespace instead of snippets/.'
			);
		}
	}

	/**
	 * Their five are adopted, not re-declared.
	 */
	public function test_the_integration_adopts_wpcodes_own_prefix(): void {
		$src = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/WPCode.php' );

		$this->assertStringContainsString( "array( 'wpcode/' )", $src );
		$this->assertStringContainsString( "TAB_GROUP = 'wpcode'", $src );
	}

	/**
	 * Nothing in the suite writes the custom post type or its meta.
	 *
	 * This is the whole reason the suite exists. `WPCode_Snippet::save()` ends with
	 * `rebuild_cache()`, which rewrites the `wpcode_snippets` option — and that option, not the CPT,
	 * is what the loader reads. A raw post write leaves the cache stale, so the snippet reads back
	 * perfectly and never runs.
	 */
	public function test_no_ability_writes_the_post_type_directly(): void {
		foreach ( self::ability_files() as $file ) {
			$code = self::code_only( self::read( $file ) );

			foreach ( array( 'wp_insert_post(', 'wp_update_post(', 'update_post_meta(', 'delete_post_meta(' ) as $writer ) {
				$this->assertStringNotContainsString(
					$writer,
					$code,
					basename( $file ) . " calls {$writer} directly. Snippet state belongs behind Snippet_Repository so WPCode rebuilds its loader cache."
				);
			}
		}
	}

	/**
	 * Writes are slashed.
	 *
	 * `WPCode_Snippet::save()` hands `code` straight to wp_update_post() WITHOUT slashing, and core
	 * then unslashes — so unslashed input loses a level of backslashes: \WP_Query becomes WP_Query,
	 * "\n" becomes "n", /\d+/ becomes /d+/. WPCode's admin form survives only because $_POST is
	 * pre-slashed. WPCode hit this itself and fixed it in duplicate() alone.
	 */
	public function test_free_text_writes_are_slashed(): void {
		$repo = self::code_only( self::read( self::util() . 'Snippet_Repository.php' ) );

		/*
		 * The free-text branch of apply() specifically, not merely "the file mentions slashing
		 * somewhere" - the global-scripts writer also slashes, so a loose check stays green with
		 * apply() left unslashed, which is the path snippet CODE travels.
		 */
		$this->assertMatchesRegularExpression(
			'/in_array\(\s*\$key,\s*self::FREE_TEXT,\s*true\s*\)\s*\)\s*\{\s*\$value\s*=\s*Slash_Input::slash\(/',
			$repo,
			'apply() must slash the free-text fields; snippet code is the payload that loses backslashes.'
		);
		/*
		 * Exactly one call site, and it is apply(). The global-scripts writer deliberately does NOT
		 * slash: wp_insert_post()/update_post_meta() unslash internally so their input must be
		 * slashed, while update_option() does not, so slashing an option ADDS a backslash level.
		 * Measured both ways on a live site. A second call site here would mean an option write has
		 * picked up the post-write rule.
		 */
		$this->assertSame(
			1,
			substr_count( $repo, 'Slash_Input::slash(' ),
			'Slashing belongs to the post-write path only; update_option() must not be slashed.'
		);
		$this->assertStringContainsString( "self::FREE_TEXT", $repo );
		$this->assertStringContainsString( "'code'", $repo );

		// Snippet writers only. Update_Global_Scripts is deliberately absent: it writes options,
		// which must not be slashed, so advertising an apply_wp_slash flag there would offer a
		// control that does nothing (the Feature 105 lesson).
		foreach ( array( 'Create_Snippet', 'Update_Snippet' ) as $class ) {
			$this->assertStringContainsString(
				'Slash_Input::schema_fragment()',
				self::read( self::dir() . $class . '.php' ),
				"{$class} writes snippet code and must expose the apply_wp_slash flag."
			);
		}

		$this->assertStringNotContainsString(
			'apply_wp_slash',
			self::read( self::dir() . 'Update_Global_Scripts.php' ),
			'Update_Global_Scripts writes options, which are not slashed; the flag would be inert.'
		);
	}

	/**
	 * Activation is read back, because WPCode refuses silently.
	 *
	 * `save()` calls `run_activation_checks()`, which on a php/universal snippet that errors sets
	 * `active = false` and saves anyway. The call returns normally. Trusting it would report a
	 * snippet as live when it is switched off — BUG-WRITE-REPORTED-WITHOUT-READ-BACK exactly.
	 */
	public function test_activation_is_verified_after_saving(): void {
		$repo = self::code_only( self::read( self::util() . 'Snippet_Repository.php' ) );

		$this->assertStringContainsString( 'activation_refused', $repo );
		$this->assertMatchesRegularExpression(
			'/\$expect_active\s*&&\s*!\s*\$active/',
			$repo,
			'persist() must compare the requested active state against what WPCode actually stored.'
		);

		$this->assertStringContainsString(
			'Snippet_Repository::persist( $snippet, true )',
			self::read( self::dir() . 'Activate_Snippet.php' ),
			'activate-snippet must ask persist() to verify the snippet really ended up active.'
		);
	}

	/**
	 * Deleting rebuilds the cache, or a deleted snippet keeps running.
	 */
	public function test_delete_rebuilds_the_loader_cache(): void {
		$repo = self::code_only( self::read( self::util() . 'Snippet_Repository.php' ) );

		$this->assertStringContainsString( 'self::rebuild_cache();', $repo );
		$this->assertStringContainsString( 'delete_incomplete', $repo, 'A snippet still in the cache after deletion must be reported, not ignored.' );
	}

	/**
	 * Clearing an error uses reset_last_error(), never set_last_error('').
	 *
	 * `set_last_error()` returns early unless handed an array with a 'message' key, so clearing
	 * through it changes nothing while reporting success. `reset_last_error()` also clears WPCode's
	 * aggregated error state, which deleting the meta alone does not.
	 */
	public function test_errors_are_cleared_through_the_reset_method(): void {
		$code = self::code_only( self::read( self::dir() . 'Clear_Snippet_Errors.php' ) );

		$this->assertStringContainsString( '->reset_last_error()', $code );

		/*
		 * The arrow form, deliberately: 'set_last_error(' is a substring of 'reset_last_error(', so
		 * the bare check can never fail and would assert nothing.
		 */
		$this->assertStringNotContainsString(
			'->set_last_error(',
			$code,
			"set_last_error('') is a silent no-op - it returns early unless handed an array with a message key. Use reset_last_error()."
		);
	}

	/**
	 * Only executable code types are confirm-gated, and they all are.
	 */
	public function test_executable_writes_are_confirm_gated(): void {
		foreach ( array( 'Create_Snippet', 'Update_Snippet', 'Activate_Snippet' ) as $class ) {
			$src = self::read( self::dir() . $class . '.php' );

			$this->assertStringContainsString( 'requires_confirmation', $src, "{$class} must declare confirmation." );
			$this->assertStringContainsString(
				'WPCode_Guard::EXECUTED_TYPES',
				$src,
				"{$class} must gate on the executed code types rather than confirming everything."
			);
		}

		// Reversible operations must NOT ask; gating them teaches reflexive confirmation.
		foreach ( array( 'Deactivate_Snippet', 'Set_Location', 'List_Snippets' ) as $class ) {
			$this->assertStringNotContainsString(
				'requires_confirmation(): bool {' . PHP_EOL . "\t\treturn true;",
				self::read( self::dir() . $class . '.php' ),
				"{$class} is reversible or read-only and should not require confirmation."
			);
		}
	}

	/**
	 * Installing third-party code never lands active.
	 */
	public function test_library_installs_land_inactive_and_confirm(): void {
		$lib = self::code_only( self::read( self::util() . 'Library_Repository.php' ) );

		/*
		 * The definition, not a mention: renaming the method leaves every call site still matching
		 * a substring check while the code fatals.
		 */
		$this->assertMatchesRegularExpression(
			'/private static function force_inactive\(/',
			$lib,
			'Library_Repository must define force_inactive().'
		);
		$this->assertStringContainsString( '->deactivate();', $lib );

		foreach ( array( 'install', 'apply_pack' ) as $method ) {
			$this->assertStringContainsString(
				'self::force_inactive(',
				$lib,
				"Library_Repository::{$method}() must route created snippets through force_inactive()."
			);
		}
		$this->assertGreaterThanOrEqual(
			2,
			substr_count( $lib, 'self::force_inactive(' ),
			'Both the single install and the pack install must force the snippet inactive.'
		);

		foreach ( array( 'Install_Library_Snippet', 'Apply_Pack' ) as $class ) {
			$this->assertStringContainsString(
				'requires_confirmation',
				self::read( self::dir() . $class . '.php' ),
				"{$class} installs third-party code and must be confirm-gated."
			);
		}
	}

	/**
	 * Pro-only conditional logic is refused by name rather than stored.
	 *
	 * Lite stores an unsupported rule happily and then never matches it, so the snippet silently
	 * stops appearing with nothing reporting why.
	 */
	public function test_pro_only_rules_are_refused(): void {
		$repo = self::code_only( self::read( self::util() . 'Snippet_Repository.php' ) );

		$this->assertStringContainsString( 'requires_pro', $repo );
		$this->assertStringContainsString( 'LITE_RULE_TYPES', $repo );
	}

	/**
	 * The global scripts use the option names that are actually rendered.
	 *
	 * global-output.php reads `ihaf_insert_*`, not the `wpcode_global_*` names the output functions
	 * are called. Writing the function-shaped name stores a value nothing ever outputs.
	 */
	public function test_global_scripts_use_the_rendered_option_keys(): void {
		$repo = self::read( self::util() . 'Snippet_Repository.php' );

		foreach ( array( 'ihaf_insert_header', 'ihaf_insert_body', 'ihaf_insert_footer' ) as $key ) {
			$this->assertStringContainsString( "'" . $key . "'", $repo );
		}

		$this->assertStringNotContainsString( "'wpcode_global_frontend_header'", $repo );
	}

	/**
	 * Every ability that returns error_code declares it.
	 *
	 * Output schemas set additionalProperties: false, so an undeclared key fails the ability's own
	 * validation after the work is done.
	 */
	public function test_error_code_is_declared_by_the_base(): void {
		$base = self::read( self::dir() . 'Base_WPCode_Ability.php' );

		$this->assertStringContainsString( "'error_code' => array( 'type' => 'string' )", $base );
		$this->assertStringContainsString( "'additionalProperties' => false", $base );
	}

	/**
	 * The floor is manage_options and cannot be overridden by a subclass.
	 */
	public function test_permission_floor_is_final_and_admin(): void {
		$base = self::read( self::dir() . 'Base_WPCode_Ability.php' );

		$this->assertMatchesRegularExpression(
			'/final protected function permission_floor\(\): string \{\s*return \'manage_options\';/',
			$base
		);

		foreach ( self::ability_files() as $file ) {
			$this->assertStringNotContainsString(
				'function permission_floor',
				self::read( $file ),
				basename( $file ) . ' overrides the capability floor.'
			);
		}
	}

	/**
	 * The permission filter can tighten access but never widen it.
	 */
	public function test_permission_filter_is_raise_only(): void {
		$guard = self::code_only( self::read( self::util() . 'WPCode_Guard.php' ) );

		$this->assertMatchesRegularExpression(
			'/if\s*\(\s*!\s*current_user_can\(\s*\$floor\s*\)\s*\)\s*\{\s*return false;\s*\}/',
			$guard,
			'The capability check must run and fail BEFORE the filter is consulted.'
		);
		$this->assertStringContainsString( 'apply_filters( self::PERMISSION_FILTER, true, $floor )', $guard );
	}

	/**
	 * The registrar is never instantiated as an ability.
	 *
	 * It is a singleton with a private constructor. Feature 111 caught exactly this: a glob-based
	 * wiring pass that would have been a fatal on every page load.
	 */
	public function test_the_category_registrar_is_not_instantiated(): void {
		$bootstrap = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );

		$this->assertStringNotContainsString( 'new WPCode\\Category_Registrar();', $bootstrap );
		$this->assertStringContainsString( "WPCode\\Category_Registrar::instance(), 'register'", $bootstrap );
	}

	public function test_the_bootstrap_instantiates_every_ability(): void {
		$bootstrap = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );

		foreach ( array_keys( self::inventory() ) as $class ) {
			$this->assertStringContainsString(
				'new WPCode\\' . $class . '();',
				$bootstrap,
				"{$class} is declared but never instantiated."
			);
		}
	}

	/**
	 * The suite is gated on WPCode, so its tab and MCP tool cost nothing elsewhere.
	 */
	public function test_the_suite_is_gated_on_the_host_plugin(): void {
		$bootstrap = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );

		$this->assertStringContainsString(
			"class_exists( 'WPCode_Snippet' ) && function_exists( 'wpcode' )",
			$bootstrap
		);
	}

	/**
	 * No WPCode symbol leaks outside the utilities folder.
	 */
	public function test_wpcode_symbols_stay_behind_the_repositories(): void {
		foreach ( self::ability_files() as $file ) {
			$code = self::code_only( self::read( $file ) );

			foreach ( array( 'wpcode()->library', 'wpcode()->packs', 'wpcode()->settings' ) as $symbol ) {
				$this->assertStringNotContainsString(
					$symbol,
					$code,
					basename( $file ) . " reaches into {$symbol} directly instead of going through a repository."
				);
			}
		}
	}

	/**
	 * Every file that names a global WPCode class imports it.
	 *
	 * The suite lives in a namespace ending in \WPCode, so an unqualified `new WPCode_Snippet()`
	 * resolves to AcrossAI_Abilities_Manager\Includes\Abilities\WPCode\WPCode_Snippet and fatals
	 * at call time, not at load time. Nothing in a source-reading test catches it and the file lints
	 * clean; it surfaces only when the ability actually runs. Caught exactly this way in
	 * get-snippet-status.
	 */
	public function test_global_wpcode_classes_are_imported(): void {
		$files = array_merge(
			self::ability_files(),
			array( self::util() . 'Snippet_Repository.php', self::util() . 'Library_Repository.php' )
		);

		foreach ( $files as $file ) {
			$code = self::code_only( self::read( $file ) );

			if ( ! preg_match( '/(?<!\\\\)\\bWPCode_Snippet\\b/', $code ) ) {
				continue;
			}

			$this->assertStringContainsString(
				'use WPCode_Snippet;',
				$code,
				basename( $file ) . ' names WPCode_Snippet without importing it, so it resolves inside this plugin\'s namespace and fatals at runtime.'
			);
		}
	}

	/**
	 * Private WPCode properties are written through load_from_array(), never assigned.
	 *
	 * note, priority, use_rules and rules are PRIVATE on WPCode_Snippet, so `$snippet->note = ...`
	 * is a fatal at call time. Lints clean, passes every source check, dies the moment the ability
	 * runs.
	 */
	public function test_private_properties_are_not_assigned_directly(): void {
		$files = array_merge( self::ability_files(), array( self::util() . 'Snippet_Repository.php' ) );

		foreach ( $files as $file ) {
			$code = self::code_only( self::read( $file ) );

			foreach ( array( 'note', 'priority', 'use_rules', 'rules' ) as $property ) {
				$this->assertDoesNotMatchRegularExpression(
					'/->' . $property . '\s*=[^=]/',
					$code,
					basename( $file ) . " assigns \$snippet->{$property} directly, but that property is private on WPCode_Snippet."
				);
			}
		}

		$this->assertStringContainsString(
			'load_from_array(',
			self::code_only( self::read( self::util() . 'Snippet_Repository.php' ) ),
			'Private fields must go through WPCode\'s own loader.'
		);
	}

	/**
	 * auto_insert is written as an integer.
	 *
	 * WPCode compares it with `1 === $this->auto_insert`. A boolean fails that strict check, so
	 * save() takes the else branch and CLEARS the location terms instead of setting them: the write
	 * reports success and the snippet ends up placed nowhere.
	 */
	public function test_auto_insert_is_an_integer(): void {
		$repo = self::code_only( self::read( self::util() . 'Snippet_Repository.php' ) );

		$this->assertStringContainsString( 'function auto_insert_flag', $repo );
		$this->assertDoesNotMatchRegularExpression(
			'/auto_insert\s*=\s*\(bool\)/',
			$repo,
			'auto_insert must not be cast to bool; WPCode strict-compares it against integer 1.'
		);
	}

	/**
	 * The loader cache is keyed by location, so id extraction has to descend.
	 *
	 * Reading only the top level finds location buckets and no ids, which reports every active
	 * snippet as absent from the cache.
	 */
	public function test_cache_ids_are_collected_recursively(): void {
		$repo = self::code_only( self::read( self::util() . 'Snippet_Repository.php' ) );

		$this->assertStringContainsString( 'function collect_ids', $repo );
		$this->assertStringContainsString( 'self::collect_ids( $child )', $repo );
	}

	/**
	 * Option writes are NOT slashed, even though post writes are.
	 *
	 * wp_insert_post()/update_post_meta() unslash internally so their input must be slashed;
	 * update_option() does not, so slashing there ADDS a level. Measured: sending \d+ stored \\d+.
	 */
	public function test_option_writes_are_not_slashed(): void {
		$repo = self::code_only( self::read( self::util() . 'Snippet_Repository.php' ) );

		$start = strpos( $repo, 'function update_global_scripts' );
		$this->assertNotFalse( $start );

		$end = strpos( $repo, 'function global_scripts', $start );
		$this->assertNotFalse( $end );

		$this->assertStringNotContainsString(
			'Slash_Input::slash(',
			substr( $repo, $start, $end - $start ),
			'update_option() does not unslash, so slashing here double-escapes the value.'
		);
	}

	/**
	 * The library and packs are loaded on demand.
	 *
	 * WPCode only builds library, library_auth, file_cache and the packs helper inside
	 * `if ( is_admin() || DOING_CRON )`. A REST/MCP request is neither, so without on-demand loading
	 * all five library abilities fatal or report the library missing on a working site.
	 */
	public function test_admin_only_components_are_loaded_on_demand(): void {
		$lib = self::code_only( self::read( self::util() . 'Library_Repository.php' ) );

		$this->assertStringContainsString( 'function load_component', $lib );

		foreach ( array( 'file_cache', 'library_auth', 'WPCode_Packs' ) as $needle ) {
			$this->assertStringContainsString( $needle, $lib, "Library_Repository must handle {$needle}." );
		}

		// Packs is a singleton, not a property on wpcode().
		$this->assertStringContainsString( 'WPCode_Packs::get_instance()', $lib );
		$this->assertStringNotContainsString( 'wpcode()->packs', $lib );
	}

	/**
	 * Pack rows read the key WPCode actually builds.
	 */
	public function test_pack_rows_use_the_name_key(): void {
		$lib = self::read( self::util() . 'Library_Repository.php' );

		$this->assertStringContainsString( "\$pack['name']", $lib );
		$this->assertStringNotContainsString( "\$pack['title']", $lib );
	}

	/**
	 * The library credentials are never returned.
	 *
	 * `wpcode_library_api_auth` holds an auth key, a webhook secret and a client id. An ability that
	 * returned them would be handing live credentials off-site over MCP — the same exposure Feature
	 * 106 found in Yoast's stored OAuth tokens. Only the connection state and the public username
	 * may leave.
	 */
	public function test_library_credentials_are_never_returned(): void {
		$lib = self::code_only( self::read( self::util() . 'Library_Repository.php' ) );

		$start = strpos( $lib, 'function connection(' );
		$this->assertNotFalse( $start );

		$end  = strpos( $lib, 'function updates(', $start );
		$body = substr( $lib, $start, $end - $start );

		foreach ( array( 'get_auth_key', 'get_webhook_secret', 'get_client_id', 'get_auth_data' ) as $getter ) {
			$this->assertStringNotContainsString(
				$getter,
				$body,
				"connection() must not read {$getter}; those are credentials."
			);
		}

		foreach ( self::ability_files() as $file ) {
			$code = self::code_only( self::read( $file ) );

			foreach ( array( 'get_auth_key', 'get_webhook_secret', 'get_client_id', 'wpcode_library_api_auth' ) as $secret ) {
				$this->assertStringNotContainsString(
					$secret,
					$code,
					basename( $file ) . " reads {$secret}; credentials must not reach an ability."
				);
			}
		}
	}

	/**
	 * A library update must not switch a snippet on.
	 *
	 * WPCode's updater saves the library payload wholesale, so `active` comes from the LIBRARY, not
	 * from this site. Without capturing and restoring the local state, updating a deliberately
	 * disabled snippet would silently start executing it.
	 */
	public function test_library_update_preserves_local_active_state(): void {
		$lib = self::code_only( self::read( self::util() . 'Library_Repository.php' ) );

		$this->assertStringContainsString( '$was_active', $lib );
		$this->assertMatchesRegularExpression(
			'/\$was_active\s*=\s*\(bool\)\s*\$snippet->is_active\(\)/',
			$lib,
			'pull_update() must capture the local active state before the library write.'
		);
		$this->assertStringContainsString( '$updated->active = $was_active;', $lib );
	}

	/**
	 * A shared install lands inactive like every other library install.
	 */
	public function test_shared_installs_land_inactive(): void {
		$lib = self::code_only( self::read( self::util() . 'Library_Repository.php' ) );

		$start = strpos( $lib, 'function install_shared(' );
		$this->assertNotFalse( $start );

		$this->assertStringContainsString(
			'self::force_inactive(',
			substr( $lib, $start ),
			'install_shared() must force the snippet inactive.'
		);
	}

	public function test_repositories_are_final_and_static_only(): void {
		foreach ( array( 'Snippet_Repository', 'Library_Repository', 'WPCode_Guard' ) as $class ) {
			$src = self::read( self::util() . $class . '.php' );

			$this->assertStringContainsString( 'final class ' . $class, $src );
			$this->assertStringContainsString( 'private function __construct()', $src );
		}
	}

	/**
	 * Safe mode is detected the way WPCode actually detects it.
	 *
	 * `wpcode_maybe_prevent_execution()` only checks that the parameter is PRESENT — its value is
	 * never read — so testing for '1' would report safe mode as off for ?wpcode-safe-mode and
	 * ?wpcode-safe-mode=yes, both of which do enable it.
	 */
	public function test_safe_mode_matches_wpcodes_own_condition(): void {
		$guard = self::code_only( self::read( self::util() . 'WPCode_Guard.php' ) );

		$this->assertStringContainsString( "isset( \$_GET['wpcode-safe-mode'] )", $guard );
		$this->assertStringNotContainsString( "'1' === \$_GET['wpcode-safe-mode']", $guard );
		$this->assertStringContainsString( "current_user_can( 'wpcode_activate_snippets' )", $guard );
	}

	/**
	 * All eleven auto-insert locations are declared.
	 */
	public function test_every_auto_insert_location_is_declared(): void {
		$repo = self::read( self::util() . 'Snippet_Repository.php' );

		foreach (
			array(
				'site_wide_header',
				'site_wide_body',
				'site_wide_footer',
				'everywhere',
				'admin_only',
				'before_post',
				'after_post',
				'before_content',
				'after_content',
				'before_paragraph',
				'after_paragraph',
			) as $location
		) {
			$this->assertStringContainsString( "'" . $location . "'", $repo, "Location {$location} is missing." );
		}
	}

	/**
	 * PHP writes refuse while WPCode's own kill-switch is on.
	 */
	public function test_php_writes_respect_the_disable_setting(): void {
		$guard = self::code_only( self::read( self::util() . 'WPCode_Guard.php' ) );

		$this->assertStringContainsString( 'completely_disable_php', $guard );
		$this->assertStringContainsString( 'php_disabled', $guard );
	}
}
