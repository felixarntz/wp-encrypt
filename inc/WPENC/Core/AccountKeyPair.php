<?php
/**
 * WPENC\Core\AccountKeyPair class
 *
 * @package WPENC
 * @subpackage Core
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.5.0
 */

namespace WPENC\Core;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPENC\Core\AccountKeyPair' ) ) {
	/**
	 * This class represents a single pair of public and private key for an account.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	final class AccountKeyPair extends KeyPair {
		private static $instance = null;

		public static function get() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		protected function __construct() {
			parent::__construct( 'account' );
		}
	}
}
