<?php
/**
 * Plugin Name: DYDAPS API Protection
 * Plugin URI: https://dydaps.com/
 * Description: Protect selected WordPress REST API routes with a secret API key sent in a custom HTTP header.
 * Version: 1.3.0
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: DYDAPS
 * Text Domain: dydaps-api-protection
 * Domain Path: /languages
 *
 * @package DYDAPS_API_Protection
 */

defined( 'ABSPATH' ) || exit;

define( 'DYDAPS_API_PROTECTION_VERSION', '1.3.0' );
define( 'DYDAPS_API_PROTECTION_FILE', __FILE__ );
define( 'DYDAPS_API_PROTECTION_PATH', plugin_dir_path( __FILE__ ) );
define( 'DYDAPS_API_PROTECTION_URL', plugin_dir_url( __FILE__ ) );

require_once DYDAPS_API_PROTECTION_PATH . 'autoload.php';

/**
 * Bootstrap the plugin once all plugins are loaded.
 */
function dydaps_api_protection() {
	return Dydaps_Api_Protection_Plugin::get_instance();
}

// Register activation/deactivation hooks.
register_activation_hook( DYDAPS_API_PROTECTION_FILE, [ 'Dydaps_Api_Activation', 'check_requirements' ] );
register_deactivation_hook( DYDAPS_API_PROTECTION_FILE, [ 'Dydaps_Api_Activation', 'on_deactivation' ] );

add_action(
	'plugins_loaded',
	static function () {
		dydaps_api_protection()->run();
	}
);
