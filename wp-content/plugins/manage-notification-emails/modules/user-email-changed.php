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
 * since: 1.7.0
 */

// phpcs:disable WordPress.Security.NonceVerification.Missing
// phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load module famne_user_email_changed
 *
 * @return void
 */
function famne_load_mod_user_email_changed() {
	$famne_options = FAMNE::get_option( 'famne_options' );

	FAMNE::add_module(
		'email_changed_email',
		array(
			'name'      => 'Add user e-mailaddress change notifier',
			'version'   => '1.0.0',
			'option_id' => array( 'send_email_change_request_email_to_admin', 'send_email_changed_email_to_admin' ),
			'card'      => 'famne_card_user_email_changed',
		)
	);

	/**
	 * Render the settings card for this module.
	 *
	 * @param  array $famne_options Plugin options.
	 * @return void
	 */
	function famne_card_user_email_changed( $famne_options ) {
		$active = ! empty( $famne_options['send_email_change_request_email_to_admin'] ) || ! empty( $famne_options['send_email_changed_email_to_admin'] );
		?>
	<div class="card">
		<label class="switch"><div class="slider round <?php echo ( $active ? 'active' : '' ); ?>"><span class="on">ON</span><span class="off">OFF</span></div></label>
		<h2 class="title"><?php esc_html_e( 'E-mail address changed by user', 'manage-notification-emails' ); ?></h2>
		<?php famne_print_checkbox( $famne_options, 'send_email_change_request_email_to_admin', __( 'Request to change e-mailadress notification to admin', 'manage-notification-emails' ), __( 'Sends an e-mail to administrators after a user requested to update his or her e-mailaddress.', 'manage-notification-emails' ) ); ?>
		<?php famne_print_checkbox( $famne_options, 'send_email_changed_email_to_admin', __( 'User changed (confirmed) e-mail notification to admin', 'manage-notification-emails' ), __( 'Sends an e-mail to administrators after a user successfully updated his or her e-mailaddress.', 'manage-notification-emails' ) ); ?>
	</div>
		<?php
	}

	if ( ! empty( $famne_options['send_email_change_request_email_to_admin'] ) ) :

		/**
		 * Famne_user_email_change_request_to_admin
		 *
		 * @return void
		 */
		function famne_user_email_change_request_to_admin() {
			// stop if email is not right.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by core in profile.php before this action fires.
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			if ( ! is_email( $email ) || email_exists( $email ) ) {
				return;
			}

			$blog_name   = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
			$admin_email = get_option( 'admin_email' );

			/* translators: Do not translate USERNAME, ADMIN_EMAIL, EMAIL, SITENAME, SITEURL: those are placeholders. */
			$email_text = __(
				'Howdy admin,
        
        Recently a user requested to have his email address on his account changed.

        An email change request has been sent to ###EMAIL###
        
        Regards,
        All at ###SITENAME###
        ###SITEURL###',
				'manage-notification-emails'
			);

			$email_text = str_replace( '###EMAIL###', $email, $email_text );
			$email_text = str_replace( '###SITENAME###', $blog_name, $email_text );
			$email_text = str_replace( '###SITEURL###', home_url(), $email_text );

			/* translators: %s: site title. */
			wp_mail( $admin_email, sprintf( __( '[%s] User requested Email Change', 'manage-notification-emails' ), $blog_name ), $email_text );
		}
		add_action( 'personal_options_update', 'famne_user_email_change_request_to_admin' );

	endif;

	if ( ! empty( $famne_options['send_email_changed_email_to_admin'] ) ) :
		/**
		 * Also notify the site admin when a user changed their email address.
		 *
		 * @param  array $email_change_email Email data used for the user notification.
		 * @param  array $user               The original user array.
		 * @param  array $userdata           The updated user array.
		 * @return array $email_change_email
		 */
		function famne_user_email_changed_email_to_admin( $email_change_email, $user, $userdata ) {
			$blog_name   = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
			$admin_email = get_option( 'admin_email' );

			/* translators: Do not translate USERNAME, ADMIN_EMAIL, NEW_EMAIL, EMAIL, SITENAME, SITEURL: those are placeholders. */
			$email_text = __(
				'Hi admin,

    This notice confirms that the user ###USERNAME### changed his email address on ###SITENAME### from ###EMAIL### to ###NEW_EMAIL###.

    Regards,
    All at ###SITENAME###
    ###SITEURL###',
				'manage-notification-emails'
			);

			$email_text = str_replace( '###USERNAME###', $user['user_login'], $email_text );
			$email_text = str_replace( '###NEW_EMAIL###', $userdata['user_email'], $email_text );
			$email_text = str_replace( '###EMAIL###', $user['user_email'], $email_text );
			$email_text = str_replace( '###SITENAME###', $blog_name, $email_text );
			$email_text = str_replace( '###SITEURL###', home_url(), $email_text );

			/* translators: Email change notification email subject. %s: Site title. */
			wp_mail( $admin_email, sprintf( __( '[%s] Email Changed' ), $blog_name ), $email_text, $email_change_email['headers'] ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- Core string, uses the WordPress translation.

			return $email_change_email;
		}

		add_filter( 'email_change_email', 'famne_user_email_changed_email_to_admin', 10, 3 );

	endif;
}

add_action( 'fa_mne_modules', 'famne_load_mod_user_email_changed' );
