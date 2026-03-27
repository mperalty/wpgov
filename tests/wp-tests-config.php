<?php
/**
 * Local WordPress test configuration for WP Governance.
 */

define( 'DB_NAME', 'wpgov_tests' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', 'taskfade' );
define( 'DB_HOST', '127.0.0.1' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_';

define( 'ABSPATH', dirname( __DIR__ ) . '/wp/' );
define( 'WPMU_PLUGIN_DIR', dirname( __DIR__ ) . '/wp/wp-content/test-mu-plugins' );
define( 'WPMU_PLUGIN_URL', 'http://localhost/wp-content/test-mu-plugins' );
define( 'WP_TESTS_DOMAIN', 'localhost' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'WP Governance Tests' );
define( 'WP_PHP_BINARY', 'C:/laragon/bin/php/php-8.3.28-Win32-vs16-x64/php.exe' );

define( 'WP_DEBUG', false );
define( 'FS_METHOD', 'direct' );
