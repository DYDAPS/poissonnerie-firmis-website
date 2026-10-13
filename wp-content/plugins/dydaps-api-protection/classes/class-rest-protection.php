<?php
/**
 * REST API route protection middleware.
 *
 * @package DYDAPS_API_Protection
 */

defined( 'ABSPATH' ) || exit;

/**
 * Protects configured routes through the HTTP authentication and internal dispatch filters.
 */
final class Dydaps_Api_Rest_Protection {
	/**
	 * API key service.
	 *
	 * @var Dydaps_Api_Key_Service
	 */
	private $api_key_service;

	/**
	 * Default protected route prefixes.
	 *
	 * @var string[]
	 */
	private $default_protected_route_prefixes;

	/**
	 * Option name storing configured route prefixes.
	 *
	 * @var string
	 */
	private $protected_route_option_name;

	/**
	 * Option name toggling full REST API blocking.
	 *
	 * @var string
	 */
	private $block_all_option_name;

	/**
	 * Option name storing public route prefixes.
	 *
	 * @var string
	 */
	private $public_route_option_name;

	/** Whether same-process rest_do_request() dispatches may bypass DYDAPS checks. */
	private $allow_internal_option_name;

	/** Option containing exact namespaces exempt from DYDAPS checks. */
	private $allowed_namespaces_option_name;

	/**
	 * Constructor.
	 *
	 * @param Dydaps_Api_Key_Service $api_key_service           API key service.
	 * @param string[]               $protected_route_prefixes  Default protected route prefixes.
	 * @param string                 $protected_route_option_name Option storing protected prefixes.
	 * @param string                 $block_all_option_name       Option toggling full REST API blocking.
	 * @param string                 $public_route_option_name    Option storing public prefixes.
	 */
	public function __construct( Dydaps_Api_Key_Service $api_key_service, array $protected_route_prefixes, $protected_route_option_name, $block_all_option_name, $public_route_option_name, $allow_internal_option_name = '', $allowed_namespaces_option_name = '' ) {
		$this->api_key_service                  = $api_key_service;
		$this->default_protected_route_prefixes = array_map(
			[ Dydaps_Api_Route_Utils::class, 'normalize_route_prefix' ],
			$protected_route_prefixes
		);
		$this->protected_route_option_name      = $protected_route_option_name;
		$this->block_all_option_name            = $block_all_option_name;
		$this->public_route_option_name         = $public_route_option_name;
		$this->allow_internal_option_name       = $allow_internal_option_name;
		$this->allowed_namespaces_option_name   = $allowed_namespaces_option_name;
	}

	/**
	 * Filter REST authentication for protected routes only.
	 *
	 * Returning the incoming result preserves authentication behavior from core
	 * and third-party plugins for routes we do not explicitly own.
	 *
	 * @param WP_Error|null|true $result Existing auth result.
	 * @return WP_Error|null|true
	 */
	public function protect_routes( $result ) {
		if ( $result instanceof WP_Error ) {
			return $result;
		}

		$route = $this->get_current_route();

		if ( '' === $route ) {
			return $result;
		}

		if ( $this->is_public_route( $route ) ) {
			return $result;
		}

		// Exceptions bypass only this plugin. Core and route permission callbacks run later.
		if ( $this->is_allowed_namespace_route( $route ) ) {
			return $result;
		}

		if ( ! $this->is_protected_route( $route ) ) {
			if ( $this->is_block_all_enabled() ) {
				return new WP_Error(
					'dydaps_api_protection_blocked',
					__( 'This REST API endpoint is blocked.', 'dydaps-api-protection' ),
					[
						'status' => 403,
					]
				);
			}

			return $result;
		}

		if ( $this->api_key_service->verify_key( $this->get_header_value( $this->api_key_service->get_header_name() ) ) ) {
			return true;
		}

		return new WP_Error(
			'dydaps_api_protection_forbidden',
			__( 'A valid API key is required to access this endpoint.', 'dydaps-api-protection' ),
			[
				'status' => 403,
			]
		);
	}

	/**
	 * Apply DYDAPS rules to same-process rest_do_request() dispatches.
	 *
	 * Core's HTTP serve_request() runs rest_authentication_errors before dispatch(),
	 * but rest_do_request() goes directly to dispatch(). This filter covers that
	 * internal path without treating loopback HTTP as trusted.
	 *
	 * @param mixed           $result  Existing pre-dispatch result.
	 * @param WP_REST_Server  $server  REST server.
	 * @param WP_REST_Request $request REST request.
	 * @return mixed
	 */
	public function protect_internal_dispatch( $result, $server, $request ) {
		if ( $result instanceof WP_Error || ! $server instanceof WP_REST_Server || ! $request instanceof WP_REST_Request || ! $this->is_rest_do_request_dispatch( $server, $request ) ) {
			return $result;
		}

		// This option bypasses only DYDAPS. Returning null continues normal route dispatch
		// and leaves the endpoint's permission_callback untouched.
		if ( $this->is_internal_bypass_enabled() ) {
			return $result;
		}

		$route = Dydaps_Api_Route_Utils::normalize_route_prefix( $request->get_route() );
		if ( '' === $route || $this->is_public_route( $route ) || $this->is_allowed_namespace_route( $route ) ) {
			return $result;
		}

		if ( ! $this->is_protected_route( $route ) ) {
			if ( ! $this->is_block_all_enabled() ) {
				return $result;
			}

			return new WP_Error(
				'dydaps_api_protection_blocked',
				__( 'This REST API endpoint is blocked.', 'dydaps-api-protection' ),
				[ 'status' => 403 ]
			);
		}

		if ( $this->api_key_service->verify_key( $this->get_request_header_value( $request, $this->api_key_service->get_header_name() ) ) ) {
			// Do not return true here: rest_pre_dispatch treats non-empty values as
			// a short-circuit response, which would skip native permission checks.
			return $result;
		}

		return new WP_Error(
			'dydaps_api_protection_forbidden',
			__( 'A valid API key is required to access this endpoint.', 'dydaps-api-protection' ),
			[ 'status' => 403 ]
		);
	}

	/** Determine whether a route falls under a configured exact namespace. */
	private function is_allowed_namespace_route( $route ) {
		if ( '' === $this->allowed_namespaces_option_name ) {
			return false;
		}

		foreach ( Dydaps_Api_Option_Store::get_namespaces( $this->allowed_namespaces_option_name ) as $namespace ) {
			if ( Dydaps_Api_Route_Utils::route_matches_namespace( $route, $namespace ) ) {
				return true;
			}
		}

		return false;
	}

	/** Check whether trusted same-process internal dispatch bypass is enabled. */
	private function is_internal_bypass_enabled() {
		return '' !== $this->allow_internal_option_name && Dydaps_Api_Option_Store::get_bool( $this->allow_internal_option_name );
	}

	/**
	 * Recognize the current server dispatch only when it was entered through core rest_do_request().
	 *
	 * @return bool
	 */
	private function is_rest_do_request_dispatch( $server, $request ) {
		$trace = debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT, 20 );
		$count = count( $trace );
		for ( $index = 0; $index < $count; $index++ ) {
			$frame = $trace[ $index ];
			if ( isset( $frame['class'], $frame['function'] ) && 'WP_REST_Server' === $frame['class'] && 'dispatch' === $frame['function'] ) {
				$caller = isset( $trace[ $index + 1 ] ) ? $trace[ $index + 1 ] : [];
				return isset( $caller['function'], $caller['args'][0], $frame['args'][0] )
					&& 'rest_do_request' === $caller['function']
					&& is_object( $caller['args'][0] )
					&& $caller['args'][0] === $frame['args'][0]
					&& isset( $frame['object'] )
					&& $frame['object'] === $server
					&& $frame['args'][0] === $request;
			}
		}

		return false;
	}

	/**
	 * Resolve the current REST route in a defensive way.
	 *
	 * @return string
	 */
	private function get_current_route() {
		$request = $this->get_current_request_object();

		if ( $request instanceof WP_REST_Request ) {
			$route = $request->get_route();

			if ( is_string( $route ) && '' !== $route ) {
				return Dydaps_Api_Route_Utils::normalize_route_prefix( $route );
			}
		}

		if ( isset( $GLOBALS['wp']->query_vars['rest_route'] ) && is_string( $GLOBALS['wp']->query_vars['rest_route'] ) ) {
			return Dydaps_Api_Route_Utils::normalize_route_prefix( $GLOBALS['wp']->query_vars['rest_route'] );
		}

		$route = filter_input( INPUT_GET, 'rest_route', FILTER_UNSAFE_RAW );

		if ( is_string( $route ) && '' !== $route ) {
			return Dydaps_Api_Route_Utils::normalize_route_prefix( wp_unslash( $route ) );
		}

		return '';
	}

	/**
	 * Get the current REST request object when available.
	 *
	 * @return WP_REST_Request|null
	 */
	private function get_current_request_object() {
		if ( function_exists( 'rest_get_server' ) ) {
			$server = rest_get_server();
			if ( $server instanceof WP_REST_Server && method_exists( $server, 'get_current_request' ) ) {
				$request = $server->get_current_request();
				if ( $request instanceof WP_REST_Request ) {
					return $request;
				}
			}
		}

		// WordPress does not expose the active request consistently across supported versions.
		// The in-flight dispatch frame carries the actual request object for both HTTP and internal dispatches.
		foreach ( debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT, 20 ) as $frame ) {
			if ( isset( $frame['class'], $frame['function'], $frame['args'][0] )
				&& 'WP_REST_Server' === $frame['class']
				&& 'dispatch' === $frame['function']
				&& $frame['args'][0] instanceof WP_REST_Request
			) {
				return $frame['args'][0];
			}
		}

		return null;
	}

	/**
	 * Determine whether the route should be protected.
	 *
	 * @param string $route Current REST route.
	 * @return bool
	 */
	private function is_protected_route( $route ) {
		return Dydaps_Api_Route_Utils::route_matches_prefixes( $route, $this->get_protected_route_prefixes() );
	}

	/**
	 * Determine whether the route is explicitly public.
	 *
	 * @param string $route Current REST route.
	 * @return bool
	 */
	private function is_public_route( $route ) {
		return Dydaps_Api_Route_Utils::route_matches_prefixes( $route, $this->get_public_route_prefixes() );
	}

	/**
	 * Read and sanitize the custom header value.
	 *
	 * @param string $header_name Header name.
	 * @return string
	 */
	private function get_header_value( $header_name ) {
		$request = $this->get_current_request_object();
		return $this->get_request_header_value( $request, $header_name );
	}

	/**
	 * Read and sanitize a header from a known REST request.
	 *
	 * @param WP_REST_Request|null $request     REST request.
	 * @param string               $header_name Header name.
	 * @return string
	 */
	private function get_request_header_value( $request, $header_name ) {
		$value = null;

		if ( $request instanceof WP_REST_Request ) {
			$value = $request->get_header( $header_name );
		}

		if ( null === $value ) {
			$server_key = 'HTTP_' . strtoupper( str_replace( '-', '_', $header_name ) );
			$value      = isset( $_SERVER[ $server_key ] ) ? wp_unslash( $_SERVER[ $server_key ] ) : null;
		}

		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		if ( ! is_string( $value ) ) {
			return '';
		}

		return trim( sanitize_text_field( wp_unslash( $value ) ) );
	}

	/**
	 * Get the current effective protected route prefixes.
	 *
	 * @return string[]
	 */
	private function get_protected_route_prefixes() {
		return Dydaps_Api_Option_Store::get_route_prefixes(
			$this->protected_route_option_name,
			$this->default_protected_route_prefixes
		);
	}

	/**
	 * Get public route prefixes that bypass all protection.
	 *
	 * @return string[]
	 */
	private function get_public_route_prefixes() {
		return Dydaps_Api_Option_Store::get_route_prefixes( $this->public_route_option_name, [] );
	}

	/**
	 * Check whether full REST API blocking is enabled.
	 *
	 * @return bool
	 */
	private function is_block_all_enabled() {
		return Dydaps_Api_Option_Store::get_bool( $this->block_all_option_name );
	}
}
