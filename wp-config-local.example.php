<?php
// Copy this file to wp-config-local.php and replace every placeholder.
// Keep the real wp-config-local.php out of Git; never put production secrets here.

// Required database connection settings (preserve the host's real values).
define( 'DB_NAME', 'database_name' );
define( 'DB_USER', 'database_user' );
define( 'DB_PASSWORD', 'database_password' );
define( 'DB_HOST', 'database_host' );
define( 'DB_CHARSET', 'database_charset' );
define( 'DB_COLLATE', 'database_collation' );

// Required unique authentication keys and salts. Generate unique values per site.
define( 'AUTH_KEY', 'replace_with_unique_local_value' );
define( 'SECURE_AUTH_KEY', 'replace_with_unique_local_value' );
define( 'LOGGED_IN_KEY', 'replace_with_unique_local_value' );
define( 'NONCE_KEY', 'replace_with_unique_local_value' );
define( 'AUTH_SALT', 'replace_with_unique_local_value' );
define( 'SECURE_AUTH_SALT', 'replace_with_unique_local_value' );
define( 'LOGGED_IN_SALT', 'replace_with_unique_local_value' );
define( 'NONCE_SALT', 'replace_with_unique_local_value' );
$table_prefix = 'replace_with_table_prefix_';

// Environment: use local, development, staging, or production as appropriate.
// Never leave the local value on a production host.
define( 'WP_ENVIRONMENT_TYPE', 'local' );

// Scheduled tasks: keep WordPress cron enabled unless a working server cron has
// been configured and verified. Do not disable backups, scheduled posts, or site features.
define( 'DISABLE_WP_CRON', false );

// Local diagnostic defaults. Production must never display PHP errors publicly.
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', false );
define( 'SCRIPT_DEBUG', false );
define( 'SAVEQUERIES', false );
// WP_DEBUG enables notices and warnings; use it only during local diagnosis.
// WP_DEBUG_DISPLAY controls showing errors in page output; keep false in production.
// WP_DEBUG_LOG writes errors to a log (normally wp-content/debug.log); keep it off
// in production unless logs are routed and protected outside the public web root.
// SCRIPT_DEBUG loads development versions of core CSS/JavaScript instead of minified files.
// SAVEQUERIES stores SQL queries and timings in memory; it can slow requests and expose data.
// For temporary local diagnosis, enable only the required options and then disable them.

// Production policy for Git-managed application files (apply only to reviewed
// production configs). AUTOMATIC_UPDATER_DISABLED stops background updates,
// including translations, and can suppress WordPress update notification emails.
// WP_AUTO_UPDATE_CORE=false stops automatic WordPress Core updates.
// DISALLOW_FILE_MODS blocks dashboard installation/update of Core, plugins, and themes.
// DISALLOW_FILE_EDIT disables the built-in dashboard plugin/theme code editor.
// Keep independent security monitoring and deliver fixes promptly through Git.
// define( 'AUTOMATIC_UPDATER_DISABLED', true );
// define( 'WP_AUTO_UPDATE_CORE', false );
// define( 'DISALLOW_FILE_MODS', true );
// define( 'DISALLOW_FILE_EDIT', true );

// Optional content-retention settings; review site needs before enabling.
// define( 'AUTOSAVE_INTERVAL', 120 ); // Seconds between post autosaves.
// define( 'WP_POST_REVISIONS', 10 ); // Maximum revisions per post; false disables revisions.
// define( 'EMPTY_TRASH_DAYS', 30 ); // Days before trash is permanently emptied; 0 disables trash.

// Optional memory limits; never exceed the hosting plan's effective limits.
// define( 'WP_MEMORY_LIMIT', '256M' );
// define( 'WP_MAX_MEMORY_LIMIT', '512M' );

// Optional HTTPS admin enforcement. Verify HTTPS and proxy headers first to avoid redirects.
// define( 'FORCE_SSL_ADMIN', true );

// Optional cache integration; enable only when the installed cache mechanism requires it.
// define( 'WP_CACHE', true );

// Optional URL overrides; only use when necessary and replace the fictional URL.
// define( 'WP_HOME', 'https://example.com' );
// define( 'WP_SITEURL', 'https://example.com' );
