<?php

use WP_Governance\Config;

/**
 * Tests for the Notices module.
 */
class NoticesTest extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        wp_set_current_user( 0 );
        Config::reset();
    }

    public function tearDown(): void {
        remove_all_actions( 'admin_init' );
        remove_all_actions( 'admin_notices' );
        remove_all_actions( 'network_admin_notices' );
        remove_all_actions( 'try_gutenberg_panel' );

        wp_set_current_user( 0 );
        Config::reset();
        parent::tearDown();
    }

    public function test_update_nag_removal_registered(): void {
        $notices = ['update_nag'];

        require_once WP_GOVERNANCE_DIR . 'modules/class-notices.php';
        new \WP_Governance\Modules\Notices($notices, Config::get());

        // The module registers an admin_init action to remove the nag.
        $this->assertNotFalse(has_action('admin_init'));
    }

    public function test_empty_notices_registers_nothing_extra(): void {
        $before = has_action('admin_init');

        require_once WP_GOVERNANCE_DIR . 'modules/class-notices.php';
        new \WP_Governance\Modules\Notices([], Config::get());

        // No new hooks should be added for an empty list.
        $this->assertSame($before, has_action('admin_init'));
    }

    public function test_custom_notice_is_removed_for_governed_users(): void {
        $callback = static function (): void {};
        add_action( 'admin_notices', $callback );

        require_once WP_GOVERNANCE_DIR . 'modules/class-notices.php';
        $module = new \WP_Governance\Modules\Notices( [ $callback ], Config::get() );
        $module->suppress_notices();

        $this->assertFalse( has_action( 'admin_notices', $callback ) );
    }

    public function test_unrestricted_users_keep_configured_notices(): void {
        $user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $user_id );

        $callback = static function (): void {};
        add_action( 'admin_notices', $callback );

        require_once WP_GOVERNANCE_DIR . 'modules/class-notices.php';
        $module = new \WP_Governance\Modules\Notices( [ $callback ], Config::get() );
        $module->suppress_notices();

        $this->assertNotFalse( has_action( 'admin_notices', $callback ) );
    }
}
