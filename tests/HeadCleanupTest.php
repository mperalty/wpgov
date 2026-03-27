<?php

use WP_Governance\Config;

/**
 * Tests for the Head Cleanup module.
 */
class HeadCleanupTest extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        Config::reset();
    }

    public function tearDown(): void {
        // Restore the default wp_head actions that tests may have removed.
        add_action( 'wp_head', 'rsd_link' );
        add_action( 'wp_head', 'wlwmanifest_link' );
        add_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
        add_action( 'wp_head', 'feed_links', 2 );
        add_action( 'wp_head', 'feed_links_extra', 3 );
        add_action( 'wp_head', 'rest_output_link_wp_head', 10 );
        add_action( 'template_redirect', 'wp_shortlink_header', 11 );
        add_action( 'template_redirect', 'rest_output_link_header', 11 );

        Config::reset();
        parent::tearDown();
    }

    public function test_rsd_link_removed(): void {
        // Ensure the action is there first.
        add_action('wp_head', 'rsd_link');

        $settings = ['remove_rsd_link' => true];
        $this->load_module($settings);

        $this->assertFalse(has_action('wp_head', 'rsd_link'));
    }

    public function test_wlwmanifest_removed(): void {
        add_action('wp_head', 'wlwmanifest_link');

        $settings = ['remove_wlwmanifest' => true];
        $this->load_module($settings);

        $this->assertFalse(has_action('wp_head', 'wlwmanifest_link'));
    }

    public function test_shortlink_removed(): void {
        add_action('wp_head', 'wp_shortlink_wp_head');

        $settings = ['remove_shortlink' => true];
        $this->load_module($settings);

        $this->assertFalse(has_action('wp_head', 'wp_shortlink_wp_head'));
    }

    public function test_feed_links_preserved_when_off(): void {
        add_action('wp_head', 'feed_links', 2);

        $settings = ['remove_feed_links' => false];
        $this->load_module($settings);

        $this->assertNotFalse(has_action('wp_head', 'feed_links'));
    }

    public function test_feed_links_removed_when_on(): void {
        add_action('wp_head', 'feed_links', 2);
        add_action('wp_head', 'feed_links_extra', 3);

        $settings = ['remove_feed_links' => true];
        $this->load_module($settings);

        $this->assertFalse(has_action('wp_head', 'feed_links'));
        $this->assertFalse(has_action('wp_head', 'feed_links_extra'));
    }

    public function test_rest_api_link_removed(): void {
        add_action('wp_head', 'rest_output_link_wp_head', 10);

        $settings = ['remove_rest_api_link' => true];
        $this->load_module($settings);

        $this->assertFalse(has_action('wp_head', 'rest_output_link_wp_head'));
    }

    private function load_module(array $settings): void {
        require_once WP_GOVERNANCE_DIR . 'modules/class-head-cleanup.php';
        new \WP_Governance\Modules\Head_Cleanup($settings, Config::get());
    }
}
