<?php
namespace RFS_GF_EMAIL_VERIFICATION;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( '\RFS_GF_EMAIL_VERIFICATION\GF_Email_Verification_Field' ) ) {

	/**
	 * GF_Email_Verification_Field class
	 */
	class GF_Email_Verification_Field extends \GF_Field {

		/**
		 * The type of the custom field.
		 *
		 * @var string
		 */
		public $type = 'email-verification-code';

		/**
		 * Enable field input mask by default.
		 *
		 * @var string
		 */
		public $inputMask = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

		/**
		 * Field custom mask.
		 *
		 * @var string
		 */
		public $inputMaskIsCustom = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

		/**
		 * Field custom mask value.
		 *
		 * @var string
		 */
		public $inputMaskValue = '*****-*****-*****-*****-*****'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

		/**
		 * Set the field title.
		 *
		 * @return string
		 */
		public function get_form_editor_field_title() {
			return esc_attr__( 'Email Verification Code', 'rfs-email-verification-for-gravity-forms' );
		}

		/**
		 * Set the field button properties for the form editor.
		 *
		 * @return array
		 */
		public function get_form_editor_button() {
			return array(
				'group' => 'advanced_fields',
				'text'  => $this->get_form_editor_field_title(),
			);
		}

		/**
		 * Set the field's form editor icon.
		 *
		 * @return string
		 */
		public function get_form_editor_field_icon() {
			return 'gform-icon--verified';
		}

		/**
		 * Set the class names of the settings, which should be available on the field in the form editor.
		 *
		 * @return array
		 */
		public function get_form_editor_field_settings() {
			return array(
				'conditional_logic_field_setting',
				'error_message_setting',
				'label_setting',
				'label_placement_setting',
				'admin_label_setting',
				'size_setting',
				'placeholder_setting',
				'description_setting',
				'css_class_setting',
			);
		}

		/**
		 * Set the field to support conditional logic feature.
		 *
		 * @return bool
		 */
		public function is_conditional_logic_supported() {
			return true;
		}

		/**
		 * Set the field inner markup.
		 *
		 * @param array        $form  The Form Object currently being processed.
		 * @param string|array $value The field value.
		 * @param null|array   $entry Null or the Entry Object currently being edited.
		 *
		 * @return string
		 */
		public function get_field_input( $form, $value = '', $entry = null ) {
			$form_id         = absint( $form['id'] );
			$is_entry_detail = $this->is_entry_detail();
			$is_form_editor  = $this->is_form_editor();

			$html_input_type = 'text';

			$id       = (int) $this->id;
			$field_id = $is_entry_detail || $is_form_editor || absint( $form_id ) === 0 ? "input_$id" : 'input_' . $form_id . "_$id";

			$value        = esc_attr( $value );
			$size         = $this->size;
			$class_suffix = $is_entry_detail ? '_admin' : '';
			$class        = $size . $class_suffix;
			$class        = esc_attr( $class );

			$max_length_value = is_numeric( $this->maxLength ) ? absint( $this->maxLength ) : false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			$max_length = $max_length_value ? "maxlength='{$max_length_value}'" : '';

			$tabindex              = $this->get_tabindex();
			$disabled_text         = $is_form_editor ? 'disabled="disabled"' : '';
			$placeholder_attribute = sanitize_text_field( $this->get_field_placeholder_attribute() );
			$required_attribute    = $this->isRequired ? 'aria-required="true"' : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$invalid_attribute     = $this->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';
			$aria_describedby      = sanitize_text_field( $this->get_aria_describedby() );
			$autocomplete          = $this->enableAutocomplete ? sanitize_text_field( $this->get_field_autocomplete_attribute() ) : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			$input = "<input name='input_{$id}' id='{$field_id}' type='{$html_input_type}' value='{$value}' class='{$class}' {$max_length} {$aria_describedby} {$tabindex} {$placeholder_attribute} {$required_attribute} {$invalid_attribute} {$disabled_text} {$autocomplete} />";

			return sprintf( "<div class='ginput_container ginput_container_text'>%s</div>", $input );
		}

		/**
		 * Field custom validation logic.
		 *
		 * @param string|array $value The field value from get_value_submission().
		 * @param array        $form  The Form Object currently being processed.
		 *
		 * @return void
		 */
		public function validate( $value, $form ) {
			$is_blank       = rgblank( $value );
			$correct_format = false;

			if ( ! $is_blank ) {
				$correct_format = preg_match( '/^[A-Za-z0-9]{5}(?:-[A-Za-z0-9]{5}){4}$/', $value );
			}

			if ( $is_blank || ! $correct_format ) {
				$this->failed_validation  = true;
				$this->validation_message = empty( $this->errorMessage ) ? Gravity_Forms::get_error_message( 'email-verification-code' ) : $this->errorMessage; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			} else {
				$saved_code      = Helpers::get_cookie( $form['id'] );
				$decrypt         = Helpers::encrypt_decrypt( 'decrypt', $saved_code );
				$code_to_compare = Helpers::get_real_verification_code( $decrypt );

				if ( $value !== $code_to_compare ) {
					$this->failed_validation  = true;
					$this->validation_message = empty( $this->errorMessage ) ? Gravity_Forms::get_error_message( 'email-verification-code' ) : $this->errorMessage; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				}
			}
		}
	}

}
