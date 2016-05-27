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
		public static function needs_filesystem_credentials( $credentials = false ) {
			$paths = array(
				self::get_letsencrypt_certificates_dir_path(),
				self::get_letsencrypt_challenges_dir_path(),
			);

			$type = 'direct';
			$is_direct = true;
			foreach ( $paths as $key => $path ) {
				if ( ! is_dir( $paths[ $key ] ) ) {
					$paths[ $key ] = dirname( $paths[ $key ] );
				}
				$type = get_filesystem_method( array(), $paths[ $key ], true );
				if ( 'direct' !== $type ) {
					$is_direct = false;
					break;
				}
			}

			if ( $is_direct ) {
				return false;
			}

			if ( false === $credentials ) {
				ob_start();
				$credentials = request_filesystem_credentials( site_url(), $type, false, $paths[0], null, true );
				$data = ob_get_clean();
				if ( false === $credentials ) {
					return true;
				}
			}

			return ! WP_Filesystem( $credentials, $paths[0], true );
		}

		public static function setup_filesystem( $form_post, $extra_fields = array() ) {
			global $wp_filesystem;

			$paths = array(
				self::get_letsencrypt_certificates_dir_path(),
				self::get_letsencrypt_challenges_dir_path(),
			);

			$type = 'direct';
			$is_direct = true;
			foreach ( $paths as $key => $path ) {
				if ( ! is_dir( $paths[ $key ] ) ) {
					$paths[ $key ] = dirname( $paths[ $key ] );
				}
				$type = get_filesystem_method( array(), $paths[ $key ], true );
				if ( 'direct' !== $type ) {
					$is_direct = false;
					break;
				}
			}

			ob_start();
			if ( false === ( $credentials = request_filesystem_credentials( $form_post, $type, false, $paths[0], $extra_fields, true ) ) ) {
				$data = ob_get_clean();

				if ( ! empty( $data ) ) {
					include_once ABSPATH . 'wp-admin/admin-header.php';
					echo $data;
					include ABSPATH . 'wp-admin/admin-footer.php';
					exit;
				}
				return false;
			}

			if ( ! WP_Filesystem( $credentials, $paths[0], true ) ) {
				request_filesystem_credentials( $form_post, $type, true, $paths[0], $extra_fields, true );
				$data = ob_get_clean();

				if ( ! empty( $data ) ) {
					include_once ABSPATH . 'wp-admin/admin-header.php';
					echo $data;
					include ABSPATH . 'wp-admin/admin-footer.php';
					exit;
				}
				return false;
			}

			if ( ! is_object( $wp_filesystem ) || is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->get_error_code() ) {
				return false;
			}

			return true;
		}

		public static function get_filesystem() {
			global $wp_filesystem;

			return $wp_filesystem;
		}

		public static function get_letsencrypt_certificates_dir_path() {
			if ( defined( 'WP_ENCRYPT_SSL_CERTIFICATES_DIR_PATH' ) && WP_ENCRYPT_SSL_CERTIFICATES_DIR_PATH ) {
				return untrailingslashit( WP_ENCRYPT_SSL_CERTIFICATES_DIR_PATH );
			}
			return dirname( self::detect_base( 'path' ) ) . '/letsencrypt/live';
		}

		public static function get_letsencrypt_challenges_dir_path() {
			return self::detect_base( 'path' ) . self::get_letsencrypt_challenges_relative_dir();
		}

		public static function get_letsencrypt_challenges_dir_url() {
			return self::detect_base( 'url' ) . self::get_letsencrypt_challenges_relative_dir();
		}

		public static function maybe_create_letsencrypt_certificates_dir() {
			return self::maybe_create_dir( self::get_letsencrypt_certificates_dir_path() );
		}

		public static function maybe_create_letsencrypt_challenges_dir() {
			return self::maybe_create_dir( self::get_letsencrypt_challenges_dir_path() );
		}

		private static function maybe_create_dir( $path ) {
			$filesystem = self::get_filesystem();

			if ( ! $filesystem->is_dir( $path ) ) {
				if ( ! $filesystem->is_dir( dirname( $path ) ) ) {
					$filesystem->mkdir( dirname( $path ) );
				}

				if ( ! $filesystem->mkdir( $path ) ) {
					return new WP_Error( 'cannot_create_dir', sprintf( __( 'Could not create directory <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $path ) );
				}
			}
			return true;
		}

		private static function get_letsencrypt_challenges_relative_dir() {
			return '/.well-known/acme-challenge';
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
			return str_replace( '=', '', strtr( base64_encode( $data ), '+/', '-_' ) );
		}

		public static function base64_url_decode( $data ) {
			$rest = strlen( $data ) % 4;
			if ( 0 < $rest ) {
				$pad = 4 - $rest;
				$data .= str_repeat( '=', $pad );
			}
			return base64_decode( strtr( $data, '-_', '+/' ) );
		}

		public static function get_all_domains( $domain, $addon_domains = array() ) {
			array_unshift( $addon_domains, $domain );

			$all_domains = array();

			foreach ( $addon_domains as $addon_domain ) {
				$all_domains[] = $addon_domain;
				if ( 1 === substr_count( $addon_domain, '.' ) ) {
					$all_domains[] = 'www.' . $addon_domain;
				} elseif ( 2 === substr_count( $addon_domain, '.' ) && 'www.' === substr( $addon_domain, 0, 4 ) ) {
					$all_domains[] = substr( $addon_domain, 4 );
				}
			}

			return array_unique( $all_domains );
		}
	}
}
