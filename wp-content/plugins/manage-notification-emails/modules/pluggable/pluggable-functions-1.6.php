<?php
/**
 * Stop sending notification mails to the users.
 *
 * Version 1.6.3
 * fixed: A site without settings (on multisite every site but the first) uses the default settings instead of disabling all notifications.
 * version 1.6.2
 * fixed: Disabling the password forgotten e-mail no longer blocks password resets (and the new user e-mail) altogether.
 * fixed: On multisite the new user notification to admin follows the network registration notification setting.
 * version 1.6.1
 * added: Lowered the priority to 99 in the filters for new user notifications fixing some issues where plugins overwrite the disabling of sending the e-mails.
 *
 * @package manage-notification-emails
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

$famne_options = FAMNE::get_option( 'famne_options' );

// Not installed on this site yet: FAMNE::update_check() saves the defaults on plugins_loaded.
if ( false === $famne_options ) {
	$famne_options = FAMNE::default_options();
}

/*
 * On a multisite where each site manages its own settings, the new user notification
 * to the admin is managed network wide by the "registrationnotification" setting.
 */
if ( is_multisite() && ! FAMNE::network_managed() && is_array( $famne_options ) ) {
	$famne_options['wp_new_user_notification_to_admin'] = 'yes' === get_site_option( 'registrationnotification' ) ? '1' : '';
}

FAMNE::add_module(
	'pluggable',
	array(
		'name'    => 'Pluggable',
		'version' => '1.6.3',
	)
);

if ( ! function_exists( 'famne_dont_send_password_change_email' ) ) :
	/**
	 * Email password change notification to registered user.
	 *
	 * @param  bool         $send Whether to send the email.
	 * @param  array|object $user The original user data.
	 * @return bool
	 */
	function famne_dont_send_password_change_email( $send = false, $user = '' ) {
		global $famne_options;

		if ( is_array( $user ) ) {
			$user = (object) $user;
		}

		if ( ! empty( $famne_options['wp_password_change_notification'] ) ) :
			/*
			 * Send a copy of password change notification to the admin,
			 * but check to see if it's the admin whose password we're changing, and skip this.
			 */
			if ( 0 !== strcasecmp( $user->user_email, get_option( 'admin_email' ) ) ) {
				/* translators: %s: User name. */
				$message = sprintf( __( 'Password changed for user: %s' ), $user->user_login ) . "\r\n"; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Core string, uses the WordPress translation.

				/*
				 * The blogname option is escaped with esc_html() on the way into the database in sanitize_option().
				 * We want to reverse this for the plain text arena of emails.
				 */
				$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
				/* translators: Password change notification email subject. %s: Site title. */
				wp_mail( get_option( 'admin_email' ), sprintf( __( '[%s] Password Changed' ), $blogname ), $message ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Core string, uses the WordPress translation.
			}

		endif;

		if ( empty( $famne_options['send_password_change_email'] ) ) :
			return false;
		else :
			return true;
		endif;
	}
	add_filter( 'send_password_change_email', 'famne_dont_send_password_change_email', 99, 2 );
endif;


if ( empty( $famne_options['send_email_change_email'] ) ) :
	// Email users e-mail change notification to registered user.
	add_filter( 'send_email_change_email', '__return_false', 99, 3 );
endif;


if ( empty( $famne_options['wp_new_user_notification_to_admin'] ) ) {
	// Notify admin of new user registration.
	add_filter( 'wp_send_new_user_notification_to_admin', '__return_false', 99, 1 );
}

if ( empty( $famne_options['wp_new_user_notification_to_user'] ) ) {
	// Notify user of new user registration.
	add_filter( 'wp_send_new_user_notification_to_user', '__return_false', 99, 1 );
	add_filter( 'wpmu_welcome_user_notification', '__return_false', 99, 2 );
}


if ( empty( $famne_options['wp_notify_postauthor'] ) && ! function_exists( 'wp_notify_postauthor' ) ) :
	/**
	 * Notify an author (and/or others) of a comment/trackback/pingback on a post.
	 *
	 * @param int|WP_Comment $comment_id Comment ID or object.
	 * @param string|null    $deprecated Not used.
	 */
	function wp_notify_postauthor( $comment_id, $deprecated = null ) {}
endif;

if ( empty( $famne_options['wp_notify_moderator'] ) && ! function_exists( 'wp_notify_moderator' ) ) :
	/**
	 * Notifies the moderator of the blog about a new comment that is awaiting approval.
	 *
	 * @param int $comment_id Comment ID.
	 */
	function wp_notify_moderator( $comment_id ) {}
endif;


if ( empty( $famne_options['wp_password_change_notification'] ) && ! function_exists( 'wp_password_change_notification' ) ) :
	/**
	 * Notify the blog admin of a user changing password, normally via email.
	 *
	 * @param WP_User $user User object.
	 */
	function wp_password_change_notification( $user ) {}


endif;


if ( ( empty( $famne_options['send_password_forgotten_email'] ) || empty( $famne_options['send_password_admin_forgotten_email'] ) ) && ! function_exists( 'famne_dont_send_password_forgotten_email' ) ) :
	/**
	 * Email forgotten password notification to registered user.
	 *
	 * Only stops the e-mail; resetting the password stays allowed.
	 *
	 * @param  bool    $send       Whether to send the password reset e-mail.
	 * @param  string  $user_login The username for the user.
	 * @param  WP_User $user_data  WP_User object.
	 * @return bool
	 */
	function famne_dont_send_password_forgotten_email( $send, $user_login, $user_data ) {
		global $famne_options;

		$is_administrator = famne_user_is_administrator( $user_data->ID );

		if ( $is_administrator && empty( $famne_options['send_password_admin_forgotten_email'] ) ) {
			// Stop sending admin forgot email.
			return false;
		}
		if ( ! $is_administrator && empty( $famne_options['send_password_forgotten_email'] ) ) {
			// Stop sending user forgot email.
			return false;
		}
		// None of the above so give the default status back.
		return $send;
	}
	add_filter( 'send_retrieve_password_email', 'famne_dont_send_password_forgotten_email', 99, 3 );
endif;


if ( empty( $famne_options['auto_core_update_send_email'] ) && ! function_exists( 'famne_dont_send_auto_core_update_emails' ) ) :
	/**
	 * Only send the core update email when the update failed.
	 *
	 * @param  bool   $send Whether to send the email.
	 * @param  string $type The type of email to send.
	 * @return bool
	 */
	function famne_dont_send_auto_core_update_emails( $send, $type ) {
		if ( ! empty( $type ) && 'success' === $type ) {
			return false;
		}
		return true;
	}
	add_filter( 'auto_core_update_send_email', 'famne_dont_send_auto_core_update_emails', 99, 2 );
endif;


/**
 * Check whether a user has the administrator role.
 *
 * @param  int $user_id User ID.
 * @return bool
 */
function famne_user_is_administrator( $user_id = 0 ) {
	$user             = new WP_User( intval( $user_id ) );
	$is_administrator = false;
	if ( ! empty( $user->roles ) && is_array( $user->roles ) ) {
		foreach ( $user->roles as $role ) {
			if ( 'administrator' === strtolower( $role ) ) {
				$is_administrator = true;
			}
		}
	}
	return $is_administrator;
}


if ( empty( $famne_options['auto_plugin_update_send_email'] ) ) :
	/**
	 * Only send the automatic plugin update email when an update failed.
	 *
	 * @param  bool  $notifications_enabled  Whether notifications are enabled.
	 * @param  array $update_results_plugins Plugin update results.
	 * @return bool
	 */
	function famne_auto_plugin_update_send_email( $notifications_enabled, $update_results_plugins ) {
		$notifications_enabled = false;
		foreach ( $update_results_plugins as $update_result ) {
			// Do we have a failed update?
			if ( true !== $update_result->result ) {
				$notifications_enabled = true;
			}
		}
		return $notifications_enabled;
	}

	add_filter( 'auto_plugin_update_send_email', 'famne_auto_plugin_update_send_email', 99, 2 );
endif;


if ( empty( $famne_options['auto_theme_update_send_email'] ) ) :
	/**
	 * Only send the automatic theme update email when an update failed.
	 *
	 * @param  bool  $notifications_enabled Whether notifications are enabled.
	 * @param  array $update_results_theme  Theme update results.
	 * @return bool
	 */
	function famne_auto_theme_update_send_email( $notifications_enabled, $update_results_theme ) {
		$notifications_enabled = false;

		foreach ( $update_results_theme as $update_result ) {
			// Do we have a failed update?
			if ( true !== $update_result->result ) {
				$notifications_enabled = true;
			}
		}
		return $notifications_enabled;
	}

	add_filter( 'auto_theme_update_send_email', 'famne_auto_theme_update_send_email', 99, 2 );
endif;
