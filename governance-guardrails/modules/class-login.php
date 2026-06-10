<?php

namespace GovGuard\Modules;

use GovGuard\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Enforces login and authentication restrictions.
 */
class Login {

	/**
	 * @var array
	 * @phpstan-var array<string, mixed>
	 */
	private array $settings;

	/**
	 * @param array $settings Login settings.
	 * @phpstan-param array<string, mixed> $settings Login settings.
	 * @psalm-param array $settings Login settings.
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
		if ( ! empty( $this->settings['disable_password_reset'] ) ) {
			add_filter( 'allow_password_reset', array( $this, 'filter_allow_password_reset' ), 10, 2 );
			add_filter(
				'show_password_fields',
				static function ( bool $show ): bool {
					return Config::current_user_is_unrestricted() ? $show : false;
				}
			);

			// Hide the "Lost your password?" link.
			add_action(
				'login_enqueue_scripts',
				static function (): void {
					wp_register_style( 'govguard-login', false, array(), GOVGUARD_VERSION );
					wp_enqueue_style( 'govguard-login' );
					wp_add_inline_style( 'govguard-login', '#login #nav a[href*="lostpassword"] { display: none; }' );
				}
			);
		}

		if ( ! empty( $this->settings['hide_login_errors'] ) ) {
			add_filter(
				'login_errors',
				static function (): string {
					return __( 'Invalid credentials.', 'governance-guardrails' );
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

	/**
	 * Disable password resets for governed users while preserving them for unrestricted accounts.
	 *
	 * @param bool     $allow   Whether WordPress currently allows the reset.
	 * @param int|bool $user_id Resolved user ID when available.
	 * @return bool
	 */
	public function filter_allow_password_reset( bool $allow, $user_id = false ): bool {
		if ( ! $allow ) {
			return false;
		}

		if ( ! is_int( $user_id ) || $user_id <= 0 ) {
			return false;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		return Config::user_is_unrestricted( $user );
	}
}
