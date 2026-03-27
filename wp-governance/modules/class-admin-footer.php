<?php

namespace WP_Governance\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Controls admin footer text and branding.
 */
class Admin_Footer {

	private array $settings;

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function __construct( array $settings, array $config ) {
		$this->settings = $settings;
		$this->register();
	}

	private function register(): void {
		// remove_footer takes precedence — skip individual text replacements.
		if ( ! empty( $this->settings['remove_footer'] ) ) {
			add_filter( 'admin_footer_text', '__return_empty_string', 999 );
			add_filter( 'update_footer', '__return_empty_string', 999 );
			return;
		}

		if ( isset( $this->settings['left_text'] ) ) {
			$text = $this->settings['left_text'];
			add_filter(
				'admin_footer_text',
				static function () use ( $text ): string {
					return wp_kses_post( $text );
				},
				999
			);
		}

		if ( isset( $this->settings['right_text'] ) ) {
			$text = $this->settings['right_text'];
			add_filter(
				'update_footer',
				static function () use ( $text ): string {
					return wp_kses_post( $text );
				},
				999
			);
		}
	}
}
