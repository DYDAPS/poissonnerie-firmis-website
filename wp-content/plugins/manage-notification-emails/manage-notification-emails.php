<?php
/**
 * Plugin Name: Manage Notification E-mails
 * Description: This plugin gives you the option to disable some of the notification e-mails send by WordPress. It's a simple plugin but effective.
 * Version: 1.9.2
 * Author: Virgial Berveling
 * Author URI: https://www.freeamigos.nl
 * Text Domain: manage-notification-emails
 * Domain Path: /languages/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package manage-notification-emails
 */

/*
	Copyright (c) 2006-2015  Virgial Berveling

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

	You may also view the license here:
	https://www.gnu.org/licenses/gpl-2.0.html
*/


/*
	A NOTE ABOUT LICENSE:

	While this plugin is freely available and open-source under the GPLv2 (or later)
	license, that does not mean it is "public domain." You are free to modify
	and redistribute as long as you comply with the license. Any derivative
	work MUST be GPL licensed and available as open source.  You also MUST give
	proper attribution to the original author, copyright holder, and trademark
	owner.  This means you cannot change two lines of code and claim copyright
	of the entire work as your own.  The GPL license requires that if you
	modify this code, you must clearly indicate what section(s) you have
	modified and you may only claim copyright of your modifications and not
	the body of work.  If you are unsure or have questions about how a
	derivative work you are developing complies with the license, copyright,
	trademark, or if you do not understand the difference between
	open source and public domain, contact the original author at:
	https://www.freeamigos.nl/contact/.


	INSTALLATION PROCEDURE:

	Just put it in your plugins directory.
*/

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

define( 'FA_MNE_VERSION', '1.9.2' );
define( 'FA_MNE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'FA_MNE_PLUGIN_DIR', untrailingslashit( __DIR__ ) );
define( 'FA_MNE_SLUG', 'manage-notification-emails' );




/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 */

require_once FA_MNE_PLUGIN_DIR . '/core/class.FAMNE.php';
require_once FA_MNE_PLUGIN_DIR . '/core/functions.setup.php';
require_once FA_MNE_PLUGIN_DIR . '/modules/settings-page/settings-page.php';
require_once FA_MNE_PLUGIN_DIR . '/modules/pluggable/pluggable.php';
require_once FA_MNE_PLUGIN_DIR . '/modules/user-email-changed.php';
require_once FA_MNE_PLUGIN_DIR . '/modules/custom-recipients.php';
require_once FA_MNE_PLUGIN_DIR . '/modules/export-settings.php';
require_once FA_MNE_PLUGIN_DIR . '/core/functions.deprecated.php';

/**
 * Start the plugin.
 *
 * @return void
 */
function famne_init() {
	new FAMNE();
	add_action( 'plugins_loaded', 'FAMNE::update_check' );
}

// Remove all plugin options on uninstall.
register_uninstall_hook( __FILE__, 'FAMNE::uninstall' );

famne_init();
