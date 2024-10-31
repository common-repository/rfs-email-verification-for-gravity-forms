<?php
namespace RFS_GF_EMAIL_VERIFICATION;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( '\RFS_GF_EMAIL_VERIFICATION\Helpers' ) ) {

	/**
	 * Helpers class.
	 */
	class Helpers {
		/**
		 * Check if Gravity Forms plugin has been activated.
		 *
		 * @return bool
		 */
		public static function is_gravity_forms_active() {
			return in_array( 'gravityforms/gravityforms.php', self::get_active_plugins(), true );
		}

		/**
		 * Check if a Free version of the plugin has been installed.
		 *
		 * @return bool
		 */
		public static function is_free_plugin_active() {
			return in_array( 'rfs-email-verification-for-gravity-forms/rfs-email-verification-for-gravity-forms.php', self::get_active_plugins(), true );
		}

		/**
		 * Check if a PRO version of the plugin has been installed.
		 *
		 * @return bool
		 */
		public static function is_pro_plugin_active() {
			return in_array( 'rfs-email-verification-for-gravity-forms-pro/rfs-email-verification-for-gravity-forms-pro.php', self::get_active_plugins(), true );
		}

		/**
		 * Get an array of activated plugins.
		 *
		 * @return array
		 */
		public static function get_active_plugins() {
			return apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
		}

		/**
		 * Generate random verification code.
		 *
		 * @return string
		 */
		public static function generate_verification_code() {
			return strtoupper( substr( sha1( microtime() ), wp_rand( 0, 5 ), 30 ) );
		}

		/**
		 * Create verification code.
		 *
		 * @param string $generated_verification_code .
		 *
		 * @return string
		 */
		public static function get_real_verification_code( $generated_verification_code ) {
			$vc = str_split( $generated_verification_code, 5 );

			if ( ! isset( $vc[4] ) ) {
					return '';
			}

			return sprintf( '%s-%s-%s-%s-%s', $vc[4], $vc[2], $vc[0], $vc[3], $vc[1] );
		}

		/**
		 * Encrypt or decrypt the verification code.
		 *
		 * @param string $action  Action name.
		 * @param string $code  Code.
		 *
		 * @return string
		 */
		public static function encrypt_decrypt( $action, $code ) {
			$output         = false;
			$encrypt_method = 'AES-256-CBC';
			$secret_key     = '20L4ihmVL9ci0U3SJvCHgA0Tc0erZv9X';
			$secret_iv      = 'N3dsWOZKgwthxjwsVhIoghzAKSqy6uxy';

			// hash.
			$key = hash( 'sha256', $secret_key );

			// iv - encrypt method AES-256-CBC expects 16 bytes.
			$iv = substr( hash( 'sha256', $secret_iv ), 0, 16 );

			if ( $action === 'encrypt' ) {
					$output = openssl_encrypt( $code, $encrypt_method, $key, 0, $iv );
					$output = base64_encode( $output ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			} elseif ( $action === 'decrypt' ) {
					$output = openssl_decrypt( base64_decode( $code ), $encrypt_method, $key, 0, $iv ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			}

			return $output;
		}

		/**
		 * Set verification code cookie for the form.
		 *
		 * @param string $form_id Current form id.
		 * @param string $value cookie value.
		 * @param string $expiry expity time in seconds.
		 *
		 * @return void
		 */
		public static function set_cookie( $form_id, $value, $expiry = 300 ) {
			$cookie_name    = RFS_GF_EMAIL_VERIFICATION_COOKIE_NAME . '-' . $form_id;
			$cookie_expires = $expiry <= 0 ? 0 : time() + $expiry;

			setcookie( $cookie_name, $value, $cookie_expires, '/', COOKIE_DOMAIN, is_ssl(), true );
		}

		/**
		 * Get verification code cookie for the form.
		 *
		 * @param string $form_id Current form id.
		 *
		 * @return string|null
		 */
		public static function get_cookie( $form_id ) {
			$cookie_name = RFS_GF_EMAIL_VERIFICATION_COOKIE_NAME . '-' . $form_id;

			return isset( $_COOKIE[ $cookie_name ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) ) : null;
		}

		/**
		 * Clear forms custom cookies.
		 */
		public static function clear_cookies() {
			if ( $_COOKIE ) {
				// Needs to loop through the whole $_COOKIE array because the cookie name is dynamic (includes the form id).
				foreach ( $_COOKIE as $cookie_name => $cookie_value ) {
					if ( strpos( $cookie_name, RFS_GF_EMAIL_VERIFICATION_COOKIE_NAME ) !== false ) {
							self::clear_cookie( $cookie_name );
							break;
					}
				}
			}
		}

		/**
		 * Clear cookie by its name.
		 *
		 * @param string $cookie_name name of the cookie.
		 */
		public static function clear_cookie( $cookie_name ) {
			setcookie( $cookie_name, '', -1, '/', COOKIE_DOMAIN, is_ssl(), true );
		}

		/**
		 * Send email to user.
		 *
		 * @param array $form_settings the form settings.
		 * @param array $post_data     The post data including $_POST.
		 *
		 * @return bool
		 */
		public static function send_email( $form_settings, $post_data ) {
			$email_from = self::prepare_text( $form_settings['email-from'] );
			$subject    = self::prepare_text( $form_settings['email-subject'], $post_data );
			$message    = self::prepare_text( $form_settings['email-notification'], $post_data );

			/**
			 * Allows for modifying the email message or using your custom email template.
			 *
			 * @since 1.0.0
			 *
			 * @param html  $message
			 * @param array $form_settings
			 * @param array $post_data     $_POST data + custom data
			 *
			 * @return html $message
			 */
			$message = apply_filters( RFS_GF_EMAIL_VERIFICATION_HOOK_PREFIX . '_email_message', $message, $form_settings, $post_data );

			$from_name = esc_attr( get_bloginfo( 'name' ) );

			/**
			 * Allows for modifying the email "from name", which is a blog name by default.
			 *
			 * @since 1.0.0
			 *
			 * @param string $from_name
			 * @param array  $form_settings
			 * @param array  $post_data     $_POST data + custom data
			 *
			 * @return string $from_name
			 */
			$from_name = apply_filters( RFS_GF_EMAIL_VERIFICATION_HOOK_PREFIX . '_email_from_name', $from_name, $form_settings, $post_data );

			if ( $email_from ) {
				return \GFCommon::send_email(
					$email_from,
					$post_data['email'],
					'',
					'',
					$subject,
					$message,
					$from_name,
					'html',
					'',
					false,
					array(
						'id'   => 'gfvc',
						'name' => RFS_GF_EMAIL_VERIFICATION_ADDON_NAME,
					),
					null
				);
			}
		}

		/**
		 * Prepare text with merge tags.
		 *
		 * @param string|html $text the content to prepare.
		 * @param array       $args Additional data for dynamic merge tags.
		 *
		 * @return string|html
		 */
		public static function prepare_text( $text, $args = array() ) {
			$tags    = self::get_merge_tags_from_text( $text );
			$find    = array();
			$replace = array();

			foreach ( $tags as $group_name => $group_tags ) {
				foreach ( $group_tags as $key => $pattern ) {
					$find[] = '/' . $pattern . '/';

					if ( $group_name !== 'fields' ) {
						$replace[] = self::replace_merge_tag( $pattern, $args );
					}
				}
			}

			if ( ! empty( $find ) ) {
				$text = preg_replace( $find, $replace, $text );
			}

			return $text;
		}

		/**
		 * Extract merge tags from text.
		 *
		 * @param string|html $text  the content to process.
		 * @param array       $group The group name to get tags for. By default all groups are returned.
		 *
		 * @return array
		 */
		public static function get_merge_tags_from_text( $text, $group = 'all' ) {
			preg_match_all( '/{+(.*?)}/', $text, $matches );

			$tags = array(
				'other' => array(),
			);

			if ( isset( $matches[1] ) ) {
				foreach ( $matches[1] as $match ) {
					$tag = explode( ':', $match );

					if ( isset( $tag[0] ) ) {
							$tags['other'][] = sprintf( '{%s}', $match );
					}
				}
			}

			if ( $group === 'all' ) {
				return $tags;
			} else {
				return isset( $tags[ $group ] ) ? $tags[ $group ] : array();
			}
		}

		/**
		 * Replace merge tags with values.
		 *
		 * @param string $tag  the tag to replace.
		 * @param array  $args Additional data for dynamic merge tags.
		 *
		 * @return array
		 */
		public static function replace_merge_tag( $tag, $args = array() ) {
			$replace = '';

			switch ( $tag ) {
				case '{verification_code}':
					if ( isset( $args['verification_code'] ) ) {
						$replace = $args['verification_code'];
					}
					break;
				case '{site_title}':
						$replace = self::get_site_title();
					break;
				case '{admin_email}':
						$replace = self::get_admin_email();
					break;
				default:
					break;
			}

			return $replace;
		}

		/**
		 * Get the site title.
		 *
		 * @return string
		 */
		public static function get_site_title() {
			return esc_html( get_bloginfo( 'name' ) );
		}

		/**
		 * Get the site admin email.
		 *
		 * @return string
		 */
		public static function get_admin_email() {
			return esc_attr( get_option( 'admin_email' ) );
		}

		/**
		 * Get the notice text offering a Pro version of the plugin.
		 *
		 * @return string
		 */
		public static function get_pro_notice_text() {
			return sprintf(
				__( 'Enjoying the %1$s plugin? Get %2$s to get more features and premium support. ', 'rfs-email-verification-for-gravity-forms' ), // phpcs:ignore WordPress.WP.I18n.MissingTranslatorsComment
				RFS_GF_EMAIL_VERIFICATION_NAME,
				sprintf(
					'<a href="%s" target="_blank" rel="nofollow noopener noreferrer">%s</a>',
					esc_url( RFS_GF_EMAIL_VERIFICATION_PRODUCT_URL ),
					esc_html__( 'PRO Version', 'rfs-email-verification-for-gravity-forms' )
				)
			);
		}

		/**
		 * Display wp admin notice.
		 *
		 * @param string $notice         notice text.
		 * @param string $notice_type    error, warning, info.
		 * @param bool   $disable_plugin true to disable self.
		 *
		 * @return void
		 */
		public static function display_notice( $notice, $notice_type, $disable_plugin = false ) {
			add_action(
				'admin_notices',
				function () use ( $notice, $notice_type, $disable_plugin ) {
					$notice_types = array(
						'error',
						'warning',
						'info',
					);

					$notice_type = in_array( $notice_type, $notice_types, true ) ? $notice_type : 'error';

					printf(
						'<div class="notice notice-%s is-dismissible"><p><strong>%s</strong></p></div>',
						esc_attr( $notice_type ),
						wp_kses_post( $notice )
					);

					if ( $disable_plugin ) {
						$plugin = RFS_GF_EMAIL_VERIFICATION_PATH;

						if ( is_plugin_active( $plugin ) ) {
							deactivate_plugins( $plugin );
						}
					}
				}
			);
		}

		/**
		 * Get add-on global settings values.
		 *
		 * @param string $setting_name provide setting name key to get value for that setting only.
		 *
		 * @return array|string
		 */
		public static function get_addon_global_settings( $setting_name = '' ) {
			$global_settings = get_option( 'gravityformsaddon_' . RFS_GF_EMAIL_VERIFICATION_SLUG . '_settings' );
			$settings        = array();

			if ( ! $global_settings ) {
				$global_settings = array();
			}

			if ( $global_settings ) {
				foreach ( $global_settings as $key => $value ) {
					$setting              = str_replace( RFS_GF_EMAIL_VERIFICATION_SLUG . '-', '', $key );
					$settings[ $setting ] = $value;
				}
			}

			if ( $setting_name ) {
				return isset( $settings[ $setting_name ] ) ? $settings[ $setting_name ] : '';
			}

			return $settings;
		}

		/**
		 * Check if current page is form settings page.
		 *
		 * @return string
		 */
		public static function is_form_settings_page() {
			$subview = rgget( 'subview' );
			$page    = rgget( 'page' );

			return $page === 'gf_edit_forms' && $subview === RFS_GF_EMAIL_VERIFICATION_SLUG;
		}

		/**
		 * Update log
		 *
		 * @param array $data log data.
		 *
		 * @return void
		 */
		public static function update_log_option( $data ) {
			$current = get_option( RFS_GF_EMAIL_VERIFICATION_HOOK_PREFIX . '_log', array() );

			$updated = array_merge( $current, $data );

			update_option( RFS_GF_EMAIL_VERIFICATION_HOOK_PREFIX . '_log', $updated, false );
		}

		/**
		 * Get log data
		 *
		 * @return array
		 */
		public static function get_log_option() {
			return get_option( RFS_GF_EMAIL_VERIFICATION_HOOK_PREFIX . '_log', array() );
		}

		/**
		 * Delete log
		 *
		 * @return array
		 */
		public static function delete_log_option() {
			return delete_option( RFS_GF_EMAIL_VERIFICATION_HOOK_PREFIX . '_log' );
		}
	}
}
