# WP Governance

WP Governance is a file-based WordPress mu-plugin for locking down admin features, capabilities, UI, uploads, and other operational rules without writing settings into the database.

## What You Can Control

- **Feature toggles** — disable XML-RPC, the block editor, comments, search, RSS feeds, the Customizer, widgets, application passwords, user registration, WP Cron, and more with a single boolean
- **Admin UI** — remove admin bar nodes, dashboard widgets, menu pages, and admin footer text; lock the permalink structure and tagline
- **Capabilities** — deny specific capabilities per role at runtime without touching the database
- **Uploads** — restrict allowed MIME types and enforce a per-file size cap
- **Content** — control revisions, autosave intervals, oEmbed, and emoji loading
- **Login & auth** — disable password resets, mask login errors, and set a post-logout redirect
- **Security hardening** — inject HTTP security headers, disable author archives, strip version strings, block file editing, and add noindex headers for staging environments
- **Head cleanup** — remove RSD, WLW manifest, shortlinks, feed links, and REST API links from `<head>`
- **Post types** — hide post types from the admin and selectively remove feature support (e.g. trackbacks, custom fields, comments)
- **Locked options** — pin any `wp_options` value (permalink structure, date format, timezone, posts per page, etc.) from the config file so the database value never wins
- **Custom rules** — register your own governance callbacks with hook, priority, and admin/front targeting
- **Role-based bypass** — exempt a role (and above) from all restrictions so administrators keep full access

## Why File-Based Governance

Because the entire policy is a single PHP file, you can:

- **Version-control your policy** — track every change in git with full history and code review
- **Deploy to many sites at once** — push the same config across dozens or hundreds of client sites via your deployment pipeline, CI/CD, or configuration management
- **Enforce consistency** — guarantee that every site in a fleet shares the same security posture, upload rules, and UI restrictions regardless of what any individual admin toggles in the dashboard
- **Avoid database drift** — settings live in code, not in `wp_options` rows that can be changed through the admin, lost during migrations, or diverge between environments
- **Onboard new sites instantly** — drop the mu-plugin and config file in, and the full policy is active on the next page load with nothing to configure through the UI

## Coming from Drupal?

If you've managed Drupal sites, most of WP Governance will feel familiar — it's the same idea as config sync, just shaped for WordPress.

| Drupal concept | WP Governance equivalent |
|---|---|
| `core.extension.yml` / config sync directory | `wp-governance-config.php` — one file that declares the full policy |
| `$config` overrides in `settings.php` | The entire approach — config lives in a PHP file, not the database |
| `drush config:export` | `wp governance export` |
| `drush config:import` | Not needed — deploy the file and the policy is live on the next page load |
| Config Split (per-environment config) | `define('WP_GOVERNANCE_CONFIG', '/path/to/env-config.php')` in `wp-config.php` |
| Permissions page (`admin/people/permissions`) | `deny_capabilities` — same role/capability matrix, defined in config |
| SecKit module | `security.headers` + `security` toggles (pingback, author enumeration, etc.) |
| Security Review / `drush security:check` | `wp governance audit` — opinionated checklist of ungoverned items |
| `system.date` / `system.site` config objects | `locked_options` — pin any `wp_options` value (date format, timezone, site name, etc.) from the config file |

The mental model is the same: define your site's policy in code, commit it, and deploy it across environments. The difference is that WordPress doesn't have a native config management layer, so WP Governance fills that gap for the operational and security settings that matter most. There's no export/import cycle — the PHP file _is_ the active config, read on every request.

## Requirements

- PHP >= 8.1
- WordPress >= 6.4

## Structure

- `wp-governance.php` is the mu-plugin loader.
- `wp-governance/` contains the governance modules and sample config.
- `tests/` contains the PHPUnit suite and bootstrap.

## Installation

Copy `wp-governance.php` and the `wp-governance/` directory into `wp-content/mu-plugins/`.

By default the plugin loads its config from:

```php
wp-content/mu-plugins/wp-governance/wp-governance-config.php
```

Override the path in `wp-config.php` if needed:

```php
define( 'WP_GOVERNANCE_CONFIG', '/absolute/path/to/wp-governance-config.php' );
```

The shipped sample config lives at `wp-governance/wp-governance-config.php`.

## Policy Recipes

Limit uploads to approved image/document types and a 10 MB cap:

```php
'allowed_mime_types' => array(
    'jpg|jpeg|jpe' => 'image/jpeg',
    'png'          => 'image/png',
    'pdf'          => 'application/pdf',
),
'uploads' => array(
    'max_upload_size_mb' => 10,
),
```

Turn off update checks and UI in a locked-down environment:

```php
'features' => array(
    'disable_updates'        => true,
    'disable_auto_update_ui' => true,
),
```

Pin settings so they can't drift across environments:

```php
'locked_options' => array(
    'permalink_structure' => '/%postname%/',
    'date_format'         => 'Y-m-d',
    'timezone_string'     => 'America/New_York',
    'posts_per_page'      => 10,
    'blog_public'         => 1,
),
```

Let editors bypass governance while lower roles stay restricted:

```php
'unrestricted_role' => 'editor',
```

Minimal production-style config:

```php
return array(
    'features' => array(
        'disable_file_editor'           => true,
        'disable_xmlrpc'                => true,
        'disable_application_passwords' => true,
        'remove_wp_version'             => true,
    ),
    'security' => array(
        'remove_pingback_header' => true,
    ),
    'unrestricted_role' => 'administrator',
);
```

## Extending

The plugin exposes a small extension surface for site-specific governance:

- `custom_rules` can be plain callables or structured rules with `callback`, `hook`, `priority`, `admin_only`, and `front_only`.
- `wp_governance_before_enforce` fires before modules are instantiated.
- `wp_governance_loaded` fires after modules are loaded and passes the config plus instantiated modules.
- `wp_governance_deny_caps` lets you adjust denied capabilities per role at runtime.

Example structured custom rule:

```php
'custom_rules' => array(
    'lock_tagline' => array(
        'callback' => function ( array $config ) {
            add_filter( 'pre_update_option_blogdescription', function () {
                return get_option( 'blogdescription' );
            } );
        },
        'hook'       => 'admin_init',
        'priority'   => 20,
        'admin_only' => true,
    ),
),
```

Example capability filter:

```php
add_filter( 'wp_governance_deny_caps', function ( array $caps, string $role ): array {
    if ( $role === 'editor' ) {
        $caps[] = 'edit_theme_options';
    }

    return $caps;
}, 10, 2 );
```

## Commands

Composer scripts:

```bash
composer test
composer analyze
composer lint
composer test:all
```

## WP-CLI

When WP-CLI is available, the plugin registers the `wp governance` command set.

Examples:

```bash
wp governance status
wp governance check
wp governance audit
wp governance audit --severity=high
wp governance diff
wp governance get features --format=json
wp governance mimes
```

`wp governance audit` scans the site against an opinionated checklist and reports everything that isn't locked down — ungoverned features, missing security headers, open upload types, default admin bar nodes, and more. Use `--severity=high` to focus on the most critical findings.

`wp governance diff` compares the active config against the shipped sample defaults, not the fail-open runtime schema.
