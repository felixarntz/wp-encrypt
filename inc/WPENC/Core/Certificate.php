<?php
/**
 * WPENC\Core\Certificate class
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

if ( ! class_exists( 'WPENC\Core\Certificate' ) ) {
	/**
	 * This class represents a single certificate.
	 *
	 * @since 1.0.0
	 */
	final class Certificate {
		/**
		 * Filename for the full chain (certificate and chain).
		 *
		 * @since 1.0.0
		 */
		const FULLCHAIN_NAME = 'fullchain.pem';

		/**
		 * Filename for the certificate.
		 *
		 * @since 1.0.0
		 */
		const CERT_NAME = 'cert.pem';

		/**
		 * Filename for the certificate chain.
		 *
		 * @since 1.0.0
		 */
		const CHAIN_NAME = 'chain.pem';

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
		 * @param string $domain The root domain to get the instance for.
		 * @return WPENC\Core\Certificate The class instance for the domain.
		 */
		public static function get( $domain ) {
			if ( ! isset( self::$instances[ $domain ] ) ) {
				self::$instances[ $domain ] = new self( $domain );
			}
			return self::$instances[ $domain ];
		}

		/**
		 * The root domain for this certificate.
		 *
		 * @since 1.0.0
		 * @access private
		 * @var string
		 */
		private $domain = null;

		/**
		 * The path the certificate files reside in.
		 *
		 * @since 1.0.0
		 * @access private
		 * @var string
		 */
		private $path = null;

		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 * @access private
		 *
		 * @param string $domain The root domain of this certificate.
		 */
		private function __construct( $domain ) {
			$this->domain = $domain;
			$this->path = Util::get_letsencrypt_certificates_dir_path() . '/' . $domain;
		}

		/**
		 * Writes certificates into the certificate files.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param array $certs Array of certificate strings.
		 * @return bool|WP_Error True if successful, an error object otherwise.
		 */
		public function set( $certs ) {
			$filesystem = Util::get_filesystem();

			$status = Util::maybe_create_letsencrypt_certificates_dir();
			if ( is_wp_error( $status ) ) {
				return $status;
			}

			$certs = array_map( array( $this, 'parse_pem' ), $certs );
			if ( false === $filesystem->put_contents( $this->path . '/' . self::FULLCHAIN_NAME, implode( "\n", $certs ) ) ) {
				return new WP_Error( 'new_cert_cannot_write_fullchain', sprintf( __( 'Could not write certificates to file <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $this->path . '/' . self::FULLCHAIN_NAME ) );
			}
			if ( false === $filesystem->put_contents( $this->path . '/' . self::CERT_NAME, array_shift( $certs ) ) ) {
				return new WP_Error( 'new_cert_cannot_write_cert', sprintf( __( 'Could not write certificate to file <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $this->path . '/' . self::CERT_NAME ) );
			}
			if ( false === $filesystem->put_contents( $this->path . '/' . self::CHAIN_NAME, implode( "\n", $certs ) ) ) {
				return new WP_Error( 'new_cert_cannot_write_chain', sprintf( __( 'Could not write certificates to file <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $this->path . '/' . self::CHAIN_NAME ) );
			}
			return true;
		}

		/**
		 * Checks whether the certificate for this domain exists.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @return bool True if the certificate files exists, false otherwise.
		 */
		public function exists() {
			$filesystem = Util::get_filesystem();

			return $filesystem->exists( $this->path . '/' . self::FULLCHAIN_NAME ) && $filesystem->exists( $this->path . '/' . self::CERT_NAME ) && $filesystem->exists( $this->path . '/' . self::CHAIN_NAME );
		}

		/**
		 * Reads the certificate.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @return string|WP_Error The certificate string if successful or an error object otherwise.
		 */
		public function read() {
			$filesystem = Util::get_filesystem();

			$pem = $filesystem->get_contents( $this->path . '/' . self::CERT_NAME );
			if ( false === $pem ) {
				return new WP_Error( 'new_cert_cannot_read_cert', sprintf( __( 'Could not read certificate from file <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $this->path . '/' . self::CERT_NAME ) );
			}

			$begin = $this->get_cert_begin();
			$end = $this->get_cert_end();

			$pem = substr( $pem, strpos( $pem, $begin ) + strlen( $begin ) );
			$pem = substr( $pem, 0, strpos( $pem, $end ) );

			return $pem;
		}

		/**
		 * Generates a CSR for one or more domains.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param resource $key_resource The private key resource for the CSR.
		 * @param array    $domains      Array of domains the CSR should be created for.
		 * @param array    $dn           Array of CSR settings. It should have the array keys
		 *                               'ST' (for country), 'C' (for two-letter country code)
		 *                               and 'O' (for organization name).
		 * @return string|WP_Error The generated CSR if successful or an error object otherwise.
		 */
		public function generate_csr( $key_resource, $domains, $dn = array() ) {
			$filesystem = Util::get_filesystem();

			$status = Util::maybe_create_letsencrypt_certificates_dir();
			if ( is_wp_error( $status ) ) {
				return $status;
			}

			$san = implode( ',', array_map( array( $this, 'dnsify' ), $domains ) );

			$output = 'HOME = .
RANDFILE = $ENV::HOME/.rnd
[ req ]
default_bits = 2048
default_keyfile = privkey.pem
distinguished_name = req_distinguished_name
req_extensions = v3_req
[ req_distinguished_name ]
countryName = Country Name (2 letter code)
[ v3_req ]
basicConstraints = CA:FALSE
subjectAltName = ' . $san . '
keyUsage = nonRepudiation, digitalSignature, keyEncipherment';

			$tmp_config_path = tempnam( sys_get_temp_dir(), 'wpenc' );

			if ( false === $filesystem->put_contents( $tmp_config_path, $output ) ) {
				return new WP_Error( 'csr_cannot_write_tmp_file', __( 'Could not write CSR configuration to temporary file. Please check your filesystem permissions.', 'wp-encrypt' ) );
			}

			$dn = wp_parse_args( $dn, array(
				'CN'	=> $this->domain,
				'ST'	=> 'United States of America',
				'C'		=> 'US',
				'O'		=> 'Unknown',
			) );

			$dn = apply_filters( 'wp_encrypt_csr_dn', $dn, $this->domain );

			$csr = openssl_csr_new( $dn, $key_resource, array(
				'config'		=> $tmp_config_path,
				'digest_alg'	=> 'sha256',
			) );
			if ( false === $csr ) {
				$filesystem->delete( $tmp_config_path );
				return new WP_Error( 'csr_cannot_generate', sprintf( __( 'Could not generate CSR. Original error message: %s', 'wp-encrypt' ), openssl_error_string() ) );
			}

			if ( false === openssl_csr_export( $csr, $csr ) ) {
				$filesystem->delete( $tmp_config_path );
				return new WP_Error( 'csr_cannot_export', sprintf( __( 'Could not export CSR. Original error message: %s', 'wp-encrypt' ), openssl_error_string() ) );
			}

			$filesystem->delete( $tmp_config_path );

			if ( false === $filesystem->put_contents( $this->path . '/last.csr', $csr ) ) {
				return new WP_Error( 'csr_cannot_write', sprintf( __( 'Could not write CSR into file <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $this->path . '/last.csr' ) );
			}

			preg_match( '#REQUEST-----(.*)-----END#s', $csr, $matches );

			return trim( $matches[1] );
		}

		/**
		 * Prefixes a domain with `DNS:`.
		 *
		 * Used as a callback for `array_map()` to create a CSR.
		 *
		 * @since 1.0.0
		 * @access private
		 * @see WPENC\Core\Certificate::generate_csr()
		 *
		 * @param string $domain The domain to prefix.
		 * @return string The prefixed domain.
		 */
		private function dnsify( $domain ) {
			return 'DNS:' . $domain;
		}

		/**
		 * Parses a certificate so that it can be printed to a file.
		 *
		 * Used as callback for `array_map()` to write the certificate files.
		 *
		 * @since 1.0.0
		 * @access private
		 * @see WPENC\Core\Certificate::set()
		 *
		 * @param string $cert The certificate to parse.
		 * @return string The parsed certificate.
		 */
		private function parse_pem( $cert ) {
			$pem = chunk_split( base64_encode( $cert ), 64, "\n" );
			return $this->get_cert_begin() . "\n" . $pem . $this->get_cert_end() . "\n";
		}

		/**
		 * Returns the certificate begin flag.
		 *
		 * @since 1.0.0
		 * @access private
		 *
		 * @return string The certificate begin flag.
		 */
		private function get_cert_begin() {
			return '-----BEGIN CERTIFICATE-----';
		}

		/**
		 * Returns the certificate end flag.
		 *
		 * @since 1.0.0
		 * @access private
		 *
		 * @return string The certificate end flag.
		 */
		private function get_cert_end() {
			return '-----END CERTIFICATE-----';
		}
	}
}
