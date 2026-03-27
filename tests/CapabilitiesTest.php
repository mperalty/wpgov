<?php

use WP_Governance\Config;

/**
 * Tests for the Capabilities module.
 */
class CapabilitiesTest extends WP_UnitTestCase {

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

    public function test_denies_capability_for_specified_role(): void {
        $deny_map = [
            'editor' => [ 'install_plugins', 'install_themes' ],
        ];

        $this->load_module( $deny_map );

        $user_id = self::factory()->user->create( [ 'role' => 'editor' ] );
        wp_set_current_user( $user_id );

        $this->assertFalse( current_user_can( 'install_plugins' ) );
        $this->assertFalse( current_user_can( 'install_themes' ) );
    }

    public function test_allows_non_denied_capabilities(): void {
        $deny_map = [
            'editor' => [ 'install_plugins' ],
        ];

        $this->load_module( $deny_map );

        $user_id = self::factory()->user->create( [ 'role' => 'editor' ] );
        wp_set_current_user( $user_id );

        // Editors can normally edit posts — this should not be denied.
        $this->assertTrue( current_user_can( 'edit_posts' ) );
    }

    public function test_unrestricted_role_bypasses_denial(): void {
        $deny_map = [
            'administrator' => [ 'install_plugins' ],
        ];

        $this->load_module( $deny_map );

        $user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $user_id );

        // Admin is the unrestricted role, so denial should be bypassed.
        $this->assertTrue( current_user_can( 'install_plugins' ) );
    }

    public function test_higher_role_bypasses_denial_when_lower_unrestricted_role_is_configured(): void {
        $deny_map = [
            'administrator' => [ 'install_plugins' ],
        ];

        $this->load_module( $deny_map, 'editor' );

        $user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $user_id );

        // Administrators inherit editor capabilities, so they should remain unrestricted.
        $this->assertTrue( current_user_can( 'install_plugins' ) );
    }

    public function test_different_roles_get_different_denials(): void {
        $deny_map = [
            'editor' => [ 'install_plugins' ],
            'author' => [ 'upload_files' ],
        ];

        $this->load_module( $deny_map );

        // Editor should not have install_plugins denied for upload_files.
        $editor_id = self::factory()->user->create( [ 'role' => 'editor' ] );
        wp_set_current_user( $editor_id );

        $this->assertFalse( current_user_can( 'install_plugins' ) );
        // Editors normally can upload files — should still be allowed.
        $this->assertTrue( current_user_can( 'upload_files' ) );

        // Author should have upload_files denied.
        $author_id = self::factory()->user->create( [ 'role' => 'author' ] );
        wp_set_current_user( $author_id );

        $this->assertFalse( current_user_can( 'upload_files' ) );
    }

    public function test_wp_governance_deny_caps_filter(): void {
        $deny_map = [
            'editor' => [ 'install_plugins' ],
        ];

        $this->load_module( $deny_map );

        // Add an extra denied cap via filter.
        $callback = static function ( array $denied, string $role ): array {
            if ( $role === 'editor' ) {
                $denied[] = 'edit_others_posts';
            }
            return $denied;
        };
        add_filter( 'wp_governance_deny_caps', $callback, 10, 2 );
        $this->filters_to_remove[] = [ 'wp_governance_deny_caps', $callback, 10 ];

        $user_id = self::factory()->user->create( [ 'role' => 'editor' ] );
        wp_set_current_user( $user_id );

        $this->assertFalse( current_user_can( 'install_plugins' ) );
        $this->assertFalse( current_user_can( 'edit_others_posts' ) );
    }

    public function test_subscriber_unaffected_by_editor_denials(): void {
        $deny_map = [
            'editor' => [ 'install_plugins' ],
        ];

        $this->load_module( $deny_map );

        $user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
        wp_set_current_user( $user_id );

        // Subscriber doesn't have install_plugins anyway, but
        // the point is the denial doesn't cause unexpected side effects.
        $this->assertFalse( current_user_can( 'install_plugins' ) );
        $this->assertTrue( current_user_can( 'read' ) );
    }

    // ── Helper ───────────────────────────────────────────────────

    private function load_module( array $deny_map, string $unrestricted_role = 'administrator' ): void {
        $callback = static function ( array $config ) use ( $deny_map, $unrestricted_role ): array {
            $config['deny_capabilities'] = $deny_map;
            $config['unrestricted_role']  = $unrestricted_role;
            return $config;
        };
        add_filter( 'wp_governance_config', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_config', $callback, 10 ];

        Config::reset();

        require_once WP_GOVERNANCE_DIR . 'modules/class-capabilities.php';
        new \WP_Governance\Modules\Capabilities( $deny_map, Config::get() );
    }
}
