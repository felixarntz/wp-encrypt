<?php
/**
 * @package WPENC
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPENC;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPENC\NetworkSettingsAPI' ) ) {
	/**
	 * This class implements a simple Settings API for the network admin.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	final class NetworkSettingsAPI {
		public function run() {
			add_action( 'wpmuadminedit', array( $this, 'update_site_option' ) );
		}

		public function update_site_option() {
			if ( ! isset( $_POST['option_page'] ) || ! isset( $_POST['action'] ) || 'update' !== $_POST['action'] ) {
				return;
			}

			$option_page = $_POST['option_page'];
			if ( 'wp_encrypt_settings' !== $option_page ) {
				return;
			}

			check_admin_referer( 'wp_encrypt_settings-options' );

			if ( isset( $_POST['wp_encrypt_settings'] ) ) {
				update_site_option( 'wp_encrypt_settings', wp_unslash( $_POST['wp_encrypt_settings'] ) );
			}

			wp_redirect( add_query_arg( 'updated', 'true', network_admin_url( 'settings.php?page=wp_encrypt' ) ) );
		}
	}
}
