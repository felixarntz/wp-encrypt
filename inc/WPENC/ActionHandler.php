<?php
/**
 * WPENC\ActionHandler class
 *
 * @package WPENC
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 1.0.0
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
	 * This class handles actions for regular, AJAX and Cron requests.
	 *
	 * @since 1.0.0
	 */
	class ActionHandler {
		/**
		 * The actions handled by this class.
		 *
		 * @since 1.0.0
		 * @access protected
		 * @var array
		 */
		protected $actions = array(
			'register_account',
			'generate_certificate',
			'revoke_certificate',
			'reset',
		);

		/**
		 * Adds the required action hooks.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function run() {
			foreach ( $this->actions as $action ) {
				add_action( 'admin_action_wpenc_' . $action, array( $this, 'request' ) );
				add_action( 'wp_ajax_wpenc_' . $action, array( $this, 'ajax_request' ) );
				add_action( 'wp_encrypt_' . $action, array( $this, 'cron_request' ) );
			}
		}

		/**
		 * Action callback that handles a regular request.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function request() {
			$this->handle_request();
		}

		/**
		 * Action callback that handles an AJAX request.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function ajax_request() {
			$this->handle_request( 'ajax' );
		}

		/**
		 * Action callback that handles a Cron request.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function cron_request() {
			$this->handle_request( 'cron' );
		}

		/**
		 * Registers the account with Let's Encrypt.
		 *
		 * This method is called through one of the action callbacks.
		 *
		 * @since 1.0.0
		 * @access protected
		 *
		 * @param array $data         The request data for the action.
		 * @param bool  $network_wide Whether this action should be performed network-wide.
		 * @return string|WP_Error The success message or an error object.
		 */
		protected function register_account( $data = array(), $network_wide = false ) {
			$filesystem_check = $this->maybe_request_filesystem_credentials( $network_wide );
			if ( false === $filesystem_check ) {
				return new WP_Error( 'invalid_filesystem_credentials', __( 'Invalid or missing filesystem credentials.', 'wp-encrypt' ), 'error' );
			}

			$manager = CertificateManager::get();
			$response = $manager->register_account();
			if ( is_wp_error( $response ) ) {
				$data = $response->get_error_data();
				if ( is_array( $data ) && isset( $data['location'] ) ) {
					$response = array_merge( Util::get_registration_info( 'account' ), $data );
					Util::set_registration_info( 'account', $response );

					return __( 'Account was already registered before.', 'wp-encrypt' );
				}

				return $response;
			}

			Util::set_registration_info( 'account', $response );

			return __( 'Account registered.', 'wp-encrypt' );
		}

		/**
		 * Generates a certificate with Let's Encrypt.
		 *
		 * This method is called through one of the action callbacks.
		 *
		 * In a Multinetwork setup, a certificate can either be generated for only the current
		 * network or for all networks.
		 *
		 * @since 1.0.0
		 * @access protected
		 *
		 * @param array $data         The request data for the action.
		 * @param bool  $network_wide Whether this action should be performed network-wide.
		 * @return string|WP_Error The success message or an error object.
		 */
		protected function generate_certificate( $data = array(), $network_wide = false ) {
			if ( ! Util::can_generate_certificate() ) {
				return new WP_Error( 'domain_cannot_sign', __( 'Domain cannot be signed. Either the account is not registered yet or the settings are not valid.', 'wp-encrypt' ) );
			}

			$filesystem_check = $this->maybe_request_filesystem_credentials( $network_wide );
			if ( false === $filesystem_check ) {
				return new WP_Error( 'invalid_filesystem_credentials', __( 'Invalid or missing filesystem credentials.', 'wp-encrypt' ), 'error' );
			}

			$global = Util::get_option( 'include_all_networks' );

			$domain = $network_wide ? Util::get_network_domain() : Util::get_site_domain();
			$addon_domains = $network_wide ? Util::get_network_addon_domains( null, $global ) : array();

			/**
			 * Filters the addon domains to create the certificate for.
			 *
			 * Using this filter basically allows to generate the certificate for any URLs,
			 * even those outside of the WordPress installation.
			 *
			 * @since 1.0.0
			 *
			 * @param array  $addon_domains The addon domains.
			 * @param string $domain        The root domain for the certificate.
			 * @param bool   $network_wide  Whether this certificate is created for an entire network.
			 */
			$addon_domains = apply_filters( 'wpenc_addon_domains', $addon_domains, $domain, $network_wide );

			$manager = CertificateManager::get();
			$response = $manager->generate_certificate( $domain, $addon_domains, array(
				'ST'	=> Util::get_option( 'country_name' ),
				'C'		=> Util::get_option( 'country_code' ),
				'O'		=> Util::get_option( 'organization' ),
			) );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			Util::set_registration_info( 'certificate', $response );

			if ( Util::get_option( 'autogenerate_certificate' ) ) {
				Util::schedule_autogenerate_event( current_time( 'timestamp' ), true );
			}

			return sprintf( __( 'Certificate generated for %s.', 'wp-encrypt' ), implode( ', ', $response['domains'] ) );
		}

		/**
		 * Revokes a certificate with Let's Encrypt.
		 *
		 * This method is called through one of the action callbacks.
		 *
		 * @since 1.0.0
		 * @access protected
		 *
		 * @param array $data         The request data for the action.
		 * @param bool  $network_wide Whether this action should be performed network-wide.
		 * @return string|WP_Error The success message or an error object.
		 */
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

			Util::delete_registration_info( 'certificate' );

			if ( Util::get_option( 'autogenerate_certificate' ) ) {
				Util::unschedule_autogenerate_event();
			}

			return __( 'Certificate revoked.', 'wp-encrypt' );
		}

		/**
		 * Deletes all certificates, keys and challenges. Basically resets the plugin.
		 *
		 * This method is called through one of the action callbacks.
		 *
		 * @since 1.0.0
		 * @access protected
		 *
		 * @param array $data         The request data for the action.
		 * @param bool  $network_wide Whether this action should be performed network-wide.
		 * @return string|WP_Error The success message or an error object.
		 */
		protected function reset( $data = array(), $network_wide = false ) {
			$filesystem_check = $this->maybe_request_filesystem_credentials( $network_wide );
			if ( false === $filesystem_check ) {
				return new WP_Error( 'invalid_filesystem_credentials', __( 'Invalid or missing filesystem credentials.', 'wp-encrypt' ), 'error' );
			}

			$manager = CertificateManager::get();
			$response = $manager->reset();
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			delete_site_option( 'wp_encrypt_registration' );

			return __( 'All certificates and keys have been successfully deleted.', 'wp-encrypt' );
		}

		/**
		 * Requests filesystem credentials if necessary.
		 *
		 * The key files and certificates need to be stored on disk.
		 * If the directories aren't writeable by WordPress, the user needs to manually enter
		 * access keys (FTP, SSH, ...).
		 *
		 * @since 1.0.0
		 * @access protected
		 *
		 * @param bool $network_wide Whether filesystem access is needed for a network-wide request.
		 * @return bool Whether the filesystem was successfully set up.
		 */
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

			$url = App::get_admin_action_url( $network_wide ? 'network' : 'site' );
			$extra_fields = array( 'action', '_wpnonce' );

			return CoreUtil::setup_filesystem( $url, $extra_fields );
		}

		/**
		 * General action callback for any kind of request.
		 *
		 * It makes the necessary security nonce checks and sends the response in the
		 * appropriate way.
		 *
		 * @since 1.0.0
		 * @access protected
		 *
		 * @param string $mode Either 'admin', 'ajax' or 'cron'. Default 'admin'.
		 */
		protected function handle_request( $mode = 'admin' ) {
			$ajax = false;
			$prefix = 'admin_action_wpenc_';
			$args = $_REQUEST;

			switch ( $mode ) {
				case 'cron':
					$prefix = 'wp_encrypt_';
					$args = array(
						'context'	=> is_multisite() ? 'network' : 'site',
						'_wpnonce'	=> wp_create_nonce( 'wp_encrypt_action' ),
					);
					break;
				case 'ajax':
					$prefix = 'wp_ajax_wpenc_';
					$ajax = true;
					break;
			}

			$network_wide = $this->is_network_request( $args );

			$action = str_replace( $prefix, '', current_action() );

			$valid = $this->check_request( $action, $mode, $args );
			if ( is_wp_error( $valid ) ) {
				if ( 'cron' === $mode ) {
					return;
				}
				$this->handle_error( $valid, $ajax, $network_wide );
			}

			$response = call_user_func( array( $this, $action ), $args, $network_wide );
			if ( is_wp_error( $response ) ) {
				if ( 'cron' === $mode ) {
					return;
				}
				$this->handle_error( $response, $ajax, $network_wide );
			}

			if ( 'cron' === $mode ) {
				return;
			}
			$this->handle_success( $response, $ajax, $network_wide );
		}

		/**
		 * Checks whether the current request is a network request.
		 *
		 * On WordPress < 4.6, it was not possible to make network-wide AJAX requests.
		 * Therefore a context argument was used there.
		 *
		 * @since 1.0.0
		 * @access protected
		 *
		 * @param array $args Request data.
		 * @return bool Whether this is a network request.
		 */
		protected function is_network_request( $args ) {
			return is_network_admin() || isset( $args['context'] ) && 'network' === $args['context'];
		}

		/**
		 * Checks whether this is a valid request.
		 *
		 * It verifies the security nonce, capabilities and action.
		 *
		 * @since 1.0.0
		 * @access protected
		 *
		 * @param string $action The action to perform.
		 * @param string $mode   What kind of request this is.
		 * @param array  $args   Request data.
		 * @return bool|WP_Error Either true or an error object.
		 */
		protected function check_request( $action, $mode = 'admin', $args = array() ) {
			if ( ! isset( $args['_wpnonce'] ) ) {
				return new WP_Error( 'nonce_missing', __( 'Missing nonce.', 'wp-encrypt' ) );
			}

			$status = 'ajax' === $mode ? check_ajax_referer( 'wp_encrypt_ajax', '_wpnonce', false ) : wp_verify_nonce( $args['_wpnonce'], 'wp_encrypt_action' );
			if ( ! $status ) {
				return new WP_Error( 'nonce_invalid', __( 'Invalid nonce.', 'wp-encrypt' ) );
			}

			if ( 'cron' !== $mode && ! current_user_can( 'manage_certificates' ) ) {
				return new WP_Error( 'capabilities_lacking', __( 'Lacking required capabilities.', 'wp-encrypt' ) );
			}

			if ( ! method_exists( $this, $action ) ) {
				return new WP_Error( 'action_invalid', __( 'Invalid action.', 'wp-encrypt' ) );
			}

			return true;
		}

		/**
		 * Handles an error for any action.
		 *
		 * On regular requests, this method adds a settings error and redirects.
		 * For AJAX requests, it sends the error JSON data.
		 *
		 * This method is not used for Cron requests.
		 *
		 * @since 1.0.0
		 * @access protected
		 *
		 * @param WP_Error $error        The current error object.
		 * @param bool     $ajax         Whether this is an AJAX request.
		 * @param bool     $network_wide Whether this is a network wide request.
		 */
		protected function handle_error( $error, $ajax = false, $network_wide = false ) {
			if ( $ajax ) {
				wp_send_json_error( $error->get_error_message() );
			}

			add_settings_error( 'wp_encrypt_action', $error->get_error_code(), $error->get_error_message(), 'error' );
			$this->store_and_redirect( $network_wide );
		}

		/**
		 * Handles successful execution for any action.
		 *
		 * On regular requests, this method adds a settings success message and redirects.
		 * For AJAX requests, it sends the success JSON data.
		 *
		 * This method is not used for Cron requests.
		 *
		 * @since 1.0.0
		 * @access protected
		 *
		 * @param string $error        The current success message.
		 * @param bool   $ajax         Whether this is an AJAX request.
		 * @param bool   $network_wide Whether this is a network wide request.
		 */
		protected function handle_success( $message, $ajax = false, $network_wide = false ) {
			if ( $ajax ) {
				wp_send_json_success( $message );
			}

			add_settings_error( 'wp_encrypt_action', 'action_success', $message, 'updated' );
			$this->store_and_redirect( $network_wide );
		}

		/**
		 * Stores the current settings errors in a transient and redirects.
		 *
		 * This method is used at the end of regular action requests so that the result
		 * of the action is displayed on the actual settings page.
		 *
		 * @since 1.0.0
		 * @access protected
		 *
		 * @param boolean $network_wide Whether this is a network wide request.
		 */
		protected function store_and_redirect( $network_wide = false ) {
			$func = 'set_transient';
			$query_arg = 'settings-updated';
			if ( $network_wide ) {
				$func = 'set_site_transient';
				$query_arg = 'updated';
			}

			call_user_func( $func, 'settings_errors', get_settings_errors(), 30 );

			wp_redirect( add_query_arg( $query_arg, 'true', App::get_admin_url( $network_wide ? 'network' : 'site' ) ) );
			exit;
		}
	}
}
