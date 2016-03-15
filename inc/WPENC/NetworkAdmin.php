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
	class NetworkAdmin extends Admin {
		/**
		 * Class constructor.
		 *
		 * @since 0.5.0
		 */
		protected function __construct() {
			parent::__construct();
			$this->context = 'network';
		}

		public function init_settings() {

		}

		public function init_menu() {

		}

		protected function set_transient( $name, $value, $expiration = 0 ) {
			return set_site_transient( $name, $value, $expiration );
		}

		protected function get_url() {
			return network_admin_url( 'settings.php?page=' . self::PAGE_SLUG );
		}
	}

}
