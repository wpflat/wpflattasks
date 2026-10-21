<?php
/**
 * Plugin Name: WPFlat Tasks
 * Description: Projects, phases and tasks for the WPFlat suite. Needs WPFlat Core.
 * Version: 0.2.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Digitalzooks
 * License: GPL-2.0-or-later
 * Text Domain: wpflattasks
 */

defined( 'ABSPATH' ) || exit;

define( 'WPFLATTASKS_VERSION', '0.2.1' );
define( 'WPFLATTASKS_FILE', __FILE__ );
define( 'WPFLATTASKS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPFLATTASKS_URL', plugin_dir_url( __FILE__ ) );

add_action(
	'plugins_loaded',
	function () {
		if ( ! defined( 'WPFLATCORE_VERSION' ) ) {
			add_action(
				'admin_notices',
				function () {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-error"><p><strong>WPFlat Tasks</strong> needs the <strong>WPFlat Core</strong> plugin. Install and activate WPFlat Core, then WPFlat Tasks will start.</p></div>';
					}
				}
			);
			return;
		}
		spl_autoload_register(
			function ( $class ) {
				if ( 0 === strpos( $class, 'WPFlatTasks\\' ) ) {
					$path = WPFLATTASKS_DIR . 'includes/' . str_replace( '\\', '/', substr( $class, 12 ) ) . '.php';
					if ( is_readable( $path ) ) {
						require_once $path;
					}
				}
			}
		);
		WPFlatTasks\Module::init();
	},
	1
);
