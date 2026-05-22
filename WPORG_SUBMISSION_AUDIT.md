# WordPress.org Submission Audit - Governance Guardrails

Audit date: 2026-05-22
Plugin: Governance Guardrails
Intended WordPress.org slug: `wp-governance`
Repository: https://github.com/mperalty/wpgov

## Scope

Focused pre-submission review for obvious WordPress.org plugin review issues:

- Plugin header and readme metadata
- License consistency
- Admin capability checks and nonce needs
- Escaped output and sanitized input
- Remote requests, tracking, file writes, eval, and direct database writes
- Text domain consistency
- Must-use plugin documentation caveats

This was not a full WordPress.org Plugin Check run because the local environment does not currently provide PHP or Composer.

## Changes applied during audit

### 1. WordPress.org readme

Created `readme.txt` in WordPress.org plugin readme format with:

- Contributors: `mperalty`
- Tags: `governance, security, admin, mu-plugin, wp-cli`
- Requires at least: `6.4`
- Tested up to: `6.8`
- Requires PHP: `8.1`
- Stable tag: `1.0.0`
- License: `GPL-2.0-or-later`
- License URI: `https://www.gnu.org/licenses/gpl-2.0.html`

The readme describes both normal plugin installation and must-use plugin installation, and avoids claiming that the plugin secures a site by itself.

### 2. Plugin header

Updated `wp-governance.php` to include WordPress.org-ready metadata:

- Plugin URI: `https://github.com/mperalty/wpgov`
- Author: `Malcolm Peralty`
- Author URI: `https://peralty.com/`
- License URI: `https://www.gnu.org/licenses/gpl-2.0.html`
- Text Domain: `wp-governance`
- Domain Path: `/languages`

The header now states that the plugin can be activated as a normal plugin or installed as a must-use plugin.

### 3. License consistency

Replaced the GPLv3 `LICENSE` text with GPLv2 text so the file aligns with the declared `GPL-2.0-or-later` metadata in the plugin header, Composer config, and readme.

### 4. Languages directory

Added `languages/.gitkeep` because the plugin header declares `Domain Path: /languages`.

### 5. Admin access hardening

Added an explicit runtime access check to `WP_Governance\Status_Page::render()`.

The status page was already registered only for users who pass `Config::current_user_is_unrestricted()`, but the render callback now also blocks direct access with a 403 response if the current user is not unrestricted.

### 6. Text domain cleanup

Added the `wp-governance` text domain to runtime translation calls found in:

- `wp-governance/class-status-page.php`
- `wp-governance/modules/class-admin-menu.php`
- `wp-governance/modules/class-features.php`
- `wp-governance/modules/class-login.php`
- `wp-governance/modules/class-post-types.php`
- `wp-governance/modules/class-uploads.php`

A follow-up search found no remaining simple runtime translation calls in `wp-governance/` without an explicit text domain.

## Findings

### Passed / no obvious issue found

- ABSPATH guard present in reviewed runtime PHP files.
- No obvious remote HTTP calls found (`wp_remote_*`, `curl_*`).
- No obvious tracking or phone-home behavior found.
- No `eval()`, `base64_decode()`, or obfuscated-code pattern found in runtime files.
- No direct `$wpdb` usage or obvious raw SQL writes found.
- No `file_put_contents()` / `fopen()` writes found in runtime files.
- Admin status page output is read-only and uses escaping helpers for rendered config values.
- Direct `$_GET` reads found in admin URL access checks are read-only checks, are unslashed/sanitized, and include PHPCS nonce-justification comments.
- Security header values are normalized to remove CRLF characters before being sent.
- Config loading is fail-open and logs warnings rather than fatalling the site on missing or invalid config.

### Small fixes applied

- Added explicit status-page render capability gate.
- Added missing text domains to runtime translation calls.
- Aligned license file with GPL-2.0-or-later metadata.
- Added WordPress.org readme and language directory placeholder.

### Items to expect reviewer scrutiny on

1. Must-use plugin posture

Governance Guardrails was originally documented as a must-use plugin. The new readme and plugin header now explain that it can run as a normal plugin too, but reviewers may still ask for clarification because governance rules are code/config-file driven rather than settings-screen driven.

2. Runtime capability restriction model

The plugin intentionally changes admin visibility, capabilities, upload rules, option behavior, REST access, and headers. Reviewers may look closely at the `unrestricted_role` bypass model. The status page now has an explicit render-time gate; other enforcement modules appear to apply restrictions rather than process privileged state-changing actions.

3. Custom rule callbacks

The `custom_rules` config supports site-defined callables. This is powerful, but because rules live in local PHP config and are not fetched remotely, it does not appear to be remote code execution or phone-home behavior. The readme frames this as an extension surface for site-specific governance.

4. Locked options behavior

The plugin filters option reads and update attempts to pin selected settings from config. This is intentional governance behavior and should be described clearly if reviewers ask.

## Larger items not changed

- No activation/deactivation hooks were added. The plugin does not appear to require database setup or cleanup, and it remains compatible with must-use deployment. WordPress.org may accept this, but reviewers could ask for clarification.
- No `uninstall.php` was added. The plugin does not appear to store persistent plugin settings that require cleanup.
- No screenshots were added. The readme omits a Screenshots section because no screenshot assets are present.

## Verification performed

- Confirmed `readme.txt` exists and uses WordPress.org section/header format.
- Confirmed plugin header metadata is present in `wp-governance.php`.
- Confirmed `LICENSE` now begins with `GNU GENERAL PUBLIC LICENSE Version 2, June 1991`.
- Searched runtime PHP files for obvious remote calls, file writes, eval/obfuscation patterns, direct DB usage, request superglobals, and missing simple text-domain calls.

## Verification blocked

Could not run `composer lint:syntax`, `composer lint`, or PHP syntax checks in this environment because both `php` and `composer` are unavailable on PATH.

Recommended before final zip submission:

```bash
composer lint:syntax
composer lint
```

If available, also run the WordPress.org Plugin Check plugin or CLI workflow against the final zip.
