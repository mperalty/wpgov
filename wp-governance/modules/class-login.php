<?php

namespace WP_Governance\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Enforces login and authentication restrictions.
 */
class Login {

	private array $settings;

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function __construct( array $settings, array $config ) {
		$this->settings = $settings;
		$this->register();
	}

	private function register(): void {
		if ( ! empty( $this->settings['disable_password_reset'] ) ) {
			add_filter( 'allow_password_reset', '__return_false' );
			add_filter( 'show_password_fields', '__return_false' );
		}

		if ( ! empty( $this->settings['hide_login_errors'] ) ) {
			add_filter(
				'login_errors',
				static function (): string {
					return __( 'Invalid credentials.' );
				}
			);
		}

		if ( ! empty( $this->settings['redirect_after_logout'] ) ) {
			$url = $this->settings['redirect_after_logout'];
			add_filter(
				'logout_redirect',
				static function ( string $redirect_to ) use ( $url ): string {
					$target = (string) $url;

					if ( str_starts_with( $target, '/' ) ) {
						$target = home_url( $target );
					}

					$fallback = '' !== $redirect_to ? $redirect_to : home_url( '/' );

					return wp_validate_redirect( $target, $fallback );
				},
				10,
				1
			);
		}
	}
}
