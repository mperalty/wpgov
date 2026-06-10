<?php

use GovGuard\Config;

/**
 * Tests for the Content module.
 */
class ContentTest extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        Config::reset();
    }

    public function tearDown(): void {
        remove_all_filters( 'wp_revisions_to_keep' );
        remove_all_filters( 'tiny_mce_plugins' );
        remove_all_filters( 'wp_resource_hints' );
        remove_all_filters( 'block_editor_settings_all' );
        remove_all_actions( 'admin_enqueue_scripts' );

        Config::reset();
        parent::tearDown();
    }

    public function test_revisions_disabled_keeps_zero_revisions(): void {
        $settings = [
            'disable_revisions' => true,
        ];

        $this->load_module($settings);

        $limit = apply_filters('wp_revisions_to_keep', 10, new stdClass());
        $this->assertSame(0, $limit);
    }

    public function test_autosave_interval_applied_to_block_editor(): void {
        $settings = [
            'autosave_interval' => 120,
        ];

        $this->load_module($settings);

        $editor_settings = apply_filters('block_editor_settings_all', [], null);
        $this->assertSame(120, $editor_settings['autosaveInterval']);
    }

    public function test_autosave_interval_overrides_classic_editor_setting(): void {
        $settings = [
            'autosave_interval' => 120,
        ];

        $this->load_module($settings);

        set_current_screen('post');
        do_action('admin_enqueue_scripts', 'post.php');

        $inline = wp_scripts()->get_data('autosave', 'before');
        $this->assertNotEmpty($inline);
        $this->assertStringContainsString(
            'autosaveInterval = 120',
            implode('', array_filter((array) $inline, 'is_string'))
        );

        // Clean up the inline data attached to the shared core handle.
        wp_scripts()->add_data('autosave', 'before', []);
    }

    public function test_revision_limit_enforced(): void {
        $settings = [
            'disable_revisions' => false,
            'revision_limit'    => 3,
        ];

        $this->load_module($settings);

        $limit = apply_filters('wp_revisions_to_keep', 10, new stdClass());
        $this->assertSame(3, $limit);
    }

    public function test_emojis_disabled(): void {
        $settings = [
            'disable_emojis' => true,
        ];

        $this->load_module($settings);

        // The emoji TinyMCE plugin should be removed.
        $plugins = apply_filters('tiny_mce_plugins', ['wpemoji', 'wordpress', 'lists']);
        $this->assertNotContains('wpemoji', $plugins);
        $this->assertContains('wordpress', $plugins);
    }

    public function test_emoji_dns_prefetch_removed(): void {
        $settings = [
            'disable_emojis' => true,
        ];

        $this->load_module($settings);

        $urls   = ['https://s.w.org/images/core/emoji/14.0.0/svg/', 'https://example.com'];
        $result = apply_filters('wp_resource_hints', $urls, 'dns-prefetch');

        // The emoji URL should be removed.
        foreach ($result as $url) {
            $this->assertStringNotContainsString('wp-emoji-release', $url);
        }
    }

    public function test_no_enforcement_when_all_off(): void {
        $settings = [
            'disable_revisions' => false,
            'revision_limit'    => null,
            'disable_autosave'  => false,
            'disable_embeds'    => false,
            'disable_emojis'    => false,
        ];

        $this->load_module($settings);

        // Revisions should keep the passed-in value.
        $limit = apply_filters('wp_revisions_to_keep', 10, new stdClass());
        $this->assertSame(10, $limit);

        // TinyMCE should keep wpemoji.
        $plugins = apply_filters('tiny_mce_plugins', ['wpemoji', 'wordpress']);
        $this->assertContains('wpemoji', $plugins);
    }

    private function load_module(array $settings): void {
        require_once GOVGUARD_DIR . 'modules/class-content.php';
        new \GovGuard\Modules\Content($settings, Config::get());
    }
}
