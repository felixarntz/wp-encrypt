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

if ( ! class_exists( 'WPENC\Core\Certificate' ) ) {
	/**
	 * This class represents a single certificate.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	final class Certificate {
		const FULLCHAIN_NAME = 'fullchain.pem';
		const CERT_NAME = 'cert.pem';
		const CHAIN_NAME = 'chain.pem';

		private static $instances = array();

		public static function get( $domain ) {
			if ( ! isset( self::$instances[ $domain ] ) ) {
				self::$instances[ $domain ] = new self( $domain );
			}
			return self::$instances[ $domain ];
		}

		private $domain = null;
		private $path = null;

		private function __construct( $domain ) {
			$this->domain = $domain;
			$this->path = Util::get_letsencrypt_certificates_dir_path() . '/' . $domain;
		}

		public function exists() {
			return file_exists( $this->path . '/' . self::FULLCHAIN_NAME ) && file_exists( $this->path . '/' . self::CERT_NAME ) && file_exists( $this->path . '/' . self::CHAIN_NAME );
		}

		public function set( $certs ) {
			$certs = array_map( array( $this, 'parse_pem' ), $certs );
			if ( false === @file_put_contents( $this->path . '/' . self::FULLCHAIN_NAME, implode( "\n", $certs ) ) ) {
				return new WP_Error( 'new_cert_cannot_write_fullchain', sprintf( __( 'Could not write certificates to file <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $this->path . '/' . self::FULLCHAIN_NAME ) );
			}
			if ( false === @file_put_contents( $this->path . '/' . self::CERT_NAME, array_shift( $certs ) ) ) {
				return new WP_Error( 'new_cert_cannot_write_cert', sprintf( __( 'Could not write certificate to file <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $this->path . '/' . self::CERT_NAME ) );
			}
			if ( false === @file_put_contents( $this->path . '/' . self::CHAIN_NAME, implode( "\n", $certs ) ) ) {
				return new WP_Error( 'new_cert_cannot_write_chain', sprintf( __( 'Could not write certificates to file <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $this->path . '/' . self::CHAIN_NAME ) );
			}
			return true;
		}

		public function read() {
			$pem = @file_get_contents( $this->path . '/' . self::CERT_NAME );
			if ( false === $pem ) {
				return new WP_Error( 'new_cert_cannot_read_cert', sprintf( __( 'Could not read certificate from file <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $this->path . '/' . self::CERT_NAME ) );
			}

			$begin = $this->get_cert_begin();
			$end = $this->get_cert_end();

			$pem = substr( $pem, strpos( $pem, $begin ) + strlen( $begin ) );
			$pem = substr( $pem, 0, strpos( $pem, $end ) );

			return $pem;
		}

		public function generate_csr( $key_resource, $domains, $dn = array() ) {
			$san = implode( ',', array_map( array( $this, 'dnsify' ), $domains ) );

			$tmp_conf = tmpfile();
			if ( false === $tmp_conf ) {
				return new WP_Error( 'csr_cannot_generate_tmp_file', __( 'Could not generate temporary file to generate CSR. Please check your filesystem permissions.', 'wp-encrypt' ) );
			}

			$tmp_conf_meta = stream_get_meta_data( $tmp_conf );
			$tmp_conf_path = $tmp_conf_meta['uri'];

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

			if ( false === fwrite( $tmp_conf, $output ) ) {
				return new WP_Error( 'csr_cannot_write_tmp_file', __( 'Could not write to temporary file to generate CSR. Please check your filesystem permissions.', 'wp-encrypt' ) );
			}

			$dn = wp_parse_args( $dn, array(
				'CN'	=> $this->domain,
				'ST'	=> 'United States of America',
				'C'		=> 'US',
				'O'		=> 'Unknown',
			) );

			$dn = apply_filters( 'wp_encrypt_csr_dn', $dn, $this->domain );

			$csr = openssl_csr_new( $dn, $key_resource, array(
				'config'		=> $tmp_conf_path,
				'digest_alg'	=> 'sha256',
			) );
			if ( false === $csr ) {
				return new WP_Error( 'csr_cannot_generate', sprintf( __( 'Could not generate CSR. Original error message: %s', 'wp-encrypt' ), openssl_error_string() ) );
			}

			if ( false === openssl_csr_export( $csr, $csr ) ) {
				return new WP_Error( 'csr_cannot_export', sprintf( __( 'Could not export CSR. Original error message: %s', 'wp-encrypt' ), openssl_error_string() ) );
			}

			fclose( $tmp_conf );

			if ( false === @file_put_contents( $this->certificates_dir_path . '/' . $this->domain . '/last.csr', $csr ) ) {
				return new WP_Error( 'csr_cannot_write', sprintf( __( 'Could not write CSR into file <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $this->certificates_dir_path . '/' . $this->domain . '/last.csr' ) );
			}

			preg_match( '#REQUEST-----(.*)-----END#s', $csr, $matches );

			return trim( $matches[1] );
		}

		private function dnsify( $domain ) {
			return 'DNS:' . $domain;
		}

		private function parse_pem( $cert ) {
			$pem = chunk_split( base64_encode( $cert ), 64, "\n" );
			return $this->get_cert_begin() . "\n" . $pem . $this->get_cert_end() . "\n";
		}

		private function get_cert_begin() {
			return '-----BEGIN CERTIFICATE-----';
		}

		private function get_cert_end() {
			return '-----END CERTIFICATE-----';
		}
	}
}
