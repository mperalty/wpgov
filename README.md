# WP Governance

WP Governance is a file-based WordPress mu-plugin for locking down admin features, capabilities, UI, uploads, and other operational rules without writing settings into the database.

## Structure

- `wp-governance.php` is the mu-plugin loader.
- `wp-governance/` contains the governance modules and sample config.
- `wp/` is a local WordPress install for manual testing in this workspace.
- `tests/` contains the PHPUnit suite and the local WP test bootstrap.

## Install As A Mu-Plugin

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

## Local Workspace Setup

This repo includes a local WordPress copy under `wp/` and a PHPUnit config under `tests/wp-tests-config.php`.

- The local site config is `wp/wp-config.php`.
- The workspace mu-plugin loader is `wp/wp-content/mu-plugins/wp-governance.php`.
- PHPUnit uses the separate `wpgov_tests` database via `tests/bootstrap.php`.

The local site is configured for development use in this workspace. PHPUnit does not rely on a browser-installed WordPress site.

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

Direct Windows examples with the local Laragon PHP binary:

```powershell
& 'C:\laragon\bin\php\php-8.3.28-Win32-vs16-x64\php.exe' vendor\bin\phpunit
& 'C:\laragon\bin\php\php-8.3.28-Win32-vs16-x64\php.exe' vendor\bin\phpstan analyse --no-progress --memory-limit=1G
& 'C:\laragon\bin\php\php-8.3.28-Win32-vs16-x64\php.exe' bin\lint.php
```

## WP-CLI

When WP-CLI is available, the plugin registers the `wp governance` command set.

Examples:

```bash
wp governance status
wp governance check
wp governance diff
wp governance get features --format=json
wp governance mimes
```

`wp governance diff` compares the active config against the shipped sample defaults, not the fail-open runtime schema.
