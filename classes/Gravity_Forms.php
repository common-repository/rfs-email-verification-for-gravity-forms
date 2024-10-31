<?php
namespace RFS_GF_EMAIL_VERIFICATION;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( '\RFS_GF_EMAIL_VERIFICATION\Gravity_Forms' ) ) {

	/**
	 * Gravity_Forms class
	 */
	class Gravity_Forms {

		/**
		 * The class instance.
		 *
		 * @var class|null
		 */
		private static $_instance = null; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

		/**
		 * The custom merge tag for the verification code.
		 *
		 * @var array
		 */
		public static $verification_code_tag = '{verification_code}';

		/**
		 * The class constructor.
		 */
		private function __construct() {
			add_action( 'gform_loaded', array( $this, 'load_addon' ), 5 );
		}

		/**
		 * Get the instance of the class.
		 *
		 * @return class
		 */
		public static function get_instance() {
			if ( self::$_instance === null ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		/**
		 * Register the addon and initialize custom functionality
		 */
		public function load_addon() {
			if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
				return;
			}

			require_once RFS_GF_EMAIL_VERIFICATION_FULL_DIR . '/classes/GF_Email_Verification_Addon.php';

			\GFAddOn::register( 'RFS_GF_EMAIL_VERIFICATION\GF_Email_Verification_Addon' );

			add_filter( 'gform_form_settings_menu', array( $this, 'settings_tabs' ), 99 );
			add_filter( 'gform_settings_menu', array( $this, 'settings_tabs' ), 99 );

			$this->register_hooks();
			$this->register_ajax_actions();
		}

		/**
		 * Register hooks and filters
		 */
		public function register_hooks() {
			add_filter( 'gform_pre_render', array( $this, 'prepare_form' ), 10, 2 );
			add_filter( 'gform_submit_button', array( $this, 'antibots_hidden_field' ), 10, 2 );
			add_filter( 'gform_submit_button', array( $this, 'verification_code_button' ), 10, 2 );
			add_filter( 'gform_next_button', array( $this, 'verification_code_button' ), 10, 2 );
			add_filter( 'gform_after_submission', array( $this, 'after_submission' ) );
			add_filter( 'gform_custom_merge_tags', array( $this, 'custom_merge_tags' ), 10, 4 );
			add_filter( 'gform_replace_merge_tags', array( $this, 'replace_merge_tags' ), 10, 7 );
		}

		/**
		 * Register custom ajax actions
		 */
		public function register_ajax_actions() {
			add_action( 'wp_ajax_rfs_gf_email_verification_send_verification_code', array( $this, 'ajax_send_verification_code' ) );
			add_action( 'wp_ajax_nopriv_rfs_gf_email_verification_send_verification_code', array( $this, 'ajax_send_verification_code' ) );
			add_action( 'wp_ajax_rfs_gf_email_verification_clear_cookies', array( $this, 'ajax_clear_cookies' ) );
			add_action( 'wp_ajax_nopriv_rfs_gf_email_verification_clear_cookies', array( $this, 'ajax_clear_cookies' ) );
		}

		/**
		 * Run some preparation tasks before the form is rendered
		 *
		 * @param array   $form  The Form Object currently being processed.
		 * @param boolean $is_ajax  If ajax call.
		 *
		 * @return array
		 */
		public function prepare_form( $form, $is_ajax ) {
			if ( ! $is_ajax && ! is_admin() && ! isset( $_POST['gform_submit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified elsewhere by GF plugin.

				// Clear the cookies on form initial load - not ajax forms.
				Helpers::clear_cookies();
			}

			$form_info = self::get_form_info( $form );

			if ( ! $form_info['id'] ) {
				return $form;
			}

			if ( $form_info['id'] && $form_info['id'] === $form['id'] ) {
				$form['cssClass'] .= ' is-vc-form';

				if ( $is_ajax ) {
					$form['cssClass'] .= ' is-ajax-form';
				}
			}

			return $form;
		}

		/**
		 * Add hidden field with value added via js to detect bots
		 *
		 * @param html  $button  The form button html.
		 * @param array $form  The Form Object currently being processed.
		 *
		 * @return html
		 */
		public function antibots_hidden_field( $button, $form ) {
			$form_info = self::get_form_info( $form );

			// Bail early if the form hasn't got the addon enabled.
			if ( ! $form_info['id'] ) {
				return $button;
			}

			$field = '<input type="hidden" class="gform_hidden" name="gform_vc" value="">';

			return $button . $field;
		}

		/**
		 * Display the verification code button
		 *
		 * @param html  $button  The form button html.
		 * @param array $form  The Form Object currently being processed.
		 *
		 * @return html
		 */
		public function verification_code_button( $button, $form ) {
			$form_info = self::get_form_info( $form );

			// Bail early if the form hasn't got the addon enabled.
			if ( ! $form_info['id'] ) {
				return $button;
			}

			$code_button = '';

			if ( $form_info['id'] === $form['id'] ) {
				$code_button = self::get_button_html( $form_info );
			}

			return $code_button . $button;
		}

		/**
		 * Clear add-on cookies after form has been submitted
		 */
		public function after_submission() {
			Helpers::clear_cookies();
		}

		/**
		 * Add custom merge tags
		 *
		 * @param array $merge_tags  The custom merge tags.
		 *
		 * @return array
		 */
		public function custom_merge_tags( $merge_tags ) {
			if ( Helpers::is_form_settings_page() ) {
				$custom_merge_tags = array(
					array(
						'tag'   => self::$verification_code_tag,
						'label' => __( 'Verification code', 'rfs-email-verification-for-gravity-forms' ),
					),
					array(
						'tag'   => '{site_title}',
						'label' => __( 'Site title', 'rfs-email-verification-for-gravity-forms' ),
					),
					array(
						'tag'   => '{admin_email}',
						'label' => __( 'Admin email', 'rfs-email-verification-for-gravity-forms' ),
					),
				);

				$merge_tags = array_merge( $merge_tags, $custom_merge_tags );
			}

			return $merge_tags;
		}

		/**
		 * Replace custom merge tags
		 *
		 * @param string        $text  The custom merge tags.
		 * @param array|boolean $form  False or the current form.
		 *
		 * @return string
		 */
		public function replace_merge_tags( $text, $form ) {
			preg_match( '/' . self::$verification_code_tag . '/', $text, $verification_code_matches );
			preg_match( '/{site_title}/', $text, $site_title_matches );
			preg_match( '/{admin_email}/', $text, $admin_email_matches );

			if ( ! empty( $verification_code_matches ) ) {
				$cookie = Helpers::get_cookie( $form['id'] );

				if ( $cookie ) {
					$decrypted_code    = Helpers::encrypt_decrypt( 'decrypt', $cookie );
					$verification_code = Helpers::get_real_verification_code( $decrypted_code );

					if ( ! $verification_code ) {
						return $text;
					}

					$text = str_replace( $verification_code_matches[0], sanitize_text_field( $verification_code ), $text );
				}
			}

			if ( ! empty( $site_title_matches ) ) {
				$text = str_replace( $site_title_matches[0], Helpers::get_site_title(), $text );
			}

			if ( ! empty( $admin_email_matches ) ) {
				$text = str_replace( $admin_email_matches[0], Helpers::get_admin_email(), $text );
			}

			return $text;
		}

		/**
		 * Display custom icon for settings tabs
		 *
		 * @param array $tabs  False or the current form.
		 */
		public function settings_tabs( $tabs ) {
			if ( $tabs ) {
				foreach ( $tabs as &$tab ) {
					if ( $tab['name'] === RFS_GF_EMAIL_VERIFICATION_SETTINGS_SLUG ) {
						$tab['icon'] = 'gform-icon--mail';
					}
				}
			}

			return $tabs;
		}

		/**
		 * Send the email with verification code via ajax
		 *
		 * @return void  Returns json by wp_send_json_ functions.
		 */
		public function ajax_send_verification_code() {
			$security    = Helpers::get_addon_global_settings( 'ajax-helper' ) ? true : check_ajax_referer( 'security', 'nonce', false );
			$fatal_error = self::get_error_message( 'email-verification-code', 'fatal' );

			if ( ! $security ) {
				wp_send_json_error(
					array(
						'fatal' => $fatal_error,
					),
				);
			}

			$hidden_field = '';
			$email        = '';
			$form_id      = '';

			if ( isset( $_POST['hiddenField'] ) ) {
				$hidden_field = sanitize_text_field( wp_unslash( $_POST['hiddenField'] ) );
			}

			if ( $hidden_field !== 'evc' ) {
				wp_send_json_error(
					array(
						'fatal' => $fatal_error,
					),
				);
			}

			if ( isset( $_POST['email'] ) ) {
				$email = sanitize_text_field( wp_unslash( $_POST['email'] ) );
			}

			if ( isset( $_POST['formId'] ) ) {
				$form_id = absint( $_POST['formId'] );
			}

			$form      = self::get_form_by_id( $form_id );
			$form_info = self::get_form_info( $form );

			if ( \GFCommon::is_invalid_or_empty_email( $email ) ) {
				wp_send_json_error(
					array(
						'email' => $form_info['fields']['email']['error'],
					),
				);
			}

			$form_settings = $form_info['settings'];

			$cookie = Helpers::get_cookie( $form_id );

			if ( ! $cookie ) {
				$verification_code = self::set_form_verification_code( $form_id, 5 );
			} else {
				$verification_code = '';
			}

			// Do not send if cookie is already set, which means the code has already been sent.
			if ( $cookie ) {
				wp_send_json_success();
			}

			// Do not send if there is no verification code.
			if ( ! $verification_code ) {
				wp_send_json_success();
			}

			$post_data = array(
				'formId'      => absint( $form_id ),
				'email'       => esc_html( $email ),
				'hiddenField' => esc_attr( $hidden_field ),
			);

			$post_data['verification_code'] = $verification_code;

			/**
			 * Fires before the verification code has been sent via email
			 *
			 * @since 1.0.0
			 *
			 * @param array $form_settings
			 * @param array $post_data  $_POST data + custom data
			 * @param int $form_id
			 * @param string $action  default or next
			 * @param boolean $is_resend
			 */
			do_action( RFS_GF_EMAIL_VERIFICATION_HOOK_PREFIX . '_before_send_code', $form_settings, $post_data, $form_id, 'default', false );

			/**
			 * Allows for preventing the email to be sent, for whatever reason you want to do that, maybe sending a custom email
			 *
			 * @since 1.0.3
			 *
			 * @param boolean $allow
			 * @param array $form_info
			 * @param array $post_data  $_POST data + custom data
			 * @param boolean $is_resend
			 *
			 * @return boolean $allow
			 */
			$allow_send_email = apply_filters( RFS_GF_EMAIL_VERIFICATION_HOOK_PREFIX . '_allow_send_email', true, $form_info, $post_data, false );

			if ( $allow_send_email ) {
				Helpers::send_email( $form_settings, $post_data );
			}

			/**
			 * Fires after the verification code has been sent via email
			 *
			 * @since 1.0.0
			 *
			 * @param array $form_settings
			 * @param array $post_data  $_POST data
			 * @param int $form_id
			 * @param string $action  default or next
			 * @param boolean $is_resend
			 */
			do_action( RFS_GF_EMAIL_VERIFICATION_HOOK_PREFIX . '_after_send_code', $form_settings, $post_data, $form_id, 'default', false );

			wp_send_json_success();
		}

		/**
		 * Clear cookies on form load for ajax forms
		 */
		public function ajax_clear_cookies() {
			$security    = Helpers::get_addon_global_settings( 'ajax-helper' ) ? true : check_ajax_referer( 'security', 'nonce', false );
			$fatal_error = self::get_error_message( 'email-verification-code', 'fatal' );

			if ( ! $security ) {
				wp_send_json_error(
					array(
						'fatal' => $fatal_error,
					)
				);
			}
			Helpers::clear_cookies();

			wp_send_json_success();
		}

		/**
		 * Get form info for the given form
		 *
		 * @param array $form  The Form Object currently being processed.
		 *
		 * @return array  Prepared data of the form
		 */
		public static function get_form_info( $form ) {
			$form_info = array(
				'id'         => null,
				'pagination' => false,
				'fields'     => array(
					'email'                   => array(),
					'email-verification-code' => array(),
				),
				'settings'   => array(),
			);

			if ( $form['fields'] ) {
				$form_info['settings']   = self::get_form_settings( $form );
				$form_info['pagination'] = $form['pagination'];

				foreach ( $form['fields'] as $field ) {
					if ( $field->type === 'email' || $field->type === 'email-verification-code' ) {
						$error = $field->errorMessage; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

						if ( ! $error ) {
							$error = self::get_error_message( $field->type );
						}

						$form_info['fields'][ $field->type ] = array(
							'page'     => $field->pageNumber, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
							'id'       => $field->id,
							'error'    => $error,
							'required' => $field->isRequired, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						);
					}

					if ( $field->type === 'email-verification-code' ) {
						$form_info['id'] = $field->formId; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					}
				}

				if ( $form_info['id'] ) {
					$form_info['code_sent'] = Helpers::get_cookie( $form_info['id'] ) && isset( $_POST['gform_submit'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified elsewhere by GF plugin.
				}
			}

			/**
			 * Allows for adding custom data to the form info array
			 *
			 * @since 1.0.0
			 *
			 * @param array $form_info  Current form info
			 * @param array $form  Current form
			 *
			 * @return array $form_info
			 */
			return apply_filters( RFS_GF_EMAIL_VERIFICATION_HOOK_PREFIX . '_form_info', $form_info, $form );
		}

		/**
		 * Get form object by id using Gravity Forms API
		 *
		 * @param array $form_id  The Form id fo the currently being processed form.
		 *
		 * @return array  Prepared data of the form
		 */
		public static function get_form_by_id( $form_id ) {
			return \GFAPI::get_form( $form_id );
		}

		/**
		 * Generate "send verification code" button.
		 *
		 * @param array $form_info  The Form data.
		 *
		 * @return html
		 */
		public static function get_button_html( $form_info ) {
			$form_settings = $form_info['settings'];

			$button_html = sprintf(
				'<button type="button" class="gform_button button gform_vc_button js-gform-vc-button">
					<span class="gform_vc_button_label">%s</span> <span class="gform-loader"></span>
				</button>',
				esc_html( $form_settings['button-label-send'] ),
			);

			/**
			 * Allows for modifying the verification code button html markup.
			 *
			 * @since 1.0.0
			 *
			 * @param html $button_html
			 * @param array $form_info  Current form info
			 *
			 * @return html $button_html
			 */
			return apply_filters( RFS_GF_EMAIL_VERIFICATION_HOOK_PREFIX . '_button_html', $button_html, $form_info );
		}

		/**
		 * Generate and store (in a cookie) the verification code for the form.
		 *
		 * @param int $form_id  The Form ID.
		 * @param int $code_expiry  The expiry time of the cookie in minutes.
		 *
		 * @return string  The verification code
		 */
		public static function set_form_verification_code( $form_id, $code_expiry ) {
			$verification_code      = Helpers::generate_verification_code();
			$real_verification_code = Helpers::get_real_verification_code( $verification_code );

			$encrypted_code = Helpers::encrypt_decrypt( 'encrypt', $verification_code );
			$cookie_expiry  = absint( $code_expiry ) * 60;

			Helpers::set_cookie( $form_id, $encrypted_code, $cookie_expiry );

			return $real_verification_code;
		}

		/**
		 * Get values of the addon settings for the form. Will use default values if the settings hasn't been saved by the user yet.
		 *
		 * @param int $form  The Form Object.
		 *
		 * @return array
		 */
		public static function get_form_settings( $form ) {
			$form_settings = rgar( $form, RFS_GF_EMAIL_VERIFICATION_SETTINGS_SLUG );

			if ( ! is_array( $form_settings ) ) {
				$form_settings = array();
			}

			$default_settings = self::get_settings_default_values();

			$settings = array();

			foreach ( array_merge( $default_settings['f'], $form_settings, $default_settings['p'] ) as $key => $value ) {
				if ( $key === RFS_GF_EMAIL_VERIFICATION_SETTINGS_SLUG . '-email-notification' ) {
					$value = wpautop( $value );
				}

				$setting              = str_replace( RFS_GF_EMAIL_VERIFICATION_SETTINGS_SLUG . '-', '', $key );
				$settings[ $setting ] = $value;
			}

			return $settings;
		}

		/**
		 * Get default values of the addon settings for the form.
		 *
		 * @return array
		 */
		public static function get_settings_default_values() {
			$settings       = self::get_settings();
			$default_values = array(
				'f' => array(),
				'p' => array(),
			);

			foreach ( $settings as $section ) {
				foreach ( $section['fields'] as $field ) {
					$field_name = sanitize_key( $field['name'] );
					if ( isset( $field['readonly'] ) ) {
						$default_values['p'][ $field_name ] = wp_kses_post( $field['default_value'] );
					} else {
						$default_values['f'][ $field_name ] = wp_kses_post( $field['default_value'] );
					}
				}
			}

			return $default_values;
		}

		/**
		 * Get custom settings of the addon for the form.
		 *
		 * @return array
		 */
		public static function get_settings() {
			$pro_only       = __( 'Available in PRO', 'rfs-email-verification-for-gravity-forms' );
			$pro_only_affix = ' (' . $pro_only . ') ';

			$settings = array(
				array(
					'title'  => __( 'General', 'rfs-email-verification-for-gravity-forms' ),
					'fields' => array(
						array(
							'label'         => esc_html__( 'Send Code button text', 'rfs-email-verification-for-gravity-forms' ),
							'type'          => 'text',
							'name'          => RFS_GF_EMAIL_VERIFICATION_SETTINGS_SLUG . '-button-label-send',
							'class'         => 'medium',
							'default_value' => esc_html__( 'Send verification code', 'rfs-email-verification-for-gravity-forms' ),
							'required'      => true,
						),
						array(
							'label'         => esc_html__( 'Show resend button', 'rfs-email-verification-for-gravity-forms' ) . esc_attr( $pro_only_affix ),
							'tooltip'       => esc_html__( 'Allow users to resend the code', 'rfs-email-verification-for-gravity-forms' ),
							'type'          => 'toggle',
							'name'          => RFS_GF_EMAIL_VERIFICATION_SETTINGS_SLUG . '-button-show-resend',
							'default_value' => false,
							'readonly'      => true,
						),
						array(
							'label'         => esc_html__( 'Resend Code button text', 'rfs-email-verification-for-gravity-forms' ) . esc_attr( $pro_only_affix ),
							'type'          => 'text',
							'name'          => RFS_GF_EMAIL_VERIFICATION_SETTINGS_SLUG . '-button-label-resend',
							'class'         => 'medium',
							'default_value' => esc_html__( 'Resend the code', 'rfs-email-verification-for-gravity-forms' ),
							'required'      => true,
							'readonly'      => true,
						),
						array(
							'label'         => esc_html__( 'Resend interval', 'rfs-email-verification-for-gravity-forms' ) . esc_attr( $pro_only_affix ),
							'description'   => esc_html__( 'Enter number of seconds. Set to -1 to disable.', 'rfs-email-verification-for-gravity-forms' ),
							'tooltip'       => esc_html__( 'This option prevents hitting the resend button multiple times in a row, which would result in sending multiple emails.', 'rfs-email-verification-for-gravity-forms' ),
							'type'          => 'text',
							'input_type'    => 'number',
							'name'          => RFS_GF_EMAIL_VERIFICATION_SETTINGS_SLUG . '-button-interval-resend',
							'class'         => 'gform-admin-input',
							'default_value' => 10,
							'min'           => -1,
							'step'          => 1,
							'required'      => true,
							'readonly'      => true,
						),
						array(
							'label'         => esc_html__( 'Resend interval text', 'rfs-email-verification-for-gravity-forms' ) . esc_attr( $pro_only_affix ),
							'description'   => __( 'Use {resend_counter} tag to display counter.', 'rfs-email-verification-for-gravity-forms' ),
							'type'          => 'text',
							'name'          => RFS_GF_EMAIL_VERIFICATION_SETTINGS_SLUG . '-button-interval-text',
							'class'         => 'medium',
							'default_value' => 'You can resend in: {resend_counter}',
							'required'      => true,
							'readonly'      => true,
						),
						array(
							'label'         => esc_html__( 'Code expiry time', 'rfs-email-verification-for-gravity-forms' ) . esc_attr( $pro_only_affix ),
							'description'   => esc_html__( 'Enter number of minutes. Set to -1 to never expire.', 'rfs-email-verification-for-gravity-forms' ),
							'tooltip'       => esc_html__( 'The code will auto expire after submitting the form or reloading the page, regardless of this setting.', 'rfs-email-verification-for-gravity-forms' ),
							'type'          => 'text',
							'input_type'    => 'number',
							'name'          => RFS_GF_EMAIL_VERIFICATION_SETTINGS_SLUG . '-code-expiry',
							'class'         => 'gform-admin-input',
							'default_value' => 5,
							'min'           => -1,
							'step'          => 1,
							'required'      => true,
							'readonly'      => true,
						),
						array(
							'label'         => esc_html__( 'Code sent message - verification code field', 'rfs-email-verification-for-gravity-forms' ),
							'type'          => 'textarea',
							'default_value' => esc_html__( 'The verification code has been sent to your email address. It will be valid for 5 minutes.', 'rfs-email-verification-for-gravity-forms' ),
							'name'          => RFS_GF_EMAIL_VERIFICATION_SETTINGS_SLUG . '-code-sent-message',
							'required'      => true,
						),
						array(
							'label'         => esc_html__( 'Code sent message - email field', 'rfs-email-verification-for-gravity-forms' ) . esc_attr( $pro_only_affix ),
							'description'   => esc_html__( 'This message will display in cases where the fields are on different pages of multi-page form.', 'rfs-email-verification-for-gravity-forms' ),
							'type'          => 'textarea',
							'default_value' => esc_html__( 'The verification code has been sent to your email address. Go to the next page to enter the code. It will be valid for 2 minutes.', 'rfs-email-verification-for-gravity-forms' ),
							'name'          => RFS_GF_EMAIL_VERIFICATION_SETTINGS_SLUG . '-code-sent-message-email-field',
							'required'      => true,
							'readonly'      => true,
						),
					),
				),
				array(
					'title'  => __( 'Email', 'rfs-email-verification-for-gravity-forms' ),
					'fields' => array(
						array(
							'label'         => esc_html__( 'Email subject', 'rfs-email-verification-for-gravity-forms' ),
							'type'          => 'text',
							'name'          => RFS_GF_EMAIL_VERIFICATION_SETTINGS_SLUG . '-email-subject',
							'class'         => 'medium merge-tag-support mt-hide_all_fields',
							'default_value' => '{site_title} - Your email verification code',
							'required'      => true,
						),
						array(
							'label'         => esc_html__( 'Email from', 'rfs-email-verification-for-gravity-forms' ),
							'type'          => 'text',
							'name'          => RFS_GF_EMAIL_VERIFICATION_SETTINGS_SLUG . '-email-from',
							'class'         => 'medium',
							'default_value' => '{admin_email}',
							'required'      => true,
						),
						array(
							'label'         => esc_html__( 'Email message', 'rfs-email-verification-for-gravity-forms' ) . esc_attr( $pro_only_affix ),
							'type'          => 'textarea',
							'default_value' => esc_html__( 'Your verification code is: {verification_code}', 'rfs-email-verification-for-gravity-forms' ),
							'name'          => RFS_GF_EMAIL_VERIFICATION_SETTINGS_SLUG . '-email-notification',
							'class'         => '',
							'required'      => true,
							'readonly'      => true,
						),
					),
				),
				array(
					'title'  => __( 'Multi-Page', 'rfs-email-verification-for-gravity-forms' ),
					'fields' => array(
						array(
							'label'         => esc_html__( 'Send code button display', 'rfs-email-verification-for-gravity-forms' ) . esc_attr( $pro_only_affix ),
							'type'          => 'radio',
							'name'          => RFS_GF_EMAIL_VERIFICATION_SETTINGS_SLUG . '-multi-page-button-display',
							'default_value' => 'code-page',
							'readonly'      => true,
							'choices'       => array(
								array(
									'label' => esc_html__( 'Same page as Verification Code field', 'rfs-email-verification-for-gravity-forms' ),
									'value' => 'code-page',
								),
								array(
									'label' => esc_html__( 'Same page as Email field', 'rfs-email-verification-for-gravity-forms' ),
									'value' => 'email-page',
								),
							),
						),
						array(
							'label'         => esc_html__( 'Send code button behaviour', 'rfs-email-verification-for-gravity-forms' ) . esc_attr( $pro_only_affix ),
							'tooltip'       => esc_html__( 'Use this option in cases where the Email and Code Verification fields are on different pages and button display is set to "Same page as Email field"', 'rfs-email-verification-for-gravity-forms' ),
							'type'          => 'radio',
							'name'          => RFS_GF_EMAIL_VERIFICATION_SETTINGS_SLUG . '-multi-page-button-behaviour',
							'default_value' => 'default',
							'readonly'      => true,
							'choices'       => array(
								array(
									'label' => esc_html__( 'Default', 'rfs-email-verification-for-gravity-forms' ),
									'value' => 'default',
								),
								array(
									'label' => esc_html__( 'Go to the next page after sending the code', 'rfs-email-verification-for-gravity-forms' ),
									'value' => 'next-page',
								),
							),
						),
					),
				),
			);

			/**
			 * Allows for modifying the addon setttings fields.
			 *
			 * @since 1.0.0
			 *
			 * @param array $settings
			 *
			 * @return array $settings
			 */
			return apply_filters( RFS_GF_EMAIL_VERIFICATION_HOOK_PREFIX . '_settings', $settings );
		}

		/**
		 * Get custom global settings of the addon.
		 */
		public static function get_global_settings() {
			$settings = array(
				array(
					'title'  => esc_html__( 'Ajax', 'rfs-email-verification-for-gravity-forms' ),
					'fields' => array(
						array(
							'label'         => esc_html__( 'Ajax request helper', 'rfs-email-verification-for-gravity-forms' ),
							'description'   => esc_html__( 'Enable this setting if you are experiencing issues with sending the code.', 'rfs-email-verification-for-gravity-forms' ),
							'type'          => 'toggle',
							'name'          => RFS_GF_EMAIL_VERIFICATION_SETTINGS_SLUG . '-ajax-helper',
							'default_value' => false,
						),
					),
				),
			);

			/**
			 * Allows for modifying the addon global setttings fields.
			 *
			 * @since 1.0.0
			 *
			 * @param array $settings
			 *
			 * @return array $settings
			 */
			return apply_filters( RFS_GF_EMAIL_VERIFICATION_HOOK_PREFIX . '_global_settings', $settings );
		}

		/**
		 * Get error message.
		 *
		 * @param array $field  Field type.
		 * @param array $type  Error type.
		 *
		 * @return string
		 */
		public static function get_error_message( $field, $type = 'invalid' ) {
			$error_messages = array(
				'email'                   => array(
					'invalid' => esc_html__( 'The email address entered is invalid.', 'rfs-email-verification-for-gravity-forms' ),
				),
				'email-verification-code' => array(
					'invalid' => esc_html__( 'The verification code entered is invalid.', 'rfs-email-verification-for-gravity-forms' ),
					'fatal'   => array(
						'99' => esc_html__( 'Unexpected error occured. The code could not be sent.', 'rfs-email-verification-for-gravity-forms' ),
						'98' => esc_html__( 'Unexpected error occured.', 'rfs-email-verification-for-gravity-forms' ),
					),
				),
			);

			/**
			 * Allows for modifying the default error messages. They are displayed if the user doesn't enter a custom error message for the field.
			 *
			 * @since 1.0.0
			 *
			 * @param array $error_messages
			 *
			 * @return array $error_messages
			 */
			$error_messages = apply_filters( RFS_GF_EMAIL_VERIFICATION_HOOK_PREFIX . '_default_error_messages', $error_messages );

			return isset( $error_messages[ $field ][ $type ] ) ? $error_messages[ $field ][ $type ] : '';
		}
	}
}
