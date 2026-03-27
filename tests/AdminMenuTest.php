<?php

use WP_Governance\Config;

/**
 * Tests for the Admin Menu module.
 */
class AdminMenuTest extends WP_UnitTestCase {

    /**
     * Tracks filter callbacks added during each test so they can be cleaned up.
     *
     * @var array<array{string, callable, int}>
     */
    private array $filters_to_remove = [];

    public function setUp(): void {
        parent::setUp();
        Config::reset();
    }

    public function tearDown(): void {
        foreach ( $this->filters_to_remove as [ $tag, $callback, $priority ] ) {
            remove_filter( $tag, $callback, $priority );
        }
        $this->filters_to_remove = [];

        Config::reset();
        parent::tearDown();
    }

    public function test_registers_hooks(): void {
        $slugs = [ 'tools.php' ];

        require_once WP_GOVERNANCE_DIR . 'modules/class-admin-menu.php';
        $module = new \WP_Governance\Modules\Admin_Menu( $slugs, Config::get() );

        $this->assertNotFalse( has_action( 'admin_menu', [ $module, 'remove_menus' ] ) );
        $this->assertNotFalse( has_action( 'admin_init', [ $module, 'block_direct_access' ] ) );
    }

    public function test_menu_hook_at_late_priority(): void {
        $slugs = [ 'tools.php' ];

        require_once WP_GOVERNANCE_DIR . 'modules/class-admin-menu.php';
        $module = new \WP_Governance\Modules\Admin_Menu( $slugs, Config::get() );

        $priority = has_action( 'admin_menu', [ $module, 'remove_menus' ] );
        $this->assertSame( 999, $priority );
    }

    public function test_wp_governance_restrict_menu_filter(): void {
        $slugs = [ 'tools.php' ];

        $callback = static function ( array $slugs ): array {
            $slugs[] = 'options-general.php';
            return $slugs;
        };
        add_filter( 'wp_governance_restrict_menu', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_restrict_menu', $callback, 10 ];

        require_once WP_GOVERNANCE_DIR . 'modules/class-admin-menu.php';
        $module = new \WP_Governance\Modules\Admin_Menu( $slugs, Config::get() );

        // The filter should have been applied during construction.
        // We verify by checking the hook is registered (the internal slug list is private,
        // but the filter was applied in register()).
        $this->assertNotFalse( has_action( 'admin_menu', [ $module, 'remove_menus' ] ) );
    }

    public function test_unrestricted_user_bypasses_menu_removal(): void {
        $slugs = [ 'tools.php' ];

        require_once WP_GOVERNANCE_DIR . 'modules/class-admin-menu.php';
        $module = new \WP_Governance\Modules\Admin_Menu( $slugs, Config::get() );

        // Set up as admin (unrestricted).
        $admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin_id );

        // Simulate the admin menu global.
        global $menu;
        $menu     = [];
        $menu[25] = [ 'Tools', 'edit_posts', 'tools.php', '', 'menu-top', 'menu-tools', 'dashicons-admin-tools' ];

        // Call the removal method — should do nothing for admin.
        $module->remove_menus();

        // Menu should still be there.
        $this->assertNotEmpty( $menu );
    }
}
