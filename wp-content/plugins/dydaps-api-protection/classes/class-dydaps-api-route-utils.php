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
	/**
	 * Normalize and validate an exact REST namespace (without route slashes).
	 *
	 * @param string $namespace_name REST namespace.
	 * @return string
	 */
	public static function normalize_namespace( $namespace_name ) {
		if ( ! is_string( $namespace_name ) ) {
			return '';
		}

		$namespace = trim( $namespace_name, " \t\n\r\0\x0B/" );
		if ( '' === $namespace || strlen( $namespace ) > 200 || ! preg_match( '#^[A-Za-z0-9._~-]+(?:/[A-Za-z0-9._~-]+)*$#D', $namespace ) || false !== strpos( $namespace, '..' ) ) {
			return '';
		}

		return $namespace;
	}

	/**
	 * Match only an exact namespace boundary, never a similarly named one.
	 *
	 * @param string $route REST route.
	 * @param string $namespace_name REST namespace.
	 * @return bool
	 */
	public static function route_matches_namespace( $route, $namespace_name ) {
		$namespace = self::normalize_namespace( $namespace_name );
		$route     = self::normalize_route_prefix( $route );
		if ( '' === $namespace || '' === $route ) {
			return false;
		}

		$namespace_route = '/' . $namespace;
		return $route === $namespace_route || 0 === strpos( $route, $namespace_route . '/' );
	}

	/**
	 * Validate one explicit anonymous exception route pattern.
	 *
	 * @param string $route Route pattern.
	 * @return string
	 */
	public static function normalize_exception_route( $route ) {
		if ( ! is_string( $route ) || strlen( $route ) > 300 ) {
			return '';
		}
		$route = trim( $route );
		if ( '' === $route || false !== strpos( $route, '#' ) || preg_match( '#^[a-z][a-z0-9+.-]*://#i', $route ) ) {
			return '';
		}
		$route = '/' . ltrim( $route, '/' );
		if ( 0 === strpos( $route, '/wp-json/' ) ) {
			$route = substr( $route, 8 );
		}
		$route = untrailingslashit( $route );
		if ( '' === $route || '/' === $route || false !== strpos( $route, '//' ) || false !== strpos( $route, '..' ) ) {
			return '';
		}
		// Accept literal path segments and a small set of named WordPress params.
		$segments = explode( '/', ltrim( $route, '/' ) );
		foreach ( $segments as $segment ) {
			if ( null !== self::get_exception_parameter_pattern( $segment ) ) {
				continue;
			}
			if ( ! preg_match( '/^[A-Za-z0-9._~-]+$/D', $segment ) ) {
				return '';
			}
		}
		return '/' . implode( '/', $segments );
	}

	/**
	 * Match an exception with an explicit HTTP method and whole route boundary.
	 *
	 * @param string $route Route to check.
	 * @param string $method HTTP method.
	 * @param array  $exception Exception definition.
	 * @return bool
	 */
	public static function exception_matches( $route, $method, array $exception ) {
		if ( ! isset( $exception['enabled'], $exception['route'], $exception['method'] )
			|| ! in_array( $exception['enabled'], array( true, 1, '1' ), true )
			|| ! is_string( $exception['route'] )
			|| ! is_string( $exception['method'] )
			|| ! is_string( $method )
			|| strtoupper( $method ) !== $exception['method']
		) {
			return false;
		}
		$pattern = self::normalize_exception_route( $exception['route'] );
		$route   = self::normalize_route_prefix( $route );
		if ( '' === $pattern || '' === $route ) {
			return false;
		}
		$segments         = explode( '/', trim( $pattern, '/' ) );
		$expression_parts = array();
		foreach ( $segments as $segment ) {
			$parameter_pattern  = self::get_exception_parameter_pattern( $segment );
			$expression_parts[] = null !== $parameter_pattern ? $parameter_pattern : preg_quote( $segment, '#' );
		}

		return 1 === preg_match( '#^/' . implode( '/', $expression_parts ) . '$#D', $route );
	}

	/**
	 * Convert one whitelisted named parameter to its equivalent safe regex.
	 *
	 * @param string $segment Route segment.
	 * @return string|null
	 */
	private static function get_exception_parameter_pattern( $segment ) {
		if ( ! is_string( $segment ) || ! preg_match( '/^\(\?P<([A-Za-z_][A-Za-z0-9_]*)>\[((?:\\\\d|\\\\w-|A-Za-z0-9_-|a-zA-Z0-9_-|0-9))\]\+\)$/D', $segment, $matches ) ) {
			return null;
		}

		return in_array( $matches[2], array( '\\d', '0-9' ), true ) ? '[0-9]+' : '[A-Za-z0-9_-]+';
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
			return array();
		}

		$normalized = array();

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
		$known_routes = array_merge( $default_prefixes, array( '/', '/wp/v2' ) );

		return array_values( array_diff( $effective_prefixes, $known_routes ) );
	}
}
