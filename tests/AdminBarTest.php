<?php

use WP_Governance\Config;

/**
 * Tests for the Admin Bar module.
 */
class AdminBarTest extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        Config::reset();
    }

    public function tearDown(): void {
        remove_all_actions( 'wp_before_admin_bar_render' );

        global $wp_admin_bar;
        $wp_admin_bar = null;

        Config::reset();
        parent::tearDown();
    }

    public function test_registers_removal_hook(): void {
        $nodes = ['wp-logo', 'comments'];

        require_once WP_GOVERNANCE_DIR . 'modules/class-admin-bar.php';
        $module = new \WP_Governance\Modules\Admin_Bar($nodes, Config::get());

        $this->assertNotFalse(
            has_action('wp_before_admin_bar_render', [$module, 'remove_nodes'])
        );
    }

    public function test_removes_specified_nodes(): void {
        // Build a mock admin bar.
        require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';

        global $wp_admin_bar;
        $wp_admin_bar = new WP_Admin_Bar();
        $wp_admin_bar->initialize();

        $wp_admin_bar->add_node(['id' => 'wp-logo', 'title' => 'WP']);
        $wp_admin_bar->add_node(['id' => 'comments', 'title' => 'Comments']);
        $wp_admin_bar->add_node(['id' => 'my-custom', 'title' => 'Custom']);

        $nodes = ['wp-logo', 'comments'];

        require_once WP_GOVERNANCE_DIR . 'modules/class-admin-bar.php';
        $module = new \WP_Governance\Modules\Admin_Bar($nodes, Config::get());
        $module->remove_nodes();

        $this->assertNull($wp_admin_bar->get_node('wp-logo'));
        $this->assertNull($wp_admin_bar->get_node('comments'));
        $this->assertNotNull($wp_admin_bar->get_node('my-custom'));
    }
}
