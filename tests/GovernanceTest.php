<?php

use WP_Governance\Config;
use WP_Governance\Governance;

/**
 * Tests for the Governance orchestrator.
 */
class GovernanceTest extends WP_UnitTestCase {

    /**
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

        if ( function_exists( 'set_current_screen' ) ) {
            set_current_screen( 'front' );
        }

        Config::reset();
        parent::tearDown();
    }

    // ── Module Loading ───────────────────────────────────────────

    public function test_wp_governance_before_enforce_fires(): void {
        $fired = false;
        $callback = static function () use ( &$fired ): void {
            $fired = true;
        };
        add_action( 'wp_governance_before_enforce', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_before_enforce', $callback, 10 ];

        // Re-instantiate to trigger boot.
        $this->boot_fresh_instance();

        $this->assertTrue( $fired );
    }

    public function test_wp_governance_loaded_fires_with_config_and_modules(): void {
        $captured_config  = null;
        $captured_modules = null;
        $callback = static function ( array $config, array $modules ) use ( &$captured_config, &$captured_modules ): void {
            $captured_config  = $config;
            $captured_modules = $modules;
        };
        add_action( 'wp_governance_loaded', $callback, 10, 2 );
        $this->filters_to_remove[] = [ 'wp_governance_loaded', $callback, 10 ];

        $this->boot_fresh_instance();

        $this->assertIsArray( $captured_config );
        $this->assertIsArray( $captured_modules );
    }

    public function test_empty_config_loads_no_modules(): void {
        $this->set_config_path( __DIR__ . '/fixtures/empty-config.php' );

        $modules = $this->capture_loaded_modules();

        $this->assertEmpty( $modules );
    }

    public function test_modules_load_when_config_section_is_populated(): void {
        $this->set_config_path( __DIR__ . '/fixtures/valid-config.php' );

        $modules = $this->capture_loaded_modules();

        // Valid config has features, admin bar, dashboard, capabilities, login,
        // content, head_cleanup, notices, security, admin_footer sections populated.
        $this->assertArrayHasKey( 'features', $modules );
        $this->assertArrayHasKey( 'remove_admin_bar_nodes', $modules );
        $this->assertArrayHasKey( 'deny_capabilities', $modules );
        $this->assertArrayHasKey( 'head_cleanup', $modules );
        $this->assertArrayHasKey( 'security', $modules );
    }

    public function test_uploads_module_boots_when_mime_types_configured(): void {
        $callback = static function ( array $config ): array {
            $config['allowed_mime_types'] = [
                'jpg|jpeg|jpe' => 'image/jpeg',
            ];
            return $config;
        };
        add_filter( 'wp_governance_config', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_config', $callback, 10 ];

        $modules = $this->capture_loaded_modules();

        $this->assertArrayHasKey( 'uploads', $modules );
    }

    public function test_uploads_module_boots_when_size_limit_configured(): void {
        $callback = static function ( array $config ): array {
            $config['allowed_mime_types'] = [];
            $config['uploads']            = [ 'max_upload_size_mb' => 5 ];
            return $config;
        };
        add_filter( 'wp_governance_config', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_config', $callback, 10 ];

        $modules = $this->capture_loaded_modules();

        $this->assertArrayHasKey( 'uploads', $modules );
    }

    public function test_uploads_module_skips_when_both_sections_empty(): void {
        $callback = static function ( array $config ): array {
            $config['allowed_mime_types'] = [];
            $config['uploads']            = [];
            return $config;
        };
        add_filter( 'wp_governance_config', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_config', $callback, 10 ];

        $modules = $this->capture_loaded_modules();

        $this->assertArrayNotHasKey( 'uploads', $modules );
    }

    public function test_custom_rules_are_invoked_on_init(): void {
        $called_with = null;
        $rule        = static function ( array $config ) use ( &$called_with ): void {
            $called_with = $config;
        };

        $callback = static function ( array $config ) use ( $rule ): array {
            $config['custom_rules'] = [ 'test_rule' => $rule ];
            return $config;
        };
        add_filter( 'wp_governance_config', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_config', $callback, 10 ];

        $this->boot_fresh_instance();

        // Custom rules fire on init.
        do_action( 'init' );

        $this->assertIsArray( $called_with );
        $this->assertArrayHasKey( 'custom_rules', $called_with );
    }

    public function test_structured_custom_rules_run_on_their_declared_hook(): void {
        $called_with = null;
        $rule        = static function ( array $config ) use ( &$called_with ): void {
            $called_with = $config;
        };

        $callback = static function ( array $config ) use ( $rule ): array {
            $config['custom_rules'] = [
                'front_rule' => [
                    'callback'   => $rule,
                    'hook'       => 'wp_loaded',
                    'priority'   => 25,
                    'front_only' => true,
                ],
            ];
            return $config;
        };
        add_filter( 'wp_governance_config', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_config', $callback, 10 ];

        if ( function_exists( 'set_current_screen' ) ) {
            set_current_screen( 'front' );
        }

        $this->boot_fresh_instance();

        do_action( 'init' );
        $this->assertNull( $called_with );

        do_action( 'wp_loaded' );
        $this->assertIsArray( $called_with );
    }

    public function test_admin_only_custom_rules_do_not_run_on_front_end(): void {
        $called = false;
        $rule   = static function () use ( &$called ): void {
            $called = true;
        };

        $callback = static function ( array $config ) use ( $rule ): array {
            $config['custom_rules'] = [
                'admin_rule' => [
                    'callback'   => $rule,
                    'hook'       => 'wp_loaded',
                    'admin_only' => true,
                ],
            ];
            return $config;
        };
        add_filter( 'wp_governance_config', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_config', $callback, 10 ];

        if ( function_exists( 'set_current_screen' ) ) {
            set_current_screen( 'front' );
        }

        $this->boot_fresh_instance();

        do_action( 'wp_loaded' );
        $this->assertFalse( $called );
    }

    public function test_admin_only_custom_rules_run_in_admin_context(): void {
        $called = false;
        $rule   = static function () use ( &$called ): void {
            $called = true;
        };

        $callback = static function ( array $config ) use ( $rule ): array {
            $config['custom_rules'] = [
                'admin_rule' => [
                    'callback'   => $rule,
                    'hook'       => 'current_screen',
                    'admin_only' => true,
                ],
            ];
            return $config;
        };
        add_filter( 'wp_governance_config', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_config', $callback, 10 ];

        $this->boot_fresh_instance();

        if ( function_exists( 'set_current_screen' ) ) {
            set_current_screen( 'dashboard' );
        }

        $this->assertTrue( $called );
    }

    public function test_uncallable_custom_rules_are_silently_skipped(): void {
        $callback = static function ( array $config ): array {
            $config['custom_rules'] = [ 'bad_rule' => 'definitely_not_a_function_that_exists' ];
            return $config;
        };
        add_filter( 'wp_governance_config', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_config', $callback, 10 ];

        // Capture modules — uncallable rules should not appear.
        $modules = $this->capture_loaded_modules();

        // No init callback should have been registered for the bad rule.
        // The rule's callback is not callable, so parse_custom_rule returns
        // empty and run_custom_rules skips it entirely. Verify by running
        // init and confirming the function was never invoked (it would
        // trigger a fatal error if it were).
        $init_count_before = did_action( 'init' );
        do_action( 'init' );
        $init_count_after = did_action( 'init' );

        // init fired without errors — the bad rule was not registered.
        $this->assertSame( $init_count_before + 1, $init_count_after );

        // The custom_rules section should not have produced a module entry.
        $this->assertArrayNotHasKey( 'custom_rules', $modules );
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function set_config_path( string $path ): void {
        $callback = static function () use ( $path ): string {
            return $path;
        };
        add_filter( 'wp_governance_config_path', $callback, 1 );
        $this->filters_to_remove[] = [ 'wp_governance_config_path', $callback, 1 ];
        Config::reset();
    }

    /**
     * Boot a fresh Governance instance by resetting the singleton.
     */
    private function boot_fresh_instance(): void {
        // Use reflection to reset the singleton so boot() runs again.
        $ref = new ReflectionClass( Governance::class );
        $prop = $ref->getProperty( 'instance' );
        $prop->setAccessible( true );
        $prop->setValue( null, null );

        Governance::instance();
    }

    /**
     * Boot a fresh instance and capture which modules were loaded.
     *
     * @return array Module keys => objects.
     */
    private function capture_loaded_modules(): array {
        $captured = null;
        $callback = static function ( array $config, array $modules ) use ( &$captured ): void {
            $captured = $modules;
        };
        add_action( 'wp_governance_loaded', $callback, 10, 2 );
        $this->filters_to_remove[] = [ 'wp_governance_loaded', $callback, 10 ];

        $this->boot_fresh_instance();

        return $captured ?? [];
    }
}
