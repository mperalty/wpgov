<?php
/**
 * Valid test config with all sections populated.
 */
return [
    'features' => [
        'disable_file_editor'           => true,
        'disable_xmlrpc'                => true,
        'restrict_rest_api'             => true,
        'remove_wp_version'             => true,
        'disable_self_ping'             => true,
        'disable_admin_email_check'     => true,
        'disable_auto_update_ui'        => false,
        'disable_comments'              => true,
        'disable_block_editor'          => true,
        'disable_application_passwords' => true,
        'disable_dashboard_widgets'     => true,
        'disable_user_registration'     => true,
        'disable_customizer'            => true,
        'disable_widgets'               => true,
        'disable_search'                => true,
        'disable_feeds'                 => true,
        'disable_file_mods'             => false,
        'disable_updates'               => false,
        'force_ssl_admin'               => false,
        'disable_tagline_editing'       => true,
        'lock_permalink_structure'      => true,
        'disable_wp_cron'               => false,
    ],
    'restricted_menu_slugs' => [
        'tools.php',
        'options-general.php',
    ],
    'remove_admin_bar_nodes' => [
        'wp-logo',
        'comments',
    ],
    'remove_dashboard_widgets' => [
        'dashboard_quick_press',
        'dashboard_primary',
    ],
    'deny_capabilities' => [
        'editor' => [
            'install_plugins',
            'install_themes',
        ],
        'author' => [
            'upload_files',
        ],
    ],
    'allowed_mime_types' => [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'pdf'          => 'application/pdf',
    ],
    'uploads' => [
        'max_upload_size_mb' => 8,
    ],
    'login' => [
        'disable_password_reset' => false,
        'hide_login_errors'      => true,
        'redirect_after_logout'  => '/',
    ],
    'content' => [
        'disable_revisions'  => false,
        'revision_limit'     => 3,
        'disable_autosave'   => false,
        'autosave_interval'  => 120,
        'disable_embeds'     => false,
        'disable_emojis'     => true,
    ],
    'head_cleanup' => [
        'remove_rsd_link'      => true,
        'remove_wlwmanifest'   => true,
        'remove_shortlink'     => true,
        'remove_feed_links'    => false,
        'remove_rest_api_link' => false,
    ],
    'unrestricted_role' => 'administrator',
    'suppress_admin_notices' => [
        'update_nag',
    ],
    'admin_footer' => [
        'left_text'  => 'Test Footer',
        'remove_footer' => false,
    ],
    'post_types' => [
        'hidden' => [],
        'disable_supports' => [],
    ],
    'security' => [
        'headers' => [
            'X-Content-Type-Options' => 'nosniff',
        ],
        'disable_author_archives'      => true,
        'hide_wp_version_from_scripts' => true,
        'remove_pingback_header'       => true,
    ],
    'custom_rules' => [],
];
