<?php
/**
 * Structural tests for the Feature 063 media/list-image-sizes ability.
 *
 * Verifies the class enumerates via get_intermediate_image_sizes(),
 * enriches from wp_get_additional_image_sizes(), and falls back to the
 * *_size_w / *_size_h / *_crop options for the four WordPress-core sizes.
 *
 * @package AcrossAI_Abilities_Manager
 * @since   0.1.0
 */

namespace AcrossAI_Abilities_Manager\Tests\PHPUnit\Abilities;

use WP_UnitTestCase;

/**
 * Class Test_List_Image_Sizes.
 */
class Test_List_Image_Sizes extends WP_UnitTestCase {

	/** @var string */
	private string $src = '';

	/** @var string */
	private string $bootstrap = '';

	/**
	 * Load the ability source once per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$plugin_root     = dirname( __DIR__, 3 );
		$this->src       = (string) file_get_contents( $plugin_root . '/includes/Abilities/Media/List_Image_Sizes.php' );
		$this->bootstrap = (string) file_get_contents( $plugin_root . '/includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php' );
	}

	public function test_extends_ability_definition_and_uses_expected_ability_name(): void {
		$this->assertStringContainsString( 'extends Ability_Definition', $this->src );
		$this->assertStringContainsString( "'media/list-image-sizes'", $this->src );
	}

	public function test_targets_the_media_category(): void {
		$this->assertStringContainsString( "'acrossai-media'", $this->src );
	}

	public function test_permission_callback_gates_on_manage_options(): void {
		$this->assertMatchesRegularExpression(
			"/current_user_can\\(\\s*'manage_options'\\s*\\)/",
			$this->src
		);
	}

	public function test_declares_readonly_idempotent_non_destructive_annotations(): void {
		$this->assertStringContainsString( "'readonly'    => true", $this->src );
		$this->assertStringContainsString( "'idempotent'  => true", $this->src );
		$this->assertStringContainsString( "'destructive' => false", $this->src );
	}

	public function test_execute_enumerates_via_get_intermediate_image_sizes(): void {
		$this->assertStringContainsString( 'get_intermediate_image_sizes()', $this->src );
	}

	public function test_execute_enriches_via_wp_get_additional_image_sizes(): void {
		$this->assertStringContainsString( 'wp_get_additional_image_sizes()', $this->src );
	}

	public function test_falls_back_to_the_four_core_size_options(): void {
		foreach ( array( 'thumbnail', 'medium', 'medium_large', 'large' ) as $core_size ) {
			$this->assertStringContainsString(
				"'$core_size'",
				$this->src,
				"Core-size fallback list must include $core_size."
			);
		}
		$this->assertStringContainsString( "'_size_w'", $this->src );
		$this->assertStringContainsString( "'_size_h'", $this->src );
		$this->assertStringContainsString( "'_crop'", $this->src );
	}

	public function test_bootstrap_instantiates_the_ability(): void {
		$this->assertStringContainsString( 'new Media\\List_Image_Sizes()', $this->bootstrap );
	}
}
