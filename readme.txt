=== Governance Guardrails ===
Contributors: phoenixfireball
Tags: governance, security, admin, mu-plugin, wp-cli
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Governance Guardrails provides file-based rules for managing admin behavior, capabilities, uploads, and operational hardening from code.

== Description ==

Governance Guardrails is a file-based WordPress governance plugin for site owners, agencies, and managed WordPress teams that want important operational rules to live in code instead of scattered database settings.

You define a policy in a PHP configuration file. Governance Guardrails reads that file on each request and applies the configured rules. This makes the policy easy to review, version-control, and deploy consistently across environments or multiple sites.

Governance Guardrails can help you manage:

* Feature toggles such as XML-RPC, comments, feeds, the Customizer, widgets, application passwords, user registration, WP-Cron, and related admin features.
* Admin UI cleanup, including admin bar nodes, dashboard widgets, menu pages, and admin footer text.
* Runtime capability denials by role without changing stored role definitions in the database.
* Upload governance, including allowed MIME types and per-file size limits.
* Content behavior such as revisions, autosave intervals, oEmbed, and emoji loading.
* Login behavior such as password reset restrictions, generic login errors, and post-logout redirects.
* HTTP security headers and other hardening options such as pingback removal, author archive handling, file editing restrictions, and staging noindex headers.
* Head cleanup for RSD, WLW manifest, shortlinks, feed links, and REST API discovery links.
* Locked options so selected `wp_options` values are pinned from code.
* Custom governance callbacks for site-specific rules.

This plugin does not claim to secure a site by itself. It is intended as a governance and consistency tool that helps keep selected WordPress settings and behaviors aligned with your site's operational policy.

= Must-use plugin support =

Governance Guardrails was originally built for must-use plugin deployment. It can still be installed that way by copying `wp-governance.php` and the `wp-governance/` directory into `wp-content/mu-plugins/`.

For WordPress.org installation, it can also be installed and activated as a normal plugin. In that case, the included sample config is used from the plugin directory unless you define a custom config path.

To use a custom config file, add this to `wp-config.php`:

`define( 'WP_GOVERNANCE_CONFIG', '/absolute/path/to/wp-governance-config.php' );`

The shipped sample config lives at `wp-governance/wp-governance-config.php`.

Config loading is fail-open. If the config file is missing, unreadable, has a syntax error, or does not return an array, Governance Guardrails does not enforce governance rules and logs a warning instead of crashing the site.

= WP-CLI =

When WP-CLI is available, Governance Guardrails registers the `wp governance` command set.

Examples:

* `wp governance status`
* `wp governance check`
* `wp governance audit`
* `wp governance audit --severity=high`
* `wp governance diff`
* `wp governance get features --format=json`
* `wp governance mimes`

== Installation ==

= Normal plugin installation =

1. Upload the plugin files to the `/wp-content/plugins/governance-guardrails/` directory, or install the plugin through the WordPress Plugins screen.
2. Activate Governance Guardrails through the Plugins screen in WordPress.
3. Review the included sample config at `wp-governance/wp-governance-config.php`.
4. For a site-specific policy, define `WP_GOVERNANCE_CONFIG` in `wp-config.php` and point it at your own config file.
5. If WP-CLI is available, run `wp governance check` to validate the active config.

= Must-use plugin installation =

1. Copy `wp-governance.php` and the `wp-governance/` directory into `wp-content/mu-plugins/`.
2. Review or replace the config file at `wp-content/mu-plugins/wp-governance/wp-governance-config.php`.
3. Optionally define `WP_GOVERNANCE_CONFIG` in `wp-config.php` to point at a config file outside the plugin directory.
4. If WP-CLI is available, run `wp governance status` or `wp governance check`.

== Frequently Asked Questions ==

= Is Governance Guardrails a security plugin? =

Governance Guardrails includes security-related controls, but it is better described as a governance and configuration enforcement plugin. It helps enforce selected operational rules from code. It should be used alongside normal WordPress security practices such as updates, strong authentication, least-privilege users, backups, logging, and server hardening.

= Can I use it as a normal plugin? =

Yes. Governance Guardrails can be activated as a normal plugin. It was originally designed for must-use deployment, so teams that want policy enforced outside the normal plugin activation flow may still prefer the mu-plugin installation method.

= Where does the configuration live? =

By default, the plugin loads `wp-governance/wp-governance-config.php` from the plugin directory. You can define `WP_GOVERNANCE_CONFIG` in `wp-config.php` to use an absolute path to another config file.

= What happens if the config file is broken? =

Governance Guardrails fails open. It logs a warning and does not enforce governance rules from a broken or missing config file. This avoids taking down the site because of a bad governance config.

= Does Governance Guardrails write settings to the database? =

The core governance model is file-based. It reads policy from a PHP config file and applies rules at runtime. Some rules prevent changes to selected options by filtering reads and updates, but the plugin is not designed around storing settings in the database.

= Does it make remote requests or send tracking data? =

No. Governance Guardrails does not include phone-home tracking or external service calls.

= Who should use this plugin? =

It is most useful for developers, agencies, and managed WordPress teams that want repeatable policy controls across one or more sites. It may be more technical than a typical settings-screen plugin because the policy is configured in PHP.

== Changelog ==

= 1.0.0 =
* Initial WordPress.org-ready release.
* Provides file-based governance configuration for admin UI, feature toggles, capabilities, uploads, content behavior, login behavior, security headers, locked options, and WP-CLI inspection commands.
