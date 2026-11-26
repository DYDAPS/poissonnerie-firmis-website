<?php
/**
 * Manage notification emails settings page class
 * *
 * This file is part of the Manage Notification Emails plugin
 * You can find out more about this plugin at https://www.freeamigos.nl
 * Copyright (c) 2006-2015  Virgial Berveling
 *
 * @package WordPress
 * @author Virgial Berveling
 * @copyright 2006-2020
 *
 * since: 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load module famne_custom_recipients
 *
 * @return void
 */
function famne_load_mod_export_settings() {

	FAMNE::add_module(
		'export_settings',
		array(
			'name'      => 'Export and import settings',
			'version'   => '1.1.0',
			'option_id' => array( 'export_settings' ),
			'card'      => 'famne_card_export_settings',
		)
	);

	/**
	 * Card_famne_export_settings
	 *
	 * @return void
	 */
	function famne_card_export_settings() {
		global $pagenow;
		if ( ! FAMNESettingsPage::is_famne_settings_page() && ! FAMNESettingsPage::is_famne_network_settings_page() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only used to build the export link.
		$page         = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$downloadlink = wp_nonce_url(
			add_query_arg(
				array(
					'page'            => $page,
					'export-settings' => 'famne',
				),
				$pagenow
			),
			'famne_export_settings',
			'famne_export_nonce'
		);

		?>
	<div class="card">
		<h2 class="title"><?php esc_html_e( 'Export and import settings', 'manage-notification-emails' ); ?></h2>
		<?php esc_html_e( 'Here you can export or import your Manage notification e-mails settings.', 'manage-notification-emails' ); ?>
		<br/><br/><strong><?php esc_html_e( 'Export your settings in JSON format', 'manage-notification-emails' ); ?></strong><br/>
		<a href="<?php echo esc_url( $downloadlink ); ?>" class="button" target="_blank" rel="noopener"><?php esc_html_e( 'Export settings file', 'manage-notification-emails' ); ?></a>
		<br/><br/><strong><?php esc_html_e( 'Import setting in JSON format', 'manage-notification-emails' ); ?></strong><br/>
		<input type="file" id="settings-upload-file" name="settings-upload-file" accept="application/JSON" data-message="<?php esc_html_e( 'Do you want to overwrite your current settings?', 'manage-notification-emails' ); ?>"/>
		<input type="submit" class="button button-primary" id="settings-upload-submit" disabled="disabled" value="<?php esc_html_e( 'Start import', 'manage-notification-emails' ); ?>" />
		<div class="spacer"></div>
	</div>
		<?php
	}

	/**
	 * Added capability check for export
	 *
	 * @since 1.8.6
	 */
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$get_export_settings = ! empty( $_GET['export-settings'] ) ? sanitize_key( wp_unslash( $_GET['export-settings'] ) ) : '';
	if ( 'famne' === $get_export_settings ) :
		check_admin_referer( 'famne_export_settings', 'famne_export_nonce' );
		if ( ! headers_sent() ) {
			global $famne_options;

			header( 'Content-Disposition: attachment; filename="famne-settings-' . gmdate( 'Y-m-d-Hs' ) . '.json"' );
			header( 'Cache-Control: no-cache, must-revalidate' );
			header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
			if ( is_array( $famne_options ) ) {
				$famne_options['version'] = FA_MNE_VERSION;
			}
				echo wp_json_encode( $famne_options );
			die;
		} else {
			wp_die( esc_html__( 'Unexpected headers found.', 'manage-notification-emails' ) );
		}
	endif;

	add_filter( 'famne_sanitize_settings_page', 'famne_card_export_settings_sanitize', 99, 3 );

	/**
	 * Import settings from an uploaded JSON file.
	 *
	 * Runs inside the settings sanitize callback, so the nonce has already been
	 * verified by options.php (site) or check_admin_referer() (network).
	 *
	 * @param  array $input Sanitized settings.
	 * @return array
	 */
	function famne_card_export_settings_sanitize( $input ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- See function docblock.
		if ( ! empty( $_FILES['settings-upload-file'] ) && isset( $_FILES['settings-upload-file']['size'], $_FILES['settings-upload-file']['error'], $_FILES['settings-upload-file']['name'], $_FILES['settings-upload-file']['tmp_name'] ) ) :
			$file_error = (int) $_FILES['settings-upload-file']['error'];
			$file_size  = (int) $_FILES['settings-upload-file']['size'];
			$file_name  = sanitize_file_name( wp_unslash( $_FILES['settings-upload-file']['name'] ) );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Server generated path, validated with is_uploaded_file().
			$file_tmp = $_FILES['settings-upload-file']['tmp_name'];
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			if ( UPLOAD_ERR_NO_FILE === $file_error || 0 === $file_size ) :
				return $input;
			endif;

			// Check errors.
			if ( UPLOAD_ERR_OK !== $file_error || ! is_uploaded_file( $file_tmp ) ) {
				wp_die( esc_html__( 'Error uploading file. Please try again.', 'manage-notification-emails' ) );
			}

			// Check file type.
			if ( 'json' !== strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) ) ) {
				wp_die( esc_html__( 'Incorrect file type.', 'manage-notification-emails' ) );
			}

			// Check file size.
			if ( $file_size > 100000 ) {
				wp_die( esc_html__( 'File size exceeds maximum upload limit.', 'manage-notification-emails' ) );
			}

			// Read JSON.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local uploaded file.
			$json = json_decode( (string) file_get_contents( $file_tmp ), true );

			// Check if empty.
			if ( ! $json || ! is_array( $json ) ) {
				wp_die( esc_html__( 'Import file empty.', 'manage-notification-emails' ) );
			}

			// Check plugin version.
			if ( empty( $json['version'] ) || FA_MNE_VERSION !== $json['version'] ) {
				wp_die( esc_html__( 'The import file has a different plugin version.', 'manage-notification-emails' ) );
			}

			// Get all available options.
			$available_options = array();
			foreach ( FAMNE::default_options() as $key => $o ) :
				$available_options[] = $key;
			endforeach;
			foreach ( FAMNE::get_modules() as $mod ) :
				if ( ! empty( $mod->option_id ) && is_array( $mod->option_id ) ) :
					foreach ( $mod->option_id as $m ) :
							$available_options[] = $m;
						endforeach;
				endif;
			endforeach;

			$input = array();
			foreach ( $json as $key => $val ) :
				if ( in_array( $key, $available_options, true ) && is_scalar( $val ) ) {
					$input[ $key ] = sanitize_text_field( $val );
				}
			endforeach;
		endif;

		return $input;
	}
}

add_action( 'fa_mne_modules', 'famne_load_mod_export_settings' );
