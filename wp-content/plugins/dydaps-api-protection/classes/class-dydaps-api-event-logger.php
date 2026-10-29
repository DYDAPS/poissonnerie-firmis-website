<?php
/**
 * Bounded, optional REST denial logging.
 *
 * @package DYDAPS_API_Protection
 */

defined( 'ABSPATH' ) || exit;

/** Stores bounded REST denial diagnostics without request credentials. */
final class Dydaps_Api_Event_Logger {
	const MAX_EVENTS    = 200;
	const CRON_HOOK     = 'dydaps_api_protection_cleanup_events';
	const TABLE_VERSION = '1';

	/** In-request signature used to avoid duplicate denial records.
	 *
	 * @var string|null
	 */
	private $authentication_event_signature;

	/** Whether storage initialization has run during this request.
	 *
	 * @var bool
	 */
	private static $storage_initialized = false;

	/** Whether the persistent event table is available.
	 *
	 * @var bool
	 */
	private static $database_storage_available = false;

	/** Register storage, cleanup, and native REST permission diagnostics. */
	public function register() {
		$this->ensure_storage();
		add_filter( 'rest_post_dispatch', array( $this, 'log_wordpress_denial' ), 1000, 3 );
		add_action( self::CRON_HOOK, array( $this, 'cleanup' ) );
		try {
			if ( $this->is_enabled() && ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
			}
		} catch ( Throwable $exception ) {
			// Scheduling failure must not interfere with WordPress boot.
			return;
		}
	}

	/**
	 * Record a denial without retaining secrets, query strings, or request bodies.
	 *
	 * @param string $method HTTP method.
	 * @param string $route REST route.
	 * @param int    $status HTTP status code.
	 * @param string $reason Sanitized denial reason.
	 * @param string $layer Security layer identifier.
	 * @param string $rule Matched protection rule.
	 * @param string $auth Authentication category.
	 * @param string $error_code Safe, recognized error code.
	 * @return void
	 */
	public function record( $method, $route, $status, $reason, $layer = 'dydaps', $rule = '', $auth = 'anonymous', $error_code = '' ) {
		try {
			if ( ! $this->is_enabled() ) {
				return;
			}
			$event = array(
				'time'       => time(),
				'method'     => substr( strtoupper( preg_replace( '/[^a-z]/i', '', (string) $method ) ), 0, 10 ),
				'route'      => substr( Dydaps_Api_Route_Utils::normalize_route_prefix( explode( '?', (string) $route, 2 )[0] ), 0, 512 ),
				'status'     => absint( $status ),
				'reason'     => sanitize_key( $reason ),
				'layer'      => sanitize_key( $layer ),
				'rule'       => sanitize_key( $rule ),
				'auth'       => sanitize_key( $auth ),
				'error_code' => self::safe_error_code( $error_code ),
			);
			if ( $this->has_database_storage() ) {
				$this->insert_event( $event );
				$this->enforce_limits();
				return;
			}
			$this->record_legacy_option_event( $event );
		} catch ( Throwable $exception ) {
			// Logging must never change the REST response.
			return;
		}
	}

	/** Capture final REST denials without misattributing plugin-generated errors.
	 *
	 * @param WP_REST_Response $response REST response.
	 * @param WP_REST_Server   $server REST server.
	 * @param WP_REST_Request  $request REST request.
	 * @return WP_REST_Response
	 */
	public function log_wordpress_denial( $response, $server, $request ) {
		try {
			if ( $response instanceof WP_REST_Response && $request instanceof WP_REST_Request && in_array( $response->get_status(), array( 401, 403 ), true ) ) {
				$data = $response->get_data();
				$code = is_array( $data ) && isset( $data['code'] ) && is_string( $data['code'] ) ? $data['code'] : '';
				if ( 0 !== strpos( $code, 'dydaps_api_protection_' ) ) {
					$diagnostic = self::classify_wordpress_error( $code, is_user_logged_in() );
					$signature  = $this->get_event_signature( $request->get_method(), $request->get_route(), $response->get_status(), $diagnostic['reason'] );
					if ( null !== $this->authentication_event_signature && hash_equals( $this->authentication_event_signature, $signature ) ) {
						$this->authentication_event_signature = null;
						return $response;
					}
					$this->authentication_event_signature = null;
					$this->record( $request->get_method(), $request->get_route(), $response->get_status(), $diagnostic['reason'], $diagnostic['layer'], '', $diagnostic['auth'], $diagnostic['error_code'] );
				}
			}
		} catch ( Throwable $exception ) {
			// Diagnostics must not alter the REST response.
			return $response;
		}
		return $response;
	}

	/** Record a WordPress authentication error and suppress its duplicate response event.
	 *
	 * @param string $method HTTP method.
	 * @param string $route REST route.
	 * @param int    $status HTTP status code.
	 * @param string $error_code WordPress error code.
	 * @return void
	 */
	public function log_authentication_error( $method, $route, $status, $error_code ) {
		try {
			$diagnostic                           = self::classify_wordpress_error( $error_code, false );
			$this->authentication_event_signature = $this->get_event_signature( $method, $route, $status, $diagnostic['reason'] );
			$this->record( $method, $route, $status, $diagnostic['reason'], $diagnostic['layer'], 'wordpress_authentication', $diagnostic['auth'], $diagnostic['error_code'] );
		} catch ( Throwable $exception ) {
			// A diagnostic failure must not change WordPress authentication.
			return;
		}
	}

	/** Classify known WordPress errors without retaining arbitrary error text.
	 *
	 * @param string $error_code Error code to classify.
	 * @param bool   $user_present Whether WordPress has a current user.
	 * @return array{reason:string,layer:string,auth:string,error_code:string}
	 */
	public static function classify_wordpress_error( $error_code, $user_present = false ) {
		$authentication_errors = array(
			'rest_cookie_invalid_nonce' => array( 'invalid_wordpress_nonce', 'wordpress_authentication', 'invalid_wordpress_authentication' ),
			'rest_not_logged_in'        => array( 'missing_wordpress_authentication', 'wordpress_authentication', 'missing_wordpress_authentication' ),
		);
		$capability_errors     = array( 'rest_cannot_create', 'rest_cannot_edit', 'rest_cannot_delete', 'rest_cannot_publish', 'rest_cannot_upload', 'rest_cannot_read' );
		if ( isset( $authentication_errors[ $error_code ] ) ) {
			$result               = array_combine( array( 'reason', 'layer', 'auth' ), $authentication_errors[ $error_code ] );
			$result['error_code'] = $error_code;
			return $result;
		}
		if ( in_array( $error_code, $capability_errors, true ) ) {
			return array(
				'reason'     => 'insufficient_wordpress_capability',
				'layer'      => 'wordpress_permission',
				'auth'       => $user_present ? 'wordpress_user' : 'unknown',
				'error_code' => $error_code,
			);
		}
		if ( 'rest_forbidden' === $error_code || 'rest_forbidden_context' === $error_code ) {
			return array(
				'reason'     => 'wordpress_route_permission_denied',
				'layer'      => 'wordpress_permission',
				'auth'       => $user_present ? 'wordpress_user' : 'unknown',
				'error_code' => $error_code,
			);
		}
		return array(
			'reason'     => 'unknown_wordpress_denial',
			'layer'      => 'wordpress_rest_response',
			'auth'       => 'unknown',
			'error_code' => '',
		);
	}

	/** Return one filtered page of events and its total count.
	 *
	 * @param array $filters Validated route filters.
	 * @param int   $page Requested page number.
	 * @param int   $per_page Maximum records to return.
	 * @return array{events:array,total:int,page:int,per_page:int,pages:int}
	 */
	public function get_events_page( $filters = array(), $page = 1, $per_page = 20 ) {
		$page     = max( 1, absint( $page ) );
		$per_page = min( self::MAX_EVENTS, max( 1, absint( $per_page ) ) );
		try {
			if ( $this->has_database_storage() ) {
				$where = $this->build_filter_sql( $filters );
				global $wpdb;
				$table = $this->get_table_name();
				// The query is uncached because the live log can change between page views.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The table name is generated from the WordPress prefix and all filter values are passed to prepare().
				$count  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table}{$where['sql']}", $where['values'] ) );
				$pages  = max( 1, (int) ceil( $count / $per_page ) );
				$page   = min( $page, $pages );
				$offset = ( $page - 1 ) * $per_page;
				$sql    = "SELECT id, event_time AS time, method, route, status, reason, layer, rule, auth, error_code FROM {$table}{$where['sql']} ORDER BY event_time DESC, id DESC LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from the WordPress prefix.
				$values = array_merge( $where['values'], array( $per_page, $offset ) );
				// The query is uncached so filters and event deletions take effect immediately.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Table name and where clauses are generated internally; dynamic values are prepared.
				$events = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );
				return array(
					'events'   => is_array( $events ) ? $events : array(),
					'total'    => max( 0, $count ),
					'page'     => $page,
					'per_page' => $per_page,
					'pages'    => $pages,
				);
			}
			$events   = $this->get_legacy_events();
			$filtered = $this->filter_legacy_events( $events, $filters );
			$total    = count( $filtered );
			$pages    = max( 1, (int) ceil( $total / $per_page ) );
			$page     = min( $page, $pages );
			$offset   = ( $page - 1 ) * $per_page;
			return array(
				'events'   => array_slice( $filtered, $offset, $per_page ),
				'total'    => $total,
				'page'     => $page,
				'per_page' => $per_page,
				'pages'    => $pages,
			);
		} catch ( Throwable $exception ) {
			return array(
				'events'   => array(),
				'total'    => 0,
				'page'     => 1,
				'per_page' => $per_page,
				'pages'    => 1,
			);
		}
	}

	/** Return a bounded recent event list for backwards compatibility. */
	public function get_events() {
		return $this->get_events_page( array(), 1, self::MAX_EVENTS )['events'];
	}

	/** Count events matching a supported age threshold.
	 *
	 * @param int $days Event age in days.
	 * @return int
	 */
	public function count_older_than( $days ) {
		$days = absint( $days );
		if ( ! in_array( $days, array( 7, 14, 30 ), true ) ) {
			return 0;
		}
		try {
			if ( $this->has_database_storage() ) {
				global $wpdb;
				$table = $this->get_table_name();
				// This count is live so the action preview reflects the selected age.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from the WordPress prefix; the age value is prepared.
				return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE event_time < %d", time() - DAY_IN_SECONDS * $days ) );
			}
			return count(
				array_filter(
					$this->get_legacy_events(),
					static function ( $event ) use ( $days ) {
						return $event['time'] < time() - DAY_IN_SECONDS * $days;
					}
				)
			);
		} catch ( Throwable $exception ) {
			return 0;
		}
	}

	/** Delete only events older than an explicitly supported age threshold.
	 *
	 * @param int $days Event age in days.
	 * @return int Number of deleted events.
	 */
	public function delete_older_than( $days ) {
		$days = absint( $days );
		if ( ! in_array( $days, array( 7, 14, 30 ), true ) ) {
			return 0;
		}
		try {
			$threshold = time() - DAY_IN_SECONDS * $days;
			if ( $this->has_database_storage() ) {
				global $wpdb;
				$table = $this->get_table_name();
				// Only bounded plugin-owned event rows are affected.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from the WordPress prefix; the timestamp is prepared.
				return max( 0, (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE event_time < %d", $threshold ) ) );
			}
			$events = array_values(
				array_filter(
					$this->get_legacy_events(),
					static function ( $event ) use ( $threshold ) {
						return $event['time'] >= $threshold;
					}
				)
			);
			$before = count( $this->get_legacy_events() );
			Dydaps_Api_Option_Store::set( Dydaps_Api_Config::OPTION_SECURITY_EVENTS, $events );
			return $before - count( $events );
		} catch ( Throwable $exception ) {
			return 0;
		}
	}

	/** Apply event retention and maximum record count. */
	public function cleanup() {
		try {
			if ( $this->has_database_storage() ) {
				$this->enforce_limits();
				return;
			}
			$events = $this->get_legacy_events();
			$days   = self::get_retention_days();
			$events = array_values(
				array_filter(
					$events,
					static function ( $event ) use ( $days ) {
						return $event['time'] >= time() - DAY_IN_SECONDS * $days;
					}
				)
			);
			Dydaps_Api_Option_Store::set( Dydaps_Api_Config::OPTION_SECURITY_EVENTS, array_slice( $events, -self::get_max_events() ) );
		} catch ( Throwable $exception ) {
			// Cleanup is best effort on shared hosting.
			return;
		}
	}

	/** Remove all activity events while preserving all plugin settings. */
	public function clear() {
		try {
			if ( $this->has_database_storage() ) {
				global $wpdb;
				$table = $this->get_table_name();
				// Clear only the plugin's own event table; preferences remain in options.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from the WordPress prefix.
				$wpdb->query( "DELETE FROM {$table}" );
			}
			Dydaps_Api_Option_Store::set( Dydaps_Api_Config::OPTION_SECURITY_EVENTS, array() );
		} catch ( Throwable $exception ) {
			// Manual cleanup must not break the administration page.
			return;
		}
	}

	/** Ensure database storage exists and import the bounded legacy option once. */
	private function ensure_storage() {
		if ( self::$storage_initialized || ! isset( $GLOBALS['wpdb'] ) || ! is_object( $GLOBALS['wpdb'] ) ) {
			return;
		}
		self::$storage_initialized = true;
		try {
			global $wpdb;
			$table   = $this->get_table_name();
			$version = get_option( Dydaps_Api_Config::OPTION_EVENT_STORAGE_VERSION, '' );
			if ( self::TABLE_VERSION === $version ) {
				// Confirm the plugin-owned table exists before attempting event queries.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One existence check gates all subsequent database work in this request.
				self::$database_storage_available = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
				if ( self::$database_storage_available ) {
					return;
				}
			}
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$charset = $wpdb->get_charset_collate();
			$sql     = "CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_time bigint(20) unsigned NOT NULL,
				method varchar(10) NOT NULL DEFAULT '',
				route varchar(512) NOT NULL DEFAULT '',
				status smallint(5) unsigned NOT NULL DEFAULT 0,
				reason varchar(64) NOT NULL DEFAULT '',
				layer varchar(64) NOT NULL DEFAULT '',
				rule varchar(64) NOT NULL DEFAULT '',
				auth varchar(64) NOT NULL DEFAULT '',
				error_code varchar(64) NOT NULL DEFAULT '',
				PRIMARY KEY  (id),
				KEY event_time (event_time),
				KEY layer_time (layer,event_time),
				KEY status_time (status,event_time),
				KEY method_time (method,event_time)
			) {$charset};";
			// dbDelta keeps this plugin-owned table schema upgrade-safe and idempotent.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- This is the plugin's own event table; dbDelta handles WordPress-compatible schema updates.
			dbDelta( $sql );
			// Table existence is checked after schema creation to keep the legacy option as fallback.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The result is needed to determine whether writes can safely use the event table.
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $exists !== $table ) {
				return;
			}
			self::$database_storage_available = true;
			if ( self::TABLE_VERSION !== $version ) {
				$legacy = get_option( Dydaps_Api_Config::OPTION_SECURITY_EVENTS, array() );
				if ( is_array( $legacy ) ) {
					foreach ( $this->sanitize_events( array_slice( $legacy, -self::MAX_EVENTS ), time() - DAY_IN_SECONDS * self::get_retention_days() ) as $event ) {
						if ( false === $this->insert_event( $event ) ) {
							return;
						}
					}
				}
				Dydaps_Api_Option_Store::set( Dydaps_Api_Config::OPTION_EVENT_STORAGE_VERSION, self::TABLE_VERSION );
				Dydaps_Api_Option_Store::set( Dydaps_Api_Config::OPTION_SECURITY_EVENTS, array() );
				$this->enforce_limits();
			}
		} catch ( Throwable $exception ) {
			// The legacy option remains available if table creation or migration fails.
			return;
		}
	}

	/** Insert one already-sanitized event.
	 *
	 * @param array $event Sanitized event record.
	 * @return int|false
	 */
	private function insert_event( $event ) {
		global $wpdb;
		// The plugin-owned table requires direct inserts for bounded, indexed pagination.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Event rows are append-only and the result is immediately needed for safe migration.
		return $wpdb->insert(
			$this->get_table_name(),
			array(
				'event_time' => $event['time'],
				'method'     => $event['method'],
				'route'      => $event['route'],
				'status'     => $event['status'],
				'reason'     => $event['reason'],
				'layer'      => $event['layer'],
				'rule'       => $event['rule'],
				'auth'       => $event['auth'],
				'error_code' => $event['error_code'],
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/** Enforce configured age and size limits with bounded database work.
	 *
	 * @return void
	 */
	private function enforce_limits() {
		global $wpdb;
		$table = $this->get_table_name();
		// Each cleanup query can delete no more than the hard cap per request.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from the WordPress prefix; the values are prepared.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE event_time < %d LIMIT %d", time() - DAY_IN_SECONDS * self::get_retention_days(), self::MAX_EVENTS ) );
		$limit = self::get_max_events();
		// The subquery retains only the configured number, with a hard maximum of 200.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is generated from the WordPress prefix; the values are prepared.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id NOT IN (SELECT id FROM (SELECT id FROM {$table} ORDER BY id DESC LIMIT %d) AS retained) LIMIT %d", $limit, self::MAX_EVENTS ) );
	}

	/** Return whether the plugin event table is available. */
	private function has_database_storage() {
		$this->ensure_storage();
		return self::$database_storage_available;
	}

	/** Get the table name from WordPress's configured database prefix. */
	private function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'dydaps_api_events';
	}

	/** Build validated SQL filters for a page query.
	 *
	 * @param array $filters Requested filters.
	 * @return array{sql:string,values:array}
	 */
	private function build_filter_sql( $filters ) {
		$clauses = array( 'event_time >= %d' );
		$values  = array( 0 );
		$method  = isset( $filters['method'] ) ? strtoupper( sanitize_key( $filters['method'] ) ) : '';
		if ( in_array( $method, array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
			$clauses[] = 'method = %s';
			$values[]  = $method;
		}
		$status = isset( $filters['status'] ) ? absint( $filters['status'] ) : 0;
		if ( in_array( $status, array( 401, 403 ), true ) ) {
			$clauses[] = 'status = %d';
			$values[]  = $status;
		}
		$layer = isset( $filters['layer'] ) ? sanitize_key( $filters['layer'] ) : '';
		if ( in_array( $layer, array( 'dydaps_anonymous_block', 'dydaps_api_key', 'wordpress_authentication', 'wordpress_permission', 'wordpress_rest_response' ), true ) ) {
			$clauses[] = 'layer = %s';
			$values[]  = $layer;
		}
		foreach ( array(
			'from' => '>=',
			'to'   => '<=',
		) as $field => $operator ) {
			$value = isset( $filters[ $field ] ) ? sanitize_text_field( $filters[ $field ] ) : '';
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
				$timestamp = strtotime( $value . ( 'from' === $field ? ' 00:00:00' : ' 23:59:59' ) );
				if ( false !== $timestamp ) {
					$clauses[] = 'event_time ' . $operator . ' %d';
					$values[]  = $timestamp;
				}
			}
		}
		$sql = empty( $clauses ) ? '' : ' WHERE ' . implode( ' AND ', $clauses );
		return array(
			'sql'    => $sql,
			'values' => $values,
		);
	}

	/** Apply validated filters to the small legacy option during migration fallback.
	 *
	 * @param array $events Sanitized event records.
	 * @param array $filters Validated filters.
	 * @return array
	 */
	private function filter_legacy_events( $events, $filters ) {
		return array_values(
			array_filter(
				$events,
				static function ( $event ) use ( $filters ) {
					if ( ! empty( $filters['method'] ) && strtoupper( sanitize_key( $filters['method'] ) ) !== $event['method'] ) {
						return false; }
					if ( ! empty( $filters['status'] ) && absint( $filters['status'] ) !== $event['status'] ) {
						return false; }
					if ( ! empty( $filters['layer'] ) && sanitize_key( $filters['layer'] ) !== $event['layer'] ) {
						return false; }
					if ( ! empty( $filters['from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filters['from'] ) && $event['time'] < strtotime( $filters['from'] . ' 00:00:00' ) ) {
						return false; }
					if ( ! empty( $filters['to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $filters['to'] ) && $event['time'] > strtotime( $filters['to'] . ' 23:59:59' ) ) {
						return false; }
					return true;
				}
			)
		);
	}

	/** Return validated, unexpired legacy option events. */
	private function get_legacy_events() {
		$events = get_option( Dydaps_Api_Config::OPTION_SECURITY_EVENTS, array() );
		return is_array( $events ) ? array_reverse( $this->sanitize_events( array_slice( $events, -self::MAX_EVENTS ), time() - DAY_IN_SECONDS * self::get_retention_days() ) ) : array();
	}

	/** Append an event to the bounded legacy fallback.
	 *
	 * @param array $event Sanitized event record.
	 * @return void
	 */
	private function record_legacy_option_event( $event ) {
		$events   = get_option( Dydaps_Api_Config::OPTION_SECURITY_EVENTS, array() );
		$events   = is_array( $events ) ? $events : array();
		$events   = $this->sanitize_events( $events, time() - DAY_IN_SECONDS * self::get_retention_days() );
		$events[] = $event;
		Dydaps_Api_Option_Store::set( Dydaps_Api_Config::OPTION_SECURITY_EVENTS, array_slice( $events, -self::get_max_events() ) );
	}

	/** Build a non-secret event identity for duplicate suppression.
	 *
	 * @param string $method HTTP method.
	 * @param string $route REST route.
	 * @param int    $status HTTP status code.
	 * @param string $reason Diagnostic category.
	 * @return string
	 */
	private function get_event_signature( $method, $route, $status, $reason ) {
		return hash( 'sha256', strtoupper( (string) $method ) . "\0" . (string) $route . "\0" . absint( $status ) . "\0" . sanitize_key( $reason ) );
	}

	/** Allow only error codes with known, non-sensitive diagnostic value.
	 *
	 * @param mixed $code Candidate error code.
	 * @return string
	 */
	private static function safe_error_code( $code ) {
		$known = array( 'rest_cookie_invalid_nonce', 'rest_not_logged_in', 'rest_cannot_create', 'rest_cannot_edit', 'rest_cannot_delete', 'rest_cannot_publish', 'rest_cannot_upload', 'rest_cannot_read', 'rest_forbidden', 'rest_forbidden_context', 'dydaps_api_protection_forbidden', 'dydaps_api_protection_blocked' );
		return is_string( $code ) && in_array( $code, $known, true ) ? $code : '';
	}

	/** Validate configured retention days. */
	private static function get_retention_days() {
		$days = absint( get_option( Dydaps_Api_Config::OPTION_LOG_RETENTION_DAYS, 7 ) );
		return in_array( $days, array( 7, 14, 30, 90 ), true ) ? $days : 7;
	}

	/** Validate configured maximum event count. */
	private static function get_max_events() {
		$maximum = absint( get_option( Dydaps_Api_Config::OPTION_LOG_MAX_EVENTS, self::MAX_EVENTS ) );
		return in_array( $maximum, array( 50, 100, self::MAX_EVENTS ), true ) ? $maximum : self::MAX_EVENTS;
	}

	/** Check persistent logging preference. */
	private function is_enabled() {
		return Dydaps_Api_Option_Store::get_bool( Dydaps_Api_Config::OPTION_LOGGING_ENABLED );
	}

	/** Sanitize stored legacy events and remove expired or malformed records.
	 *
	 * @param array $events Stored event records.
	 * @param int   $threshold Earliest permitted timestamp.
	 * @return array
	 */
	private function sanitize_events( $events, $threshold ) {
		$clean = array();
		foreach ( $events as $event ) {
			if ( ! is_array( $event ) || ! isset( $event['time'], $event['method'], $event['route'], $event['status'], $event['reason'], $event['layer'] ) ) {
				continue; }
			foreach ( array( 'time', 'method', 'route', 'status', 'reason', 'layer' ) as $field ) {
				if ( ! is_scalar( $event[ $field ] ) ) {
					continue 2; }
			}
			$time = absint( $event['time'] );
			if ( $time < $threshold ) {
				continue; }
			$clean[] = array(
				'time'       => $time,
				'method'     => substr( strtoupper( preg_replace( '/[^a-z]/i', '', (string) $event['method'] ) ), 0, 10 ),
				'route'      => substr( Dydaps_Api_Route_Utils::normalize_route_prefix( explode( '?', (string) $event['route'], 2 )[0] ), 0, 512 ),
				'status'     => absint( $event['status'] ),
				'reason'     => sanitize_key( (string) $event['reason'] ),
				'layer'      => sanitize_key( (string) $event['layer'] ),
				'rule'       => isset( $event['rule'] ) && is_scalar( $event['rule'] ) ? sanitize_key( (string) $event['rule'] ) : '',
				'auth'       => isset( $event['auth'] ) && is_scalar( $event['auth'] ) ? sanitize_key( (string) $event['auth'] ) : '',
				'error_code' => isset( $event['error_code'] ) ? self::safe_error_code( $event['error_code'] ) : '',
			);
		}
		return array_slice( $clean, -self::MAX_EVENTS );
	}
}
