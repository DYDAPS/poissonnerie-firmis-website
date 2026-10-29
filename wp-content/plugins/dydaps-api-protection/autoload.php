<?php
/**
 * Lightweight plugin autoloader.
 *
 * @package DYDAPS_API_Protection
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class_name ) {
		if ( 0 !== strpos( $class_name, 'Dydaps_Api_' ) ) {
			return;
		}

		$relative_class = strtolower( str_replace( '_', '-', $class_name ) );
		$file_path      = DYDAPS_API_PROTECTION_PATH . 'classes/class-' . $relative_class . '.php';

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}
	}
);
