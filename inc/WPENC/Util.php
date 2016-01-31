<?php
/**
 * @package WPENC
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPENC;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPENC\Util' ) ) {
	/**
	 * This class contains static utility methods.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	final class Util {
		public static function update_option( $field, $value = '' ) {
			$options = get_option( 'wp_encrypt_settings', array() );
			if ( is_array( $field ) ) {
				$options = array_merge( $options, $field );
			} else {
				$options[ $field ] = $value;
			}
			return update_option( 'wp_encrypt_settings', $options );
		}

		public static function get_option( $field ) {
			$options = get_option( 'wp_encrypt_settings', array() );
			if ( ! isset( $options[ $field ] ) ) {
				switch ( $field ) {
					case 'organization':
						return get_bloginfo( 'name' );
					case 'country_code':
						return substr( get_locale(), 3, 2 );
					case 'valid':
						return false;
					default:
						return '';
				}
			}
			return $options[ $field ];
		}

		public static function set_registration_info( $field, $value ) {
			if ( 'account' !== $field && ! is_numeric( $field ) ) {
				return false;
			}
			$options = get_site_option( 'wp_encrypt_registration', array() );
			$options[ $field ] = $value;
			return update_site_option( 'wp_encrypt_registration', $options );
		}

		public static function get_registration_info( $field ) {
			if ( 'account' !== $field && ! is_numeric( $field ) ) {
				return '';
			}
			$options = get_site_option( 'wp_encrypt_registration', array() );
			if ( ! isset( $options[ $field ] ) ) {
				return '';
			}
			return $options[ $field ];
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

		public static function get_domain( $site_id = null ) {
			$url = get_home_url( $site_id );
			$url = explode( '/', str_replace( array( 'https://', 'http://' ), '', $url ) );
			return $url[0];
		}

		public static function get_letsencrypt_dir_path() {
			if ( defined( 'WP_ENCRYPT_SSL_DIR_PATH' ) && WP_ENCRYPT_SSL_DIR_PATH ) {
				return untrailingslashit( WP_ENCRYPT_SSL_DIR_PATH );
			}
			return self::detect_base( 'path' ) . '/' . self::get_letsencrypt_dirname();
		}

		public static function get_letsencrypt_dir_url() {
			if ( defined( 'WP_ENCRYPT_SSL_DIR_URL' ) && WP_ENCRYPT_SSL_DIR_URL ) {
				return untrailingslashit( WP_ENCRYPT_SSL_DIR_URL );
			}
			return self::detect_base( 'url' ) . '/' . self::get_letsencrypt_dirname();
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
	}
}
