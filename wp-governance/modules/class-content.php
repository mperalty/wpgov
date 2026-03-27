<?php

namespace WP_Governance\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Content-related restrictions: revisions, autosave, embeds, emojis.
 */
class Content {

	private array $settings;

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
			if ( ! defined( 'WP_POST_REVISIONS' ) ) {
				define( 'WP_POST_REVISIONS', false );
			}
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
			$interval = (int) $this->settings['autosave_interval'];
			if ( ! defined( 'AUTOSAVE_INTERVAL' ) ) {
				define( 'AUTOSAVE_INTERVAL', $interval );
			}
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
