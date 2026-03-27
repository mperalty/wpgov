<?php

use WP_Governance\Config;

/**
 * Tests for the Post Types module.
 */
class PostTypesTest extends WP_UnitTestCase {

    /**
     * @var array<array{string, callable, int}>
     */
    private array $filters_to_remove = [];

    public function setUp(): void {
        parent::setUp();
        Config::reset();

        remove_action( 'admin_init', 'wp_admin_headers' );
        remove_action( 'admin_init', 'send_frame_options_header', 10 );
        remove_action( 'admin_init', array( 'WP_Privacy_Policy_Content', 'add_suggested_content' ), 1 );
        remove_action( 'admin_init', array( 'WP_Privacy_Policy_Content', 'text_change_check' ), 100 );
    }

    public function tearDown(): void {
        foreach ( $this->filters_to_remove as [ $tag, $callback, $priority ] ) {
            remove_filter( $tag, $callback, $priority );
        }
        $this->filters_to_remove = [];

        // Reset any superglobal changes.
        unset( $_GET['post_type'], $_GET['post'] );

        add_action( 'admin_init', 'wp_admin_headers' );
        add_action( 'admin_init', 'send_frame_options_header', 10, 0 );
        add_action( 'admin_init', array( 'WP_Privacy_Policy_Content', 'add_suggested_content' ), 1 );
        add_action( 'admin_init', array( 'WP_Privacy_Policy_Content', 'text_change_check' ), 100 );

        Config::reset();
        parent::tearDown();
    }

    public function test_disable_supports_removes_features(): void {
        $settings = [
            'hidden'           => [],
            'disable_supports' => [
                'post' => [ 'comments', 'trackbacks' ],
            ],
        ];

        require_once WP_GOVERNANCE_DIR . 'modules/class-post-types.php';
        new \WP_Governance\Modules\Post_Types( $settings, Config::get() );

        // Fire init to trigger the support removal.
        do_action( 'init' );

        $this->assertFalse( post_type_supports( 'post', 'comments' ) );
        $this->assertFalse( post_type_supports( 'post', 'trackbacks' ) );
        // Other supports should remain.
        $this->assertTrue( post_type_supports( 'post', 'title' ) );
        $this->assertTrue( post_type_supports( 'post', 'editor' ) );
    }

    public function test_empty_settings_registers_nothing(): void {
        $settings = [
            'hidden'           => [],
            'disable_supports' => [],
        ];

        require_once WP_GOVERNANCE_DIR . 'modules/class-post-types.php';
        new \WP_Governance\Modules\Post_Types( $settings, Config::get() );

        // Post should still support everything.
        $this->assertTrue( post_type_supports( 'post', 'title' ) );
        $this->assertTrue( post_type_supports( 'post', 'editor' ) );
    }

    public function test_hidden_post_type_resolved_from_get_post_type_param(): void {
        $settings = [
            'hidden'           => [ 'page' ],
            'disable_supports' => [],
        ];

        require_once WP_GOVERNANCE_DIR . 'modules/class-post-types.php';
        new \WP_Governance\Modules\Post_Types( $settings, Config::get() );

        // Set up a restricted user.
        $editor_id = self::factory()->user->create( [ 'role' => 'editor' ] );
        wp_set_current_user( $editor_id );

        // Simulate navigating to edit.php?post_type=page.
        global $pagenow, $typenow;
        $pagenow = 'edit.php';
        $typenow = '';
        $_GET['post_type'] = 'page';

        // The admin_init action should wp_die — catch it.
        $blocked = false;
        add_filter( 'wp_die_handler', static function () use ( &$blocked ) {
            return static function () use ( &$blocked ): void {
                $blocked = true;
            };
        } );

        do_action( 'admin_init' );

        $this->assertTrue( $blocked, 'Access to hidden post type via $_GET[post_type] should be blocked.' );
    }

    public function test_hidden_post_type_resolved_from_get_post_id(): void {
        $settings = [
            'hidden'           => [ 'page' ],
            'disable_supports' => [],
        ];

        require_once WP_GOVERNANCE_DIR . 'modules/class-post-types.php';
        new \WP_Governance\Modules\Post_Types( $settings, Config::get() );

        // Create a page so we can reference it by ID.
        $page_id = self::factory()->post->create( [ 'post_type' => 'page' ] );

        // Set up a restricted user.
        $editor_id = self::factory()->user->create( [ 'role' => 'editor' ] );
        wp_set_current_user( $editor_id );

        // Simulate navigating to post.php?post=123 (editing a page by ID).
        global $pagenow, $typenow;
        $pagenow = 'post.php';
        $typenow = '';
        unset( $_GET['post_type'] );
        $_GET['post'] = (string) $page_id;

        $blocked = false;
        add_filter( 'wp_die_handler', static function () use ( &$blocked ) {
            return static function () use ( &$blocked ): void {
                $blocked = true;
            };
        } );

        do_action( 'admin_init' );

        $this->assertTrue( $blocked, 'Access to hidden post type via $_GET[post] ID should be blocked.' );
    }

    public function test_unrestricted_user_can_access_hidden_post_types(): void {
        $settings = [
            'hidden'           => [ 'page' ],
            'disable_supports' => [],
        ];

        require_once WP_GOVERNANCE_DIR . 'modules/class-post-types.php';
        new \WP_Governance\Modules\Post_Types( $settings, Config::get() );

        // Admin is unrestricted.
        $admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $admin_id );

        global $pagenow, $typenow;
        $pagenow = 'edit.php';
        $typenow = '';
        $_GET['post_type'] = 'page';

        $blocked = false;
        add_filter( 'wp_die_handler', static function () use ( &$blocked ) {
            return static function () use ( &$blocked ): void {
                $blocked = true;
            };
        } );

        do_action( 'admin_init' );

        $this->assertFalse( $blocked, 'Unrestricted users should not be blocked from hidden post types.' );
    }
}
