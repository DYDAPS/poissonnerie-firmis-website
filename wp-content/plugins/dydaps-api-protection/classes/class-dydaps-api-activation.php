<?php
/**
 * Plugin activation and deactivation hooks.
 *
 * @package DYDAPS_API_Protection
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin activation and version compatibility checks.
 */
final class Dydaps_Api_Activation {
	/**
	 * Check plugin requirements on activation.
	 *
	 * Verifies WordPress and PHP versions match plugin requirements.
	 * Dies with error message if requirements are not met.
	 *
	 * @return void
	 */
	public static function check_requirements() {
		global $wp_version;

		// Check WordPress version.
		if ( version_compare( $wp_version, Dydaps_Api_Config::MINIMUM_WP_VERSION, '<' ) ) {
			wp_die(
				sprintf(
					/* translators: %1$s: Required WordPress version, %2$s: Current WordPress version */
					esc_html__( 'DYDAPS API Protection requires WordPress %1$s or later. You are running WordPress %2$s.', 'dydaps-api-protection' ),
					esc_html( Dydaps_Api_Config::MINIMUM_WP_VERSION ),
					esc_html( $wp_version )
				)
			);
		}

		// Check PHP version.
		if ( version_compare( PHP_VERSION, Dydaps_Api_Config::MINIMUM_PHP_VERSION, '<' ) ) {
			wp_die(
				sprintf(
					/* translators: %1$s: Required PHP version, %2$s: Current PHP version */
					esc_html__( 'DYDAPS API Protection requires PHP %1$s or later. You are running PHP %2$s.', 'dydaps-api-protection' ),
					esc_html( Dydaps_Api_Config::MINIMUM_PHP_VERSION ),
					esc_html( PHP_VERSION )
				)
			);
		}

		self::upgrade( true );
	}

	/** Apply idempotent defaults and record the current schema version. */
	/**
	 * Apply idempotent defaults and record the current schema version.
	 *
	 * @param bool $during_activation Whether the upgrade runs during activation.
	 * @return void
	 */
	/**
	 * Apply idempotent defaults and record the current schema version.
	 *
	 * @param bool $during_activation Whether the upgrade runs during activation.
	 * @return void
	 */
	public static function upgrade( $during_activation = false ) {
		$schema_version = get_option( Dydaps_Api_Config::OPTION_SCHEMA_VERSION, '' );
		if ( version_compare( (string) $schema_version, '1.4.0', '>=' ) ) {
			return;
		}

		// Existing sites retain their effective block-all setting. A site with no
		// DYDAPS configuration is new and starts with strict anonymous protection.
		$has_legacy_configuration = false;
		foreach ( array( Dydaps_Api_Config::OPTION_API_KEY_HASH, Dydaps_Api_Config::OPTION_PROTECTED_ROUTE_PREFIXES, Dydaps_Api_Config::OPTION_PUBLIC_ROUTE_PREFIXES, Dydaps_Api_Config::OPTION_BLOCK_ALL_REST_API, Dydaps_Api_Config::OPTION_ALLOW_INTERNAL_REST_REQUESTS, Dydaps_Api_Config::OPTION_ALLOWED_REST_NAMESPACES, Dydaps_Api_Config::OPTION_API_KEY_CREATED_AT, Dydaps_Api_Config::OPTION_LOGGING_ENABLED, Dydaps_Api_Config::OPTION_LOG_RETENTION_DAYS ) as $option_name ) {
			if ( false !== get_option( $option_name, false ) ) {
				$has_legacy_configuration = true;
				break;
			}
		}

		if ( $during_activation && ! $has_legacy_configuration ) {
			Dydaps_Api_Option_Store::set( Dydaps_Api_Config::OPTION_BLOCK_ALL_REST_API, '1' );
			Dydaps_Api_Option_Store::set( Dydaps_Api_Config::OPTION_LOGGING_ENABLED, '1' );
			Dydaps_Api_Option_Store::set( Dydaps_Api_Config::OPTION_LOG_RETENTION_DAYS, '7' );
		}

		Dydaps_Api_Option_Store::set( Dydaps_Api_Config::OPTION_SCHEMA_VERSION, '1.4.0' );
	}

	/**
	 * Run on plugin deactivation.
	 *
	 * Clears any transient data but keeps stored options.
	 *
	 * @return void
	 */
	public static function on_deactivation() {
		delete_transient( Dydaps_Api_Config::TRANSIENT_PLAIN_KEY );
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( Dydaps_Api_Event_Logger::CRON_HOOK );
		}
	}
}
