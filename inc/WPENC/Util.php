<?php
/**
 * @package WPENC
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPENC;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPENC\Util' ) ) {
	/**
	 * This class contains static utility methods.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	final class Util {
		public static function update_option( $field, $value = '' ) {
			$options = get_option( 'wp_encrypt_settings', array() );
			if ( is_array( $field ) ) {
				$options = array_merge( $options, $field );
			} else {
				$options[ $field ] = $value;
			}
			return update_option( 'wp_encrypt_settings', $options );
		}

		public static function get_option( $field ) {
			$options = get_option( 'wp_encrypt_settings', array() );
			if ( ! isset( $options[ $field ] ) ) {
				switch ( $field ) {
					case 'organization':
						return get_bloginfo( 'name' );
					case 'country_code':
						return substr( get_locale(), 3, 2 );
					case 'valid':
						return false;
					default:
						return '';
				}
			}
			return $options[ $field ];
		}

		public static function set_registration_info( $field, $value ) {
			if ( 'account' !== $field && ! is_numeric( $field ) ) {
				return false;
			}
			$options = get_site_option( 'wp_encrypt_registration', array() );
			$options[ $field ] = $value;
			return update_site_option( 'wp_encrypt_registration', $options );
		}

		public static function get_registration_info( $field ) {
			if ( 'account' !== $field && ! is_numeric( $field ) ) {
				return '';
			}
			$options = get_site_option( 'wp_encrypt_registration', array() );
			if ( ! isset( $options[ $field ] ) ) {
				return '';
			}
			return $options[ $field ];
		}

		public static function get_site_domain( $site_id = null ) {
			$url = get_home_url( $site_id );
			$url = explode( '/', str_replace( array( 'https://', 'http://' ), '', $url ) );
			return $url[0];
		}

		public static function get_network_domain( $network_id = null ) {
			if ( null === $network_id ) {
				$network = get_current_site();
			} else {
				$network = wp_get_network( $network_id );
			}

			return $network->domain;
		}

		public static function get_network_addon_domains( $network_id = null ) {
			if ( null === $network_id ) {
				$network = get_current_site();
			} else {
				$network = wp_get_network( $network_id );
			}

			$addon_domains = array();

			$sites = wp_get_sites( array(
				'network_id'	=> $network->id,
			) );
			foreach ( $sites as $site ) {
				if ( $site->domain === $network->domain ) {
					continue;
				}
				$addon_domains[] = $site->domain;
			}

			return $addon_domains;
		}
	}
}
