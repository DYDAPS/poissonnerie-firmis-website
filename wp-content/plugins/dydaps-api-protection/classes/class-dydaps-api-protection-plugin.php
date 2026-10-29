<?php
/**
 * Main plugin bootstrap and dependency wiring.
 *
 * @package DYDAPS_API_Protection
 */

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates plugin services while keeping the bootstrap file minimal.
 */
final class Dydaps_Api_Protection_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * API key service.
	 *
	 * @var Dydaps_Api_Key_Service|null
	 */
	private $api_key_service = null;

	/**
	 * REST protection service.
	 *
	 * @var Dydaps_Api_Rest_Protection|null
	 */
	private $rest_protection = null;

	/**
	 * Admin page controller.
	 *
	 * @var Dydaps_Api_Admin_Page|null
	 */
	private $admin_page = null;

	/**
	 * REST denial logger.
	 *
	 * @var Dydaps_Api_Event_Logger|null
	 */
	private $event_logger = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 *
	 * Hook registration happens here so dependencies are created lazily and
	 * remain easy to test in isolation.
	 *
	 * @return void
	 */
	public function run() {
		Dydaps_Api_Activation::upgrade();
		$this->load_textdomain();
		$this->get_event_logger()->register();
		// Run after WordPress core's cookie authentication and REST nonce check.
		add_filter( 'rest_authentication_errors', array( $this->get_rest_protection(), 'protect_routes' ), PHP_INT_MAX );
		add_filter( 'rest_pre_dispatch', array( $this->get_rest_protection(), 'protect_internal_dispatch' ), 20, 3 );
		add_filter( 'plugin_action_links_' . plugin_basename( DYDAPS_API_PROTECTION_FILE ), array( $this, 'add_plugin_action_links' ) );
		add_filter( 'all_plugins', array( $this, 'filter_plugin_row_text' ) );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this->get_admin_page(), 'enqueue_assets' ) );
			add_action( 'admin_post_dydaps_api_protection_generate', array( $this->get_admin_page(), 'handle_generate' ) );
			add_action( 'admin_post_dydaps_api_protection_rotate', array( $this->get_admin_page(), 'handle_rotate' ) );
			add_action( 'admin_post_dydaps_api_protection_revoke', array( $this->get_admin_page(), 'handle_revoke' ) );
			add_action( 'admin_post_dydaps_api_protection_save_routes', array( $this->get_admin_page(), 'handle_save_routes' ) );
			add_action( 'admin_post_dydaps_api_protection_save_internal', array( $this->get_admin_page(), 'handle_save_internal' ) );
			add_action( 'admin_post_dydaps_api_protection_save_namespaces', array( $this->get_admin_page(), 'handle_save_namespaces' ) );
			add_action( 'admin_post_dydaps_api_protection_save_exceptions', array( $this->get_admin_page(), 'handle_save_exceptions' ) );
			add_action( 'admin_post_dydaps_api_protection_save_logging', array( $this->get_admin_page(), 'handle_save_logging' ) );
			add_action( 'admin_post_dydaps_api_protection_clear_logs', array( $this->get_admin_page(), 'handle_clear_logs' ) );
			add_action( 'admin_post_dydaps_api_protection_delete_old_logs', array( $this->get_admin_page(), 'handle_delete_old_logs' ) );
			add_action( 'admin_post_dydaps_api_protection_apply_recommendation', array( $this->get_admin_page(), 'handle_apply_recommendation' ) );
		}
	}

	/**
	 * Load plugin translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'dydaps-api-protection',
			false,
			dirname( plugin_basename( DYDAPS_API_PROTECTION_FILE ) ) . '/languages/'
		);
	}

	/**
	 * Register the settings page.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		add_options_page(
			__( 'DYDAPS API Protection', 'dydaps-api-protection' ),
			__( 'DYDAPS API Protection', 'dydaps-api-protection' ),
			'manage_options',
			Dydaps_Api_Config::ADMIN_PAGE_SLUG,
			array( $this->get_admin_page(), 'render' )
		);
	}

	/**
	 * Add a direct settings link on the plugins list table.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public function add_plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'options-general.php?page=' . Dydaps_Api_Config::ADMIN_PAGE_SLUG ) ),
			esc_html__( 'Settings', 'dydaps-api-protection' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Translate plugin list row labels derived from the plugin header.
	 *
	 * WordPress does not automatically localize custom plugin headers from local
	 * plugin files in the same way it localizes runtime strings, so we override
	 * the visible row labels for this plugin explicitly.
	 *
	 * @param array<string, array<string, mixed>> $plugins Installed plugins.
	 * @return array<string, array<string, mixed>>
	 */
	public function filter_plugin_row_text( $plugins ) {
		$plugin_basename = plugin_basename( DYDAPS_API_PROTECTION_FILE );

		if ( ! isset( $plugins[ $plugin_basename ] ) || ! is_array( $plugins[ $plugin_basename ] ) ) {
			return $plugins;
		}

		$plugins[ $plugin_basename ]['Name']        = __( 'DYDAPS API Protection', 'dydaps-api-protection' );
		$plugins[ $plugin_basename ]['Description'] = __( 'Protect anonymous WordPress REST API access with configurable exceptions and API keys.', 'dydaps-api-protection' );

		return $plugins;
	}

	/**
	 * Get the API key service.
	 *
	 * @return Dydaps_Api_Key_Service
	 */
	private function get_api_key_service() {
		if ( null === $this->api_key_service ) {
			$this->api_key_service = new Dydaps_Api_Key_Service(
				Dydaps_Api_Config::OPTION_API_KEY_HASH,
				Dydaps_Api_Config::OPTION_API_KEY_CREATED_AT,
				Dydaps_Api_Config::OPTION_API_KEY_LAST_ROTATED_AT,
				Dydaps_Api_Config::HEADER_NAME
			);
		}

		return $this->api_key_service;
	}

	/**
	 * Get the REST protection service.
	 *
	 * @return Dydaps_Api_Rest_Protection
	 */
	private function get_rest_protection() {
		if ( null === $this->rest_protection ) {
			$this->rest_protection = new Dydaps_Api_Rest_Protection(
				$this->get_api_key_service(),
				Dydaps_Api_Config::DEFAULT_PROTECTED_ROUTE_PREFIXES,
				Dydaps_Api_Config::OPTION_PROTECTED_ROUTE_PREFIXES,
				Dydaps_Api_Config::OPTION_BLOCK_ALL_REST_API,
				Dydaps_Api_Config::OPTION_PUBLIC_ROUTE_PREFIXES,
				Dydaps_Api_Config::OPTION_ALLOW_INTERNAL_REST_REQUESTS,
				Dydaps_Api_Config::OPTION_ALLOWED_REST_NAMESPACES,
				$this->get_event_logger()
			);
		}

		return $this->rest_protection;
	}

	/**
	 * Get the admin page controller.
	 *
	 * @return Dydaps_Api_Admin_Page
	 */
	private function get_admin_page() {
		if ( null === $this->admin_page ) {
			$this->admin_page = new Dydaps_Api_Admin_Page(
				$this->get_api_key_service(),
				Dydaps_Api_Config::DEFAULT_PROTECTED_ROUTE_PREFIXES,
				Dydaps_Api_Config::HEADER_NAME,
				Dydaps_Api_Config::OPTION_PROTECTED_ROUTE_PREFIXES,
				Dydaps_Api_Config::OPTION_BLOCK_ALL_REST_API,
				Dydaps_Api_Config::OPTION_PUBLIC_ROUTE_PREFIXES,
				Dydaps_Api_Config::OPTION_ALLOW_INTERNAL_REST_REQUESTS,
				Dydaps_Api_Config::OPTION_ALLOWED_REST_NAMESPACES,
				$this->get_event_logger()
			);
		}

		return $this->admin_page;
	}

	/** Get the event logger. */
	private function get_event_logger() {
		if ( null === $this->event_logger ) {
			$this->event_logger = new Dydaps_Api_Event_Logger();
		}
		return $this->event_logger;
	}
}
