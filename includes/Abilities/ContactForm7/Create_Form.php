<?php
/**
 * Feature 103 — create a contact form.
 *
 * @license    GPL-2.0-or-later
 * @package    AcrossAI_Abilities_Manager
 * @subpackage Includes\Abilities\ContactForm7
 * @since      0.0.35
 */

namespace AcrossAI_Abilities_Manager\Includes\Abilities\ContactForm7;

use AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\ContactForm7\Form_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * contact-form-7/create-form — a new form, seeded from CF7's default template.
 */
class Create_Form extends Base_Contact_Form_7_Ability {

	protected function slug(): string {
		return 'create-form';
	}

	protected function ability_label(): string {
		return __( 'Create Contact Form', 'acrossai-abilities-manager' );
	}

	protected function ability_description(): string {
		return __( 'Create a contact form. Supplying only a title gives Contact Form 7\'s default template — name, email, subject, message and a submit button — which is usually the right starting point; add fields afterwards with contact-form-7/add-form-field. Supply a form template only if you already know the tag syntax. The new form is not visible to visitors until its shortcode is placed on a page.', 'acrossai-abilities-manager' );
	}

	protected function sub_group(): string {
		return 'cf7-forms';
	}

	protected function cf7_cap(): string {
		return 'edit_contact_forms';
	}

	protected function input_properties(): array {
		return array(
			'title'               => array(
				'type'        => 'string',
				'minLength'   => 1,
				'description' => __( 'Form title, shown in the admin list.', 'acrossai-abilities-manager' ),
			),
			'locale'              => array( 'type' => 'string' ),
			'form'                => array(
				'type'        => 'string',
				'description' => __( 'Optional form template. Omit for the default template.', 'acrossai-abilities-manager' ),
			),
			'mail'                => array( 'type' => 'object' ),
			'mail_2'              => array( 'type' => 'object' ),
			'messages'            => array( 'type' => 'object' ),
			'additional_settings' => array( 'type' => 'string' ),
		);
	}

	protected function output_properties(): array {
		return array(
			'form'          => array( 'type' => 'object' ),
			'config_errors' => array( 'type' => 'array' ),
		);
	}

	protected function required_input(): array {
		return array( 'title' );
	}

	protected function annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => false,
		);
	}

	protected function run( array $input ) {
		$data = array(
			'id'    => -1,
			'title' => sanitize_text_field( (string) ( $input['title'] ?? '' ) ),
		);

		foreach ( array( 'locale', 'form', 'additional_settings' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$data[ $key ] = (string) $input[ $key ];
			}
		}

		foreach ( array( 'mail', 'mail_2', 'messages' ) as $key ) {
			if ( isset( $input[ $key ] ) && is_array( $input[ $key ] ) ) {
				$data[ $key ] = $input[ $key ];
			}
		}

		$form_id = Form_Repository::save( $data );

		if ( is_wp_error( $form_id ) ) {
			return $form_id;
		}

		$form = Form_Repository::get( $form_id );

		if ( is_wp_error( $form ) ) {
			return $form;
		}

		return array(
			'form'          => Form_Repository::describe( $form ),
			'config_errors' => Form_Repository::config_errors( $form ),
			'message'       => sprintf(
				/* translators: 1: form title, 2: form id */
				__( 'Created "%1$s" (id %2$d). Place its shortcode on a page to publish it.', 'acrossai-abilities-manager' ),
				$form->title(),
				$form_id
			),
		);
	}
}
