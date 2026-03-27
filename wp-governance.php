<?php
/**
 * Plugin Name: WP Governance
 * Description: File-based WordPress governance — restrict features, capabilities, and admin UI via config.
 * Version: 1.0.0
 * Author: WP Governance
 * License: GPL-2.0-or-later
 *
 * This is a must-use plugin. Drop it (along with the wp-governance/ directory)
 * into wp-content/mu-plugins/.
 */

defined( 'ABSPATH' ) || exit;

define( 'WP_GOVERNANCE_VERSION', '1.0.0' );
define( 'WP_GOVERNANCE_DIR', __DIR__ . '/wp-governance/' );

// Load core classes.
require_once WP_GOVERNANCE_DIR . 'class-config.php';
require_once WP_GOVERNANCE_DIR . 'class-governance.php';

// Boot.
WP_Governance\Governance::instance();

// Register WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once WP_GOVERNANCE_DIR . 'class-cli.php';
	WP_CLI::add_command( 'governance', WP_Governance\CLI::class );
}
