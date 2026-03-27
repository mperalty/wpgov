<?php
/**
 * WP Governance Configuration
 *
 * This file controls all governance rules enforced by the WP Governance mu-plugin.
 * Toggle features by setting values to true/false. Changes take effect immediately
 * on the next page load — no database writes, no cache to clear.
 *
 * To use a custom path for this file, define WP_GOVERNANCE_CONFIG in wp-config.php:
 *     define('WP_GOVERNANCE_CONFIG', '/path/to/governance-config.php');
 *
 * Environment-specific overrides:
 *     Create a file named wp-governance-config.{environment}.php alongside this
 *     file (e.g. wp-governance-config.local.php) and it will be deep-merged on
 *     top of this base config. The environment is read from WP_ENVIRONMENT_TYPE.
 *     Only include the keys you want to override — everything else inherits.
 */

// phpcs:disable Squiz.PHP.CommentedOutCode.Found
return array(

	// ─── Feature Toggles ────────────────────────────────────────────────────────
	// Each toggle enables or disables a specific WordPress feature.
	// true  = restriction is ACTIVE (feature is disabled/locked down)
	// false = restriction is OFF   (WordPress default behavior)

	'features'                 => array(

		// Disable the built-in theme/plugin file editor (Appearance → Theme Editor, Plugins → Plugin Editor).
		'disable_file_editor'           => true,

		// Disable XML-RPC entirely. Prevents pingbacks, trackbacks, and XML-RPC API access.
		'disable_xmlrpc'                => true,

		// Restrict REST API to authenticated users only. Unauthenticated requests receive a 401.
		'restrict_rest_api'             => true,

		// Remove the WordPress version number from <head> and RSS feeds.
		'remove_wp_version'             => true,

		// Prevent WordPress from sending pingbacks to its own URLs.
		'disable_self_ping'             => true,

		// Suppress the periodic "please verify your admin email" prompt.
		'disable_admin_email_check'     => true,

		// Hide the auto-update toggle UI on plugin/theme screens.
		// Does NOT affect auto-updates controlled via constants or WP-CLI.
		'disable_auto_update_ui'        => false,

		// Disable comments site-wide: closes comments, removes the menu item,
		// hides the admin bar icon, and strips comment support from all post types.
		'disable_comments'              => false,

		// Force the Classic Editor for all post types (disable Gutenberg block editor).
		'disable_block_editor'          => false,

		// Disable application passwords (Settings → Users → Application Passwords).
		'disable_application_passwords' => true,

		// Remove all dashboard widgets for governed users. Fine-tune targeted removals via 'remove_dashboard_widgets' below.
		'disable_dashboard_widgets'     => true,

		// Force-disable user registration regardless of the Settings → General toggle.
		'disable_user_registration'     => true,

		// Remove the Customizer (Appearance → Customize) and block direct access.
		'disable_customizer'            => false,

		// Disable legacy widget areas and the Widgets admin page.
		'disable_widgets'               => false,

		// Disable front-end search entirely (returns 404 for search queries).
		'disable_search'                => false,

		// Disable all RSS/Atom feeds (redirects feed URLs to homepage).
		'disable_feeds'                 => false,

		// Prevent ALL file modifications: plugin/theme install, update, and edit.
		// More aggressive than disable_file_editor. Sets DISALLOW_FILE_MODS constant.
		'disable_file_mods'             => false,

		// Disable all WordPress update checks and hide the Updates admin page.
		'disable_updates'               => false,

		// Force SSL on the admin area. Sets FORCE_SSL_ADMIN constant.
		'force_ssl_admin'               => false,

		// Prevent changes to the site tagline (Settings → General → Tagline).
		'disable_tagline_editing'       => false,

		// Lock the permalink structure so it cannot be changed via Settings → Permalinks.
		'lock_permalink_structure'      => false,

		// Disable the built-in WordPress cron system (use a real system cron instead).
		'disable_wp_cron'               => false,
	),

	// ─── Admin Menu Restrictions ────────────────────────────────────────────────
	// Remove admin menu pages by slug for users below the unrestricted role.
	// Also blocks direct URL access to these pages (returns 403).
	//
	// Find a page's slug by hovering over its menu link — it's the .php filename
	// (with optional query string) in the URL.

	'restricted_menu_slugs'    => array(
		// 'tools.php',
		// 'options-general.php',
		// 'edit.php?post_type=acf-field-group',
	),

	// ─── Admin Bar Removal ──────────────────────────────────────────────────────
	// Remove nodes from the top admin bar by their ID.
	// Inspect the admin bar HTML to find node IDs (look for id="wp-admin-bar-{id}").

	'remove_admin_bar_nodes'   => array(
		'wp-logo',
		'comments',
		'new-content',
		'updates',
	),

	// ─── Dashboard Widget Control ───────────────────────────────────────────────
	// Remove specific dashboard widgets by their ID.
	// These are the meta box IDs registered on the dashboard screen.

	'remove_dashboard_widgets' => array(
		'dashboard_quick_press',    // Quick Draft
		'dashboard_primary',        // WordPress Events and News
		'dashboard_site_health',    // Site Health Status
		// 'dashboard_right_now',   // At a Glance
		// 'dashboard_activity',    // Activity
	),

	// ─── Capability Restrictions ────────────────────────────────────────────────
	// Deny specific capabilities for roles at runtime. These are enforced via the
	// user_has_cap filter — no database role definitions are modified.
	//
	// Format: 'role_slug' => ['capability_1', 'capability_2', ...]
	//
	// Users with the 'unrestricted_role' (below) bypass all denials.

	'deny_capabilities'        => array(
		'editor' => array(
			'install_plugins',
			'install_themes',
			'edit_themes',
			'edit_plugins',
			'update_core',
		),
		// 'author' => [
		//     'upload_files',
		// ],
	),

	// ─── Allowed Upload MIME Types ──────────────────────────────────────────────
	// When set, uploads are restricted to ONLY these MIME types.
	// Leave as an empty array [] to use WordPress defaults.
	//
	// Format: 'extension|alt_extension' => 'mime/type'

	'allowed_mime_types'       => array(
		// 'jpg|jpeg|jpe' => 'image/jpeg',
		// 'png'          => 'image/png',
		// 'gif'          => 'image/gif',
		// 'webp'         => 'image/webp',
		// 'pdf'          => 'application/pdf',
		//
		// WARNING: Do NOT allow 'svg' => 'image/svg+xml' without a dedicated
		// SVG sanitization plugin. SVG files can contain embedded JavaScript
		// and are a known XSS attack vector. If you must allow SVGs, use a
		// library like enshrined/svg-sanitize to strip malicious content.
	),

	// Additional upload rules that apply alongside the MIME allowlist above.
	'uploads'                  => array(
		// Limit each uploaded file to this many megabytes.
		// Set to null to use the server/WordPress default limit.
		'max_upload_size_mb' => null,
	),

	// ─── Login & Auth Restrictions ──────────────────────────────────────────────

	'login'                    => array(
		// Disable the "Lost your password?" link and password reset functionality.
		'disable_password_reset' => false,

		// Replace specific login error messages with a generic "Invalid credentials." message.
		'hide_login_errors'      => true,

		// Redirect users to this URL after logging out. Empty string = WordPress default.
		'redirect_after_logout'  => '/',
	),

	// ─── Content Restrictions ───────────────────────────────────────────────────

	'content'                  => array(
		// Disable post revisions entirely.
		'disable_revisions' => false,

		// Limit the number of revisions kept per post. null = WordPress default.
		'revision_limit'    => 5,

		// Disable the autosave feature in the editor.
		'disable_autosave'  => false,

		// Autosave interval in seconds (default WordPress is 60).
		'autosave_interval' => 120,

		// Disable oEmbed (auto-embedding of URLs from YouTube, Twitter, etc.).
		'disable_embeds'    => false,

		// Remove WordPress emoji scripts and styles (the inline SVG/JS in <head>).
		'disable_emojis'    => true,
	),

	// ─── Head Cleanup ───────────────────────────────────────────────────────────
	// Remove items WordPress adds to the <head> section of every page.

	'head_cleanup'             => array(
		// Remove the Really Simple Discovery (RSD) link (used by XML-RPC clients).
		'remove_rsd_link'      => true,

		// Remove the Windows Live Writer manifest link.
		'remove_wlwmanifest'   => true,

		// Remove the shortlink <link> tag.
		'remove_shortlink'     => true,

		// Remove RSS feed links from <head>.
		'remove_feed_links'    => false,

		// Remove the REST API <link> tag from <head> and the Link: header.
		'remove_rest_api_link' => false,
	),

	// ─── Role-Based Bypass ──────────────────────────────────────────────────────
	// Users with this role (or higher) bypass user-scoped governance such as
	// menus, admin bar cleanup, dashboard cleanup, uploads, and capability denials.
	// Global sitewide hardening rules still apply to everyone.
	// Set to 'administrator' so only admins are unrestricted.

	'unrestricted_role'        => 'administrator',

	// ─── Suppressed Admin Notices ───────────────────────────────────────────────
	// Hide specific admin notices by their hook name or identifier.

	'suppress_admin_notices'   => array(
		'update_nag',               // "WordPress X.X is available! Please update."
		// 'try_gutenberg_panel',
	),

	// ─── Admin Footer ─────────────────────────────────────────────────────────
	// Control the text shown in the WordPress admin footer area.

	'admin_footer'             => array(
		// Replace the left footer text (e.g., "Thank you for creating with WordPress").
		// Supports basic HTML. Set to empty string to clear it.
		// 'left_text'     => 'Managed by IT Governance',

		// Replace the right footer text (normally shows the WordPress version).
		// 'right_text'    => 'Internal Use Only',

		// Remove both footer texts entirely (overrides left_text/right_text).
		'remove_footer' => false,
	),

	// ─── Post Type Restrictions ───────────────────────────────────────────────
	// Control access to specific post types for non-unrestricted users.

	'post_types'               => array(
		// Hide these post types from the admin menu and block direct access.
		// Uses the post type slug (e.g., 'post', 'page', 'attachment', or custom types).
		'hidden'           => array(
			// 'post',       // Hide blog Posts
			// 'attachment', // Hide the Media Library
		),

		// Remove specific feature support from post types.
		// Format: 'post_type' => ['feature_1', 'feature_2']
		// Common features: 'editor', 'author', 'thumbnail', 'excerpt',
		//                  'trackbacks', 'custom-fields', 'comments', 'revisions'
		'disable_supports' => array(
			// 'post' => ['trackbacks', 'custom-fields'],
			// 'page' => ['comments'],
		),
	),

	// ─── Security Hardening ───────────────────────────────────────────────────
	// HTTP headers, enumeration protection, and other hardening measures.

	'security'                 => array(
		// Add custom HTTP security headers to front-end, admin, login, AJAX, and REST responses.
		// Format: 'Header-Name' => 'header value'
		'headers'                      => array(
			// 'X-Content-Type-Options'  => 'nosniff',
			// 'X-Frame-Options'         => 'SAMEORIGIN',
			// 'Referrer-Policy'         => 'strict-origin-when-cross-origin',
			// 'Permissions-Policy'      => 'camera=(), microphone=(), geolocation=()',
			// 'X-XSS-Protection'        => '1; mode=block',
		),

		// Disable author archives to prevent user enumeration via /?author=1.
		// Also blocks the /wp/v2/users REST endpoint for unauthenticated requests.
		'disable_author_archives'      => false,

		// Remove version query strings from enqueued scripts and stylesheets only
		// when they match the current WordPress core version. Theme/plugin asset
		// versions are preserved for cache busting.
		'hide_wp_version_from_scripts' => false,

		// Remove the X-Pingback HTTP header from responses.
		'remove_pingback_header'       => true,

		// Disable the theme/plugin file editors (belt-and-suspenders with
		// the 'disable_file_editor' feature toggle above). Useful when you
		// want the security module alone to own this hardening rule.
		'disable_file_editing'         => false,

		// Add X-Robots-Tag: noindex, nofollow to front-end, admin, login, AJAX,
		// and REST responses. Useful on staging/dev environments to prevent
		// search-engine indexing.
		'add_noindex_headers'          => false,
	),

	// ─── Custom Rules ───────────────────────────────────────────────────────────
	// Register your own governance rules. Each callback receives the full
	// config array.
	//
	// Simple format:
	//     'rule_name' => function (array $config) { ... }
	//
	// Structured format:
	//     'rule_name' => array(
	//         'callback'   => function (array $config) { ... },
	//         'hook'       => 'wp_loaded', // default: init
	//         'priority'   => 20,          // default: 10
	//         'admin_only' => false,
	//         'front_only' => false,
	//     )
	//
	// Example:
	//     'lock_permalink_structure' => array(
	//         'callback' => function (array $config) {
	//             add_filter('pre_update_option_permalink_structure', function () {
	//                 return get_option('permalink_structure');
	//             });
	//         },
	//         'hook' => 'admin_init',
	//         'admin_only' => true,
	//     ),

	'custom_rules'             => array(),

	// ─── Locked Options ─────────────────────────────────────────────────────────
	// Pin wp_options values from this file. Each entry overrides the database
	// value at runtime (via pre_option_) and silently blocks writes. The admin
	// form fields are disabled automatically.
	//
	// This generalises the individual feature toggles for tagline and permalink
	// locking: anything that lives in a Settings page can be pinned here.
	//
	// Note: WordPress treats a pre_option_ return of `false` as "not set."
	// Use 0, '', or 'no' instead of false for boolean-like options.

	'locked_options'           => array(
		// 'permalink_structure' => '/%postname%/',
		// 'date_format'         => 'Y-m-d',
		// 'time_format'         => 'H:i',
		// 'timezone_string'     => 'America/New_York',
		// 'posts_per_page'      => 10,
		// 'blog_public'         => 1,
		// 'default_role'        => 'subscriber',
		// 'start_of_week'       => 1,
		// 'blogdescription'     => 'My Tagline',
	),
);
