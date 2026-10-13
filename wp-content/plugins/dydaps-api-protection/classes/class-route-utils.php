<?php
/**
 * Utility functions for route handling and normalization.
 *
 * @package DYDAPS_API_Protection
 */

defined( 'ABSPATH' ) || exit;

/**
 * Route normalization and validation utilities.
 */
final class Dydaps_Api_Route_Utils {
	/** Normalize and validate an exact REST namespace (without route slashes). */
	public static function normalize_namespace( $namespace ) {
		if ( ! is_string( $namespace ) ) {
			return '';
		}

		$namespace = trim( $namespace, " \t\n\r\0\x0B/" );
		if ( '' === $namespace || strlen( $namespace ) > 200 || ! preg_match( '#^[A-Za-z0-9._~-]+(?:/[A-Za-z0-9._~-]+)*$#D', $namespace ) || false !== strpos( $namespace, '..' ) ) {
			return '';
		}

		return $namespace;
	}

	/** Match only an exact namespace boundary, never a similarly named one. */
	public static function route_matches_namespace( $route, $namespace ) {
		$namespace = self::normalize_namespace( $namespace );
		$route     = self::normalize_route_prefix( $route );
		if ( '' === $namespace || '' === $route ) {
			return false;
		}

		$namespace_route = '/' . $namespace;
		return $route === $namespace_route || 0 === strpos( $route, $namespace_route . '/' );
	}

	/**
	 * Normalize a route prefix into a leading-slash REST path.
	 *
	 * Handles multiple input formats: full URLs, route paths, and wp-json paths.
	 * Returns an empty string for invalid input.
	 *
	 * @param string $route_prefix Raw route prefix to normalize.
	 * @return string Normalized route prefix.
	 */
	public static function normalize_route_prefix( $route_prefix ) {
		$route_prefix = trim( (string) $route_prefix );

		if ( '' === $route_prefix ) {
			return '';
		}

		if ( preg_match( '#^https?://#i', $route_prefix ) ) {
			$path         = wp_parse_url( $route_prefix, PHP_URL_PATH );
			$route_prefix = is_string( $path ) ? $path : '';
		}

		if ( '' === $route_prefix ) {
			return '';
		}

		$route_prefix = '/' . ltrim( $route_prefix, '/' );

		if ( '/wp-json' === $route_prefix ) {
			return '/';
		}

		if ( 0 === strpos( $route_prefix, '/wp-json/' ) ) {
			$route_prefix = substr( $route_prefix, strlen( '/wp-json' ) );
		}

		$route_prefix = '/' . ltrim( $route_prefix, '/' );

		if ( '/' === $route_prefix ) {
			return '/';
		}

		return untrailingslashit( $route_prefix );
	}

	/**
	 * Determine whether a route matches any prefix in a list.
	 *
	 * The root "/" prefix matches all routes. Other prefixes match only the
	 * exact route or one of its children, which avoids false positives such as
	 * "/wp/v2/post" matching "/wp/v2/posts".
	 *
	 * @param string   $route    Current REST route.
	 * @param string[] $prefixes List of route prefixes.
	 * @return bool
	 */
	public static function route_matches_prefixes( $route, array $prefixes ) {
		$route = self::normalize_route_prefix( $route );

		if ( '' === $route ) {
			return false;
		}

		foreach ( $prefixes as $prefix ) {
			$prefix = self::normalize_route_prefix( $prefix );

			if ( '' === $prefix ) {
				continue;
			}

			if ( '/' === $prefix || $route === $prefix ) {
				return true;
			}

			if ( 0 === strpos( $route, $prefix . '/' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parse route prefixes from multi-line input.
	 *
	 * @param string $input Multi-line input with one route per line.
	 * @return string[]
	 */
	public static function parse_routes_from_input( $input ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $input );

		if ( ! is_array( $lines ) ) {
			return [];
		}

		$normalized = [];

		foreach ( $lines as $line ) {
			$normalized_line = self::normalize_route_prefix( $line );

			if ( '' !== $normalized_line ) {
				$normalized[] = $normalized_line;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Format route prefixes for textarea display.
	 *
	 * @param string[] $prefixes Route prefixes to format.
	 * @return string
	 */
	public static function format_routes_for_textarea( array $prefixes ) {
		return implode( "\n", $prefixes );
	}

	/**
	 * Extract custom route prefixes that are not part of built-in defaults.
	 *
	 * @param string[] $effective_prefixes Effective protected prefixes.
	 * @param string[] $default_prefixes   Built-in protected prefixes.
	 * @return string[]
	 */
	public static function get_custom_routes( array $effective_prefixes, array $default_prefixes ) {
		$known_routes = array_merge( $default_prefixes, [ '/', '/wp/v2' ] );

		return array_values( array_diff( $effective_prefixes, $known_routes ) );
	}
}
