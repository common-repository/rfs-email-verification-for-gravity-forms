<?php
/**
 * Plugin Name: RFS Email Verification for Gravity Forms
 * Plugin URI: https://rfswp.com//plugins/rfs-email-verification-for-gravity-forms/
 * Description: Email OTP Verification for Gravity Forms. Easily verify or athenticate your users.
 * Version: 1.0.2
 * Author: Rafal Puczel - RFS WP
 * Author URI: https://rfswp.com/
 * Copyright: Rafal Puczel - RFS WP
 * Text Domain: rfs-email-verification-for-gravity-forms
 * Domain Path: /languages
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package RFS_GF_Email_Verification
 */

namespace RFS_GF_EMAIL_VERIFICATION;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/*
 * @var string Plugin version.
 */
if ( ! defined( 'RFS_GF_EMAIL_VERIFICATION_VERSION' ) ) {
	define( 'RFS_GF_EMAIL_VERIFICATION_VERSION', '1.0.2' );
}

/*
 * @var string Plugin full path.
 */
if ( ! defined( 'RFS_GF_EMAIL_VERIFICATION_FULL_PATH' ) ) {
	define( 'RFS_GF_EMAIL_VERIFICATION_FULL_PATH', __FILE__ );
}

/*
 * @var string Plugin directory.
 */
if ( ! defined( 'RFS_GF_EMAIL_VERIFICATION_FULL_DIR' ) ) {
	define( 'RFS_GF_EMAIL_VERIFICATION_FULL_DIR', __DIR__ );
}

// Plugin initialize.
require_once RFS_GF_EMAIL_VERIFICATION_FULL_DIR . '/classes/Helpers.php';
require_once RFS_GF_EMAIL_VERIFICATION_FULL_DIR . '/classes/Plugin.php';
\RFS_GF_EMAIL_VERIFICATION\Plugin::get_instance();
