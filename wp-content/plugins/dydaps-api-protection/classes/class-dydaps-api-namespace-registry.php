<?php
/**
 * Read-only inventory of registered REST namespaces and routes.
 *
 * @package DYDAPS_API_Protection
 */

defined( 'ABSPATH' ) || exit;

/** Inventories registered REST namespaces and verifiable callback owners. */
final class Dydaps_Api_Namespace_Registry {
	private const CORE_OWNER = '__DYDAPS_WORDPRESS_CORE__';
	/**
	 * Return registered namespaces with route names and a verifiable owner where possible.
	 *
	 * @return array<string, array{routes: string[], owner: string}>
	 */
	public static function get_inventory() {
		if ( ! function_exists( 'rest_get_server' ) ) {
			return array();
		}

		$server = rest_get_server();
		if ( ! $server instanceof WP_REST_Server || ! method_exists( $server, 'get_namespaces' ) || ! method_exists( $server, 'get_routes' ) ) {
			return array();
		}

		$inventory = array();
		if ( ! function_exists( 'get_plugins' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$routes  = $server->get_routes();
		foreach ( $server->get_namespaces() as $namespace ) {
			$namespace = Dydaps_Api_Route_Utils::normalize_namespace( $namespace );
			if ( '' === $namespace ) {
				continue;
			}

			$inventory[ $namespace ] = array(
				'routes' => array(),
				'owners' => array(),
			);
			foreach ( $routes as $route => $handlers ) {
				if ( ! Dydaps_Api_Route_Utils::route_matches_namespace( $route, $namespace ) ) {
					continue;
				}

				$inventory[ $namespace ]['routes'][] = $route;
				foreach ( $handlers as $handler ) {
					if ( isset( $handler['callback'] ) ) {
						$owner = self::get_callback_owner( $handler['callback'], $plugins );
						if ( '' !== $owner ) {
							$inventory[ $namespace ]['owners'][ $owner ] = true;
						}
					}
				}
			}

			sort( $inventory[ $namespace ]['routes'] );
			$owners                           = array_keys( $inventory[ $namespace ]['owners'] );
			$inventory[ $namespace ]['owner'] = self::format_owner( $owners );
			unset( $inventory[ $namespace ]['owners'] );
		}

		ksort( $inventory );
		return $inventory;
	}

	/** Return one row per registered route and HTTP method. */
	public static function get_endpoint_inventory() {
		if ( ! function_exists( 'rest_get_server' ) ) {
			return array();
		}
		$server = rest_get_server();
		if ( ! $server instanceof WP_REST_Server || ! method_exists( $server, 'get_routes' ) ) {
			return array();
		}
		$plugins = array();
		if ( ! function_exists( 'get_plugins' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( function_exists( 'get_plugins' ) ) {
			$plugins = get_plugins();
		}
		$rows = array();
		foreach ( $server->get_routes() as $route => $handlers ) {
			$namespace = '';
			foreach ( $server->get_namespaces() as $registered_namespace ) {
				if ( Dydaps_Api_Route_Utils::route_matches_namespace( $route, $registered_namespace ) ) {
					$namespace = $registered_namespace;
					break;
				}
			}
			$methods = array();
			$owners  = array();
			foreach ( $handlers as $handler ) {
				foreach ( isset( $handler['methods'] ) && is_array( $handler['methods'] ) ? $handler['methods'] : array() as $method => $enabled ) {
					if ( $enabled ) {
						$methods[] = strtoupper( $method );
					}
				}
				if ( isset( $handler['callback'] ) ) {
					$owner = self::get_callback_owner( $handler['callback'], $plugins );
					if ( '' !== $owner ) {
						$owners[ $owner ] = true;
					}
				}
			}
			$methods = array_values( array_unique( $methods ) );
			sort( $methods );
			$owner_names = array_keys( $owners );
			$rows[]      = array(
				'namespace' => $namespace,
				'route'     => $route,
				'methods'   => $methods,
				'owner'     => self::format_owner( $owner_names ),
				'core'      => isset( $owners[ self::CORE_OWNER ] ),
			);
		}
		return $rows;
	}

	/** Resolve a registered callback file to a plugin only when it falls inside its directory. */
	/**
	 * Resolve a callback file to an installed plugin when its location is verified.
	 *
	 * @param callable $callback REST endpoint callback.
	 * @param array    $plugins Installed plugin metadata.
	 * @return string
	 */
	private static function get_callback_owner( $callback, array $plugins ) {
		$file = '';
		try {
			if ( is_array( $callback ) && isset( $callback[0], $callback[1] ) ) {
				$reflection = new ReflectionMethod( $callback[0], $callback[1] );
			} elseif ( $callback instanceof Closure ) {
				$reflection = new ReflectionFunction( $callback );
			} elseif ( is_object( $callback ) && is_callable( $callback ) ) {
				$reflection = new ReflectionMethod( $callback, '__invoke' );
			} elseif ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
				$reflection = new ReflectionMethod( $callback );
			} elseif ( is_string( $callback ) && function_exists( $callback ) ) {
				$reflection = new ReflectionFunction( $callback );
			} else {
				return '';
			}
			$file = $reflection->getFileName();
		} catch ( Throwable $exception ) {
			return '';
		}

		if ( ! is_string( $file ) || '' === $file ) {
			return '';
		}

		$file = wp_normalize_path( $file );
		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			foreach ( $plugins as $plugin_file => $plugin_data ) {
				$plugin_path = wp_normalize_path( WP_PLUGIN_DIR . '/' . dirname( $plugin_file ) );
				if ( '.' === dirname( $plugin_file ) ) {
					$plugin_main_file = wp_normalize_path( WP_PLUGIN_DIR . '/' . $plugin_file );
					if ( $file === $plugin_main_file ) {
						return isset( $plugin_data['Name'] ) ? sanitize_text_field( $plugin_data['Name'] ) : '';
					}
					continue;
				}
				$prefix = trailingslashit( $plugin_path );
				if ( 0 === stripos( $file, $prefix ) ) {
					return isset( $plugin_data['Name'] ) ? sanitize_text_field( $plugin_data['Name'] ) : '';
				}
			}
		}

		if ( defined( 'ABSPATH' ) && 0 === stripos( $file, wp_normalize_path( ABSPATH . 'wp-includes/' ) ) ) {
			return self::CORE_OWNER;
		}

		return '';
	}

	/** Format verified callback ownership for display. */
	/**
	 * Format verified callback ownership for display.
	 *
	 * @param string[] $owners Verified owner labels.
	 * @return string
	 */
	private static function format_owner( array $owners ) {
		if ( 1 === count( $owners ) ) {
			return self::CORE_OWNER === $owners[0] ? __( 'WordPress Core', 'dydaps-api-protection' ) : $owners[0];
		}

		return empty( $owners ) ? __( 'Unknown', 'dydaps-api-protection' ) : __( 'Multiple owners', 'dydaps-api-protection' );
	}
}
