<?php
/**
 * Deprecated functions.
 *
 * Wrappers for the function names used before 1.9.0, when all functions got the famne_ prefix.
 * They keep third party code that calls the old names working. A wrapper is only defined when
 * its replacement exists, so the old names exist under the same conditions as before.
 * The form field wrappers (print_checkbox() etc.) are in modules/settings-page/formfields.php.
 *
 * This file is part of the Manage Notification Emails plugin
 * You can find out more about this plugin at https://www.freeamigos.nl
 * Copyright (c) 2006-2015  Virgial Berveling
 *
 * @package WordPress
 * @author Virgial Berveling
 * @copyright 2006-2015
 *
 * since: 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Backwards compatible function names.

/*
 * Modules.
 */

if ( ! function_exists( 'load_mod_famne_settings_page' ) ) :
	/**
	 * Load the settings page module.
	 *
	 * @deprecated 1.9.0 Use famne_load_mod_settings_page().
	 *
	 * @return void
	 */
	function load_mod_famne_settings_page() {
		_deprecated_function( __FUNCTION__, '1.9.0', 'famne_load_mod_settings_page' );
		famne_load_mod_settings_page();
	}
endif;

if ( ! function_exists( 'load_mod_famne_user_email_changed' ) ) :
	/**
	 * Load the user e-mail changed module.
	 *
	 * @deprecated 1.9.0 Use famne_load_mod_user_email_changed().
	 *
	 * @return void
	 */
	function load_mod_famne_user_email_changed() {
		_deprecated_function( __FUNCTION__, '1.9.0', 'famne_load_mod_user_email_changed' );
		famne_load_mod_user_email_changed();
	}
endif;

if ( ! function_exists( 'load_mod_famne_custom_recipients' ) ) :
	/**
	 * Load the custom recipients module.
	 *
	 * @deprecated 1.9.0 Use famne_load_mod_custom_recipients().
	 *
	 * @return void
	 */
	function load_mod_famne_custom_recipients() {
		_deprecated_function( __FUNCTION__, '1.9.0', 'famne_load_mod_custom_recipients' );
		famne_load_mod_custom_recipients();
	}
endif;

if ( ! function_exists( 'load_mod_famne_export_settings' ) ) :
	/**
	 * Load the export settings module.
	 *
	 * @deprecated 1.9.0 Use famne_load_mod_export_settings().
	 *
	 * @return void
	 */
	function load_mod_famne_export_settings() {
		_deprecated_function( __FUNCTION__, '1.9.0', 'famne_load_mod_export_settings' );
		famne_load_mod_export_settings();
	}
endif;

/*
 * Module cards. The replacements are defined when the modules load on init.
 */

if ( ! function_exists( 'card_famne_user_email_changed' ) ) :
	/**
	 * Render the user e-mail changed card.
	 *
	 * @deprecated 1.9.0 Use famne_card_user_email_changed().
	 *
	 * @param array $famne_options Plugin options.
	 * @return void
	 */
	function card_famne_user_email_changed( $famne_options ) {
		_deprecated_function( __FUNCTION__, '1.9.0', 'famne_card_user_email_changed' );
		famne_card_user_email_changed( $famne_options );
	}
endif;

if ( ! function_exists( 'card_famne_custom_admin_recipients' ) ) :
	/**
	 * Render the custom admin recipients card.
	 *
	 * @deprecated 1.9.0 Use famne_card_custom_admin_recipients().
	 *
	 * @param array $famne_options Plugin options.
	 * @return void
	 */
	function card_famne_custom_admin_recipients( $famne_options ) {
		_deprecated_function( __FUNCTION__, '1.9.0', 'famne_card_custom_admin_recipients' );
		famne_card_custom_admin_recipients( $famne_options );
	}
endif;

if ( ! function_exists( 'card_famne_export_settings' ) ) :
	/**
	 * Render the export settings card.
	 *
	 * @deprecated 1.9.0 Use famne_card_export_settings().
	 *
	 * @return void
	 */
	function card_famne_export_settings() {
		_deprecated_function( __FUNCTION__, '1.9.0', 'famne_card_export_settings' );
		famne_card_export_settings();
	}
endif;

if ( ! function_exists( 'card_famne_export_settings_sanitize' ) ) :
	/**
	 * Import settings from an uploaded file while sanitizing the settings page.
	 *
	 * @deprecated 1.9.0 Use famne_card_export_settings_sanitize().
	 *
	 * @param array $input Settings input.
	 * @return array
	 */
	function card_famne_export_settings_sanitize( $input ) {
		_deprecated_function( __FUNCTION__, '1.9.0', 'famne_card_export_settings_sanitize' );
		return famne_card_export_settings_sanitize( $input );
	}
endif;

/*
 * Pluggable.
 */

if ( function_exists( 'famne_user_is_administrator' ) && ! function_exists( 'fa_user_is_administrator' ) ) :
	/**
	 * Check whether a user has the administrator role.
	 *
	 * @deprecated 1.9.0 Use famne_user_is_administrator().
	 *
	 * @param  int $user_id User ID.
	 * @return bool
	 */
	function fa_user_is_administrator( $user_id = 0 ) {
		_deprecated_function( __FUNCTION__, '1.9.0', 'famne_user_is_administrator' );
		return famne_user_is_administrator( $user_id );
	}
endif;

if ( function_exists( 'famne_dont_send_password_change_email' ) && ! function_exists( 'dont_send_password_change_email' ) ) :
	/**
	 * Email password change notification to registered user.
	 *
	 * @deprecated 1.9.0 Use famne_dont_send_password_change_email().
	 *
	 * @param  bool         $send     Whether to send the email.
	 * @param  array|object $user     The original user data.
	 * @param  array        $userdata Not used.
	 * @return bool
	 */
	function dont_send_password_change_email( $send = false, $user = '', $userdata = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Kept for the old signature.
		_deprecated_function( __FUNCTION__, '1.9.0', 'famne_dont_send_password_change_email' );
		return famne_dont_send_password_change_email( $send, $user );
	}
endif;

if ( has_filter( 'send_email_change_email', '__return_false' ) && ! function_exists( 'dont_send_email_change_email' ) ) :
	/**
	 * Email users e-mail change notification to registered user.
	 *
	 * @deprecated 1.9.0 Use __return_false().
	 *
	 * @return bool
	 */
	function dont_send_email_change_email() {
		_deprecated_function( __FUNCTION__, '1.9.0', '__return_false' );
		return false;
	}
endif;

if ( function_exists( 'famne_dont_send_password_forgotten_email' ) && ! function_exists( 'dont_send_password_forgotten_email' ) ) :
	/**
	 * Email forgotten password notification to registered user.
	 *
	 * @deprecated 1.9.0 Use famne_dont_send_password_forgotten_email().
	 *
	 * @param  bool $send    Whether to send the email.
	 * @param  int  $user_id User ID.
	 * @return bool
	 */
	function dont_send_password_forgotten_email( $send = true, $user_id = 0 ) {
		global $wp_version;

		_deprecated_function( __FUNCTION__, '1.9.0', 'famne_dont_send_password_forgotten_email' );

		// Before WordPress 6.1 the replacement has the same signature, see modules/pluggable/pluggable.php.
		if ( version_compare( $wp_version, '6.1.0' ) < 0 ) {
			return famne_dont_send_password_forgotten_email( $send, $user_id );
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return $send;
		}
		return famne_dont_send_password_forgotten_email( $send, $user->user_login, $user );
	}
endif;

if ( function_exists( 'famne_dont_send_auto_core_update_emails' ) && ! function_exists( 'fa_dont_sent_auto_core_update_emails' ) ) :
	/**
	 * Only send the core update email when the update failed.
	 *
	 * @deprecated 1.9.0 Use famne_dont_send_auto_core_update_emails().
	 *
	 * @param  bool   $send        Whether to send the email.
	 * @param  string $type        The type of email to send.
	 * @param  object $core_update Not used.
	 * @param  mixed  $result      Not used.
	 * @return bool
	 */
	function fa_dont_sent_auto_core_update_emails( $send, $type, $core_update = null, $result = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Kept for the old signature.
		_deprecated_function( __FUNCTION__, '1.9.0', 'famne_dont_send_auto_core_update_emails' );
		return famne_dont_send_auto_core_update_emails( $send, $type );
	}
endif;

if ( function_exists( 'famne_auto_plugin_update_send_email' ) && ! function_exists( 'fa_auto_plugin_update_send_email' ) ) :
	/**
	 * Only send the plugin update email when an update failed.
	 *
	 * @deprecated 1.9.0 Use famne_auto_plugin_update_send_email().
	 *
	 * @param  bool  $notifications_enabled  Whether to send the email.
	 * @param  array $update_results_plugins Plugin update results.
	 * @return bool
	 */
	function fa_auto_plugin_update_send_email( $notifications_enabled, $update_results_plugins ) {
		_deprecated_function( __FUNCTION__, '1.9.0', 'famne_auto_plugin_update_send_email' );
		return famne_auto_plugin_update_send_email( $notifications_enabled, $update_results_plugins );
	}
endif;

if ( function_exists( 'famne_auto_theme_update_send_email' ) && ! function_exists( 'fa_auto_theme_update_send_email' ) ) :
	/**
	 * Only send the theme update email when an update failed.
	 *
	 * @deprecated 1.9.0 Use famne_auto_theme_update_send_email().
	 *
	 * @param  bool  $notifications_enabled Whether to send the email.
	 * @param  array $update_results_theme  Theme update results.
	 * @return bool
	 */
	function fa_auto_theme_update_send_email( $notifications_enabled, $update_results_theme ) {
		_deprecated_function( __FUNCTION__, '1.9.0', 'famne_auto_theme_update_send_email' );
		return famne_auto_theme_update_send_email( $notifications_enabled, $update_results_theme );
	}
endif;

if ( function_exists( 'famne_new_user_notification_to_admin' ) && ! function_exists( 'fa_new_user_notification_to_admin' ) ) :
	/**
	 * Email the site admin about a newly-registered user.
	 *
	 * @deprecated 1.9.0 Use famne_new_user_notification_to_admin().
	 *
	 * @param int    $user_id    User ID.
	 * @param null   $deprecated Not used.
	 * @param string $notify     Who to notify.
	 * @return void
	 */
	function fa_new_user_notification_to_admin( $user_id, $deprecated, $notify = '' ) {
		_deprecated_function( __FUNCTION__, '1.9.0', 'famne_new_user_notification_to_admin' );
		famne_new_user_notification_to_admin( $user_id, $deprecated, $notify );
	}
endif;

if ( function_exists( 'famne_new_user_notification_to_user' ) && ! function_exists( 'fa_new_user_notification_to_user' ) ) :
	/**
	 * Email login credentials to a newly-registered user.
	 *
	 * @deprecated 1.9.0 Use famne_new_user_notification_to_user().
	 *
	 * @param int    $user_id    User ID.
	 * @param null   $deprecated Not used.
	 * @param string $notify     Who to notify.
	 * @return void
	 */
	function fa_new_user_notification_to_user( $user_id, $deprecated = null, $notify = '' ) {
		_deprecated_function( __FUNCTION__, '1.9.0', 'famne_new_user_notification_to_user' );
		famne_new_user_notification_to_user( $user_id, $deprecated, $notify );
	}
endif;

// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
