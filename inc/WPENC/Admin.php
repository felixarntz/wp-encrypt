<?php
/**
 * WPENC\Admin class
 *
 * @package WPENC
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 1.0.0
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
	 * On a regular site, the settings page is in the regular admin, while on a Multisite, the
	 * settings page is located in the network admin.
	 *
	 * @since 1.0.0
	 */
	class Admin {
		/**
		 * The page slug for the settings page.
		 *
		 * @since 1.0.0
		 */
		const PAGE_SLUG = 'wp_encrypt';

		/**
		 * Context for this settings page. Either 'site' or 'network'.
		 *
		 * @since 1.0.0
		 * @access protected
		 * @var string
		 */
		protected $context = 'site';

		/**
		 * Constructor.
		 *
		 * Sets the context for this settings page.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param string $context Either 'site' or 'network'. Default is 'site'.
		 */
		public function __construct( $context = 'site' ) {
			$this->context = $context;
		}

		/**
		 * Adds the required action hooks.
		 *
		 * Depending on the $context property, different hooks are used.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function run() {
			$menu_hook = 'admin_menu';
			$option_hook = 'pre_update_option_wp_encrypt_settings';
			if ( 'network' === $this->context ) {
				$menu_hook = 'network_admin_menu';
				$option_hook = 'pre_update_site_option_wp_encrypt_settings';
			}

			add_action( 'admin_notices', array( $this, 'maybe_show_expire_warning' ) );
			add_action( 'network_admin_notices', array( $this, 'maybe_show_expire_warning' ) );

			add_action( 'admin_init', array( $this, 'init_settings' ) );
			add_action( $menu_hook, array( $this, 'init_menu' ) );

			add_filter( $option_hook, array( $this, 'check_valid' ) );
		}

		/**
		 * Registers setting, setting sections and settings fields.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function init_settings() {
			register_setting( 'wp_encrypt_settings', 'wp_encrypt_settings', array( $this, 'validate_settings' ) );

			add_settings_section( 'wp_encrypt_settings', __( 'Account Settings', 'wp-encrypt' ), array( $this, 'render_settings_description' ), self::PAGE_SLUG );
			add_settings_field( 'organization', __( 'Organization Name', 'wp-encrypt' ), array( $this, 'render_settings_field' ), self::PAGE_SLUG, 'wp_encrypt_settings', array( 'id' => 'organization' ) );
			add_settings_field( 'country_name', __( 'Country Name', 'wp-encrypt' ), array( $this, 'render_settings_field' ), self::PAGE_SLUG, 'wp_encrypt_settings', array( 'id' => 'country_name' ) );
			add_settings_field( 'country_code', __( 'Country Code', 'wp-encrypt' ), array( $this, 'render_settings_field' ), self::PAGE_SLUG, 'wp_encrypt_settings', array( 'id' => 'country_code' ) );

			add_settings_section( 'wp_encrypt_additional_settings', __( 'Additional Settings', 'wp-encrypt' ), '__return_false', self::PAGE_SLUG );
			if ( App::is_multinetwork() ) {
				add_settings_field( 'include_all_networks', __( 'Global SSL Certificate', 'wp-encrypt' ), array( $this, 'render_settings_field' ), self::PAGE_SLUG, 'wp_encrypt_additional_settings', array( 'id' => 'include_all_networks' ) );
			}
			if ( ! CoreUtil::needs_filesystem_credentials() ) {
				add_settings_field( 'autogenerate_certificate', __( 'Auto-generate Certificate', 'wp-encrypt' ), array( $this, 'render_settings_field' ), self::PAGE_SLUG, 'wp_encrypt_additional_settings', array( 'id' => 'autogenerate_certificate' ) );
			}
			add_settings_field( 'show_warning', __( 'Expire Warnings', 'wp-encrypt' ), array( $this, 'render_settings_field' ), self::PAGE_SLUG, 'wp_encrypt_additional_settings', array( 'id' => 'show_warning' ) );
			add_settings_field( 'show_warning_days', __( 'Expire Warnings Trigger', 'wp-encrypt' ), array( $this, 'render_settings_field' ), self::PAGE_SLUG, 'wp_encrypt_additional_settings', array( 'id' => 'show_warning_days' ) );
		}

		/**
		 * Adds the settings page to the menu.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function init_menu() {
			$parent = 'options-general.php';
			if ( 'network' === $this->context ) {
				$parent = 'settings.php';
			}
			add_submenu_page( $parent, __( 'WP Encrypt', 'wp-encrypt' ), __( 'WP Encrypt', 'wp-encrypt' ), 'manage_certificates', self::PAGE_SLUG, array( $this, 'render_page' ) );
		}

		/**
		 * Renders the settings page.
		 *
		 * The settings page contains all UI for the plugin. The user can specify the settings for
		 * his or her organization, then register the account with Let's Encrypt and then generate
		 * a certificate.
		 *
		 * The settings page furthermore provides some additional settings to specify how the plugin
		 * should behave.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function render_page() {
			if ( CoreUtil::needs_filesystem_credentials() ) {
				?>
				<div class="notice notice-warning">
					<p><?php printf( __( 'The directories %1$s and %2$s that WP Encrypt needs access to are not automatically writable by the site. Unless you change this, it is not possible to auto-renew certificates.', 'wp-encrypt' ), '<code>' . CoreUtil::get_letsencrypt_certificates_dir_path() . '</code>', '<code>' . CoreUtil::get_letsencrypt_challenges_dir_path() . '</code>' ); ?></p>
					<p><?php _e( 'Note that you can still manually renew certificates by providing valid filesystem credentials each time.', 'wp-encrypt' ); ?></p>
				</div>
				<?php
			}

			$account_registration_info = Util::get_registration_info( 'account' );
			$certificate_registration_info = Util::get_registration_info( 'certificate' );

			$account_registration_timestamp = false;
			$certificate_generation_timestamp = false;
			$site_domains = array();

			if ( isset( $account_registration_info['_wp_time'] ) ) {
				$account_registration_timestamp = strtotime( $account_registration_info['_wp_time'] );
			}

			if ( isset( $certificate_registration_info['_wp_time'] ) ) {
				$certificate_generation_timestamp = strtotime( $certificate_registration_info['_wp_time'] );
				if ( isset( $certificate_registration_info['domains'] ) ) {
					$site_domains = $certificate_registration_info['domains'];
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

				.remove {
					margin-left: 6px;
					line-height: 28px;
					color: #aa0000;
					text-decoration: none;
				}

				.remove:hover {
					color: #ff0000;
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
							<?php submit_button( __( 'Generate Certificate', 'wp-encrypt' ), ( 'generate' === $primary ? 'primary' : 'secondary' ), 'submit', false, array( 'id' => 'generate-certificate-button' ) ); ?>
							<?php if ( $has_certificate ) : ?>
								<a id="revoke-certificate-button" class="remove" href="<?php echo $this->action_get_url( 'wpenc_revoke_certificate' ); ?>"><?php _e( 'Revoke Certificate', 'wp-encrypt' ); ?></a>
							<?php endif; ?>
						</form>

						<?php if ( $has_certificate ) : ?>
							<?php $this->render_instructions(); ?>
						<?php endif; ?>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<?php

			// for AJAX
			/*if ( CoreUtil::needs_filesystem_credentials() ) {
				wp_print_request_filesystem_credentials_modal();
			}*/
		}

		/**
		 * Renders the main settings section description.
		 *
		 * Used as a callback for `add_settings_section()`.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function render_settings_description() {
			echo '<p class="description">' . __( 'The following settings are required to generate a certificate.', 'wp-encrypt' ) . '</p>';
		}

		/**
		 * Renders a settings field.
		 *
		 * Used as a callback for `add_settings_field()`.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param array $args Arguments for the current field.
		 */
		public function render_settings_field( $args = array() ) {
			$value = Util::get_option( $args['id'] );

			$type = 'text';
			$more_args = '';

			switch ( $args['id'] ) {
				case 'country_code':
					$more_args .= ' maxlength="2"';
					break;
				case 'include_all_networks':
				case 'autogenerate_certificate':
				case 'show_warning':
					$type = 'checkbox';
					if ( $value ) {
						$more_args .= ' checked="checked"';
					}
					$value = '1';
					break;
				case 'show_warning_days':
					$type = 'number';
					$more_args .= ' min="1" max="90" step="1"';
					break;
			}

			echo '<input type="' . $type . '" id="' . $args['id'] . '" name="wp_encrypt_settings[' . $args['id'] . ']" value="' . $value . '"' . $more_args . ' />';
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
				case 'include_all_networks':
					$description = __( 'Generate a global certificate for all networks? This will ensure that all sites in your entire WordPress setup are covered.', 'wp-encrypt' );
					break;
				case 'autogenerate_certificate':
					$description = __( 'Automatically regenerate the certificate prior to expiration? A Let&apos;s Encrypt certificate is valid for 90 days.', 'wp-encrypt' );
					break;
				case 'show_warning':
					$description = __( 'Show a warning across the admin when the certificate is close to expire?', 'wp-encrypt' );
					break;
				case 'show_warning_days':
					$description = __( 'Specify the amount of days that should trigger the warning to show.', 'wp-encrypt' );
					break;
				default:
			}
			if ( isset( $description ) ) {
				echo ' <span class="description">' . $description . '</span>';
			}
		}

		/**
		 * Shows a warning in the admin if the current certificate is close to expiration.
		 *
		 * Whether a warning like this should be shown or not can be specified through a setting,
		 * as well as how many days before expiration it should start to show.
		 *
		 * Let's Encrypt certificates are valid for 90 days. The plugin may autogenerate the
		 * certificate prior to expiration, but it is recommended to have this message show to keep
		 * that in mind.
		 *
		 * @since 1.0.0
		 * @access public
		 */
		public function maybe_show_expire_warning() {
			if ( ! Util::get_option( 'show_warning' ) ) {
				return;
			}

			if ( ! current_user_can( 'manage_certificates' ) ) {
				return;
			}

			$certificate_registration_info = Util::get_registration_info( 'certificate' );
			if ( ! isset( $certificate_registration_info['_wp_time'] ) ) {
				return;
			}

			$expire = strtotime( $certificate_registration_info['_wp_time'] ) + 90 * DAY_IN_SECONDS;
			$now = current_time( 'timestamp' );

			$diff = absint( ( $expire - $now ) / DAY_IN_SECONDS );

			$trigger = Util::get_option( 'show_warning_days' );
			if ( $diff > $trigger ) {
				return;
			}

			if ( 'network' === $this->context ) {
				$url = network_admin_url( 'settings.php?page=wp_encrypt' );
			} else {
				$url = admin_url( 'options-general.php?page=wp_encrypt' );
			}

			if ( Util::get_option( 'autogenerate_certificate' ) ) {
				$text = _n( 'The Let&apos;s Encrypt certificate will expire in %1$s day. It will be automatically renewed prior to expiration, but you can also manually renew it <a href="%2$s">here</a>.', 'The Let&apos;s Encrypt certificate will expire in %1$s days. It will be automatically renewed prior to expiration, but you can also manually renew it <a href="%2$s">here</a>.', $diff, 'wp-encrypt' );
			} else {
				$text = _n( 'The Let&apos;s Encrypt certificate will expire in %1$s day. Please renew it soon <a href="%2$s">here</a>.', 'The Let&apos;s Encrypt certificate will expire in %1$s days. Please renew it soon <a href="%2$s">here</a>.', $diff, 'wp-encrypt' );
			}

			?>
			<div id="wp-encrypt-expire-warning" class="notice notice-warning">
				<p><?php printf( $text, number_format_i18n( $diff ), $url ); ?></p>
			</div>
			<?php
		}

		/**
		 * Validates the plugin settings.
		 *
		 * Used as a callback for `register_setting()`.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param array $options The options prior to being saved.
		 * @return array The validated options.
		 */
		public function validate_settings( $options = array() ) {
			$options = array_map( 'strip_tags', array_map( 'trim', $options ) );

			if ( isset( $options['country_code'] ) ) {
				$options['country_code'] = strtoupper( substr( $options['country_code'], 0, 2 ) );
			}
			if ( isset( $options['show_warning_days'] ) ) {
				$options['show_warning_days'] = absint( $options['show_warning_days'] );
			}

			if ( isset( $options['autogenerate_certificate'] ) && $options['autogenerate_certificate'] ) {
				$certificate_registration_info = Util::get_registration_info( 'certificate' );
				if ( isset( $certificate_registration_info['_wp_time'] ) ) {
					Util::schedule_autogenerate_event( strtotime( $certificate_registration_info['_wp_time'] ) );
				}
			} else {
				Util::unschedule_autogenerate_event();
			}

			return $options;
		}

		/**
		 * Checks whether the main settings for the plugin are filled and valid.
		 *
		 * This is an additional check that is made before the options are saved.
		 * Only when they are valid, it is possible to generate certificates with Let's Encrypt.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param array $options The options prior to being saved.
		 * @return array The options including a `valid` key.
		 */
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

		/**
		 * Prints the hidden fields for an action form.
		 *
		 * These fields are printed for the `register_account` and `generate_certificate` actions.
		 * The `revoke_certificate` action is invoked by a GET request (a simple link button).
		 *
		 * @since 1.0.0
		 * @access protected
		 *
		 * @param string $action The action the fields should be printed for.
		 */
		protected function action_post_fields( $action ) {
			echo '<input type="hidden" name="action" value="' . $action . '" />';
			wp_nonce_field( 'wp_encrypt_action' );
		}

		/**
		 * Returns the URL to run an action.
		 *
		 * The plugin only uses this for the `revoke_certificate` action. The `register_account` and
		 * `generate_certificate` actions are invoked by a POST request (a form).
		 *
		 * @since 1.0.0
		 * @access protected
		 *
		 * @param string $action The action the URL should be returned for.
		 * @return string The URL to trigger the action.
		 */
		protected function action_get_url( $action ) {
			$url = ( 'network' === $this->context ) ? network_admin_url( 'settings.php' ) : admin_url( 'options-general.php' );
			$url = add_query_arg( 'action', $action, $url );
			$url = wp_nonce_url( $url, 'wp_encrypt_action' );
			return $url;
		}

		/**
		 * Renders basic instructions on how to set up the server with the acquired Let's Encrypt
		 * certificate.
		 *
		 * The plugin can only obtain the certificates. Setting up the server to include them is not
		 * possible through WordPress.
		 *
		 * These instructions should help to get familiar with the process of setting up SSL on the
		 * server. For specific use-cases, online tutorials are probably a better source of
		 * information.
		 *
		 * This method also shows where the keys and certificates are located on disk so that the
		 * user knows where to find them.
		 *
		 * @since 1.0.0
		 * @access protected
		 */
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

		/**
		 * Renders basic instructions on how to set up an Apache server with the acquired SSL
		 * certificate.
		 *
		 * @since 1.0.0
		 * @access protected
		 *
		 * @param string $site_domain      The (root) domain for this site (or Multisite).
		 * @param string $site_dir         The root directory for this WordPress setup.
		 * @param array  $certificate_dirs Array of directories for the keys and certificates.
		 */
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
					<textarea class="code" readonly="readonly" cols="100" rows="9"><?php echo esc_textarea( $config ); ?></textarea>
				</li>
			</ul>
			<?php
		}

		/**
		 * Renders basic instructions on how to set up an nginx server with the acquired SSL
		 * certificate.
		 *
		 * @since 1.0.0
		 * @access protected
		 *
		 * @param string $site_domain      The (root) domain for this site (or Multisite).
		 * @param string $site_dir         The root directory for this WordPress setup.
		 * @param array  $certificate_dirs Array of directories for the keys and certificates.
		 */
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
					<?php _e( 'If you want your site to be accessible through both HTTP and HTTPS, copy the existing block and configure the new block as described below. Otherwise simply configure the existing block.', 'wp-encrypt' ); ?>
				</li>
				<li>
					<strong><?php _e( 'Configure your virtual host block with the certificate.', 'wp-encrypt' ); ?></strong>
					<br />
					<?php _e( 'Below is a simple example configuration for an SSL setup:', 'wp-encrypt' ); ?>
					<br />
					<textarea class="code" readonly="readonly" cols="100" rows="16"><?php echo esc_textarea( $config ); ?></textarea>
				</li>
			</ul>
			<?php
		}
	}
}
