<?php
/**
 * Feature 121 — the store toolset spine and the adoption of WooCommerce's own abilities.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.51
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

class Test_Store_Suite extends WP_UnitTestCase {

	/**
	 * The seven WooCommerce registers itself, and which are writes.
	 *
	 * @return array<string, bool> name => is_write
	 */
	private static function woocommerce_abilities(): array {
		return array(
			'woocommerce/products-query'     => false,
			'woocommerce/orders-query'       => false,
			'woocommerce/product-create'     => true,
			'woocommerce/product-update'     => true,
			'woocommerce/product-delete'     => true,
			'woocommerce/order-update-status' => true,
			'woocommerce/order-add-note'     => true,
		);
	}

	private static function dir(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Store/';
	}

	private static function util(): string {
		return dirname( __DIR__, 3 ) . '/includes/Abilities/Utilities/Store/';
	}

	private static function read( string $path ): string {
		return is_readable( $path ) ? (string) file_get_contents( $path ) : '';
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

	/**
	 * Our abilities live under `store/`, and can never live anywhere else.
	 *
	 * WooCommerce reserves the whole `woocommerce/` prefix: `is_reserved_woocommerce_ability_name()`
	 * skips a third-party class using it, and if one of its own seven names is shadowed it calls
	 * `wp_unregister_ability()` and takes the name back. A slug in that namespace would not merely be
	 * impolite, it would not survive.
	 */
	public function test_our_abilities_never_use_the_reserved_namespace(): void {
		$files = glob( self::dir() . '*.php' );

		foreach ( is_array( $files ) ? $files : array() as $file ) {
			$base = basename( (string) $file );

			if ( in_array( $base, array( 'Base_Store_Ability.php', 'Category_Registrar.php' ), true ) ) {
				continue;
			}

			$code = self::code_only( (string) file_get_contents( (string) $file ) );

			$this->assertMatchesRegularExpression(
				"/return 'store\\/[a-z0-9-]+';/",
				$code,
				"{$base} must declare a store/ slug."
			);
			$this->assertDoesNotMatchRegularExpression(
				"/return 'woocommerce\\/[a-z0-9-]+';/",
				$code,
				"{$base} claims a name WooCommerce reserves; it would be unregistered and taken back."
			);
		}
	}

	/**
	 * The integration adopts WooCommerce's seven by prefix, bare.
	 *
	 * The tagger matches the segment before the first slash, so a trailing slash never matches (#209).
	 */
	public function test_the_integration_adopts_the_bare_prefix(): void {
		$src = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/WooCommerce.php' );

		$this->assertStringContainsString( "TAB_GROUP = 'woocommerce'", $src );
		$this->assertStringContainsString( "return array( 'woocommerce' );", $src );
		$this->assertStringNotContainsString( "return array( 'woocommerce/' );", $src );
	}

	/**
	 * Nothing here re-registers one of WooCommerce's names.
	 */
	public function test_we_register_none_of_their_abilities(): void {
		$bootstrap = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );
		$src       = self::code_only( self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/WooCommerce.php' ) );

		foreach ( array_keys( self::woocommerce_abilities() ) as $name ) {
			$this->assertStringNotContainsString( "'" . $name . "'", $src, "Adoption is tagging; {$name} must not be declared here." );
		}

		$this->assertStringContainsString( 'Store\\Get_Store_Status();', $bootstrap );
		$this->assertStringNotContainsString( 'new Store\\Category_Registrar();', $bootstrap );
		$this->assertStringContainsString( "Store\\Category_Registrar::instance(), 'register'", $bootstrap );
	}

	/**
	 * Gateway settings are never returned.
	 *
	 * A gateway's configuration holds API keys, secrets and webhook tokens in the same array as its
	 * title, so a reader that returns settings wholesale hands those out — the Feature 106 lesson
	 * about stored credentials, and the one thing the user asked to keep manual.
	 */
	public function test_gateway_settings_are_never_returned(): void {
		$repo = self::code_only( self::read( self::util() . 'Store_Repository.php' ) );

		$this->assertMatchesRegularExpression(
			"/'id'\\s*=>.*'title'\\s*=>.*'enabled'\\s*=>/s",
			$repo,
			'Only id, title and enabled may be returned for a gateway.'
		);

		foreach ( array( 'get_option_key', '->settings', 'get_post_data', 'api_key', 'secret', 'webhook' ) as $forbidden ) {
			$this->assertStringNotContainsString(
				$forbidden,
				$repo,
				"The repository must not touch {$forbidden}: gateway credentials live beside the title."
			);
		}
	}

	/**
	 * The lookup-table drift check is reported, because it is how a silent corruption shows up.
	 */
	public function test_lookup_drift_is_reported(): void {
		$repo = self::code_only( self::read( self::util() . 'Store_Repository.php' ) );

		$this->assertStringContainsString( 'wc_product_meta_lookup', $repo );

		/*
		 * The COMPUTATION, not the key. `'drift'` also appears in the early-return branch with a
		 * hardcoded 0, so a substring check passes even when the real calculation is deleted — the
		 * same trap that let the WPCode prefix assertion certify its own bug.
		 */
		$this->assertMatchesRegularExpression(
			"/'drift'\s*=> \\\$products - \\\$rows,/",
			$repo,
			'The drift between the catalogue and the lookup table must actually be computed.'
		);
		$this->assertMatchesRegularExpression(
			"/'healthy'\s*=> \\\$products === \\\$rows,/",
			$repo
		);
	}

	/**
	 * Availability is two symbols, and never a version test.
	 *
	 * A version-based refusal would take the diagnostics offline exactly when they are most wanted.
	 */
	public function test_availability_is_two_symbols_and_not_a_version_gate(): void {
		$guard = self::code_only( self::read( self::util() . 'Store_Guard.php' ) );

		$this->assertStringContainsString( "defined( 'WC_VERSION' ) && function_exists( 'wc_get_product' )", $guard );
		$this->assertDoesNotMatchRegularExpression(
			'/version_compare\s*\(/',
			$guard,
			'Report the version in get-store-status; do not refuse on it.'
		);
	}

	/**
	 * Every key the status ability returns is declared.
	 *
	 * Undeclared keys are rejected by `additionalProperties: false` after the work is done — the
	 * trap that has now appeared four times across this plugin.
	 */
	public function test_status_keys_are_declared(): void {
		$repo   = self::code_only( self::read( self::util() . 'Store_Repository.php' ) );
		$schema = self::read( self::dir() . 'Get_Store_Status.php' );

		foreach ( array( 'woocommerce_version', 'hpos_enabled', 'order_sync_enabled', 'order_placeholder_rows', 'product_lookup', 'catalogue_transients', 'payment_gateways', 'notes' ) as $key ) {
			$this->assertStringContainsString( "'" . $key . "'", $repo, "The repository is expected to return {$key}." );
			$this->assertStringContainsString( "'" . $key . "'", $schema, "{$key} is returned but not declared." );
		}
	}

	/**
	 * The status ability is a read.
	 */
	public function test_the_status_ability_is_read_only(): void {
		$src = self::read( self::dir() . 'Get_Store_Status.php' );

		$this->assertStringContainsString( "'readonly'    => true", $src );
		$this->assertStringContainsString( "'destructive' => false", $src );
	}

	public function test_permission_floor_is_final_and_admin(): void {
		$base = self::read( self::dir() . 'Base_Store_Ability.php' );

		$this->assertMatchesRegularExpression(
			'/final protected function permission_floor\(\): string \{\s*return \'manage_options\';/',
			$base
		);
	}

	/**
	 * The two permission models in this tab are stated, not papered over.
	 */
	public function test_the_split_permission_model_is_disclosed(): void {
		$src = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/WooCommerce.php' );

		$this->assertStringContainsString( 'administrator', $src );
		$this->assertMatchesRegularExpression( '/shop manager/i', $src );
	}

	/**
	 * The description warns about the derived copies before anyone writes.
	 */
	public function test_the_description_warns_about_derived_data(): void {
		$src = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/Integrations/WooCommerce.php' );

		$this->assertStringContainsString( 'store/get-store-status', $src );
		$this->assertMatchesRegularExpression( '/lookup table/i', $src );
		$this->assertMatchesRegularExpression( '/thirty days/i', $src );
	}

	public function test_repositories_are_final_and_static_only(): void {
		foreach ( array( 'Store_Guard', 'Store_Repository' ) as $class ) {
			$src = self::read( self::util() . $class . '.php' );

			$this->assertStringContainsString( 'final class ' . $class, $src );
			$this->assertStringContainsString( 'private function __construct()', $src );
		}
	}

	public function test_input_is_not_slashed(): void {
		$files = glob( self::dir() . '*.php' );

		foreach ( is_array( $files ) ? $files : array() as $file ) {
			// code_only: the base class docblock explains that Slash_Input is deliberately absent, and
			// a raw substring check cannot tell an explanation apart from a use.
			$this->assertStringNotContainsString(
				'Slash_Input',
				self::code_only( (string) file_get_contents( (string) $file ) )
			);
		}
	}
}
