<?php

use WP_Governance\Config;

/**
 * Tests for the Admin Footer module.
 */
class AdminFooterTest extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        Config::reset();
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
}
