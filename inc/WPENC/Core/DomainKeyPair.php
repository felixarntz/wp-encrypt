<?php
/**
 * @package WPENC
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPENC\Core;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPENC\Core\DomainKeyPair' ) ) {
	/**
	 * This class represents a single pair of public and private key for a domain.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	final class DomainKeyPair extends KeyPair {
		private static $instances = array();

		public static function get( $domain ) {
			if ( ! isset( self::$instances[ $domain ] ) ) {
				self::$instances[ $domain ] = new self( $domain );
			}
			return self::$instances[ $domain ];
		}

		protected function __construct( $domain ) {
			parent::__construct( $domain );
		}
	}
}
