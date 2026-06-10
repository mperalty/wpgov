<?php

namespace GovGuard\Modules;

use GovGuard\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Enforces boolean feature toggles.
 */
class Features {

	/**
	 * @var array
	 * @phpstan-var array<string, bool>
	 */
	private array $features;

	/**
	 * @param array $features Feature flags.
	 * @phpstan-param array<string, bool> $features Feature flags.
	 * @psalm-param array $features Feature flags.
	 * @param array $config Governance config.
	 * @phpstan-param array<string, mixed> $config Governance config.
	 * @psalm-param array $config Governance config.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function __construct( array $features, array $config ) {
		$this->features = $features;
		$this->register();
	}

	private function register(): void {
		if ( $this->on( 'disable_file_editor' ) ) {
			$this->disable_file_editor();
		}

		if ( $this->on( 'disable_xmlrpc' ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter(
				'wp_headers',
				static function ( array $headers ): array {
					unset( $headers['X-Pingback'] );
					return $headers;
				}
			);
		}

		if ( $this->on( 'restrict_rest_api' ) ) {
			add_filter(
				'rest_authentication_errors',
				static function ( mixed $result ): mixed {
					if ( true === $result || is_wp_error( $result ) ) {
						return $result;
					}
					if ( ! is_user_logged_in() ) {
						return new \WP_Error(
							'rest_not_logged_in',
							__( 'REST API access is restricted to authenticated users.', 'governance-guardrails' ),
							array( 'status' => 401 )
						);
					}
					return $result;
				}
			);
		}

		if ( $this->on( 'remove_wp_version' ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
		}

		if ( $this->on( 'disable_self_ping' ) ) {
			add_action(
				'pre_ping',
				static function ( array &$links ): void {
					$home = home_url();
					foreach ( $links as $i => $link ) {
						if ( str_starts_with( $link, $home ) ) {
							unset( $links[ $i ] );
						}
					}
				}
			);
		}

		if ( $this->on( 'disable_admin_email_check' ) ) {
			add_filter( 'admin_email_check_interval', array( $this, 'filter_admin_email_check_interval' ) );
		}

		if ( $this->on( 'disable_auto_update_ui' ) ) {
			add_filter( 'plugins_auto_update_enabled', array( $this, 'filter_auto_update_ui' ) );
			add_filter( 'themes_auto_update_enabled', array( $this, 'filter_auto_update_ui' ) );
		}

		if ( $this->on( 'disable_comments' ) ) {
			$this->disable_comments();
		}

		if ( $this->on( 'disable_block_editor' ) ) {
			add_filter( 'use_block_editor_for_post', array( $this, 'filter_block_editor_usage' ) );
			add_filter( 'use_block_editor_for_post_type', array( $this, 'filter_block_editor_usage' ) );
		}

		if ( $this->on( 'disable_application_passwords' ) ) {
			add_filter( 'wp_is_application_passwords_available', array( $this, 'filter_application_password_availability' ) );
		}

		if ( $this->on( 'disable_dashboard_widgets' ) ) {
			$this->disable_dashboard_widgets();
		}

		if ( $this->on( 'disable_user_registration' ) ) {
			add_filter( 'option_users_can_register', '__return_zero' );
			$this->hide_registration_toggle();
		}

		if ( $this->on( 'disable_customizer' ) ) {
			$this->disable_customizer();
		}

		if ( $this->on( 'disable_widgets' ) ) {
			$this->disable_widgets();
		}

		if ( $this->on( 'disable_search' ) ) {
			$this->disable_search();
		}

		if ( $this->on( 'disable_feeds' ) ) {
			$this->disable_feeds();
		}

		if ( $this->on( 'disable_file_mods' ) ) {
			$this->disable_file_mods();
		}

		if ( $this->on( 'disable_tagline_editing' ) ) {
			add_filter(
				'pre_update_option_blogdescription',
				static function ( mixed $new_value, mixed $old_value ): mixed {
					return $old_value;
				},
				10,
				2
			);
		}

		if ( $this->on( 'lock_permalink_structure' ) ) {
			add_filter(
				'pre_update_option_permalink_structure',
				static function ( mixed $new_value, mixed $old_value ): mixed {
					return $old_value;
				},
				10,
				2
			);
			$this->lock_permalink_ui();
		}

		if ( $this->on( 'disable_wp_cron' ) ) {
			add_filter( 'pre_get_ready_cron_jobs', array( $this, 'filter_ready_cron_jobs' ) );
		}
	}

	/**
	 * Stop WordPress from spawning loopback cron requests on normal page views.
	 *
	 * Real cron runners are unaffected: direct wp-cron.php hits and WP-CLI
	 * cron commands define DOING_CRON and therefore still see the live queue.
	 * This scopes the behavior to this plugin instead of defining the global
	 * DISABLE_WP_CRON constant.
	 *
	 * @param array|null $pre Ready cron jobs to short-circuit with, or null.
	 * @return array|null
	 */
	public function filter_ready_cron_jobs( $pre ) {
		return wp_doing_cron() ? $pre : array();
	}

	/**
	 * Block the theme/plugin file editors via the capability context only,
	 * leaving installs and updates unaffected. Same scope as the core
	 * DISALLOW_FILE_EDIT constant without defining a global constant.
	 *
	 * @param bool   $allowed Whether file modifications of this type are allowed.
	 * @param string $context The usage context.
	 * @return bool
	 */
	public function filter_file_edit_allowed( bool $allowed, string $context ): bool {
		return 'capability_edit_themes' === $context ? false : $allowed;
	}

	/**
	 * Hide the "Anyone can register" checkbox on Settings → General.
	 */
	private function hide_registration_toggle(): void {
		add_action(
			'admin_enqueue_scripts',
			static function ( string $hook_suffix ): void {
				if ( 'options-general.php' !== $hook_suffix ) {
					return;
				}

				wp_register_style( 'govguard-registration-lock', false, array(), GOVGUARD_VERSION );
				wp_enqueue_style( 'govguard-registration-lock' );
				wp_add_inline_style( 'govguard-registration-lock', 'tr:has(#users_can_register) { display: none !important; }' );
			}
		);
	}

	/**
	 * Disable permalink controls and show a notice on Settings → Permalinks.
	 */
	private function lock_permalink_ui(): void {
		add_action(
			'admin_notices',
			static function (): void {
				$screen = get_current_screen();
				if ( ! $screen || 'options-permalink' !== $screen->id ) {
					return;
				}
				echo '<div class="notice notice-warning"><p>';
				echo esc_html__( 'Permalink structure is locked by Governance Guardrails and cannot be changed.', 'governance-guardrails' );
				echo '</p></div>';
			}
		);

		add_action(
			'admin_enqueue_scripts',
			static function ( string $hook_suffix ): void {
				if ( 'options-permalink.php' !== $hook_suffix ) {
					return;
				}

				wp_register_style( 'govguard-permalink-lock', false, array(), GOVGUARD_VERSION );
				wp_enqueue_style( 'govguard-permalink-lock' );
				wp_add_inline_style( 'govguard-permalink-lock', 'input[name="selection"], input[name="permalink_structure"] { pointer-events: none; opacity: 0.5; }' );
			}
		);
	}

	/**
	 * Check if a feature toggle is on.
	 */
	private function on( string $key ): bool {
		$enabled = ! empty( $this->features[ $key ] );

		return (bool) apply_filters( 'govguard_feature_enabled', $enabled, $key );
	}

	/**
	 * Prevent file editing via the admin theme/plugin editor.
	 */
	private function disable_file_editor(): void {
		add_filter( 'file_mod_allowed', array( $this, 'filter_file_edit_allowed' ), 10, 2 );
	}

	/**
	 * Remove the Customizer (Appearance → Customize).
	 */
	private function disable_customizer(): void {
		add_action( 'admin_menu', array( $this, 'remove_customizer_menu' ) );
		add_action( 'admin_init', array( $this, 'block_customizer_access' ) );
		add_action( 'wp_before_admin_bar_render', array( $this, 'remove_customizer_admin_bar_node' ) );
	}

	/**
	 * Remove the Customizer submenu for governed users.
	 */
	public function remove_customizer_menu(): void {
		if ( Config::current_user_is_unrestricted() ) {
			return;
		}

		remove_submenu_page( 'themes.php', 'customize.php' );
	}

	/**
	 * Redirect governed users away from direct Customizer access.
	 */
	public function block_customizer_access(): void {
		if ( Config::current_user_is_unrestricted() ) {
			return;
		}

		global $pagenow;

		if ( 'customize.php' === $pagenow ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
	}

	/**
	 * Remove the Customizer admin-bar node for governed users.
	 */
	public function remove_customizer_admin_bar_node(): void {
		if ( Config::current_user_is_unrestricted() ) {
			return;
		}

		global $wp_admin_bar;

		if ( $wp_admin_bar instanceof \WP_Admin_Bar ) {
			$wp_admin_bar->remove_node( 'customize' );
		}
	}

	/**
	 * Disable legacy widgets (block widgets remain if Gutenberg is active).
	 */
	private function disable_widgets(): void {
		add_action(
			'admin_menu',
			static function (): void {
				remove_submenu_page( 'themes.php', 'widgets.php' );
			}
		);

		// Unregister all widget areas.
		add_action(
			'widgets_init',
			static function (): void {
				global $wp_registered_sidebars;
				$wp_registered_sidebars = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentional governance action.
			},
			999
		);

		add_action(
			'admin_init',
			static function (): void {
				global $pagenow;
				if ( 'widgets.php' === $pagenow ) {
					wp_safe_redirect( admin_url() );
					exit;
				}
			}
		);
	}

	/**
	 * Remove all dashboard widgets for governed users.
	 */
	private function disable_dashboard_widgets(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'clear_dashboard_widgets' ), 999 );
	}

	/**
	 * Remove every dashboard meta box, including third-party widgets.
	 */
	public function clear_dashboard_widgets(): void {
		if ( Config::current_user_is_unrestricted() ) {
			return;
		}

		global $wp_meta_boxes;

		if ( empty( $wp_meta_boxes['dashboard'] ) || ! is_array( $wp_meta_boxes['dashboard'] ) ) {
			return;
		}

		foreach ( array_keys( $wp_meta_boxes['dashboard'] ) as $context ) {
			$wp_meta_boxes['dashboard'][ $context ] = array();
		}
	}

	/**
	 * Disable front-end search entirely.
	 */
	private function disable_search(): void {
		add_action(
			'parse_query',
			static function ( \WP_Query $query ): void {
				if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
					$query->is_search = false;
					$query->is_404    = true;
				}
			}
		);

		// Remove the search form.
		add_filter( 'get_search_form', '__return_empty_string' );
	}

	/**
	 * Disable all RSS/Atom feeds.
	 */
	private function disable_feeds(): void {
		add_action( 'do_feed', array( $this, 'redirect_feed_request' ), 1 );
		add_action( 'do_feed_rdf', array( $this, 'redirect_feed_request' ), 1 );
		add_action( 'do_feed_rss', array( $this, 'redirect_feed_request' ), 1 );
		add_action( 'do_feed_rss2', array( $this, 'redirect_feed_request' ), 1 );
		add_action( 'do_feed_atom', array( $this, 'redirect_feed_request' ), 1 );
		add_action( 'do_feed_rss2_comments', array( $this, 'redirect_feed_request' ), 1 );
		add_action( 'do_feed_atom_comments', array( $this, 'redirect_feed_request' ), 1 );

		// Remove feed links from <head>.
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
	}

	/**
	 * Prevent all file modifications (plugin/theme install, update, edit)
	 * for every context, mirroring the core DISALLOW_FILE_MODS constant
	 * without defining a global constant.
	 */
	private function disable_file_mods(): void {
		add_filter( 'file_mod_allowed', '__return_false', 999 );
	}

	/**
	 * Keep the admin email check enabled for unrestricted users.
	 *
	 * @param mixed $interval Current interval value.
	 * @return mixed
	 */
	public function filter_admin_email_check_interval( $interval ) {
		return Config::current_user_is_unrestricted() ? $interval : false;
	}

	/**
	 * Disable block editor usage for governed users only.
	 */
	public function filter_block_editor_usage( bool $use_block_editor ): bool {
		return Config::current_user_is_unrestricted() ? $use_block_editor : false;
	}

	/**
	 * Disable the auto-update UI for governed users only.
	 */
	public function filter_auto_update_ui( bool $enabled ): bool {
		return Config::current_user_is_unrestricted() ? $enabled : false;
	}

	/**
	 * Disable application passwords for governed users only.
	 */
	public function filter_application_password_availability( bool $available ): bool {
		return Config::current_user_is_unrestricted() ? $available : false;
	}


	/**
	 * Comprehensively disable comments site-wide.
	 */
	private function disable_comments(): void {
		// Close comments and pings on the front end.
		add_filter( 'comments_open', '__return_false', 20 );
		add_filter( 'pings_open', '__return_false', 20 );

		// Hide existing comments.
		add_filter( 'comments_array', '__return_empty_array', 10 );

		// Remove Comments from admin menu.
		add_action(
			'admin_menu',
			static function (): void {
				remove_menu_page( 'edit-comments.php' );
			}
		);

		// Remove Comments from admin bar.
		add_action(
			'wp_before_admin_bar_render',
			static function (): void {
				global $wp_admin_bar;

				if ( $wp_admin_bar instanceof \WP_Admin_Bar ) {
					$wp_admin_bar->remove_node( 'comments' );
				}
			}
		);

		// Remove comment support from all post types.
		add_action(
			'init',
			static function (): void {
				foreach ( get_post_types() as $post_type ) {
					if ( post_type_supports( $post_type, 'comments' ) ) {
						remove_post_type_support( $post_type, 'comments' );
						remove_post_type_support( $post_type, 'trackbacks' );
					}
				}
			},
			100
		);

		// Redirect any direct access to the comments admin page.
		add_action(
			'admin_init',
			static function (): void {
				global $pagenow;
				if ( 'edit-comments.php' === $pagenow ) {
					wp_safe_redirect( admin_url() );
					exit;
				}
			}
		);
	}

	/**
	 * Redirect a feed request back to the homepage.
	 */
	public function redirect_feed_request(): void {
		wp_safe_redirect( home_url(), 301 );
		exit;
	}
}
