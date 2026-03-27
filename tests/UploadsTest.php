<?php

use WP_Governance\Config;

/**
 * Tests for the Uploads module.
 */
class UploadsTest extends WP_UnitTestCase {

    /**
     * Tracks filter callbacks added during each test so they can be cleaned up.
     *
     * @var array<array{string, callable, int}>
     */
    private array $filters_to_remove = [];

    public function setUp(): void {
        parent::setUp();
        wp_set_current_user( 0 );
        Config::reset();
    }

    public function tearDown(): void {
        foreach ( $this->filters_to_remove as [ $tag, $callback, $priority ] ) {
            remove_filter( $tag, $callback, $priority );
        }
        $this->filters_to_remove = [];

        foreach ( $this->governance_hooks() as $hook ) {
            remove_all_filters( $hook );
        }

        wp_set_current_user( 0 );
        Config::reset();
        parent::tearDown();
    }

    public function test_restricts_mime_types_when_configured(): void {
        $allowed = [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
        ];

        $this->load_module( $allowed );

        $result = apply_filters( 'upload_mimes', wp_get_mime_types() );

        $this->assertArrayHasKey( 'jpg|jpeg|jpe', $result );
        $this->assertArrayHasKey( 'png', $result );
        // Original types like .gif should be gone.
        $this->assertArrayNotHasKey( 'gif', $result );
    }

    public function test_preserves_defaults_when_empty(): void {
        $this->load_module( [] );

        $defaults = wp_get_mime_types();
        $result   = apply_filters( 'upload_mimes', $defaults );

        // When empty, filter is not registered — defaults pass through.
        $this->assertSame( $defaults, $result );
    }

    public function test_wp_governance_allowed_mimes_filter(): void {
        $allowed = [
            'jpg|jpeg|jpe' => 'image/jpeg',
        ];

        $this->load_module( $allowed );

        $callback = static function ( array $mimes ): array {
            $mimes['svg'] = 'image/svg+xml';
            return $mimes;
        };
        add_filter( 'wp_governance_allowed_mimes', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_allowed_mimes', $callback, 10 ];

        $result = apply_filters( 'upload_mimes', wp_get_mime_types() );

        $this->assertArrayHasKey( 'svg', $result );
        $this->assertArrayHasKey( 'jpg|jpeg|jpe', $result );
    }

    public function test_file_type_validation_rejects_disallowed_extensions(): void {
        $allowed = [
            'jpg|jpeg|jpe' => 'image/jpeg',
        ];

        $this->load_module( $allowed );

        $result = apply_filters(
            'wp_check_filetype_and_ext',
            [
                'ext'             => 'gif',
                'type'            => 'image/gif',
                'proper_filename' => false,
            ],
            'C:\\temp\\image.gif',
            'image.gif',
            $allowed,
            false
        );

        $this->assertFalse( $result['ext'] );
        $this->assertFalse( $result['type'] );
    }

    public function test_file_type_validation_allows_configured_extensions(): void {
        $allowed = [
            'jpg|jpeg|jpe' => 'image/jpeg',
        ];

        $this->load_module( $allowed );

        $result = apply_filters(
            'wp_check_filetype_and_ext',
            [
                'ext'             => 'jpg',
                'type'            => 'image/jpeg',
                'proper_filename' => false,
            ],
            'C:\\temp\\image.jpg',
            'image.jpg',
            $allowed,
            false
        );

        $this->assertSame( 'jpg', $result['ext'] );
        $this->assertSame( 'image/jpeg', $result['type'] );
    }

    public function test_upload_size_limit_is_reduced_when_configured(): void {
        $this->load_module( [], [ 'max_upload_size_mb' => 2 ] );

        $result = apply_filters( 'upload_size_limit', 10 * MB_IN_BYTES );

        $this->assertSame( 2 * MB_IN_BYTES, $result );
    }

    public function test_allowed_mime_lookup_is_cached_per_request(): void {
        $allowed = [
            'jpg|jpeg|jpe' => 'image/jpeg',
        ];

        $this->load_module( $allowed );

        $calls = 0;
        $callback = static function ( array $mimes ) use ( &$calls ): array {
            ++$calls;
            return $mimes;
        };
        add_filter( 'wp_governance_allowed_mimes', $callback );
        $this->filters_to_remove[] = [ 'wp_governance_allowed_mimes', $callback, 10 ];

        $payload = [
            'ext'             => 'jpg',
            'type'            => 'image/jpeg',
            'proper_filename' => false,
        ];

        apply_filters( 'wp_check_filetype_and_ext', $payload, 'C:\\temp\\image.jpg', 'image.jpg', $allowed, false );
        apply_filters( 'wp_check_filetype_and_ext', $payload, 'C:\\temp\\image.jpg', 'image.jpg', $allowed, false );

        $this->assertSame( 1, $calls );
    }

    public function test_oversized_uploads_are_rejected(): void {
        $this->load_module( [], [ 'max_upload_size_mb' => 2 ] );

        $result = apply_filters(
            'wp_handle_upload_prefilter',
            [
                'name'  => 'large.pdf',
                'type'  => 'application/pdf',
                'tmp_name' => 'C:\\temp\\large.pdf',
                'error' => '',
                'size'  => 3 * MB_IN_BYTES,
            ]
        );

        $this->assertNotEmpty( $result['error'] );
        $this->assertStringContainsString( '2', $result['error'] );
    }

    public function test_unrestricted_users_bypass_mime_restrictions(): void {
        $allowed = [
            'jpg|jpeg|jpe' => 'image/jpeg',
        ];

        $user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $user_id );

        $this->load_module( $allowed );

        $result = apply_filters( 'upload_mimes', wp_get_mime_types() );

        $this->assertArrayHasKey( 'gif', $result );
    }

    public function test_unrestricted_users_bypass_upload_size_limits(): void {
        $user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $user_id );

        $this->load_module( [], [ 'max_upload_size_mb' => 2 ] );

        $result = apply_filters( 'upload_size_limit', 10 * MB_IN_BYTES );

        $this->assertSame( 10 * MB_IN_BYTES, $result );
    }

    private function load_module( array $allowed, array $uploads = [] ): void {
        require_once WP_GOVERNANCE_DIR . 'modules/class-uploads.php';
        new \WP_Governance\Modules\Uploads(
            $allowed,
            array_merge(
                Config::get(),
                [
                    'uploads' => $uploads,
                ]
            )
        );
    }

    /**
     * Filters the Uploads module can register during tests.
     *
     * @return string[]
     */
    private function governance_hooks(): array {
        return [
            'upload_mimes',
            'wp_governance_allowed_mimes',
            'wp_check_filetype_and_ext',
            'upload_size_limit',
            'wp_handle_upload_prefilter',
            'wp_handle_sideload_prefilter',
        ];
    }
}
