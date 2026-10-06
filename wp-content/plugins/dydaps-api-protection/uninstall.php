<?php
/**
 * Uninstall plugin cleanup.
 *
 * @package DYDAPS_API_Protection
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

define( 'DYDAPS_API_PROTECTION_PATH', __DIR__ . '/' );

require_once __DIR__ . '/autoload.php';

Dydaps_Api_Option_Store::delete_many( Dydaps_Api_Config::OPTION_NAMES );
delete_transient( 'dydaps_api_protection_plain_key' );
