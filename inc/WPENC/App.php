<?php
/**
 * WPENC\App class
 *
 * @package WPENC
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.5.0
 */

namespace WPENC;

use LaL_WP_Plugin as Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPENC\App' ) ) {
	/**
	 * This class initializes the plugin.
	 *
	 * It also triggers the action and filter to hook into and contains all API functions of the plugin.
	 *
	 * @since 0.5.0
	 */
	final class App extends Plugin {

		/**
		 * @since 0.5.0
		 * @var array Holds the plugin data.
		 */
		protected static $_args = array();

		/**
		 * @since 0.5.0
		 * @var boolean Stores whether this is a multi network setup.
		 */
		protected static $is_multinetwork = null;

		/**
		 * Class constructor.
		 *
		 * This is protected on purpose since it is called by the parent class' singleton.
		 *
		 * @internal
		 * @since 0.5.0
		 * @param array $args Array of class arguments passed by the plugin utility class.
		 */
		protected function __construct( $args ) {
			parent::__construct( $args );
		}

		/**
		 * The run() method.
		 *
		 * This will initialize the plugin.
		 *
		 * @internal
		 * @since 0.5.0
		 */
		protected function run() {
			$action_handler = new ActionHandler();
			$action_handler->run();

			$context = 'site';
			if ( is_multisite() ) {
				$context = 'network';

				$settings_api = new NetworkSettingsAPI();
				$settings_api->run();
			} else {
				add_filter( 'map_meta_cap', array( $this, 'map_meta_cap_non_multisite' ), 10, 4 );
			}

			$admin = new Admin( $context );
			$admin->run();
		}

		public function map_meta_cap_non_multisite( $caps, $cap, $user_id, $args ) {
			if ( 'manage_certificates' === $cap ) {
				$caps = array( 'manage_options' );
			}
			return $caps;
		}

		/**
		 * Checks whether this is a multi network setup.
		 *
		 * @since 0.5.0
		 * @return boolean
		 */
		public static function is_multinetwork() {
			if ( ! is_multisite() ) {
				return false;
			}

			if ( null === self::$is_multinetwork ) {
				global $wpdb;

				$network_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->site" );

				self::$is_multinetwork = 1 < $network_count;
			}

			return self::$is_multinetwork;
		}

		public static function filter_plugin_links( $links = array() ) {
			if ( is_multisite() ) {
				return self::filter_network_plugin_links( $links );
			}

			if ( ! current_user_can( 'manage_certificates' ) ) {
				return $links;
			}

			$custom_links = array(
				'<a href="' . admin_url( 'options-general.php?page=wp_encrypt' ) . '">' . __( 'Settings', 'wp-encrypt' ) . '</a>',
			);

			return array_merge( $custom_links, $links );
		}

		public static function filter_network_plugin_links( $links = array() ) {
			if ( ! current_user_can( 'manage_certificates' ) ) {
				return $links;
			}

			$custom_links = array(
				'<a href="' . network_admin_url( 'settings.php?page=wp_encrypt' ) . '">' . __( 'Settings', 'wp-encrypt' ) . '</a>',
			);

			return array_merge( $custom_links, $links );
		}
	}
}
