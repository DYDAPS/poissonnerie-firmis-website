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
	 * Option name controlling internal dispatch behavior.
	 *
	 * @var string
	 */
	private $allow_internal_option_name;

	/**
	 * Option name storing allowed REST namespaces.
	 *
	 * @var string
	 */
	private $allowed_namespaces_option_name;

	/**
	 * Optional security event logger.
	 *
	 * @var Dydaps_Api_Event_Logger|null
	 */
	private $event_logger;

	/**
	 * Constructor.
	 *
	 * @param Dydaps_Api_Key_Service       $api_key_service         API key service.
	 * @param string[]                     $protected_route_prefixes Protected route prefixes.
	 * @param string                       $header_name             Header name.
	 * @param string                       $protected_routes_option_name Option storing protected routes.
	 * @param string                       $block_all_option_name        Option toggling full REST API blocking.
	 * @param string                       $public_routes_option_name    Option storing public routes.
	 * @param string                       $allow_internal_option_name   Option controlling internal dispatch behavior.
	 * @param string                       $allowed_namespaces_option_name Option storing allowed REST namespaces.
	 * @param Dydaps_Api_Event_Logger|null $event_logger Optional security event logger.
	 */
	public function __construct( Dydaps_Api_Key_Service $api_key_service, array $protected_route_prefixes, $header_name, $protected_routes_option_name, $block_all_option_name, $public_routes_option_name, $allow_internal_option_name = '', $allowed_namespaces_option_name = '', $event_logger = null ) {
		$this->api_key_service                = $api_key_service;
		$this->protected_route_prefixes       = $protected_route_prefixes;
		$this->header_name                    = $header_name;
		$this->protected_routes_option_name   = $protected_routes_option_name;
		$this->block_all_option_name          = $block_all_option_name;
		$this->public_routes_option_name      = $public_routes_option_name;
		$this->allow_internal_option_name     = $allow_internal_option_name;
		$this->allowed_namespaces_option_name = $allowed_namespaces_option_name;
		$this->event_logger                   = $event_logger;
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
			array(),
			DYDAPS_API_PROTECTION_VERSION
		);

		wp_enqueue_script(
			Dydaps_Api_Config::ADMIN_SCRIPT_HANDLE,
			DYDAPS_API_PROTECTION_URL . 'assets/admin.js',
			array(),
			DYDAPS_API_PROTECTION_VERSION,
			true
		);

		wp_add_inline_script(
			Dydaps_Api_Config::ADMIN_SCRIPT_HANDLE,
			'window.dydapsApiProtectionAdmin = ' . wp_json_encode(
				array(
					'copiedLabel' => __( 'Copied', 'dydaps-api-protection' ),
				)
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
		$plain_key         = $this->consume_plain_key();
		$endpoints         = Dydaps_Api_Namespace_Registry::get_endpoint_inventory();
		$namespaces        = '' !== $this->allowed_namespaces_option_name ? Dydaps_Api_Option_Store::get_namespaces( $this->allowed_namespaces_option_name ) : array();
		$exceptions        = $this->normalize_saved_exceptions( (array) get_option( Dydaps_Api_Config::OPTION_ANONYMOUS_EXCEPTIONS, array() ) );
		$public_routes     = $this->get_public_route_prefixes();
		$protected_routes  = $this->get_saved_route_prefixes();
		$block_all         = $this->is_block_all_enabled();
		$event_filters     = array(
			'method' => isset( $_GET['dydaps_event_method'] ) ? sanitize_key( wp_unslash( $_GET['dydaps_event_method'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filtering.
			'status' => isset( $_GET['dydaps_event_status'] ) ? absint( $_GET['dydaps_event_status'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filtering.
			'layer'  => isset( $_GET['dydaps_event_layer'] ) ? sanitize_key( wp_unslash( $_GET['dydaps_event_layer'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filtering.
			'from'   => isset( $_GET['dydaps_event_from'] ) ? sanitize_text_field( wp_unslash( $_GET['dydaps_event_from'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filtering.
			'to'     => isset( $_GET['dydaps_event_to'] ) ? sanitize_text_field( wp_unslash( $_GET['dydaps_event_to'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filtering.
		);
		$event_page_number = isset( $_GET['dydaps_event_page'] ) ? absint( $_GET['dydaps_event_page'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$event_page        = $this->event_logger instanceof Dydaps_Api_Event_Logger ? $this->event_logger->get_events_page( $event_filters, $event_page_number, 20 ) : array(
			'events' => array(),
			'total'  => 0,
			'page'   => 1,
			'pages'  => 1,
		);
		$events            = $event_page['events'];
		$log_age_days      = isset( $_GET['dydaps_log_age_days'] ) ? absint( $_GET['dydaps_log_age_days'] ) : 7; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only count preview.
		if ( ! in_array( $log_age_days, array( 7, 14, 30 ), true ) ) {
			$log_age_days = 7;
		}
		$active_plugins = $this->get_active_plugin_names();
		$public_count   = count( $public_routes ) + count( $namespaces );
		foreach ( $exceptions as $exception ) {
			if ( ! empty( $exception['enabled'] ) ) {
				++$public_count;
			}
		}
		$status_key = $this->get_protection_configuration_status( $block_all, $protected_routes, $public_routes, $namespaces, $exceptions );
		$status     = array(
			'configured'          => __( 'Configured', 'dydaps-api-protection' ),
			'customized'          => __( 'Customized', 'dydaps-api-protection' ),
			'needs_configuration' => __( 'Needs configuration', 'dydaps-api-protection' ),
		)[ $status_key ];
		$analysis   = isset( $_GET['dydaps_analyze'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['dydaps_analyze'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only analysis only.
		$result     = isset( $_GET['dydaps_result'] ) ? sanitize_key( wp_unslash( $_GET['dydaps_result'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Notice only.
		?>
		<div class="wrap dydaps-admin"><h1><?php echo esc_html__( 'REST API Protection', 'dydaps-api-protection' ); ?></h1>
		<?php
		if ( 'applied' === $result && 'configured' === $status_key ) :
			?>
			<div class="notice notice-success is-dismissible" role="status"><p><?php echo esc_html__( 'Configuration applied successfully. Your site is now using the recommended protection settings. Previous public access rules have been removed.', 'dydaps-api-protection' ); ?></p></div>
			<?php
		elseif ( 'already_configured' === $result && 'configured' === $status_key ) :
			?>
			<div class="notice notice-success is-dismissible" role="status"><p><?php echo esc_html__( 'Your site is already configured correctly. No changes are needed.', 'dydaps-api-protection' ); ?></p></div>
			<?php
		endif;
		?>
		<section id="dydaps-protection" class="dydaps-api-protection-card dydaps-primary" aria-labelledby="dydaps-protection-heading">
			<h2 id="dydaps-protection-heading"><?php echo esc_html__( 'Site protection', 'dydaps-api-protection' ); ?></h2>
			<p class="dydaps-status"><span class="dydaps-state-badge dydaps-state-badge--<?php echo esc_attr( $status_key ); ?>"><?php echo esc_html( $status ); ?></span></p>
			<p><?php echo esc_html( $block_all ? __( 'Anonymous REST access is blocked by default. WordPress sign-in and each route permission remain in control.', 'dydaps-api-protection' ) : __( 'Only selected routes are protected. Other REST routes may still be accessible to visitors.', 'dydaps-api-protection' ) ); ?></p>
			<?php
			if ( $public_count ) :
				?>
				<div class="notice notice-warning inline"><p><?php echo esc_html( sprintf( /* translators: %d: Count of public access rules. */ __( '%d public access rules need your review.', 'dydaps-api-protection' ), $public_count ) ); ?> <a href="#dydaps-advanced" data-dydaps-tab-link="exceptions"><?php echo esc_html__( 'Review advanced settings', 'dydaps-api-protection' ); ?></a></p></div><?php endif; ?>
			<form method="get" action="<?php echo esc_url( $this->get_settings_page_url() ); ?>"><input type="hidden" name="page" value="<?php echo esc_attr( Dydaps_Api_Config::ADMIN_PAGE_SLUG ); ?>" /><input type="hidden" name="dydaps_analyze" value="1" /><button class="button button-primary button-hero" type="submit"><?php echo esc_html__( 'Check and configure my website', 'dydaps-api-protection' ); ?></button></form>
			<?php if ( $analysis ) : ?>
				<div class="dydaps-assistant" role="region" aria-labelledby="dydaps-assistant-title"><h3 id="dydaps-assistant-title"><?php echo esc_html__( 'Website check', 'dydaps-api-protection' ); ?></h3>
				<p><?php echo esc_html( sprintf( /* translators: %1$d: REST endpoint count; %2$d: active plugin count. */ __( 'Checked %1$d registered REST routes and %2$d active plugins.', 'dydaps-api-protection' ), count( $endpoints ), count( $active_plugins ) ) ); ?></p>
				<p><?php echo esc_html( sprintf( /* translators: %d: number of public access rules removed by the recommendation. */ __( 'The recommendation will block anonymous access by default and remove %d existing public route, method, and namespace rules.', 'dydaps-api-protection' ), $public_count ) ); ?></p>
				<p><?php echo esc_html__( 'No plugin is trusted automatically. Some visitor features may need a precise exception after you test them. WordPress authentication and native route permissions are unchanged.', 'dydaps-api-protection' ); ?></p>
				<?php
				if ( ! empty( $active_plugins ) ) :
					?>
					<details><summary><?php echo esc_html__( 'Active plugins', 'dydaps-api-protection' ); ?></summary><ul>
					<?php
					foreach ( $active_plugins as $plugin_name ) :
						?>
					<li><?php echo esc_html( $plugin_name ); ?></li><?php endforeach; ?></ul></details><?php endif; ?>
				<?php
				$uncertain_routes = array_filter(
					$endpoints,
					static function ( $endpoint ) {
						return empty( $endpoint['core'] );
					}
				);
				if ( ! empty( $uncertain_routes ) ) :
					?>
					<details><summary><?php echo esc_html( sprintf( /* translators: %d: Number of non-core REST routes to review. */ __( 'Review %d extension routes manually', 'dydaps-api-protection' ), count( $uncertain_routes ) ) ); ?></summary><p><?php echo esc_html__( 'Route discovery cannot confirm whether public features work. No plugin routes will be opened automatically.', 'dydaps-api-protection' ); ?></p><ul>
					<?php
					foreach ( $uncertain_routes as $uncertain_route ) :
						?>
						<li><code><?php echo esc_html( implode( ', ', $uncertain_route['methods'] ) . ' ' . $uncertain_route['route'] ); ?></code> <span><?php echo esc_html( $uncertain_route['owner'] ); ?></span></li><?php endforeach; ?></ul></details>
				<?php endif; ?>
				<?php
				if ( ! $block_all || $public_count ) :
					?>
					<form method="post" action="<?php echo esc_url( $this->get_admin_post_url() ); ?>"><?php wp_nonce_field( 'dydaps_api_protection_apply_recommendation', 'dydaps_api_protection_nonce' ); ?><input type="hidden" name="action" value="dydaps_api_protection_apply_recommendation" /><input type="hidden" name="dydaps_policy_fingerprint" value="<?php echo esc_attr( $this->get_policy_fingerprint() ); ?>" /><label class="dydaps-confirm"><input type="checkbox" name="dydaps_confirm_recommended_protection" value="1" required /> <?php echo esc_html__( 'I understand public REST access rules will be removed.', 'dydaps-api-protection' ); ?></label><p><button class="button button-primary" type="submit"><?php echo esc_html__( 'Apply recommended configuration', 'dydaps-api-protection' ); ?></button></p></form>
					<?php
else :
	?>
					<p><strong><?php echo esc_html__( 'Your site is already configured correctly. No changes are needed.', 'dydaps-api-protection' ); ?></strong></p><?php endif; ?></div>
			<?php endif; ?>
		</section>

		<section id="dydaps-activity" class="dydaps-api-protection-card" aria-labelledby="dydaps-activity-heading"><h2 id="dydaps-activity-heading"><?php echo esc_html__( 'Activity log', 'dydaps-api-protection' ); ?></h2><h3><?php echo esc_html__( 'API access denials', 'dydaps-api-protection' ); ?></h3>
			<?php if ( isset( $_GET['dydaps_log_result'] ) && 'cleared' === sanitize_key( wp_unslash( $_GET['dydaps_log_result'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice. ?>
				<div class="notice notice-success inline"><p><?php echo esc_html__( 'The activity log was cleared.', 'dydaps-api-protection' ); ?></p></div>
			<?php elseif ( isset( $_GET['dydaps_log_result'] ) && 'deleted' === sanitize_key( wp_unslash( $_GET['dydaps_log_result'] ) ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice. ?>
				<div class="notice notice-success inline"><p><?php echo esc_html__( 'Older activity events were deleted.', 'dydaps-api-protection' ); ?></p></div>
			<?php endif; ?>
			<form method="get" action="<?php echo esc_url( $this->get_settings_page_url() ); ?>" class="dydaps-api-protection-filters"><input type="hidden" name="page" value="<?php echo esc_attr( Dydaps_Api_Config::ADMIN_PAGE_SLUG ); ?>" /><label><?php echo esc_html__( 'From', 'dydaps-api-protection' ); ?> <input type="date" name="dydaps_event_from" value="<?php echo esc_attr( $event_filters['from'] ); ?>" /></label><label><?php echo esc_html__( 'To', 'dydaps-api-protection' ); ?> <input type="date" name="dydaps_event_to" value="<?php echo esc_attr( $event_filters['to'] ); ?>" /></label><label><?php echo esc_html__( 'Method', 'dydaps-api-protection' ); ?> <select name="dydaps_event_method"><option value=""><?php echo esc_html__( 'All methods', 'dydaps-api-protection' ); ?></option>
			<?php
			foreach ( array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ) as $method ) :
				?>
				<option value="<?php echo esc_attr( $method ); ?>" <?php selected( $event_filters['method'], $method ); ?>><?php echo esc_html( $method ); ?></option><?php endforeach; ?></select></label><label><?php echo esc_html__( 'Status', 'dydaps-api-protection' ); ?> <select name="dydaps_event_status"><option value="0"><?php echo esc_html__( 'All statuses', 'dydaps-api-protection' ); ?></option><option value="401" <?php selected( $event_filters['status'], 401 ); ?>>401</option><option value="403" <?php selected( $event_filters['status'], 403 ); ?>>403</option></select></label><label><?php echo esc_html__( 'Origin of refusal', 'dydaps-api-protection' ); ?> <select name="dydaps_event_layer"><option value=""><?php echo esc_html__( 'All origins', 'dydaps-api-protection' ); ?></option>
				<?php
				foreach ( array( 'dydaps_anonymous_block', 'dydaps_api_key', 'wordpress_authentication', 'wordpress_permission', 'wordpress_rest_response' ) as $layer ) :
					?>
				<option value="<?php echo esc_attr( $layer ); ?>" <?php selected( $event_filters['layer'], $layer ); ?>><?php echo esc_html( $this->format_event_layer( $layer ) ); ?></option><?php endforeach; ?></select></label><button class="button" type="submit"><?php echo esc_html__( 'Filter', 'dydaps-api-protection' ); ?></button></form>
			<p><?php echo esc_html( sprintf( /* translators: %1$d: current page; %2$d: total pages; %3$d: total events. */ __( 'Page %1$d of %2$d · %3$d events', 'dydaps-api-protection' ), min( $event_page['page'], $event_page['pages'] ), $event_page['pages'], $event_page['total'] ) ); ?></p>
			<div class="dydaps-api-protection-table-wrap"><table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Date and time', 'dydaps-api-protection' ); ?></th><th><?php echo esc_html__( 'Method', 'dydaps-api-protection' ); ?></th><th><?php echo esc_html__( 'Requested route', 'dydaps-api-protection' ); ?></th><th><?php echo esc_html__( 'Status', 'dydaps-api-protection' ); ?></th><th><?php echo esc_html__( 'Reason for blocking', 'dydaps-api-protection' ); ?></th><th><?php echo esc_html__( 'Origin of refusal', 'dydaps-api-protection' ); ?></th><th><?php echo esc_html__( 'Authentication context', 'dydaps-api-protection' ); ?></th></tr></thead><tbody>
			<?php foreach ( $events as $event ) : ?>
				<tr><td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $event['time'] ) ); ?></td><td><?php echo esc_html( $event['method'] ); ?></td><td><code><?php echo esc_html( $event['route'] ); ?></code></td><td><?php echo esc_html( (string) $event['status'] ); ?>
				<?php
				if ( ! empty( $event['error_code'] ) ) :
					?>
					<br /><code><?php echo esc_html( $event['error_code'] ); ?></code><?php endif; ?></td><td><?php echo esc_html( $this->format_event_reason( $event ) ); ?></td><td><?php echo esc_html( $this->format_event_layer( $event['layer'] ) ); ?></td><td><?php echo esc_html( $this->format_event_auth( isset( $event['auth'] ) ? $event['auth'] : 'unknown' ) ); ?></td></tr>
			<?php endforeach; ?>
			<?php
			if ( empty( $events ) ) :
				?>
				<tr><td colspan="7"><?php echo esc_html__( 'No API access denials match these filters.', 'dydaps-api-protection' ); ?></td></tr><?php endif; ?></tbody></table></div>
			<?php
			if ( $event_page['pages'] > 1 ) :
				$pagination_args = array_merge( array( 'page' => Dydaps_Api_Config::ADMIN_PAGE_SLUG ), array_filter( $event_filters ) );
				?>
				<div class="tablenav"><div class="tablenav-pages"><span class="pagination-links">
				<?php
				if ( $event_page['page'] > 1 ) :
					?>
					<a class="button" href="<?php echo esc_url( add_query_arg( array_merge( $pagination_args, array( 'dydaps_event_page' => $event_page['page'] - 1 ) ), $this->get_settings_page_url() ) ); ?>">&larr; <?php echo esc_html__( 'Previous', 'dydaps-api-protection' ); ?></a><?php endif; ?>
					<?php
					if ( $event_page['page'] < $event_page['pages'] ) :
						?>
					<a class="button" href="<?php echo esc_url( add_query_arg( array_merge( $pagination_args, array( 'dydaps_event_page' => $event_page['page'] + 1 ) ), $this->get_settings_page_url() ) ); ?>"><?php echo esc_html__( 'Next', 'dydaps-api-protection' ); ?> &rarr;</a><?php endif; ?></span></div></div>
			<?php endif; ?>
			<div class="dydaps-log-actions"><form method="post" action="<?php echo esc_url( $this->get_admin_post_url() ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'Delete events older than the selected period?', 'dydaps-api-protection' ) ); ?>');"><?php wp_nonce_field( 'dydaps_api_protection_delete_old_logs', 'dydaps_api_protection_nonce' ); ?><input type="hidden" name="action" value="dydaps_api_protection_delete_old_logs" /><label><?php echo esc_html__( 'Delete events older than', 'dydaps-api-protection' ); ?> <select name="dydaps_log_age_days" data-event-age-select="1">
			<?php
			foreach ( array( 7, 14, 30 ) as $days ) :
				?>
				<option value="<?php echo esc_attr( (string) $days ); ?>" data-count="<?php echo esc_attr( (string) ( $this->event_logger instanceof Dydaps_Api_Event_Logger ? $this->event_logger->count_older_than( $days ) : 0 ) ); ?>" <?php selected( $log_age_days, $days ); ?>><?php echo esc_html( sprintf( /* translators: %d: Number of days. */ __( '%d days', 'dydaps-api-protection' ), $days ) ); ?></option><?php endforeach; ?></select> <span><strong data-event-count="1"><?php echo esc_html( (string) ( $this->event_logger instanceof Dydaps_Api_Event_Logger ? $this->event_logger->count_older_than( $log_age_days ) : 0 ) ); ?></strong> <?php echo esc_html__( 'matching events', 'dydaps-api-protection' ); ?></span></label> <button class="button" type="submit"><?php echo esc_html__( 'Delete old events', 'dydaps-api-protection' ); ?></button></form><form method="post" action="<?php echo esc_url( $this->get_admin_post_url() ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'This permanently deletes all recorded activity events. It does not change API keys or protection settings. Continue?', 'dydaps-api-protection' ) ); ?>');"><?php wp_nonce_field( 'dydaps_api_protection_clear_logs', 'dydaps_api_protection_nonce' ); ?><input type="hidden" name="action" value="dydaps_api_protection_clear_logs" /><button class="button button-secondary" type="submit"><?php echo esc_html__( 'Clear activity log', 'dydaps-api-protection' ); ?></button></form></div>
		</section>

		<section id="dydaps-advanced" class="dydaps-api-protection-card dydaps-advanced" aria-labelledby="dydaps-advanced-heading"><h2 id="dydaps-advanced-heading"><?php echo esc_html__( 'Advanced settings', 'dydaps-api-protection' ); ?></h2>
			<div class="dydaps-tabs" role="tablist" aria-label="<?php echo esc_attr__( 'Advanced settings', 'dydaps-api-protection' ); ?>"><button type="button" role="tab" id="dydaps-tab-protection" aria-controls="dydaps-panel-protection" aria-selected="true" tabindex="0"><?php echo esc_html__( 'Protection', 'dydaps-api-protection' ); ?></button><button type="button" role="tab" id="dydaps-tab-exceptions" aria-controls="dydaps-panel-exceptions" aria-selected="false" tabindex="-1"><?php echo esc_html__( 'Exceptions', 'dydaps-api-protection' ); ?></button><button type="button" role="tab" id="dydaps-tab-logging" aria-controls="dydaps-panel-logging" aria-selected="false" tabindex="-1"><?php echo esc_html__( 'Logging', 'dydaps-api-protection' ); ?></button></div>
			<div id="dydaps-panel-protection" role="tabpanel" aria-labelledby="dydaps-tab-protection"><form method="post" action="<?php echo esc_url( $this->get_admin_post_url() ); ?>"><?php wp_nonce_field( 'dydaps_api_protection_save_routes', 'dydaps_api_protection_nonce' ); ?><input type="hidden" name="action" value="dydaps_api_protection_save_routes" /><input type="hidden" name="dydaps_internal_setting_submitted" value="1" /><label class="dydaps-setting"><input type="checkbox" name="dydaps_block_all_rest_api" value="1" <?php checked( $block_all ); ?> /> <span><strong><?php echo esc_html__( 'Block anonymous REST requests by default', 'dydaps-api-protection' ); ?></strong><small><?php echo esc_html__( 'WordPress authentication and endpoint permissions continue to apply.', 'dydaps-api-protection' ); ?></small></span></label><label class="dydaps-setting"><input type="checkbox" name="dydaps_allow_internal_rest_requests" value="1" <?php checked( Dydaps_Api_Option_Store::get_bool( $this->allow_internal_option_name ) ); ?> /> <span><strong><?php echo esc_html__( 'Allow same-process WordPress REST dispatch', 'dydaps-api-protection' ); ?></strong><small><?php echo esc_html__( 'Native route permission checks still run. HTTP loopback requests remain protected.', 'dydaps-api-protection' ); ?></small></span></label><fieldset class="dydaps-protected-route-options"><legend><strong><?php echo esc_html__( 'WordPress routes requiring an API client key', 'dydaps-api-protection' ); ?></strong></legend>
			<?php
			foreach ( $this->protected_route_prefixes as $known_route ) :
				?>
				<label class="dydaps-inline-option"><input type="checkbox" name="dydaps_protected_routes[]" value="<?php echo esc_attr( $known_route ); ?>" <?php checked( $this->route_is_effectively_selected( $known_route, $protected_routes ) ); ?> /> <code><?php echo esc_html( '/wp-json' . $known_route ); ?></code></label><?php endforeach; ?></fieldset><p><label for="dydaps-protected-routes"><strong><?php echo esc_html__( 'Additional protected route prefixes', 'dydaps-api-protection' ); ?></strong></label></p><textarea id="dydaps-protected-routes" class="dydaps-api-protection-textarea" name="dydaps_custom_route_prefixes"><?php echo esc_textarea( $this->get_custom_route_prefixes_text( $protected_routes ) ); ?></textarea><p><button class="button button-primary" type="submit"><?php echo esc_html__( 'Save protection settings', 'dydaps-api-protection' ); ?></button></p></form></div>
			<div id="dydaps-panel-exceptions" role="tabpanel" aria-labelledby="dydaps-tab-exceptions" hidden><p><?php echo esc_html__( 'A public exception only removes this protection rule. WordPress login and native endpoint permissions remain active.', 'dydaps-api-protection' ); ?></p><form method="post" action="<?php echo esc_url( $this->get_admin_post_url() ); ?>"><?php wp_nonce_field( 'dydaps_api_protection_save_exceptions', 'dydaps_api_protection_nonce' ); ?><input type="hidden" name="action" value="dydaps_api_protection_save_exceptions" /><div class="dydaps-api-protection-table-wrap"><table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Method', 'dydaps-api-protection' ); ?></th><th><?php echo esc_html__( 'REST route', 'dydaps-api-protection' ); ?></th><th><?php echo esc_html__( 'Description', 'dydaps-api-protection' ); ?></th><th><?php echo esc_html__( 'Enabled', 'dydaps-api-protection' ); ?></th><th><?php echo esc_html__( 'Remove', 'dydaps-api-protection' ); ?></th></tr></thead><tbody>
			<?php
			foreach ( $exceptions as $index => $exception ) :
				?>
				<tr><td><?php $this->render_method_select( 'dydaps_exceptions[' . $index . '][method]', $exception['method'] ); ?></td><td><input class="regular-text code" name="dydaps_exceptions[<?php echo esc_attr( (string) $index ); ?>][route]" value="<?php echo esc_attr( $exception['route'] ); ?>" /></td><td><input class="regular-text" name="dydaps_exceptions[<?php echo esc_attr( (string) $index ); ?>][description]" value="<?php echo esc_attr( $exception['description'] ); ?>" /></td><td><input type="checkbox" name="dydaps_exceptions[<?php echo esc_attr( (string) $index ); ?>][enabled]" value="1" <?php checked( $exception['enabled'] ); ?> /></td><td><input type="checkbox" name="dydaps_remove_exceptions[]" value="<?php echo esc_attr( (string) $index ); ?>" aria-label="<?php echo esc_attr__( 'Remove exception', 'dydaps-api-protection' ); ?>" /></td></tr><?php endforeach; ?><tr><td><?php $this->render_method_select( 'dydaps_new_exception[method]', 'GET' ); ?></td><td><input class="regular-text code" name="dydaps_new_exception[route]" placeholder="/plugin/v1/items" /></td><td><input class="regular-text" name="dydaps_new_exception[description]" /></td><td><input type="checkbox" name="dydaps_new_exception[enabled]" value="1" /></td><td><?php echo esc_html__( 'New exception', 'dydaps-api-protection' ); ?></td></tr></tbody></table></div><div class="notice notice-warning inline"><p><?php echo esc_html__( 'Enabling a rule makes that route and method reachable to anonymous clients. Review carefully before saving.', 'dydaps-api-protection' ); ?></p></div><details><summary><?php echo esc_html__( 'Broad legacy namespace permissions', 'dydaps-api-protection' ); ?> (<?php echo esc_html( (string) count( $namespaces ) ); ?>)</summary>
				<?php
				if ( $namespaces ) :
					?>
				<p><?php echo esc_html__( 'Each selected namespace opens every matching route for anonymous requests. Remove a selection to close it.', 'dydaps-api-protection' ); ?></p><?php endif; ?>
				<?php
				foreach ( Dydaps_Api_Namespace_Registry::get_inventory() as $namespace => $details ) :
					?>
	<label class="dydaps-inline-option"><input type="checkbox" name="dydaps_allowed_namespaces[]" value="<?php echo esc_attr( $namespace ); ?>" <?php checked( in_array( $namespace, $namespaces, true ) ); ?> /> <code><?php echo esc_html( $namespace ); ?></code> <span><?php echo esc_html( $details['owner'] ); ?></span></label><details class="dydaps-namespace-routes"><summary><?php echo esc_html( sprintf( /* translators: %d: Route count. */ __( 'Show %d affected routes', 'dydaps-api-protection' ), count( $details['routes'] ) ) ); ?></summary><ul>
					<?php
					foreach ( $details['routes'] as $affected_route ) :
						?>
		<li><code><?php echo esc_html( $affected_route ); ?></code></li><?php endforeach; ?></ul></details><?php endforeach; ?>
		<?php
		foreach ( array_diff( $namespaces, array_keys( Dydaps_Api_Namespace_Registry::get_inventory() ) ) as $legacy_namespace ) :
			?>
	<label class="dydaps-inline-option"><input type="checkbox" name="dydaps_allowed_namespaces[]" value="<?php echo esc_attr( $legacy_namespace ); ?>" checked="checked" /> <code><?php echo esc_html( $legacy_namespace ); ?></code> ? <?php echo esc_html__( 'Not currently registered', 'dydaps-api-protection' ); ?></label><?php endforeach; ?></details><details><summary><?php echo esc_html__( 'Earlier public route permissions', 'dydaps-api-protection' ); ?> (<?php echo esc_html( (string) count( $public_routes ) ); ?>)</summary><p><?php echo esc_html__( 'These older route prefixes may match several routes. Uncheck an item to remove it.', 'dydaps-api-protection' ); ?></p>
		<?php
		foreach ( $public_routes as $legacy_index => $legacy_route ) :
			?>
	<label class="dydaps-inline-option"><input type="checkbox" name="dydaps_legacy_public_routes[]" value="<?php echo esc_attr( $legacy_route ); ?>" checked="checked" /> <code><?php echo esc_html( $legacy_route ); ?></code></label><?php endforeach; ?></details><label class="dydaps-public-confirm"><input type="checkbox" name="dydaps_confirm_public_access" value="1" /> <?php echo esc_html__( 'I confirm that enabled rules and selected namespaces allow anonymous API requests.', 'dydaps-api-protection' ); ?></label><p><button class="button button-primary" type="submit"><?php echo esc_html__( 'Save exceptions', 'dydaps-api-protection' ); ?></button></p></form><details><summary><?php echo esc_html__( 'Registered route inventory', 'dydaps-api-protection' ); ?> (<?php echo esc_html( (string) count( $endpoints ) ); ?>)</summary><label><?php echo esc_html__( 'Search routes', 'dydaps-api-protection' ); ?> <input type="search" data-dydaps-endpoint-search="1" /></label><ul class="dydaps-route-list">
		<?php
		foreach ( $endpoints as $endpoint ) :
			?>
	<li data-dydaps-endpoint-row="1" data-search="<?php echo esc_attr( strtolower( $endpoint['namespace'] . ' ' . $endpoint['route'] ) ); ?>"><code><?php echo esc_html( implode( ', ', $endpoint['methods'] ) . ' ' . $endpoint['route'] ); ?></code> <span><?php echo esc_html( $endpoint['owner'] ); ?></span></li><?php endforeach; ?></ul></details></div>
			<div id="dydaps-panel-logging" role="tabpanel" aria-labelledby="dydaps-tab-logging" hidden><form method="post" action="<?php echo esc_url( $this->get_admin_post_url() ); ?>"><?php wp_nonce_field( 'dydaps_api_protection_save_logging', 'dydaps_api_protection_nonce' ); ?><input type="hidden" name="action" value="dydaps_api_protection_save_logging" /><label class="dydaps-setting"><input type="checkbox" name="dydaps_logging_enabled" value="1" <?php checked( Dydaps_Api_Option_Store::get_bool( Dydaps_Api_Config::OPTION_LOGGING_ENABLED ) ); ?> /> <span><strong><?php echo esc_html__( 'Record API access denials', 'dydaps-api-protection' ); ?></strong><small><?php echo esc_html__( 'Credentials, cookies, nonces and request bodies are never saved.', 'dydaps-api-protection' ); ?></small></span></label><p><label><?php echo esc_html__( 'Retention', 'dydaps-api-protection' ); ?> <select name="dydaps_log_retention_days">
			<?php
			foreach ( array( 7, 14, 30, 90 ) as $days ) :
				?>
				<option value="<?php echo esc_attr( (string) $days ); ?>" <?php selected( absint( get_option( Dydaps_Api_Config::OPTION_LOG_RETENTION_DAYS, 7 ) ), $days ); ?>><?php echo esc_html( sprintf( /* translators: %d: Number of days. */ __( '%d days', 'dydaps-api-protection' ), $days ) ); ?></option><?php endforeach; ?></select></label></p><p><label><?php echo esc_html__( 'Maximum saved entries', 'dydaps-api-protection' ); ?> <select name="dydaps_log_max_events">
				<?php
				foreach ( array( 50, 100, 200 ) as $max_events ) :
					?>
				<option value="<?php echo esc_attr( (string) $max_events ); ?>" <?php selected( absint( get_option( Dydaps_Api_Config::OPTION_LOG_MAX_EVENTS, 200 ) ), $max_events ); ?>><?php echo esc_html( (string) $max_events ); ?></option><?php endforeach; ?></select></label></p><p><button class="button button-primary" type="submit"><?php echo esc_html__( 'Save logging settings', 'dydaps-api-protection' ); ?></button></p></form></div>
		</section>

		<section id="dydaps-api-keys" class="dydaps-api-protection-card" aria-labelledby="dydaps-keys-heading"><h2 id="dydaps-keys-heading"><?php echo esc_html__( 'API keys', 'dydaps-api-protection' ); ?></h2><p><?php echo esc_html__( 'An API client key identifies a client only. It does not sign in a WordPress user or grant WordPress capabilities.', 'dydaps-api-protection' ); ?></p>
			<?php
			if ( '' !== $plain_key ) :
				?>
				<div class="notice notice-success"><p><strong><?php echo esc_html__( 'Copy this key now. It will only be shown once.', 'dydaps-api-protection' ); ?></strong></p><code id="dydaps-api-key-value" class="dydaps-api-protection-value"><?php echo esc_html( $plain_key ); ?></code><button type="button" class="button" data-dydaps-copy-target="#dydaps-api-key-value" data-dydaps-default-label="<?php echo esc_attr__( 'Copy key', 'dydaps-api-protection' ); ?>"><?php echo esc_html__( 'Copy key', 'dydaps-api-protection' ); ?></button></div><?php endif; ?>
			<p><?php echo esc_html__( 'Stored key', 'dydaps-api-protection' ); ?>: <code><?php echo esc_html( $this->api_key_service->has_key() ? $this->api_key_service->get_masked_preview() : __( 'No key generated', 'dydaps-api-protection' ) ); ?></code></p>
			<?php
			if ( $this->api_key_service->has_key() ) :
				?>
				<form class="dydaps-key-action" method="post" action="<?php echo esc_url( $this->get_admin_post_url() ); ?>"><?php wp_nonce_field( 'dydaps_api_protection_rotate_key', 'dydaps_api_protection_nonce' ); ?><input type="hidden" name="action" value="dydaps_api_protection_rotate" /><button class="button" type="submit"><?php echo esc_html__( 'Generate a replacement key', 'dydaps-api-protection' ); ?></button></form><form class="dydaps-key-action" method="post" action="<?php echo esc_url( $this->get_admin_post_url() ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'Revoke the current API key?', 'dydaps-api-protection' ) ); ?>');"><?php wp_nonce_field( 'dydaps_api_protection_revoke_key', 'dydaps_api_protection_nonce' ); ?><input type="hidden" name="action" value="dydaps_api_protection_revoke" /><button class="button" type="submit"><?php echo esc_html__( 'Revoke key', 'dydaps-api-protection' ); ?></button></form>
				<?php
else :
	?>
				<form method="post" action="<?php echo esc_url( $this->get_admin_post_url() ); ?>"><?php wp_nonce_field( 'dydaps_api_protection_generate_key', 'dydaps_api_protection_nonce' ); ?><input type="hidden" name="action" value="dydaps_api_protection_generate" /><button class="button button-primary" type="submit"><?php echo esc_html__( 'Generate an API key', 'dydaps-api-protection' ); ?></button></form><?php endif; ?></section>

		<section id="dydaps-integration" class="dydaps-api-protection-card" aria-labelledby="dydaps-integration-heading"><h2 id="dydaps-integration-heading"><?php echo esc_html__( 'Integration examples', 'dydaps-api-protection' ); ?></h2><p><?php echo esc_html__( 'Replace YOUR_API_KEY with the key above. The key does not bypass WordPress authentication or route permissions.', 'dydaps-api-protection' ); ?></p><h3><?php echo esc_html__( 'cURL', 'dydaps-api-protection' ); ?></h3><pre class="dydaps-api-protection-value"><code id="dydaps-curl-example">curl -H "<?php echo esc_html( $this->header_name ); ?>: YOUR_API_KEY" https://example.com/wp-json/wp/v2/posts</code></pre><button class="button" type="button" data-dydaps-copy-target="#dydaps-curl-example" data-dydaps-default-label="<?php echo esc_attr__( 'Copy example', 'dydaps-api-protection' ); ?>" id="dydaps-curl-copy"><?php echo esc_html__( 'Copy example', 'dydaps-api-protection' ); ?></button><h3><?php echo esc_html__( 'JavaScript', 'dydaps-api-protection' ); ?></h3><pre class="dydaps-api-protection-value"><code id="dydaps-js-example">fetch("https://example.com/wp-json/wp/v2/posts", { headers: { "<?php echo esc_html( $this->header_name ); ?>": "YOUR_API_KEY" } });</code></pre><button class="button" type="button" data-dydaps-copy-target="#dydaps-js-example" data-dydaps-default-label="<?php echo esc_attr__( 'Copy JavaScript example', 'dydaps-api-protection' ); ?>"><?php echo esc_html__( 'Copy JavaScript example', 'dydaps-api-protection' ); ?></button></section>
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

		// verify_admin_action() checks the capability and nonce before these values are read.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$selected_routes = isset( $_POST['dydaps_protected_routes'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['dydaps_protected_routes'] ) ) : array();
		$custom_routes   = isset( $_POST['dydaps_custom_route_prefixes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['dydaps_custom_route_prefixes'] ) ) : '';
		$public_routes   = isset( $_POST['dydaps_public_route_prefixes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['dydaps_public_route_prefixes'] ) ) : null;
		$block_all       = isset( $_POST['dydaps_block_all_rest_api'] ) ? '1' : '0';
		$route_prefixes  = array();

		foreach ( $selected_routes as $selected_route ) {
			$normalized = Dydaps_Api_Route_Utils::normalize_route_prefix( $selected_route );
			if ( '' !== $normalized ) {
				$route_prefixes[] = $normalized;
			}
		}

		$route_prefixes  = array_values( array_unique( array_merge( $route_prefixes, Dydaps_Api_Route_Utils::parse_routes_from_input( $custom_routes ) ) ) );
		$public_prefixes = null !== $public_routes ? Dydaps_Api_Route_Utils::parse_routes_from_input( $public_routes ) : null;

		Dydaps_Api_Option_Store::set( $this->protected_routes_option_name, $route_prefixes );
		Dydaps_Api_Option_Store::set( $this->block_all_option_name, $block_all );
		if ( null !== $public_prefixes ) {
			Dydaps_Api_Option_Store::set( $this->public_routes_option_name, $public_prefixes );
		}
		if ( isset( $_POST['dydaps_allow_internal_rest_requests'] ) ) {
			Dydaps_Api_Option_Store::set( $this->allow_internal_option_name, '1' );
		} elseif ( isset( $_POST['dydaps_internal_setting_submitted'] ) ) {
			Dydaps_Api_Option_Store::set( $this->allow_internal_option_name, '0' );
		}

		$this->redirect_to_settings();
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/** Save exception rows after validating every route and method. */
	public function handle_save_exceptions() {
		$this->verify_admin_action( 'dydaps_api_protection_save_exceptions', 'dydaps_api_protection_save_exceptions' );
		// verify_admin_action() checks the capability and nonce before these values are read.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Exception routes are validated against a strict route pattern grammar by sanitize_exception().
		$submitted  = isset( $_POST['dydaps_exceptions'] ) ? (array) wp_unslash( $_POST['dydaps_exceptions'] ) : array();
		$removed    = isset( $_POST['dydaps_remove_exceptions'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['dydaps_remove_exceptions'] ) ) : array();
		$exceptions = array();
		foreach ( $submitted as $index => $row ) {
			if ( ! is_array( $row ) || in_array( absint( $index ), $removed, true ) ) {
				continue;
			}
			$exception = $this->sanitize_exception( $row );
			if ( null !== $exception ) {
				$exceptions[] = $exception;
			}
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Exception routes are validated against a strict route pattern grammar by sanitize_exception().
		$new_exception = isset( $_POST['dydaps_new_exception'] ) ? (array) wp_unslash( $_POST['dydaps_new_exception'] ) : array();
		if ( ! empty( $new_exception['route'] ) ) {
			$exception = $this->sanitize_exception( $new_exception );
			if ( null !== $exception ) {
				$exceptions[] = $exception;
			}
		}
		$unique = array();
		foreach ( $exceptions as $exception ) {
			$key            = $exception['method'] . ' ' . Dydaps_Api_Route_Utils::normalize_exception_route( $exception['route'] );
			$unique[ $key ] = $exception;
		}
		$submitted_namespaces = isset( $_POST['dydaps_allowed_namespaces'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['dydaps_allowed_namespaces'] ) ) : array();
		$known_namespaces     = array_merge( array_keys( Dydaps_Api_Namespace_Registry::get_inventory() ), Dydaps_Api_Option_Store::get_namespaces( $this->allowed_namespaces_option_name ) );
		$submitted_namespaces = array_values( array_intersect( $submitted_namespaces, $known_namespaces ) );
		$legacy_public_routes = isset( $_POST['dydaps_legacy_public_routes'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['dydaps_legacy_public_routes'] ) ) : array();
		$existing_public      = $this->get_public_route_prefixes();
		$remaining_public     = array_values( array_intersect( $existing_public, array_map( array( 'Dydaps_Api_Route_Utils', 'normalize_route_prefix' ), $legacy_public_routes ) ) );
		$has_enabled_rules    = ! empty( $submitted_namespaces ) || ! empty( $remaining_public );
		foreach ( $unique as $exception ) {
			if ( ! empty( $exception['enabled'] ) ) {
				$has_enabled_rules = true;
				break;
			}
		}
		$confirmed_public_access = isset( $_POST['dydaps_confirm_public_access'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['dydaps_confirm_public_access'] ) );
		if ( $has_enabled_rules && ! $confirmed_public_access ) {
			wp_die( esc_html__( 'Confirm that you understand these exceptions allow anonymous API requests before saving.', 'dydaps-api-protection' ), esc_html__( 'Confirmation required', 'dydaps-api-protection' ), array( 'response' => 400 ) );
		}
		Dydaps_Api_Option_Store::set( Dydaps_Api_Config::OPTION_ANONYMOUS_EXCEPTIONS, array_values( $unique ) );
		Dydaps_Api_Option_Store::set( $this->allowed_namespaces_option_name, $submitted_namespaces );
		Dydaps_Api_Option_Store::set( $this->public_routes_option_name, $remaining_public );
		$this->redirect_to_settings( true );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/** Save optional logging and retention settings. */
	public function handle_save_logging() {
		$this->verify_admin_action( 'dydaps_api_protection_save_logging', 'dydaps_api_protection_save_logging' );
		// verify_admin_action() checks the capability and nonce before these values are read.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$days = isset( $_POST['dydaps_log_retention_days'] ) ? absint( wp_unslash( $_POST['dydaps_log_retention_days'] ) ) : 7;
		if ( ! in_array( $days, array( 7, 14, 30, 90 ), true ) ) {
			$days = 7;
		}
		Dydaps_Api_Option_Store::set( Dydaps_Api_Config::OPTION_LOG_RETENTION_DAYS, (string) $days );
		$max_events = isset( $_POST['dydaps_log_max_events'] ) ? absint( wp_unslash( $_POST['dydaps_log_max_events'] ) ) : Dydaps_Api_Event_Logger::MAX_EVENTS;
		if ( ! in_array( $max_events, array( 50, 100, Dydaps_Api_Event_Logger::MAX_EVENTS ), true ) ) {
			$max_events = Dydaps_Api_Event_Logger::MAX_EVENTS;
		}
		Dydaps_Api_Option_Store::set( Dydaps_Api_Config::OPTION_LOG_MAX_EVENTS, (string) $max_events );
		Dydaps_Api_Option_Store::set( Dydaps_Api_Config::OPTION_LOGGING_ENABLED, isset( $_POST['dydaps_logging_enabled'] ) ? '1' : '0' );
		if ( ! isset( $_POST['dydaps_logging_enabled'] ) && function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( Dydaps_Api_Event_Logger::CRON_HOOK );
		}
		$this->redirect_to_settings( true );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/** Clear retained security event details. */
	public function handle_clear_logs() {
		$this->verify_admin_action( 'dydaps_api_protection_clear_logs', 'dydaps_api_protection_clear_logs' );
		if ( $this->event_logger instanceof Dydaps_Api_Event_Logger ) {
			$this->event_logger->clear();
		}
		$this->redirect_to_log_result( 'cleared' );
	}

	/** Delete only activity events older than a selected supported period. */
	public function handle_delete_old_logs() {
		$this->verify_admin_action( 'dydaps_api_protection_delete_old_logs', 'dydaps_api_protection_delete_old_logs' );
		// Capability and nonce checks have completed before reading the submitted age.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$days = isset( $_POST['dydaps_log_age_days'] ) ? absint( wp_unslash( $_POST['dydaps_log_age_days'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! in_array( $days, array( 7, 14, 30 ), true ) ) {
			wp_die( esc_html__( 'Choose a supported age before deleting events.', 'dydaps-api-protection' ), esc_html__( 'Invalid request', 'dydaps-api-protection' ), array( 'response' => 400 ) );
		}
		if ( $this->event_logger instanceof Dydaps_Api_Event_Logger ) {
			$this->event_logger->delete_older_than( $days );
		}
		$this->redirect_to_log_result( 'deleted' );
	}

	/** Redirect to the activity section after a log management action.
	 *
	 * @param string $result Whitelisted log-action result.
	 * @return void
	 */
	private function redirect_to_log_result( $result ) {
		wp_safe_redirect( add_query_arg( 'dydaps_log_result', sanitize_key( $result ), $this->get_settings_page_url() . '#dydaps-activity' ) );
		exit;
	}

	/** Apply the confirmed strict recommendation, replacing all anonymous access exceptions. */
	public function handle_apply_recommendation() {
		$this->verify_admin_action( 'dydaps_api_protection_apply_recommendation', 'dydaps_api_protection_apply_recommendation' );
		// The nonce and capability were checked by verify_admin_action().
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$confirmed   = isset( $_POST['dydaps_confirm_recommended_protection'] ) && is_string( $_POST['dydaps_confirm_recommended_protection'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['dydaps_confirm_recommended_protection'] ) );
		$fingerprint = isset( $_POST['dydaps_policy_fingerprint'] ) && is_string( $_POST['dydaps_policy_fingerprint'] ) ? sanitize_text_field( wp_unslash( $_POST['dydaps_policy_fingerprint'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! $confirmed ) {
			wp_die( esc_html__( 'Confirm the recommended change before applying it.', 'dydaps-api-protection' ), esc_html__( 'Confirmation required', 'dydaps-api-protection' ), array( 'response' => 400 ) );
		}
		if ( '' === $fingerprint || ! hash_equals( $this->get_policy_fingerprint(), $fingerprint ) ) {
			wp_die( esc_html__( 'The protection settings changed after analysis. Run the check again before applying it.', 'dydaps-api-protection' ), esc_html__( 'Settings changed', 'dydaps-api-protection' ), array( 'response' => 409 ) );
		}
		if ( $this->is_recommended_configuration() ) {
			$this->redirect_to_settings( false, 'already_configured' );
		}
		Dydaps_Api_Option_Store::set( $this->block_all_option_name, '1' );
		Dydaps_Api_Option_Store::set( $this->public_routes_option_name, array() );
		Dydaps_Api_Option_Store::set( $this->allowed_namespaces_option_name, array() );
		Dydaps_Api_Option_Store::set( Dydaps_Api_Config::OPTION_ANONYMOUS_EXCEPTIONS, array() );
		if ( ! $this->is_recommended_configuration() ) {
			wp_die( esc_html__( 'The recommended configuration could not be verified after saving. Review your settings and try again.', 'dydaps-api-protection' ), esc_html__( 'Configuration not confirmed', 'dydaps-api-protection' ), array( 'response' => 500 ) );
		}
		$this->redirect_to_settings( false, 'applied' );
	}

	/**
	 * Determine the persistent status from the effective saved protection rules.
	 *
	 * @param bool     $strict           Whether strict anonymous protection is enabled.
	 * @param string[] $protected_routes Protected REST route prefixes.
	 * @param string[] $public_routes    Public REST route prefixes.
	 * @param string[] $namespaces       Legacy public namespaces.
	 * @param array    $exceptions       Method-specific public exceptions.
	 * @return string Status key: configured, customized, or needs_configuration.
	 */
	private function get_protection_configuration_status( $strict, array $protected_routes, array $public_routes, array $namespaces, array $exceptions ) {
		if ( ! empty( $public_routes ) || ! empty( $namespaces ) ) {
			return 'needs_configuration';
		}

		$enabled_exceptions = array_filter(
			$exceptions,
			static function ( $exception ) {
				return ! empty( $exception['enabled'] );
			}
		);

		foreach ( $enabled_exceptions as $exception ) {
			if ( Dydaps_Api_Route_Utils::route_matches_prefixes( $exception['route'], $protected_routes ) ) {
				return 'needs_configuration';
			}
		}

		if ( $strict ) {
			return empty( $enabled_exceptions ) ? 'configured' : 'customized';
		}

		if ( ! empty( $enabled_exceptions ) ) {
			return 'needs_configuration';
		}

		return empty( $protected_routes ) ? 'needs_configuration' : 'customized';
	}

	/** Check whether saved rules already match the strict recommendation. */
	private function is_recommended_configuration() {
		$namespaces            = '' !== $this->allowed_namespaces_option_name ? Dydaps_Api_Option_Store::get_namespaces( $this->allowed_namespaces_option_name ) : array();
		$exceptions            = $this->normalize_saved_exceptions( (array) get_option( Dydaps_Api_Config::OPTION_ANONYMOUS_EXCEPTIONS, array() ) );
		$has_enabled_exception = (bool) array_filter(
			$exceptions,
			static function ( $exception ) {
				return ! empty( $exception['enabled'] );
			}
		);

		return $this->is_block_all_enabled()
			&& empty( $this->get_public_route_prefixes() )
			&& empty( $namespaces )
			&& ! $has_enabled_exception;
	}

	/** Build a fingerprint for the access policy shown during read-only analysis. */
	private function get_policy_fingerprint() {
		$policy = array(
			'block_all'  => get_option( $this->block_all_option_name, false ),
			'public'     => get_option( $this->public_routes_option_name, array() ),
			'namespaces' => get_option( $this->allowed_namespaces_option_name, array() ),
			'exceptions' => get_option( Dydaps_Api_Config::OPTION_ANONYMOUS_EXCEPTIONS, array() ),
		);
		return hash( 'sha256', wp_json_encode( $policy ) );
	}

	/** Analyze routes and saved rules without modifying configuration.
	 *
	 * @param array    $endpoints             Registered endpoint inventory.
	 * @param array    $namespace_inventory   Registered namespaces and owner labels.
	 * @param string[] $protected_prefixes    Protected route prefixes.
	 * @param string[] $allowed_namespaces    Legacy namespace exceptions.
	 * @param string[] $public_route_prefixes Legacy public route prefixes.
	 * @param array    $exceptions            Method-specific exceptions.
	 * @param array    $events                Sanitized diagnostic events.
	 * @param bool     $strict                Whether strict mode is enabled.
	 * @return array{broad_rules:int,broad_public_prefixes:array,uncertain_plugins:bool,recommend_strict:bool,active_plugins:int,legacy_namespaces:array,duplicate_exceptions:int,conflicts:int,recent_events:int,dydaps_denials:int}
	 */
	private function analyze_configuration( array $endpoints, array $namespace_inventory, array $protected_prefixes, array $allowed_namespaces, array $public_route_prefixes, array $exceptions, array $events, $strict ) {
		$plugin_routes = false;
		foreach ( $endpoints as $endpoint ) {
			if ( isset( $endpoint['owner'] ) && 'WordPress Core' !== $endpoint['owner'] && 'Unknown' !== $endpoint['owner'] ) {
				$plugin_routes = true;
				break;
			}
		}
		$broad_public_prefixes = array_values( $public_route_prefixes );
		$broad_rules           = count( $allowed_namespaces ) + count( $broad_public_prefixes );
		$conflicts             = $strict ? count( $allowed_namespaces ) : 0;
		foreach ( $public_route_prefixes as $prefix ) {
			if ( $strict ) {
				++$conflicts;
				continue;
			}
			foreach ( $protected_prefixes as $protected_prefix ) {
				if ( Dydaps_Api_Route_Utils::route_matches_prefixes( $prefix, array( $protected_prefix ) ) || Dydaps_Api_Route_Utils::route_matches_prefixes( $protected_prefix, array( $prefix ) ) ) {
					++$conflicts;
					break;
				}
			}
		}
		$seen_exceptions      = array();
		$duplicate_exceptions = 0;
		foreach ( $exceptions as $exception ) {
			$key = $exception['method'] . ' ' . Dydaps_Api_Route_Utils::normalize_exception_route( $exception['route'] );
			if ( isset( $seen_exceptions[ $key ] ) ) {
				++$duplicate_exceptions;
			} else {
				$seen_exceptions[ $key ] = true;
			}
			if ( ! empty( $exception['enabled'] ) && ( $strict || Dydaps_Api_Route_Utils::route_matches_prefixes( $exception['route'], $protected_prefixes ) ) ) {
				++$conflicts;
			}
		}
		$active_plugins = get_option( 'active_plugins', array() );
		if ( ! is_array( $active_plugins ) ) {
			$active_plugins = array();
		}
		$network_plugins = get_site_option( 'active_sitewide_plugins', array() );
		if ( is_array( $network_plugins ) ) {
			$active_plugins = array_unique( array_merge( $active_plugins, array_keys( $network_plugins ) ) );
		}
		$legacy_namespaces = array();
		foreach ( $allowed_namespaces as $namespace ) {
			$routes = array();
			foreach ( $endpoints as $endpoint ) {
				if ( Dydaps_Api_Route_Utils::route_matches_namespace( $endpoint['route'], $namespace ) ) {
					$routes[] = array(
						'route'   => $endpoint['route'],
						'methods' => $endpoint['methods'],
					);
				}
			}
			$legacy_namespaces[] = array(
				'namespace' => $namespace,
				'owner'     => isset( $namespace_inventory[ $namespace ]['owner'] ) ? $namespace_inventory[ $namespace ]['owner'] : __( 'Unknown', 'dydaps-api-protection' ),
				'routes'    => $routes,
			);
		}
		$dydaps_denials = 0;
		foreach ( $events as $event ) {
			if ( isset( $event['layer'] ) && 0 === strpos( $event['layer'], 'dydaps' ) ) {
				++$dydaps_denials;
			}
		}
		return array(
			'broad_rules'           => $broad_rules,
			'broad_public_prefixes' => $broad_public_prefixes,
			'uncertain_plugins'     => $plugin_routes || ! empty( $active_plugins ),
			'recommend_strict'      => ! $strict,
			'active_plugins'        => count( $active_plugins ),
			'legacy_namespaces'     => $legacy_namespaces,
			'duplicate_exceptions'  => $duplicate_exceptions,
			'conflicts'             => $conflicts,
			'recent_events'         => count( $events ),
			'dydaps_denials'        => $dydaps_denials,
		);
	}

	/**
	 * Return active plugin names without inferring route ownership.
	 *
	 * @return string[]
	 */
	private function get_active_plugin_names() {
		if ( ! function_exists( 'get_plugins' ) ) {
			return array();
		}

		$active_plugins = get_option( 'active_plugins', array() );
		if ( ! is_array( $active_plugins ) ) {
			$active_plugins = array();
		}
		$network_plugins = get_site_option( 'active_sitewide_plugins', array() );
		if ( is_array( $network_plugins ) ) {
			$active_plugins = array_merge( $active_plugins, array_keys( $network_plugins ) );
		}

		$installed_plugins = get_plugins();
		$names             = array();
		foreach ( array_unique( $active_plugins ) as $plugin_file ) {
			if ( isset( $installed_plugins[ $plugin_file ]['Name'] ) && is_string( $installed_plugins[ $plugin_file ]['Name'] ) ) {
				$names[] = sanitize_text_field( $installed_plugins[ $plugin_file ]['Name'] );
			}
		}

		sort( $names, SORT_NATURAL | SORT_FLAG_CASE );
		return $names;
	}

	/** Validate and sanitize one exception form row.
	 *
	 * @param array $row Submitted exception data.
	 * @return array|null
	 */
	private function sanitize_exception( array $row ) {
		$method = isset( $row['method'] ) && is_string( $row['method'] ) ? strtoupper( sanitize_key( $row['method'] ) ) : '';
		$route  = isset( $row['route'] ) && is_string( $row['route'] ) ? Dydaps_Api_Route_Utils::normalize_exception_route( $row['route'] ) : '';
		if ( ! in_array( $method, array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) || '' === $route ) {
			return null;
		}
		return array(
			'method'      => $method,
			'route'       => $route,
			'description' => isset( $row['description'] ) && is_string( $row['description'] ) ? sanitize_text_field( $row['description'] ) : '',
			'enabled'     => isset( $row['enabled'] ) && '1' === (string) $row['enabled'],
		);
	}

	/** Sanitize saved exception rows before rendering.
	 *
	 * @param array $exceptions Saved exception data.
	 * @return array
	 */
	private function normalize_saved_exceptions( array $exceptions ) {
		$normalized = array();
		foreach ( $exceptions as $exception ) {
			if ( is_array( $exception ) ) {
				$clean = $this->sanitize_exception( $exception );
				if ( null !== $clean ) {
					$normalized[] = $clean;
				}
			}
		}
		return $normalized;
	}

	/** Render the supported HTTP method selector.
	 *
	 * @param string $name           Field name.
	 * @param string $selected_method Currently selected method.
	 * @return void
	 */
	private function render_method_select( $name, $selected_method ) {
		printf( '<select name="%1$s">', esc_attr( $name ) );
		foreach ( array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ) as $method ) {
			printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $method ), selected( $selected_method, $method, false ) );
		}
		echo '</select>';
	}

	/** Determine whether a route is covered by a protection rule.
	 *
	 * @param string   $route     REST route.
	 * @param string[] $prefixes  Protected route prefixes.
	 * @param bool     $block_all Whether strict mode is enabled.
	 * @return bool
	 */
	private function endpoint_is_protected( $route, array $prefixes, $block_all ) {
		return (bool) $block_all || Dydaps_Api_Route_Utils::route_matches_prefixes( $route, $prefixes );
	}

	/** Determine whether a route and method have a configured exception.
	 *
	 * @param string $route      REST route.
	 * @param string $method     HTTP method.
	 * @param array  $exceptions Configured exception rows.
	 * @return bool
	 */
	private function endpoint_has_exception( $route, $method, array $exceptions ) {
		foreach ( $exceptions as $exception ) {
			if ( strtoupper( $exception['method'] ) === $method && Dydaps_Api_Route_Utils::normalize_exception_route( $exception['route'] ) === Dydaps_Api_Route_Utils::normalize_exception_route( $route ) ) {
				return true;
			}
		}
		return false;
	}

	/** Determine whether a matching exception is enabled.
	 *
	 * @param string $route      REST route.
	 * @param string $method     HTTP method.
	 * @param array  $exceptions Configured exception rows.
	 * @return bool
	 */
	private function endpoint_exception_enabled( $route, $method, array $exceptions ) {
		foreach ( $exceptions as $exception ) {
			if ( strtoupper( $exception['method'] ) === $method && Dydaps_Api_Route_Utils::normalize_exception_route( $exception['route'] ) === Dydaps_Api_Route_Utils::normalize_exception_route( $route ) && ! empty( $exception['enabled'] ) ) {
				return true;
			}
		}
		return false;
	}

	/** Determine whether a legacy prefix or namespace covers a route.
	 *
	 * @param string   $route           REST route.
	 * @param string[] $public_prefixes Legacy public prefixes.
	 * @param string[] $namespaces      Allowed namespaces.
	 * @return bool
	 */
	private function endpoint_has_legacy_exception( $route, array $public_prefixes, array $namespaces ) {
		if ( Dydaps_Api_Route_Utils::route_matches_prefixes( $route, $public_prefixes ) ) {
			return true;
		}
		foreach ( $namespaces as $namespace ) {
			if ( Dydaps_Api_Route_Utils::route_matches_namespace( $route, $namespace ) ) {
				return true;
			}
		}
		return false;
	}

	/** Collect warnings about legacy and conflicting route configuration.
	 *
	 * @param string[] $protected_route_prefixes Protected route prefixes.
	 * @param string[] $public_route_prefixes    Public route prefixes.
	 * @param string[] $allowed_namespaces       Allowed REST namespaces.
	 * @param array    $exceptions               Configured exception rows.
	 * @param bool     $strict                   Whether strict blocking is enabled.
	 * @return string[]
	 */
	private function get_configuration_warnings( array $protected_route_prefixes, array $public_route_prefixes, array $allowed_namespaces, array $exceptions, $strict ) {
		$warnings = array();
		if ( ! $strict ) {
			$warnings[] = __( 'Anonymous access remains open for routes outside the selected protected prefixes.', 'dydaps-api-protection' );
		}
		if ( false === get_option( Dydaps_Api_Config::OPTION_BLOCK_ALL_REST_API, false ) ) {
			$warnings[] = __( 'No saved strict protection value exists; this legacy installation retains its prior open-by-default behavior.', 'dydaps-api-protection' );
		}
		if ( ! empty( $protected_route_prefixes ) && ! get_option( Dydaps_Api_Config::OPTION_API_KEY_HASH, '' ) ) {
			$warnings[] = __( 'Routes that require an API client key are configured, but no key has been generated.', 'dydaps-api-protection' );
		}
		foreach ( $public_route_prefixes as $prefix ) {
			if ( '/' === $prefix || strlen( $prefix ) < 12 ) {
				/* translators: %s is a saved public REST route prefix. */
				$warnings[] = sprintf( __( 'Legacy public route prefix %s may expose multiple endpoints and methods.', 'dydaps-api-protection' ), $prefix );
			}
			if ( Dydaps_Api_Route_Utils::route_matches_prefixes( $prefix, $protected_route_prefixes ) ) {
				/* translators: %s is a saved public REST route prefix. */
				$warnings[] = sprintf( __( 'Public route prefix %s overrides a protected route prefix.', 'dydaps-api-protection' ), $prefix );
			}
		}
		foreach ( $protected_route_prefixes as $prefix ) {
			if ( Dydaps_Api_Route_Utils::route_matches_prefixes( $prefix, $public_route_prefixes ) ) {
				/* translators: %s is a saved protected REST route prefix. */
				$warnings[] = sprintf( __( 'Protected route prefix %s is covered by a broader public prefix.', 'dydaps-api-protection' ), $prefix );
			}
		}
		foreach ( $allowed_namespaces as $namespace ) {
			/* translators: %s is a saved REST namespace. */
			$warnings[] = sprintf( __( 'Legacy namespace exception %s applies to every route registered in that namespace.', 'dydaps-api-protection' ), $namespace );
		}
		$keys = array();
		foreach ( $exceptions as $exception ) {
			$key = $exception['method'] . ' ' . Dydaps_Api_Route_Utils::normalize_exception_route( $exception['route'] );
			if ( false !== strpos( $exception['route'], '__DYDAPS_PARAM_' ) ) {
				/* translators: %s is a saved legacy exception route. */
				$warnings[] = sprintf( __( 'Legacy exception route %s contains an old parameter placeholder. It is now treated literally; review and replace it with an explicit route pattern if needed.', 'dydaps-api-protection' ), $exception['route'] );
			}
			if ( isset( $keys[ $key ] ) ) {
				/* translators: %s is an HTTP method and route pattern. */
				$warnings[] = sprintf( __( 'Duplicate route exception: %s.', 'dydaps-api-protection' ), $key );
			}
			if ( ! empty( $exception['enabled'] ) && Dydaps_Api_Route_Utils::route_matches_prefixes( $exception['route'], $protected_route_prefixes ) ) {
				/* translators: %s is an HTTP method and route pattern. */
				$warnings[] = sprintf( __( 'Enabled exception %s overrides a protected route rule for that method.', 'dydaps-api-protection' ), $key );
			}
			$keys[ $key ] = true;
		}
		return array_values( array_unique( $warnings ) );
	}

	/** Format a sanitized event reason for display.
	 *
	 * @param array $event Security event.
	 * @return string
	 */
	private function format_event_reason( array $event ) {
		$messages = array(
			'anonymous_access_denied'           => __( 'An anonymous request was blocked by the active protection rule.', 'dydaps-api-protection' ),
			'missing_dydaps_api_key'            => __( 'This route requires an API client key. No key was sent, and a key does not sign in a WordPress user.', 'dydaps-api-protection' ),
			'invalid_dydaps_api_key'            => __( 'The API client key was not accepted. A key does not sign in a WordPress user.', 'dydaps-api-protection' ),
			'invalid_wordpress_nonce'           => __( 'WordPress rejected the REST nonce. Sign in again and reload the editor.', 'dydaps-api-protection' ),
			'missing_wordpress_authentication'  => __( 'WordPress did not receive a signed-in session for this request.', 'dydaps-api-protection' ),
			'insufficient_wordpress_capability' => __( 'The WordPress account may not have permission for this operation.', 'dydaps-api-protection' ),
			'wordpress_route_permission_denied' => __( 'The REST route permission check rejected this request.', 'dydaps-api-protection' ),
			'unknown_wordpress_denial'          => __( 'WordPress returned an access denial. The saved details do not identify whether authentication or route permissions caused it.', 'dydaps-api-protection' ),
			'rest_response_denied'              => __( 'WordPress returned an access denial. This earlier record does not contain enough detail to identify the cause.', 'dydaps-api-protection' ),
			'wordpress_authentication_error'    => __( 'WordPress authentication rejected the request. Sign in again and retry.', 'dydaps-api-protection' ),
		);
		return isset( $messages[ $event['reason'] ] ) ? $messages[ $event['reason'] ] : __( 'The cause was not recorded. Review this request in WordPress diagnostics.', 'dydaps-api-protection' );
	}

	/**
	 * Describe whether the saved evidence points to a protection rule or needs review.
	 *
	 * @param array $event Security event.
	 * @return string
	 */
	private function format_event_assessment( array $event ) {
		if ( 'dydaps_anonymous_block' === $event['layer'] ) {
			return __( 'Protection rule applied', 'dydaps-api-protection' );
		}
		if ( 'dydaps_api_key' === $event['layer'] ) {
			return __( 'API client credentials need review', 'dydaps-api-protection' );
		}
		if ( 'wordpress_authentication' === $event['layer'] ) {
			return __( 'WordPress authentication needs review', 'dydaps-api-protection' );
		}
		if ( 'wordpress_permission' === $event['layer'] ) {
			return __( 'WordPress permissions need review', 'dydaps-api-protection' );
		}
		return __( 'Cause unknown; review the request', 'dydaps-api-protection' );
	}

	/** Format a rule identifier for display.
	 *
	 * @param string $rule Rule identifier.
	 * @return string
	 */
	private function format_event_rule( $rule ) {
		$labels = array(
			'strict_mode'             => __( 'Strict anonymous mode', 'dydaps-api-protection' ),
			'protected_route'         => __( 'Protected route rule', 'dydaps-api-protection' ),
			'authentication_provider' => __( 'WordPress authentication provider', 'dydaps-api-protection' ),
		);
		return isset( $labels[ $rule ] ) ? $labels[ $rule ] : $rule;
	}

	/** Format an authentication category for display.
	 *
	 * @param string $auth Authentication category.
	 * @return string
	 */
	private function format_event_auth( $auth ) {
		$labels = array(
			'anonymous'                        => __( 'No WordPress session', 'dydaps-api-protection' ),
			'wordpress_authentication_error'   => __( 'WordPress authentication error', 'dydaps-api-protection' ),
			'wordpress_user'                   => __( 'Signed-in WordPress user', 'dydaps-api-protection' ),
			'unknown'                          => __( 'Unknown', 'dydaps-api-protection' ),
			'invalid_wordpress_authentication' => __( 'Invalid WordPress authentication', 'dydaps-api-protection' ),
			'missing_wordpress_authentication' => __( 'No WordPress session', 'dydaps-api-protection' ),
			'wordpress_user_or_anonymous'      => __( 'WordPress user or anonymous client', 'dydaps-api-protection' ),
		);
		return isset( $labels[ $auth ] ) ? $labels[ $auth ] : $auth;
	}

	/** Format a security layer identifier for display.
	 *
	 * @param string $layer Security layer identifier.
	 * @return string
	 */
	private function format_event_layer( $layer ) {
		$labels = array(
			'dydaps_anonymous_block'   => __( 'REST API protection', 'dydaps-api-protection' ),
			'dydaps_api_key'           => __( 'API client key', 'dydaps-api-protection' ),
			'wordpress_authentication' => __( 'WordPress authentication', 'dydaps-api-protection' ),
			'wordpress_rest_response'  => __( 'Unknown WordPress denial', 'dydaps-api-protection' ),
			'wordpress_permission'     => __( 'WordPress route permission', 'dydaps-api-protection' ),
		);
		return isset( $labels[ $layer ] ) ? $labels[ $layer ] : $layer;
	}

	/** Save the conservative internal dispatch opt-in. */
	public function handle_save_internal() {
		$this->verify_admin_action( 'dydaps_api_protection_save_internal', 'dydaps_api_protection_save_internal' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		Dydaps_Api_Option_Store::set( $this->allow_internal_option_name, isset( $_POST['dydaps_allow_internal_rest_requests'] ) ? '1' : '0' );
		$this->redirect_to_settings( true );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/** Save only valid, unique namespace identifiers. */
	public function handle_save_namespaces() {
		$this->verify_admin_action( 'dydaps_api_protection_save_namespaces', 'dydaps_api_protection_save_namespaces' );
		// verify_admin_action() checks the capability and nonce before this value is read.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$submitted          = isset( $_POST['dydaps_allowed_namespaces'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['dydaps_allowed_namespaces'] ) ) : array();
		$namespaces         = array();
		$registered         = array_fill_keys( array_keys( Dydaps_Api_Namespace_Registry::get_inventory() ), true );
		$previously_allowed = array_fill_keys( Dydaps_Api_Option_Store::get_namespaces( $this->allowed_namespaces_option_name ), true );
		foreach ( $submitted as $namespace ) {
			$namespace = Dydaps_Api_Route_Utils::normalize_namespace( $namespace );
			// New grants must reference an active namespace. Preserve only prior grants
			// for temporarily inactive extensions so administrators can remove them later.
			if ( '' !== $namespace && ( isset( $registered[ $namespace ] ) || isset( $previously_allowed[ $namespace ] ) ) ) {
				$namespaces[] = $namespace;
			}
		}
		Dydaps_Api_Option_Store::set( $this->allowed_namespaces_option_name, array_values( array_unique( $namespaces ) ) );
		$this->redirect_to_settings( true );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
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
			array(
				'response' => 403,
			)
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
				array(
					'response' => 400,
				)
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
	 * @param bool   $saved  Whether to show the saved notice.
	 * @param string $result Optional assistant result notice.
	 * @return void
	 */
	private function redirect_to_settings( $saved = false, $result = '' ) {
		$url = $this->get_settings_page_url() . ( $saved ? '&dydaps_saved=1' : '' );
		if ( '' !== $result ) {
			$url = add_query_arg( 'dydaps_result', sanitize_key( $result ), $url );
		}
		wp_safe_redirect( $url );
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

		return Dydaps_Api_Option_Store::get_route_prefixes( $this->protected_routes_option_name, array() );
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
		return Dydaps_Api_Option_Store::get_route_prefixes( $this->public_routes_option_name, array() );
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
