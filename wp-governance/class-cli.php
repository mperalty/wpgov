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
 *
 *     # Audit the site for ungoverned items
 *     wp governance audit
 *     wp governance audit --severity=high
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

	/**
	 * Audit the site for ungoverned items.
	 *
	 * Scans the governance config against an opinionated security and
	 * operational checklist. Reports features, settings, and defaults
	 * that are not currently locked down.
	 *
	 * ## OPTIONS
	 *
	 * [--severity=<severity>]
	 * : Filter findings by severity.
	 * ---
	 * default: all
	 * options:
	 *   - all
	 *   - high
	 *   - medium
	 *   - low
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
	 *     wp governance audit
	 *     wp governance audit --severity=high
	 *     wp governance audit --format=json
	 *
	 * @subcommand audit
	 */
	public function audit( $args, $assoc_args ) {
		$config   = Config::get();
		$findings = array();

		$this->audit_feature_toggles( $config, $findings );
		$this->audit_login_settings( $config, $findings );
		$this->audit_security_settings( $config, $findings );
		$this->audit_head_cleanup_settings( $config, $findings );
		$this->audit_content_settings( $config, $findings );
		$this->audit_upload_settings( $config, $findings );
		$this->audit_admin_bar_nodes( $config, $findings );
		$this->audit_dashboard_widgets( $config, $findings );
		$this->audit_admin_footer_settings( $config, $findings );

		// Filter by severity.
		$severity = $assoc_args['severity'] ?? 'all';
		if ( 'all' !== $severity ) {
			$findings = array_values(
				array_filter( $findings, static fn( array $f ): bool => $f['severity'] === $severity )
			);
		}

		$count  = count( $findings );
		$format = $assoc_args['format'] ?? 'table';

		if ( 0 === $count ) {
			if ( 'all' === $severity ) {
				WP_CLI::success( 'Nothing ungoverned — every checklist item is locked down.' );
			} else {
				WP_CLI::success( "No {$severity}-severity findings." );
			}
			return;
		}

		if ( 'table' === $format ) {
			$high = count( array_filter( $findings, static fn( array $f ): bool => 'high' === $f['severity'] ) );
			$med  = count( array_filter( $findings, static fn( array $f ): bool => 'medium' === $f['severity'] ) );
			$low  = count( array_filter( $findings, static fn( array $f ): bool => 'low' === $f['severity'] ) );

			WP_CLI::log( '' );
			WP_CLI::log( WP_CLI::colorize( "%Y{$count} ungoverned items found%n" ) );
			WP_CLI::log( WP_CLI::colorize( "  %R{$high} high%n  /  %Y{$med} medium%n  /  %C{$low} low%n" ) );
			WP_CLI::log( '' );
		}

		$formatter = new Formatter( $assoc_args, array( 'severity', 'category', 'finding', 'setting' ) );
		$formatter->display_items( $findings );

		if ( 'table' === $format ) {
			WP_CLI::log( '' );
			WP_CLI::log( 'Set the listed settings in your governance config to resolve these findings.' );
		}
	}

	// ── Audit helpers ────────────────────────────────────────────

	/**
	 * Check feature toggles that are not enabled.
	 */
	private function audit_feature_toggles( array $config, array &$findings ): void {
		$features = $config['features'] ?? array();

		$checks = array(
			// High — security surface.
			array( 'disable_xmlrpc', 'high', 'XML-RPC is enabled — exposes brute-force and DDoS amplification surface' ),
			array( 'disable_file_editor', 'high', 'Theme/plugin file editor is accessible — allows code execution from the admin' ),
			array( 'disable_application_passwords', 'high', 'Application passwords are enabled — adds API authentication surface' ),
			array( 'restrict_rest_api', 'high', 'REST API is open to unauthenticated requests' ),
			array( 'remove_wp_version', 'high', 'WordPress version is exposed in page source' ),
			array( 'disable_user_registration', 'high', 'User registration is not locked — can be toggled in the dashboard' ),

			// Medium — operational hardening.
			array( 'disable_file_mods', 'medium', 'File modifications (plugin/theme install and update) are allowed from the admin' ),
			array( 'disable_self_ping', 'medium', 'Self-pinging is enabled — WordPress pings its own URLs on publish' ),
			array( 'disable_admin_email_check', 'medium', 'Admin email verification prompt is active — can disrupt admin workflows' ),
			array( 'disable_tagline_editing', 'medium', 'Site tagline can be changed from the dashboard' ),
			array( 'lock_permalink_structure', 'medium', 'Permalink structure can be changed from Settings' ),

			// Low — cleanup and preferences.
			array( 'disable_auto_update_ui', 'low', 'Auto-update toggles are visible on plugin/theme screens' ),
			array( 'disable_comments', 'low', 'Comments are enabled site-wide' ),
			array( 'disable_feeds', 'low', 'RSS/Atom feeds are publicly accessible' ),
			array( 'disable_customizer', 'low', 'Customizer is accessible under Appearance' ),
		);

		foreach ( $checks as list( $key, $severity, $description ) ) {
			if ( empty( $features[ $key ] ) ) {
				$findings[] = array(
					'severity' => $severity,
					'category' => 'Features',
					'finding'  => $description,
					'setting'  => "features.{$key}",
				);
			}
		}
	}

	/**
	 * Check login and authentication settings.
	 */
	private function audit_login_settings( array $config, array &$findings ): void {
		$login = $config['login'] ?? array();

		if ( empty( $login['hide_login_errors'] ) ) {
			$findings[] = array(
				'severity' => 'high',
				'category' => 'Login',
				'finding'  => 'Login errors reveal whether a username exists — aids brute-force attacks',
				'setting'  => 'login.hide_login_errors',
			);
		}

		if ( empty( $login['disable_password_reset'] ) ) {
			$findings[] = array(
				'severity' => 'medium',
				'category' => 'Login',
				'finding'  => 'Password reset form is publicly accessible',
				'setting'  => 'login.disable_password_reset',
			);
		}

		if ( empty( $login['redirect_after_logout'] ) ) {
			$findings[] = array(
				'severity' => 'low',
				'category' => 'Login',
				'finding'  => 'No post-logout redirect — users see the default login screen after logging out',
				'setting'  => 'login.redirect_after_logout',
			);
		}
	}

	/**
	 * Check security hardening settings.
	 */
	private function audit_security_settings( array $config, array &$findings ): void {
		$security = $config['security'] ?? array();

		if ( empty( $security['disable_author_archives'] ) ) {
			$findings[] = array(
				'severity' => 'high',
				'category' => 'Security',
				'finding'  => 'Author archives are active — usernames can be enumerated via /?author=N',
				'setting'  => 'security.disable_author_archives',
			);
		}

		if ( empty( $security['remove_pingback_header'] ) ) {
			$findings[] = array(
				'severity' => 'high',
				'category' => 'Security',
				'finding'  => 'X-Pingback header is present in HTTP responses',
				'setting'  => 'security.remove_pingback_header',
			);
		}

		if ( empty( $security['hide_wp_version_from_scripts'] ) ) {
			$findings[] = array(
				'severity' => 'medium',
				'category' => 'Security',
				'finding'  => 'Version query strings are visible on enqueued scripts and stylesheets (?ver=X.X)',
				'setting'  => 'security.hide_wp_version_from_scripts',
			);
		}

		if ( empty( $security['headers'] ) ) {
			$findings[] = array(
				'severity' => 'medium',
				'category' => 'Security',
				'finding'  => 'No HTTP security headers configured (X-Content-Type-Options, X-Frame-Options, etc.)',
				'setting'  => 'security.headers',
			);
		}
	}

	/**
	 * Check head cleanup settings.
	 */
	private function audit_head_cleanup_settings( array $config, array &$findings ): void {
		$head = $config['head_cleanup'] ?? array();

		$checks = array(
			array( 'remove_rsd_link', 'medium', 'RSD (Really Simple Discovery) link is present in <head>' ),
			array( 'remove_wlwmanifest', 'medium', 'Windows Live Writer manifest link is present in <head>' ),
			array( 'remove_shortlink', 'low', 'Shortlink tag is present in <head>' ),
			array( 'remove_feed_links', 'low', 'Feed discovery links are present in <head>' ),
			array( 'remove_rest_api_link', 'low', 'REST API discovery link is present in <head>' ),
		);

		foreach ( $checks as list( $key, $severity, $description ) ) {
			if ( empty( $head[ $key ] ) ) {
				$findings[] = array(
					'severity' => $severity,
					'category' => 'Head Cleanup',
					'finding'  => $description,
					'setting'  => "head_cleanup.{$key}",
				);
			}
		}
	}

	/**
	 * Check content restriction settings.
	 */
	private function audit_content_settings( array $config, array &$findings ): void {
		$content = $config['content'] ?? array();

		if ( empty( $content['disable_emojis'] ) ) {
			$findings[] = array(
				'severity' => 'low',
				'category' => 'Content',
				'finding'  => 'WordPress emoji scripts and styles are loaded on every page',
				'setting'  => 'content.disable_emojis',
			);
		}

		if ( empty( $content['disable_embeds'] ) ) {
			$findings[] = array(
				'severity' => 'low',
				'category' => 'Content',
				'finding'  => 'oEmbed is active — URLs from external services auto-embed content',
				'setting'  => 'content.disable_embeds',
			);
		}

		if ( ! isset( $content['revision_limit'] ) || null === $content['revision_limit'] ) {
			$findings[] = array(
				'severity' => 'low',
				'category' => 'Content',
				'finding'  => 'Post revisions have no limit — database will grow unbounded',
				'setting'  => 'content.revision_limit',
			);
		}
	}

	/**
	 * Check upload restriction settings.
	 */
	private function audit_upload_settings( array $config, array &$findings ): void {
		$mimes   = $config['allowed_mime_types'] ?? array();
		$uploads = $config['uploads'] ?? array();

		if ( empty( $mimes ) ) {
			$default_count = function_exists( 'wp_get_mime_types' ) ? count( wp_get_mime_types() ) : '80+';
			$findings[]    = array(
				'severity' => 'medium',
				'category' => 'Uploads',
				'finding'  => "No upload type restrictions — WordPress allows {$default_count} file types by default",
				'setting'  => 'allowed_mime_types',
			);
		}

		if ( empty( $uploads['max_upload_size_mb'] ) ) {
			$findings[] = array(
				'severity' => 'medium',
				'category' => 'Uploads',
				'finding'  => 'No per-file upload size cap — limited only by PHP/server defaults',
				'setting'  => 'uploads.max_upload_size_mb',
			);
		}
	}

	/**
	 * Check for common admin bar nodes that are not removed.
	 */
	private function audit_admin_bar_nodes( array $config, array &$findings ): void {
		$removed = $config['remove_admin_bar_nodes'] ?? array();

		$recommended = array(
			'wp-logo'     => 'WordPress logo menu links to wordpress.org — leaks platform choice',
			'comments'    => 'Comments quick-access link in the admin bar',
			'new-content' => '"+ New" content creation shortcut in the admin bar',
			'updates'     => 'Update notifications badge in the admin bar',
		);

		foreach ( $recommended as $node => $description ) {
			if ( ! in_array( $node, $removed, true ) ) {
				$findings[] = array(
					'severity' => 'low',
					'category' => 'Admin Bar',
					'finding'  => $description,
					'setting'  => 'remove_admin_bar_nodes',
				);
			}
		}
	}

	/**
	 * Check for common dashboard widgets that are not removed.
	 */
	private function audit_dashboard_widgets( array $config, array &$findings ): void {
		$features = $config['features'] ?? array();

		// All dashboard widgets disabled — nothing to check.
		if ( ! empty( $features['disable_dashboard_widgets'] ) ) {
			return;
		}

		$removed = $config['remove_dashboard_widgets'] ?? array();

		$recommended = array(
			'dashboard_quick_press'  => 'Quick Draft widget — lets users publish posts from the dashboard',
			'dashboard_primary'      => 'WordPress Events and News widget — pulls content from wordpress.org',
			'dashboard_site_health'  => 'Site Health Status widget is visible on the dashboard',
		);

		foreach ( $recommended as $widget => $description ) {
			if ( ! in_array( $widget, $removed, true ) ) {
				$findings[] = array(
					'severity' => 'low',
					'category' => 'Dashboard',
					'finding'  => $description,
					'setting'  => 'remove_dashboard_widgets',
				);
			}
		}
	}

	/**
	 * Check admin footer customization.
	 */
	private function audit_admin_footer_settings( array $config, array &$findings ): void {
		$footer = $config['admin_footer'] ?? array();

		if ( empty( $footer['remove_footer'] ) && empty( $footer['left_text'] ) && empty( $footer['right_text'] ) ) {
			$findings[] = array(
				'severity' => 'low',
				'category' => 'Admin Footer',
				'finding'  => 'Default WordPress admin footer is shown — consider custom branding for managed sites',
				'setting'  => 'admin_footer',
			);
		}
	}
}
