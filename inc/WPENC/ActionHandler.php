<?php
/**
 * @package WPENC
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPENC;

use WPENC\Core\CertificateManager;
use WPENC\Core\Util as CoreUtil;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPENC\ActionHandler' ) ) {
	/**
	 * This class handles actions for both regular POST requests and AJAX requests.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	class ActionHandler {
		protected $actions = array(
			'register_account',
			'generate_certificate',
			'revoke_certificate',
		);

		public function run() {
			foreach ( $this->actions as $action ) {
				add_action( 'admin_action_wpenc_' . $action, array( $this, 'request' ) );
				add_action( 'wp_ajax_wpenc_' . $action, array( $this, 'ajax_request' ) );
			}
		}

		public function request() {
			$this->handle_request();
		}

		public function ajax_request() {
			$this->handle_request( true );
		}

		protected function register_account( $data = array(), $network_wide = false ) {
			$filesystem_check = $this->maybe_request_filesystem_credentials( $network_wide );
			if ( false === $filesystem_check ) {
				return new WP_Error( 'invalid_filesystem_credentials', __( 'Invalid or missing filesystem credentials.', 'wp-encrypt' ), 'error' );
			}

			$manager = CertificateManager::get();
			$response = $manager->register_account();
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			Util::set_registration_info( 'account', $response );

			return __( 'Account registered.', 'wp-encrypt' );
		}

		protected function generate_certificate( $data = array(), $network_wide = false ) {
			if ( ! Util::can_generate_certificate() ) {
				return new WP_Error( 'domain_cannot_sign', __( 'Domain cannot be signed. Either the account is not registered yet or the settings are not valid.', 'wp-encrypt' ) );
			}

			$filesystem_check = $this->maybe_request_filesystem_credentials( $network_wide );
			if ( false === $filesystem_check ) {
				return new WP_Error( 'invalid_filesystem_credentials', __( 'Invalid or missing filesystem credentials.', 'wp-encrypt' ), 'error' );
			}

			$global = isset( $data['include_all_networks'] ) && $data['include_all_networks'];

			$domain = $network_wide ? Util::get_network_domain() : Util::get_site_domain();
			$addon_domains = $network_wide ? Util::get_network_addon_domains( null, $global ) : array();

			$manager = CertificateManager::get();
			$response = $manager->generate_certificate( $domain, $addon_domains, array(
				'ST'	=> Util::get_option( 'country_name' ),
				'C'		=> Util::get_option( 'country_code' ),
				'O'		=> Util::get_option( 'organization' ),
			) );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$ids = $network_wide ? Util::get_current_network_site_ids() : Util::get_current_site_id();

			Util::set_registration_info( $ids, array() );

			$site_domains = $addon_domains;
			array_unshift( $site_domains, $domain );

			return sprintf( __( 'Certificate generated for %s.', 'wp-encrypt' ), implode( ', ', $site_domains ) );
		}

		protected function revoke_certificate( $data = array(), $network_wide = false ) {
			$filesystem_check = $this->maybe_request_filesystem_credentials( $network_wide );
			if ( false === $filesystem_check ) {
				return new WP_Error( 'invalid_filesystem_credentials', __( 'Invalid or missing filesystem credentials.', 'wp-encrypt' ), 'error' );
			}

			$domain = $network_wide ? Util::get_network_domain() : Util::get_site_domain();

			$manager = CertificateManager::get();
			$response = $manager->revoke_certificate( $domain );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$ids = $network_wide ? Util::get_current_network_site_ids() : Util::get_current_site_id();

			Util::delete_registration_info( $ids );

			return __( 'Certificate revoked.', 'wp-encrypt' );
		}

		protected function maybe_request_filesystem_credentials( $network_wide = false ) {
			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				$credentials = array();

				$fields = array( 'hostname', 'port', 'username', 'password', 'public_key', 'private_key', 'connection_type' );
				foreach ( $fields as $field ) {
					if ( isset( $_REQUEST[ $field ] ) ) {
						$credentials[ $field ] = $_REQUEST[ $field ];
					}
				}

				if ( CoreUtil::needs_filesystem_credentials( $credentials ) ) {
					return false;
				}

				return true;
			}

			$url = $this->get_url( $network_wide );
			$extra_fields = array( 'action', '_wpnonce' );

			return CoreUtil::setup_filesystem( $url, $extra_fields );
		}

		protected function handle_request( $ajax = false ) {
			$network_wide = $this->is_network_request();

			$prefix = $ajax ? 'wp_ajax_wpenc_' : 'admin_action_wpenc_';
			$action = str_replace( $prefix, '', current_action() );

			$valid = $this->check_request( $action, $ajax, $network_wide );
			if ( is_wp_error( $valid ) ) {
				$this->handle_error( $valid, $ajax, $network_wide );
			}

			$response = call_user_func( array( $this, $action ), $_REQUEST, $network_wide );
			if ( is_wp_error( $response ) ) {
				$this->handle_error( $response, $ajax, $network_wide );
			}

			$this->handle_success( $response, $ajax, $network_wide );
		}

		protected function is_network_request() {
			return is_network_admin() || isset( $_REQUEST['context'] ) && 'network' === $_REQUEST['context'];
		}

		protected function check_request( $action, $ajax = false, $network_wide = false ) {
			if ( ! isset( $_REQUEST['_wpnonce'] ) ) {
				return new WP_Error( 'nonce_missing', __( 'Missing nonce.', 'wp-encrypt' ) );
			}

			$status = $ajax ? check_ajax_referer( 'wp_encrypt_ajax', '_wpnonce', false ) : wp_verify_nonce( $_REQUEST['_wpnonce'], 'wp_encrypt_action' );
			if ( ! $status ) {
				return new WP_Error( 'nonce_invalid', __( 'Invalid nonce.', 'wp-encrypt' ) );
			}

			if ( ! current_user_can( 'manage_certificates' ) ) {
				return new WP_Error( 'capabilities_lacking', __( 'Lacking required capabilities.', 'wp-encrypt' ) );
			}

			if ( ! method_exists( $this, $action ) ) {
				return new WP_Error( 'action_invalid', __( 'Invalid action.', 'wp-encrypt' ) );
			}

			return true;
		}

		protected function handle_error( $error, $ajax = false, $network_wide = false ) {
			if ( $ajax ) {
				wp_send_json_error( $error->get_error_message() );
			}

			add_settings_error( 'wp_encrypt_action', $error->get_error_code(), $error->get_error_message(), 'error' );
			$this->store_and_redirect( $network_wide );
		}

		protected function handle_success( $message, $ajax = false, $network_wide = false ) {
			if ( $ajax ) {
				wp_send_json_success( $message );
			}

			add_settings_error( 'wp_encrypt_action', 'action_success', $message, 'updated' );
			$this->store_and_redirect( $network_wide );
		}

		protected function store_and_redirect( $network_wide = false ) {
			$func = 'set_transient';
			$query_arg = 'settings-updated';
			if ( $network_wide ) {
				$func = 'set_site_transient';
				$query_arg = 'updated';
			}

			call_user_func( $func, 'settings_errors', get_settings_errors(), 30 );

			wp_redirect( add_query_arg( $query_arg, 'true', $this->get_url( $network_wide ) ) );
			exit;
		}

		protected function get_url( $network_wide = false ) {
			if ( $network_wide ) {
				return network_admin_url( 'settings.php?page=wp_encrypt' );
			}
			return admin_url( 'options-general.php?page=wp_encrypt' );
		}
	}
}
