<?php

use WP_Governance\Config;

/**
 * Tests for the Features module.
 */
class FeaturesTest extends WP_UnitTestCase {

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

        foreach ( $this->governance_hooks() as $hook ) {
            remove_all_filters( $hook );
            remove_all_actions( $hook );
        }

        Config::reset();
        parent::tearDown();
    }

    // ── XML-RPC ──────────────────────────────────────────────────

    public function test_xmlrpc_disabled_when_toggled(): void {
        $this->load_module_with( [ 'disable_xmlrpc' => true ] );

        $this->assertFalse( apply_filters( 'xmlrpc_enabled', true ) );
    }

    public function test_xmlrpc_remains_when_not_toggled(): void {
        $this->load_module_with( [ 'disable_xmlrpc' => false ] );

        $this->assertTrue( apply_filters( 'xmlrpc_enabled', true ) );
    }

    // ── REST API ─────────────────────────────────────────────────

    public function test_rest_api_blocked_for_unauthenticated(): void {
        $this->load_module_with( [ 'restrict_rest_api' => true ] );

        wp_set_current_user( 0 );
        $result = apply_filters( 'rest_authentication_errors', null );

        $this->assertInstanceOf( 'WP_Error', $result );
        $this->assertSame( 'rest_not_logged_in', $result->get_error_code() );
    }

    public function test_rest_api_allowed_for_authenticated(): void {
        $this->load_module_with( [ 'restrict_rest_api' => true ] );

        $user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
        wp_set_current_user( $user_id );

        $result = apply_filters( 'rest_authentication_errors', null );

        $this->assertNull( $result );
    }

    public function test_rest_api_passes_through_existing_errors(): void {
        $this->load_module_with( [ 'restrict_rest_api' => true ] );

        $error  = new WP_Error( 'test_error', 'Test' );
        $result = apply_filters( 'rest_authentication_errors', $error );

        $this->assertSame( $error, $result );
    }

    // ── WP Version ───────────────────────────────────────────────

    public function test_wp_version_removed_from_generator(): void {
        $this->load_module_with( [ 'remove_wp_version' => true ] );

        $output = apply_filters( 'the_generator', '<meta name="generator" content="WordPress 6.4" />' );
        $this->assertEmpty( $output );
    }

    // ── Admin Email Check ────────────────────────────────────────

    public function test_admin_email_check_disabled(): void {
        $this->load_module_with( [ 'disable_admin_email_check' => true ] );

        $result = apply_filters( 'admin_email_check_interval', 180 );
        $this->assertFalse( $result );
    }

    // ── Block Editor ─────────────────────────────────────────────

    public function test_block_editor_disabled(): void {
        $this->load_module_with( [ 'disable_block_editor' => true ] );

        $this->assertFalse( apply_filters( 'use_block_editor_for_post', true ) );
        $this->assertFalse( apply_filters( 'use_block_editor_for_post_type', true, 'post' ) );
    }

    // ── Application Passwords ────────────────────────────────────

    public function test_application_passwords_disabled(): void {
        $this->load_module_with( [ 'disable_application_passwords' => true ] );

        $this->assertFalse( apply_filters( 'wp_is_application_passwords_available', true ) );
    }

    // ── User Registration ────────────────────────────────────────

    public function test_user_registration_disabled(): void {
        $this->load_module_with( [ 'disable_user_registration' => true ] );

        $this->assertEquals( 0, apply_filters( 'option_users_can_register', 1 ) );
    }

    // ── Comments ─────────────────────────────────────────────────

    public function test_comments_closed_when_disabled(): void {
        $this->load_module_with( [ 'disable_comments' => true ] );

        $this->assertFalse( apply_filters( 'comments_open', true, 1 ) );
        $this->assertFalse( apply_filters( 'pings_open', true, 1 ) );
    }

    public function test_comments_array_empty_when_disabled(): void {
        $this->load_module_with( [ 'disable_comments' => true ] );

        $result = apply_filters( 'comments_array', [ [ 'comment' ] ], 1 );
        $this->assertEmpty( $result );
    }

    // ── Auto Update UI ───────────────────────────────────────────

    public function test_auto_update_ui_disabled(): void {
        $this->load_module_with( [ 'disable_auto_update_ui' => true ] );

        $this->assertFalse( apply_filters( 'plugins_auto_update_enabled', true ) );
        $this->assertFalse( apply_filters( 'themes_auto_update_enabled', true ) );
    }

    public function test_update_checks_are_short_circuited(): void {
        $this->load_module_with( [ 'disable_updates' => true ] );

        $core = apply_filters( 'pre_site_transient_update_core', false );
        $this->assertInstanceOf( stdClass::class, $core );
        $this->assertSame( [], $core->updates );
        $this->assertNotEmpty( $core->version_checked );

        $plugins = apply_filters( 'pre_site_transient_update_plugins', false );
        $this->assertInstanceOf( stdClass::class, $plugins );
        $this->assertSame( [], $plugins->response );
        $this->assertSame( [], $plugins->no_update );

        $themes = apply_filters( 'pre_site_transient_update_themes', false );
        $this->assertInstanceOf( stdClass::class, $themes );
        $this->assertSame( [], $themes->response );
        $this->assertSame( [], $themes->no_update );
    }

    public function test_automatic_updates_are_disabled(): void {
        $this->load_module_with( [ 'disable_updates' => true ] );

        $this->assertTrue( apply_filters( 'automatic_updater_disabled', false ) );
        $this->assertFalse( apply_filters( 'auto_update_core', true, (object) [] ) );
        $this->assertFalse( apply_filters( 'auto_update_plugin', true, (object) [] ) );
        $this->assertFalse( apply_filters( 'auto_update_theme', true, (object) [] ) );
        $this->assertFalse( apply_filters( 'allow_major_auto_core_updates', true ) );
        $this->assertFalse( apply_filters( 'send_core_update_notification_email', true, (object) [] ) );
    }

    public function test_search_disable_only_affects_the_main_query(): void {
        $this->load_module_with( [ 'disable_search' => true ] );

        $secondary = new WP_Query();
        $secondary->is_search = true;

        do_action_ref_array( 'parse_query', [ &$secondary ] );

        $this->assertTrue( $secondary->is_search );
        $this->assertFalse( $secondary->is_404 );

        global $wp_query, $wp_the_query;

        $previous_query     = $wp_query;
        $previous_the_query = $wp_the_query;

        try {
            $main = new WP_Query();
            $main->is_search = true;
            $wp_query = $main;
            $wp_the_query = $main;

            do_action_ref_array( 'parse_query', [ &$main ] );

            $this->assertFalse( $main->is_search );
            $this->assertTrue( $main->is_404 );
        } finally {
            $wp_query = $previous_query;
            $wp_the_query = $previous_the_query;
        }
    }

    // ── Tagline Lock ─────────────────────────────────────────────

    public function test_tagline_editing_prevented(): void {
        $this->load_module_with( [ 'disable_tagline_editing' => true ] );

        $result = apply_filters( 'pre_update_option_blogdescription', 'new tagline', 'old tagline' );
        $this->assertSame( 'old tagline', $result );
    }

    // ── Permalink Lock ───────────────────────────────────────────

    public function test_permalink_structure_locked(): void {
        $this->load_module_with( [ 'lock_permalink_structure' => true ] );

        $result = apply_filters( 'pre_update_option_permalink_structure', '/%postname%/', '/old/' );
        $this->assertSame( '/old/', $result );
    }

    // ── Helper ───────────────────────────────────────────────────

    /**
     * Load the Features module with specific toggles.
     */
    private function load_module_with( array $features ): void {
        // Override config to return our test features.
        $callback = static function ( array $config ) use ( $features ): array {
            $config['features'] = array_merge( $config['features'] ?? [], $features );
            return $config;
        };
        add_filter( 'wp_governance_config', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_config', $callback, 10 ];

        Config::reset();

        require_once WP_GOVERNANCE_DIR . 'modules/class-features.php';
        new \WP_Governance\Modules\Features( Config::section( 'features' ), Config::get() );
    }

    /**
     * Hooks the Features module can register during tests.
     *
     * @return string[]
     */
    private function governance_hooks(): array {
        return [
            'xmlrpc_enabled',
            'wp_headers',
            'rest_authentication_errors',
            'the_generator',
            'pre_ping',
            'admin_email_check_interval',
            'plugins_auto_update_enabled',
            'themes_auto_update_enabled',
            'pre_site_transient_update_core',
            'pre_site_transient_update_plugins',
            'pre_site_transient_update_themes',
            'pre_set_site_transient_update_core',
            'pre_set_site_transient_update_plugins',
            'pre_set_site_transient_update_themes',
            'automatic_updater_disabled',
            'auto_update_core',
            'auto_update_plugin',
            'auto_update_theme',
            'allow_dev_auto_core_updates',
            'allow_minor_auto_core_updates',
            'allow_major_auto_core_updates',
            'send_core_update_notification_email',
            'comments_open',
            'pings_open',
            'comments_array',
            'use_block_editor_for_post',
            'use_block_editor_for_post_type',
            'wp_is_application_passwords_available',
            'option_users_can_register',
            'admin_menu',
            'admin_init',
            'wp_before_admin_bar_render',
            'widgets_init',
            'parse_query',
            'get_search_form',
            'do_feed',
            'do_feed_rdf',
            'do_feed_rss',
            'do_feed_rss2',
            'do_feed_atom',
            'do_feed_rss2_comments',
            'do_feed_atom_comments',
            'pre_update_option_blogdescription',
            'pre_update_option_permalink_structure',
            'load-plugins.php',
            'load-themes.php',
            'load-update-core.php',
            'load-update.php',
            'wp_maybe_auto_update',
            'init',
        ];
    }
}
