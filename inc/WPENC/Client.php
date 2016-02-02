<?php
/**
 * @package WPENC
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPENC;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPENC\Client' ) ) {
	/**
	 * This class contains core methods to interact with the Let's Encrypt API.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	final class Client {

		private $domain = null;

		private $certificates_dir_path = null;

		private $certificates_dir_url = null;

		/**
		 * Class constructor.
		 *
		 * @since 0.5.0
		 */
		public function __construct( $domain ) {
			$this->domain = $domain;
			$this->certificates_dir_path = Util::get_letsecrypt_dir_path();
			$this->certificates_dir_url = Util::get_letsecrypt_dir_url();
		}

		public function register_account() {
			if ( ! $this->private_key_exists( 'account' ) ) {
				$public_key = $this->generate_key( 'account' );
				if ( is_wp_error( $public_key ) ) {
					return $public_key;
				}
			}

			return $this->register();
		}

		public function sign_domain() {
			$private_account_key = $this->read_private_key( 'account' );
			if ( is_wp_error( $private_account_key ) ) {
				return $private_account_key;
			}

			$private_account_key_details = $this->get_private_key_details( $private_account_key );
			if ( is_wp_error( $private_account_key_details ) ) {
				return $private_account_key_details;
			}

			$response = $this->auth( $this->domain );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$challenge = array_reduce( $response['challenges'], function( $v, $w ) {
				if ( $v ) {
					return $v;
				}
				if ( 'http-01' === $w['type'] ) {
					return $w;
				}
				return false;
			});
			if ( ! $challenge ) {
				return new WP_Error( 'no_challenge_available', sprintf( __( 'No HTTP challenge available. Original response: %s', 'wp-encrypt' ), json_encode( $response ) ) );
			}

			$location = $this->get_last_location();

			$directory = $this->certificates_dir_path . '/.well-known/acme-challenge';
			$token_path = $directory . '/' . $challenge['token'];

			if ( ! is_dir( $directory ) && ! @mkdir( $directory, 0755, true ) ) {
				return new WP_Error( 'challenge_cannot_create_dir', sprintf( __( 'Could not create challenge directory <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $directory ) );
			}

			$header = array(
				'e'		=> Util::base64_url_encode( $private_account_key_details['rsa']['e'] ),
				'kty'	=> 'RSA',
				'n'		=> Util::base64_url_encode( $private_account_key_details['rsa']['n'] ),
			);
			$data = $challenge['token'] . '.' . Util::base64_url_encode( hash( 'sha256', json_encode( $header ), true ) );

			if ( false === file_put_contents( $token_path, $data ) ) {
				return new WP_Error( 'challenge_cannot_write_file', sprintf( __( 'Could not write challenge to file <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $token_path ) );
			}
			chmod( $token_path, 0644 );

			$uri = $this->certificates_dir_url . '/.well-known/acme-challenge/' . $challenge['token'];

			if ( $data !== trim( @file_get_contents( $uri ) ) ) {
				return new WP_Error( 'challenge_self_failed', __( 'Challenge self check failed.', 'wp-encrypt' ) );
			}

			$result = $this->challenge( $uri, $challenge['token'], $data );

			$done = false;

			do {
				if ( empty( $result['status'] ) || 'invalid' === $result['status'] ) {
					return new WP_Error( 'challenge_remote_failed', __( 'Challenge remote check failed.', 'wp-encrypt' ) );
				}

				$done = 'pending' !== $result['status'];
				if ( ! $done ) {
					sleep( 1 );
				}

				$result = $this->request( $location, 'GET' );
			} while ( ! $done );

			@unlink( $token_path );

			if ( ! $this->private_key_exists( $this->domain ) ) {
				$public_key = $this->generate_key( $this->domain );
				if ( is_wp_error( $public_key ) ) {
					return $public_key;
				}
			}

			$private_key = $this->read_private_key( $this->domain );
			if ( is_wp_error( $private_key ) ) {
				$public_key = $this->generate_key( $this->domain );
				if ( is_wp_error( $public_key ) ) {
					return $public_key;
				}
				$private_key = $this->read_private_key( $this->domain );
			}

			$csr = $this->generate_csr( $private_key );
			if ( is_wp_error( $csr ) ) {
				return $csr;
			}

			$result = $this->generate( $csr );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( 201 !== $this->get_last_code() ) {
				return new WP_Error( 'new_cert_invalid_response_code', __( 'Invalid response code for new certificate request.', 'wp-encrypt' ) );
			}

			$location = $this->get_last_location();

			$certificates = array();

			$done = false;

			do {
				$result = $this->request( $location, 'GET' );
				if ( is_wp_error( $result ) ) {
					return $result;
				}

				if ( 202 === $this->get_last_code() ) {
					sleep( 1 );
				} elseif ( 200 === $this->get_last_code() ) {
					$certificates[] = $this->parse_pem( $result );
					foreach ( $this->get_last_links() as $link ) {
						$result = $this->request( $link, 'GET' );
						if ( is_wp_error( $result ) ) {
							return $result;
						}
						$certificates[] = $this->parse_pem( $result );
					}
					$done = true;
				} else {
					return new WP_Error( 'new_cert_invalid_response_code', __( 'Invalid response code for new certificate request.', 'wp-encrypt' ) );
				}
			} while ( ! $done );

			if ( 0 === count( $certificates ) ) {
				return new WP_Error( 'new_cert_fail', __( 'No certificates generated.', 'wp-encrypt' ) );
			}

			$domain_path = $this->certificates_dir_path . '/' . $this->domain;

			if ( false === file_put_contents( $domain_path . '/fullchain.pem', implode( "\n", $certificates ) ) ) {
				return new WP_Error( 'new_cert_cannot_write_fullchain', sprintf( __( 'Could not write certificates to file <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $domain_path . '/fullchain.pem' ) );
			}
			if ( false === file_put_contents( $domain_path . '/cert.pem', array_shift( $certificates ) ) ) {
				return new WP_Error( 'new_cert_cannot_write_cert', sprintf( __( 'Could not write certificate to file <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $domain_path . '/cert.pem' ) );
			}
			if ( false === file_put_contents( $domain_path . '/chain.pem', implode( "\n", $certificates ) ) ) {
				return new WP_Error( 'new_cert_cannot_write_chain', sprintf( __( 'Could not write certificates to file <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $domain_path . '/chain.pem' ) );
			}

			return true;
		}

		public function unsign_domain() {
			$domain_path = $this->certificates_dir_path . '/' . $this->domain;
			if ( ! file_exists( $domain_path . '/cert.pem' ) ) {
				return new WP_Error( 'cert_not_exist', sprintf( __( 'The certificate <code>%s</code> does not exist.', 'wp-encrypt' ), $domain_path . '/cert.pem' ) );
			}

			$pem = file_get_contents( $domain_path . '/cert.pem' );

			$begin = 'CERTIFICATE-----';
			$end = '-----END';

			$pem = substr( $pem, strpos( $pem, $begin ) + strlen( $begin ) );
			$pem = substr( $pem, 0, strpos( $pem, $end ) );

			$result = $this->revoke( $pem );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( 200 !== $this->get_last_code() ) {
				return new WP_Error( 'revoke_cert_invalid_response_code', __( 'Invalid response code for revoke certificate request.', 'wp-encrypt' ) );
			}

			return true;
		}

		private function generate_csr( $private_key ) {
			$san = 'DNS:' . $this->domain;

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

			$locale = get_locale()

			$dn = apply_filters( 'wp_encrypt_csr_dn', array(
				'CN'	=> $this->domain,
				'ST'	=> Util::get_option( 'country_name' ),
				'C'		=> Util::get_option( 'country_code' ),
				'O'		=> Util::get_option( 'organization' ),
			), $this->domain );

			$csr = openssl_csr_new( $dn, $private_key, array(
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

			if ( false === file_put_contents( $this->certificates_dir_path . '/' . $this->domain . '/last.csr', $csr ) ) {
				return new WP_Error( 'csr_cannot_write', sprintf( __( 'Could not write CSR into file <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $this->certificates_dir_path . '/' . $this->domain . '/last.csr' ) );
			}

			preg_match( '#REQUEST-----(.*)-----END#s', $csr, $matches );

			return trim( $matches[1] );
		}

		private function parse_pem( $response ) {
			$pem = chunk_split( base64_encode( $response ), 64, "\n" );
			return "-----BEGIN CERTIFICATE-----\n" . $pem . "-----END CERTIFICATE-----\n";
		}
	}
}
