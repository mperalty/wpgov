<?php

namespace WP_Governance;

use WP_CLI;
use WP_CLI\Formatter;

defined( 'ABSPATH' ) || exit;

/**
 * Manage WP Governance rules from the command line.
 *
 * ## EXAMPLES
 *
 *     # Show governance status overview
 *     wp governance status
 *
 *     # Validate the config file
 *     wp governance check
 *
 *     # List all feature toggles
 *     wp governance features
 *
 *     # List denied capabilities by role
 *     wp governance caps
 *
 *     # List restricted menu slugs
 *     wp governance menus
 *
 *     # List allowed MIME types
 *     wp governance mimes
 *
 *     # Export the full effective config as JSON
 *     wp governance export
 *
 *     # Show a specific config section
 *     wp governance get features
 *     wp governance get login
 */
class CLI extends \WP_CLI_Command {

	/**
	 * Show governance status overview.
	 *
	 * Displays the config file path, version, last modified time,
	 * module activation status, and a count of active rules.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp governance status
	 *     wp governance status --format=json
	 *
	 * @subcommand status
	 */
	public function status( $args, $assoc_args ) {
		$config = Config::get();
		$path   = Config::path();

		WP_CLI::log( '' );
		WP_CLI::log( WP_CLI::colorize( '%GWP Governance v' . WP_GOVERNANCE_VERSION . '%n' ) );
		WP_CLI::log( '' );

		// Config file info.
		if ( is_readable( $path ) ) {
			WP_CLI::log( WP_CLI::colorize( '%bConfig file:%n  ' . $path ) );
			WP_CLI::log( WP_CLI::colorize( '%bLast modified:%n ' . gmdate( 'Y-m-d H:i:s', filemtime( $path ) ) . ' UTC' ) );
		} else {
			WP_CLI::warning( 'Config file not found: ' . $path );
		}

		WP_CLI::log( WP_CLI::colorize( '%bUnrestricted role:%n ' . ( $config['unrestricted_role'] ?? 'administrator' ) ) );
		WP_CLI::log( '' );

		// Module summary.
		$rows = array();

		// Features.
		$features = $config['features'] ?? array();
		$active   = count( array_filter( $features ) );
		$rows[]   = array(
			'module'       => 'Features',
			'active_rules' => $active . '/' . count( $features ),
			'status'       => $active > 0 ? 'enforcing' : 'off',
		);

		// Menu restrictions.
		$menus  = $config['restricted_menu_slugs'] ?? array();
		$rows[] = array(
			'module'       => 'Admin Menu',
			'active_rules' => (string) count( $menus ),
			'status'       => count( $menus ) > 0 ? 'enforcing' : 'off',
		);

		// Admin bar.
		$bar    = $config['remove_admin_bar_nodes'] ?? array();
		$rows[] = array(
			'module'       => 'Admin Bar',
			'active_rules' => (string) count( $bar ),
			'status'       => count( $bar ) > 0 ? 'enforcing' : 'off',
		);

		// Dashboard.
		$dash   = $config['remove_dashboard_widgets'] ?? array();
		$rows[] = array(
			'module'       => 'Dashboard',
			'active_rules' => (string) count( $dash ),
			'status'       => count( $dash ) > 0 ? 'enforcing' : 'off',
		);

		// Capabilities.
		$caps      = $config['deny_capabilities'] ?? array();
		$cap_count = 0;
		foreach ( $caps as $role_caps ) {
			$cap_count += count( $role_caps );
		}
		$rows[] = array(
			'module'       => 'Capabilities',
			'active_rules' => $cap_count . ' caps across ' . count( $caps ) . ' roles',
			'status'       => $cap_count > 0 ? 'enforcing' : 'off',
		);

		// Uploads.
		$mimes        = $config['allowed_mime_types'] ?? array();
		$upload_rules = $config['uploads'] ?? array();
		$upload_count = count( $mimes ) + count( array_filter( $upload_rules ) );
		$rows[]       = array(
			'module'       => 'Uploads',
			'active_rules' => (string) $upload_count,
			'status'       => $upload_count > 0 ? 'enforcing' : 'off',
		);

		// Login.
		$login       = $config['login'] ?? array();
		$login_count = count( array_filter( $login ) );
		$rows[]      = array(
			'module'       => 'Login',
			'active_rules' => (string) $login_count,
			'status'       => $login_count > 0 ? 'enforcing' : 'off',
		);

		// Content.
		$content       = $config['content'] ?? array();
		$content_count = count( array_filter( $content ) );
		$rows[]        = array(
			'module'       => 'Content',
			'active_rules' => (string) $content_count,
			'status'       => $content_count > 0 ? 'enforcing' : 'off',
		);

		// Head cleanup.
		$head       = $config['head_cleanup'] ?? array();
		$head_count = count( array_filter( $head ) );
		$rows[]     = array(
			'module'       => 'Head Cleanup',
			'active_rules' => (string) $head_count,
			'status'       => $head_count > 0 ? 'enforcing' : 'off',
		);

		// Notices.
		$notices = $config['suppress_admin_notices'] ?? array();
		$rows[]  = array(
			'module'       => 'Notices',
			'active_rules' => (string) count( $notices ),
			'status'       => count( $notices ) > 0 ? 'enforcing' : 'off',
		);

		// Admin footer.
		$footer       = $config['admin_footer'] ?? array();
		$footer_count = count( array_filter( $footer ) );
		$rows[]       = array(
			'module'       => 'Admin Footer',
			'active_rules' => (string) $footer_count,
			'status'       => $footer_count > 0 ? 'enforcing' : 'off',
		);

		// Post types.
		$pt       = $config['post_types'] ?? array();
		$pt_count = count( $pt['hidden'] ?? array() ) + count( $pt['disable_supports'] ?? array() );
		$rows[]   = array(
			'module'       => 'Post Types',
			'active_rules' => (string) $pt_count,
			'status'       => $pt_count > 0 ? 'enforcing' : 'off',
		);

		// Security.
		$sec       = $config['security'] ?? array();
		$sec_count = count( array_filter( array_diff_key( $sec, array( 'headers' => '' ) ) ) ) + count( $sec['headers'] ?? array() );
		$rows[]    = array(
			'module'       => 'Security',
			'active_rules' => (string) $sec_count,
			'status'       => $sec_count > 0 ? 'enforcing' : 'off',
		);

		// Custom rules.
		$custom = $config['custom_rules'] ?? array();
		$rows[] = array(
			'module'       => 'Custom Rules',
			'active_rules' => (string) count( $custom ),
			'status'       => count( $custom ) > 0 ? 'enforcing' : 'off',
		);

		$format    = $assoc_args['format'] ?? 'table';
		$formatter = new Formatter( $assoc_args, array( 'module', 'active_rules', 'status' ) );
		$formatter->display_items( $rows );
	}

	/**
	 * Validate the governance config file.
	 *
	 * Checks that the config file exists, is readable, returns a PHP array,
	 * and contains only known top-level keys.
	 *
	 * ## EXAMPLES
	 *
	 *     wp governance check
	 *
	 * @subcommand check
	 */
	public function check( $args, $assoc_args ) {
		$path = Config::path();

		WP_CLI::log( 'Checking config: ' . $path );
		WP_CLI::log( '' );

		// Existence.
		if ( ! file_exists( $path ) ) {
			WP_CLI::error( 'Config file not found.' );
		}

		// Readable.
		if ( ! is_readable( $path ) ) {
			WP_CLI::error( 'Config file exists but is not readable. Check file permissions.' );
		}

		// Returns array.
		$config = include $path;
		if ( ! is_array( $config ) ) {
			WP_CLI::error( 'Config file does not return a PHP array.' );
		}

		WP_CLI::success( 'File exists and returns a valid array.' );

		$errors = Config::validation_errors( $config );

		if ( empty( array_filter( $errors, static fn( string $message ): bool => str_starts_with( $message, 'Unknown config keys:' ) ) ) ) {
			WP_CLI::success( 'All config keys are recognized.' );
		}

		foreach ( $errors as $message ) {
			WP_CLI::warning( $message );
		}

		if ( 0 === count( $errors ) ) {
			WP_CLI::success( 'All type checks passed.' );
		} else {
			WP_CLI::warning( count( $errors ) . ' type issue(s) found.' );
		}

		WP_CLI::log( '' );
		WP_CLI::success( 'Config validation complete.' );
	}

	/**
	 * List all feature toggles and their state.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Filter by status.
	 * ---
	 * options:
	 *   - enforced
	 *   - off
	 *   - all
	 * default: all
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp governance features
	 *     wp governance features --status=enforced
	 *     wp governance features --format=json
	 *
	 * @subcommand features
	 */
	public function features( $args, $assoc_args ) {
		$features = Config::section( 'features' );

		if ( empty( $features ) ) {
			WP_CLI::log( 'No features configured.' );
			return;
		}

		$filter = $assoc_args['status'] ?? 'all';
		$rows   = array();

		foreach ( $features as $key => $enabled ) {
			$status = $enabled ? 'enforced' : 'off';

			if ( 'all' !== $filter && $status !== $filter ) {
				continue;
			}

			$rows[] = array(
				'feature' => $key,
				'status'  => $status,
			);
		}

		if ( empty( $rows ) ) {
			WP_CLI::log( "No features match filter: {$filter}" );
			return;
		}

		$formatter = new Formatter( $assoc_args, array( 'feature', 'status' ) );
		$formatter->display_items( $rows );
	}

	/**
	 * List denied capabilities by role.
	 *
	 * ## OPTIONS
	 *
	 * [--role=<role>]
	 * : Show caps for a specific role only.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp governance caps
	 *     wp governance caps --role=editor
	 *
	 * @subcommand caps
	 */
	public function caps( $args, $assoc_args ) {
		$deny = Config::section( 'deny_capabilities' );

		if ( empty( $deny ) ) {
			WP_CLI::log( 'No capability denials configured.' );
			return;
		}

		$filter_role = $assoc_args['role'] ?? null;
		$rows        = array();

		foreach ( $deny as $role => $caps ) {
			if ( $filter_role && $role !== $filter_role ) {
				continue;
			}
			foreach ( $caps as $cap ) {
				$rows[] = array(
					'role'       => $role,
					'capability' => $cap,
					'status'     => 'denied',
				);
			}
		}

		if ( empty( $rows ) ) {
			WP_CLI::log( 'No matching capability denials found.' );
			return;
		}

		$formatter = new Formatter( $assoc_args, array( 'role', 'capability', 'status' ) );
		$formatter->display_items( $rows );
	}

	/**
	 * List restricted admin menu slugs.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp governance menus
	 *
	 * @subcommand menus
	 */
	public function menus( $args, $assoc_args ) {
		$slugs = Config::section( 'restricted_menu_slugs' );

		if ( empty( $slugs ) ) {
			WP_CLI::log( 'No menu restrictions configured.' );
			return;
		}

		$rows = array();
		foreach ( $slugs as $slug ) {
			$rows[] = array( 'slug' => $slug );
		}

		$formatter = new Formatter( $assoc_args, array( 'slug' ) );
		$formatter->display_items( $rows );
	}

	/**
	 * List allowed MIME types for uploads.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp governance mimes
	 *
	 * @subcommand mimes
	 */
	public function mimes( $args, $assoc_args ) {
		$mimes = Config::section( 'allowed_mime_types' );

		if ( empty( $mimes ) ) {
			WP_CLI::log( 'No MIME type restrictions configured (using WordPress defaults).' );
			return;
		}

		$rows = array();
		foreach ( $mimes as $ext => $mime ) {
			$rows[] = array(
				'extension' => $ext,
				'mime_type' => $mime,
			);
		}

		$formatter = new Formatter( $assoc_args, array( 'extension', 'mime_type' ) );
		$formatter->display_items( $rows );
	}

	/**
	 * Get a specific config section.
	 *
	 * ## OPTIONS
	 *
	 * <section>
	 * : Config section key (e.g., features, login, content, head_cleanup).
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: json
	 * options:
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp governance get features
	 *     wp governance get login
	 *     wp governance get content --format=yaml
	 *
	 * @subcommand get
	 */
	public function get( $args, $assoc_args ) {
		$section_key = $args[0];
		$config      = Config::get();

		if ( ! array_key_exists( $section_key, $config ) ) {
			WP_CLI::error( "Unknown config section: {$section_key}" );
		}

		$format = $assoc_args['format'] ?? 'json';
		$data   = $config[ $section_key ];

		WP_CLI::print_value( $data, array( 'format' => $format ) );
	}

	/**
	 * Export the full effective config as JSON.
	 *
	 * Outputs the entire merged and filtered config. Useful for
	 * debugging or comparing environments.
	 *
	 * ## OPTIONS
	 *
	 * [--pretty]
	 * : Pretty-print the JSON output.
	 *
	 * ## EXAMPLES
	 *
	 *     wp governance export
	 *     wp governance export --pretty
	 *     wp governance export > governance-backup.json
	 *
	 * @subcommand export
	 */
	public function export( $args, $assoc_args ) {
		$config = Config::get();
		$flags  = JSON_UNESCAPED_SLASHES;

		if ( isset( $assoc_args['pretty'] ) ) {
			$flags |= JSON_PRETTY_PRINT;
		}

		WP_CLI::log( wp_json_encode( $config, $flags ) );
	}

	/**
	 * Show a diff between the current config and defaults.
	 *
	 * Highlights which settings deviate from the plugin's default values.
	 *
	 * ## EXAMPLES
	 *
	 *     wp governance diff
	 *
	 * @subcommand diff
	 */
	public function diff( $args, $assoc_args ) {
		$config   = Config::get();
		$defaults = Config::sample_defaults();

		$rows = array();

		foreach ( $config as $key => $value ) {
			$default     = $defaults[ $key ] ?? null;
			$is_modified = $value !== $default;

			if ( ! $is_modified ) {
				continue;
			}

			$display = is_array( $value )
				? count( $value ) . ' items configured'
				: (string) $value;

			$rows[] = array(
				'section' => $key,
				'status'  => 'modified',
				'value'   => $display,
			);
		}

		if ( empty( $rows ) ) {
			WP_CLI::success( 'Config matches the shipped sample defaults; no overrides detected.' );
			return;
		}

		$formatter = new Formatter( $assoc_args, array( 'section', 'status', 'value' ) );
		$formatter->display_items( $rows );
	}
}
