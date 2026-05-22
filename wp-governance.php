<?php
/**
 * Plugin Name: Governance Guardrails
 * Plugin URI: https://github.com/mperalty/wpgov
 * Description: File-based WordPress governance — restrict features, capabilities, and admin UI via config.
 * Version: 1.0.0
 * Author: Malcolm Peralty
 * Author URI: https://peralty.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: governance-guardrails
 * Domain Path: /languages
 *
 * This plugin can be activated as a normal plugin or installed as a must-use
 * plugin by dropping this file and the wp-governance/ directory into
 * wp-content/mu-plugins/.
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
