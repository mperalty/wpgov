<?php

use WP_Governance\Config;

/**
 * Tests for the Login module.
 */
class LoginTest extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        Config::reset();
    }

    public function tearDown(): void {
        remove_all_filters( 'allow_password_reset' );
        remove_all_filters( 'show_password_fields' );
        remove_all_filters( 'login_errors' );
        remove_all_filters( 'logout_redirect' );
        remove_all_actions( 'login_form_lostpassword' );
        remove_all_actions( 'login_form_retrievepassword' );
        remove_all_actions( 'login_head' );

        Config::reset();
        parent::tearDown();
    }

    public function test_password_reset_disabled(): void {
        $settings = ['disable_password_reset' => true];
        $this->load_module($settings);

        $this->assertFalse(apply_filters('allow_password_reset', true));
        $this->assertFalse(apply_filters('show_password_fields', true));
    }

    public function test_password_reset_blocks_lost_password_form(): void {
        $settings = ['disable_password_reset' => true];
        $this->load_module($settings);

        $this->assertNotFalse(has_action('login_form_lostpassword'));
        $this->assertNotFalse(has_action('login_form_retrievepassword'));
    }

    public function test_password_reset_hides_lost_password_link(): void {
        $settings = ['disable_password_reset' => true];
        $this->load_module($settings);

        $this->assertNotFalse(has_action('login_head'));
    }

    public function test_password_reset_allowed_when_off(): void {
        $settings = ['disable_password_reset' => false];
        $this->load_module($settings);

        $this->assertTrue(apply_filters('allow_password_reset', true));
    }

    public function test_login_errors_hidden(): void {
        $settings = ['hide_login_errors' => true];
        $this->load_module($settings);

        $result = apply_filters('login_errors', '<strong>Error:</strong> The username is not registered.');
        $this->assertSame('Invalid credentials.', $result);
    }

    public function test_login_errors_shown_when_off(): void {
        $settings = ['hide_login_errors' => false];
        $this->load_module($settings);

        $original = '<strong>Error:</strong> The username is not registered.';
        $result   = apply_filters('login_errors', $original);
        $this->assertSame($original, $result);
    }

    public function test_logout_redirect_is_validated_to_local_url(): void {
        $settings = ['redirect_after_logout' => '/signed-out'];
        $this->load_module($settings);

        $result = apply_filters('logout_redirect', 'https://example.org/wp-login.php?loggedout=true');

        $this->assertSame(home_url('/signed-out'), $result);
    }

    public function test_external_logout_redirect_falls_back_to_safe_url(): void {
        $settings = ['redirect_after_logout' => 'https://evil.example/logout'];
        $this->load_module($settings);

        $fallback = home_url('/wp-login.php?loggedout=true');
        $result   = apply_filters('logout_redirect', $fallback);

        $this->assertSame($fallback, $result);
    }

    private function load_module(array $settings): void {
        require_once WP_GOVERNANCE_DIR . 'modules/class-login.php';
        new \WP_Governance\Modules\Login($settings, Config::get());
    }
}
