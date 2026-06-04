<?php
/**
 * PHPUnit bootstrap for Governance Guardrails tests.
 *
 * Prefers the vendored wp-phpunit library and the local `tests/wp-tests-config.php`
 * file so the test suite can run directly in this workspace without manual env setup.
 */

$project_root = dirname( __DIR__ );
$config_path  = $project_root . '/tests/wp-tests-config.php';

// Resolve the WordPress test library path.
$wp_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $wp_tests_dir ) {
	$wp_tests_dir = $project_root . '/vendor/wp-phpunit/wp-phpunit';
}

if ( ! getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) && file_exists( $config_path ) ) {
	putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . $config_path );
}

if ( ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find WordPress test library at {$wp_tests_dir}.\n";
	echo "Set WP_TESTS_DIR to the path of the WordPress test library.\n";
	exit( 1 );
}

// Give access to tests_add_filter().
require_once $wp_tests_dir . '/includes/functions.php';

/**
 * Load the mu-plugin during the test suite bootstrap.
 */
tests_add_filter(
	'muplugins_loaded',
	function () use ( $project_root ) {
		if ( ! defined( 'GOVGUARD_VERSION' ) ) {
			define( 'GOVGUARD_VERSION', '1.0.0-test' );
		}

		if ( ! defined( 'GOVGUARD_DIR' ) ) {
			define( 'GOVGUARD_DIR', $project_root . '/governance-guardrails/' );
		}

		require_once GOVGUARD_DIR . 'class-config.php';
		require_once GOVGUARD_DIR . 'class-governance.php';
	}
);

// Start the WordPress test environment.
require $wp_tests_dir . '/includes/bootstrap.php';
