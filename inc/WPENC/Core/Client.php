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

if ( ! class_exists( 'WPENC\Core\Client' ) ) {
	/**
	 * This class contains core methods to interact with the Let's Encrypt API.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	final class Client {
		const API_URL = 'https://acme-v01.api.letsencrypt.org';

		const ENDPOINT_REGISTER = 'acme/new-reg';
		const ENDPOINT_AUTH = 'acme/new-authz';
		const ENDPOINT_NEW = 'acme/new-cert';
		const ENDPOINT_REVOKE = 'acme/revoke-cert';
		const ENDPOINT_DIRECTORY = 'directory';

		const RESOURCE_REGISTER = 'new-reg';
		const RESOURCE_AUTH = 'new-authz';
		const RESOURCE_CHALLENGE = 'challenge';
		const RESOURCE_NEW = 'new-cert';

		const LICENSE_URL = 'https://letsencrypt.org/documents/LE-SA-v1.0.1-July-27-2015.pdf';

		private static $instance = null;

		public static function get() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private $last_response_code = null;

		private $last_response_header = null;

		protected function __construct() {
		}

		public function register() {
			return $this->signed_request( self::ENDPOINT_REGISTER, array(
				'resource'		=> self::RESOURCE_REGISTER,
				'agreement'		=> self::LICENSE_URL,
			) );
		}

		public function auth( $domain ) {
			return $this->signed_request( self::ENDPOINT_AUTH, array(
				'resource'		=> self::RESOURCE_AUTH,
				'identifier'	=> array(
					'type'			=> 'dns',
					'value'			=> $domain,
				),
			) );
		}

		public function challenge( $uri, $token, $data ) {
			return $this->signed_request( $uri, array(
				'resource'			=> self::RESOURCE_CHALLENGE,
				'type'				=> 'http-01',
				'keyAuthorization'	=> $data,
				'token'				=> $token,
			) );
		}

		public function generate( $csr ) {
			return $this->signed_request( self::ENDPOINT_NEW, array(
				'resource'		=> self::RESOURCE_NEW,
				'csr'			=> Util::base64_url_encode( base64_decode( $csr ) ),
			) );
		}

		public function revoke( $cert ) {
			return $this->signed_request( self::ENDPOINT_REVOKE, array(
				'certificate'	=> Util::base64_url_encode( base64_decode( $cert ) ),
			) );
		}

		public function directory() {
			return $this->request( 'directory', 'GET' );
		}

		private function signed_request( $endpoint, $data = null ) {
			$keypair = AccountKeyPair::get();

			$private_key = $keypair->read_private();
			if ( is_wp_error( $private_key ) ) {
				return $private_key;
			}

			$details = $keypair->get_private_details();
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
				$url = self::API_URL . '/' . $endpoint;
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
