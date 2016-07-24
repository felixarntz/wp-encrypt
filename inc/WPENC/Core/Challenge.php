<?php
/**
 * WPENC\Core\Challenge class
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

if ( ! class_exists( 'WPENC\Core\Challenge' ) ) {
	/**
	 * This class validates challenges for a domain.
	 *
	 * @since 1.0.0
	 */
	final class Challenge {
		/**
		 * Validates a domain with Let's Encrypt through a HTTP challenge.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param string $domain              The domain to validate.
		 * @param array  $account_key_details The account private key details.
		 * @return bool|WP_Error True if the domain was successfully validated, an error object otherwise.
		 */
		public static function validate( $domain, $account_key_details ) {
			$filesystem = Util::get_filesystem();

			$status = Util::maybe_create_letsencrypt_challenges_dir();
			if ( is_wp_error( $status ) ) {
				return $status;
			}

			$client = Client::get();

			$response = $client->auth( $domain );
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
				return new WP_Error( 'no_challenge_available', sprintf( __( 'No HTTP challenge available for domain %1$s. Original response: %2$s', 'wp-encrypt' ), $domain, json_encode( $response ) ) );
			}

			$location = $client->get_last_location();

			$directory = Util::get_letsencrypt_challenges_dir_path();
			$token_path = $directory . '/' . $challenge['token'];

			if ( ! $filesystem->is_dir( $directory ) && ! $filesystem->mkdir( $directory, 0755, true ) ) {
				return new WP_Error( 'challenge_cannot_create_dir', sprintf( __( 'Could not create challenge directory <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $directory ) );
			}

			$header = array(
				'e'		=> Util::base64_url_encode( $account_key_details['rsa']['e'] ),
				'kty'	=> 'RSA',
				'n'		=> Util::base64_url_encode( $account_key_details['rsa']['n'] ),
			);
			$data = $challenge['token'] . '.' . Util::base64_url_encode( hash( 'sha256', json_encode( $header ), true ) );

			if ( false === $filesystem->put_contents( $token_path, $data ) ) {
				return new WP_Error( 'challenge_cannot_write_file', sprintf( __( 'Could not write challenge to file <code>%s</code>. Please check your filesystem permissions.', 'wp-encrypt' ), $token_path ) );
			}
			$filesystem->chmod( $token_path, 0644 );

			$response = wp_remote_get( Util::get_letsencrypt_challenges_dir_url() . '/' . $challenge['token'] );
			if ( is_wp_error( $response ) ) {
				$filesystem->delete( $token_path );
				return new WP_Error( 'challenge_request_failed', sprintf( __( 'Challenge request failed for domain %s.', 'wp-encrypt' ), $domain ) );
			}

			if ( $data !== trim( wp_remote_retrieve_body( $response ) ) ) {
				$filesystem->delete( $token_path );
				return new WP_Error( 'challenge_self_check_failed', sprintf( __( 'Challenge self check failed for domain %s.', 'wp-encrypt' ), $domain ) );
			}

			$result = $client->challenge( $challenge['uri'], $challenge['token'], $data );

			$done = false;

			do {
				if ( empty( $result['status'] ) || 'invalid' === $result['status'] ) {
					$filesystem->delete( $token_path );
					return new WP_Error( 'challenge_remote_check_failed', sprintf( __( 'Challenge remote check failed for domain %s.', 'wp-encrypt' ), $domain ) );
				}

				$done = 'pending' !== $result['status'];
				if ( ! $done ) {
					sleep( 1 );
				}

				$result = $client->request( $location, 'GET' );
				if ( 'invalid' === $result['status'] ) {
					$filesystem->delete( $token_path );
					return new WP_Error( 'challenge_remote_check_failed', sprintf( __( 'Challenge remote check failed for domain %s.', 'wp-encrypt' ), $domain ) );
				}
			} while ( ! $done );

			$filesystem->delete( $token_path );

			return true;
		}
	}
}
