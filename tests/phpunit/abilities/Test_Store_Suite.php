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

	/**
	 * All fourteen, and their slugs.
	 *
	 * @return string[]
	 */
	private static function inventory(): array {
		return array(
			'Get_Store_Status'        => 'store/get-store-status',
			'Get_Product'             => 'store/get-product',
			'Update_Product_Details'  => 'store/update-product-details',
			'Set_Product_Taxonomy'    => 'store/set-product-taxonomy',
			'Set_Product_Images'      => 'store/set-product-images',
			'Set_Product_Attributes'  => 'store/set-product-attributes',
			'Create_Variable_Product' => 'store/create-variable-product',
			'Generate_Variations'     => 'store/generate-variations',
			'Update_Variation'        => 'store/update-variation',
			'Schedule_Sale'           => 'store/schedule-sale',
			'Bulk_Update_Prices'      => 'store/bulk-update-prices',
			'Get_Stock'               => 'store/get-stock',
			'Adjust_Stock'            => 'store/adjust-stock',
			'List_Low_Stock'          => 'store/list-low-stock',
		);
	}

	public function test_every_ability_is_declared_and_wired(): void {
		$bootstrap = self::read( dirname( __DIR__, 3 ) . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );

		foreach ( self::inventory() as $class => $slug ) {
			$src = self::read( self::dir() . $class . '.php' );

			$this->assertNotSame( '', $src, "{$class} is missing." );
			$this->assertStringContainsString( "return '" . $slug . "';", $src );
			$this->assertStringContainsString( 'new Store\\' . $class . '();', $bootstrap, "{$class} is never instantiated." );
		}
	}

	/**
	 * Nothing writes the catalogue outside WooCommerce's CRUD.
	 *
	 * The whole reason this repository exists. Measured on 11.1: writing `_regular_price` directly
	 * leaves the displayed price and the lookup table on the OLD value, and saving the product
	 * correctly afterwards does not repair it, because WooCommerce sees the field already changed
	 * and concludes nothing happened.
	 */
	public function test_the_repository_never_writes_directly(): void {
		$repo = self::code_only( self::read( self::util() . 'Product_Repository.php' ) );

		foreach ( array( 'update_post_meta(', 'add_post_meta(', 'wp_insert_post(', 'wp_update_post(', 'wp_set_object_terms(' ) as $forbidden ) {
			$this->assertStringNotContainsString(
				$forbidden,
				$repo,
				"The repository must not use {$forbidden}; every write goes through WooCommerce's own CRUD."
			);
		}

		// The one permitted direct read, and it is a SELECT.
		$this->assertStringNotContainsString( '$wpdb->update(', $repo );
		$this->assertStringNotContainsString( '$wpdb->insert(', $repo );
		$this->assertStringNotContainsString( '$wpdb->delete(', $repo );
	}

	/**
	 * Every write proves itself by re-reading from the database.
	 */
	public function test_writes_are_read_back(): void {
		$repo = self::code_only( self::read( self::util() . 'Product_Repository.php' ) );

		$this->assertStringContainsString( 'function save_and_reread', $repo );

		/*
		 * The re-read itself, not just the call. Asserting that the writers call save_and_reread()
		 * proves nothing about whether that method reads anything back — a version that returned the
		 * in-memory object survived this test until the assertion was tightened.
		 */
		$this->assertMatchesRegularExpression(
			'/\$id = \$product->save\(\);.*\$fresh = self::load\( \(int\) \$id \);/s',
			self::method_body( $repo, 'save_and_reread' ),
			'save_and_reread() must load the product again from the database after saving.'
		);

		foreach ( array( 'update_details', 'set_taxonomy', 'set_images', 'set_attributes', 'schedule_sale', 'update_variation' ) as $writer ) {
			$body = self::method_body( $repo, $writer );

			$this->assertStringContainsString(
				'save_and_reread(',
				$body,
				"{$writer}() must read the product back rather than trusting the in-memory object."
			);
		}
	}

	/**
	 * One method's body, bounded by the next.
	 */
	private static function method_body( string $src, string $method ): string {
		$start = strpos( $src, 'function ' . $method . '(' );

		if ( false === $start ) {
			return '';
		}

		$next = preg_match( '/\n\t(?:public|private|protected) static function /', $src, $m, PREG_OFFSET_CAPTURE, $start + 1 )
			? (int) $m[0][1]
			: strlen( $src );

		return substr( $src, $start, $next - $start );
	}

	/**
	 * Stock goes through WooCommerce's own stock function.
	 *
	 * It resolves the record that actually holds the number, updates atomically, recalculates the
	 * stock status, refreshes the visibility term the catalogue filters on and fires the hooks every
	 * inventory integration listens for. A meta write does none of it.
	 */
	public function test_stock_uses_the_proper_function(): void {
		$repo = self::code_only( self::read( self::util() . 'Product_Repository.php' ) );

		$this->assertStringContainsString( 'wc_update_product_stock(', $repo );
		$this->assertStringContainsString( 'get_stock_managed_by_id()', $repo );
	}

	/**
	 * The reported stock is the authority's, not the object's own field.
	 *
	 * Measured: after decreasing a parent-managed variation by 2, the parent held 5 and a fresh
	 * request agreed — while the variation's own field still read 7 in the request that made the
	 * change. Reporting that as "after" would be a stale read-back dressed up as a confirmation.
	 */
	public function test_effective_stock_is_reported_not_the_raw_field(): void {
		$repo = self::code_only( self::read( self::util() . 'Product_Repository.php' ) );

		$this->assertMatchesRegularExpression(
			"/'stock_quantity'\s*=> \\\$effective,/",
			$repo,
			'The reported quantity must be the authoritative one.'
		);
		$this->assertStringContainsString( "'own_row_quantity'", $repo, 'The raw field stays visible for comparison.' );
		// Single-quoted: a double-quoted needle interpolates \$cached away, the trap from #119.
		$this->assertStringContainsString( 'wp_cache_delete( $cached, \'post_meta\' )', $repo );
	}

	/**
	 * The two dangerous abilities preview by default and ask only when applying.
	 *
	 * A dry run changes nothing, so demanding confirmation for it is friction that teaches a caller
	 * to pass confirm reflexively — the habit the gate exists to prevent. Measured: the first
	 * version asked for confirmation on a preview.
	 */
	public function test_dry_run_is_the_default_and_only_the_real_run_asks(): void {
		foreach ( array( 'Bulk_Update_Prices', 'Generate_Variations' ) as $class ) {
			$src = self::code_only( self::read( self::dir() . $class . '.php' ) );

			$this->assertMatchesRegularExpression(
				"/function needs_confirmation_for\( array \\\$input \): bool \{\s*return ! empty\( \\\$input\['apply'\] \);/",
				$src,
				"{$class} must ask only when apply is set."
			);
			$this->assertStringContainsString( "'default' => false", $src, "{$class} must default to a preview." );
		}
	}

	/**
	 * Both bulk operations are capped, and neither can address the whole store.
	 */
	public function test_bulk_operations_are_bounded(): void {
		$repo = self::code_only( self::read( self::util() . 'Product_Repository.php' ) );

		$this->assertStringContainsString( 'const MAX_BULK', $repo );
		$this->assertStringContainsString( 'const MAX_VARIATIONS', $repo );

		$body = self::method_body( $repo, 'bulk_update_prices' );

		$this->assertStringContainsString( 'too_many', $body, 'A set larger than the cap must be refused, not truncated.' );

		// The COMPARISON, not the error code. Changing the condition to `false` leaves the code
		// string sitting there untouched, so a presence check passes with the cap gone.
		$this->assertMatchesRegularExpression(
			'/if \( count\( \$ids \) > self::MAX_BULK \)/',
			$body,
			'The bulk cap must actually be compared.'
		);
		$this->assertMatchesRegularExpression(
			'/if \( count\( \$wanted \) > self::MAX_VARIATIONS \)/',
			self::method_body( self::code_only( self::read( self::util() . 'Product_Repository.php' ) ), 'generate_variations' ),
			'The variation cap must actually be compared.'
		);
		$this->assertMatchesRegularExpression(
			'/if \( array\(\) === \$ids && \$category <= 0 \)/',
			$body,
			'An explicit filter is required; there is deliberately no way to reprice the whole store.'
		);
	}

	/**
	 * A sale price that WooCommerce would discard is refused rather than silently ignored.
	 */
	public function test_a_useless_sale_price_is_refused(): void {
		$body = self::method_body( self::code_only( self::read( self::util() . 'Product_Repository.php' ) ), 'schedule_sale' );

		$this->assertMatchesRegularExpression(
			'/\$sale >= \$regular/',
			$body,
			'WooCommerce discards a sale price that is not below the regular price, so accepting one would report success having done nothing.'
		);
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
