<?php

use WP_Governance\Config;

/**
 * Tests for the Locked_Options module.
 */
class LockedOptionsTest extends WP_UnitTestCase {

    /**
     * Option names used in tests — cleaned up after each test.
     *
     * @var string[]
     */
    private array $test_options = array(
        'date_format',
        'posts_per_page',
        'blogdescription',
        'timezone_string',
        'start_of_week',
    );

    public function setUp(): void {
        parent::setUp();
        Config::reset();
    }

    public function tearDown(): void {
        foreach ( $this->test_options as $opt ) {
            remove_all_filters( "pre_option_{$opt}" );
            remove_all_filters( "pre_update_option_{$opt}" );
        }
        remove_all_actions( 'admin_notices' );
        remove_all_actions( 'admin_footer' );

        Config::reset();
        parent::tearDown();
    }

    // ── Read override ───────────────────────────────────────────

    public function test_locked_option_overrides_database(): void {
        $this->load_module( array( 'date_format' => 'Y-m-d' ) );

        $this->assertSame( 'Y-m-d', get_option( 'date_format' ) );
    }

    public function test_multiple_options_locked(): void {
        $this->load_module( array(
            'date_format'     => 'Y-m-d',
            'posts_per_page'  => 25,
            'blogdescription' => 'Locked Tagline',
        ) );

        $this->assertSame( 'Y-m-d', get_option( 'date_format' ) );
        $this->assertSame( 25, get_option( 'posts_per_page' ) );
        $this->assertSame( 'Locked Tagline', get_option( 'blogdescription' ) );
    }

    public function test_unlocked_options_unaffected(): void {
        $this->load_module( array( 'date_format' => 'Y-m-d' ) );

        // timezone_string is not locked, so it should come from the database.
        $this->assertNotSame( 'Y-m-d', get_option( 'timezone_string' ) );
    }

    // ── Write prevention ────────────────────────────────────────

    public function test_locked_option_rejects_writes(): void {
        $this->load_module( array( 'posts_per_page' => 25 ) );

        $result = apply_filters( 'pre_update_option_posts_per_page', 50, 25 );
        $this->assertSame( 25, $result );
    }

    public function test_locked_option_rejects_writes_with_different_old(): void {
        $this->load_module( array( 'start_of_week' => 1 ) );

        // Even when old_value differs, the filter returns old_value (prevents the write).
        $result = apply_filters( 'pre_update_option_start_of_week', 0, 3 );
        $this->assertSame( 3, $result );
    }

    // ── Integer and string values ───────────────────────────────

    public function test_integer_value_preserved(): void {
        $this->load_module( array( 'posts_per_page' => 15 ) );

        $this->assertSame( 15, get_option( 'posts_per_page' ) );
    }

    public function test_zero_value_locks_correctly(): void {
        $this->load_module( array( 'start_of_week' => 0 ) );

        // 0 is not false, so pre_option_ should return it.
        $this->assertSame( 0, get_option( 'start_of_week' ) );
    }

    public function test_empty_string_locks_correctly(): void {
        $this->load_module( array( 'blogdescription' => '' ) );

        $this->assertSame( '', get_option( 'blogdescription' ) );
    }

    // ── Admin hooks registered ──────────────────────────────────

    public function test_admin_hooks_registered_in_admin_context(): void {
        // Simulate admin context.
        set_current_screen( 'options-general' );

        $this->load_module( array( 'date_format' => 'Y-m-d' ) );

        $this->assertNotFalse( has_action( 'admin_notices' ) );
        $this->assertNotFalse( has_action( 'admin_footer' ) );
    }

    // ── Helper ──────────────────────────────────────────────────

    private function load_module( array $options ): void {
        require_once WP_GOVERNANCE_DIR . 'modules/class-locked-options.php';
        new \WP_Governance\Modules\Locked_Options( $options, Config::get() );
    }
}
