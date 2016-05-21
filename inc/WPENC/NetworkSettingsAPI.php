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
			add_action( 'wpmuadminedit', array( $this, 'update_network_option' ) );
			add_action( 'network_admin_notices', array( $this, 'network_options_head' ), 9999 );
		}

		public function update_network_option() {
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

			if ( 0 === count( get_settings_errors() ) ) {
				add_settings_error( 'general', 'settings_updated', __( 'Settings saved.' ), 'updated' );
			}

			set_site_transient( 'settings_errors', get_settings_errors(), 30 );

			wp_redirect( add_query_arg( 'updated', 'true', network_admin_url( 'settings.php?page=wp_encrypt' ) ) );
			exit;
		}

		public function network_options_head() {
			global $parent_file, $wp_settings_errors;

			if ( 'settings.php' === $parent_file ) {
				if ( isset( $_GET['updated'] ) && $_GET['updated'] && get_site_transient( 'settings_errors' ) ) {
					$wp_settings_errors = array_merge( (array) $wp_settings_errors, get_site_transient( 'settings_errors' ) );
				}
				settings_errors();
			}
		}
	}
}
