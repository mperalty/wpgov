<?php

namespace GovGuard\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Removes items WordPress injects into the <head>.
 */
class Head_Cleanup {

	/**
	 * @var array
	 * @phpstan-var array<string, mixed>
	 */
	private array $settings;

	/**
	 * @param array $settings Head cleanup settings.
	 * @phpstan-param array<string, mixed> $settings Head cleanup settings.
	 * @psalm-param array $settings Head cleanup settings.
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
		if ( ! empty( $this->settings['remove_rsd_link'] ) ) {
			remove_action( 'wp_head', 'rsd_link' );
		}

		if ( ! empty( $this->settings['remove_wlwmanifest'] ) ) {
			remove_action( 'wp_head', 'wlwmanifest_link' );
		}

		if ( ! empty( $this->settings['remove_shortlink'] ) ) {
			remove_action( 'wp_head', 'wp_shortlink_wp_head' );
			remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		}

		if ( ! empty( $this->settings['remove_feed_links'] ) ) {
			remove_action( 'wp_head', 'feed_links', 2 );
			remove_action( 'wp_head', 'feed_links_extra', 3 );
		}

		if ( ! empty( $this->settings['remove_rest_api_link'] ) ) {
			remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
			remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		}
	}
}
