<?php

use WP_Governance\Config;

/**
 * Tests for the Admin Footer module.
 */
class AdminFooterTest extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        wp_set_current_user( 0 );
        Config::reset();
    }

    public function tearDown(): void {
        remove_all_filters( 'admin_footer_text' );
        remove_all_filters( 'update_footer' );

        wp_set_current_user( 0 );
        Config::reset();
        parent::tearDown();
    }

    public function test_left_text_replaced(): void {
        $settings = ['left_text' => 'Managed by IT'];

        require_once WP_GOVERNANCE_DIR . 'modules/class-admin-footer.php';
        new \WP_Governance\Modules\Admin_Footer($settings, Config::get());

        $result = apply_filters('admin_footer_text', 'Thank you for creating with WordPress.');
        $this->assertSame('Managed by IT', $result);
    }

    public function test_right_text_replaced(): void {
        $settings = ['right_text' => 'v1.0'];

        require_once WP_GOVERNANCE_DIR . 'modules/class-admin-footer.php';
        new \WP_Governance\Modules\Admin_Footer($settings, Config::get());

        $result = apply_filters('update_footer', 'WordPress 6.4');
        $this->assertSame('v1.0', $result);
    }

    public function test_footer_removed_entirely(): void {
        $settings = ['remove_footer' => true];

        require_once WP_GOVERNANCE_DIR . 'modules/class-admin-footer.php';
        new \WP_Governance\Modules\Admin_Footer($settings, Config::get());

        $left  = apply_filters('admin_footer_text', 'Thank you for creating with WordPress.');
        $right = apply_filters('update_footer', 'WordPress 6.4');

        $this->assertEmpty($left);
        $this->assertEmpty($right);
    }

    public function test_html_is_sanitized(): void {
        $settings = ['left_text' => '<b>Bold</b><script>alert("xss")</script>'];

        require_once WP_GOVERNANCE_DIR . 'modules/class-admin-footer.php';
        new \WP_Governance\Modules\Admin_Footer($settings, Config::get());

        $result = apply_filters('admin_footer_text', '');

        $this->assertStringContainsString('<b>Bold</b>', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function test_unrestricted_users_keep_original_footer_text(): void {
        $user_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);

        $settings = ['left_text' => 'Managed by IT'];

        require_once WP_GOVERNANCE_DIR . 'modules/class-admin-footer.php';
        new \WP_Governance\Modules\Admin_Footer($settings, Config::get());

        $result = apply_filters('admin_footer_text', 'Thank you for creating with WordPress.');

        $this->assertSame('Thank you for creating with WordPress.', $result);
    }
}
