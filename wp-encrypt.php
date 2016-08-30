<?php
/*
Plugin Name: WP Encrypt
Plugin URI:  https://wordpress.org/plugins/wp-encrypt/
Description: Generate and manage SSL certificates for your WordPress sites for free with this Let's Encrypt client.
Version:     1.0.0-beta.7
Author:      Felix Arntz
Author URI:  https://leaves-and-love.net
License:     GNU General Public License v3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Text Domain: wp-encrypt
Network:     true
Tags:        lets encrypt, ssl, certificates, https, free ssl
*/
/**
 * Plugin initialization file
 *
 * @package WPENC
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( version_compare( phpversion(), '5.3.0' ) >= 0 && ! class_exists( 'WPENC\App' ) ) {
	if ( file_exists( dirname( __FILE__ ) . '/wp-encrypt/vendor/autoload.php' ) ) {
		require_once dirname( __FILE__ ) . '/wp-encrypt/vendor/autoload.php';
	} elseif ( file_exists( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) {
		require_once dirname( __FILE__ ) . '/vendor/autoload.php';
	}
} elseif ( ! class_exists( 'LaL_WP_Plugin_Loader' ) ) {
	if ( file_exists( dirname( __FILE__ ) . '/wp-encrypt/vendor/felixarntz/leavesandlove-wp-plugin-util/leavesandlove-wp-plugin-loader.php' ) ) {
		require_once dirname( __FILE__ ) . '/wp-encrypt/vendor/felixarntz/leavesandlove-wp-plugin-util/leavesandlove-wp-plugin-loader.php';
	} elseif ( file_exists( dirname( __FILE__ ) . '/vendor/felixarntz/leavesandlove-wp-plugin-util/leavesandlove-wp-plugin-loader.php' ) ) {
		require_once dirname( __FILE__ ) . '/vendor/felixarntz/leavesandlove-wp-plugin-util/leavesandlove-wp-plugin-loader.php';
	}
}

// Initialize the plugin through the plugin loader.
LaL_WP_Plugin_Loader::load_plugin( array(
	'slug'					=> 'wp-encrypt',
	'name'					=> 'WP Encrypt',
	'version'				=> '1.0.0-beta.7',
	'main_file'				=> __FILE__,
	'namespace'				=> 'WPENC',
	'textdomain'			=> 'wp-encrypt',
	'use_language_packs'	=> true,
	'network_only'			=> true,
), array(
	'phpversion'			=> '5.3.0',
	'wpversion'				=> '4.2',
	'functions'				=> array(
		'curl_init',
		'curl_setopt',
		'openssl_pkey_new',
		'openssl_csr_new',
	),
) );

/**
 * Returns the WP Encrypt main class instance.
 *
 * @since 1.0.0
 *
 * @return WPENC\App|null The WP Encrypt instance or null if failed to initialize.
 */
function wpenc() {
	return LaL_WP_Plugin_Loader::get_plugin( 'wp-encrypt' );
}
