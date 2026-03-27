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
		// Front-end headers go through wp_headers. Admin, login, AJAX, and REST
		// need separate hooks because they do not consistently use that filter.
		$needs_header_filter = ! empty( $this->settings['headers'] ) || $this->add_noindex || $this->remove_pingback;
		if ( $needs_header_filter ) {
			add_filter( 'wp_headers', array( $this, 'filter_headers' ), 999 );
			add_action( 'admin_init', array( $this, 'send_direct_headers' ), 0 );
			add_action( 'login_init', array( $this, 'send_direct_headers' ), 0 );
			add_filter( 'rest_post_dispatch', array( $this, 'filter_rest_response_headers' ), 999 );
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
		return $this->apply_header_policy( $headers );
	}

	/**
	 * Remove version query strings from enqueued script/style URLs.
	 */
	public static function strip_version_query( string $src ): string {
		$query = wp_parse_url( $src, PHP_URL_QUERY );
		if ( ! is_string( $query ) || '' === $query ) {
			return $src;
		}

		parse_str( $query, $args );
		$version = (string) ( $args['ver'] ?? '' );

		if ( '' !== $version && $version === self::current_wp_version() ) {
			$src = remove_query_arg( 'ver', $src );
		}

		return $src;
	}

	/**
	 * Send configured headers directly for response types that bypass wp_headers.
	 */
	public function send_direct_headers(): void {
		foreach ( $this->build_header_map() as $name => $value ) {
			header( sprintf( '%s: %s', $name, $value ), true );
		}

		if ( $this->remove_pingback && function_exists( 'header_remove' ) ) {
			header_remove( 'X-Pingback' );
		}
	}

	/**
	 * Add configured headers to REST responses.
	 *
	 * @param \WP_HTTP_Response $response Current REST response.
	 * @return \WP_HTTP_Response
	 */
	public function filter_rest_response_headers( \WP_HTTP_Response $response ): \WP_HTTP_Response {
		foreach ( $this->build_header_map() as $name => $value ) {
			$response->header( $name, $value );
		}

		if ( $this->remove_pingback ) {
			$headers = $response->get_headers();
			unset( $headers['X-Pingback'] );
			$response->set_headers( $headers );
		}

		return $response;
	}

	/**
	 * Apply the configured header policy to a header map.
	 *
	 * @param array $headers Existing headers.
	 * @return array
	 */
	private function apply_header_policy( array $headers ): array {
		foreach ( $this->build_header_map() as $name => $value ) {
			$headers[ $name ] = $value;
		}

		if ( $this->remove_pingback ) {
			unset( $headers['X-Pingback'] );
		}

		return $headers;
	}

	/**
	 * Build the sanitized header map configured for this site.
	 *
	 * @return array<string, string>
	 */
	private function build_header_map(): array {
		$headers = array();

		foreach ( (array) ( $this->settings['headers'] ?? array() ) as $name => $value ) {
			$name  = $this->sanitize_header_name( $name );
			$value = $this->sanitize_header_value( $value );

			if ( '' === $name || '' === $value ) {
				continue;
			}

			$headers[ $name ] = $value;
		}

		if ( $this->add_noindex ) {
			$headers['X-Robots-Tag'] = 'noindex, nofollow';
		}

		return $headers;
	}

	/**
	 * Get the current WordPress version used for core asset cache busting.
	 */
	private static function current_wp_version(): string {
		global $wp_version;

		if ( is_string( $wp_version ) && '' !== $wp_version ) {
			return $wp_version;
		}

		return get_bloginfo( 'version' );
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
