=== DYDAPS API Protection ===
Contributors: dydaps
Tags: rest api, api key, security, headless, protection
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Protect anonymous WordPress REST API access while preserving native WordPress authorization.

== Description ==

DYDAPS API Protection lets you secure selected WordPress REST API routes behind a secret API key.

Features:

* Protect selected REST API routes
* Generate, rotate, and revoke API keys
* Keep selected routes public through exceptions
* Block the full REST API by default when needed
* Optionally allow same-process WordPress rest_do_request() calls (disabled by default)
* Manage exact REST namespace exceptions without bypassing native permissions
* Browse registered endpoints and manage method-specific route exceptions
* Diagnose denials with optional bounded logging, disabled by default
* Use a lightweight admin interface with no external dependencies
* Ship with English and French translations

Anonymous clients must include the `X-DYDAPS-API-Key` header for protected routes.

Valid WordPress authentication is also accepted and endpoint permission callbacks remain authoritative. A DYDAPS API key does not grant WordPress user capabilities. New installations deny anonymous REST requests by default; upgrades preserve the previous saved policy.

Same-process internal requests through WordPress `rest_do_request()` are subject to DYDAPS rules unless explicitly allowed. HTTP loopbacks remain protected because they cannot be safely distinguished from external requests. Namespace exceptions bypass DYDAPS blocking only; authentication handling is unchanged and endpoint permission callbacks still run. WordPress does not perform HTTP authentication for direct `rest_do_request()` calls; callers supply the current-user context.

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

= 1.4.0 =

* Preserved WordPress authentication and native endpoint permission checks.
* Enabled strict anonymous REST protection by default for new installations.
* Added method-specific anonymous REST route exceptions.
* Preserved existing API keys and saved settings during upgrades.
* Added registered endpoint inventory, visual exception management, optional bounded denial logs, and legacy policy warnings.

= 1.2.0 =

* Added a lightweight autoloader and cleaned the internal plugin structure.
* Moved admin CSS and JavaScript into dedicated asset files.
* Tightened route prefix matching and improved translation maintenance.

= 1.3.0 =

* Added conservative same-process internal REST request handling.
* Added registered namespace inventory and exact namespace exceptions.

= 1.1.0 =

* Added configurable protected REST API routes.
* Added API key generation, rotation, and revocation.
* Added public route exceptions and full REST API blocking mode.

= 1.0.0 =

* Initial release.
* Added secret token protection for targeted `wp-json` endpoints.

= 1.4.1 =
* Simplified the protection administration page and clarified REST denial diagnostics.
* Added paginated activity logs with filtering, bounded retention, and manual cleanup.
* Added a read-only configuration analysis and explicit strict-mode confirmation.
