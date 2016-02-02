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

if ( ! class_exists( 'WPENC\Core\Util' ) ) {
	/**
	 * This class contains static utility methods.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	final class Util {
		public static function get_letsencrypt_certificates_dir_path() {
			if ( defined( 'WP_ENCRYPT_SSL_CERTIFICATES_DIR_PATH' ) && WP_ENCRYPT_SSL_CERTIFICATES_DIR_PATH ) {
				return untrailingslashit( WP_ENCRYPT_SSL_CERTIFICATES_DIR_PATH );
			}
			return self::detect_base( 'path' ) . '/' . self::get_letsencrypt_dirname() . '/.certs';
		}

		public static function get_letsencrypt_challenges_dir_path() {
			if ( defined( 'WP_ENCRYPT_SSL_CHALLENGES_DIR_PATH' ) && WP_ENCRYPT_SSL_CHALLENGES_DIR_PATH ) {
				return untrailingslashit( WP_ENCRYPT_SSL_CHALLENGES_DIR_PATH );
			}
			return self::detect_base( 'path' ) . '/' . self::get_letsencrypt_dirname() . '/.well-known/acme-challenge';
		}

		public static function get_letsencrypt_challenges_dir_url() {
			if ( defined( 'WP_ENCRYPT_SSL_CHALLENGES_DIR_URL' ) && WP_ENCRYPT_SSL_CHALLENGES_DIR_URL ) {
				return untrailingslashit( WP_ENCRYPT_SSL_CHALLENGES_DIR_URL );
			}
			return self::detect_base( 'url' ) . '/' . self::get_letsencrypt_dirname() . '/.well-known/acme-challenge';
		}

		public static function get_letsencrypt_dirname() {
			if ( defined( 'WP_ENCRYPT_SSL_DIRNAME' ) && WP_ENCRYPT_SSL_DIRNAME ) {
				return trim( WP_ENCRYPT_SSL_DIRNAME, '/' );
			}
			return 'letsencrypt';
		}

		private static function detect_base( $mode = 'url' ) {
			$content_parts = explode( '/', str_replace( array( 'https://', 'http://' ), '', rtrim( WP_CONTENT_URL, '/' ) ) );
			$dirname_up = count( $content_parts ) - 1;

			$base = WP_CONTENT_URL;
			if ( 'path' === $mode ) {
				$base = WP_CONTENT_DIR;
			}

			$base = rtrim( $base, '/' );

			for ( $i = 0; $i < $dirname_up; $i++ ) {
				$base = dirname( $base );
			}

			return $base;
		}

		public static function base64_url_encode( $data ) {
			return str_replace( '=', '', strtr( base64_encode( $data, '+/', '-_' ) ) );
		}

		public static function base64_url_decode( $data ) {
			$rest = strlen( $data ) % 4;
			if ( 0 < $rest ) {
				$pad = 4 - $rest;
				$data .= str_repeat( '=', $pad );
			}
			return base64_decode( strtr( $data, '-_', '+/' ) );
		}
	}
}
