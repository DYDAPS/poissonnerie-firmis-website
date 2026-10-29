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

	/**
	 * Option name controlling same-process dispatch behavior.
	 *
	 * @var string
	 */
	private $allow_internal_option_name;

	/**
	 * Option containing exact namespaces exempt from DYDAPS checks.
	 *
	 * @var string
	 */
	private $allowed_namespaces_option_name;

	/**
	 * Optional denial logger.
	 *
	 * @var Dydaps_Api_Event_Logger|null
	 */
	private $event_logger;

	/**
	 * Constructor.
	 *
	 * @param Dydaps_Api_Key_Service       $api_key_service           API key service.
	 * @param string[]                     $protected_route_prefixes  Default protected route prefixes.
	 * @param string                       $protected_route_option_name Option storing protected prefixes.
	 * @param string                       $block_all_option_name       Option toggling full REST API blocking.
	 * @param string                       $public_route_option_name    Option storing public prefixes.
	 * @param string                       $allow_internal_option_name  Option controlling internal dispatch behavior.
	 * @param string                       $allowed_namespaces_option_name Option storing allowed REST namespaces.
	 * @param Dydaps_Api_Event_Logger|null $event_logger Optional denial logger.
	 */
	public function __construct( Dydaps_Api_Key_Service $api_key_service, array $protected_route_prefixes, $protected_route_option_name, $block_all_option_name, $public_route_option_name, $allow_internal_option_name = '', $allowed_namespaces_option_name = '', $event_logger = null ) {
		$this->api_key_service                  = $api_key_service;
		$this->default_protected_route_prefixes = array_map(
			array( Dydaps_Api_Route_Utils::class, 'normalize_route_prefix' ),
			$protected_route_prefixes
		);
		$this->protected_route_option_name      = $protected_route_option_name;
		$this->block_all_option_name            = $block_all_option_name;
		$this->public_route_option_name         = $public_route_option_name;
		$this->allow_internal_option_name       = $allow_internal_option_name;
		$this->allowed_namespaces_option_name   = $allowed_namespaces_option_name;
		$this->event_logger                     = $event_logger;
	}

	/**
	 * Apply anonymous REST restrictions after WordPress authentication.
	 *
	 * Returning the incoming result preserves authentication behavior from core
	 * and third-party plugins for routes we do not explicitly own.
	 *
	 * @param WP_Error|null|true $result Existing auth result.
	 * @return WP_Error|null|true
	 */
	public function protect_routes( $result ) {
		if ( $result instanceof WP_Error ) {
			if ( $this->event_logger instanceof Dydaps_Api_Event_Logger && method_exists( $result, 'get_error_code' ) ) {
				$this->event_logger->log_authentication_error( $this->get_current_method(), $this->get_current_route(), $this->get_error_status( $result ), $result->get_error_code() );
			}
			return $result;
		}

		// Core validates REST cookies and nonces before this filter runs. A valid
		// WordPress identity proceeds to native endpoint permission callbacks.
		// This grants no capabilities and does not override those callbacks.
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			return $result;
		}

		$route = $this->get_current_route();

		if ( '' === $route ) {
			if ( $this->is_block_all_enabled() ) {
				$this->log_denial( $this->get_current_method(), '', 403, 'anonymous_access_denied', 'dydaps_anonymous_block', 'strict_mode' );
				return new WP_Error(
					'dydaps_api_protection_blocked',
					__( 'This REST API endpoint is blocked.', 'dydaps-api-protection' ),
					array( 'status' => 403 )
				);
			}
			return $result;
		}
		if ( $this->has_anonymous_exception( $route, $this->get_current_method() ) ) {
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
				$this->log_denial( $this->get_current_method(), $route, 403, 'anonymous_access_denied', 'dydaps_anonymous_block', 'strict_mode' );
				return new WP_Error(
					'dydaps_api_protection_blocked',
					__( 'This REST API endpoint is blocked.', 'dydaps-api-protection' ),
					array(
						'status' => 403,
					)
				);
			}

			return $result;
		}

		$api_key = $this->get_header_value( $this->api_key_service->get_header_name() );
		if ( $this->api_key_service->verify_key( $api_key ) ) {
			// An API key authenticates the client to DYDAPS, not a WordPress user.
			// Preserve the original auth result so native permission checks run as-is.
			return $result;
		}
		$this->log_denial( $this->get_current_method(), $route, 403, $this->get_api_key_denial_reason( $api_key ), 'dydaps_api_key', 'protected_route' );

		return new WP_Error(
			'dydaps_api_protection_forbidden',
			__( 'A valid API key is required to access this endpoint.', 'dydaps-api-protection' ),
			array(
				'status' => 403,
			)
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
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			return $result;
		}

		// This option bypasses only DYDAPS. Returning null continues normal route dispatch
		// and leaves the endpoint's permission_callback untouched.
		if ( $this->is_internal_bypass_enabled() ) {
			return $result;
		}

		$route = Dydaps_Api_Route_Utils::normalize_route_prefix( $request->get_route() );
		if ( '' === $route ) {
			if ( ! $this->is_block_all_enabled() ) {
				return $result;
			}
			$this->log_denial( $request->get_method(), '', 403, 'anonymous_access_denied', 'dydaps_anonymous_block', 'strict_mode' );
			return new WP_Error(
				'dydaps_api_protection_blocked',
				__( 'This REST API endpoint is blocked.', 'dydaps-api-protection' ),
				array( 'status' => 403 )
			);
		}
		if ( $this->has_anonymous_exception( $route, $request->get_method() ) ) {
			return $result;
		}
		if ( $this->is_public_route( $route ) || $this->is_allowed_namespace_route( $route ) ) {
			return $result;
		}

		if ( ! $this->is_protected_route( $route ) ) {
			if ( ! $this->is_block_all_enabled() ) {
				return $result;
			}
			$this->log_denial( $request->get_method(), $route, 403, 'anonymous_access_denied', 'dydaps_anonymous_block', 'strict_mode' );

			return new WP_Error(
				'dydaps_api_protection_blocked',
				__( 'This REST API endpoint is blocked.', 'dydaps-api-protection' ),
				array( 'status' => 403 )
			);
		}

		$api_key = $this->get_request_header_value( $request, $this->api_key_service->get_header_name() );
		if ( $this->api_key_service->verify_key( $api_key ) ) {
			// Continue dispatch; this key does not establish a WordPress identity.
			return $result;
		}
		$this->log_denial( $request->get_method(), $route, 403, $this->get_api_key_denial_reason( $api_key ), 'dydaps_api_key', 'protected_route' );

		return new WP_Error(
			'dydaps_api_protection_forbidden',
			__( 'A valid API key is required to access this endpoint.', 'dydaps-api-protection' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Best effort, credential-free event logging.
	 *
	 * @param string $method HTTP method.
	 * @param string $route REST route.
	 * @param int    $status HTTP status.
	 * @param string $reason Denial reason.
	 * @param string $layer Security layer.
	 * @param string $rule Matched rule.
	 * @param string $auth Authentication category.
	 * @return void
	 */
	private function log_denial( $method, $route, $status, $reason, $layer, $rule, $auth = 'anonymous' ) {
		if ( $this->event_logger instanceof Dydaps_Api_Event_Logger ) {
			$error_code = 'missing_dydaps_api_key' === $reason || 'invalid_dydaps_api_key' === $reason ? 'dydaps_api_protection_forbidden' : 'dydaps_api_protection_blocked';
			$this->event_logger->record( $method, $route, $status, $reason, $layer, $rule, $auth, $error_code );
		}
	}

	/**
	 * Resolve an HTTP error status without recording its message or data.
	 *
	 * @param WP_Error $error Authentication error.
	 * @return int
	 */
	private function get_error_status( $error ) {
		$data = $error->get_error_data();
		return is_array( $data ) && isset( $data['status'] ) ? absint( $data['status'] ) : 401;
	}

	/**
	 * Determine whether a route falls under a configured exact namespace.
	 *
	 * @param string $route REST route.
	 * @return bool
	 */
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

	/**
	 * Match an enabled, method-specific exception.
	 *
	 * @param string $route REST route.
	 * @param string $method HTTP method.
	 * @return bool
	 */
	private function has_anonymous_exception( $route, $method ) {
		$exceptions = get_option( Dydaps_Api_Config::OPTION_ANONYMOUS_EXCEPTIONS, array() );
		if ( ! is_array( $exceptions ) ) {
			return false;
		}
		foreach ( $exceptions as $exception ) {
			if ( is_array( $exception ) && Dydaps_Api_Route_Utils::exception_matches( $route, $method, $exception ) ) {
				return true;
			}
		}
		return false;
	}

	/** Resolve the HTTP method for the in-flight request. */
	private function get_current_method() {
		// Core applies query-string method overrides before dispatch, and then
		// header overrides when no query override is present. Match that effective
		// method here so an exception cannot authorize a different operation.
		// Query overrides only choose the effective method; no state changes here. The sanitized method is later matched against the explicit allowlist.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- WordPress REST method override input is not a plugin settings mutation.
		if ( isset( $_GET['_method'] ) ) {
			$method = is_string( $_GET['_method'] ) ? sanitize_text_field( wp_unslash( $_GET['_method'] ) ) : '';
		} elseif ( isset( $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ) ) {
			$method = is_string( $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ) ) : '';
		} else {
			$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return is_string( $method ) ? strtoupper( $method ) : '';
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
	/**
	 * Recognize the current dispatch only when core rest_do_request() called it.
	 *
	 * @param WP_REST_Server  $server REST server.
	 * @param WP_REST_Request $request REST request.
	 * @return bool
	 */
	private function is_rest_do_request_dispatch( $server, $request ) {
		// Core exposes no request-origin marker. Inspect only the short direct call
		// chain needed to distinguish rest_do_request() from HTTP dispatch.
		// Core exposes no origin marker; the bounded trace distinguishes its direct internal call.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Required because WordPress exposes no safer dispatch-origin marker.
		$trace = debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT, 8 );
		$count = count( $trace );
		for ( $index = 0; $index < $count; $index++ ) {
			$frame = $trace[ $index ];
			if ( isset( $frame['class'], $frame['function'] ) && 'WP_REST_Server' === $frame['class'] && 'dispatch' === $frame['function'] ) {
				$caller = isset( $trace[ $index + 1 ] ) ? $trace[ $index + 1 ] : array();
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
		return $this->get_request_header_value( null, $header_name );
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
			// The value is type-checked below, then unslashed and sanitized before use.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$value = isset( $_SERVER[ $server_key ] ) ? wp_unslash( $_SERVER[ $server_key ] ) : null;
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
		return Dydaps_Api_Option_Store::get_route_prefixes( $this->public_route_option_name, array() );
	}

	/**
	 * Check whether full REST API blocking is enabled.
	 *
	 * @return bool
	 */
	private function is_block_all_enabled() {
		return Dydaps_Api_Option_Store::get_bool( $this->block_all_option_name );
	}

	/**
	 * Classify API key failures without retaining the submitted credential.
	 *
	 * @param mixed $api_key Submitted API key value.
	 * @return string
	 */
	private function get_api_key_denial_reason( $api_key ) {
		return is_string( $api_key ) && '' !== trim( $api_key ) ? 'invalid_dydaps_api_key' : 'missing_dydaps_api_key';
	}
}
