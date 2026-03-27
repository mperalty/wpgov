<?php
/**
 * Config fixture with structured custom rules.
 */
return [
    'custom_rules' => [
        'front_rule' => [
            'callback'   => '__return_null',
            'hook'       => 'wp_loaded',
            'priority'   => 25,
            'front_only' => true,
        ],
    ],
];
