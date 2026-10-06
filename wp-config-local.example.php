<?php
// Copy to wp-config-local.php and replace every placeholder with local values.
// Generate unique replacement values for all authentication keys and salts.
define( 'DB_NAME', 'database_name' );
define( 'DB_USER', 'database_user' );
define( 'DB_PASSWORD', 'database_password' );
define( 'DB_HOST', 'database_host' );
define( 'DB_CHARSET', 'database_charset' );
define( 'DB_COLLATE', 'database_collation' );
define( 'AUTH_KEY', 'replace_with_unique_local_value' );
define( 'SECURE_AUTH_KEY', 'replace_with_unique_local_value' );
define( 'LOGGED_IN_KEY', 'replace_with_unique_local_value' );
define( 'NONCE_KEY', 'replace_with_unique_local_value' );
define( 'AUTH_SALT', 'replace_with_unique_local_value' );
define( 'SECURE_AUTH_SALT', 'replace_with_unique_local_value' );
define( 'LOGGED_IN_SALT', 'replace_with_unique_local_value' );
define( 'NONCE_SALT', 'replace_with_unique_local_value' );
define( 'WP_DEBUG', false ); // Placeholder for the target environment.
$table_prefix = 'replace_with_table_prefix_';
