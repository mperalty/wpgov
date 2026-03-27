<?php

use WP_Governance\Config;
use WP_Governance\Status_Page;

require_once WP_GOVERNANCE_DIR . 'class-status-page.php';

/**
 * Tests for the governance status page.
 */
class StatusPageTest extends WP_UnitTestCase {

    /**
     * @var array<array{string, callable, int}>
     */
    private array $filters_to_remove = [];

    public function setUp(): void {
        parent::setUp();
        Config::reset();

        if ( function_exists( 'set_current_screen' ) ) {
            set_current_screen( 'dashboard' );
        }
    }

    public function tearDown(): void {
        foreach ( $this->filters_to_remove as [ $tag, $callback, $priority ] ) {
            remove_filter( $tag, $callback, $priority );
        }
        $this->filters_to_remove = [];

        $this->remove_governance_menu_entry();

        if ( function_exists( 'set_current_screen' ) ) {
            set_current_screen( 'front' );
        }

        Config::reset();
        parent::tearDown();
    }

    public function test_register_page_is_hidden_for_restricted_users(): void {
        $this->prime_tools_menu();

        $user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
        wp_set_current_user( $user_id );

        $page = new Status_Page( Config::get() );
        $page->register_page();

        $this->assertNotContains( 'wp-governance', $this->tools_menu_slugs() );
    }

    public function test_register_page_allows_higher_roles_when_lower_unrestricted_role_is_configured(): void {
        $callback = static function ( array $config ): array {
            $config['unrestricted_role'] = 'editor';
            return $config;
        };
        add_filter( 'wp_governance_config', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_config', $callback, 10 ];

        Config::reset();
        $this->prime_tools_menu();

        $user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $user_id );

        $page = new Status_Page( Config::get() );
        $page->register_page();

        $this->assertContains( 'wp-governance', $this->tools_menu_slugs() );
    }

    public function test_render_outputs_custom_rule_metadata(): void {
        $config = [
            'features' => [
                'disable_xmlrpc' => true,
            ],
            'custom_rules' => [
                'front_rule' => [
                    'callback'   => '__return_null',
                    'hook'       => 'wp_loaded',
                    'priority'   => 25,
                    'front_only' => true,
                ],
            ],
            'security' => [],
        ];

        $page = new Status_Page( $config );

        ob_start();
        $page->render();
        $html = ob_get_clean();

        $this->assertStringContainsString( 'WP Governance - Active Rules', $html );
        $this->assertStringContainsString( 'Feature Toggles', $html );
        $this->assertStringContainsString( 'Custom Rules', $html );
        $this->assertStringContainsString( 'Hook: wp_loaded', $html );
        $this->assertStringContainsString( 'Priority: 25', $html );
        $this->assertStringContainsString( 'Front-end only', $html );
    }

    private function prime_tools_menu(): void {
        global $submenu, $admin_page_hooks;

        if ( ! isset( $submenu['tools.php'] ) ) {
            $submenu['tools.php'] = [];
        }

        if ( ! isset( $admin_page_hooks['tools.php'] ) ) {
            $admin_page_hooks['tools.php'] = 'tools';
        }
    }

    /**
     * @return string[]
     */
    private function tools_menu_slugs(): array {
        global $submenu;

        $slugs = [];

        foreach ( $submenu['tools.php'] ?? [] as $entry ) {
            if ( isset( $entry[2] ) ) {
                $slugs[] = (string) $entry[2];
            }
        }

        return $slugs;
    }

    private function remove_governance_menu_entry(): void {
        global $submenu;

        if ( ! isset( $submenu['tools.php'] ) ) {
            return;
        }

        foreach ( $submenu['tools.php'] as $index => $entry ) {
            if ( ( $entry[2] ?? '' ) === 'wp-governance' ) {
                unset( $submenu['tools.php'][ $index ] );
            }
        }
    }
}
