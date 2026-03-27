<?php

use WP_Governance\Config;

/**
 * Tests for the Dashboard module.
 */
class DashboardTest extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        wp_set_current_user( 0 );
        Config::reset();
    }

    public function tearDown(): void {
        remove_all_actions( 'wp_dashboard_setup' );

        wp_set_current_user( 0 );
        Config::reset();
        parent::tearDown();
    }

    public function test_registers_removal_hook(): void {
        $widgets = ['dashboard_quick_press', 'dashboard_primary'];

        require_once WP_GOVERNANCE_DIR . 'modules/class-dashboard.php';
        $module = new \WP_Governance\Modules\Dashboard($widgets, Config::get());

        $this->assertNotFalse(
            has_action('wp_dashboard_setup', [$module, 'remove_widgets'])
        );
    }

    public function test_hook_registered_at_late_priority(): void {
        $widgets = ['dashboard_quick_press'];

        require_once WP_GOVERNANCE_DIR . 'modules/class-dashboard.php';
        $module = new \WP_Governance\Modules\Dashboard($widgets, Config::get());

        $priority = has_action('wp_dashboard_setup', [$module, 'remove_widgets']);
        $this->assertSame(999, $priority);
    }

    public function test_unrestricted_users_keep_dashboard_widgets(): void {
        global $wp_meta_boxes;

        $user_id = self::factory()->user->create(['role' => 'administrator']);
        wp_set_current_user($user_id);

        $wp_meta_boxes['dashboard']['side']['core']['dashboard_quick_press'] = ['id' => 'dashboard_quick_press'];

        require_once WP_GOVERNANCE_DIR . 'modules/class-dashboard.php';
        $module = new \WP_Governance\Modules\Dashboard(['dashboard_quick_press'], Config::get());
        $module->remove_widgets();

        $this->assertArrayHasKey('dashboard_quick_press', $wp_meta_boxes['dashboard']['side']['core']);
    }
}
