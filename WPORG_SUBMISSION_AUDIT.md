# WordPress.org Submission Audit - Governance Guardrails

Audit date: 2026-05-22
Plugin: Governance Guardrails
Intended WordPress.org slug: `governance-guardrails`
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

Updated `governance-guardrails.php` to include WordPress.org-ready metadata:

- Plugin URI: `https://github.com/mperalty/wpgov`
- Author: `Malcolm Peralty`
- Author URI: `https://peralty.com/`
- License URI: `https://www.gnu.org/licenses/gpl-2.0.html`
- Text Domain: `governance-guardrails`
- Domain Path: `/languages`

The header now states that the plugin can be activated as a normal plugin or installed as a must-use plugin.

### 3. License consistency

Replaced the GPLv3 `LICENSE` text with GPLv2 text so the file aligns with the declared `GPL-2.0-or-later` metadata in the plugin header, Composer config, and readme.

### 4. Languages directory

Added `languages/.gitkeep` because the plugin header declares `Domain Path: /languages`.

### 5. Admin access hardening

Added an explicit runtime access check to `GovGuard\Status_Page::render()`.

The status page was already registered only for users who pass `Config::current_user_is_unrestricted()`, but the render callback now also blocks direct access with a 403 response if the current user is not unrestricted.

### 6. Text domain cleanup

Added the `governance-guardrails` text domain to runtime translation calls found in:

- `governance-guardrails/class-status-page.php`
- `governance-guardrails/modules/class-admin-menu.php`
- `governance-guardrails/modules/class-features.php`
- `governance-guardrails/modules/class-login.php`
- `governance-guardrails/modules/class-post-types.php`
- `governance-guardrails/modules/class-uploads.php`

A follow-up search found no remaining simple runtime translation calls in `governance-guardrails/` without an explicit text domain.

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
- Confirmed plugin header metadata is present in `governance-guardrails.php`.
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

---

# Review round 2 fixes — 2026-06-09

Responses to the WordPress.org plugin review email (Plugin URI, wp_enqueue usage, changing global behaviour), plus a sweep for similar issues.

## 1. Plugin URI seems to be invalid

The reviewer's checker timed out fetching `https://github.com/mperalty/wpgov`. The repository is now publicly reachable (verified anonymously with an HTTP 200 response), so the header was left unchanged. If the repository was private at review time, it must stay public for resubmission.

## 2. Use wp_enqueue commands

All four flagged inline `<script>`/`<style>` echoes were converted to the enqueue APIs:

- `modules/class-locked-options.php` — the field-lock script moved from an `admin_footer` echo to `admin_enqueue_scripts` with `wp_register_script( 'govguard-locked-options', false, ... )` + `wp_enqueue_script()` + `wp_add_inline_script()`. The "Locked by Governance Guardrails" tooltip string is now translatable and JSON-encoded.
- `modules/class-features.php` (registration toggle) — `admin_head-options-general.php` echo replaced with `admin_enqueue_scripts` + `wp_register_style( 'govguard-registration-lock', false, ... )` + `wp_add_inline_style()`, gated on the `options-general.php` hook suffix.
- `modules/class-features.php` (permalink lock) — same pattern with the `govguard-permalink-lock` handle on `options-permalink.php`.
- `modules/class-login.php` — `login_head` echo replaced with `login_enqueue_scripts` + `wp_register_style( 'govguard-login', false, ... )` + `wp_add_inline_style()`.

## 3. Changing global behaviour (defining core constants)

All runtime `define()` calls of WordPress core constants were replaced with targeted, plugin-scoped filters. No functionality was removed:

- `AUTOSAVE_INTERVAL` (`modules/class-content.php`) — replaced with a `block_editor_settings_all` filter (`autosaveInterval`) for the block editor plus a `wp_add_inline_script( 'autosave', ..., 'before' )` override of `autosaveL10n.autosaveInterval` for the classic editor. The previous define was dead code in normal plugin mode because core defines the constant in `wp_functionality_constants()`.
- `DISABLE_WP_CRON` (`modules/class-features.php`) — replaced with a `pre_get_ready_cron_jobs` filter that returns an empty list only when `wp_doing_cron()` is false. Page loads no longer spawn loopback cron requests, while direct `wp-cron.php` hits and WP-CLI cron runs (which set `DOING_CRON`) still see the live queue. The sample config and a new readme FAQ now state that a real system cron is required.
- `WP_POST_REVISIONS` (`modules/class-content.php`) — replaced with `add_filter( 'wp_revisions_to_keep', '__return_zero', 999 )`, which is exactly how core maps `WP_POST_REVISIONS = false`.
- `DISALLOW_FILE_EDIT` (`modules/class-features.php` and `modules/class-security.php`) — replaced with a `file_mod_allowed` filter that returns false only for the `capability_edit_themes` context (the same scope core gives the constant in `map_meta_cap()`).
- `DISALLOW_FILE_MODS` (`modules/class-features.php`) — replaced with `add_filter( 'file_mod_allowed', '__return_false', 999 )`, covering every context the constant covers.

## 4. Additional fixes found during the sweep

- `class-cli.php` — `wp governance caps` without `--role` listed nothing because the null default failed the `'' !== $filter_role` guard. Fixed by defaulting the filter to an empty string.
- `modules/class-locked-options.php` — the admin notice text was hardcoded English; now uses `_n()` with the plugin text domain, `number_format_i18n()`, and `wp_kses()`.
- Removed two stale tests for a `disable_updates` feature that does not exist in the plugin, and fixed the status-page render test to authenticate as an administrator (the render gate added in round 1 made it fail).
- Added `bin/build-zip.php` to build the submission zip reproducibly.

## Verification (round 2)

- `php bin/lint.php` — syntax OK for 50 files.
- `phpcs --standard=phpcs.xml.dist` — clean.
- `phpstan analyse` — no errors.
- `phpunit` — 194 tests, 389 assertions, all passing (baseline before fixes was 186 tests with 2 failures and 2 errors).
- Rebuilt `governance-guardrails-1.0.0.zip` from the fixed tree.
