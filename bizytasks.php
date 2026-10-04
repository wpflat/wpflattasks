<?php
/**
 * Plugin Name: BizyTasks
 * Description: Projects, phases and tasks for the Bizy suite. Needs BizyCore.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Digitalzooks
 * License: GPL-2.0-or-later
 * Text Domain: bizytasks
 */

defined( 'ABSPATH' ) || exit;

define( 'BIZYTASKS_VERSION', '0.1.0' );
define( 'BIZYTASKS_FILE', __FILE__ );
define( 'BIZYTASKS_DIR', plugin_dir_path( __FILE__ ) );
define( 'BIZYTASKS_URL', plugin_dir_url( __FILE__ ) );

add_action(
	'plugins_loaded',
	function () {
		if ( ! defined( 'BIZYCORE_VERSION' ) ) {
			add_action(
				'admin_notices',
				function () {
					if ( current_user_can( 'activate_plugins' ) ) {
						echo '<div class="notice notice-error"><p><strong>BizyTasks</strong> needs the <strong>BizyCore</strong> plugin. Install and activate BizyCore, then BizyTasks will start.</p></div>';
					}
				}
			);
			return;
		}
		spl_autoload_register(
			function ( $class ) {
				if ( 0 === strpos( $class, 'BizyTasks\\' ) ) {
					$path = BIZYTASKS_DIR . 'includes/' . str_replace( '\\', '/', substr( $class, 10 ) ) . '.php';
					if ( is_readable( $path ) ) {
						require_once $path;
					}
				}
			}
		);
		BizyTasks\Module::init();
	},
	1
);
