<?php

namespace WP_Governance\Modules;

use WP_Governance\Config;

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
			add_filter(
				'admin_footer_text',
				static function ( string $text ): string {
					return Config::current_user_is_unrestricted() ? $text : '';
				},
				999
			);
			add_filter(
				'update_footer',
				static function ( string $text ): string {
					return Config::current_user_is_unrestricted() ? $text : '';
				},
				999
			);
			return;
		}

		if ( isset( $this->settings['left_text'] ) ) {
			$text = $this->settings['left_text'];
			add_filter(
				'admin_footer_text',
				static function ( string $original ) use ( $text ): string {
					return Config::current_user_is_unrestricted() ? $original : wp_kses_post( $text );
				},
				999
			);
		}

		if ( isset( $this->settings['right_text'] ) ) {
			$text = $this->settings['right_text'];
			add_filter(
				'update_footer',
				static function ( string $original ) use ( $text ): string {
					return Config::current_user_is_unrestricted() ? $original : wp_kses_post( $text );
				},
				999
			);
		}
	}
}
