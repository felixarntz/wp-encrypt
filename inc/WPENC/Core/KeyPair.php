<?php
/**
 * WPENC\Core\KeyPair class
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

if ( ! class_exists( 'WPENC\Core\KeyPair' ) ) {
	/**
	 * This class represents a single pair or public and private key.
	 *
	 * @since 1.0.0
	 */
	abstract class KeyPair {
		/**
		 * Filename for the public key.
		 *
		 * @since 1.0.0
		 */
		const PUBLIC_NAME = 'public.pem';

		/**
		 * Filename for the private key.
		 *
		 * @since 1.0.0
		 */
		const PRIVATE_NAME = 'private.pem';

		/**
		 * The path the key files reside in.
		 *
		 * @since 1.0.0
		 * @access protected
		 * @var string
		 */
		protected $path = null;

		/**
		 * The public key. Used temporarily to cache within the same request.
		 *
		 * @since 1.0.0
		 * @access protected
		 * @var string
		 */
		protected $public_key = null;

		/**
		 * The private key. Used temporarily to cache within the same request.
		 *
		 * @since 1.0.0
		 * @access protected
		 * @var string
		 */
		protected $private_key = null;

		/**
		 * The private key resource. Used temporarily to cache within the same request.
		 *
		 * @since 1.0.0
		 * @access protected
		 * @var resource
		 */
		protected $private_key_resource = null;

		/**
		 * The private key details. Used temporarily to cache within the same request.
		 *
		 * @since 1.0.0
		 * @access protected
		 * @var array
		 */
		protected $private_key_details = null;

		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 * @access protected
		 *
		 * @param string $sub_path The relative sub path for the key files.
		 */
		protected function __construct( $sub_path ) {
			$this->path = Util::get_letsencrypt_certificates_dir_path() . '/' . trim( $sub_path, '/' );
		}

		/**
		 * Generates the public and private key.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @return bool|WP_Error True if successful, an error object otherwise.
		 */
		public function generate() {
			$filesystem = Util::get_filesystem();

			$status = Util::maybe_create_letsencrypt_certificates_dir();
			if ( is_wp_error( $status ) ) {
				return $status;
			}

			$res = openssl_pkey_new( array(
				'private_key_type'	=> OPENSSL_KEYTYPE_RSA,
				'private_key_bits'	=> 4096,
			) );
			if ( false === $res ) {
				return new WP_Error( 'private_key_cannot_generate', sprintf( __( 'Could not generate private key. Original error message: %s', 'wp-encrypt' ), openssl_error_string() ) );
			}

			if ( false === openssl_pkey_export( $res, $private_key ) ) {
				return new WP_Error( 'private_key_cannot_export', sprintf( __( 'Could not export private key. Original error message: %s', 'wp-encrypt' ), openssl_error_string() ) );
			}

			$this->private_key = $private_key;
			$this->private_key_resource = $res;

			$details = $this->get_private_details();
			if ( is_wp_error( $details ) ) {
				return $details;
			}

			$this->public_key = $details['key'];

			if ( ! $filesystem->is_dir( $this->path ) ) {
				$filesystem->mkdir( $this->path, 0700, true );
				if ( ! $filesystem->is_dir( $this->path ) ) {
					return new WP_Error( 'private_key_cannot_create_dir', sprintf( __( 'Could not create directory <code>%s</code> for private key. Please check your filesystem permissions.', 'wp-encrypt' ), $this->path ) );
				}
			}

			if ( false === $filesystem->put_contents( $this->path . '/' . self::PRIVATE_NAME, $this->private_key ) ) {
				return new WP_Error( 'private_key_cannot_write', sprintf( __( 'Could not write private key into file <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $this->path . '/' . self::PRIVATE_NAME ) );
			}

			if ( false === $filesystem->put_contents( $this->path . '/' . self::PUBLIC_NAME, $this->public_key ) ) {
				return new WP_Error( 'public_key_cannot_write', sprintf( __( 'Could not write public key into file <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $this->path . '/' . self::PUBLIC_NAME ) );
			}

			return true;
		}

		/**
		 * Checks whether the public and private key exist.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @return bool True if the key files exists, false otherwise.
		 */
		public function exists() {
			$filesystem = Util::get_filesystem();

			return $filesystem->exists( $this->path . '/' . self::PRIVATE_NAME ) && $filesystem->exists( $this->path . '/' . self::PUBLIC_NAME );
		}

		/**
		 * Returns the public key.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param bool $force_refresh Whether to explicitly re-check the public key.
		 * @return string|WP_Error The public key if successful or an error object otherwise.
		 */
		public function get_public( $force_refresh = false ) {
			$filesystem = Util::get_filesystem();

			if ( null === $this->public_key || $force_refresh ) {
				$path = $this->path . '/' . self::PUBLIC_NAME;
				if ( ! $filesystem->exists( $path ) ) {
					return new WP_Error( 'public_key_missing', sprintf( __( 'Missing public key <code>%s</code>.', 'wp-encrypt' ), $path ) );
				}
				$this->public_key = $filesystem->get_contents( $path );
			}
			return $this->public_key;
		}

		/**
		 * Returns the private key.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param bool $force_refresh Whether to explicitly re-check the private key.
		 * @return string|WP_Error The private key if successful or an error object otherwise.
		 */
		public function get_private( $force_refresh = false ) {
			$filesystem = Util::get_filesystem();

			if ( null === $this->private_key || $force_refresh ) {
				$path = $this->path . '/' . self::PRIVATE_NAME;
				if ( ! $filesystem->exists( $path ) ) {
					return new WP_Error( 'private_key_missing', sprintf( __( 'Missing private key <code>%s</code>.', 'wp-encrypt' ), $path ) );
				}
				$this->private_key = $filesystem->get_contents( $path );
			}
			return $this->private_key;
		}

		/**
		 * Returns the private key resource.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param bool $force_refresh Whether to explicitly re-check the private key resource.
		 * @return resource|WP_Error The private key resource if successful or an error object otherwise.
		 */
		public function read_private( $force_refresh = false ) {
			$filesystem = Util::get_filesystem();

			if ( null === $this->private_key_resource || $force_refresh ) {
				$path = $this->path . '/' . self::PRIVATE_NAME;
				if ( ! $filesystem->exists( $path ) ) {
					return new WP_Error( 'private_key_missing', sprintf( __( 'Missing private key <code>%s</code>.', 'wp-encrypt' ), $path ) );
				}

				$private_key = openssl_pkey_get_private( 'file://' . $path );
				if ( false === $private_key ) {
					return new WP_Error( 'private_key_invalid', sprintf( __( 'Invalid private key <code>%1$s</code>. Original error message: %2$s', 'wp-encrypt' ), $path, openssl_error_string() ) );
				}
				$this->private_key_resource = $private_key;
			}
			return $this->private_key_resource;
		}

		/**
		 * Returns the private key details.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param bool $force_refresh Whether to explicitly re-check the private key details.
		 * @return array|WP_Error The private key details if successful or an error object otherwise.
		 */
		public function get_private_details( $force_refresh = false ) {
			if ( null === $this->private_key_details || $force_refresh ) {
				$private_key = $this->read_private();
				if ( is_wp_error( $private_key ) ) {
					return $private_key;
				}
				$details = openssl_pkey_get_details( $private_key );
				if ( false === $details ) {
					return new WP_Error( 'private_key_details_invalid', sprintf( __( 'Could not retrieve details from private key <code>%1$s</code>. Original error message: %2$s', 'wp-encrypt' ), $this->path . '/' . self::PRIVATE_NAME, openssl_error_string() ) );
				}
				$this->private_key_details = $details;
			}
			return $this->private_key_details;
		}
	}
}
