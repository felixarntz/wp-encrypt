<?php
/**
 * @package WPENC
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPENC;

use LaL_WP_Plugin as Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPENC\App' ) ) {
	/**
	 * This class initializes the plugin.
	 *
	 * It also triggers the action and filter to hook into and contains all API functions of the plugin.
	 *
	 * @since 0.5.0
	 */
	final class App extends Plugin {

		/**
		 * @since 0.5.0
		 * @var array Holds the plugin data.
		 */
		protected static $_args = array();

		/**
		 * Class constructor.
		 *
		 * This is protected on purpose since it is called by the parent class' singleton.
		 *
		 * @internal
		 * @since 0.5.0
		 * @param array $args array of class arguments (passed by the plugin utility class)
		 */
		protected function __construct( $args ) {
			parent::__construct( $args );
		}

		/**
		 * The run() method.
		 *
		 * This will initialize the plugin on the 'after_setup_theme' action.
		 *
		 * @internal
		 * @since 0.5.0
		 */
		protected function run() {
			if ( is_admin() ) {
				Admin::instance()->run();
			}
		}
	}
}
