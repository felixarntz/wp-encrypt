<?php
/**
 * WPENC\Core\DomainKeyPair class
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

if ( ! class_exists( 'WPENC\Core\DomainKeyPair' ) ) {
	/**
	 * This class represents a single pair of public and private key for a domain.
	 *
	 * @since 1.0.0
	 */
	final class DomainKeyPair extends KeyPair {
		/**
		 * Singleton instances.
		 *
		 * @since 1.0.0
		 * @access private
		 * @static
		 * @var array
		 */
		private static $instances = array();

		/**
		 * Singleton method.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param string $domain The domain to get the instance for.
		 * @return WPENC\Core\DomainKeyPair The class instance for the domain.
		 */
		public static function get( $domain ) {
			if ( ! isset( self::$instances[ $domain ] ) ) {
				self::$instances[ $domain ] = new self( $domain );
			}
			return self::$instances[ $domain ];
		}

		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 * @access protected
		 *
		 * @param string $domain The domain of this key pair.
		 */
		protected function __construct( $domain ) {
			parent::__construct( $domain );
		}
	}
}
