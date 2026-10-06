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
		$this->load_textdomain();
		add_filter( 'rest_authentication_errors', [ $this->get_rest_protection(), 'protect_routes' ], 20 );
		add_filter( 'plugin_action_links_' . plugin_basename( DYDAPS_API_PROTECTION_FILE ), [ $this, 'add_plugin_action_links' ] );
		add_filter( 'all_plugins', [ $this, 'filter_plugin_row_text' ] );

		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
			add_action( 'admin_enqueue_scripts', [ $this->get_admin_page(), 'enqueue_assets' ] );
			add_action( 'admin_post_dydaps_api_protection_generate', [ $this->get_admin_page(), 'handle_generate' ] );
			add_action( 'admin_post_dydaps_api_protection_rotate', [ $this->get_admin_page(), 'handle_rotate' ] );
			add_action( 'admin_post_dydaps_api_protection_revoke', [ $this->get_admin_page(), 'handle_revoke' ] );
			add_action( 'admin_post_dydaps_api_protection_save_routes', [ $this->get_admin_page(), 'handle_save_routes' ] );
		}
	}

	/**
	 * Load plugin translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			Dydaps_Api_Config::TEXT_DOMAIN,
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
			__( 'DYDAPS API Protection', Dydaps_Api_Config::TEXT_DOMAIN ),
			__( 'DYDAPS API Protection', Dydaps_Api_Config::TEXT_DOMAIN ),
			'manage_options',
			Dydaps_Api_Config::ADMIN_PAGE_SLUG,
			[ $this->get_admin_page(), 'render' ]
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
			esc_html__( 'Settings', Dydaps_Api_Config::TEXT_DOMAIN )
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

		$plugins[ $plugin_basename ]['Name']        = __( 'DYDAPS API Protection', Dydaps_Api_Config::TEXT_DOMAIN );
		$plugins[ $plugin_basename ]['Description'] = __( 'Protect selected WordPress REST API routes with a secret API key sent in a custom HTTP header.', Dydaps_Api_Config::TEXT_DOMAIN );

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
				Dydaps_Api_Config::OPTION_PUBLIC_ROUTE_PREFIXES
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
				Dydaps_Api_Config::OPTION_PUBLIC_ROUTE_PREFIXES
			);
		}

		return $this->admin_page;
	}
}
