<?php
/**
 * Tests: what one row of the Integrations screen reports.
 *
 * The row has to stay honest in three states, and the awkward one is the third:
 *
 * - **On.** The count is the registry's, because that is what is actually running.
 * - **Off, nothing left.** The count falls back to the catalogue's published figure. Zero is the
 *   one number this row must never show, because the only reason to look at a switched-off row is
 *   to find out what turning it back on would give you.
 * - **Off, host still contributing.** Several integrations sit on software that publishes abilities
 *   under the same namespace whether this plugin exists or not — WooCommerce reserves
 *   `woocommerce/`, Akismet ships its own, Elementor and Jetpack have their own switches. Those
 *   keep working when our switch is off, so the row reports how many rather than implying the
 *   group is empty.
 *
 * Driven through `get_directory()` rather than asserted against the source, because the earlier
 * source-assertion version of this coverage passed while the behaviour was wrong.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.0.42
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Tests\Modules\Library;

use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\AcrossAI_Toolset_Integrations;
use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\AcrossAI_Toolset_Integration;
use AcrossAI_Abilities_Manager\Includes\Abilities\Rest\Integrations_Directory_Controller;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Integration_Directory;
use AcrossAI_Abilities_Manager\Includes\Modules\Library\AcrossAI_Integration_Settings;
use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Ability_Group;
use PHPUnit\Framework\TestCase;
use WP_Ability;
use WP_REST_Request;

/**
 * An integration descriptor that reports itself present.
 *
 * The real `Rank_Math` descriptor answers `is_active()` from `class_exists( '\RankMath\Helper' )`,
 * which is false in a unit run — so the row would arrive from the catalogue as "supported but not
 * installed here", and `enabled` would be false whatever the option says. This stands in for a
 * plugin that is actually on the site.
 */
final class Fixture_Present_Integration implements AcrossAI_Toolset_Integration {

	/** @return string */
	public function group(): string {
		return 'rank-math';
	}

	/** @return string */
	public function toolset_label(): string {
		return 'Rank Math';
	}

	/** @return string */
	public function toolset_description(): string {
		return 'Fixture integration descriptor.';
	}

	/** @return string[] */
	public function ability_prefixes(): array {
		return array( 'rank-math' );
	}

	/** @return bool */
	public function is_active(): bool {
		return true;
	}
}

/**
 * A member of an integration group.
 */
final class Fixture_Row_Ability extends WP_Ability {

	/**
	 * @param  mixed $input Input.
	 * @return bool
	 */
	public function check_permissions( $input = null ) {
		return true;
	}

	/**
	 * @param  mixed $input Input.
	 * @return mixed
	 */
	public function execute( $input = null ) {
		return array();
	}
}

/**
 * Row shape across the three states.
 */
class Test_Integration_Directory_Row extends TestCase {

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['acrossai_test_abilities']     = array();
		$GLOBALS['acrossai_test_capabilities']  = array( 'manage_options' );
		$GLOBALS['acrossai_test_current_user']  = 1;
		$GLOBALS['acrossai_test_filter_values'] = array(
			'acrossai_toolset_integrations' => array( new Fixture_Present_Integration() ),
			// Pin the catalogue to the copy shipped with the plugin: no network in a unit run, and
			// the published figures are what the "off" case is supposed to fall back to anyway.
			'acrossai_integration_directory_endpoint' => '',
		);

		AcrossAI_Toolset_Integrations::flush();
		AcrossAI_Ability_Group::flush();
		AcrossAI_Integration_Directory::flush();

		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array() );
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['acrossai_test_abilities'] = array();

		unset( $GLOBALS['acrossai_test_filter_values'] );

		AcrossAI_Toolset_Integrations::flush();
		AcrossAI_Ability_Group::flush();
		AcrossAI_Integration_Directory::flush();

		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array() );

		parent::tearDown();
	}

	/**
	 * Put an ability in a group, as the host plugin would.
	 *
	 * @param  string $name  Ability name.
	 * @param  string $group Its group.
	 * @return void
	 */
	private function given_member( string $name, string $group = 'rank-math' ): void {
		$GLOBALS['acrossai_test_abilities'][ $name ] = new Fixture_Row_Ability(
			$name,
			array(
				'label'    => $name,
				'category' => 'acrossai-' . $group,
				'meta'     => array(
					'acrossai' => array( 'tab_group' => $group ),
					'mcp'      => array( 'type' => 'tool' ),
				),
			)
		);

		AcrossAI_Ability_Group::flush();
	}

	/**
	 * The row for one integration, from the real endpoint handler.
	 *
	 * @param  string $slug Integration slug.
	 * @return array<string, mixed>|null
	 */
	private function row( string $slug ): ?array {
		$response = Integrations_Directory_Controller::instance()->get_directory(
			new WP_REST_Request( 'GET', '/acrossai/v1/integrations' )
		);

		foreach ( (array) $response->get_data()['integrations'] as $row ) {
			if ( $slug === $row['slug'] ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Store a decision for one integration.
	 *
	 * @param  string $slug    Integration slug.
	 * @param  bool   $enabled Whether it is on.
	 * @return void
	 */
	private function switch_integration( string $slug, bool $enabled ): void {
		update_site_option( AcrossAI_Integration_Settings::OPTION_KEY, array( $slug => $enabled ) );
	}

	/**
	 * An enabled row counts the registry and reports no host remainder.
	 *
	 * @return void
	 */
	public function test_an_enabled_row_counts_the_registry(): void {
		$this->switch_integration( 'rank-math', true );
		$this->given_member( 'rank-math/get-settings' );
		$this->given_member( 'rank-math/get-redirections' );

		$row = $this->row( 'rank-math' );

		$this->assertNotNull( $row, 'rank-math must appear once its integration is active.' );
		$this->assertTrue( $row['enabled'] );
		$this->assertSame( 2, $row['n'] );
		$this->assertSame(
			0,
			$row['host_n'],
			'An enabled row has no "still provided by" remainder — everything is simply on.'
		);
	}

	/**
	 * A switched-off row reports what the host still provides.
	 *
	 * The case this field exists for. Generic, not an Elementor special case: the count is read from
	 * the registry by group, so every integration whose host publishes its own behaves the same way
	 * without declaring anything extra.
	 *
	 * @return void
	 */
	public function test_a_switched_off_row_reports_the_hosts_remaining_abilities(): void {
		$this->switch_integration( 'rank-math', false );

		// With the switch off the gate registers none of ours, so whatever is left arrived without
		// us — the host's own.
		$this->given_member( 'rank-math/get-settings' );
		$this->given_member( 'rank-math/get-redirections' );
		$this->given_member( 'rank-math/get-sitemap' );

		$row = $this->row( 'rank-math' );

		$this->assertNotNull( $row );
		$this->assertFalse( $row['enabled'] );
		$this->assertSame(
			3,
			$row['host_n'],
			'The row must say how many are still live, not imply the group is empty.'
		);
	}

	/**
	 * A switched-off row with nothing left reports no remainder.
	 *
	 * @return void
	 */
	public function test_a_switched_off_row_with_an_empty_group_reports_no_remainder(): void {
		$this->switch_integration( 'rank-math', false );

		$row = $this->row( 'rank-math' );

		$this->assertNotNull( $row );
		$this->assertFalse( $row['enabled'] );
		$this->assertSame( 0, $row['host_n'] );
	}

	/**
	 * A switched-off row never reports zero abilities.
	 *
	 * Zero is the one number this row must not show: the only reason to look at a switched-off row
	 * is to find out what turning it back on would give you, and "0 abilities" answers that with
	 * something that is both useless and false.
	 *
	 * @return void
	 */
	public function test_a_switched_off_row_never_reports_zero_abilities(): void {
		$this->switch_integration( 'rank-math', false );

		$row = $this->row( 'rank-math' );

		$this->assertNotNull( $row );
		$this->assertGreaterThan(
			0,
			$row['n'],
			'A switched-off row must fall back to the catalogue figure rather than showing zero.'
		);
	}

	/**
	 * Every row carries the field, so the screen never has to guard for it.
	 *
	 * @return void
	 */
	public function test_every_row_carries_the_host_remainder_field(): void {
		$this->switch_integration( 'rank-math', true );
		$this->given_member( 'rank-math/get-settings' );

		$response = Integrations_Directory_Controller::instance()->get_directory(
			new WP_REST_Request( 'GET', '/acrossai/v1/integrations' )
		);

		$rows = (array) $response->get_data()['integrations'];

		$this->assertNotEmpty( $rows );

		foreach ( $rows as $row ) {
			$this->assertArrayHasKey( 'host_n', $row, $row['slug'] . ' is missing host_n.' );
			$this->assertIsInt( $row['host_n'] );
		}
	}

	/**
	 * The catalogue can never report an integration as enabled.
	 *
	 * `enabled` is read from the stored option on every request, never from the feed — which is what
	 * stops a changed or hostile endpoint describing this site's state.
	 *
	 * @return void
	 */
	public function test_enabled_is_read_from_the_option_not_the_catalogue(): void {
		$this->switch_integration( 'rank-math', false );
		$this->given_member( 'rank-math/get-settings' );

		$this->assertFalse( $this->row( 'rank-math' )['enabled'] );

		$this->switch_integration( 'rank-math', true );

		$this->assertTrue( $this->row( 'rank-math' )['enabled'] );
	}
}
