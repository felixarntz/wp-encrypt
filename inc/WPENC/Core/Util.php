<?php
/**
 * WPENC\Core\Util class
 *
 * @package WPENC
 * @subpackage Core
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 1.0.0
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
	 * @since 1.0.0
	 */
	final class Util {
		/**
		 * Checks whether filesystem credentials are required to write to the necessary server locations.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param array|bool $credentials Credentials to check their validity or false for a general check.
		 * @return bool True if filesystem credentials are required, false otherwise.
		 */
		public static function needs_filesystem_credentials( $credentials = false ) {
			$paths = self::get_filesystem_paths();
			$type = 'direct';
			$is_direct = true;
			foreach ( $paths as $key => $path ) {
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

		/**
		 * Sets up the filesystem to be able to write to the server.
		 *
		 * If filesystem credentials are required and haven't been entered yet,
		 * a form to enter them will be shown and the request will exit afterwards.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param string $form_post    The location to post the form to.
		 * @param array  $extra_fields Additional fields to include in the form post request.
		 * @return bool True if the filesystem was setup successfully, false otherwise.
		 */
		public static function setup_filesystem( $form_post, $extra_fields = array() ) {
			global $wp_filesystem;

			$paths = self::get_filesystem_paths();
			$type = 'direct';
			$is_direct = true;
			foreach ( $paths as $key => $path ) {
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
				$error = ( isset( $wp_filesystem ) && is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->get_error_code() ) ? $wp_filesystem->errors : true;
				request_filesystem_credentials( $form_post, $type, $error, $paths[0], $extra_fields, true );
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

		/**
		 * Returns the filesystem instance to access the server filesystem with.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @global WP_Filesystem_Base $wp_filesystem The WordPress filesystem instance.
		 *
		 * @return WP_Filesystem_Base The WordPress filesystem instance.
		 */
		public static function get_filesystem() {
			global $wp_filesystem;

			return $wp_filesystem;
		}

		/**
		 * Returns the base path the key and certificate files reside in.
		 *
		 * The default location is one level above the project's root directory. However, it can
		 * be changed to any location using the `WP_ENCRYPT_SSL_CERTIFICATES_DIR_PATH` constant.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @return string Base path.
		 */
		public static function get_letsencrypt_certificates_dir_path() {
			if ( defined( 'WP_ENCRYPT_SSL_CERTIFICATES_DIR_PATH' ) && WP_ENCRYPT_SSL_CERTIFICATES_DIR_PATH ) {
				return untrailingslashit( WP_ENCRYPT_SSL_CERTIFICATES_DIR_PATH );
			}
			return dirname( self::detect_base( 'path' ) ) . '/letsencrypt/live';
		}

		/**
		 * Returns the base path the challenges for Let's Encrypt reside in.
		 *
		 * The location is inside a `.well-known` directory in the project's root directory.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @return string Base path.
		 */
		public static function get_letsencrypt_challenges_dir_path() {
			return self::detect_base( 'path' ) . self::get_letsencrypt_challenges_relative_dir();
		}

		/**
		 * Returns the base URL the challenges for Let's Encrypt reside in.
		 *
		 * The location is inside a `.well-known` directory in the project's root directory.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @return string Base URL.
		 */
		public static function get_letsencrypt_challenges_dir_url() {
			return self::detect_base( 'url' ) . self::get_letsencrypt_challenges_relative_dir();
		}

		/**
		 * Detects the root directory of this project.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param string $mode Either 'path' or 'url'.
		 * @return string The root path or URL, depending on the $mode parameter.
		 */
		public static function detect_base( $mode = 'url' ) {
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

		/**
		 * Creates the directory for the keys and certificates if it doesn't exist yet.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @return bool|WP_Error True if the directory was created, an error object otherwise.
		 */
		public static function maybe_create_letsencrypt_certificates_dir() {
			return self::maybe_create_dir( self::get_letsencrypt_certificates_dir_path() );
		}

		/**
		 * Creates the directory for the Let's Encrypt challenges if it doesn't exist yet.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @return bool|WP_Error True if the directory was created, an error object otherwise.
		 */
		public static function maybe_create_letsencrypt_challenges_dir() {
			return self::maybe_create_dir( self::get_letsencrypt_challenges_dir_path() );
		}

		/**
		 * Base64-encodes data.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param mixed $data Data to encode.
		 * @return string The encoded data.
		 */
		public static function base64_url_encode( $data ) {
			return str_replace( '=', '', strtr( base64_encode( $data ), '+/', '-_' ) );
		}

		/**
		 * Base64-decodes a string.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param string $data The string to decode.
		 * @return mixed The decoded data.
		 */
		public static function base64_url_decode( $data ) {
			$rest = strlen( $data ) % 4;
			if ( 0 < $rest ) {
				$pad = 4 - $rest;
				$data .= str_repeat( '=', $pad );
			}
			return base64_decode( strtr( $data, '-_', '+/' ) );
		}

		/**
		 * Returns all domains for a list of domains.
		 *
		 * What that function does is to add the www/non-www equivalents of all passed domains
		 * to the array if they are not in the array yet.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param string $domain        The root domain.
		 * @param array  $addon_domains Array of additional domains.
		 * @return array Array of all domains, including www/non-www equivalents.
		 */
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

		/**
		 * Returns the relevant paths that require filesystem access.
		 *
		 * If the inner directories of a path do not yet exist, the method will walk up the path tree
		 * until it finds an existing directory to go from.
		 *
		 * @since 1.0.0
		 * @access private
		 * @static
		 *
		 * @return array An array of paths.
		 */
		private static function get_filesystem_paths() {
			$paths = array(
				self::get_letsencrypt_certificates_dir_path(),
				self::get_letsencrypt_challenges_dir_path(),
			);

			foreach ( $paths as $key => $path ) {
				while ( ! is_dir( $paths[ $key ] ) ) {
					$paths[ $key ] = dirname( $paths[ $key ] );
				}
			}

			return $paths;
		}

		/**
		 * Creates a specific directory if it doesn't exist yet.
		 *
		 * @since 1.0.0
		 * @access private
		 * @static
		 *
		 * @param string $path The path to the directory to maybe create.
		 * @return bool|WP_Error True if the directory was created, an error object otherwise.
		 */
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

		/**
		 * Returns the relative path for the Let's Encrypt challenges directory.
		 *
		 * @since 1.0.0
		 * @access private
		 * @static
		 *
		 * @return string The relative challenges path.
		 */
		private static function get_letsencrypt_challenges_relative_dir() {
			return '/.well-known/acme-challenge';
		}
	}
}
