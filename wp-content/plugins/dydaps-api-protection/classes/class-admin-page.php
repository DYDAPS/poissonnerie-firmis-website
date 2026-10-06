<?php
/**
 * Admin settings page and secure action handlers.
 *
 * @package DYDAPS_API_Protection
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the settings page under Settings and handles nonce-protected actions.
 */
final class Dydaps_Api_Admin_Page {
	/**
	 * API key service.
	 *
	 * @var Dydaps_Api_Key_Service
	 */
	private $api_key_service;

	/**
	 * Protected route prefixes.
	 *
	 * @var string[]
	 */
	private $protected_route_prefixes;

	/**
	 * Header name.
	 *
	 * @var string
	 */
	private $header_name;

	/**
	 * Option name storing protected route prefixes.
	 *
	 * @var string
	 */
	private $protected_routes_option_name;

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
	private $public_routes_option_name;

	/**
	 * Constructor.
	 *
	 * @param Dydaps_Api_Key_Service $api_key_service         API key service.
	 * @param string[]               $protected_route_prefixes Protected route prefixes.
	 * @param string                 $header_name             Header name.
	 * @param string                 $protected_routes_option_name Option storing protected routes.
	 * @param string                 $block_all_option_name        Option toggling full REST API blocking.
	 * @param string                 $public_routes_option_name    Option storing public routes.
	 */
	public function __construct( Dydaps_Api_Key_Service $api_key_service, array $protected_route_prefixes, $header_name, $protected_routes_option_name, $block_all_option_name, $public_routes_option_name ) {
		$this->api_key_service             = $api_key_service;
		$this->protected_route_prefixes    = $protected_route_prefixes;
		$this->header_name                 = $header_name;
		$this->protected_routes_option_name = $protected_routes_option_name;
		$this->block_all_option_name       = $block_all_option_name;
		$this->public_routes_option_name   = $public_routes_option_name;
	}

	/**
	 * Enqueue lightweight admin assets for the settings page only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_' . Dydaps_Api_Config::ADMIN_PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			Dydaps_Api_Config::ADMIN_STYLE_HANDLE,
			DYDAPS_API_PROTECTION_URL . 'assets/admin.css',
			[],
			DYDAPS_API_PROTECTION_VERSION
		);

		wp_enqueue_script(
			Dydaps_Api_Config::ADMIN_SCRIPT_HANDLE,
			DYDAPS_API_PROTECTION_URL . 'assets/admin.js',
			[],
			DYDAPS_API_PROTECTION_VERSION,
			true
		);

		wp_add_inline_script(
			Dydaps_Api_Config::ADMIN_SCRIPT_HANDLE,
			'window.dydapsApiProtectionAdmin = ' . wp_json_encode(
				[
					'copiedLabel' => __( 'Copied', 'dydaps-api-protection' ),
				]
			) . ';',
			'before'
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render() {
		$this->ensure_manage_options();

		$plain_key  = $this->consume_plain_key();
		$has_key    = $this->api_key_service->has_key();
		$created_at = $this->api_key_service->get_created_at();
		$rotated_at = $this->api_key_service->get_last_rotated_at();
		$effective_route_prefixes = $this->get_saved_route_prefixes();
		$block_all_enabled        = $this->is_block_all_enabled();
		$public_route_prefixes    = $this->get_public_route_prefixes();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'DYDAPS API Protection', 'dydaps-api-protection' ); ?></h1>

			<div class="dydaps-api-protection-card">
				<div class="dydaps-api-protection-intro">
					<div>
						<p><?php echo esc_html__( 'Protect selected WordPress REST API routes by requiring a secret API key in the X-DYDAPS-API-Key header.', 'dydaps-api-protection' ); ?></p>
						<p><?php echo esc_html( $block_all_enabled ? __( 'Full blocking is enabled: public exceptions stay open, selected routes require the API key, and all other REST routes are blocked.', 'dydaps-api-protection' ) : __( 'Only selected routes require the API key. Other REST API routes remain public unless you enable full blocking.', 'dydaps-api-protection' ) ); ?></p>
					</div>
					<div class="dydaps-api-protection-panel dydaps-api-protection-routes">
						<strong><?php echo esc_html__( 'Protected routes', 'dydaps-api-protection' ); ?></strong>
						<?php foreach ( $effective_route_prefixes as $route_prefix ) : ?>
							<code><?php echo esc_html( '/' === $route_prefix ? '/wp-json' : '/wp-json' . $route_prefix ); ?></code>
						<?php endforeach; ?>
						<?php if ( ! empty( $public_route_prefixes ) ) : ?>
							<strong><?php echo esc_html__( 'Public exceptions', 'dydaps-api-protection' ); ?></strong>
							<?php foreach ( $public_route_prefixes as $route_prefix ) : ?>
								<code><?php echo esc_html( '/' === $route_prefix ? '/wp-json' : '/wp-json' . $route_prefix ); ?></code>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( '' !== $plain_key ) : ?>
					<div class="dydaps-api-protection-warning">
						<p><strong><?php echo esc_html__( 'Your new API key is shown only once.', 'dydaps-api-protection' ); ?></strong></p>
						<p><?php echo esc_html__( 'Copy it now and store it securely. The plain key is never stored and cannot be retrieved later.', 'dydaps-api-protection' ); ?></p>
						<div class="dydaps-api-protection-code">
							<div id="dydaps-api-protection-plain-key" class="dydaps-api-protection-value"><?php echo esc_html( $plain_key ); ?></div>
							<button type="button" class="button button-primary" data-dydaps-copy-target="#dydaps-api-protection-plain-key" data-dydaps-default-label="<?php echo esc_attr__( 'Copy API Key', 'dydaps-api-protection' ); ?>">
								<?php echo esc_html__( 'Copy API Key', 'dydaps-api-protection' ); ?>
							</button>
						</div>
					</div>
				<?php endif; ?>

				<div class="dydaps-api-protection-grid">
					<div class="dydaps-api-protection-panel">
						<strong><?php echo esc_html__( 'Protection status', 'dydaps-api-protection' ); ?></strong>
						<p><?php echo esc_html( $has_key ? __( 'Active', 'dydaps-api-protection' ) : __( 'Inactive', 'dydaps-api-protection' ) ); ?></p>
					</div>
					<div class="dydaps-api-protection-panel">
						<strong><?php echo esc_html__( 'Blocking mode', 'dydaps-api-protection' ); ?></strong>
						<p><?php echo esc_html( $block_all_enabled ? __( 'Full REST API block', 'dydaps-api-protection' ) : __( 'Selected routes only', 'dydaps-api-protection' ) ); ?></p>
					</div>
					<div class="dydaps-api-protection-panel">
						<strong><?php echo esc_html__( 'Header name', 'dydaps-api-protection' ); ?></strong>
						<div class="dydaps-api-protection-code" style="margin-top:8px;">
							<input id="dydaps-api-protection-header" class="regular-text code" type="text" readonly value="<?php echo esc_attr( $this->header_name ); ?>" />
							<button type="button" class="button" data-dydaps-copy-target="#dydaps-api-protection-header" data-dydaps-default-label="<?php echo esc_attr__( 'Copy Header', 'dydaps-api-protection' ); ?>">
								<?php echo esc_html__( 'Copy Header', 'dydaps-api-protection' ); ?>
							</button>
						</div>
					</div>
					<div class="dydaps-api-protection-panel">
						<strong><?php echo esc_html__( 'Stored key preview', 'dydaps-api-protection' ); ?></strong>
						<p><code><?php echo esc_html( $has_key ? $this->api_key_service->get_masked_preview() : __( 'No key generated yet.', 'dydaps-api-protection' ) ); ?></code></p>
					</div>
					<div class="dydaps-api-protection-panel">
						<strong><?php echo esc_html__( 'Created at', 'dydaps-api-protection' ); ?></strong>
						<p><?php echo esc_html( $this->format_timestamp( $created_at ) ); ?></p>
					</div>
					<div class="dydaps-api-protection-panel">
						<strong><?php echo esc_html__( 'Last rotation', 'dydaps-api-protection' ); ?></strong>
						<p><?php echo esc_html( $this->format_timestamp( $rotated_at ) ); ?></p>
					</div>
				</div>

				<div class="dydaps-api-protection-actions">
					<?php if ( $has_key ) : ?>
						<form method="post" action="<?php echo esc_url( $this->get_admin_post_url() ); ?>">
							<?php wp_nonce_field( 'dydaps_api_protection_rotate_key', 'dydaps_api_protection_nonce' ); ?>
							<input type="hidden" name="action" value="dydaps_api_protection_rotate" />
							<button type="submit" class="button button-primary"><?php echo esc_html__( 'Rotate API Key', 'dydaps-api-protection' ); ?></button>
						</form>

						<form method="post" action="<?php echo esc_url( $this->get_admin_post_url() ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'Revoke the current API key? This will immediately block requests using the old key.', 'dydaps-api-protection' ) ); ?>');">
							<?php wp_nonce_field( 'dydaps_api_protection_revoke_key', 'dydaps_api_protection_nonce' ); ?>
							<input type="hidden" name="action" value="dydaps_api_protection_revoke" />
							<button type="submit" class="button button-secondary"><?php echo esc_html__( 'Revoke API Key', 'dydaps-api-protection' ); ?></button>
						</form>
					<?php else : ?>
						<form method="post" action="<?php echo esc_url( $this->get_admin_post_url() ); ?>">
							<?php wp_nonce_field( 'dydaps_api_protection_generate_key', 'dydaps_api_protection_nonce' ); ?>
							<input type="hidden" name="action" value="dydaps_api_protection_generate" />
							<button type="submit" class="button button-primary"><?php echo esc_html__( 'Generate API Key', 'dydaps-api-protection' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
			</div>

			<div class="dydaps-api-protection-card">
				<h2><?php echo esc_html__( 'Protected route configuration', 'dydaps-api-protection' ); ?></h2>
				<p><?php echo esc_html__( 'Choose the REST API prefixes that must require the API key. You can keep the default WordPress content routes and add broader prefixes such as /wp/v2/ or custom plugin namespaces if needed.', 'dydaps-api-protection' ); ?></p>
				<form method="post" action="<?php echo esc_url( $this->get_admin_post_url() ); ?>">
					<?php wp_nonce_field( 'dydaps_api_protection_save_routes', 'dydaps_api_protection_nonce' ); ?>
					<input type="hidden" name="action" value="dydaps_api_protection_save_routes" />
					<div class="dydaps-api-protection-option">
						<label>
							<input type="checkbox" name="dydaps_block_all_rest_api" value="1" <?php checked( $block_all_enabled ); ?> />
							<strong><?php echo esc_html__( 'Block the whole REST API by default', 'dydaps-api-protection' ); ?></strong>
						</label>
						<p><?php echo esc_html__( 'When enabled, public exceptions stay open, selected routes require the API key, and every other REST API route is blocked.', 'dydaps-api-protection' ); ?></p>
					</div>
					<div class="dydaps-api-protection-tree">
						<div class="dydaps-api-protection-toggle-actions">
							<button type="button" class="button" data-dydaps-toggle-all="check"><?php echo esc_html__( 'Check all', 'dydaps-api-protection' ); ?></button>
							<button type="button" class="button" data-dydaps-toggle-all="uncheck"><?php echo esc_html__( 'Uncheck all', 'dydaps-api-protection' ); ?></button>
						</div>
						<ul>
							<li>
								<label>
									<input
										id="dydaps-route-root"
										type="checkbox"
										name="dydaps_protected_routes[]"
										value="/"
										data-dydaps-route-checkbox="1"
										<?php checked( $this->route_is_effectively_selected( '/', $effective_route_prefixes ) ); ?>
									/>
									<span><code>/wp-json</code> <?php echo esc_html__( '(all REST API endpoints)', 'dydaps-api-protection' ); ?></span>
								</label>
								<ul>
									<li>
										<label>
											<input
												id="dydaps-route-v2"
												type="checkbox"
												name="dydaps_protected_routes[]"
												value="/wp/v2"
												data-dydaps-route-checkbox="1"
												data-parent-checkbox="#dydaps-route-root"
												<?php checked( $this->route_is_effectively_selected( '/wp/v2', $effective_route_prefixes ) ); ?>
											/>
											<span><code>/wp-json/wp/v2/</code> <?php echo esc_html__( '(all WordPress v2 content endpoints)', 'dydaps-api-protection' ); ?></span>
										</label>
										<ul>
											<?php foreach ( $this->protected_route_prefixes as $route_prefix ) : ?>
												<li>
													<label>
														<input
															type="checkbox"
															name="dydaps_protected_routes[]"
															value="<?php echo esc_attr( $route_prefix ); ?>"
															data-dydaps-route-checkbox="1"
															data-parent-checkbox="#dydaps-route-v2"
															<?php checked( $this->route_is_effectively_selected( $route_prefix, $effective_route_prefixes ) ); ?>
														/>
														<span>
															<code><?php echo esc_html( '/wp-json' . $route_prefix ); ?></code>
															<?php if ( ! in_array( $route_prefix, $effective_route_prefixes, true ) && $this->route_is_effectively_selected( $route_prefix, $effective_route_prefixes ) ) : ?>
																<span class="dydaps-api-protection-inherited"><?php echo esc_html__( 'included by parent', 'dydaps-api-protection' ); ?></span>
															<?php endif; ?>
														</span>
													</label>
												</li>
											<?php endforeach; ?>
										</ul>
									</li>
								</ul>
							</li>
						</ul>
					</div>
					<p><label for="dydaps-api-protection-custom-routes"><strong><?php echo esc_html__( 'Additional custom prefixes', 'dydaps-api-protection' ); ?></strong></label></p>
					<p><?php echo esc_html__( 'Add one REST route prefix per line, for example /wc/v3/ or /my-plugin/v1/. Invalid or empty lines are ignored.', 'dydaps-api-protection' ); ?></p>
					<textarea id="dydaps-api-protection-custom-routes" class="dydaps-api-protection-textarea" name="dydaps_custom_route_prefixes"><?php echo esc_textarea( $this->get_custom_route_prefixes_text( $effective_route_prefixes ) ); ?></textarea>
					<p><label for="dydaps-api-protection-public-routes"><strong><?php echo esc_html__( 'Public exceptions', 'dydaps-api-protection' ); ?></strong></label></p>
					<p><?php echo esc_html__( 'Add one REST URL or route prefix per line that must stay public, even when full blocking is enabled or when it falls under a protected parent route.', 'dydaps-api-protection' ); ?></p>
					<textarea id="dydaps-api-protection-public-routes" class="dydaps-api-protection-textarea" name="dydaps_public_route_prefixes" placeholder="https://example.com/wp-json/wp/v2/types"><?php echo esc_textarea( $this->get_public_route_prefixes_text( $public_route_prefixes ) ); ?></textarea>
					<p>
						<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save protected routes', 'dydaps-api-protection' ); ?></button>
					</p>
				</form>
			</div>

			<div class="dydaps-api-protection-card">
				<h2><?php echo esc_html__( 'Integration examples', 'dydaps-api-protection' ); ?></h2>
				<p><?php echo esc_html__( 'Every protected request must include the exact custom header shown above.', 'dydaps-api-protection' ); ?></p>
				<div class="dydaps-api-protection-panel">
					<strong><?php echo esc_html__( 'cURL', 'dydaps-api-protection' ); ?></strong>
					<div id="dydaps-api-protection-curl" class="dydaps-api-protection-value">curl -H "<?php echo esc_html( $this->header_name ); ?>: YOUR_SECRET_KEY" https://example.com/wp-json/wp/v2/posts</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle key generation.
	 *
	 * @return void
	 */
	public function handle_generate() {
		$this->verify_admin_action( 'dydaps_api_protection_generate', 'dydaps_api_protection_generate_key' );

		$plain_key = $this->api_key_service->create_key();

		set_transient( Dydaps_Api_Config::TRANSIENT_PLAIN_KEY, $plain_key, Dydaps_Api_Config::TRANSIENT_PLAIN_KEY_TTL );
		$this->redirect_to_settings();
	}

	/**
	 * Handle key rotation.
	 *
	 * @return void
	 */
	public function handle_rotate() {
		$this->verify_admin_action( 'dydaps_api_protection_rotate', 'dydaps_api_protection_rotate_key' );

		$plain_key = $this->api_key_service->rotate_key();

		set_transient( Dydaps_Api_Config::TRANSIENT_PLAIN_KEY, $plain_key, Dydaps_Api_Config::TRANSIENT_PLAIN_KEY_TTL );
		$this->redirect_to_settings();
	}

	/**
	 * Handle key revocation.
	 *
	 * @return void
	 */
	public function handle_revoke() {
		$this->verify_admin_action( 'dydaps_api_protection_revoke', 'dydaps_api_protection_revoke_key' );

		$this->api_key_service->revoke_key();
		delete_transient( Dydaps_Api_Config::TRANSIENT_PLAIN_KEY );

		$this->redirect_to_settings();
	}

	/**
	 * Handle protected route configuration updates.
	 *
	 * @return void
	 */
	public function handle_save_routes() {
		$this->verify_admin_action( 'dydaps_api_protection_save_routes', 'dydaps_api_protection_save_routes' );

		$selected_routes = isset( $_POST['dydaps_protected_routes'] ) ? (array) wp_unslash( $_POST['dydaps_protected_routes'] ) : [];
		$custom_routes   = isset( $_POST['dydaps_custom_route_prefixes'] ) ? (string) wp_unslash( $_POST['dydaps_custom_route_prefixes'] ) : '';
		$public_routes   = isset( $_POST['dydaps_public_route_prefixes'] ) ? (string) wp_unslash( $_POST['dydaps_public_route_prefixes'] ) : '';
		$block_all       = isset( $_POST['dydaps_block_all_rest_api'] ) ? '1' : '0';
		$route_prefixes  = [];

		foreach ( $selected_routes as $selected_route ) {
			$normalized = Dydaps_Api_Route_Utils::normalize_route_prefix( $selected_route );
			if ( '' !== $normalized ) {
				$route_prefixes[] = $normalized;
			}
		}

		$route_prefixes  = array_values( array_unique( array_merge( $route_prefixes, Dydaps_Api_Route_Utils::parse_routes_from_input( $custom_routes ) ) ) );
		$public_prefixes = Dydaps_Api_Route_Utils::parse_routes_from_input( $public_routes );

		Dydaps_Api_Option_Store::set( $this->protected_routes_option_name, $route_prefixes );
		Dydaps_Api_Option_Store::set( $this->block_all_option_name, $block_all );
		Dydaps_Api_Option_Store::set( $this->public_routes_option_name, $public_prefixes );

		$this->redirect_to_settings();
	}

	/**
	 * Enforce capability checks.
	 *
	 * @return void
	 */
	private function ensure_manage_options() {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_die(
			esc_html__( 'You are not allowed to manage this setting.', 'dydaps-api-protection' ),
			esc_html__( 'Access denied', 'dydaps-api-protection' ),
			[
				'response' => 403,
			]
		);
	}

	/**
	 * Verify a secure admin-post action.
	 *
	 * @param string $expected_action Expected action name.
	 * @param string $nonce_action    Nonce action name.
	 * @return void
	 */
	private function verify_admin_action( $expected_action, $nonce_action ) {
		$this->ensure_manage_options();

		$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';

		if ( $expected_action !== $action ) {
			wp_die(
				esc_html__( 'The request action is invalid.', 'dydaps-api-protection' ),
				esc_html__( 'Invalid request', 'dydaps-api-protection' ),
				[
					'response' => 400,
				]
			);
		}

		check_admin_referer( $nonce_action, 'dydaps_api_protection_nonce' );
	}

	/**
	 * Retrieve and delete the one-time key display transient.
	 *
	 * @return string
	 */
	private function consume_plain_key() {
		$plain_key = get_transient( Dydaps_Api_Config::TRANSIENT_PLAIN_KEY );

		if ( ! is_string( $plain_key ) || '' === $plain_key ) {
			return '';
		}

		delete_transient( Dydaps_Api_Config::TRANSIENT_PLAIN_KEY );

		return $plain_key;
	}

	/**
	 * Format timestamps for the admin UI.
	 *
	 * @param int|null $timestamp Unix timestamp.
	 * @return string
	 */
	private function format_timestamp( $timestamp ) {
		if ( empty( $timestamp ) ) {
			return __( 'Not available', 'dydaps-api-protection' );
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/**
	 * Redirect back to the settings page.
	 *
	 * @return void
	 */
	private function redirect_to_settings() {
		wp_safe_redirect( $this->get_settings_page_url() );
		exit;
	}

	/**
	 * Get effective route prefixes from storage with fallback defaults.
	 *
	 * @return string[]
	 */
	private function get_saved_route_prefixes() {
		if ( false === get_option( $this->protected_routes_option_name, false ) ) {
			return $this->protected_route_prefixes;
		}

		return Dydaps_Api_Option_Store::get_route_prefixes( $this->protected_routes_option_name, [] );
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
	 * Get configured public route prefixes.
	 *
	 * @return string[]
	 */
	private function get_public_route_prefixes() {
		return Dydaps_Api_Option_Store::get_route_prefixes( $this->public_routes_option_name, [] );
	}

	/**
	 * Return only custom route prefixes as textarea content.
	 *
	 * @param string[] $effective_route_prefixes Effective protected route prefixes.
	 * @return string
	 */
	private function get_custom_route_prefixes_text( array $effective_route_prefixes ) {
		$custom_routes = Dydaps_Api_Route_Utils::get_custom_routes( $effective_route_prefixes, $this->protected_route_prefixes );
		return Dydaps_Api_Route_Utils::format_routes_for_textarea( $custom_routes );
	}

	/**
	 * Return public route prefixes as textarea content.
	 *
	 * @param string[] $public_route_prefixes Public route prefixes.
	 * @return string
	 */
	private function get_public_route_prefixes_text( array $public_route_prefixes ) {
		return Dydaps_Api_Route_Utils::format_routes_for_textarea( $public_route_prefixes );
	}

	/**
	 * Determine whether a route is selected directly or through a parent prefix.
	 *
	 * @param string   $route_prefix              Route prefix to test.
	 * @param string[] $effective_route_prefixes Effective protected prefixes.
	 * @return bool
	 */
	private function route_is_effectively_selected( $route_prefix, array $effective_route_prefixes ) {
		return Dydaps_Api_Route_Utils::route_matches_prefixes( $route_prefix, $effective_route_prefixes );
	}

	/**
	 * Get the settings page URL.
	 *
	 * @return string
	 */
	private function get_settings_page_url() {
		return admin_url( 'options-general.php?page=' . Dydaps_Api_Config::ADMIN_PAGE_SLUG );
	}

	/**
	 * Get the admin-post endpoint URL.
	 *
	 * @return string
	 */
	private function get_admin_post_url() {
		return admin_url( 'admin-post.php' );
	}
}
