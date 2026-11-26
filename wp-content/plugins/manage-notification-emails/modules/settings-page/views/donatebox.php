<?php
/**
 * Custom view
 *
 * This file is part of the Manage Notification Emails plugin
 * You can find out more about this plugin at https://www.freeamigos.nl
 * Copyright (c) 2006-2015  Virgial Berveling
 *
 * @package WordPress
 * @author Virgial Berveling
 * @copyright 2006-2015
 *
 * since: 1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="donation-box">
	<p class="description"><?php esc_html_e( 'If you find this plugin useful, you can show your appreciation here :-)', 'manage-notification-emails' ); ?></p>
	<a href="https://paypal.me/virgial/5" class="button" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-coffee" style="vertical-align:text-bottom"></span> <?php esc_html_e( 'Donate', 'manage-notification-emails' ); ?></a>
</div>
<div class="clear"></div>
