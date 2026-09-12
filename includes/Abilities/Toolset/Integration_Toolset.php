<?php
/**
 * A Toolset dispatcher built from an integration's declaration.
 *
 * The thirteen sibling classes each hardcode four answers, which is right for the curated first-party
 * groups: they are few and they rarely change. Integrations are neither. Every one of them would carry
 * the same four declarations it has already made through
 * {@see \AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\AcrossAI_Toolset_Integration}, so
 * requiring a file here as well would mean two places to edit and one to forget — and forgetting this
 * one is silent: the group still gets a tab, a count and a REST filter, and is simply unreachable over
 * MCP.
 *
 * So this subclass takes the declaration and answers from it. Adding an integration adds no file here.
 *
 * The base constructor only registers hooks — it calls none of the four abstract methods — so setting
 * the descriptor before `parent::__construct()` is safe.
 *
 * @package    AcrossAI_Abilities_Manager
 * @subpackage AcrossAI_Abilities_Manager/includes/Abilities/Toolset
 * @since      0.0.35
 */

declare( strict_types = 1 );

namespace AcrossAI_Abilities_Manager\Includes\Abilities\Toolset;

use AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\AcrossAI_Toolset_Integration;

defined( 'ABSPATH' ) || exit;

/**
 * Dispatcher for a group declared by an integration.
 */
final class Integration_Toolset extends Base_Toolset_Ability {

	/**
	 * The declaration this dispatcher serves.
	 *
	 * @since 0.0.35
	 * @var   AcrossAI_Toolset_Integration
	 */
	private $integration;

	/**
	 * @since 0.0.35
	 * @param AcrossAI_Toolset_Integration $integration Declaration to serve.
	 */
	public function __construct( AcrossAI_Toolset_Integration $integration ) {
		$this->integration = $integration;

		parent::__construct();
	}

	/**
	 * @since  0.0.35
	 * @return string
	 */
	protected function group(): string {
		return $this->integration->group();
	}

	/**
	 * @since  0.0.35
	 * @return string
	 */
	protected function slug(): string {
		return 'toolset/' . $this->integration->group();
	}

	/**
	 * @since  0.0.35
	 * @return string
	 */
	protected function toolset_label(): string {
		return $this->integration->toolset_label();
	}

	/**
	 * @since  0.0.35
	 * @return string
	 */
	protected function toolset_description(): string {
		return $this->integration->toolset_description();
	}
}
