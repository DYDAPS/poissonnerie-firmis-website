=== Manage Notification E-mails ===
Contributors: virgial
Tags: notification,notify,user,email,switch
Requires at least: 5.2
Donate link: https://paypal.me/virgial/5
Tested up to: 7.1
Stable tag: 1.9.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enable and disable email notifications that WordPress sends to the admin and user. Works perfectly with many other plugins!

== Description ==

With this plugin you can switch the different WordPress notification e-mails on and off, like options as the new user and password change notifications send by WordPress to the administrator and user. Works perfectly in combination with a lot of other plugins!

Watch this nice tut made by Robert Orzanna: 
[youtube https://www.youtube.com/watch?v=66UkQKgSFio]


**The options you can manage are:**

1. New user notification to admin
2. New user notification to user
3. Notify post author
4. Notify moderator
5. Password change notification to admin
6. Password change notification to user
7. E-mail address change notification to user
8. Forgotten password e-mail to user
9. Forgotten password e-mail to administrator
10. Automatic WordPress core update e-mail
11. Automatic WordPress plugin update e-mail
12. Automatic WordPress theme update e-mail
13. Send admin notifications to extra admin e-mail addresses *(1.7.0)*
14. Send an e-mail to administrators after a user requested to update his or her e-mail address *(1.7.0)*
15. Send an e-mail to administrators after a user successfully updated his or her e-mail address *(1.7.0)*
16. Multi-site support! Now you can manage your notification settings in one single environment *(1.8.0)*
17. Export and import settings *(1.8.0)*

The automatic core, plugin, and theme updates have a special built-in feature. When one of these options is disabled, successful e-mails don't get send out, but failed updates still will send an e-mail to the admin.

This version contains some nice new stuff. If you find any issues, let me know. If you like the new features, please be so kind to give this plugin a good rating! It will help me to keep up the good work for you :-)

Want regular updates? Feel free to support me with a small donation :-)


== Installation ==

1. Upload `manage-notification-emails.zip` via the plugins menu
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Use the settings-page to enable or disable sending of notifications


== Frequently Asked Questions ==

= Is it active right away? =

Yes, after activating you can go to the settings page and disable or enable the notification e-mails that suits you.

= Can I use this plugin for multi-site? =
Yes! starting from version 1.8.0 multi-site is supported.

= Disabling user notifications does not work =
Some other plugins also use their custom notifications which overwrite the core notifications of WordPress. To be sure, please first try the plugin without other plugins installed or at least temporarily disable them.

== Screenshots ==

1. Settings view: Core options.
2. Settings view: Available modules.
3. Settings view: Multi-site network settings.

== Upgrade Notice ==
= 1.9.2 =
On multisite, sites that never got their own settings had all notifications disabled. They now get the default settings, so these sites send the notification e-mails again.

= 1.9.0 =
Security hardening. Requires WordPress 5.2+. Password resets now always work; disabling the forgotten password e-mail only stops the e-mail. On multisite the new user e-mail to the site admin follows the network registration notification setting.

== Changelog ==
= 1.9.2 =
FIXED: On multisite only the first site got the default settings, so on the other sites all notifications were disabled. A site without settings now gets the default settings.

= 1.9.1 =
FIXED: All PHP files now block direct access, as required by Plugin Check.
UPDATED: Resolved all Plugin Check warnings.
UPDATED: Tested up to WordPress 7.1.

= 1.9.0 =
FIXED: Disabling the password forgotten e-mail no longer blocks resetting the password, which also stopped the new user e-mail from being sent.
FIXED: On multisite the new user notification to the site admin now follows the network registration notification setting, as shown on the settings page.
FIXED: Security hardening of the settings page: all output is escaped, module options are sanitized, resetting and saving network settings check user capabilities and exporting settings requires a nonce.
UPDATED: Minimum required WordPress version is now 5.2.
UPDATED: Code follows the WordPress Coding Standards. All functions now use the famne_ prefix. The old function names still work, but are deprecated.
UPDATED: The password changed e-mail and the e-mail changed e-mail to the admin now use the WordPress texts, so they are translated in every language.
UPDATED: The donate button no longer loads external images.
UPDATED: License is now GPLv2 or later.
UPDATED: Dutch translation.

= 1.8.6 =
FIXED: Medium vulnerability in settings module. Thanks to Wordfence for reporting this.
UPDATED: Lowered the priority to 99 in the filters fixing some issues where plugins overwrite the disabling of sending the e-mails.

= 1.8.5 =
UPDATED: Compatibility fix with WP 6.1+ where new user notifications are managed by filters.

= 1.8.4 =
UPDATED: Refactoring.

= 1.8.3 =
FIXED: (CSRF) Security fix. Special thanks to Muhammad Daffa (Patchstack Alliance) for reporting this.

= 1.8.2 =
FIXED: fixed an issue with automatic plugin update notification.
FIXED: fixed issues with not correct activating options in multisite environment.
UPDATED: language corrections.

= 1.8.0 =
ADDED: Multi-site support.
ADDED: Import and export of settings.
UPDATED: A lot of files are refactored and added some more comments along with it.

For older versions, see [changelog.txt](https://plugins.svn.wordpress.org/manage-notification-emails/trunk/changelog.txt).
