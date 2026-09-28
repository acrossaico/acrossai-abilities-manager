<?php
/**
 * Feature 120 — architectural invariants and the ability contract for the Site Kit suite.
 *
 * These sweep every file in includes/Abilities/SiteKit/ and
 * includes/Abilities/Utilities/SiteKit/, so a new ability is covered the moment it
 * lands rather than needing its own assertion.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.39
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Site_Kit_Suite_Contract extends WP_UnitTestCase {

	/** Files in the abilities directory that are not themselves abilities. */
	private const NON_ABILITY_FILES = array(
		'Category_Registrar.php',
		'Base_Site_Kit_Ability.php',
	);

	/** The eleven abilities this suite ships, class basename => slug. */
	private const INVENTORY = array(
		'Get_Status'              => 'get-status',
		'List_Modules'            => 'list-modules',
		'Get_Module_Settings'     => 'get-module-settings',
		'Set_Module_State'        => 'set-module-state',
		'Get_Sharing_Settings'    => 'get-sharing-settings',
		'List_Module_Datapoints'  => 'list-module-datapoints',
		'Get_Search_Analytics'    => 'get-search-analytics',
		'Get_Analytics_Report'    => 'get-analytics-report',
		'Get_Pagespeed_Insights'  => 'get-pagespeed-insights',
		'Get_Adsense_Report'      => 'get-adsense-report',
		'Get_Module_Data'         => 'get-module-data',
	);

	private static function abilities_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/SiteKit/';
	}

	private static function utilities_dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/SiteKit/';
	}

	/**
	 * @return string[] Absolute paths to the ability classes.
	 */
	private static function ability_files(): array {
		$files = glob( self::abilities_dir() . '*.php' );
		$files = is_array( $files ) ? $files : array();

		return array_values(
			array_filter(
				$files,
				static fn( string $f ): bool => ! in_array( basename( $f ), self::NON_ABILITY_FILES, true )
			)
		);
	}

	/**
	 * Source with comments stripped and our own namespace segments removed, so only
	 * genuine third-party symbol references remain.
	 */
	private static function code_only( string $src ): string {
		$stripped = '';
		foreach ( token_get_all( $src ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$stripped .= is_array( $token ) ? $token[1] : $token;
		}

		return str_replace(
			array( 'Abilities\\Utilities\\SiteKit', 'Abilities\\SiteKit' ),
			'',
			$stripped
		);
	}

	private static function src( string $file ): string {
		return (string) file_get_contents( $file );
	}

	/* ------------------------------------------------------------ inventory */

	public function test_every_declared_ability_file_exists(): void {
		foreach ( array_keys( self::INVENTORY ) as $class ) {
			$this->assertFileExists( self::abilities_dir() . $class . '.php' );
		}
	}

	/**
	 * The inventory is the contract. A file added without a line here means an
	 * ability nothing in this class checks.
	 */
	public function test_no_ability_file_is_missing_from_the_inventory(): void {
		$found = array_map(
			static fn( string $f ): string => basename( $f, '.php' ),
			self::ability_files()
		);
		sort( $found );

		$declared = array_keys( self::INVENTORY );
		sort( $declared );

		$this->assertSame( $declared, $found );
	}

	public function test_slugs_are_unique(): void {
		$slugs = array_values( self::INVENTORY );
		$this->assertSame( array_unique( $slugs ), $slugs );
	}

	public function test_each_class_declares_its_documented_slug(): void {
		foreach ( self::INVENTORY as $class => $slug ) {
			$src = self::src( self::abilities_dir() . $class . '.php' );
			$this->assertMatchesRegularExpression(
				"/function slug\(\): string \{\s*return '" . preg_quote( $slug, '/' ) . "';/",
				$src,
				"{$class} does not declare the slug {$slug}."
			);
		}
	}

	public function test_slugs_are_verb_first_kebab_case(): void {
		foreach ( self::INVENTORY as $class => $slug ) {
			$this->assertMatchesRegularExpression( '/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug, "{$class} slug shape" );
		}
	}

	/* -------------------------------------------------------- architecture */

	/**
	 * All third-party access is confined to Utilities/SiteKit. That is what gives one
	 * place to absorb a Site Kit API change — and Site Kit ships weekly, so this is
	 * the invariant with the shortest half-life if it is allowed to slip.
	 *
	 * Comments are stripped first: a docblock may legitimately name a symbol while
	 * explaining why it must not be called from there.
	 */
	public function test_no_ability_class_references_a_site_kit_symbol(): void {
		foreach ( self::ability_files() as $file ) {
			$this->assertStringNotContainsString(
				'Google\\Site_Kit',
				self::code_only( self::src( $file ) ),
				basename( $file ) . ' reaches a Site Kit symbol directly; route it through Utilities/SiteKit.'
			);
		}
	}

	public function test_the_base_class_references_no_site_kit_symbol_either(): void {
		$this->assertStringNotContainsString(
			'Google\\Site_Kit',
			self::code_only( self::src( self::abilities_dir() . 'Base_Site_Kit_Ability.php' ) )
		);
	}

	/**
	 * Only the context factory may construct Site Kit's object graph. Everything else
	 * in Utilities asks it, so there is exactly one place that knows the constructor
	 * signatures — which is the thing most likely to change between versions.
	 */
	public function test_only_the_context_factory_builds_site_kit_objects(): void {
		$files = glob( self::utilities_dir() . '*.php' );
		foreach ( is_array( $files ) ? $files : array() as $file ) {
			if ( 'Site_Kit_Context.php' === basename( $file ) ) {
				continue;
			}
			$this->assertStringNotContainsString(
				'new \\Google\\Site_Kit',
				self::code_only( self::src( $file ) ),
				basename( $file ) . ' constructs a Site Kit object; ask Site_Kit_Context instead.'
			);
		}
	}

	/**
	 * Every ability declares all three annotations. A missing one reads as
	 * "not destructive", which is the dangerous way to be wrong.
	 */
	public function test_every_ability_declares_all_three_annotations(): void {
		foreach ( self::ability_files() as $file ) {
			$src = self::src( $file );
			foreach ( array( 'readonly', 'destructive', 'idempotent' ) as $key ) {
				$this->assertMatchesRegularExpression(
					"/'" . $key . "'\s*=>\s*(true|false)/",
					$src,
					basename( $file ) . " does not declare the {$key} annotation."
				);
			}
		}
	}

	/**
	 * A destructive ability must be confirm-gated. Nothing in this suite is currently
	 * destructive, but the rule is asserted rather than assumed so a later addition
	 * cannot ship without the gate.
	 */
	public function test_any_destructive_ability_requires_confirmation(): void {
		$destructive = 0;

		foreach ( self::ability_files() as $file ) {
			$src = self::src( $file );
			if ( ! preg_match( "/'destructive'\s*=>\s*true/", $src ) ) {
				continue;
			}
			++$destructive;
			$this->assertMatchesRegularExpression(
				'/function requires_confirmation\(\): bool \{\s*return true;/',
				$src,
				basename( $file ) . ' is destructive but is not confirm-gated.'
			);
		}

		// Asserted rather than left implicit: the suite is entirely non-destructive
		// today, and this records that as a checked fact rather than an accident.
		$this->assertSame( 0, $destructive, 'A destructive Site Kit ability was added; confirm the gate above still covers it.' );
	}

	/**
	 * A write must not claim readonly. The pair is what an MCP client uses to decide
	 * whether it may run something without asking.
	 */
	public function test_no_write_ability_claims_to_be_readonly(): void {
		$src = self::src( self::abilities_dir() . 'Set_Module_State.php' );
		$this->assertMatchesRegularExpression( "/'readonly'\s*=>\s*false/", $src );
		$this->assertMatchesRegularExpression( '/function requires_confirmation\(\): bool \{\s*return true;/', $src );
	}

	/**
	 * get-status must run on a site where nothing is connected. If it ever starts
	 * requiring setup, the one ability that explains an unconnected site stops working
	 * on exactly the sites that need it.
	 */
	public function test_get_status_is_exempt_from_the_setup_gate(): void {
		$this->assertMatchesRegularExpression(
			'/function requires_setup\(\): bool \{\s*return false;/',
			self::src( self::abilities_dir() . 'Get_Status.php' )
		);
	}

	/**
	 * Same reasoning one step down: listing modules is how a caller learns what
	 * setting Site Kit up would give them.
	 */
	public function test_list_modules_is_exempt_from_the_setup_gate(): void {
		$this->assertMatchesRegularExpression(
			'/function requires_setup\(\): bool \{\s*return false;/',
			self::src( self::abilities_dir() . 'List_Modules.php' )
		);
	}

	/**
	 * Every ability that reaches Google must require that the user actually holds a
	 * token — PageSpeed Insights included. The PSI API is public, which makes it easy
	 * to assume Site Kit reaches it with an API key, but setup_services() builds it on
	 * the same OAuth client as every other module. Without this the ability would fail
	 * with a raw 401 from Google instead of a sentence naming the problem.
	 */
	public function test_every_google_data_ability_requires_authentication(): void {
		$needs = array(
			'Get_Search_Analytics.php',
			'Get_Analytics_Report.php',
			'Get_Adsense_Report.php',
			'Get_Module_Data.php',
			'Get_Pagespeed_Insights.php',
		);

		foreach ( $needs as $file ) {
			$this->assertMatchesRegularExpression(
				'/function requires_authentication\(\): bool \{\s*return true;/',
				self::src( self::abilities_dir() . $file ),
				"{$file} reads Google data but does not require the user to be authenticated."
			);
		}
	}

	/**
	 * The full-page screenshot must be stripped at every detail level. It is a base64
	 * image ~199 KB in a measured run, larger on its own than most response budgets,
	 * and no MCP client can display it.
	 */
	public function test_the_pagespeed_screenshot_is_always_stripped(): void {
		$this->assertStringContainsString(
			"unset( \$result['lighthouseResult']['fullPageScreenshot'] );",
			self::src( self::utilities_dir() . 'Report_Repository.php' )
		);
	}

	/**
	 * The generic passthrough must never reach a POST datapoint. Site Kit's POST
	 * datapoints create Analytics properties and rewrite tag configuration.
	 */
	public function test_the_generic_passthrough_is_read_only(): void {
		$src = self::code_only( self::src( self::utilities_dir() . 'Report_Repository.php' ) );
		$this->assertStringNotContainsString( 'set_data', $src );
	}

	/**
	 * The capability floor is final and is manage_options for the whole suite.
	 */
	public function test_the_permission_floor_is_final_and_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/final protected function permission_floor\(\): string \{\s*return 'manage_options';/",
			self::src( self::abilities_dir() . 'Base_Site_Kit_Ability.php' )
		);
	}

	/**
	 * No ability may override execute() or ability(); the guard order and the spec
	 * shape are properties of the base class, not of each subclass.
	 */
	public function test_no_ability_overrides_execute_or_ability(): void {
		foreach ( self::ability_files() as $file ) {
			$code = self::code_only( self::src( $file ) );
			$this->assertDoesNotMatchRegularExpression(
				'/function execute\(/',
				$code,
				basename( $file ) . ' overrides execute(), which bypasses the guard order.'
			);
			$this->assertDoesNotMatchRegularExpression(
				'/function ability\(/',
				$code,
				basename( $file ) . ' overrides ability(), which bypasses the shared spec assembly.'
			);
		}
	}

	/**
	 * Credentials must never leave the settings reader.
	 */
	public function test_module_settings_redact_credentials(): void {
		$src = self::src( self::utilities_dir() . 'Module_Repository.php' );
		$this->assertStringContainsString( "'clientSecret'", $src );
		$this->assertStringContainsString( 'REDACTED_KEYS', $src );
	}
}
