<?php

use WP_Governance\Config;

/**
 * Tests for the Config loader.
 */
class ConfigTest extends WP_UnitTestCase {

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
        // Remove any filters added during this test.
        foreach ( $this->filters_to_remove as [ $tag, $callback, $priority ] ) {
            remove_filter( $tag, $callback, $priority );
        }
        $this->filters_to_remove = [];

        Config::reset();
        parent::tearDown();
    }

    /**
     * Helper: override the config path via filter and track for cleanup.
     */
    private function set_config_path( string $path ): void {
        $callback = static function () use ( $path ): string {
            return $path;
        };
        add_filter( 'wp_governance_config_path', $callback, 1 );
        $this->filters_to_remove[] = [ 'wp_governance_config_path', $callback, 1 ];
    }

    // ── Loading ──────────────────────────────────────────────────

    public function test_loads_valid_config_file(): void {
        $this->set_config_path( __DIR__ . '/fixtures/valid-config.php' );
        Config::reset();

        $config = Config::get();

        $this->assertIsArray( $config );
        $this->assertArrayHasKey( 'features', $config );
        $this->assertTrue( $config['features']['disable_xmlrpc'] );
        $this->assertSame( 8, $config['uploads']['max_upload_size_mb'] );
    }

    public function test_returns_defaults_for_missing_file(): void {
        $this->set_config_path( __DIR__ . '/fixtures/nonexistent.php' );
        Config::reset();

        $config = Config::get();

        $this->assertIsArray( $config );
        // Should have default keys with empty arrays.
        $this->assertArrayHasKey( 'features', $config );
        $this->assertEmpty( $config['features'] );
    }

    public function test_returns_defaults_for_malformed_file(): void {
        $this->set_config_path( __DIR__ . '/fixtures/malformed-config.php' );
        Config::reset();

        $config = Config::get();

        $this->assertIsArray( $config );
        $this->assertArrayHasKey( 'features', $config );
        $this->assertEmpty( $config['features'] );
    }

    public function test_returns_defaults_for_empty_config(): void {
        $this->set_config_path( __DIR__ . '/fixtures/empty-config.php' );
        Config::reset();

        $config = Config::get();

        $this->assertIsArray( $config );
        $this->assertArrayHasKey( 'features', $config );
        $this->assertEmpty( $config['features'] );
        $this->assertSame( 'administrator', $config['unrestricted_role'] );
    }

    public function test_minimal_config_merges_with_defaults(): void {
        $this->set_config_path( __DIR__ . '/fixtures/minimal-config.php' );
        Config::reset();

        $config = Config::get();

        $this->assertIsArray( $config );
        $this->assertTrue( $config['features']['disable_xmlrpc'] );
        // Non-specified sections should still get defaults.
        $this->assertArrayHasKey( 'restricted_menu_slugs', $config );
        $this->assertArrayHasKey( 'uploads', $config );
        $this->assertSame( 'administrator', $config['unrestricted_role'] );
    }

    public function test_sample_defaults_load_the_shipped_config(): void {
        $defaults = Config::sample_defaults();

        $this->assertIsArray( $defaults );
        $this->assertTrue( $defaults['features']['disable_xmlrpc'] );
        $this->assertArrayHasKey( 'uploads', $defaults );
        $this->assertArrayHasKey( 'max_upload_size_mb', $defaults['uploads'] );
        $this->assertNull( $defaults['uploads']['max_upload_size_mb'] );
    }

    public function test_partial_nested_sections_receive_neutral_defaults(): void {
        $this->set_config_path( __DIR__ . '/fixtures/partial-nested-config.php' );
        Config::reset();

        $config = Config::get();

        $this->assertTrue( $config['features']['disable_xmlrpc'] );
        $this->assertArrayHasKey( 'disable_feeds', $config['features'] );
        $this->assertFalse( $config['features']['disable_feeds'] );

        $this->assertTrue( $config['login']['hide_login_errors'] );
        $this->assertFalse( $config['login']['disable_password_reset'] );
        $this->assertSame( '', $config['login']['redirect_after_logout'] );

        $this->assertTrue( $config['content']['disable_emojis'] );
        $this->assertNull( $config['content']['revision_limit'] );
        $this->assertNull( $config['content']['autosave_interval'] );

        $this->assertTrue( $config['security']['remove_pingback_header'] );
        $this->assertSame( [], $config['security']['headers'] );
    }

    public function test_structured_custom_rules_are_normalized(): void {
        $this->set_config_path( __DIR__ . '/fixtures/custom-rules-config.php' );
        Config::reset();

        $config = Config::get();

        $this->assertArrayHasKey( 'front_rule', $config['custom_rules'] );
        $this->assertSame( '__return_null', $config['custom_rules']['front_rule']['callback'] );
        $this->assertSame( 'wp_loaded', $config['custom_rules']['front_rule']['hook'] );
        $this->assertSame( 25, $config['custom_rules']['front_rule']['priority'] );
        $this->assertFalse( $config['custom_rules']['front_rule']['admin_only'] );
        $this->assertTrue( $config['custom_rules']['front_rule']['front_only'] );
    }

    public function test_invalid_nested_values_fail_open_to_neutral_defaults(): void {
        $this->set_config_path( __DIR__ . '/fixtures/invalid-nested-config.php' );
        Config::reset();

        $config = Config::get();

        $this->assertFalse( $config['features']['disable_xmlrpc'] );
        $this->assertArrayNotHasKey( 'unknown_flag', $config['features'] );
        $this->assertNull( $config['uploads']['max_upload_size_mb'] );
        $this->assertFalse( $config['login']['hide_login_errors'] );
        $this->assertSame( [], $config['post_types']['hidden'] );
        $this->assertSame( [], $config['post_types']['disable_supports'] );
        $this->assertSame( [], $config['security']['headers'] );
        $this->assertSame( 'administrator', $config['unrestricted_role'] );
    }

    public function test_validation_errors_report_nested_schema_issues(): void {
        $config  = include __DIR__ . '/fixtures/invalid-nested-config.php';
        $errors  = Config::validation_errors( $config );
        $joined  = implode( "\n", $errors );

        $this->assertStringContainsString( 'Unknown features keys: unknown_flag', $joined );
        $this->assertStringContainsString( "'features.disable_xmlrpc' should be a boolean.", $joined );
        $this->assertStringContainsString( "'uploads.max_upload_size_mb' should be a positive number or null.", $joined );
        $this->assertStringContainsString( "'post_types.hidden' should be an array.", $joined );
        $this->assertStringContainsString( "'security.headers' should be an array.", $joined );
    }

    public function test_validation_errors_report_structured_custom_rule_issues(): void {
        $errors = Config::validation_errors(
            [
                'custom_rules' => [
                    'bad_rule' => [
                        'callback'   => 'definitely_not_a_function',
                        'hook'       => '',
                        'priority'   => 'later',
                        'admin_only' => 'maybe',
                        'front_only' => true,
                        'extra'      => true,
                    ],
                ],
            ]
        );
        $joined = implode( "\n", $errors );

        $this->assertStringContainsString( 'Unknown custom_rules.bad_rule keys: extra', $joined );
        $this->assertStringContainsString( "Custom rule 'bad_rule' does not appear to be callable.", $joined );
        $this->assertStringContainsString( "'custom_rules.bad_rule.hook' should be a non-empty string.", $joined );
        $this->assertStringContainsString( "'custom_rules.bad_rule.priority' should be numeric.", $joined );
        $this->assertStringContainsString( "'custom_rules.bad_rule.admin_only' should be a boolean.", $joined );
    }

    // ── Caching ──────────────────────────────────────────────────

    public function test_config_is_cached_across_calls(): void {
        $first  = Config::get();
        $second = Config::get();

        $this->assertSame( $first, $second );
    }

    public function test_reset_clears_cache(): void {
        Config::get(); // Populate cache.
        Config::reset();

        // After reset, the path should be empty until next load.
        // Calling get again will reload.
        $config = Config::get();
        $this->assertIsArray( $config );
    }

    // ── Section Access ───────────────────────────────────────────

    public function test_section_returns_config_section(): void {
        $features = Config::section( 'features' );
        $this->assertIsArray( $features );
    }

    public function test_section_returns_default_for_missing_key(): void {
        $result = Config::section( 'nonexistent_key', 'fallback' );
        $this->assertSame( 'fallback', $result );
    }

    // ── Feature Enabled ──────────────────────────────────────────

    public function test_feature_enabled_returns_bool(): void {
        $result = Config::feature_enabled( 'disable_xmlrpc' );
        $this->assertIsBool( $result );
    }

    public function test_feature_enabled_returns_false_for_unknown_feature(): void {
        $this->assertFalse( Config::feature_enabled( 'totally_fake_feature' ) );
    }

    // ── Filter ───────────────────────────────────────────────────

    public function test_wp_governance_config_filter(): void {
        $callback = static function ( array $config ): array {
            $config['features']['injected_toggle'] = true;
            return $config;
        };
        add_filter( 'wp_governance_config', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_config', $callback, 10 ];

        Config::reset();
        $config = Config::get();

        $this->assertTrue( $config['features']['injected_toggle'] );
    }

    public function test_wp_governance_feature_enabled_filter(): void {
        $callback = static function ( bool $enabled, string $feature ): bool {
            if ( $feature === 'override_test' ) {
                return true;
            }
            return $enabled;
        };
        add_filter( 'wp_governance_feature_enabled', $callback, 10, 2 );
        $this->filters_to_remove[] = [ 'wp_governance_feature_enabled', $callback, 10 ];

        $this->assertTrue( Config::feature_enabled( 'override_test' ) );
    }

    // ── Path ─────────────────────────────────────────────────────

    public function test_path_returns_string(): void {
        $path = Config::path();
        $this->assertIsString( $path );
        $this->assertNotEmpty( $path );
    }

    public function test_config_path_filter_overrides_path(): void {
        $custom = __DIR__ . '/fixtures/minimal-config.php';
        $this->set_config_path( $custom );
        Config::reset();

        $this->assertSame( $custom, Config::path() );
    }

    // ── Unrestricted User ────────────────────────────────────────

    public function test_unauthenticated_user_is_not_unrestricted(): void {
        wp_set_current_user( 0 );
        $this->assertFalse( Config::current_user_is_unrestricted() );
    }

    public function test_admin_user_is_unrestricted(): void {
        $user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $user_id );

        $this->assertTrue( Config::current_user_is_unrestricted() );
    }

    public function test_editor_user_is_not_unrestricted(): void {
        $user_id = self::factory()->user->create( [ 'role' => 'editor' ] );
        wp_set_current_user( $user_id );

        $this->assertFalse( Config::current_user_is_unrestricted() );
    }

    public function test_administrator_is_unrestricted_when_lower_role_is_configured(): void {
        $callback = static function ( array $config ): array {
            $config['unrestricted_role'] = 'editor';
            return $config;
        };
        add_filter( 'wp_governance_config', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_config', $callback, 10 ];

        Config::reset();

        $user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $user_id );

        $this->assertTrue( Config::current_user_is_unrestricted() );
    }

    public function test_nonexistent_role_does_not_grant_unrestricted(): void {
        $callback = static function ( array $config ): array {
            $config['unrestricted_role'] = 'totally_fake_role';
            return $config;
        };
        add_filter( 'wp_governance_config', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_config', $callback, 10 ];

        Config::reset();

        $user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $user_id );

        // Role does not exist, so wp_roles()->get_role() returns null — should be false.
        $this->assertFalse( Config::current_user_is_unrestricted() );
    }

    public function test_subscriber_is_not_unrestricted_when_editor_is_configured(): void {
        $callback = static function ( array $config ): array {
            $config['unrestricted_role'] = 'editor';
            return $config;
        };
        add_filter( 'wp_governance_config', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_config', $callback, 10 ];

        Config::reset();

        $user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
        wp_set_current_user( $user_id );

        // Subscribers lack editor capabilities, so the fallback check should fail.
        $this->assertFalse( Config::current_user_is_unrestricted() );
    }

    public function test_editor_is_unrestricted_when_editor_is_configured(): void {
        $callback = static function ( array $config ): array {
            $config['unrestricted_role'] = 'editor';
            return $config;
        };
        add_filter( 'wp_governance_config', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_config', $callback, 10 ];

        Config::reset();

        $user_id = self::factory()->user->create( [ 'role' => 'editor' ] );
        wp_set_current_user( $user_id );

        // Direct role match — should be true.
        $this->assertTrue( Config::current_user_is_unrestricted() );
    }

    public function test_custom_role_with_no_capabilities_does_not_grant_unrestricted(): void {
        // Register a role with zero capabilities.
        add_role( 'empty_role', 'Empty Role', [] );

        $callback = static function ( array $config ): array {
            $config['unrestricted_role'] = 'empty_role';
            return $config;
        };
        add_filter( 'wp_governance_config', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_config', $callback, 10 ];

        Config::reset();

        $user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $user_id );

        // A role with zero capabilities means the capability check can't
        // confirm the user — should return false.
        $this->assertFalse( Config::current_user_is_unrestricted() );

        // Cleanup.
        remove_role( 'empty_role' );
    }

    public function test_user_with_exact_custom_role_is_unrestricted(): void {
        add_role( 'governance_admin', 'Governance Admin', [ 'read' => true, 'manage_options' => true ] );

        $callback = static function ( array $config ): array {
            $config['unrestricted_role'] = 'governance_admin';
            return $config;
        };
        add_filter( 'wp_governance_config', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_config', $callback, 10 ];

        Config::reset();

        $user_id = self::factory()->user->create( [ 'role' => 'governance_admin' ] );
        wp_set_current_user( $user_id );

        // Direct role match.
        $this->assertTrue( Config::current_user_is_unrestricted() );

        remove_role( 'governance_admin' );
    }
}
