<?php
/**
 * Structural payload-shape parity test for absorbed abilities (US2 / T032).
 *
 * Samples three high-traffic abilities across three category folders and
 * asserts that each ability class exposes the same top-level `args`
 * structure it did in the companion source. Source-inspection only per the
 * suite's Test_Boot_Resilience precedent (WP_UnitTestCase without a full
 * WP fixture).
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities\Absorbed;

use WP_UnitTestCase;

/**
 * Class Test_Feature_046_Payload_Shape.
 */
class Test_Feature_046_Payload_Shape extends WP_UnitTestCase {

	/**
	 * Absolute paths to the three sampled ability files.
	 *
	 * @var array<string,string>
	 */
	private array $sources = array();

	/**
	 * Load all three sampled ability sources once per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$plugin_root           = dirname( __DIR__, 4 );
		$this->sources         = array(
			'plugin_list' => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/Plugins/List_Plugins.php'
			),
			'get_post'    => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/Content/Get_Post.php'
			),
			'user_get'    => (string) file_get_contents(
				$plugin_root . '/includes/Abilities/Users/Get_User.php'
			),
		);
	}

	/**
	 * Every sampled ability class extends Ability_Definition and preserves
	 * the required top-level args keys (name, args → label, description,
	 * category, execute_callback, permission_callback, input_schema,
	 * output_schema, meta) — same shape as the companion pre-migration.
	 *
	 * @return void
	 */
	public function test_all_samples_carry_full_args_shape(): void {
		foreach ( $this->sources as $tag => $src ) {
			$this->assertStringContainsString(
				'extends Ability_Definition',
				$src,
				"$tag must extend Ability_Definition"
			);
			foreach (
				array(
					"'name'",
					"'label'",
					"'description'",
					"'category'",
					"'execute_callback'",
					"'permission_callback'",
					"'input_schema'",
					"'output_schema'",
					"'meta'",
				) as $key
			) {
				$this->assertStringContainsString(
					$key,
					$src,
					"$tag must expose args key $key"
				);
			}
		}
	}

	/**
	 * Every sampled ability now carries the rebranded manager-branded
	 * ability name prefix and the rebranded category slug (US2 breaking
	 * change verification).
	 *
	 * @return void
	 */
	public function test_all_samples_use_rebranded_slugs(): void {
		$expected = array(
			'plugin_list' => array(
				'name'     => 'plugins/list-plugins',
				'category' => 'acrossai-plugins',
			),
			'get_post'    => array(
				'name'     => 'content/get-post',
				'category' => 'acrossai-content',
			),
			'user_get'    => array(
				'name'     => 'users/get-user',
				'category' => 'acrossai-users',
			),
		);
		foreach ( $expected as $tag => $pair ) {
			$this->assertStringContainsString(
				"'{$pair['name']}'",
				$this->sources[ $tag ],
				"$tag must carry rebranded ability name"
			);
			$this->assertStringContainsString(
				"'{$pair['category']}'",
				$this->sources[ $tag ],
				"$tag must carry rebranded category slug"
			);
		}
	}

	/**
	 * No sampled ability leaks a legacy `acrossai-core-abilities-` slug or
	 * the `acrossai-core-abilities/` name prefix — audit backstop for US2.
	 *
	 * @return void
	 */
	public function test_no_sample_contains_legacy_slug(): void {
		foreach ( $this->sources as $tag => $src ) {
			$this->assertStringNotContainsString(
				'acrossai-core-abilities-',
				$src,
				"$tag must not carry legacy category-slug prefix"
			);
			$this->assertStringNotContainsString(
				'acrossai-core-abilities/',
				$src,
				"$tag must not carry legacy name prefix"
			);
		}
	}
}
