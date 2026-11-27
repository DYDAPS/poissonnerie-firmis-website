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
// For temporary local diagnosis only, enable WP_DEBUG and optionally WP_DEBUG_LOG.
// Keep logs outside the public web root; disable diagnostics after investigation.

// Production policy for Git-managed application files (apply only to reviewed
// production configs): these settings disable WordPress-managed file updates,
// including automatic translation updates. Monitor security releases and deploy
// promptly through the DYDAPS Git workflow. Update notifications/checks remain
// available through approved monitoring and maintenance workflows.
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
