<?php
/**
 * Stop sending notification mails to the users.
 *
 * Version 1.5.5
 * fixed: A site without settings (on multisite every site but the first) uses the default settings instead of disabling all notifications.
 * version 1.5.4
 * fixed: On multisite the new user notification to admin follows the network registration notification setting.
 * version 1.5.3
 * fixed: Email automatic plugin update notification to admin option
 * version 1.5.2
 * added: Email automatic plugin update notification to admin option
 * added: Email automatic theme update notification to admin option
 * since 1.5.1
 * updated: the core pluggable function wp_new_user_notification
 * added: passing through the $deprecated and $notify
 * fixed notice of $deprecated
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
		'version' => '1.5.1',
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

			// Send a copy of password change notification to the admin,
			// but check to see if it's the admin whose password we're changing, and skip this.
			if ( 0 !== strcasecmp( $user->user_email, get_option( 'admin_email' ) ) ) {
				/* translators: %s: User name. */
				$message = sprintf( __( 'Password changed for user: %s' ), $user->user_login ) . "\r\n"; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Core string, uses the WordPress translation.
				// The blogname option is escaped with esc_html on the way into the database in sanitize_option
				// we want to reverse this for the plain text arena of emails.
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
	add_filter( 'send_password_change_email', 'famne_dont_send_password_change_email', 1, 2 );
endif;


if ( empty( $famne_options['send_email_change_email'] ) ) :
	/**
	 * Email users e-mail change notification to registered user.
	 */
	add_filter( 'send_email_change_email', '__return_false', 1 );
endif;


if ( ! function_exists( 'wp_new_user_notification' ) ) :
	/**
	 * Email login credentials to a newly-registered user.
	 *
	 * A new user registration notification is also sent to admin email.
	 *
	 * @param int    $user_id    User ID.
	 * @param null   $deprecated Not used.
	 * @param string $notify     Who to notify: 'admin', 'user', 'both' or ''.
	 */
	function wp_new_user_notification( $user_id, $deprecated = null, $notify = '' ) {

		global $famne_options;

		if ( ! empty( $famne_options['wp_new_user_notification_to_admin'] ) ) {
			famne_new_user_notification_to_admin( $user_id, $deprecated, $notify );
		}
		if ( ! empty( $famne_options['wp_new_user_notification_to_user'] ) ) {
			famne_new_user_notification_to_user( $user_id, $deprecated, $notify );
		}
	}
endif;

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
	 * @param  bool $send    Whether to allow the password to be reset.
	 * @param  int  $user_id User ID.
	 * @return bool
	 */
	function famne_dont_send_password_forgotten_email( $send = true, $user_id = 0 ) {
		global $famne_options;

		$is_administrator = famne_user_is_administrator( $user_id );

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
	add_filter( 'allow_password_reset', 'famne_dont_send_password_forgotten_email', 1, 3 );
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
	add_filter( 'auto_core_update_send_email', 'famne_dont_send_auto_core_update_emails', 10, 2 );
endif;


/**
 * Email the site admin about a newly-registered user.
 *
 * Most parts of this function are copied from wp_new_user_notification() in pluggable.php.
 *
 * @param int    $user_id    User ID.
 * @param null   $deprecated Not used.
 * @param string $notify     Who to notify: 'admin', 'user', 'both' or ''.
 */
function famne_new_user_notification_to_admin( $user_id, $deprecated, $notify = '' ) {

	if ( null !== $deprecated ) {
		_deprecated_argument( __FUNCTION__, '4.3.1' );
	}

	global $wpdb, $wp_hasher;
	$user = get_userdata( $user_id );

	// The blogname option is escaped with esc_html on the way into the database in sanitize_option
	// we want to reverse this for the plain text arena of emails.
	$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

	if ( 'user' !== $notify ) {
		$switched_locale = switch_to_locale( get_locale() );

		/* translators: %s: site title */
		$message = sprintf( __( 'New user registration on your site %s:' ), $blogname ) . "\r\n\r\n"; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Core string, uses the WordPress translation.
		/* translators: %s: user login */
		$message .= sprintf( __( 'Username: %s' ), $user->user_login ) . "\r\n\r\n"; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Core string, uses the WordPress translation.
		/* translators: %s: user email address */
		$message .= sprintf( __( 'Email: %s' ), $user->user_email ) . "\r\n"; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Core string, uses the WordPress translation.

		$wp_new_user_notification_email_admin = array(
			'to'      => get_option( 'admin_email' ),
			/* translators: New user registration notification email subject. %s: Site title */
			'subject' => __( '[%s] New User Registration' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Core string, uses the WordPress translation.
			'message' => $message,
			'headers' => '',
		);

		/**
		 * Filters the contents of the new user notification email sent to the site admin.
		 *
		 * @since 4.9.0
		 *
		 * @param array   $wp_new_user_notification_email {
		 *     Used to build wp_mail().
		 *
		 *     @type string $to      The intended recipient - site admin email address.
		 *     @type string $subject The subject of the email.
		 *     @type string $message The body of the email.
		 *     @type string $headers The headers of the email.
		 * }
		 * @param WP_User $user     User object for new user.
		 * @param string  $blogname The site title.
		 */
		$wp_new_user_notification_email_admin = apply_filters( 'wp_new_user_notification_email_admin', $wp_new_user_notification_email_admin, $user, $blogname ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, mirrors wp_new_user_notification().

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Mirrors core wp_new_user_notification().
		@wp_mail(
			$wp_new_user_notification_email_admin['to'],
			wp_specialchars_decode( sprintf( $wp_new_user_notification_email_admin['subject'], $blogname ) ),
			$wp_new_user_notification_email_admin['message'],
			$wp_new_user_notification_email_admin['headers']
		);

		if ( $switched_locale ) {
			restore_previous_locale();
		}
	}
}


/**
 * Email login credentials to a newly-registered user.
 *
 * Most parts of this function are copied from wp_new_user_notification() in pluggable.php.
 *
 * @param int    $user_id    User ID.
 * @param null   $deprecated Not used.
 * @param string $notify     Who to notify: 'admin', 'user', 'both' or ''.
 */
function famne_new_user_notification_to_user( $user_id, $deprecated = null, $notify = '' ) {
	if ( null !== $deprecated ) {
		_deprecated_argument( __FUNCTION__, '4.3.1' );
	}

	// Only accept user, admin, both or an empty value for the notify argument.
	if ( ! in_array( $notify, array( 'user', 'admin', 'both', '' ), true ) ) {
		return;
	}

	global $wpdb, $wp_hasher;
	$user = get_userdata( $user_id );

	// The blogname option is escaped with esc_html on the way into the database in sanitize_option
	// we want to reverse this for the plain text arena of emails.
	$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

	// `$deprecated was pre-4.3 `$plaintext_pass`. An empty `$plaintext_pass` didn't sent a user notification.
	if ( 'admin' === $notify || ( empty( $deprecated ) && empty( $notify ) ) ) {
		return;
	}

	// Generate something random for a password reset key.
	$key = wp_generate_password( 20, false );

	/** This action is documented in wp-login.php */
	do_action( 'retrieve_password_key', $user->user_login, $key ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, mirrors wp_new_user_notification().

	// Now insert the key, hashed, into the DB.
	if ( empty( $wp_hasher ) ) {
		require_once ABSPATH . WPINC . '/class-phpass.php';
		$wp_hasher = new PasswordHash( 8, true ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Mirrors core wp_new_user_notification().
	}
	$hashed = time() . ':' . $wp_hasher->HashPassword( $key );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Mirrors core wp_new_user_notification().
	$wpdb->update( $wpdb->users, array( 'user_activation_key' => $hashed ), array( 'user_login' => $user->user_login ) );

	$switched_locale = switch_to_locale( get_user_locale( $user ) );

	/* translators: %s: user login */
	$message  = sprintf( __( 'Username: %s' ), $user->user_login ) . "\r\n\r\n"; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Core string, uses the WordPress translation.
	$message .= __( 'To set your password, visit the following address:' ) . "\r\n\r\n"; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Core string, uses the WordPress translation.
	$message .= '<' . network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user->user_login ), 'login' ) . ">\r\n\r\n";

	$message .= wp_login_url() . "\r\n";

	$wp_new_user_notification_email = array(
		'to'      => $user->user_email,
		/* translators: Login details notification email subject. %s: Site title */
		'subject' => __( '[%s] Login Details' ), // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Core string, uses the WordPress translation.
		'message' => $message,
		'headers' => '',
	);

	/**
	 * Filters the contents of the new user notification email sent to the new user.
	 *
	 * @since 4.9.0
	 *
	 * @param array   $wp_new_user_notification_email {
	 *     Used to build wp_mail().
	 *
	 *     @type string $to      The intended recipient - New user email address.
	 *     @type string $subject The subject of the email.
	 *     @type string $message The body of the email.
	 *     @type string $headers The headers of the email.
	 * }
	 * @param WP_User $user     User object for new user.
	 * @param string  $blogname The site title.
	 */
	$wp_new_user_notification_email = apply_filters( 'wp_new_user_notification_email', $wp_new_user_notification_email, $user, $blogname ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, mirrors wp_new_user_notification().

	wp_mail(
		$wp_new_user_notification_email['to'],
		wp_specialchars_decode( sprintf( $wp_new_user_notification_email['subject'], $blogname ) ),
		$wp_new_user_notification_email['message'],
		$wp_new_user_notification_email['headers']
	);

	if ( $switched_locale ) {
		restore_previous_locale();
	}
}

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

	add_filter( 'auto_plugin_update_send_email', 'famne_auto_plugin_update_send_email', 10, 2 );
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

	add_filter( 'auto_theme_update_send_email', 'famne_auto_theme_update_send_email', 10, 2 );
endif;
