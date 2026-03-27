<?php

namespace WP_Governance\Modules;

use WP_Governance\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Removes admin menu and submenu pages for restricted users.
 * Also blocks direct URL access to those pages.
 */
class Admin_Menu {

	private array $slugs;

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function __construct( array $slugs, array $config ) {
		$this->slugs = $slugs;
		$this->register();
	}

	private function register(): void {
		/**
		 * Filter the list of menu slugs to restrict.
		 *
		 * @param array $slugs Menu page slugs.
		 */
		$this->slugs = apply_filters( 'wp_governance_restrict_menu', $this->slugs );

		// Remove menu items at a late priority so other plugins have registered theirs.
		add_action( 'admin_menu', array( $this, 'remove_menus' ), 999 );

		// Block direct URL access.
		add_action( 'admin_init', array( $this, 'block_direct_access' ) );
	}

	/**
	 * Remove menu and submenu pages.
	 */
	public function remove_menus(): void {
		if ( Config::current_user_is_unrestricted() ) {
			return;
		}

		foreach ( $this->slugs as $slug ) {
			remove_menu_page( $slug );

			// If the slug contains a query string it might be a submenu.
			// Try removing it from common parent menus as well.
			if ( str_contains( $slug, '?' ) || str_contains( $slug, '.php' ) ) {
				$this->remove_from_all_parents( $slug );
			}
		}
	}

	/**
	 * Block direct navigation to restricted admin pages.
	 */
	public function block_direct_access(): void {
		if ( Config::current_user_is_unrestricted() ) {
			return;
		}

		foreach ( $this->slugs as $slug ) {
			if ( $this->request_matches_slug( $slug ) ) {
				wp_die(
					esc_html__( 'You do not have permission to access this page.' ),
					esc_html__( 'Restricted' ),
					array(
						'response'  => 403,
						'back_link' => true,
					)
				);
			}
		}
	}

	/**
	 * Check whether the current admin request matches a restricted slug.
	 *
	 * Query-string slugs are matched by page and required query arguments,
	 * so nonce parameters or argument ordering do not bypass restrictions.
	 *
	 * @param string $slug Restricted menu slug.
	 * @return bool
	 */
	private function request_matches_slug( string $slug ): bool {
		global $pagenow;

		if ( ! is_string( $pagenow ) || '' === $pagenow ) {
			return false;
		}

		[$page, $query] = array_pad( explode( '?', $slug, 2 ), 2, '' );

		if ( $pagenow !== $page && $pagenow !== $slug ) {
			return false;
		}

		if ( '' === $query ) {
			return true;
		}

		parse_str( $query, $required_args );
		$current_args = $this->current_request_args();

		foreach ( $required_args as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( '' === $key || ! array_key_exists( $key, $current_args ) ) {
				return false;
			}

			if ( $this->normalize_request_value( $value ) !== $current_args[ $key ] ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get the normalized current request query arguments.
	 *
	 * @return array<string, mixed>
	 */
	private function current_request_args(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only access check.
		$query_args = wp_unslash( $_GET );

		unset( $query_args['_wpnonce'], $query_args['_wp_http_referer'] );

		$normalized = array();

		foreach ( $query_args as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( '' === $key ) {
				continue;
			}

			$normalized[ $key ] = $this->normalize_request_value( $value );
		}

		return $normalized;
	}

	/**
	 * Normalize a request value for comparison.
	 *
	 * @param mixed $value Request value.
	 * @return mixed
	 */
	private function normalize_request_value( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( $this, 'normalize_request_value' ), $value );
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Attempt to remove a slug as a submenu from all known parent menus.
	 */
	private function remove_from_all_parents( string $slug ): void {
		$parents = array(
			'index.php',
			'edit.php',
			'upload.php',
			'themes.php',
			'plugins.php',
			'users.php',
			'tools.php',
			'options-general.php',
		);

		foreach ( $parents as $parent ) {
			remove_submenu_page( $parent, $slug );
		}
	}
}
