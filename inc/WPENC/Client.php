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

		private $endpoint_url = null;

		private $license_url = null;

		private $domain = null;

		private $certificates_dir_path = null;

		private $certificates_dir_url = null;

		private $last_response_code = null;

		private $last_response_header = null;

		/**
		 * Class constructor.
		 *
		 * @since 0.5.0
		 */
		public function __construct( $domain ) {
			$this->endpoint_url = 'https://acme-v01.api.letsencrypt.org';
			$this->license_url = 'https://letsencrypt.org/documents/LE-SA-v1.0.1-July-27-2015.pdf';
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

			return $this->new_reg();
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

			$response = $this->new_authz();
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

			$result = $this->new_cert();
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

			$pem = Util::base64_url_encode( base64_decode( $pem ) );

			$result = $this->revoke_cert( $pem );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( 200 !== $this->get_last_code() ) {
				return new WP_Error( 'revoke_cert_invalid_response_code', __( 'Invalid response code for revoke certificate request.', 'wp-encrypt' ) );
			}

			return true;
		}

		public function new_authz() {
			return $this->signed_request( 'acme/new-authz', array(
				'resource'		=> 'new-authz',
				'identifier'	=> array(
					'type'			=> 'dns',
					'value'			=> $this->domain,
				),
			) );
		}

		public function new_cert() {
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

			return $this->signed_request( 'acme/new-cert', array(
				'resource'		=> 'new-cert',
				'csr'			=> $csr,
			) );
		}

		public function revoke_cert( $cert ) {
			return $this->signed_request( 'acme/revoke-cert', array(
				'certificate'	=> $cert,
			) );
		}

		public function challenge( $uri, $token, $data ) {
			return $this->signed_request( $uri, array(
				'resource'			=> 'challenge',
				'type'				=> 'http-01',
				'keyAuthorization'	=> $data,
				'token'				=> $token,
			) );
		}

		public function new_reg() {
			return $this->signed_request( 'acme/new-reg', array(
				'resource'		=> 'new-reg',
				'agreement'		=> $this->license_url,
			) );
		}

		public function directory() {
			return $this->request( 'directory', 'GET' );
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

			return trim( Util::base64_url_encode( base64_decode( $matches[1] ) ) );
		}

		private function generate_key( $mode = 'account' ) {
			if ( 'account' === $mode ) {
				$mode = '_' . $mode;
			} elseif ( 'domain' === $mode ) {
				$mode = $this->domain;
			}
			$path = $this->certificates_dir_path . '/' . $mode;

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

			$details = $this->get_private_key_details( $res );
			if ( is_wp_error( $details ) ) {
				return $details;
			}

			if ( ! is_dir( $path ) ) {
				@mkdir( $path, 0700, true );
				if ( ! is_dir( $path ) ) {
					return new WP_Error( 'private_key_cannot_create_dir', sprintf( __( 'Could not create directory <code>%s</code> for private key. Please check your filesystem permissions.', 'wp-encrypt' ), $path ) );
				}
			}

			if ( false === file_put_contents( $path . '/private.pem', $private_key ) ) {
				return new WP_Error( 'private_key_cannot_write', sprintf( __( 'Could not write private key into file <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $path . '/private.pem' ) );
			}

			if ( false === file_put_contents( $path . '/public.pem', $details['key'] ) ) {
				return new WP_Error( 'public_key_cannot_write', sprintf( __( 'Could not write public key into file <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $path . '/public.pem' ) );
			}

			return $details['key'];
		}

		private function read_private_key( $mode = 'account' ) {
			if ( 'account' === $mode ) {
				$mode = '_' . $mode;
			} elseif ( 'domain' === $mode ) {
				$mode = $this->domain;
			}
			$path = $this->certificates_dir_path . '/' . $mode . '/private.pem';
			if ( ! file_exists( $path ) ) {
				return new WP_Error( 'private_key_missing', __( 'Missing private key.', 'wp-encrypt' ) );
			}

			$private_key = openssl_pkey_get_private( 'file://' . $path );
			if ( false === $private_key ) {
				return new WP_Error( 'private_key_invalid', sprintf( __( 'Invalid private key. Original error message: %s', 'wp-encrypt' ), openssl_error_string() ) );
			}
			return $private_key;
		}

		private function private_key_exists( $mode = 'account' ) {
			if ( 'account' === $mode ) {
				$mode = '_' . $mode;
			} elseif ( 'domain' === $mode ) {
				$mode = $this->domain;
			}
			return file_exists( $this->certificates_dir_path . '/' . $mode . '/private.pem' );
		}

		private function get_private_key_details( $private_key ) {
			$details = openssl_pkey_get_details( $private_key );
			if ( false === $details ) {
				return new WP_Error( 'private_key_details_invalid', sprintf( __( 'Could not retrieve details from private key. Original error message: %s', 'wp-encrypt' ), openssl_error_string() ) );
			}
			return $details;
		}

		private function signed_request( $endpoint, $data = null ) {
			$private_key = $this->read_private_key( 'account' );
			if ( is_wp_error( $private_key ) ) {
				return $private_key;
			}

			$details = $this->get_private_key_details( $private_key );
			if ( is_wp_error( $details ) ) {
				return $details;
			}

			$protected = $header = array(
				'alg'	=> 'RS256',
				'jwk'	=> array(
					'kty'	=> 'RSA',
					'n'		=> Util::base64_url_encode( $details['rsa']['n'] ),
					'e'		=> Util::base64_url_encode( $details['rsa']['e'] ),
				),
			);

			if ( null !== ( $nonce = $this->get_last_nonce() ) ) {
				$protected['nonce'] = $nonce;
			} else {
				$this->directory();
				if ( null !== ( $nonce = $this->get_last_nonce() ) ) {
					$protected['nonce'] = $nonce;
				}
			}

			if ( ! isset( $protected['nonce'] ) ) {
				return new WP_Error( 'signed_request_no_nonce', __( 'No nonce available for a signed request.', 'wp-encrypt' ) );
			}

			$data64 = Util::base64_url_encode( str_replace( '\\/', '/', json_encode( $data ) ) );
			$protected64 = Util::base64_url_encode( json_encode( $protected ) );

			$sign_status = openssl_sign( $protected64 . '.' . $data64, $signature, $private_key, 'SHA256' );
			if ( false === $status ) {
				return new WP_Error( 'private_key_cannot_sign', sprintf( __( 'Could not sign request with private key. Original error message: %s', 'wp-encrypt' ), openssl_error_string() ) );
			}

			$signature64 = Util::base64_url_encode( $signature );

			return $this->request( $endpoint, 'POST', array(
				'header'	=> $header,
				'protected'	=> $protected64,
				'payload'	=> $data64,
				'signature'	=> $signature64,
			) );
		}

		private function request( $endpoint, $method = 'GET', $data = null ) {
			if ( is_array( $data ) ) {
				$data = json_encode( $data );
			}

			$args = array(
				'method'	=> strtoupper( $method ),
				'headers'	=> array(
					'Accept'		=> 'application/json',
					'Content-Type'	=> 'application/json; charset=' . get_option( 'blog_charset' ),
				),
				'body'		=> $data,
			);

			$url = $endpoint;
			if ( false === strpos( $url, 'http://' ) && false === strpos( $url, 'https://' ) ) {
				$url = $this->endpoint_url . '/' . trim( $endpoint, '/' );
			}

			$response = wp_remote_request( $url, $args );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$this->last_response_code = wp_remote_retrieve_response_code( $response );
			$this->last_response_header = wp_remote_retrieve_header( $response );

			$body = wp_remote_retrieve_body( $response );

			$response = json_decode( $body, true );

			return null === $response ? $body : $response;
		}

		private function parse_pem( $response ) {
			$pem = chunk_split( base64_encode( $response ), 64, "\n" );
			return "-----BEGIN CERTIFICATE-----\n" . $pem . "-----END CERTIFICATE-----\n";
		}

		private function get_last_nonce() {
			if ( null !== $this->last_response_header && preg_match( '#Replay\-Nonce: (.+)#i', $this->last_response_header, $matches ) ) {
				return trim( $matches[1] );
			}

			return null;
		}

		private function get_last_location() {
			if ( null !== $this->last_response_header && preg_match( '#Location: (.+)#i', $this->last_response_header, $matches ) ) {
				return trim( $matches[1] );
			}

			return null;
		}

		private function get_last_code() {
			if ( null !== $this->last_response_code ) {
				return $this->last_response_code;
			}

			return null;
		}

		private function get_last_links() {
			if ( null !== $this->last_response_header && preg_match_all( '#Link: <(.+)>;rel="up"#', $this->last_response_header, $matches ) ) {
				return $matches[1];
			}

			return array();
		}
	}
}
