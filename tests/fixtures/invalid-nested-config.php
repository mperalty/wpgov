<?php
/**
 * Invalid nested config used to verify fail-open normalization and validation.
 */
return [
    'features' => [
        'disable_xmlrpc' => 'yes',
        'unknown_flag'   => true,
    ],
    'uploads' => [
        'max_upload_size_mb' => 'nope',
        'extra'              => 1,
    ],
    'login' => [
        'hide_login_errors' => 'maybe',
    ],
    'admin_footer' => [
        'left_text' => [],
    ],
    'post_types' => [
        'hidden' => 'page',
        'disable_supports' => [
            'post' => 'editor',
        ],
    ],
    'security' => [
        'headers' => 'nope',
    ],
    'custom_rules' => [
        'bad_rule' => 'definitely_not_a_function',
    ],
    'unrestricted_role' => [
        'administrator',
    ],
];
