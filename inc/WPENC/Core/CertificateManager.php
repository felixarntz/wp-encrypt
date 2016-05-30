<?php
/**
 * WPENC\Core\CertificateManager class
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

if ( ! class_exists( 'WPENC\Core\CertificateManager' ) ) {
	/**
	 * This class is the global access point that manages certificates.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	final class CertificateManager {
		private static $instance = null;

		public static function get() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {}

		public function register_account() {
			$account_keypair = AccountKeyPair::get();
			if ( ! $account_keypair->exists() ) {
				$status = $account_keypair->generate();
				if ( is_wp_error( $status ) ) {
					return $status;
				}
			}

			$response = Client::get()->register();
			if ( is_wp_error( $response ) ) {
				return $response;
			}

			if ( isset( $response['status'] ) && 200 !== absint( $response['status'] ) ) {
				$code = 'letsencrypt_error';
				$message = __( 'Unknown error', 'wp-encrypt' );
				if ( isset( $response['type'] ) ) {
					$code = 'letsencrypt_' . str_replace( ':', '_', $response['type'] );
				}
				if ( isset( $response['detail'] ) ) {
					$message = $response['detail'];
				}
				return new WP_Error( $code, $message );
			}

			return $response;
		}

		public function generate_certificate( $domain, $addon_domains = array(), $dn_args = array() ) {
			$account_keypair = AccountKeyPair::get();

			$account_key_details = $account_keypair->get_private_details();
			if ( is_wp_error( $account_key_details ) ) {
				return $account_key_details;
			}

			$all_domains = Util::get_all_domains( $domain, $addon_domains );

			foreach ( $all_domains as $_domain ) {
				$status = Challenge::validate( $_domain, $account_key_details );
				if ( is_wp_error( $status ) ) {
					return $status;
				}
			}

			$domain_keypair = DomainKeyPair::get( $domain );
			if ( ! $domain_keypair->exists() ) {
				$status = $domain_keypair->generate();
				if ( is_wp_error( $status ) ) {
					return $status;
				}
			}

			$domain_key_resource = $domain_keypair->read_private();
			if ( is_wp_error( $domain_key_resource ) ) {
				return $domain_key_resource;
			}

			$certificate = Certificate::get( $domain );

			$csr = $certificate->generate_csr( $domain_key_resource, $all_domains, $dn_args );
			if ( is_wp_error( $csr ) ) {
				return $csr;
			}

			$client = Client::get();

			$result = $client->generate( $csr );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( 201 !== $client->get_last_code() ) {
				return new WP_Error( 'new_cert_invalid_response_code', __( 'Invalid response code for new certificate request.', 'wp-encrypt' ) );
			}

			$location = $client->get_last_location();

			$certs = array();

			$done = false;

			do {
				$result = $client->request( $location, 'GET' );
				if ( is_wp_error( $result ) ) {
					return $result;
				}

				if ( 202 === $client->get_last_code() ) {
					sleep( 1 );
				} elseif ( 200 === $client->get_last_code() ) {
					$certs[] = $result;
					foreach ( $client->get_last_links() as $link ) {
						$result = $client->request( $link, 'GET' );
						if ( is_wp_error( $result ) ) {
							return $result;
						}
						$certs[] = $result;
					}
					$done = true;
				} else {
					return new WP_Error( 'new_cert_invalid_response_code', __( 'Invalid response code for new certificate request.', 'wp-encrypt' ) );
				}
			} while ( ! $done );

			if ( 0 === count( $certs ) ) {
				return new WP_Error( 'new_cert_fail', __( 'No certificates generated.', 'wp-encrypt' ) );
			}

			$status = $certificate->set( $certs );
			if ( is_wp_error( $status ) ) {
				return $status;
			}

			return array(
				'domains'	=> $all_domains,
			);
		}

		public function revoke_certificate( $domain ) {
			$certificate = Certificate::get( $domain );
			if ( ! $certificate->exists() ) {
				return new WP_Error( 'cert_not_exist', sprintf( __( 'The certificate <code>%s</code> does not exist.', 'wp-encrypt' ), $domain_path . '/cert.pem' ) );
			}

			$pem = $certificate->read();
			if ( is_wp_error( $pem ) ) {
				return $pem;
			}

			$client = Client::get();

			$result = $client->revoke( $pem );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			if ( 200 !== $client->get_last_code() ) {
				return new WP_Error( 'revoke_cert_invalid_response_code', __( 'Invalid response code for revoke certificate request.', 'wp-encrypt' ) );
			}

			return true;
		}
	}
}
