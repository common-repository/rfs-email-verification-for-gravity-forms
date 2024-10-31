<?php
namespace RFS_GF_EMAIL_VERIFICATION;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( '\RFS_GF_EMAIL_VERIFICATION\Plugin' ) ) {

	/**
	 * Plugin class.
	 */
	class Plugin {
		/**
		 * The class instance.
		 *
		 * @var class|null
		 */
		private static $_instance = null; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

		/**
		 * The class constructor.
		 */
		private function __construct() {
			add_action( 'plugins_loaded', array( $this, 'init' ), 9 );
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
		 * Initialize plugin functionality.
		 */
		public function init() {
			$this->define_constants();
			$this->i18n();
			$this->load_plugin_core();
		}

		/**
		 * Define plugin constants.
		 */
		public function define_constants() {
			/*
				* @var string Plugin name.
				*/
			if ( ! defined( 'RFS_GF_EMAIL_VERIFICATION_NAME' ) ) {
				define( 'RFS_GF_EMAIL_VERIFICATION_NAME', 'RFS Email Verification for Gravity Forms' );
			}

			/*
				* @var string Addon name.
				*/
			if ( ! defined( 'RFS_GF_EMAIL_VERIFICATION_ADDON_NAME' ) ) {
				define( 'RFS_GF_EMAIL_VERIFICATION_ADDON_NAME', 'Email Verification' );
			}

			/*
				* @var string Plugin folder slug.
				*/
			if ( ! defined( 'RFS_GF_EMAIL_VERIFICATION_REAL_SLUG' ) ) {
				define( 'RFS_GF_EMAIL_VERIFICATION_REAL_SLUG', basename( RFS_GF_EMAIL_VERIFICATION_FULL_DIR ) );
			}

			/*
				* @var string Plugin slug.
				*/
			if ( ! defined( 'RFS_GF_EMAIL_VERIFICATION_SLUG' ) ) {
				define( 'RFS_GF_EMAIL_VERIFICATION_SLUG', 'rfs-email-verification-for-gravity-forms' );
			}

			/*
				* @var string Plugin settings slug.
				*/
			if ( ! defined( 'RFS_GF_EMAIL_VERIFICATION_SETTINGS_SLUG' ) ) {
				define( 'RFS_GF_EMAIL_VERIFICATION_SETTINGS_SLUG', RFS_GF_EMAIL_VERIFICATION_SLUG );
			}

			/*
				* @var string Plugin path inside plugins directory.
				*/
			if ( ! defined( 'RFS_GF_EMAIL_VERIFICATION_PATH' ) ) {
				define( 'RFS_GF_EMAIL_VERIFICATION_PATH', plugin_basename( RFS_GF_EMAIL_VERIFICATION_FULL_PATH ) );
			}

			/*
				* @var string Plugin hooks (and filters) prefix and js localization handle
				*/
			if ( ! defined( 'RFS_GF_EMAIL_VERIFICATION_HOOK_PREFIX' ) ) {
				define( 'RFS_GF_EMAIL_VERIFICATION_HOOK_PREFIX', 'rfs_gf_ev' );
			}

			/*
				* @var string Plugin url.
				*/
			if ( ! defined( 'RFS_GF_EMAIL_VERIFICATION_URL' ) ) {
				define( 'RFS_GF_EMAIL_VERIFICATION_URL', plugins_url() . '/' . RFS_GF_EMAIL_VERIFICATION_REAL_SLUG );
			}

			/*
				* @var string Addon cookie base name.
				*/
			if ( ! defined( 'RFS_GF_EMAIL_VERIFICATION_COOKIE_NAME' ) ) {
				define( 'RFS_GF_EMAIL_VERIFICATION_COOKIE_NAME', RFS_GF_EMAIL_VERIFICATION_SLUG . '-code-form' );
			}

			/*
				* @var string plugin website url
				*/
			if ( ! defined( 'RFS_GF_EMAIL_VERIFICATION_WEB_URL' ) ) {
				define( 'RFS_GF_EMAIL_VERIFICATION_WEB_URL', 'https://rfswp.com/' );
			}

			/*
				* @var string plugin external page url
				*/
			if ( ! defined( 'RFS_GF_EMAIL_VERIFICATION_PRODUCT_URL' ) ) {
				define( 'RFS_GF_EMAIL_VERIFICATION_PRODUCT_URL', RFS_GF_EMAIL_VERIFICATION_WEB_URL . 'plugins/rfs-email-verification-for-gravity-forms/' );
			}
		}

		/**
		 * Load plugin translation strings.
		 */
		public function i18n() {
			// Load user's custom translations from wp-content/languages/ folder.
			load_textdomain(
				'rfs-email-verification-for-gravity-forms',
				sprintf(
					'%s/%s-%s.mo',
					WP_LANG_DIR,
					RFS_GF_EMAIL_VERIFICATION_REAL_SLUG,
					get_locale()
				),
			);

			// Load plugin's available translations.
			load_plugin_textdomain(
				'rfs-email-verification-for-gravity-forms',
				false,
				sprintf(
					'%s/languages/',
					RFS_GF_EMAIL_VERIFICATION_REAL_SLUG
				),
			);
		}

		/**
		 * Load custom Gravity Forms functionalities.
		 */
		public function load_plugin_core() {
			if ( Helpers::is_gravity_forms_active() ) {
				require_once RFS_GF_EMAIL_VERIFICATION_FULL_DIR . '/classes/Gravity_Forms.php';
				Gravity_Forms::get_instance();

				$this->pro_version_notices();

				add_filter( 'plugin_action_links_' . RFS_GF_EMAIL_VERIFICATION_PATH, array( $this, 'plugin_action_links' ) );
				add_filter( 'plugin_row_meta', array( $this, 'plugin_row_custom_links' ), 10, 4 );
			} else {
				Helpers::display_notice( RFS_GF_EMAIL_VERIFICATION_NAME . ' ' . esc_html__( 'plugin could not be activated. It requires active Gravity Forms plugin to work. ', 'rfs-email-verification-for-gravity-forms' ), 'error', true );
			}
		}

		/**
		 * Display notices about the pro version.
		 */
		public function pro_version_notices() {
			if ( ! is_admin() || wp_doing_ajax() ) {
				return;
			}

			global $pagenow;

			if ( ! Helpers::is_pro_plugin_active() ) {
				$notice_text = Helpers::get_pro_notice_text();

				if ( $pagenow === 'plugins.php' ) {
					Helpers::display_notice( $notice_text, 'info' );
				}

				if ( \GFForms::is_gravity_page() ) {
					\GFCommon::add_dismissible_message( $notice_text, RFS_GF_EMAIL_VERIFICATION_SLUG . '-message', 'success' );
				}
			}
		}

		/**
		 * Add link to pro version in plugin action links.
		 *
		 * @param string $links  Links array.
		 */
		public function plugin_action_links( $links ) {
			$links[] = sprintf( '<a href="%s" style="font-weight: bold; color: #28a745;" target="_blank" rel="nofollow noopener noreferrer">%s</a>', esc_url( RFS_GF_EMAIL_VERIFICATION_PRODUCT_URL ), esc_attr__( 'Get PRO Version', 'rfs-email-verification-for-gravity-forms' ) );

			return $links;
		}

		/**
		 * Add custom links in plugin meta row.
		 *
		 * @param array  $plugin_meta  Meta array.
		 * @param string $plugin_file  File name.
		 */
		public function plugin_row_custom_links( $plugin_meta, $plugin_file ) {
			if ( $plugin_file === RFS_GF_EMAIL_VERIFICATION_PATH ) {
					$plugin_meta[] = sprintf( '<a href="%s" target="_blank" rel="nofollow noopener noreferrer">%s</a>', esc_url( RFS_GF_EMAIL_VERIFICATION_WEB_URL . 'docs/rfs-email-verification-for-gravity-forms/' ), esc_attr__( 'Documentation', 'rfs-email-verification-for-gravity-forms' ) );
					$plugin_meta[] = sprintf( '<a href="%s" target="_blank" rel="nofollow noopener noreferrer">%s</a>', esc_url( RFS_GF_EMAIL_VERIFICATION_WEB_URL . 'contact' ), esc_attr__( 'Support', 'rfs-email-verification-for-gravity-forms' ) );
			}

			return $plugin_meta;
		}
	}
}
