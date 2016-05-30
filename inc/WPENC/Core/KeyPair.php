<?php
/**
 * WPENC\Core\KeyPair class
 *
 * @package WPENC
 * @subpackage Core
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 0.5.0
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
	 * @internal
	 * @since 0.5.0
	 */
	abstract class KeyPair {
		const PUBLIC_NAME = 'public.pem';
		const PRIVATE_NAME = 'private.pem';

		protected $path = null;
		protected $public_key = null;
		protected $private_key = null;
		protected $private_key_resource = null;
		protected $private_key_details = null;

		protected function __construct( $sub_path ) {
			$this->path = Util::get_letsencrypt_certificates_dir_path() . '/' . trim( $sub_path, '/' );
		}

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

		public function exists() {
			$filesystem = Util::get_filesystem();

			return $filesystem->exists( $this->path . '/' . self::PRIVATE_NAME ) && $filesystem->exists( $this->path . '/' . self::PUBLIC_NAME );
		}

		public function get_public( $force_refresh = false ) {
			$filesystem = Util::get_filesystem();

			if ( null === $this->public_key || $force_refresh ) {
				$path = $this->path . '/' . self::PUBLIC_NAME;
				if ( ! $filesystem->exists( $path ) ) {
					return new WP_Error( 'public_key_missing', __( 'Missing public key.', 'wp-encrypt' ) );
				}
				$this->public_key = $filesystem->get_contents( $path );
			}
			return $this->public_key;
		}

		public function get_private( $force_refresh = false ) {
			$filesystem = Util::get_filesystem();

			if ( null === $this->private_key || $force_refresh ) {
				$path = $this->path . '/' . self::PRIVATE_NAME;
				if ( ! $filesystem->exists( $path ) ) {
					return new WP_Error( 'private_key_missing', __( 'Missing private key.', 'wp-encrypt' ) );
				}
				$this->private_key = $filesystem->get_contents( $path );
			}
			return $this->private_key;
		}

		public function read_private( $force_refresh = false ) {
			$filesystem = Util::get_filesystem();

			if ( null === $this->private_key_resource || $force_refresh ) {
				$path = $this->path . '/' . self::PRIVATE_NAME;
				if ( ! $filesystem->exists( $path ) ) {
					return new WP_Error( 'private_key_missing', __( 'Missing private key.', 'wp-encrypt' ) );
				}

				$private_key = openssl_pkey_get_private( 'file://' . $path );
				if ( false === $private_key ) {
					return new WP_Error( 'private_key_invalid', sprintf( __( 'Invalid private key. Original error message: %s', 'wp-encrypt' ), openssl_error_string() ) );
				}
				$this->private_key_resource = $private_key;
			}
			return $this->private_key_resource;
		}

		public function get_private_details( $force_refresh = false ) {
			if ( null === $this->private_key_details || $force_refresh ) {
				$private_key = $this->read_private();
				if ( is_wp_error( $private_key ) ) {
					return $private_key;
				}
				$details = openssl_pkey_get_details( $private_key );
				if ( false === $details ) {
					return new WP_Error( 'private_key_details_invalid', sprintf( __( 'Could not retrieve details from private key. Original error message: %s', 'wp-encrypt' ), openssl_error_string() ) );
				}
				$this->private_key_details = $details;
			}
			return $this->private_key_details;
		}
	}
}
