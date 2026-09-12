<?php
/**
 * Tests: AcrossAI_Abilities_Manager\Includes\Modules\Library\Integrations\AcrossAI_Integration_Ability_Base.
 *
 * Feature 060 — third-party integration base class contract.
 *
 *   (a) push_definition is a no-op when is_plugin_active() is false
 *   (b) push_definition emits one row per ability with card_variant='integration' when active
 *   (c) maybe_enable does NOT call enable_filter when config entry is missing (FR-008 default OFF)
 *   (d) maybe_enable calls enable_filter when config marks the slug enabled AND plugin active
 *   (e) SEC-001 — subclass whose enable_filter() throws does not propagate the exception
 *   (f) SEC-004 — invoking a synthetic row's execute_callback returns WP_Error
 *
 * @package AcrossAI_Abilities_Manager
 */

namespace AcrossAI_Abilities_Manager\Tests\Modules\Library\Integrations;

use AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Integration_Settings;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Ability_Library_Registry;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\Integrations\AcrossAI_Integration_Ability_Base;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Base-class contract test suite.
 */
class Test_Integration_Ability_Base extends TestCase {

	/**
	 * Reset the shared site-option store between tests so integration defaults
	 * behave the same in each case.
	 */
	protected function setUp(): void {
		parent::setUp();
		acrossai_test_site_options( array() );
		$this->reset_registry_definitions();
	}

	/**
	 * Clear the Registry's cached definitions between tests.
	 */
	private function reset_registry_definitions(): void {
		$refl = new ReflectionClass( AcrossAI_Ability_Library_Registry::class );
		$prop = $refl->getProperty( 'definitions' );
		$prop->setAccessible( true );
		$prop->setValue( null, null );
	}

	/**
	 * Seed the Registry singleton with one integration row for the given slug.
	 *
	 * Bypasses the acrossai_abilities_api_init filter so the sparse-storage
	 * roundtrip test in test_save_config_preserves_integration_on_state()
	 * runs deterministically without needing an active target plugin.
	 *
	 * @param string $integration_slug Category / tab_group slug.
	 */
	private function seed_integration_in_registry( string $integration_slug ): void {
		$refl = new ReflectionClass( AcrossAI_Ability_Library_Registry::class );
		$prop = $refl->getProperty( 'definitions' );
		$prop->setAccessible( true );
		$prop->setValue(
			null,
			array(
				array(
					'category'       => $integration_slug,
					'category_label' => 'Mock',
					'slug'           => $integration_slug . '/x',
					'slug_label'     => 'X',
					'name'           => $integration_slug . '/x',
					'tab_group'      => $integration_slug,
					'card_variant'   => 'integration',
					'args'           => array(),
				),
			)
		);
	}

	/**
	 * Build a mock integration subclass with tunable behaviour.
	 *
	 * The subclass captures `enable_filter()` invocations in a shared array
	 * so tests can assert whether the base class called through or short-
	 * circuited.
	 *
	 * @param bool                                                              $active     Value to return from is_plugin_active().
	 * @param array<int, array{slug: string, label: string, description?: string}> $abilities  Static ability list.
	 * @param \Throwable|null                                                   $throw_on_enable Exception to throw from enable_filter (SEC-001).
	 * @param array<int, string>                                                $enable_log Passed by reference; base class calls append.
	 * @return AcrossAI_Integration_Ability_Base
	 */
	private function make_mock(
		bool $active,
		array $abilities,
		?\Throwable $throw_on_enable,
		array &$enable_log
	): AcrossAI_Integration_Ability_Base {
		return new class( $active, $abilities, $throw_on_enable, $enable_log ) extends AcrossAI_Integration_Ability_Base {
			/** @var bool */
			private $active;

			/** @var array<int, array<string, mixed>> */
			private $abilities;

			/** @var \Throwable|null */
			private $throw_on_enable;

			/** @var array<int, string> */
			private $enable_log;

			public function __construct( bool $active, array $abilities, ?\Throwable $throw_on_enable, array &$enable_log ) {
				$this->active          = $active;
				$this->abilities       = $abilities;
				$this->throw_on_enable = $throw_on_enable;
				$this->enable_log      = &$enable_log;
				parent::__construct();
			}

			protected function slug(): string {
				return 'mock';
			}

			protected function label(): string {
				return 'Mock Integration';
			}

			protected function is_plugin_active(): bool {
				return $this->active;
			}

			protected function enable_filter(): void {
				$this->enable_log[] = 'called';
				if ( null !== $this->throw_on_enable ) {
					throw $this->throw_on_enable;
				}
			}

			protected function abilities(): array {
				return $this->abilities;
			}
		};
	}

	// -------------------------------------------------------------------------
	// (a) push_definition is a no-op when is_plugin_active() is false
	// -------------------------------------------------------------------------

	public function test_push_definition_returns_input_unchanged_when_plugin_inactive(): void {
		$log     = array();
		$subject = $this->make_mock(
			false,
			array( array( 'slug' => 'mock/foo', 'label' => 'Foo' ) ),
			null,
			$log
		);

		$existing = array( array( 'category' => 'other', 'name' => 'other/x' ) );
		$rows     = $subject->push_definition( $existing );

		$this->assertSame( $existing, $rows );
	}

	// -------------------------------------------------------------------------
	// (b) push_definition emits one row per ability with card_variant='integration'
	// -------------------------------------------------------------------------

	public function test_push_definition_emits_one_row_per_ability_when_active(): void {
		$log     = array();
		$subject = $this->make_mock(
			true,
			array(
				array( 'slug' => 'mock/foo', 'label' => 'Foo', 'description' => 'Foo does foo.' ),
				array( 'slug' => 'mock/bar', 'label' => 'Bar' ),
			),
			null,
			$log
		);

		$rows = $subject->push_definition( array() );

		$this->assertCount( 2, $rows );

		$this->assertSame( 'mock', $rows[0]['category'] );
		$this->assertSame( 'Mock Integration', $rows[0]['category_label'] );
		$this->assertSame( 'mock/foo', $rows[0]['slug'] );
		$this->assertSame( 'Foo', $rows[0]['slug_label'] );
		$this->assertSame( 'mock', $rows[0]['tab_group'] );
		$this->assertSame( 'integration', $rows[0]['card_variant'] );
		$this->assertSame( 'Foo does foo.', $rows[0]['args']['description'] );

		// Second row — same category/tab_group/card_variant, different slug/label.
		$this->assertSame( 'mock', $rows[1]['category'] );
		$this->assertSame( 'mock/bar', $rows[1]['slug'] );
		$this->assertSame( 'integration', $rows[1]['card_variant'] );
	}

	public function test_push_definition_skips_malformed_ability_entries(): void {
		$log     = array();
		$subject = $this->make_mock(
			true,
			array(
				array( 'slug' => '', 'label' => 'No slug' ),
				array( 'slug' => 'mock/valid', 'label' => 'Valid' ),
				array( 'label' => 'No slug key at all' ),
				'not an array',
			),
			null,
			$log
		);

		$rows = $subject->push_definition( array() );

		$this->assertCount( 1, $rows );
		$this->assertSame( 'mock/valid', $rows[0]['slug'] );
	}

	// -------------------------------------------------------------------------
	// (c) FR-008 default OFF — no config entry means enable_filter NOT called
	// -------------------------------------------------------------------------

	public function test_maybe_enable_no_op_when_config_entry_missing(): void {
		$log     = array();
		$subject = $this->make_mock( true, array(), null, $log );

		acrossai_test_site_options( array( AcrossAI_Integration_Settings::OPTION_KEY => array() ) );

		$subject->maybe_enable();

		$this->assertSame( array(), $log );
	}

	public function test_maybe_enable_no_op_when_config_entry_explicitly_disabled(): void {
		$log     = array();
		$subject = $this->make_mock( true, array(), null, $log );

		acrossai_test_site_options(
			array(
				AcrossAI_Integration_Settings::OPTION_KEY => array( 'mock' => false ),
			)
		);

		$subject->maybe_enable();

		$this->assertSame( array(), $log );
	}

	// -------------------------------------------------------------------------
	// (d) enable_filter fires when config on AND is_plugin_active true
	// -------------------------------------------------------------------------

	public function test_maybe_enable_calls_enable_filter_when_enabled_and_active(): void {
		$log     = array();
		$subject = $this->make_mock( true, array(), null, $log );

		acrossai_test_site_options(
			array(
				AcrossAI_Integration_Settings::OPTION_KEY => array( 'mock' => true ),
			)
		);

		$subject->maybe_enable();

		$this->assertSame( array( 'called' ), $log );
	}

	public function test_maybe_enable_no_op_when_enabled_but_plugin_inactive(): void {
		$log     = array();
		$subject = $this->make_mock( false, array(), null, $log );

		acrossai_test_site_options(
			array(
				AcrossAI_Integration_Settings::OPTION_KEY => array( 'mock' => true ),
			)
		);

		$subject->maybe_enable();

		$this->assertSame( array(), $log );
	}

	// -------------------------------------------------------------------------
	// (e) SEC-001 — enable_filter exception is caught, does NOT propagate
	// -------------------------------------------------------------------------

	public function test_maybe_enable_swallows_enable_filter_exception(): void {
		$log     = array();
		$subject = $this->make_mock(
			true,
			array(),
			new \RuntimeException( 'boom' ),
			$log
		);

		acrossai_test_site_options(
			array(
				AcrossAI_Integration_Settings::OPTION_KEY => array( 'mock' => true ),
			)
		);

		// If the base class did not catch, this would propagate and fail the test.
		$subject->maybe_enable();

		// enable_filter was reached (log entry appended before the throw) but the
		// exception did not escape the base class.
		$this->assertSame( array( 'called' ), $log );
	}

	// -------------------------------------------------------------------------
	// (f) SEC-004 — synthetic row's execute_callback is fail-closed
	// -------------------------------------------------------------------------

	public function test_synthetic_row_execute_callback_returns_wp_error(): void {
		$log     = array();
		$subject = $this->make_mock(
			true,
			array( array( 'slug' => 'mock/foo', 'label' => 'Foo' ) ),
			null,
			$log
		);

		$rows = $subject->push_definition( array() );
		$this->assertCount( 1, $rows );

		$callback = $rows[0]['args']['execute_callback'];
		$this->assertIsCallable( $callback );

		$result = $callback();
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'acrossai_integration_synthetic_row', $result->get_error_code() );

		// permission_callback also fail-closed.
		$perm = $rows[0]['args']['permission_callback'];
		$this->assertFalse( $perm() );
	}

	// -------------------------------------------------------------------------
	// US2 — Two-subclass test: base class supports multiple integrations
	// independently. Each subclass gets its own tab_group/category, and their
	// maybe_enable() callbacks fire independently.
	// -------------------------------------------------------------------------

	/**
	 * Build a mock with a custom slug/label — variant of make_mock() for the
	 * two-subclass US2 test where the shared 'mock' slug isn't sufficient.
	 *
	 * @param string                                                             $slug       Integration slug.
	 * @param string                                                             $label      Display label.
	 * @param bool                                                               $active     is_plugin_active() return value.
	 * @param array<int, array{slug: string, label: string, description?: string}> $abilities  Ability list.
	 * @param array<int, string>                                                 $enable_log Passed by reference; base class appends when enable_filter called.
	 * @return AcrossAI_Integration_Ability_Base
	 */
	private function make_mock_with_slug(
		string $slug,
		string $label,
		bool $active,
		array $abilities,
		array &$enable_log
	): AcrossAI_Integration_Ability_Base {
		return new class( $slug, $label, $active, $abilities, $enable_log ) extends AcrossAI_Integration_Ability_Base {
			/** @var string */
			private $mock_slug;

			/** @var string */
			private $mock_label;

			/** @var bool */
			private $active;

			/** @var array<int, array<string, mixed>> */
			private $abilities;

			/** @var array<int, string> */
			private $enable_log;

			public function __construct( string $slug, string $label, bool $active, array $abilities, array &$enable_log ) {
				$this->mock_slug  = $slug;
				$this->mock_label = $label;
				$this->active     = $active;
				$this->abilities  = $abilities;
				$this->enable_log = &$enable_log;
				parent::__construct();
			}

			protected function slug(): string {
				return $this->mock_slug;
			}

			protected function label(): string {
				return $this->mock_label;
			}

			protected function is_plugin_active(): bool {
				return $this->active;
			}

			protected function enable_filter(): void {
				$this->enable_log[] = $this->mock_slug;
			}

			protected function abilities(): array {
				return $this->abilities;
			}
		};
	}

	public function test_two_subclasses_produce_two_independent_tabs_and_categories(): void {
		$log_a = array();
		$log_b = array();

		$mock_a = $this->make_mock_with_slug(
			'mock-a',
			'Mock A',
			true,
			array( array( 'slug' => 'mock-a/one', 'label' => 'A One' ) ),
			$log_a
		);
		$mock_b = $this->make_mock_with_slug(
			'mock-b',
			'Mock B',
			true,
			array( array( 'slug' => 'mock-b/one', 'label' => 'B One' ) ),
			$log_b
		);

		// Chain filter output to simulate the Registry's collect step.
		$after_a = $mock_a->push_definition( array() );
		$after_b = $mock_b->push_definition( $after_a );

		$this->assertCount( 2, $after_b );

		$categories = array_column( $after_b, 'category' );
		$tab_groups = array_column( $after_b, 'tab_group' );

		$this->assertContains( 'mock-a', $categories );
		$this->assertContains( 'mock-b', $categories );
		$this->assertContains( 'mock-a', $tab_groups );
		$this->assertContains( 'mock-b', $tab_groups );

		// Both rows carry card_variant='integration'.
		foreach ( $after_b as $row ) {
			$this->assertSame( 'integration', $row['card_variant'] );
		}

		// Independent maybe_enable(): only mock-b is toggled on.
		acrossai_test_site_options(
			array(
				AcrossAI_Integration_Settings::OPTION_KEY => array( 'mock-b' => true ),
			)
		);

		$mock_a->maybe_enable();
		$mock_b->maybe_enable();

		$this->assertSame( array(), $log_a );
		$this->assertSame( array( 'mock-b' ), $log_b );
	}

	// -------------------------------------------------------------------------
	// US3 — Resilience: deactivated target plugin never surfaces UI, never
	// attaches a filter, and never mutates saved config (FR-004, FR-012, FR-013).
	// -------------------------------------------------------------------------

	public function test_push_definition_is_byte_identical_when_plugin_inactive(): void {
		$log     = array();
		$subject = $this->make_mock(
			false,
			array( array( 'slug' => 'mock/one', 'label' => 'One' ) ),
			null,
			$log
		);

		$existing = array(
			array( 'category' => 'other', 'name' => 'other/x', 'card_variant' => null ),
			array( 'category' => 'block', 'name' => 'block/y' ),
		);
		$after = $subject->push_definition( $existing );

		$this->assertSame( $existing, $after );
	}

	public function test_deactivating_plugin_does_not_mutate_saved_config(): void {
		acrossai_test_site_options(
			array(
				AcrossAI_Integration_Settings::OPTION_KEY => array( 'mock' => true ),
			)
		);

		$log     = array();
		$subject = $this->make_mock( false, array(), null, $log );

		// Both hook callbacks run under the "plugin deactivated" condition —
		// neither should write to the option store.
		$subject->push_definition( array() );
		$subject->maybe_enable();

		// The intent is unchanged — deactivating the target plugin must not discard the operator's
		// opt-in — but the opt-in now lives in its own option, so that is what is asserted.
		$this->assertTrue(
			AcrossAI_Integration_Settings::is_enabled( 'mock' ),
			'Deactivating the target plugin must leave the saved opt-in intact.'
		);
	}

	public function test_is_enabled_true_only_when_entry_explicitly_enabled(): void {
		acrossai_test_site_options(
			array(
				AcrossAI_Integration_Settings::OPTION_KEY => array( 'acf' => true ),
			)
		);
		$this->assertTrue( AcrossAI_Integration_Settings::is_enabled( 'acf' ) );
	}

	public function test_is_enabled_false_when_option_absent_entirely(): void {
		acrossai_test_site_options( array() );
		$this->assertFalse(
			AcrossAI_Integration_Settings::is_enabled( 'acf' ),
			'Absent means off — the opposite of the retired category config, where absent meant permitted.'
		);
	}

	public function test_is_enabled_false_for_a_falsy_entry(): void {
		acrossai_test_site_options(
			array(
				AcrossAI_Integration_Settings::OPTION_KEY => array( 'acf' => false ),
			)
		);
		$this->assertFalse( AcrossAI_Integration_Settings::is_enabled( 'acf' ) );
	}

	public function test_is_enabled_coerces_a_corrupted_entry_without_crashing(): void {
		// Defensive: the store is written as slug => bool, but nothing stops other code putting
		// something else there. A present entry means the operator opted in, so a truthy value
		// reads as ON — the point of the test is that it neither crashes nor silently flips a
		// site's integrations off.
		acrossai_test_site_options(
			array(
				AcrossAI_Integration_Settings::OPTION_KEY => array( 'acf' => 'yes' ),
			)
		);
		$this->assertTrue( AcrossAI_Integration_Settings::is_enabled( 'acf' ) );
	}

	public function test_is_enabled_returns_false_when_the_option_is_not_an_array(): void {
		acrossai_test_site_options(
			array(
				AcrossAI_Integration_Settings::OPTION_KEY => 'corrupted',
			)
		);
		$this->assertFalse( AcrossAI_Integration_Settings::is_enabled( 'acf' ) );
	}

	// -------------------------------------------------------------------------
	// Extension pattern: third-party Ability_Definition subclasses target the
	// same tab_group as an integration and render as additional regular cards
	// on that tab, coexisting with the integration's own card.
	// -------------------------------------------------------------------------

	public function test_third_party_ability_definition_can_target_integration_tab(): void {
		// The integration itself — pushes its own synthetic 'integration' card.
		$log         = array();
		$integration = $this->make_mock(
			true,
			array( array( 'slug' => 'mock/one', 'label' => 'One' ) ),
			null,
			$log
		);

		// A third-party Ability_Definition subclass targeting the SAME tab_group.
		// Uses a distinct category slug so it renders as a separate card, but
		// the same tab_group ('mock') so it appears on the same tab as the
		// integration card.
		$addon = new class() extends Ability_Definition {
			protected function ability(): array {
				return array(
					'name' => 'my-addon/do-something',
					'args' => array(
						'label'    => 'Add-on Ability',
						'category' => 'my-addon-mock',
						'meta'     => array(
							'acrossai' => array(
								'tab_group' => 'mock',
							),
						),
					),
				);
			}
		};

		// Chain both push_definition() outputs like the Registry does.
		$after_integration = $integration->push_definition( array() );
		$after_all         = $addon->push_definition( $after_integration );

		// Both contributions are present.
		$this->assertCount( 2, $after_all );

		// Row 0 is the integration synthetic card.
		$this->assertSame( 'integration', $after_all[0]['card_variant'] );
		$this->assertSame( 'mock', $after_all[0]['tab_group'] );
		$this->assertSame( 'mock', $after_all[0]['category'] );

		// Row 1 is the third-party card — REGULAR variant (no card_variant),
		// distinct category, same tab_group.
		$this->assertArrayNotHasKey( 'card_variant', $after_all[1] );
		$this->assertSame( 'mock', $after_all[1]['tab_group'] );
		$this->assertSame( 'my-addon-mock', $after_all[1]['category'] );
		$this->assertSame( 'my-addon/do-something', $after_all[1]['slug'] );
	}
}
