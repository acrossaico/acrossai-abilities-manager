<?php
/**
 * Feature 103 — turn the autoresponder on or off.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\ContactForm7
 * @since      0.0.35
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\ContactForm7;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\ContactForm7\Contact_Form_7_Guard;
use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\ContactForm7\Form_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * contact-form-7/toggle-mail-2 — activation only, separate from content edits.
 */
class Toggle_Mail_2 extends Base_Contact_Form_7_Ability {

	protected function slug(): string {
		return 'toggle-mail-2';
	}

	protected function ability_label(): string {
		return __( 'Toggle Contact Form Autoresponder', 'acrossai-abilities-manager' );
	}

	protected function ability_description(): string {
		return __( 'Activate or deactivate a form\'s second mail template — the autoresponder sent to whoever submitted the form. Kept separate from editing its content because switching it on while it is still empty sends blank replies to visitors. Read it with contact-form-7/get-mail using which=mail_2 before enabling.', 'acrossai-abilities-manager' );
	}

	protected function sub_group(): string {
		return 'cf7-mail';
	}

	protected function cf7_cap(): string {
		return 'edit_contact_forms';
	}

	protected function input_properties(): array {
		return array(
			'form_id' => array(
				'type'    => 'integer',
				'minimum' => 1,
			),
			'active'  => array( 'type' => 'boolean' ),
		);
	}

	protected function output_properties(): array {
		return array(
			'form_id' => array( 'type' => 'integer' ),
			'active'  => array( 'type' => 'boolean' ),
			'mail_2'  => array( 'type' => 'object' ),
		);
	}

	protected function required_input(): array {
		return array( 'form_id', 'active' );
	}

	protected function annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => true,
		);
	}

	protected function run( array $input ) {
		$form_id = absint( $input['form_id'] ?? 0 );
		$form    = Form_Repository::get( $form_id );

		if ( is_wp_error( $form ) ) {
			return $form;
		}

		$allowed = Contact_Form_7_Guard::assert_can_edit( $form_id );

		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		$active           = ! empty( $input['active'] );
		$mail_2           = (array) $form->prop( 'mail_2' );
		$mail_2['active'] = $active;

		$saved = Form_Repository::save(
			array(
				'id'     => $form_id,
				'mail_2' => $mail_2,
			)
		);

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		$fresh = Form_Repository::get( $form_id );

		if ( is_wp_error( $fresh ) ) {
			return $fresh;
		}

		$stored = (array) $fresh->prop( 'mail_2' );

		return array(
			'form_id' => $form_id,
			'active'  => ! empty( $stored['active'] ),
			'mail_2'  => $stored,
			'message' => $active
				? __( 'Autoresponder enabled.', 'acrossai-abilities-manager' )
				: __( 'Autoresponder disabled.', 'acrossai-abilities-manager' ),
		);
	}
}
