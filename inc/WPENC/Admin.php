<?php
/**
 * @package WPENC
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPENC;

use WPENC\Core\Util as CoreUtil;
use WPENC\Core\Certificate as Certificate;
use WPENC\Core\KeyPair as KeyPair;

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

		public function __construct( $context = 'site' ) {
			$this->context = $context;
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
			$parent = 'options-general.php';
			if ( 'network' === $this->context ) {
				$parent = 'settings.php';
			}
			add_submenu_page( $parent, __( 'WP Encrypt', 'wp-encrypt' ), __( 'WP Encrypt', 'wp-encrypt' ), 'manage_certificates', self::PAGE_SLUG, array( $this, 'render_page' ) );
		}

		public function render_page() {
			if ( CoreUtil::needs_filesystem_credentials() ) {
				?>
				<div class="notice notice-warning">
					<p><?php printf( __( 'The directories %1$s and %2$s that WP Encrypt needs access to are not automatically writable by the site. Unless you change this, it is not possible to auto-renew certificates.', 'wp-encrypt' ), '<code>' . CoreUtil::get_letsencrypt_certificates_dir_path() . '</code>', '<code>' . CoreUtil::get_letsencrypt_challenges_dir_path() . '</code>' ); ?></p>
					<p><?php _e( 'Note that you can still manually renew certificates by providing valid filesystem credentials each time.', 'wp-encrypt' ); ?></p>
				</div>
				<?php
			}

			$registration_info = Util::get_registration_info();

			$account_registration_timestamp = false;
			$certificate_generation_timestamp = false;
			$site_domains = array();

			if ( isset( $registration_info['account'] ) ) {
				$account_registration_timestamp = strtotime( $registration_info['account']['_wp_time'] );
				unset( $registration_info['account'] );
			}

			if ( 0 < count( $registration_info ) ) {
				$first_key = key( $registration_info );
				$certificate_generation_timestamp = strtotime( $registration_info[ $first_key ]['_wp_time'] );
				if ( isset( $registration_info[ $first_key ]['domains'] ) ) {
					$site_domains = $registration_info[ $first_key ]['domains'];
				}
			}

			$has_certificate = 0 < count( $site_domains );

			$form_action = 'network' === $this->context ? 'settings.php' : 'options.php';

			$primary = 'save';
			if ( Util::get_option( 'valid' ) ) {
				$primary = 'register';
				if ( Util::can_generate_certificate() ) {
					$primary = 'generate';
				}
			}

			?>
			<style type="text/css">
				.wp-encrypt-form {
					margin-bottom: 40px;
				}

				.delete-button {
					margin-left: 6px;
					line-height: 28px;
					text-decoration: none;
				}

				.delete-button:hover {
					color: #f00;
					text-decoration: none;
					border: none;
				}
			</style>
			<div class="wrap">
				<h1><?php _e( 'WP Encrypt', 'wp-encrypt' ); ?></h1>

				<form class="wp-encrypt-form" method="post" action="<?php echo $form_action; ?>">
					<?php settings_fields( 'wp_encrypt_settings' ); ?>
					<?php do_settings_sections( self::PAGE_SLUG ); ?>
					<?php submit_button( '', ( 'save' === $primary ? 'primary' : 'secondary' ) . ' large', 'submit', false ); ?>
				</form>

				<?php if ( Util::get_option( 'valid' ) ) : ?>
					<h2><?php _e( 'Let&rsquo;s Encrypt Account', 'wp-encrypt' ); ?></h2>

					<form class="wp-encrypt-form" method="post" action="<?php echo $form_action; ?>">
						<p class="description">
							<?php _e( 'By clicking on this button, you will register an account for the above organization with Let&apos;s Encrypt.', 'wp-encrypt' ); ?>
							<?php if ( $account_registration_timestamp ) : ?>
								<br />
								<?php printf( __( 'Your account was registered on %1$s at %2$s.', 'wp-encrypt' ), date_i18n( get_option( 'date_format' ), $account_registration_timestamp ), date_i18n( get_option( 'time_format' ), $account_registration_timestamp ) ); ?>
							<?php endif; ?>
						</p>
						<?php $this->action_post_fields( 'wpenc_register_account' ); ?>
						<?php submit_button( __( 'Register Account', 'wp-encrypt' ), ( 'register' === $primary ? 'primary' : 'secondary' ), 'submit', false, array( 'id' => 'register-account-button' ) ); ?>
					</form>

					<?php if ( Util::can_generate_certificate() ) : ?>
						<h2><?php _e( 'Let&rsquo;s Encrypt Certificate', 'wp-encrypt' ); ?></h2>

						<form class="wp-encrypt-form" method="post" action="<?php echo $form_action; ?>">
							<p class="description">
								<?php _e( 'Here you can manage the actual certificate.', 'wp-encrypt' ); ?>
								<?php if ( 'network' === $this->context ) : ?>
									<?php _e( 'The certificate will be valid for all the sites in your network by default.', 'wp-encrypt' ); ?>
								<?php endif; ?>
								<?php if ( $certificate_generation_timestamp ) : ?>
									<br />
									<?php if ( 'network' === $this->context && 0 < count( $site_domains ) ) : ?>
										<?php printf( __( 'Your certificate was last generated on %1$s at %2$s for the following domains: %3$s', 'wp-encrypt' ), date_i18n( get_option( 'date_format' ), $certificate_generation_timestamp ), date_i18n( get_option( 'time_format' ), $certificate_generation_timestamp ), implode( ', ', $site_domains ) ); ?>
									<?php else : ?>
										<?php printf( __( 'Your certificate was last generated on %1$s at %2$s.', 'wp-encrypt' ), date_i18n( get_option( 'date_format' ), $certificate_generation_timestamp ), date_i18n( get_option( 'time_format' ), $certificate_generation_timestamp ) ); ?>
									<?php endif; ?>
								<?php endif; ?>
							</p>
							<?php $this->action_post_fields( 'wpenc_generate_certificate' ); ?>
							<?php if ( App::is_multinetwork() ) : ?>
								<p>
									<input type="checkbox" id="include_all_networks" name="include_all_networks" value="1" />
									<span><?php _e( 'Generate a global certificate for all networks? This will ensure that all sites in your entire WordPress setup are covered.', 'wp-encrypt' ); ?></span>
								</p>
							<?php endif; ?>
							<?php submit_button( __( 'Generate Certificate', 'wp-encrypt' ), ( 'generate' === $primary ? 'primary' : 'secondary' ), 'submit', false, array( 'id' => 'generate-certificate-button' ) ); ?>
							<?php if ( $has_certificate ) : ?>
								<a id="revoke-certificate-button" class="delete-button" href="<?php echo $this->action_get_url( 'wpenc_revoke_certificate' ); ?>"><?php _e( 'Revoke Certificate', 'wp-encrypt' ); ?></a>
							<?php endif; ?>
						</form>

						<?php $this->render_instructions(); ?>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<?php

			// for AJAX
			/*if ( CoreUtil::needs_filesystem_credentials() ) {
				wp_print_request_filesystem_credentials_modal();
			}*/
		}

		public function render_settings_description() {
			echo '<p class="description">' . __( 'The following settings are required to generate a certificate.', 'wp-encrypt' ) . '</p>';
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

		protected function action_post_fields( $action ) {
			echo '<input type="hidden" name="action" value="' . $action . '" />';
			wp_nonce_field( 'wp_encrypt_action' );
		}

		protected function action_get_url( $action ) {
			$url = ( 'network' === $this->context ) ? network_admin_url( 'settings.php' ) : admin_url( 'options-general.php' );
			$url = add_query_arg( 'action', $action, $url );
			$url = wp_nonce_url( $url, 'wp_encrypt_action' );
			return $url;
		}

		protected function render_instructions() {
			global $is_apache, $is_nginx;

			$site_domain = 'network' === $this->context ? Util::get_network_domain() : Util::get_site_domain();

			$site_dir = CoreUtil::detect_base( 'path' );

			$certificate_dirs = array(
				'base'	=> CoreUtil::get_letsencrypt_certificates_dir_path() . '/' . $site_domain,
			);
			$certificate_dirs['cert'] = $certificate_dirs['base'] . '/' . Certificate::CERT_NAME;
			$certificate_dirs['chain'] = $certificate_dirs['base'] . '/' . Certificate::CHAIN_NAME;
			$certificate_dirs['fullchain'] = $certificate_dirs['base'] . '/' . Certificate::FULLCHAIN_NAME;
			$certificate_dirs['key'] = $certificate_dirs['base'] . '/' . KeyPair::PRIVATE_NAME;

			?>
			<h3><?php _e( 'Setup', 'wp-encrypt' ); ?></h3>

			<p><?php _e( 'In order to use the certificate you acquired, you need to configure SSL and set the paths to the certificate and the private key in your server configuration.', 'wp-encrypt' ); ?></p>

			<?php if ( $is_apache ) : ?>
				<?php $this->render_apache_instructions( $site_domain, $site_dir, $certificate_dirs ); ?>
			<?php elseif ( $is_nginx ) : ?>
				<?php $this->render_nginx_instructions( $site_domain, $site_dir, $certificate_dirs ); ?>
			<?php else : ?>
				<h4><?php _e( 'Certificate & Key locations', 'wp-encrypt' ); ?></h4>
				<ul>
					<li><?php printf( __( 'Certificate: %s', 'wp-encrypt' ), '<code>' . $certificate_dirs['cert'] . '</code>' ); ?></li>
					<li><?php printf( __( 'Certificate Chain: %s', 'wp-encrypt' ), '<code>' . $certificate_dirs['chain'] . '</code>' ); ?></li>
					<li><?php printf( __( 'Certificate Full Chain: %s', 'wp-encrypt' ), '<code>' . $certificate_dirs['fullchain'] . '</code>' ); ?></li>
					<li><?php printf( __( 'Private Key: %s', 'wp-encrypt' ), '<code>' . $certificate_dirs['key'] . '</code>' ); ?></li>
				</ul>
			<?php endif; ?>
			<?php
		}

		protected function render_apache_instructions( $site_domain, $site_dir, $certificate_dirs ) {
			$config = '<VirtualHost 192.168.0.1:443>
DocumentRoot ' . untrailingslashit( $site_dir ) . '
ServerName ' . $site_domain . '
SSLEngine on
SSLCertificateFile ' . $certificate_dirs['cert'] . '
SSLCertificateKeyFile ' . $certificate_dirs['key'] . '
SSLCertificateChainFile ' . $certificate_dirs['chain'] . '
</VirtualHost>
'; ?>
			<ul>
				<li>
					<strong><?php _e( 'Detect which Apache config file to edit.', 'wp-encrypt' ); ?></strong>
					<br />
					<?php printf( __( 'Usually this file can be found at either %1$s or %2$s.', 'wp-encrypt' ), '<code>/etc/httpd/httpd.conf</code>', '<code>/etc/apache2/apache2.conf</code>' ); ?>
					<?php printf( __( 'In particular, you need to look for a file that contains multiple %s blocks.', 'wp-encrypt' ), '<code>&lt;VirtualHost&gt;</code>' ); ?>
					<br />
					<?php printf( __( 'A good method to detect the file on Linux machines is to use the command %s (the last argument should be the base directory for your Apache installation).', 'wp-encrypt' ), '<code>grep -i -r "SSLCertificateFile" /etc/httpd/</code>' ); ?>
				</li>
				<li>
					<strong><?php printf( __( 'Find the %s block to configure.', 'wp-encrypt' ), '<code>&lt;VirtualHost&gt;</code>' ); ?></strong>
					<br />
					<?php _e( 'You need to find the block that is used to configure your WordPress site. If you want your site to be accessible through both HTTP and HTTPS, copy the block and configure the new block as described below. Otherwise simply configure the existing block.', 'wp-encrypt' ); ?>
				</li>
				<li>
					<strong><?php printf( __( 'Configure your %s block with the certificate.', 'wp-encrypt' ), '<code>&lt;VirtualHost&gt;</code>' ); ?></strong>
					<br />
					<?php _e( 'Below is a simple example configuration for an SSL setup:', 'wp-encrypt' ); ?>
					<br />
					<textarea class="code" readonly="readonly" cols="100" rows="8">
						<?php echo esc_textarea( $config ); ?>
					</textarea>
				</li>
			</ul>
			<?php
		}

		protected function render_nginx_instructions( $site_domain, $site_dir, $certificate_dirs ) {
			$config = 'server {

	listen   443;

	ssl on;
	ssl_certificate ' . $certificate_dirs['fullchain'] . '
	ssl_certificate_key ' . $certificate_dirs['key'] . ';

	server_name ' . $site_domain . ';
	location / {
		root   ' . trailingslashit( $site_dir ) . ';
		index  index.php;
	}

}
'; ?>
			<ul>
				<li>
					<strong><?php _e( 'Find the virtual host you want to configure in your Nginx virtual hosts file.', 'wp-encrypt' ); ?></strong>
					<br />
					<?php _e( 'If you want your site to be accessible through both HTTP and HTTPS, copy the block and configure the new block as described below. Otherwise simply configure the existing block.', 'wp-encrypt' ); ?>
				</li>
				<li>
					<strong><?php _e( 'Configure your virtual host block with the certificate.', 'wp-encrypt' ); ?></strong>
					<br />
					<?php _e( 'Below is a simple example configuration for an SSL setup:', 'wp-encrypt' ); ?>
					<br />
					<textarea class="code" readonly="readonly" cols="100" rows="8">
						<?php echo esc_textarea( $config ); ?>
					</textarea>
				</li>
			</ul>
			<?php
		}
	}
}
