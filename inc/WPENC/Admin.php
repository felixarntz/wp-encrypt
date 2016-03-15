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

if ( ! class_exists( 'WPENC\Admin' ) ) {
	/**
	 * This class performs the necessary actions in the WordPress admin to generate the settings page.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	class Admin {
		const PAGE_SLUG = 'wp_encrypt';

		protected $context = 'site';

		/**
		 * Class constructor.
		 *
		 * @since 0.5.0
		 */
		protected function __construct() {
		}

		/**
		 * Initialization method.
		 *
		 * @since 0.5.0
		 */
		public function run() {
			$menu_hook = 'admin_menu';
			$option_hook = 'pre_update_option_wp_encrypt_settings';
			if ( 'network' === $this->context ) {
				$menu_hook = 'network_admin_menu';
				$option_hook = 'pre_update_site_option_wp_encrypt_settings';
			}

			add_action( 'admin_init', array( $this, 'init_settings' ) );
			add_action( $menu_hook, array( $this, 'init_menu' ) );

			add_action( 'admin_action_wpenc_register_account', array( $this, 'action_register_account' ) );
			add_action( 'admin_action_wpenc_generate_certificate', array( $this, 'action_generate_certificate' ) );
			add_action( 'admin_action_wpenc_revoke_certificate', array( $this, 'action_revoke_certificate' ) );

			add_action( 'wp_ajax_wpenc_update_settings', array( $this, 'ajax_update_settings' ) );
			add_action( 'wp_ajax_wpenc_register_account', array( $this, 'ajax_register_account' ) );
			add_action( 'wp_ajax_wpenc_generate_certificate', array( $this, 'ajax_generate_certificate' ) );
			add_action( 'wp_ajax_wpenc_revoke_certificate', array( $this, 'ajax_revoke_certificate' ) );

			add_filter( $option_hook, array( $this, 'check_valid' ) );
		}

		public function init_settings() {
			register_setting( 'wp_encrypt_settings', 'wp_encrypt_settings', array( $this, 'validate_settings' ) );
			add_settings_section( 'wp_encrypt_settings', __( 'Settings', 'wp-encrypt' ), array( $this, 'render_settings_description' ), self::PAGE_SLUG );
			add_settings_field( 'organization', __( 'Organization Name', 'wp-encrypt' ), array( $this, 'render_settings_field' ), self::PAGE_SLUG, 'wp_encrypt_settings', array( 'id' => 'organization' ) );
			add_settings_field( 'country_name', __( 'Country Name', 'wp-encrypt' ), array( $this, 'render_settings_field' ), self::PAGE_SLUG, 'wp_encrypt_settings', array( 'id' => 'country_name' ) );
			add_settings_field( 'country_code', __( 'Country Code', 'wp-encrypt' ), array( $this, 'render_settings_field' ), self::PAGE_SLUG, 'wp_encrypt_settings', array( 'id' => 'country_code' ) );
		}

		public function init_menu() {
			add_options_page( __( 'WP Encrypt', 'wp-encrypt' ), __( 'WP Encrypt', 'wp-encrypt' ), 'manage_options', self::PAGE_SLUG, array( $this, 'render_page' ) );
		}

		public function render_page() {
			if ( CoreUtil::needs_filesystem_credentials() ) {
				?>
				<div class="notice notice-warning">
					<p><?php printf( __( 'The directories %1$s and %2$s that WP Encrypt needs access to are not automatically writable by the site. Unless you change this, it is not possible to auto-renew certificates.', 'wp-encrypt' ), '<code>' . CoreUtil::get_letsencrypt_certificates_dir_path() . '</code>', '<code>' . CoreUtil::get_letsencrypt_challenges_dir_path() . '' ); ?></p>
					<p><?php _e( 'Note that you can still manually renew certificates by providing valid filesystem credentials each time.', 'wp-encrypt' ); ?></p>
				</div>
				<?php
			}

			$base_url = $this->get_url();
			$settings_action = 'options.php';
			if ( 'network' === $this->context ) {
				$settings_action = 'settings.php';
			}

			//TODO: make network compatible
			?>
			<div class="wrap">
				<h1><?php _e( 'WP Encrypt', 'wp-encrypt' ); ?></h1>

				<form method="post" action="<?php echo $settings_action; ?>">
					<?php settings_fields( 'wp_encrypt_settings' ); ?>
					<?php do_settings_sections( self::PAGE_SLUG ); ?>
					<?php submit_button(); ?>
				</form>

				<?php if ( Util::get_option( 'valid' ) ) : ?>
					<h2><?php _e( 'Let&rsquo;s Encrypt Account', 'wp-encrypt' ); ?></h2>

					<form method="post" action="<?php echo $base_url; ?>">
						<?php $this->action_fields( 'wpenc_register_account' ); ?>
						<?php submit_button( __( 'Register Account', 'wp-encrypt' ), 'secondary' ); ?>
					</form>

					<?php if ( $this->can_generate_certificate() ) : ?>
						<h2><?php _e( 'Let&rsquo;s Encrypt Certificate', 'wp-encrypt' ); ?></h2>

						<form method="post" action="<?php echo $base_url; ?>">
							<?php $this->action_fields( 'wpenc_generate_certificate' ); ?>
							<?php submit_button( __( 'Generate Certificate', 'wp-encrypt' ), 'secondary' ); ?>
						</form>
						<form method="post" action="<?php echo $base_url; ?>">
							<?php $this->action_fields( 'wpenc_revoke_certificate' ); ?>
							<?php submit_button( __( 'Revoke Certificate', 'wp-encrypt' ), 'delete' ); ?>
						</form>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<?php

			// for AJAX
			wp_print_request_filesystem_credentials_modal();
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

			echo '<input type="text" id="' . $args['id'] . '" name="wp_encrypt_settings[' . $args['id'] . ']" value="' . $value . '"' . $more_args . ' />';
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
				add_settings_error( 'wp_encrypt_action', $response->get_error_code(), $response->get_error_message(), 'error' );
			} else {
				$response = $this->register_account();
				if ( is_wp_error( $response ) ) {
					add_settings_error( 'wp_encrypt_action', $response->get_error_code(), $response->get_error_message(), 'error' );
				} else {
					add_settings_error( 'wp_encrypt_action', 'account_registered', __( 'Account registered.', 'wp-encrypt' ), 'updated' );
				}
			}

			$this->set_transient( 'settings_errors', get_settings_errors(), 30 );

			wp_redirect( add_query_arg( 'settings-updated', 'true', $this->get_url() ) );
			exit;
		}

		public function action_generate_certificate() {
			$response = $this->check_action_request();
			if ( is_wp_error( $response ) ) {
				add_settings_error( 'wp_encrypt_action', $response->get_error_code(), $response->get_error_message(), 'error' );
			} else {
				$response = $this->generate_certificate();
				if ( is_wp_error( $response ) ) {
					add_settings_error( 'wp_encrypt_action', $response->get_error_code(), $response->get_error_message(), 'error' );
				} else {
					add_settings_error( 'wp_encrypt_action', 'account_registered', __( 'Domain signed.', 'wp-encrypt' ), 'updated' );
				}
			}

			$this->set_transient( 'settings_errors', get_settings_errors(), 30 );

			wp_redirect( add_query_arg( 'settings-updated', 'true', $this->get_url() ) );
			exit;
		}

		public function action_revoke_certificate() {
			$response = $this->check_action_request();
			if ( is_wp_error( $response ) ) {
				add_settings_error( 'wp_encrypt_action', $response->get_error_code(), $response->get_error_message(), 'error' );
			} else {
				$response = $this->revoke_certificate();
				if ( is_wp_error( $response ) ) {
					add_settings_error( 'wp_encrypt_action', $response->get_error_code(), $response->get_error_message(), 'error' );
				} else {
					add_settings_error( 'wp_encrypt_action', 'account_registered', __( 'Domain unsigned.', 'wp-encrypt' ), 'updated' );
				}
			}

			$this->set_transient( 'settings_errors', get_settings_errors(), 30 );

			wp_redirect( add_query_arg( 'settings-updated', 'true', $this->get_url() ) );
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
				if ( ! isset( $options[ $required_field ] ) || empty( $options[ $required_field ] ) ) {
					$valid = false;
					break;
				}
			}
			$options['valid'] = $valid;
			return $options;
		}

		protected function register_account() {
			$credentials = $this->maybe_request_filesystem_credentials();
			if ( false === $credentials ) {
				return new WP_Error( 'invalid_filesystem_credentials', __( 'Invalid or missing filesystem credentials.', 'wp-encrypt' ), 'error' );
			}

			$manager = CertificateManager::get();
			$response = $manager->register_account();
			if ( ! is_wp_error( $response ) ) {
				Util::set_registration_info( 'account', $response );
			}
			return $response;
		}

		protected function generate_certificate() {
			if ( ! $this->can_generate_certificate() ) {
				return new WP_Error( 'domain_cannot_sign', __( 'Domain cannot be signed. Either the account is not registered yet or the settings are not valid.', 'wp-encrypt' ) );
			}

			$credentials = $this->maybe_request_filesystem_credentials();
			if ( false === $credentials ) {
				return new WP_Error( 'invalid_filesystem_credentials', __( 'Invalid or missing filesystem credentials.', 'wp-encrypt' ), 'error' );
			}

			$domain = 'network' === $this->context ? Util::get_network_domain() : Util::get_site_domain();
			$addon_domains = 'network' === $this->context ? Util::get_network_addon_domains() : array();

			$manager = CertificateManager::get();
			$response = $manager->generate_certificate( $domain, $addon_domains, array(
				'ST'	=> Util::get_option( 'country_name' ),
				'C'		=> Util::get_option( 'country_code' ),
				'O'		=> Util::get_option( 'organization' ),
			) );
			if ( ! is_wp_error( $response ) ) {
				Util::set_registration_info( get_current_blog_id(), array() );
			}
			return $response;
		}

		protected function revoke_certificate() {
			$credentials = $this->maybe_request_filesystem_credentials();
			if ( false === $credentials ) {
				return new WP_Error( 'invalid_filesystem_credentials', __( 'Invalid or missing filesystem credentials.', 'wp-encrypt' ), 'error' );
			}

			$domain = 'network' === $this->context ? Util::get_network_domain() : Util::get_site_domain();

			$manager = CertificateManager::get();
			$response = $manager->revoke_certificate( $domain );
			if ( ! is_wp_error( $response ) ) {
				Util::delete_registration_info( get_current_blog_id() );
			}
			return $response;
		}

		protected function can_generate_certificate() {
			return $this->get_account_registered() && Util::get_option( 'valid' );
		}

		protected function get_account_registered() {
			return Util::get_registration_info( 'account' );
		}

		protected function get_certificate_generated() {
			return Util::get_registration_info( get_current_blog_id() );
		}

		protected function check_action_request() {
			if ( ! isset( $_REQUEST['nonce'] ) ) {
				return new WP_Error( 'nonce_missing', __( 'Missing nonce.', 'wp-encrypt' ) );
			}

			if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wp_encrypt_action' ) ) {
				return new WP_Error( 'nonce_invalid', __( 'Invalid nonce.', 'wp-encrypt' ) );
			}

			if ( 'network' === $this->context && ! current_user_can( 'manage_network_options' ) || 'site' === $this->context && ! current_user_can( 'manage_options' ) ) {
				return new WP_Error( 'capabilities_missing', __( 'Missing required capabilities.', 'wp-encrypt' ) );
			}

			return true;
		}

		protected function check_ajax_request() {
			if ( ! isset( $_REQUEST['nonce'] ) ) {
				wp_send_json_error( __( 'Missing nonce.', 'wp-encrypt' ) );
			}

			if ( ! check_ajax_referer( 'wp_encrypt_ajax', 'nonce', false ) ) {
				wp_send_json_error( __( 'Invalid nonce.', 'wp-encrypt' ) );
			}

			if ( 'network' === $this->context && ! current_user_can( 'manage_network_options' ) || 'site' === $this->context && ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'Missing required capabilities.', 'wp-encrypt' ) );
			}
		}

		protected function maybe_request_filesystem_credentials() {
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

				return $credentials;
			}

			$url = $this->get_url();
			$extra_fields = array( 'action', 'nonce' );

			return CoreUtil::maybe_request_filesystem_credentials( $url, $extra_fields );
		}

		protected function action_fields( $action ) {
			echo '<input type="hidden" name="action" value="' . $action . '" />';
			wp_nonce_field( 'wp_encrypt_action', 'nonce' );
		}

		protected function set_transient( $name, $value, $expiration = 0 ) {
			return set_transient( $name, $value, $expiration );
		}

		protected function get_url() {
			return admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		}
	}
}
