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
			$options = get_site_option( 'wp_encrypt_settings', array() );
			if ( is_array( $field ) ) {
				$options = array_merge( $options, $field );
			} else {
				$options[ $field ] = $value;
			}
			return update_site_option( 'wp_encrypt_settings', $options );
		}

		public static function get_option( $field ) {
			$options = get_site_option( 'wp_encrypt_settings', array() );
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

		public static function set_registration_info( $fields, $value ) {
			$value['_wp_time'] = current_time( 'mysql' );

			$options = get_site_option( 'wp_encrypt_registration', array() );
			foreach ( (array) $fields as $field ) {
				if ( 'account' !== $field && ! is_numeric( $field ) ) {
					continue;
				}
				$options[ $field ] = $value;
			}
			return update_site_option( 'wp_encrypt_registration', $options );
		}

		public static function get_registration_info( $field ) {
			if ( 'account' !== $field && ! is_numeric( $field ) ) {
				return '';
			}
			$options = get_site_option( 'wp_encrypt_registration', array() );
			if ( ! isset( $options[ $field ] ) || ! isset( $options[ $field ]['_wp_time'] ) ) {
				return '';
			}
			return $options[ $field ]['_wp_time'];
		}

		public static function delete_registration_info( $fields ) {
			$options = get_site_option( 'wp_encrypt_registration', array() );
			foreach ( (array) $fields as $field ) {
				if ( isset( $options[ $field ] ) ) {
					unset( $options[ $field ] );
				}
			}
			return update_site_option( 'wp_encrypt_registration', $options );
		}

		public static function can_generate_certificate() {
			return self::get_registration_info( 'account' ) && self::get_option( 'valid' );
		}

		public static function get_current_site_id() {
			return get_current_blog_id();
		}

		public static function get_current_network_site_ids() {
			$ids = array();
			foreach ( wp_get_sites() as $site ) {
				$ids[] = $site['blog_id'];
			}

			return $ids;
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
				if ( $site->domain === $network->domain || in_array( $site->domain, $addon_domains, true ) ) {
					continue;
				}
				$addon_domains[] = $site->domain;
			}

			return $addon_domains;
		}
	}
}
