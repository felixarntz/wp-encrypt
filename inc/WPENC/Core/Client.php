<?php
/**
 * WPENC\Core\Client class
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

if ( ! class_exists( 'WPENC\Core\Client' ) ) {
	/**
	 * This class contains core methods to communicate with the Let's Encrypt API.
	 *
	 * @since 1.0.0
	 */
	final class Client {
		/**
		 * The API URL for Let's Encrypt.
		 *
		 * @since 1.0.0
		 */
		const API_URL = 'https://acme-v01.api.letsencrypt.org';

		/**
		 * The API endpoint to register an account.
		 *
		 * @since 1.0.0
		 */
		const ENDPOINT_REGISTER = 'acme/new-reg';

		/**
		 * The API endpoint to generate challenges.
		 *
		 * @since 1.0.0
		 */
		const ENDPOINT_AUTH = 'acme/new-authz';

		/**
		 * The API endpoint to generate a certificate.
		 *
		 * @since 1.0.0
		 */
		const ENDPOINT_NEW = 'acme/new-cert';

		/**
		 * The API endpoint to revoke a certificate.
		 *
		 * @since 1.0.0
		 */
		const ENDPOINT_REVOKE = 'acme/revoke-cert';

		/**
		 * The API endpoint overview.
		 *
		 * @since 1.0.0
		 */
		const ENDPOINT_DIRECTORY = 'directory';

		/**
		 * The resource to pass to the account registering endpoint.
		 *
		 * @since 1.0.0
		 */
		const RESOURCE_REGISTER = 'new-reg';

		/**
		 * The resource to pass to the challenges generating endpoint.
		 *
		 * @since 1.0.0
		 */
		const RESOURCE_AUTH = 'new-authz';

		/**
		 * The resource to pass to the challenges validating endpoint.
		 *
		 * @since 1.0.0
		 */
		const RESOURCE_CHALLENGE = 'challenge';

		/**
		 * The resource to pass to the certificate generating endpoint.
		 *
		 * @since 1.0.0
		 */
		const RESOURCE_NEW = 'new-cert';

		/**
		 * Singleton instance.
		 *
		 * @since 1.0.0
		 * @access private
		 * @static
		 * @var WPENC\Core\Client
		 */
		private static $instance = null;

		/**
		 * Singleton method.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @return WPENC\Core\Client The class instance.
		 */
		public static function get() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * The response code received in the last response.
		 *
		 * @since 1.0.0
		 * @access private
		 * @var string
		 */
		private $last_response_code = null;

		/**
		 * The response header received in the last response.
		 *
		 * @since 1.0.0
		 * @access private
		 * @var string
		 */
		private $last_response_header = null;

		/**
		 * The nonce received in the last response.
		 *
		 * @since 1.0.0
		 * @access private
		 * @var string
		 */
		private $last_nonce = null;

		/**
		 * The location received in the last response.
		 *
		 * @since 1.0.0
		 * @access private
		 * @var string
		 */
		private $last_location = null;

		/**
		 * The links received in the last response.
		 *
		 * @since 1.0.0
		 * @access private
		 * @var string
		 */
		private $last_links = null;

		/**
		 * Array of license document URLs.
		 *
		 * @since 1.0.0
		 * @access private
		 * @var array
		 */
		private $licenses = array();

		/**
		 * Constructor.
		 *
		 * Sets the license document URLs.
		 *
		 * @since 1.0.0
		 * @access private
		 */
		private function __construct() {
			// Newer licenses must come first.
			$this->licenses = array(
				'2017-11-15'    => 'https://letsencrypt.org/documents/LE-SA-v1.2-November-15-2017.pdf',
				'2016-08-01'	=> 'https://letsencrypt.org/documents/LE-SA-v1.1.1-August-1-2016.pdf',
				'2015-07-27'	=> 'https://letsencrypt.org/documents/LE-SA-v1.0.1-July-27-2015.pdf',
			);
		}

		/**
		 * Returns the URL to the current license document.
		 *
		 * @since 1.0.0
		 * @access private
		 * @return string URL to the license document.
		 */
		public function get_license_url() {
			$now = current_time( 'timestamp' );

			foreach ( $this->licenses as $date => $url ) {
				if ( $now >= strtotime( $date ) ) {
					return $url;
				}
			}

			return '';
		}

		/**
		 * Registers an account with Let's Encrypt.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @return array|WP_Error The response array if successful or an error object otherwise.
		 */
		public function register() {
			return $this->signed_request( self::ENDPOINT_REGISTER, array(
				'resource'		=> self::RESOURCE_REGISTER,
				'agreement'		=> $this->get_license_url(),
			) );
		}

		/**
		 * Generates challenges for a domain with Let's Encrypt.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param string $domain The domain to receive challenges for.
		 * @return array|WP_Error The response array if successful or an error object otherwise.
		 */
		public function auth( $domain ) {
			return $this->signed_request( self::ENDPOINT_AUTH, array(
				'resource'		=> self::RESOURCE_AUTH,
				'identifier'	=> array(
					'type'			=> 'dns',
					'value'			=> $domain,
				),
			) );
		}

		/**
		 * Validates a domain challenge with Let's Encrypt.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param string $uri The URI to check.
		 * @param string $token The token for the challenge.
		 * @param string $data Key authorization data.
		 * @return array|WP_Error The response array if successful or an error object otherwise.
		 */
		public function challenge( $uri, $token, $data ) {
			return $this->signed_request( $uri, array(
				'resource'			=> self::RESOURCE_CHALLENGE,
				'type'				=> 'http-01',
				'keyAuthorization'	=> $data,
				'token'				=> $token,
			) );
		}

		/**
		 * Generates a certificate with Let's Encrypt.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param string $csr The CSR for the certificate.
		 * @return string|WP_Error The certificate if successful or an error object otherwise.
		 */
		public function generate( $csr ) {
			return $this->signed_request( self::ENDPOINT_NEW, array(
				'resource'		=> self::RESOURCE_NEW,
				'csr'			=> Util::base64_url_encode( base64_decode( $csr ) ),
			) );
		}

		/**
		 * Revokes a certificate with Let's Encrypt.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param string $cert The certificate to revoke.
		 * @return array|WP_Error The response array if successful or an error object otherwise.
		 */
		public function revoke( $cert ) {
			return $this->signed_request( self::ENDPOINT_REVOKE, array(
				'certificate'	=> Util::base64_url_encode( base64_decode( $cert ) ),
			) );
		}

		/**
		 * Lists the directory overview and general entry point.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @return array|WP_Error The response array if successful or an error object otherwise.
		 */
		public function directory() {
			return $this->request( 'directory', 'GET' );
		}

		/**
		 * Sends a signed request to the Let's Encrypt API.
		 *
		 * All requests except the directory request go through this method.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param string $endpoint The endpoint to send a request to.
		 * @param array  $data     Data to send with the request.
		 * @return string|array|WP_Error Either a JSON-decoded array response, a plain text response or an error object.
		 */
		public function signed_request( $endpoint, $data = null ) {
			$account_keypair = AccountKeyPair::get();

			$account_key_resource = $account_keypair->read_private();
			if ( is_wp_error( $account_key_resource ) ) {
				return $account_key_resource;
			}

			$account_key_details = $account_keypair->get_private_details();
			if ( is_wp_error( $account_key_details ) ) {
				return $account_key_details;
			}

			$protected = $header = array(
				'alg'	=> 'RS256',
				'jwk'	=> array(
					'kty'	=> 'RSA',
					'n'		=> Util::base64_url_encode( $account_key_details['rsa']['n'] ),
					'e'		=> Util::base64_url_encode( $account_key_details['rsa']['e'] ),
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

			$sign_status = openssl_sign( $protected64 . '.' . $data64, $signature, $account_key_resource, 'SHA256' );
			if ( false === $sign_status ) {
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

		/**
		 * Sends a regular request to the Let's Encrypt API.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @param string $endpoint The endpoint to send a request to.
		 * @param string $method   The request method ('GET' or 'POST').
		 * @param array  $data     Data to send with the request.
		 * @return string|array|WP_Error Either a JSON-decoded array response, a plain text response or an error object.
		 */
		public function request( $endpoint, $method = 'GET', $data = null ) {
			if ( is_array( $data ) ) {
				$data = json_encode( $data );
			}

			$args = array(
				'method'	=> strtoupper( $method ),
				'timeout'	=> 10,
				'headers'	=> array(
					'Accept'		=> 'application/json',
					'Content-Type'	=> 'application/json; charset=' . get_option( 'blog_charset' ),
				),
				'body'		=> $data,
			);

			$url = $endpoint;
			if ( false === strpos( $url, 'http://' ) && false === strpos( $url, 'https://' ) ) {
				$url = self::API_URL . '/' . ltrim( $endpoint, '/' );
			}

			$response = wp_remote_request( $url, $args );
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$this->last_response_code = wp_remote_retrieve_response_code( $response );
			$this->last_nonce = wp_remote_retrieve_header( $response, 'replay-nonce' );
			$this->last_location = wp_remote_retrieve_header( $response, 'location' );
			$this->last_links = wp_remote_retrieve_header( $response, 'link' );

			$body = wp_remote_retrieve_body( $response );

			$response = json_decode( $body, true );

			return null === $response ? $body : $response;
		}

		/**
		 * Returns the last response code.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @return string The last response code.
		 */
		public function get_last_code() {
			if ( $this->last_response_code ) {
				return $this->last_response_code;
			}

			return null;
		}

		/**
		 * Returns the last response nonce.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @return string The last response nonce.
		 */
		public function get_last_nonce() {
			if ( $this->last_nonce ) {
				return $this->last_nonce;
			}

			return null;
		}

		/**
		 * Returns the last response location.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @return string The last response location.
		 */
		public function get_last_location() {
			if ( $this->last_location ) {
				return $this->last_location;
			}

			return null;
		}

		/**
		 * Returns the last response links.
		 *
		 * @since 1.0.0
		 * @access public
		 *
		 * @return array The last response links.
		 */
		public function get_last_links() {
			if ( $this->last_links && preg_match_all( '#<(.*?)>;rel="up"#x', $this->last_links, $matches ) ) {
				return $matches[1];
			}

			return array();
		}
	}
}
