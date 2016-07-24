<?php
/**
 * WPENC\App class
 *
 * @package WPENC
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 1.0.0
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
	 * @since 1.0.0
	 */
	final class App extends Plugin {

		/**
		 * Holds the plugin data. This property is used by the parent class.
		 *
		 * @since 1.0.0
		 * @access protected
		 * @static
		 * @var array
		 */
		protected static $_args = array();

		/**
		 * Stores whether this is a Multinetwork setup.
		 *
		 * @since 1.0.0
		 * @access protected
		 * @static
		 * @var bool
		 */
		protected static $is_multinetwork = null;

		/**
		 * Holds the admin class instance.
		 *
		 * @since 1.0.0
		 * @access protected
		 * @var WPENC\Admin|null
		 */
		protected $admin = null;

		/**
		 * Constructor.
		 *
		 * This is protected on purpose since it is called by the parent class' singleton.
		 *
		 * @since 1.0.0
		 * @access protected
		 *
		 * @param array $args Array of class arguments passed by the plugin utility class.
		 */
		protected function __construct( $args ) {
			parent::__construct( $args );
		}

		/**
		 * Initializes the plugin and adds the required action hooks.
		 *
		 * This method will automatically be invoked by the parent class.
		 *
		 * @since 1.0.0
		 * @access protected
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

			$this->admin = new Admin( $context );
			$this->admin->run();
		}

		/**
		 * Returns the admin class instance.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @return WPENC\Admin|null The admin class instance or null prior to initialization.
		 */
		public function admin() {
			return $this->admin;
		}

		/**
		 * Ensures that, on a regular site, the admin has the capabilities to manage certificates.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param array  $caps    The capabilities to return.
		 * @param string $cap     The capability to be mapped.
		 * @param int    $user_id The current user's ID.
		 * @param array  $args    Additional arguments.
		 * @return array The adjusted capabilities to return.
		 */
		public function map_meta_cap_non_multisite( $caps, $cap, $user_id, $args ) {
			if ( 'manage_certificates' === $cap ) {
				$caps = array( 'manage_options' );
			}
			return $caps;
		}

		/**
		 * Checks whether this is a Multinetwork setup.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @return bool Whether this is a Multinetwork setup.
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

		/**
		 * Returns the URL to the admin screen.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param string $context Context for the URL. Either 'site' or 'network'.
		 * @return string URL to the admin screen.
		 */
		public static function get_admin_url( $context ) {
			$url = '';

			if ( 'network' !== $context ) {
				$context = 'site';
				$url = admin_url( 'options-general.php?page=wp_encrypt' );
			} else {
				$url = network_admin_url( 'settings.php?page=wp_encrypt' );
			}

			/**
			 * Filters the URL to the admin screen.
			 *
			 * @since 1.0.0
			 *
			 * @param string $url     URL to the admin screen.
			 * @param string $context Context for the URL. Either 'site' or 'network'.
			 */
			return apply_filters( 'wpenc_admin_url', $url, $context );
		}

		/**
		 * Returns the URL that processes admin requests via GET or POST.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param string $context Context for the URL. Either 'site' or 'network'.
		 * @param string $action  The action to perform or an empty string.
		 * @return string URL to handle actions.
		 */
		public static function get_admin_action_url( $context, $action = '' ) {
			$url = '';

			if ( 'network' !== $context ) {
				$context = 'site';
				$url = admin_url( 'options.php' );
			} else {
				$url = network_admin_url( 'settings.php' );
			}

			if ( ! empty( $action ) ) {
				$url = add_query_arg( 'action', $action, $url );
				$url = wp_nonce_url( $url, 'wp_encrypt_action' );
			}

			/**
			 * Filters the URL that processes admin requests via GET or POST.
			 *
			 * @since 1.0.0
			 *
			 * @param string $url     URL to handle actions.
			 * @param string $context Context for the URL. Either 'site' or 'network'.
			 * @param string $action  The action to perform or an empty string.
			 */
			return apply_filters( 'wpenc_admin_action_url', $url, $context, $action );
		}

		/**
		 * Returns the parent file name for the admin screen.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param string $context Context for the admin screen. Either 'site' or 'network'.
		 * @return string Parent file name of the admin screen.
		 */
		public static function get_admin_parent_file( $context ) {
			$parent_file = '';

			if ( 'network' !== $context ) {
				$context = 'site';
				$parent_file = 'options-general.php';
			} else {
				$parent_file = 'settings.php';
			}

			/**
			 * Filters the admin screen's parent file name.
			 *
			 * @since 1.0.0
			 *
			 * @param string $parent_file Parent file of the admin screen.
			 * @param string $context     Context for the admin screen. Either 'site' or 'network'.
			 */
			return apply_filters( 'wpenc_admin_parent_file', $parent_file, $context );
		}

		/**
		 * Returns the action file name the admin screen forms should POST to.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param string $context Context for the admin screen. Either 'site' or 'network'.
		 * @return string Action file name of the admin screen.
		 */
		public static function get_admin_action_file( $context ) {
			$action_file = '';

			if ( 'network' !== $context ) {
				$context = 'site';
				$action_file = 'options.php';
			} else {
				$action_file = 'settings.php';
			}

			/**
			 * Filters the admin screen's parent file name.
			 *
			 * @since 1.0.0
			 *
			 * @param string $action_file Action file name of the admin screen.
			 * @param string $context     Context for the admin screen. Either 'site' or 'network'.
			 */
			return apply_filters( 'wpenc_admin_action_file', $action_file, $context );
		}

		/**
		 * Adds a link to the plugin settings page.
		 *
		 * This method is automatically invoked by the plugin loader class.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param array $links The existing plugin action links.
		 * @return array The adjusted links.
		 */
		public static function filter_plugin_links( $links = array() ) {
			if ( is_multisite() ) {
				return self::filter_network_plugin_links( $links );
			}

			if ( ! current_user_can( 'manage_certificates' ) ) {
				return $links;
			}

			$custom_links = array(
				'<a href="' . self::get_admin_url( 'site' ) . '">' . __( 'Settings', 'wp-encrypt' ) . '</a>',
			);

			return array_merge( $custom_links, $links );
		}

		/**
		 * Adds a link to the plugin settings page in the network admin.
		 *
		 * This method is automatically invoked by the plugin loader class.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param array $links The existing plugin action links.
		 * @return array The adjusted links.
		 */
		public static function filter_network_plugin_links( $links = array() ) {
			if ( ! current_user_can( 'manage_certificates' ) ) {
				return $links;
			}

			$custom_links = array(
				'<a href="' . self::get_admin_url( 'network' ) . '">' . __( 'Settings', 'wp-encrypt' ) . '</a>',
			);

			return array_merge( $custom_links, $links );
		}

		/**
		 * Renders a plugin information message.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param string $status The activation status of the plugin. Either 'activated' or 'active'.
		 * @param string $context In which context we're currently in. Either 'site' or 'network'. Defaults to 'site'.
		 */
		public static function render_status_message( $status, $context = 'site' ) {
			$settings_page_url = self::get_admin_url( $context );

			?>
			<p>
				<?php if ( 'activated' === $status ) : ?>
					<?php printf( __( 'You have just activated %s.', 'wp-encrypt' ), '<strong>' . self::get_info( 'name' ) . '</strong>' ); ?>
				<?php elseif ( 'network' === $context ) : ?>
					<?php printf( __( 'You are running %s on your network.', 'wp-encrypt' ), '<strong>' . self::get_info( 'name' ) . '</strong>' ); ?>
				<?php else : ?>
					<?php printf( __( 'You are running %s on your site.', 'wp-encrypt' ), '<strong>' . self::get_info( 'name' ) . '</strong>' ); ?>
				<?php endif; ?>
				<?php _e( 'This plugin provides you with an easy way to manage SSL certificates through Let&apos;s Encrypt.', 'wp-encrypt' ); ?>
			</p>
			<?php if ( current_user_can( 'manage_certificates' ) ) : ?>
				<p>
					<?php printf( __( 'To get started, please follow the steps on the <a href="%s">Settings page</a>.', 'wp-encrypt' ), $settings_page_url ); ?>
				</p>
			<?php endif; ?>
			<?php
		}

		/**
		 * Renders a network plugin information message.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param string $status The activation status of the plugin. Either 'activated' or 'active'.
		 * @param string $context In which context we're currently in. Either 'site' or 'network'. Defaults to 'network'.
		 */
		public static function render_network_status_message( $status, $context = 'network' ) {
			self::render_status_message( $status, $context );
		}

		/**
		 * Uninstalls the plugin from a single site.
		 *
		 * This method is run on plugin deletion.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @return bool Whether the plugin was successfully uninstalled.
		 */
		public static function uninstall() {
			// On a multisite, the plugin loader runs this method for each site which is not required for this plugin.
			if ( is_multisite() ) {
				return true;
			}

			delete_option( 'wp_encrypt_settings' );
			delete_option( 'wp_encrypt_registration' );

			return true;
		}

		/**
		 * Uninstalls the plugin from a network.
		 *
		 * This method is run on plugin deletion.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @return bool Whether the plugin was successfully uninstalled.
		 */
		public static function network_uninstall() {
			delete_site_option( 'wp_encrypt_settings' );
			delete_site_option( 'wp_encrypt_registration' );

			return true;
		}
	}
}
