=== DYDAPS API Protection ===
Contributors: dydaps
Tags: rest api, api key, security, headless, protection
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Protect selected WordPress REST API routes with a secret API key sent in a custom HTTP header.

== Description ==

DYDAPS API Protection lets you secure selected WordPress REST API routes behind a secret API key.

Features:

* Protect selected REST API routes
* Generate, rotate, and revoke API keys
* Keep selected routes public through exceptions
* Block the full REST API by default when needed
* Use a lightweight admin interface with no external dependencies
* Ship with English and French translations

Every protected request must include the `X-DYDAPS-API-Key` header.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/dydaps-api-protection/`
2. Activate the plugin through the WordPress admin
3. Open `Settings > DYDAPS API Protection`
4. Generate your first API key

== Frequently Asked Questions ==

= How do I authenticate requests? =

Send the `X-DYDAPS-API-Key` header with your generated secret key.

= Can I leave some routes public? =

Yes. You can define public exceptions that remain accessible even when full REST API blocking is enabled.

= Is the plain API key stored in WordPress? =

No. Only a SHA-256 hash is stored. The plain key is shown once when it is generated or rotated.

== Changelog ==

= 1.2.0 =

* Added a lightweight autoloader and cleaned the internal plugin structure.
* Moved admin CSS and JavaScript into dedicated asset files.
* Tightened route prefix matching and improved translation maintenance.

= 1.1.0 =

* Added configurable protected REST API routes.
* Added API key generation, rotation, and revocation.
* Added public route exceptions and full REST API blocking mode.

= 1.0.0 =

* Initial release.
* Added secret token protection for targeted `wp-json` endpoints.
