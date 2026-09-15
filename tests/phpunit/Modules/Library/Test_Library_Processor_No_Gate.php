<?php
/**
 * Feature 102 (T044) — the registration gate is gone; every definition registers.
 *
 * This is the feature's central change and it had no test. The gate could stop an ability
 * *existing*, so a category switched off vanished from the abilities screen entirely, with nothing
 * on screen explaining it. Availability is now decided only by the per-ability `site_allowed`
 * override, which unregisters blocked abilities later at `wp_abilities_api_init` P100001 — the same
 * fail-closed outcome, but the ability stays listed with its reason visible.
 *
 * The regression this guards is a reintroduced gate: any early `continue` in the registration loop
 * makes abilities silently cease to exist again.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.34
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Processor;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Registry;

require_once dirname( __DIR__, 4 ) . '/includes/Modules/Library/AcrossAI_Ability_Library_Registry.php';
require_once dirname( __DIR__, 4 ) . '/includes/Modules/Library/AcrossAI_Ability_Library_Processor.php';

/**
 * Covers AcrossAI_Ability_Library_Processor::register_abilities().
 */
class Test_Library_Processor_No_Gate extends TestCase {

	/**
	 * Reset the recorded registrations and the injected definitions.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['acrossai_test_abilities'] = array();

		acrossai_test_site_options( array() );
	}

	/**
	 * Leave no injected state behind for other suites.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$this->seed_definitions( null );

		unset( $GLOBALS['acrossai_test_abilities'] );

		parent::tearDown();
	}

	/**
	 * Write the Registry's private static definition cache.
	 *
	 * @param  array<int, array<string, mixed>>|null $definitions Rows, or null to clear.
	 * @return void
	 */
	private function seed_definitions( ?array $definitions ): void {
		$property = new \ReflectionProperty( AcrossAI_Ability_Library_Registry::class, 'definitions' );
		$property->setAccessible( true );
		$property->setValue( null, $definitions );
	}

	/**
	 * Build a definition row.
	 *
	 * @param  string $category Card category.
	 * @param  string $name     Full ability name.
	 * @return array<string, mixed>
	 */
	private function def( string $category, string $name ): array {
		return array(
			'category' => $category,
			'slug'     => $name,
			'name'     => $name,
			'args'     => array( 'label' => $name ),
		);
	}

	/**
	 * Names passed to wp_register_ability(), in order.
	 *
	 * @return array<int, string>
	 */
	private function registered(): array {
		return array_keys( (array) ( $GLOBALS['acrossai_test_abilities'] ?? array() ) );
	}

	/**
	 * Every definition registers, including ones a retired config marked disabled.
	 *
	 * The legacy option is seeded to prove it is inert: `acrossai-database` is switched off and
	 * `acrossai-cache` is in the old "specific" mode with one slug ticked. Under the gate, four of
	 * these five abilities would never have been registered.
	 *
	 * @return void
	 */
	public function test_every_definition_registers_regardless_of_legacy_config(): void {
		acrossai_test_site_options(
			array(
				'acrossai_library_config' => array(
					'acrossai-database' => array( 'enabled' => false ),
					'acrossai-cache'    => array(
						'enabled'  => true,
						'mode'     => 'specific',
						'sub_keys' => array( 'cache/get-transient' => true ),
					),
				),
			)
		);

		$this->seed_definitions(
			array(
				$this->def( 'acrossai-database', 'database/get-option' ),
				$this->def( 'acrossai-database', 'database/list-options' ),
				$this->def( 'acrossai-cache', 'cache/get-transient' ),
				$this->def( 'acrossai-cache', 'cache/flush-transients' ),
				$this->def( 'acrossai-content', 'content/get-post' ),
			)
		);

		AcrossAI_Ability_Library_Processor::instance()->register_abilities();

		$this->assertSame(
			array(
				'database/get-option',
				'database/list-options',
				'cache/get-transient',
				'cache/flush-transients',
				'content/get-post',
			),
			$this->registered(),
			'Every definition must register. A shorter list means a gate was reintroduced and those '
				. 'abilities no longer exist, rather than existing and being blocked.'
		);
	}

	/**
	 * An empty registry registers nothing and does not error.
	 *
	 * @return void
	 */
	public function test_empty_registry_registers_nothing(): void {
		$this->seed_definitions( array() );

		AcrossAI_Ability_Library_Processor::instance()->register_abilities();

		$this->assertSame( array(), $this->registered() );
	}

	/**
	 * The gate method itself is gone.
	 *
	 * Deleting the call site while leaving the method would leave a loaded weapon for the next
	 * person editing the loop.
	 *
	 * @return void
	 */
	public function test_is_permitted_no_longer_exists(): void {
		$this->assertFalse(
			method_exists( AcrossAI_Ability_Library_Processor::class, 'is_permitted' ),
			'The registration gate was removed in Feature 102 and must not return.'
		);
	}

	/**
	 * The registration loop skips nothing that could be an ability.
	 *
	 * Behavioural cover above catches a gate keyed on the legacy config. This catches one keyed on
	 * anything else — a guard wrapped around wp_register_ability() that could stop a first-party
	 * ability existing, which is what made an ability searched for on the abilities screen report as
	 * missing (Feature 102).
	 *
	 * One skip IS legitimate and is asserted for rather than against: display-only integration rows
	 * (`card_variant === 'integration'`) describe abilities a HOST plugin owns. Registering those
	 * occupies the real name, and the registry refuses duplicates — so our placeholder could and did
	 * prevent the real ability existing (#204). Skipping them cannot hide a first-party ability,
	 * because they are not ours to register.
	 *
	 * @return void
	 */
	public function test_registration_loop_skips_only_display_only_rows(): void {
		$source = file_get_contents(
			dirname( __DIR__, 4 ) . '/includes/Modules/Library/AcrossAI_Ability_Library_Processor.php'
		);

		$this->assertIsString( $source );

		$code = '';

		foreach ( token_get_all( (string) $source ) as $token ) {
			if ( is_array( $token ) ) {
				if ( T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
					continue;
				}

				$code .= $token[1];

				continue;
			}

			$code .= $token;
		}

		$loop = strstr( $code, 'foreach ( $definitions as $definition )' );

		$this->assertIsString( $loop, 'The registration loop must still exist.' );

		$loop = (string) $loop;

		// Exactly one skip, and it must be the display-only one.
		$this->assertSame(
			1,
			substr_count( $loop, 'continue' ),
			'The only skip permitted in the registration loop is the display-only integration row.'
		);
		$this->assertMatchesRegularExpression(
			"/'integration' === \\\$definition\\['card_variant'\\][^;]*\\)[^;]*\\{\\s*continue;/",
			$loop,
			'The skip must be keyed on card_variant, not on a category, capability or config gate.'
		);

		// The gates Feature 102 removed must not come back under any name.
		foreach ( array( 'is_permitted', 'current_user_can', 'get_option', 'category' ) as $gate ) {
			$this->assertStringNotContainsString(
				$gate,
				$loop,
				"A skip keyed on {$gate} is a reintroduced gate: it can stop a first-party ability existing."
			);
		}
	}
}
