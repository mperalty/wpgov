<?php

namespace WP_Governance\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Security hardening rules.
 *
 * Adds HTTP security headers, disables directory browsing hints,
 * and other hardening measures.
 */
class Security {

	private array $settings;

	/** @var bool Whether to add X-Robots-Tag noindex header. */
	private bool $add_noindex = false;

	/** @var bool Whether to remove the X-Pingback header. */
	private bool $remove_pingback = false;

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function __construct( array $settings, array $config ) {
		$this->settings        = $settings;
		$this->add_noindex     = ! empty( $settings['add_noindex_headers'] );
		$this->remove_pingback = ! empty( $settings['remove_pingback_header'] );
		$this->register();
	}

	private function register(): void {
		// Single consolidated wp_headers filter for custom headers, noindex, and pingback removal.
		$needs_header_filter = ! empty( $this->settings['headers'] ) || $this->add_noindex || $this->remove_pingback;
		if ( $needs_header_filter ) {
			add_filter( 'wp_headers', array( $this, 'filter_headers' ), 999 );
		}

		// Disable author archive enumeration (prevents ?author=1 user discovery).
		if ( ! empty( $this->settings['disable_author_archives'] ) ) {
			add_action(
				'template_redirect',
				static function (): void {
					if ( is_author() ) {
						wp_safe_redirect( home_url(), 301 );
						exit;
					}
				}
			);

			// Block REST user enumeration for unauthenticated users.
			add_filter(
				'rest_endpoints',
				static function ( array $endpoints ): array {
					if ( ! is_user_logged_in() ) {
						unset( $endpoints['/wp/v2/users'] );
						unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
					}
					return $endpoints;
				}
			);
		}

		// Disable file editing (theme + plugin editors) — belt-and-suspenders with Features module.
		if ( ! empty( $this->settings['disable_file_editing'] ) ) {
			if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
				define( 'DISALLOW_FILE_EDIT', true );
			}
		}

		// Remove version query strings from enqueued scripts/styles.
		if ( ! empty( $this->settings['hide_wp_version_from_scripts'] ) ) {
			add_filter( 'style_loader_src', array( __CLASS__, 'strip_version_query' ), 999 );
			add_filter( 'script_loader_src', array( __CLASS__, 'strip_version_query' ), 999 );
		}
	}

	/**
	 * Consolidated wp_headers filter: merge custom headers, add noindex, remove pingback.
	 *
	 * @param array $headers Current WordPress headers.
	 * @return array
	 */
	public function filter_headers( array $headers ): array {
		// Custom security headers.
		foreach ( (array) ( $this->settings['headers'] ?? array() ) as $name => $value ) {
			$name  = $this->sanitize_header_name( $name );
			$value = $this->sanitize_header_value( $value );

			if ( '' === $name || '' === $value ) {
				continue;
			}

			$headers[ $name ] = $value;
		}

		// Noindex header.
		if ( $this->add_noindex ) {
			$headers['X-Robots-Tag'] = 'noindex, nofollow';
		}

		// Remove pingback header.
		if ( $this->remove_pingback ) {
			unset( $headers['X-Pingback'] );
		}

		return $headers;
	}

	/**
	 * Remove version query strings from enqueued script/style URLs.
	 */
	public static function strip_version_query( string $src ): string {
		if ( str_contains( $src, '?ver=' ) || str_contains( $src, '&ver=' ) ) {
			$src = remove_query_arg( 'ver', $src );
		}
		return $src;
	}

	/**
	 * Normalize a header name to a safe HTTP token.
	 *
	 * @param mixed $name Header name.
	 * @return string
	 */
	private function sanitize_header_name( $name ): string {
		$name = trim( (string) $name );

		if ( 1 !== preg_match( '/^[A-Za-z][A-Za-z0-9-]*$/', $name ) ) {
			return '';
		}

		return $name;
	}

	/**
	 * Strip CRLF characters from a header value.
	 *
	 * @param mixed $value Header value.
	 * @return string
	 */
	private function sanitize_header_value( $value ): string {
		return trim( (string) preg_replace( '/[\r\n]+/', ' ', (string) $value ) );
	}
}
