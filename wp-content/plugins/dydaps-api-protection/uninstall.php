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

global $wpdb;
$event_table = $wpdb->prefix . 'dydaps_api_events';
// Remove only the table owned by this plugin after an explicit uninstall.
$wpdb->query( "DROP TABLE IF EXISTS {$event_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The trusted-prefix table is plugin-owned and should be removed on uninstall.
