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
					esc_html__( 'DYDAPS API Protection requires WordPress %1$s or later. You are running WordPress %2$s.', Dydaps_Api_Config::TEXT_DOMAIN ),
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
					esc_html__( 'DYDAPS API Protection requires PHP %1$s or later. You are running PHP %2$s.', Dydaps_Api_Config::TEXT_DOMAIN ),
					esc_html( Dydaps_Api_Config::MINIMUM_PHP_VERSION ),
					esc_html( PHP_VERSION )
				)
			);
		}
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
	}
}
