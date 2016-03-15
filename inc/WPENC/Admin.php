<?php
/**
 * @package WPENC
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPENC;

use WPENC\Core\CertificateManager;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPENC\Admin' ) ) {
	/**
	 * This class performs the necessary actions in the WordPress admin to generate the settings page.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	final class Admin {

		/**
		 * @since 0.5.0
		 * @var WPENC\Admin|null Holds the instance of this class.
		 */
		private static $instance = null;

		/**
		 * Gets the instance of this class. If it does not exist, it will be created.
		 *
		 * @since 0.5.0
		 * @return WPENC\Admin
		 */
		public static function instance() {
			if ( null == self::$instance ) {
				self::$instance = new self;
			}

			return self::$instance;
		}

		private $credentials = array();

		/**
		 * Class constructor.
		 *
		 * @since 0.5.0
		 */
		private function __construct() {
		}

		/**
		 * Initialization method.
		 *
		 * @since 0.5.0
		 */
		public function run() {
			add_action( 'admin_init', array( $this, 'init_settings' ) );
			add_action( 'admin_menu', array( $this, 'init_menu' ) );

			add_action( 'admin_action_wpenc_register_account', array( $this, 'action_register_account' ) );
			add_action( 'admin_action_wpenc_generate_certificate', array( $this, 'action_generate_certificate' ) );
			add_action( 'admin_action_wpenc_revoke_certificate', array( $this, 'action_revoke_certificate' ) );

			add_action( 'wp_ajax_wpenc_update_settings', array( $this, 'ajax_update_settings' ) );
			add_action( 'wp_ajax_wpenc_register_account', array( $this, 'ajax_register_account' ) );
			add_action( 'wp_ajax_wpenc_generate_certificate', array( $this, 'ajax_generate_certificate' ) );
			add_action( 'wp_ajax_wpenc_revoke_certificate', array( $this, 'ajax_revoke_certificate' ) );

			add_filter( 'pre_update_option_wp_encrypt_settings', array( $this, 'check_valid' ) );
		}

		public function init_settings() {
			register_setting( 'wp_encrypt_settings', 'wp_encrypt_settings', array( $this, 'validate_settings' ) );
			add_settings_section( 'wp_encrypt_settings', __( 'Settings', 'wp-encrypt' ), array( $this, 'render_settings_description' ), 'wp_encrypt' );
			add_settings_field( 'organization', __( 'Organization Name', 'wp-encrypt' ), array( $this, 'render_settings_field' ), 'wp_encrypt', 'wp_encrypt_settings', array( 'id' => 'organization' ) );
			add_settings_field( 'country_name', __( 'Country Name', 'wp-encrypt' ), array( $this, 'render_settings_field' ), 'wp_encrypt', 'wp_encrypt_settings', array( 'id' => 'country_name' ) );
			add_settings_field( 'country_code', __( 'Country Code', 'wp-encrypt' ), array( $this, 'render_settings_field' ), 'wp_encrypt', 'wp_encrypt_settings', array( 'id' => 'country_code' ) );
		}

		public function init_menu() {
			$page_hook = add_options_page( __( 'WP Encrypt', 'wp-encrypt' ), __( 'WP Encrypt', 'wp-encrypt' ), 'manage_options', 'wp_encrypt', array( $this, 'render_page' ) );

			add_action( 'load-' . $page_hook, array( $this, 'check_filesystem' ) );
		}

		public function check_filesystem() {
			$url = admin_url( 'admin.php?page=wp_encrypt' );

			$this->credentials = CertificateManager::get()->maybe_request_filesystem_credentials( $url );
		}

		public function post_credentials() {
			if ( ! is_array( $this->credentials ) ) {
				return;
			}

			foreach ( $this->credentials as $key => $value ) {
				?>
				<input type="hidden" id="fs-credentials-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" />
				<?php
			}
		}

		public function render_page() {
			//TODO
		}

		public function render_settings_description() {
			echo '<p class="description">' . __( 'The following settings are required to generate a certificate for this site.', 'wp-encrypt' ) . '</p>';
		}

		public function render_settings_field( $args = array() ) {
			$value = Util::get_option( $args['id'] );

			$more_args = '';
			if ( 'country_code' === $args['id'] ) {
				$more_args .= ' maxlength="2"';
			}

			echo '<input type="text" id="' . $args['id'] . '" name="' . $args['id'] . '" value="' . $value . '"' . $more_args . ' />';
			switch ( $args['id'] ) {
				case 'organization':
					$description = __( 'The name of the organization behind this site.', 'wp-encrypt' );
					break;
				case 'country_name':
					$description = __( 'The name of the country the organization resides in.', 'wp-encrypt' );
					break;
				case 'country_code':
					$description = __( 'The two-letter country code for the country specified above.', 'wp-encrypt' );
					break;
				default:
			}
			if ( isset( $description ) ) {
				echo ' <span class="description">' . $description . '</span>';
			}
		}

		public function validate_settings( $options = array() ) {
			$options = array_map( 'strip_tags', array_map( 'trim', $options ) );

			if ( isset( $options['country_code'] ) ) {
				$options['country_code'] = strtoupper( substr( $options['country_code'], 0, 2 ) );
			}
			return $options;
		}

		public function action_register_account() {
			$response = $this->check_action_request();
			if ( is_wp_error( $response ) ) {
				add_settings_error( 'wp_encrypt_settings', $response->get_error_code(), $response->get_error_message(), 'error' );
			} else {
				$response = $this->register_account();
				if ( is_wp_error( $response ) ) {
					add_settings_error( 'wp_encrypt_settings', $response->get_error_code(), $response->get_error_message(), 'error' );
				} else {
					add_settings_error( 'wp_encrypt_settings', 'account_registered', __( 'Account registered.', 'wp-encrypt' ), 'updated' );
				}
			}

			wp_redirect( remove_query_arg( 'action' ) );
			exit;
		}

		public function action_generate_certificate() {
			$response = $this->check_action_request();
			if ( is_wp_error( $response ) ) {
				add_settings_error( 'wp_encrypt_settings', $response->get_error_code(), $response->get_error_message(), 'error' );
			} else {
				$response = $this->generate_certificate();
				if ( is_wp_error( $response ) ) {
					add_settings_error( 'wp_encrypt_settings', $response->get_error_code(), $response->get_error_message(), 'error' );
				} else {
					add_settings_error( 'wp_encrypt_settings', 'account_registered', __( 'Domain signed.', 'wp-encrypt' ), 'updated' );
				}
			}

			wp_redirect( remove_query_arg( 'action' ) );
			exit;
		}

		public function action_revoke_certificate() {
			$response = $this->check_action_request();
			if ( is_wp_error( $response ) ) {
				add_settings_error( 'wp_encrypt_settings', $response->get_error_code(), $response->get_error_message(), 'error' );
			} else {
				$response = $this->revoke_certificate();
				if ( is_wp_error( $response ) ) {
					add_settings_error( 'wp_encrypt_settings', $response->get_error_code(), $response->get_error_message(), 'error' );
				} else {
					add_settings_error( 'wp_encrypt_settings', 'account_registered', __( 'Domain unsigned.', 'wp-encrypt' ), 'updated' );
				}
			}

			wp_redirect( remove_query_arg( 'action' ) );
			exit;
		}

		public function ajax_update_settings() {
			$this->check_ajax_request();

			if ( ! isset( $_REQUEST['settings'] ) ) {
				wp_send_json_error( __( 'No settings provided.', 'wp-encrypt' ) );
			}

			$options = $this->validate_settings( $_REQUEST['settings'] );

			Util::update_option( $options );

			wp_send_json_success( __( 'Settings updated.', 'wp-encrypt' ) );
		}

		public function ajax_register_account() {
			$this->check_ajax_request();

			$response = $this->register_account();
			if ( is_wp_error( $response ) ) {
				wp_send_json_error( $response->get_error_message() );
			}

			$date = mysql2date( get_option( 'date_format' ), $this->get_account_registered(), true );

			wp_send_json_success( sprintf( __( 'The account has been registered on %s.', 'wp-encrypt' ), $date ) );
		}

		public function ajax_generate_certificate() {
			$this->check_ajax_request();

			$response = $this->generate_certificate();
			if ( is_wp_error( $response ) ) {
				wp_send_json_error( $response->get_error_message() );
			}

			$date = mysql2date( get_option( 'date_format' ), $this->get_certificate_generated(), true );

			wp_send_json_success( sprintf( __( 'The domain has been last signed on %s.', 'wp-encrypt' ), $date ) );
		}

		public function ajax_revoke_certificate() {
			$this->check_ajax_request();

			$response = $this->revoke_certificate();
			if ( is_wp_error( $response ) ) {
				wp_send_json_error( $response->get_error_message() );
			}

			wp_send_json_success( __( 'The domain has been unsigned.', 'wp-encrypt' ) );
		}

		public function check_valid( $options ) {
			$required_fields = array( 'country_code', 'country_name', 'organization' );
			$valid = true;
			foreach ( $required_fields as $required_field ) {
				if ( ! isset( $options[ $required_field ] ) || empty( $options[ $required_field ] ) ) {
					$valid = false;
					break;
				}
			}
			$options['valid'] = $valid;
			return $options;
		}

		private function register_account() {
			$manager = CertificateManager::get();
			$response = $manager->register_account();
			if ( ! is_wp_error( $response ) ) {
				Util::set_registration_info( 'account', current_time( 'mysql' ) );
			}
			return $response;
		}

		private function generate_certificate() {
			if ( ! $this->can_generate_certificate() ) {
				return new WP_Error( 'domain_cannot_sign', __( 'Domain cannot be signed. Either the account is not registered yet or the settings are not valid.', 'wp-encrypt' ) );
			}
			$manager = CertificateManager::get();
			$response = $manager->generate_certificate( Util::get_site_domain(), array(), array(
				'ST'	=> Util::get_option( 'country_name' ),
				'C'		=> Util::get_option( 'country_code' ),
				'O'		=> Util::get_option( 'organization' ),
			) );
			if ( ! is_wp_error( $response ) ) {
				Util::set_registration_info( get_current_blog_id(), current_time( 'mysql' ) );
			}
			return $response;
		}

		private function revoke_certificate() {
			$manager = CertificateManager::get();
			$response = $manager->revoke_certificate( Util::get_site_domain() );
			if ( ! is_wp_error( $response ) ) {
				Util::set_registration_info( get_current_blog_id(), '' );
			}
			return $response;
		}

		private function can_generate_certificate() {
			return $this->get_account_registered() && Util::get_option( 'valid' );
		}

		private function get_account_registered() {
			return Util::get_registration_info( 'account' );
		}

		private function get_certificate_generated() {
			return Util::get_registration_info( get_current_blog_id() );
		}

		private function check_action_request() {
			if ( ! isset( $_REQUEST['nonce'] ) ) {
				return new WP_Error( 'nonce_missing', __( 'Missing nonce.', 'wp-encrypt' ) );
			}

			if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wp_encrypt_action' ) ) {
				return new WP_Error( 'nonce_invalid', __( 'Invalid nonce.', 'wp-encrypt' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				return new WP_Error( 'capabilities_missing', __( 'Missing required capabilities.', 'wp-encrypt' ) );
			}

			return true;
		}

		private function check_ajax_request() {
			if ( ! isset( $_REQUEST['nonce'] ) ) {
				wp_send_json_error( __( 'Missing nonce.', 'wp-encrypt' ) );
			}

			if ( ! check_ajax_referer( 'wp_encrypt_ajax', 'nonce', false ) ) {
				wp_send_json_error( __( 'Invalid nonce.', 'wp-encrypt' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'Missing required capabilities.', 'wp-encrypt' ) );
			}
		}
	}
}
