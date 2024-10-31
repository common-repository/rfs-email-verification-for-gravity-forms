<?php
namespace RFS_GF_EMAIL_VERIFICATION;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( '\RFS_GF_EMAIL_VERIFICATION\GF_Email_Verification_Addon' ) ) {

	\GFForms::include_addon_framework();

	/**
	 * GF_Email_Verification_Addon class
	 */
	class GF_Email_Verification_Addon extends \GFAddOn {

		/**
		 * Version number of the add-on.
		 *
		 * @var string
		 */
		protected $_version = RFS_GF_EMAIL_VERIFICATION_VERSION; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

		/**
		 * The minimum Gravity Forms version required for the add-on to load.
		 *
		 * @var string
		 */
		protected $_min_gravityforms_version = '2.5'; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

		/**
		 * The minimum Gravity Forms version required to support all the features of an add-on.
		 *
		 * @var string
		 */
		protected $_min_compatible_gravityforms_version = '2.5'; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

		/**
		 * URL-friendly identifier used for form settings, add-on settings.
		 *
		 * @var string
		 */
		protected $_slug = RFS_GF_EMAIL_VERIFICATION_SLUG; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

		/**
		 * Relative path to the plugin from the plugins folder.
		 *
		 * @var string
		 */
		protected $_path = RFS_GF_EMAIL_VERIFICATION_PATH; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

		/**
		 * Full path to the plugin.
		 *
		 * @var string
		 */
		protected $_full_path = __FILE__; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

		/**
		 * Title of the plugin to be used on the settings page, form settings and plugins page.
		 *
		 * @var string
		 */
		protected $_title = RFS_GF_EMAIL_VERIFICATION_ADDON_NAME; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

		/**
		 * Short version of the plugin title.
		 *
		 * @var string
		 */
		protected $_short_title = RFS_GF_EMAIL_VERIFICATION_ADDON_NAME; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

		/**
		 * The addon class instance.
		 *
		 * @var class|null
		 */
		private static $_instance = null; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

		/**
		 * Get the instance of the addon class.
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
		 * Register custom field for the addon before the addon is initialized.
		 */
		public function pre_init() {
			parent::pre_init();

			if ( $this->is_gravityforms_supported() && class_exists( 'GF_Field' ) ) {
					require_once RFS_GF_EMAIL_VERIFICATION_FULL_DIR . '/classes/GF_Email_Verification_Field.php';

					\GF_Fields::register( new GF_Email_Verification_Field() );
			}
		}

		/**
		 * Initialize the addon.
		 */
		public function init() {
			parent::init();

			add_filter( 'gform_field_settings_tabs', array( $this, 'field_custom_settings_tab' ), 10, 2 );
			add_action( 'gform_field_settings_tab_content', array( $this, 'field_custom_settings_tab_content' ), 10, 2 );
		}



		/**
		 * Load the addon related settings fields to be rendered on the global settings page.
		 */
		public function plugin_settings_fields() {
			return Gravity_Forms::get_global_settings();
		}

		/**
		 * Add custom tab to the field settings.
		 *
		 * @param array $tabs tabs list.
		 * @param array $form object.
		 *
		 * @return array
		 */
		public function field_custom_settings_tab( $tabs, $form ) {
			$tabs[] = array(
				'id'    => RFS_GF_EMAIL_VERIFICATION_SLUG . '-pro',
				'title' => esc_attr__( 'PRO Version', 'rfs-email-verification-for-gravity-forms' ),
			);

			return $tabs;
		}

		/**
		 * Display content in the field custom tab
		 *
		 * @param array  $form Form object.
		 * @param string $tab_id Current tab id.
		 *
		 * @return html
		 */
		public function field_custom_settings_tab_content( $form, $tab_id ) {
			if ( $tab_id === RFS_GF_EMAIL_VERIFICATION_SLUG . '-pro' ) {
					$notice_text = Helpers::get_pro_notice_text();

				return sprintf( '<li>%s</li>', $notice_text );
			}
		}

		/**
		 * Load the addon related settings fields to be rendered on the form settings page.
		 *
		 * @param array $form Form object.
		 */
		public function form_settings_fields( $form ) {
				return Gravity_Forms::get_settings();
		}

		/**
		 * Add functions to be loaded on the frontend only.
		 */
		public function init_frontend() {
			add_action( 'gform_enqueue_scripts', array( $this, 'load_styles_scripts' ), 10, 2 );
		}

		/**
		 * Enqueue addon related styles and scripts on frontend
		 *
		 * @param array $form Form object.
		 */
		public function load_styles_scripts( $form ) {
			$form_info = Gravity_Forms::get_form_info( $form );

			if ( $form_info['id'] ) {
				wp_enqueue_script( RFS_GF_EMAIL_VERIFICATION_SLUG, RFS_GF_EMAIL_VERIFICATION_URL . '/dist/js/main.js', array( 'jquery' ), RFS_GF_EMAIL_VERIFICATION_VERSION, true );
				wp_enqueue_style( RFS_GF_EMAIL_VERIFICATION_SLUG, RFS_GF_EMAIL_VERIFICATION_URL . '/dist/css/main.css', array(), RFS_GF_EMAIL_VERIFICATION_VERSION, 'all' );

				wp_localize_script(
					RFS_GF_EMAIL_VERIFICATION_SLUG,
					'RFS_GF_EMAIL_VERIFICATION_FORM_' . $form_info['id'],
					array(
						'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
						'nonce'    => wp_create_nonce( 'security' ),
						'formInfo' => $form_info,
					)
				);
			}
		}

		/**
		 * Enqueue addon related styles in admin areas
		 *
		 * @return array
		 */
		public function styles() {
			$styles = array(
				array(
					'handle'  => RFS_GF_EMAIL_VERIFICATION_SLUG . '_addon',
					'src'     => RFS_GF_EMAIL_VERIFICATION_URL . '/dist/css/addon.css',
					'version' => RFS_GF_EMAIL_VERIFICATION_VERSION,
					'enqueue' => array(
						array( 'admin_page' => array( 'form_settings', 'form_editor' ) ),
					),
				),
			);

			return array_merge( parent::styles(), $styles );
		}

		/**
		 * Enqueue addon related scripts in admin areas
		 *
		 * @return array
		 */
		public function scripts() {
			$scripts = array(
				array(
					'handle'  => RFS_GF_EMAIL_VERIFICATION_HOOK_PREFIX . '_addon',
					'src'     => RFS_GF_EMAIL_VERIFICATION_URL . '/dist/js/addon.js',
					'version' => RFS_GF_EMAIL_VERIFICATION_VERSION,
					'deps'    => array( 'jquery' ),
					'strings' => array(
						'label'         => esc_attr__( 'Email Verification Code', 'rfs-email-verification-for-gravity-forms' ),
						'alert'         => esc_attr__( 'The field has already been added to the form. One per form is allowed.', 'rfs-email-verification-for-gravity-forms' ),
						'emailReminder' => esc_attr__( 'Please make sure to also add an email field into your form.', 'rfs-email-verification-for-gravity-forms' ),
					),
					'enqueue' => array(
						array( 'admin_page' => array( 'form_editor' ) ),
					),
				),
			);

			return array_merge( parent::scripts(), $scripts );
		}
	}

}
