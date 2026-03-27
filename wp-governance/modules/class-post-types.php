<?php

namespace WP_Governance\Modules;

use WP_Governance\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Restricts access to specific post types in the admin.
 *
 * Can hide post types from the admin menu and block direct access
 * for users below the unrestricted role.
 */
class Post_Types {

	private array $settings;

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function __construct( array $settings, array $config ) {
		$this->settings = $settings;
		$this->register();
	}

	private function register(): void {
		$hidden = $this->settings['hidden'] ?? array();
		if ( ! empty( $hidden ) ) {
			add_action(
				'admin_menu',
				static function () use ( $hidden ): void {
					if ( Config::current_user_is_unrestricted() ) {
						return;
					}

					foreach ( $hidden as $post_type ) {
						$slug = 'post' === $post_type ? 'edit.php' : 'edit.php?post_type=' . $post_type;
						remove_menu_page( $slug );
					}
				},
				999
			);

			// Block direct access.
			add_action(
				'admin_init',
				static function () use ( $hidden ): void {
					if ( Config::current_user_is_unrestricted() ) {
						return;
					}

					global $pagenow, $typenow;

					if ( in_array( $pagenow, array( 'edit.php', 'post-new.php', 'post.php' ), true ) ) {
						$type = self::current_post_type( $typenow );
						if ( in_array( $type, $hidden, true ) ) {
							wp_die(
								esc_html__( 'You do not have permission to access this post type.' ),
								esc_html__( 'Restricted' ),
								array(
									'response'  => 403,
									'back_link' => true,
								)
							);
						}
					}
				}
			);
		}

		// Disable post type supports.
		$disable_supports = $this->settings['disable_supports'] ?? array();
		if ( ! empty( $disable_supports ) ) {
			add_action(
				'init',
				static function () use ( $disable_supports ): void {
					foreach ( $disable_supports as $post_type => $supports ) {
						foreach ( (array) $supports as $feature ) {
							remove_post_type_support( $post_type, $feature );
						}
					}
				},
				100
			);
		}
	}

	/**
	 * Resolve the current post type from the admin request.
	 *
	 * @param mixed $typenow Current admin post type global.
	 * @return string
	 */
	private static function current_post_type( $typenow ): string {
		if ( is_string( $typenow ) && '' !== $typenow ) {
			return sanitize_key( $typenow );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only access check.
		if ( isset( $_GET['post_type'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only access check.
			$post_type = sanitize_key( wp_unslash( (string) $_GET['post_type'] ) );

			if ( '' !== $post_type ) {
				return $post_type;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only access check.
		if ( isset( $_GET['post'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only access check.
			$post_id = absint( wp_unslash( (string) $_GET['post'] ) );

			if ( $post_id > 0 ) {
				$post_type = get_post_type( $post_id );

				if ( is_string( $post_type ) && '' !== $post_type ) {
					return $post_type;
				}
			}
		}

		return 'post';
	}
}
