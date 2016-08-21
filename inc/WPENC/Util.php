<?php
/**
 * WPENC\Util class
 *
 * @package WPENC
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 * @since 1.0.0
 */

namespace WPENC;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPENC\Util' ) ) {
	/**
	 * This class contains some utility methods.
	 *
	 * @since 1.0.0
	 */
	final class Util {
		/**
		 * Updates a specific plugin option.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param string $field The option to update.
		 * @param string $value The value to update the option with.
		 * @return bool Whether the option was successfully updated.
		 */
		public static function update_option( $field, $value = '' ) {
			$options = get_site_option( 'wp_encrypt_settings', array() );
			if ( is_array( $field ) ) {
				$options = array_merge( $options, $field );
			} else {
				$options[ $field ] = $value;
			}
			return update_site_option( 'wp_encrypt_settings', $options );
		}

		/**
		 * Retrieves a specific plugin option.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param string $field The option to retrieve.
		 * @return mixed The option value.
		 */
		public static function get_option( $field ) {
			$options = get_site_option( 'wp_encrypt_settings', array() );
			if ( ! isset( $options[ $field ] ) ) {
				switch ( $field ) {
					case 'organization':
						return get_bloginfo( 'name' );
					case 'country_code':
						return substr( get_locale(), 3, 2 );
					case 'valid':
					case 'include_all_networks':
					case 'autogenerate_certificate':
					case 'show_warning':
						return false;
					case 'show_warning_days':
						return 15;
					default:
						return '';
				}
			}

			switch ( $field ) {
				case 'valid':
				case 'include_all_networks':
				case 'autogenerate_certificate':
				case 'show_warning':
					return (bool) $options[ $field ];
				case 'show_warning_days':
					return absint( $options[ $field ] );
			}

			return $options[ $field ];
		}

		/**
		 * Sets info for the latest account registration / generated certificate.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param string $field Either 'account' or 'certificate'.
		 * @param array $value The data to store.
		 * @return bool Whether the info was successfully stored.
		 */
		public static function set_registration_info( $field, $value ) {
			if ( 'account' !== $field ) {
				$field = 'certificate';
			}

			if ( 'certificate' === $field || ! isset( $value['_wp_time'] ) ) {
				$value['_wp_time'] = current_time( 'mysql' );
			}

			$options = get_site_option( 'wp_encrypt_registration', array() );
			$options[ $field ] = $value;
			return update_site_option( 'wp_encrypt_registration', $options );
		}

		/**
		 * Retrieves info for the latest account registration / generated certificate.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param string $field Either 'account' or 'certificate'.
		 * @return array Info for the latest account / certificate.
		 */
		public static function get_registration_info( $field ) {
			if ( 'account' !== $field ) {
				$field = 'certificate';
			}

			$options = get_site_option( 'wp_encrypt_registration', array() );
			if ( ! isset( $options[ $field ] ) ) {
				return array();
			}
			return $options[ $field ];
		}

		/**
		 * Deletes the info for the latest account registration / generated certificate.
		 *
		 * This method is called after a certificate has been revoked.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param string $field Either 'account' or 'certificate'.
		 * @return bool Whether the info was successfully deleted.
		 */
		public static function delete_registration_info( $field ) {
			if ( 'account' !== $field ) {
				$field = 'certificate';
			}

			$options = get_site_option( 'wp_encrypt_registration', array() );
			if ( isset( $options[ $field ] ) ) {
				unset( $options[ $field ] );
			}
			return update_site_option( 'wp_encrypt_registration', $options );
		}

		/**
		 * Checks whether the requirements are met to generate a certificate.
		 *
		 * The main settings must be filled and valid, and an account needs to be registered.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @return bool Whether a certificate can be generated.
		 */
		public static function can_generate_certificate() {
			return self::get_registration_info( 'account' ) && self::get_option( 'valid' );
		}

		/**
		 * Retrieves the ID of the current site.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @return int The current site ID.
		 */
		public static function get_site_id() {
			return get_current_blog_id();
		}

		/**
		 * Retrieves the IDs of all sites in the current network.
		 *
		 * If the $global flag is set, it will get the IDs for all sites in all networks.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param boolean $global Whether the site IDs for all networks should be retrieved.
		 * @return array The site IDs.
		 */
		public static function get_network_site_ids( $global = false ) {
			$ids = array();

			if ( version_compare( get_bloginfo( 'version' ), '4.6', '<' ) ) {
				$args = array();
				if ( $global ) {
					$args['network_id'] = 0;
				}

				foreach ( wp_get_sites( $args ) as $site ) {
					$ids[] = $site['blog_id'];
				}
			} else {
				$args = array( 'fields' => 'ids' );
				if ( ! $global ) {
					$args['network_id'] = get_current_network_id();
				}

				$ids = get_sites( $args );
			}

			return $ids;
		}

		/**
		 * Retrieves the domain for a site.
		 *
		 * This method is used on a regular site.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param int $site_id The site ID to get the domain for.
		 * @return string The domain for the site.
		 */
		public static function get_site_domain( $site_id = null ) {
			$url = get_home_url( $site_id );
			$url = explode( '/', str_replace( array( 'https://', 'http://' ), '', $url ) );
			return $url[0];
		}

		/**
		 * Retrieves the domain for a network.
		 *
		 * This method is used on Multisite.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param int $network_id The network ID to get the domain for.
		 * @return string The domain for the network.
		 */
		public static function get_network_domain( $network_id = null ) {
			if ( version_compare( get_bloginfo( 'version' ), '4.6', '<' ) ) {
				if ( ! $network_id ) {
					$network = get_current_site();
				} else {
					$network = wp_get_network( $network_id );
				}
			} else {
				$network = get_network( $network_id );
			}

			return $network->domain;
		}

		/**
		 * Retrieves the additional domains for a network.
		 *
		 * The additional domains are the domains for all sites in the network except for the
		 * main site. If the $global flag is set, it will get the domains for the sites in all
		 * networks.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param int  $network_id The network ID to get the addon domains for.
		 * @param bool $global     Whether the site domains for all networks should be retrieved.
		 * @return array The site domains.
		 */
		public static function get_network_addon_domains( $network_id = null, $global = false ) {
			if ( version_compare( get_bloginfo( 'version' ), '4.6', '<' ) ) {
				if ( ! $network_id ) {
					$network = get_current_site();
				} else {
					$network = wp_get_network( $network_id );
				}
			} else {
				$network = get_network( $network_id );
			}

			if ( ! $global ) {
				$network_id = $network->id;
			} else {
				$network_id = false;
			}

			$addon_domains = array();

			$sites = array();
			if ( version_compare( get_bloginfo( 'version' ), '4.6', '<' ) ) {
				$sites = wp_get_sites( array(
					'network_id'	=> $network_id,
				) );
			} else {
				$args = array( 'domain__not_in' => array( $network->domain ) );
				if ( $network_id ) {
					$args['network_id'] = $network_id;
				}
				$sites = get_sites( $args );
			}

			foreach ( $sites as $site ) {
				if ( is_array( $site ) ) {
					$site = (object) $site;
				}
				if ( $site->domain === $network->domain || in_array( $site->domain, $addon_domains, true ) ) {
					continue;
				}
				$addon_domains[] = $site->domain;
			}

			return $addon_domains;
		}

		/**
		 * Schedules the Cron event to regenerate the certificate automatically.
		 *
		 * Let's Encrypt certificates are valid for 90 days. The Cron event ensures that they are
		 * regenerated prior to expiration.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 *
		 * @param string $generate_timestamp The timestamp the latest certificate was generated.
		 * @param bool   $reschedule         Whether to force rescheduling even if the event exists.
		 */
		public static function schedule_autogenerate_event( $generate_timestamp = null, $reschedule = false ) {
			$timestamp = wp_next_scheduled( 'wp_encrypt_generate_certificate' );
			if ( $timestamp && $reschedule ) {
				wp_unschedule_event( $timestamp, 'wp_encrypt_generate_certificate' );
				$timestamp = false;
			}

			if ( ! $timestamp ) {
				if ( ! $generate_timestamp ) {
					$generate_timestamp = current_time( 'timestamp' );
				}
				$timestamp = absint( $generate_timestamp ) + 85 * DAY_IN_SECONDS;
				wp_schedule_single_event( $timestamp, 'wp_encrypt_generate_certificate' );
			}
		}

		/**
		 * Unschedules the Cron event to regenerate the certificate automatically.
		 *
		 * Automatically regenerating the certificate is an optional feature of this plugin. If the
		 * setting is disabled, this method is invoked.
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 */
		public static function unschedule_autogenerate_event() {
			$timestamp = wp_next_scheduled( 'wp_encrypt_generate_certificate' );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, 'wp_encrypt_generate_certificate' );
			}
		}
	}
}
