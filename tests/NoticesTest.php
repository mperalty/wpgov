<?php

use WP_Governance\Config;

/**
 * Tests for the Notices module.
 */
class NoticesTest extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        Config::reset();
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
}
