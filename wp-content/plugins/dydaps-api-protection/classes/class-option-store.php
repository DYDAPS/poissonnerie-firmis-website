<?php
/**
 * Shared option persistence and normalization helpers.
 *
 * @package DYDAPS_API_Protection
 */

defined( 'ABSPATH' ) || exit;

/**
 * Centralizes plugin option access.
 */
final class Dydaps_Api_Option_Store {
	/**
	 * Get a normalized array of route prefixes from an option.
	 *
	 * @param string   $option_name      Option name.
	 * @param string[] $default_prefixes Default value.
	 * @return string[]
	 */
	public static function get_route_prefixes( $option_name, array $default_prefixes = [] ) {
		$stored_value = get_option( $option_name, $default_prefixes );

		if ( ! is_array( $stored_value ) ) {
			return $default_prefixes;
		}

		$normalized_prefixes = [];

		foreach ( $stored_value as $stored_prefix ) {
			$normalized_prefix = Dydaps_Api_Route_Utils::normalize_route_prefix( $stored_prefix );

			if ( '' !== $normalized_prefix ) {
				$normalized_prefixes[] = $normalized_prefix;
			}
		}

		return array_values( array_unique( $normalized_prefixes ) );
	}

	/**
	 * Get a boolean flag stored as a string option.
	 *
	 * @param string $option_name Option name.
	 * @return bool
	 */
	public static function get_bool( $option_name ) {
		return '1' === (string) get_option( $option_name, '0' );
	}

	/**
	 * Store an option with autoload disabled when first created.
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $value       Option value.
	 * @return void
	 */
	public static function set( $option_name, $value ) {
		if ( false === get_option( $option_name, false ) ) {
			add_option( $option_name, $value, '', 'no' );
			return;
		}

		update_option( $option_name, $value, false );
	}

	/**
	 * Delete a list of options.
	 *
	 * @param string[] $option_names Option names.
	 * @return void
	 */
	public static function delete_many( array $option_names ) {
		foreach ( $option_names as $option_name ) {
			delete_option( $option_name );
		}
	}
}
