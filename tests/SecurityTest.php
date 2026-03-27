<?php

use WP_Governance\Config;

/**
 * Tests for the Security module.
 */
class SecurityTest extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        Config::reset();
    }

    public function tearDown(): void {
        remove_all_filters( 'wp_headers' );
        remove_all_filters( 'style_loader_src' );
        remove_all_filters( 'script_loader_src' );
        remove_all_filters( 'rest_endpoints' );
        remove_all_actions( 'template_redirect' );

        Config::reset();
        parent::tearDown();
    }

    public function test_version_query_strings_stripped(): void {
        $settings = ['hide_wp_version_from_scripts' => true];

        require_once WP_GOVERNANCE_DIR . 'modules/class-security.php';
        new \WP_Governance\Modules\Security($settings, Config::get());

        $url    = 'https://example.com/wp-includes/js/jquery.js?ver=3.7.1';
        $result = apply_filters('script_loader_src', $url);

        $this->assertStringNotContainsString('ver=', $result);
        $this->assertSame('https://example.com/wp-includes/js/jquery.js', $result);
    }

    public function test_version_query_strings_preserved_when_off(): void {
        $settings = ['hide_wp_version_from_scripts' => false];

        require_once WP_GOVERNANCE_DIR . 'modules/class-security.php';
        new \WP_Governance\Modules\Security($settings, Config::get());

        $url    = 'https://example.com/wp-includes/js/jquery.js?ver=3.7.1';
        $result = apply_filters('script_loader_src', $url);

        $this->assertStringContainsString('ver=', $result);
    }

    public function test_pingback_header_removed(): void {
        $settings = ['remove_pingback_header' => true];

        require_once WP_GOVERNANCE_DIR . 'modules/class-security.php';
        new \WP_Governance\Modules\Security($settings, Config::get());

        $headers = apply_filters('wp_headers', [
            'X-Pingback' => 'https://example.com/xmlrpc.php',
            'X-Powered-By' => 'WordPress',
        ]);

        $this->assertArrayNotHasKey('X-Pingback', $headers);
        $this->assertArrayHasKey('X-Powered-By', $headers);
    }

    public function test_custom_security_headers_are_added_through_wp_headers(): void {
        $settings = [
            'headers' => [
                'X-Frame-Options' => 'SAMEORIGIN',
                'Referrer-Policy' => "strict-origin-when-cross-origin\r\nX-Injected: no",
            ],
        ];

        require_once WP_GOVERNANCE_DIR . 'modules/class-security.php';
        new \WP_Governance\Modules\Security($settings, Config::get());

        $headers = apply_filters('wp_headers', []);

        $this->assertSame('SAMEORIGIN', $headers['X-Frame-Options']);
        $this->assertSame('strict-origin-when-cross-origin X-Injected: no', $headers['Referrer-Policy']);
    }

    public function test_invalid_security_header_names_are_ignored(): void {
        $settings = [
            'headers' => [
                "Bad\r\nHeader" => 'blocked',
                'X-Content-Type-Options' => 'nosniff',
            ],
        ];

        require_once WP_GOVERNANCE_DIR . 'modules/class-security.php';
        new \WP_Governance\Modules\Security($settings, Config::get());

        $headers = apply_filters('wp_headers', []);

        $this->assertArrayNotHasKey("Bad\r\nHeader", $headers);
        $this->assertSame('nosniff', $headers['X-Content-Type-Options']);
    }

    public function test_author_archives_blocked_for_unauthenticated(): void {
        $settings = ['disable_author_archives' => true];

        require_once WP_GOVERNANCE_DIR . 'modules/class-security.php';
        new \WP_Governance\Modules\Security($settings, Config::get());

        // The module filters REST endpoints for unauthenticated users.
        wp_set_current_user(0);

        $endpoints = apply_filters('rest_endpoints', [
            '/wp/v2/users'                    => [],
            '/wp/v2/users/(?P<id>[\d]+)'      => [],
            '/wp/v2/posts'                    => [],
        ]);

        $this->assertArrayNotHasKey('/wp/v2/users', $endpoints);
        $this->assertArrayNotHasKey('/wp/v2/users/(?P<id>[\d]+)', $endpoints);
        $this->assertArrayHasKey('/wp/v2/posts', $endpoints);
    }

    public function test_user_endpoints_preserved_for_authenticated(): void {
        $settings = ['disable_author_archives' => true];

        require_once WP_GOVERNANCE_DIR . 'modules/class-security.php';
        new \WP_Governance\Modules\Security($settings, Config::get());

        $user_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);

        $endpoints = apply_filters('rest_endpoints', [
            '/wp/v2/users' => [],
            '/wp/v2/posts' => [],
        ]);

        $this->assertArrayHasKey('/wp/v2/users', $endpoints);
    }

    public function test_all_header_operations_consolidated_in_single_filter(): void {
        $settings = [
            'headers'                 => ['X-Frame-Options' => 'DENY'],
            'add_noindex_headers'     => true,
            'remove_pingback_header'  => true,
        ];

        require_once WP_GOVERNANCE_DIR . 'modules/class-security.php';
        $module = new \WP_Governance\Modules\Security($settings, Config::get());

        // Only one wp_headers callback should be registered.
        $this->assertNotFalse(has_filter('wp_headers', [$module, 'filter_headers']));

        $headers = apply_filters('wp_headers', [
            'X-Pingback' => 'https://example.com/xmlrpc.php',
        ]);

        $this->assertSame('DENY', $headers['X-Frame-Options']);
        $this->assertSame('noindex, nofollow', $headers['X-Robots-Tag']);
        $this->assertArrayNotHasKey('X-Pingback', $headers);
    }

    public function test_no_header_filter_when_no_header_settings(): void {
        $settings = ['disable_author_archives' => true];

        require_once WP_GOVERNANCE_DIR . 'modules/class-security.php';
        $module = new \WP_Governance\Modules\Security($settings, Config::get());

        $this->assertFalse(has_filter('wp_headers', [$module, 'filter_headers']));
    }
}
