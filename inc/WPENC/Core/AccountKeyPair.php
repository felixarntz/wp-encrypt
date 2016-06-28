<?php
/**
 * WPENC\Core\AccountKeyPair class
 *
 * @package WPENC
 * @subpackage Core
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 1.0.0
 */

namespace WPENC\Core;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPENC\Core\AccountKeyPair' ) ) {
	/**
	 * This class represents a single pair of public and private key for an account.
	 *
	 * @since 1.0.0
	 */
	final class AccountKeyPair extends KeyPair {
		/**
		 * Singleton instance.
		 *
		 * @since 1.0.0
		 * @access private
		 * @static
		 * @var WPENC\Core\AccountKeyPair
		 */
		private static $instance = null;

		/**
		 * Singleton method.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @return WPENC\Core\AccountKeyPair The class instance.
		 */
		public static function get() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 * @access protected
		 */
		protected function __construct() {
			parent::__construct( 'account' );
		}
	}
}
