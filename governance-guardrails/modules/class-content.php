<?php

namespace GovGuard\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Content-related restrictions: revisions, autosave, embeds, emojis.
 */
class Content {

	/**
	 * @var array
	 * @phpstan-var array<string, mixed>
	 */
	private array $settings;

	/**
	 * @param array $settings Content settings.
	 * @phpstan-param array<string, mixed> $settings Content settings.
	 * @psalm-param array $settings Content settings.
	 * @param array $config Governance config.
	 * @phpstan-param array<string, mixed> $config Governance config.
	 * @psalm-param array $config Governance config.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function __construct( array $settings, array $config ) {
		$this->settings = $settings;
		$this->register();
	}

	private function register(): void {
		$this->handle_revisions();
		$this->handle_autosave();
		$this->handle_embeds();
		$this->handle_emojis();
	}

	private function handle_revisions(): void {
		if ( ! empty( $this->settings['disable_revisions'] ) ) {
			// Keep zero revisions for every post type. Equivalent to setting
			// WP_POST_REVISIONS to false, but scoped to this plugin instead
			// of defining a global constant.
			add_filter( 'wp_revisions_to_keep', '__return_zero', 999 );
			return;
		}

		if ( array_key_exists( 'revision_limit', $this->settings ) && null !== $this->settings['revision_limit'] ) {
			$limit = max( 0, (int) $this->settings['revision_limit'] );
			add_filter(
				'wp_revisions_to_keep',
				static function () use ( $limit ): int {
					return $limit;
				}
			);
		}
	}

	private function handle_autosave(): void {
		if ( ! empty( $this->settings['disable_autosave'] ) ) {
			add_action(
				'admin_init',
				static function (): void {
					wp_deregister_script( 'autosave' );
				}
			);
			return;
		}

		if ( ! empty( $this->settings['autosave_interval'] ) ) {
			$interval = max( 1, (int) $this->settings['autosave_interval'] );

			// The block editor reads the interval from its editor settings.
			add_filter(
				'block_editor_settings_all',
				static function ( array $editor_settings ) use ( $interval ): array {
					$editor_settings['autosaveInterval'] = $interval;
					return $editor_settings;
				}
			);

			// The classic editor reads autosaveL10n.autosaveInterval, which is
			// printed alongside the core autosave script. Override the value
			// after the settings are printed but before the script executes.
			add_action(
				'admin_enqueue_scripts',
				static function () use ( $interval ): void {
					wp_add_inline_script(
						'autosave',
						sprintf( 'if ( window.autosaveL10n ) { window.autosaveL10n.autosaveInterval = %d; }', $interval ),
						'before'
					);
				}
			);
		}
	}

	private function handle_embeds(): void {
		if ( empty( $this->settings['disable_embeds'] ) ) {
			return;
		}

		add_action(
			'init',
			static function (): void {
				// Remove the oEmbed discovery links.
				remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
				remove_action( 'wp_head', 'wp_oembed_add_host_js' );

				// Remove the oEmbed route.
				remove_action( 'rest_api_init', 'wp_oembed_register_route' );

				// Disable oEmbed auto-discovery.
				add_filter( 'embed_oembed_discover', '__return_false' );

				// Remove oEmbed-related JavaScript.
				wp_deregister_script( 'wp-embed' );
			},
			9999
		);
	}

	private function handle_emojis(): void {
		if ( empty( $this->settings['disable_emojis'] ) ) {
			return;
		}

		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		add_filter(
			'tiny_mce_plugins',
			static function ( array $plugins ): array {
				return array_diff( $plugins, array( 'wpemoji' ) );
			}
		);

		add_filter(
			'wp_resource_hints',
			static function ( array $urls, string $relation_type ): array {
				if ( 'dns-prefetch' === $relation_type ) {
					$urls = array_filter(
						$urls,
						static function ( $url ): bool {
							return ! is_string( $url ) || ! str_contains( $url, 'wp-emoji-release' );
						}
					);
				}
				return $urls;
			},
			10,
			2
		);
	}
}
