<?php
/**
 * Tests: the Elementor row registers Elementor's abilities without switching on its server.
 *
 * Elementor gates two things behind one option, `elementor_mcp_enabled`: whether its 27 MCP
 * abilities register, and whether it creates its MCP server at `/wp-json/elementor/mcp`. We want
 * the first and not the second — the server is site-wide exposure Elementor ships off, and that
 * decision belongs to the site owner.
 *
 * Until 0.0.43 this row called `force_option_true( 'elementor_mcp_enabled' )` and got both. The
 * regression guarded hardest below is that it never does so again: the option is a stored setting
 * Elementor surfaces in its own UI, and overriding it left that screen showing a switch nobody
 * had set, turned on exposure silently, and made our own transport detector report Elementor MCP
 * "active" on the strength of our own filter.
 *
 * Driven through `register_host_abilities()` and `get_option()`, never asserted against source.
 * A sibling test in `Test_Integration_Switch.php` checks `seeds_enabled()` with a source regex;
 * that is the pattern which has twice passed in this feature's work while the behaviour was wrong.
 *
 * The host is substituted by subclassing rather than by declaring fake `\Elementor\*` classes.
 * `class_exists( '\Elementor\Plugin' )` is consulted in nine places in this plugin, so fakes in a
 * shared test process would convince unrelated suites that Elementor is installed.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.43
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Tests\Abilities\Integrations;

use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\Elementor_Opt_In;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Integration_Settings;
use PHPUnit\Framework\TestCase;

/**
 * Stands in for Elementor's MCP module.
 */
final class Fixture_Host_Module {

	/** @var int How many times Elementor was asked to register its set. */
	public int $calls = 0;

	/** @var \Throwable|null Thrown on call, when set. */
	public ?\Throwable $throws = null;

	/**
	 * @return void
	 * @throws \Throwable When configured to.
	 */
	public function register_abilities(): void {
		++$this->calls;

		if ( null !== $this->throws ) {
			throw $this->throws;
		}
	}
}

/**
 * The row with both host seams under the test's control.
 */
final class Fixture_Elementor_Opt_In extends Elementor_Opt_In {

	/** @var bool Whether Elementor's own exposure switch is on. */
	public bool $host_enabled = false;

	/** @var object|null What the modules manager resolves to. */
	public ?object $module = null;

	/**
	 * @var bool Whether Elementor is installed.
	 *
	 * Defaults TRUE. The real check is
	 * `class_exists( '\Elementor\Plugin' ) && class_exists( '\Elementor\Modules\Mcp\Module' )`,
	 * which is false in a unit run — so without this `maybe_enable()` returns early and a test
	 * asserting "nothing was hooked" passes whatever the code under test does. That is how the
	 * switched-off test below passed before this existed.
	 */
	public bool $plugin_present = true;

	/** @return bool */
	protected function is_plugin_active(): bool {
		return $this->plugin_present;
	}

	/** @return bool */
	protected function host_mcp_enabled(): bool {
		return $this->host_enabled;
	}

	/** @return object|null */
	protected function host_module(): ?object {
		return $this->module;
	}
}

/**
 * Registration without exposure.
 */
class Test_Elementor_Opt_In extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		global $__acrossai_test_options;
		$__acrossai_test_options = array();

		$GLOBALS['acrossai_test_abilities']    = array();
		$GLOBALS['acrossai_test_capabilities'] = array( 'manage_options' );
		$GLOBALS['acrossai_test_current_user'] = 1;

		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array( 'elementor' => true ) );
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['acrossai_test_abilities'] = array();

		global $__acrossai_test_options;
		$__acrossai_test_options = array();

		unset( $GLOBALS['acrossai_test_filter_values'] );

		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array() );

		parent::tearDown();
	}

	/**
	 * A row with a reachable host module.
	 *
	 * @param  bool $host_enabled Whether Elementor's own exposure switch is on.
	 * @return array{0: Fixture_Elementor_Opt_In, 1: Fixture_Host_Module}
	 */
	private function given_host( bool $host_enabled = false ): array {
		$module = new Fixture_Host_Module();
		$row    = new Fixture_Elementor_Opt_In();

		$row->host_enabled = $host_enabled;
		$row->module       = $module;

		return array( $row, $module );
	}

	/* ---------------------------------------------------------------------
	 * The regression that matters.
	 * ------------------------------------------------------------------ */

	/**
	 * Enabling the row never touches Elementor's own option.
	 *
	 * The whole point. `elementor_mcp_enabled` is a stored setting Elementor surfaces at
	 * Elementor → Elementor MCP; it is the site owner's answer, not an extension point.
	 *
	 * Asserts the READ, not just the absence of a write. The previous implementation never wrote
	 * either — it short-circuited `pre_option_elementor_mcp_enabled` — so a test that only checked
	 * the stored value passed while the behaviour was wrong. That is exactly how this hid.
	 *
	 * @return void
	 */
	public function test_enabling_the_row_leaves_elementors_own_option_alone(): void {
		$GLOBALS['acrossai_test_recorded_filters'] = array();

		list( $row ) = $this->given_host();

		$row->maybe_enable();
		$row->register_host_abilities();

		// Nothing written.
		$this->assertSame(
			'__absent__',
			get_option( 'elementor_mcp_enabled', '__absent__' ),
			"Elementor's exposure switch must stay the site owner's to set."
		);

		// And nothing OVERRIDDEN. This is the assertion that actually guards the regression: the
		// old implementation never wrote the option either — it short-circuited the read through
		// `pre_option_elementor_mcp_enabled`, which this harness's get_option() cannot see. A test
		// asserting only the value above passes against the very code this replaced.
		$forced = array_values(
			array_filter(
				(array) $GLOBALS['acrossai_test_recorded_filters'],
				static fn( array $f ): bool => 0 === strpos( (string) $f['hook'], 'pre_option_' )
			)
		);

		$this->assertSame(
			array(),
			$forced,
			'No pre_option_* override. An option a host stores and surfaces in its own settings UI '
				. "is the site owner's saved answer, not an extension point."
		);

		unset( $GLOBALS['acrossai_test_recorded_filters'] );
	}

	/* ---------------------------------------------------------------------
	 * …while the abilities still arrive.
	 * ------------------------------------------------------------------ */

	/**
	 * With Elementor's switch off, we ask Elementor to register its own set.
	 *
	 * @return void
	 */
	public function test_it_registers_the_hosts_abilities_when_the_host_switch_is_off(): void {
		list( $row, $module ) = $this->given_host( false );

		$row->register_host_abilities();

		$this->assertSame(
			1,
			$module->calls,
			"Elementor's abilities are already on the site; reaching them is what this row is for."
		);
	}

	/**
	 * With Elementor's switch on, we keep out of the way.
	 *
	 * Elementor registers its own set in that case. Doing it again would mean 27 "already
	 * registered" notices on every request for no gain.
	 *
	 * @return void
	 */
	public function test_it_does_not_register_twice_when_the_host_switch_is_on(): void {
		list( $row, $module ) = $this->given_host( true );

		$row->register_host_abilities();

		$this->assertSame( 0, $module->calls );
	}

	/* ---------------------------------------------------------------------
	 * Ordering — invisible, and load-bearing.
	 * ------------------------------------------------------------------ */

	/**
	 * Registration is hooked after our own abilities and before the Toolsets.
	 *
	 * Pinned between two things, and getting either side wrong is silent:
	 *
	 * - Earlier than 10 and Elementor would win `elementor/create-page` and
	 *   `elementor/update-page-settings`, which we also declare and currently win (issue #261) —
	 *   changing what those two abilities do.
	 * - Later than 20 and a Toolset's `register()` has already resolved group membership through
	 *   `AcrossAI_Ability_Group::members()`, which memoises for the rest of the request. Measured
	 *   at priority 30: `wp_get_abilities()` held all 27 while `toolset/integrations` with
	 *   `plugin=elementor` returned 81 of 106 — a total miss for every Toolset, which is how this
	 *   plugin is used.
	 *
	 * @return void
	 */
	public function test_it_hooks_between_our_abilities_and_the_toolsets(): void {
		$GLOBALS['acrossai_test_recorded_actions'] = array();

		list( $row ) = $this->given_host();
		$row->maybe_enable();

		$hooked = array_values(
			array_filter(
				(array) $GLOBALS['acrossai_test_recorded_actions'],
				static fn( array $a ): bool => 'wp_abilities_api_init' === $a['hook']
			)
		);

		$this->assertCount( 1, $hooked, 'maybe_enable() must hook ability registration exactly once.' );
		$this->assertGreaterThan(
			10,
			$hooked[0]['priority'],
			'Must run after our own abilities, or Elementor wins the two colliding slugs.'
		);
		$this->assertLessThan(
			20,
			$hooked[0]['priority'],
			'Must run before the Toolsets, or they memoise a grouping taken before these arrived.'
		);

		unset( $GLOBALS['acrossai_test_recorded_actions'] );
	}

	/* ---------------------------------------------------------------------
	 * Degrading.
	 * ------------------------------------------------------------------ */

	/**
	 * No host module means no abilities, and no fatal.
	 *
	 * The normal case in a unit run, and the real case on a site without Elementor or with a
	 * version predating `modules/mcp/`.
	 *
	 * @return void
	 */
	public function test_an_unreachable_host_module_is_a_safe_no_op(): void {
		$row         = new Fixture_Elementor_Opt_In();
		$row->module = null;

		$row->register_host_abilities();

		$this->assertSame( array(), $GLOBALS['acrossai_test_abilities'] );
	}

	/**
	 * A module without the method we call is skipped rather than dereferenced.
	 *
	 * Guards against Elementor renaming or removing `register_abilities()`.
	 *
	 * @return void
	 */
	public function test_a_host_module_missing_the_method_is_skipped(): void {
		$row         = new Fixture_Elementor_Opt_In();
		$row->module = new \stdClass();

		$row->register_host_abilities();

		$this->assertSame( array(), $GLOBALS['acrossai_test_abilities'] );
	}

	/**
	 * A host that throws does not take the request with it.
	 *
	 * This runs on `wp_abilities_api_init`, outside the try/catch `maybe_enable()` provides, so the
	 * protection has to be here.
	 *
	 * @return void
	 */
	public function test_a_throwing_host_is_caught(): void {
		list( $row, $module ) = $this->given_host();

		$module->throws = new \RuntimeException( 'Elementor changed' );

		$row->register_host_abilities();

		$this->assertSame( 1, $module->calls, 'It was called, and the throw was contained.' );
	}

	/* ---------------------------------------------------------------------
	 * Telling the owner where the server switch lives.
	 * ------------------------------------------------------------------ */

	/**
	 * With Elementor's server off, the row points at Elementor's own screen.
	 *
	 * The cost of not switching it on for the owner is that nothing says where it is. This closes
	 * that loop on the screen where somebody configures the Elementor integration.
	 *
	 * @return void
	 */
	public function test_it_tells_the_owner_where_the_server_switch_is(): void {
		list( $row ) = $this->given_host( false );

		$todo = $row->declare_host_todo( null, 'elementor' );

		$this->assertIsArray( $todo );
		$this->assertNotEmpty( $todo['label'] );
		$this->assertStringContainsString( 'page=elementor-mcp', (string) $todo['url'] );
	}

	/**
	 * With Elementor's server already on, there is nothing to say.
	 *
	 * @return void
	 */
	public function test_it_says_nothing_once_the_server_is_on(): void {
		list( $row ) = $this->given_host( true );

		$this->assertNull( $row->declare_host_todo( null, 'elementor' ) );
	}

	/**
	 * It answers for its own slug only, and never overwrites another integration's answer.
	 *
	 * The filter is shared, so both halves matter: a second host must be able to use it, and must
	 * not be clobbered by whichever integration happens to run first.
	 *
	 * @return void
	 */
	public function test_it_answers_for_its_own_slug_only(): void {
		list( $row ) = $this->given_host( false );

		$this->assertNull( $row->declare_host_todo( null, 'jetpack' ) );

		$existing = array(
			'label' => 'Someone else got here first',
			'url'   => 'https://example.test/other',
		);

		$this->assertSame( $existing, $row->declare_host_todo( $existing, 'elementor' ) );
	}

	/**
	 * No Elementor, nothing to point at.
	 *
	 * @return void
	 */
	public function test_it_says_nothing_without_elementor(): void {
		$row                 = new Fixture_Elementor_Opt_In();
		$row->plugin_present = false;

		$this->assertNull( $row->declare_host_todo( null, 'elementor' ) );
	}

	/* ---------------------------------------------------------------------
	 * The row still means what it says.
	 * ------------------------------------------------------------------ */

	/**
	 * A switched-off row contributes nothing.
	 *
	 * Guards against "stop forcing the option" being over-applied into "stop gating".
	 *
	 * @return void
	 */
	public function test_a_switched_off_row_does_not_register_anything(): void {
		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array( 'elementor' => false ) );

		$GLOBALS['acrossai_test_recorded_actions'] = array();

		list( $row, $module ) = $this->given_host();

		$this->assertTrue(
			$row->plugin_present,
			'Elementor must be present, or this passes because of its absence rather than the row.'
		);

		$row->maybe_enable();

		$this->assertSame(
			array(),
			array_values(
				array_filter(
					(array) $GLOBALS['acrossai_test_recorded_actions'],
					static fn( array $a ): bool => 'wp_abilities_api_init' === $a['hook']
				)
			),
			'An off row must not hook registration at all.'
		);
		$this->assertSame( 0, $module->calls );

		unset( $GLOBALS['acrossai_test_recorded_actions'] );
	}
}
