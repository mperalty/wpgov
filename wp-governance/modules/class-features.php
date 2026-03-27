<?php

namespace WP_Governance\Modules;

use WP_Governance\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Enforces boolean feature toggles.
 */
class Features {

	private array $features;

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
				static function ( $result ) {
					if ( true === $result || is_wp_error( $result ) ) {
						return $result;
					}
					if ( ! is_user_logged_in() ) {
						return new \WP_Error(
							'rest_not_logged_in',
							__( 'REST API access is restricted to authenticated users.' ),
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
			add_filter( 'admin_email_check_interval', '__return_false' );
		}

		if ( $this->on( 'disable_auto_update_ui' ) ) {
			add_filter( 'plugins_auto_update_enabled', '__return_false' );
			add_filter( 'themes_auto_update_enabled', '__return_false' );
		}

		if ( $this->on( 'disable_comments' ) ) {
			$this->disable_comments();
		}

		if ( $this->on( 'disable_block_editor' ) ) {
			add_filter( 'use_block_editor_for_post', '__return_false' );
			add_filter( 'use_block_editor_for_post_type', '__return_false' );
		}

		if ( $this->on( 'disable_application_passwords' ) ) {
			add_filter( 'wp_is_application_passwords_available', '__return_false' );
		}

		if ( $this->on( 'disable_user_registration' ) ) {
			add_filter( 'option_users_can_register', '__return_zero' );
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

		if ( $this->on( 'disable_updates' ) ) {
			$this->disable_updates();
		}

		if ( $this->on( 'force_ssl_admin' ) ) {
			if ( ! defined( 'FORCE_SSL_ADMIN' ) ) {
				define( 'FORCE_SSL_ADMIN', true );
			}
		}

		if ( $this->on( 'disable_tagline_editing' ) ) {
			add_filter(
				'pre_update_option_blogdescription',
				static function ( $new_value, $old_value ) {
					return $old_value;
				},
				10,
				2
			);
		}

		if ( $this->on( 'lock_permalink_structure' ) ) {
			add_filter(
				'pre_update_option_permalink_structure',
				static function ( $new_value, $old_value ) {
					return $old_value;
				},
				10,
				2
			);
		}

		if ( $this->on( 'disable_wp_cron' ) ) {
			if ( ! defined( 'DISABLE_WP_CRON' ) ) {
				define( 'DISABLE_WP_CRON', true );
			}
		}
	}

	/**
	 * Check if a feature toggle is on.
	 */
	private function on( string $key ): bool {
		$enabled = ! empty( $this->features[ $key ] );

		return (bool) apply_filters( 'wp_governance_feature_enabled', $enabled, $key );
	}

	/**
	 * Prevent file editing via the admin theme/plugin editor.
	 */
	private function disable_file_editor(): void {
		if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', true );
		}
	}

	/**
	 * Remove the Customizer (Appearance → Customize).
	 */
	private function disable_customizer(): void {
		add_action(
			'admin_menu',
			static function (): void {
				remove_submenu_page( 'themes.php', 'customize.php' );
			}
		);

		// Block direct access.
		add_action(
			'admin_init',
			static function (): void {
				global $pagenow;
				if ( 'customize.php' === $pagenow ) {
					wp_safe_redirect( admin_url() );
					exit;
				}
			}
		);

		// Remove from admin bar.
		add_action(
			'wp_before_admin_bar_render',
			static function (): void {
				global $wp_admin_bar;

				if ( $wp_admin_bar instanceof \WP_Admin_Bar ) {
					$wp_admin_bar->remove_node( 'customize' );
				}
			}
		);
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
	 * Prevent all file modifications (plugin/theme install, update, edit).
	 */
	private function disable_file_mods(): void {
		if ( ! defined( 'DISALLOW_FILE_MODS' ) ) {
			define( 'DISALLOW_FILE_MODS', true );
		}
	}

	/**
	 * Disable all WordPress update checks and notifications.
	 */
	private function disable_updates(): void {
		// Disable update checks.
		remove_action( 'wp_version_check', 'wp_version_check' );
		remove_action( 'wp_update_plugins', 'wp_update_plugins' );
		remove_action( 'wp_update_themes', 'wp_update_themes' );
		remove_action( 'wp_maybe_auto_update', 'wp_maybe_auto_update' );
		remove_action( 'admin_init', '_maybe_update_core' );
		remove_action( 'admin_init', '_maybe_update_plugins' );
		remove_action( 'admin_init', '_maybe_update_themes' );
		remove_action( 'load-plugins.php', 'wp_update_plugins' );
		remove_action( 'load-themes.php', 'wp_update_themes' );
		remove_action( 'load-update-core.php', 'wp_update_plugins' );
		remove_action( 'load-update-core.php', 'wp_update_themes' );
		remove_action( 'load-update-core.php', 'wp_version_check' );
		remove_action( 'load-update.php', 'wp_update_plugins' );
		remove_action( 'load-update.php', 'wp_update_themes' );
		remove_action( 'load-update.php', 'wp_version_check' );

		// Prevent stale update payloads from being read or persisted.
		add_filter( 'pre_site_transient_update_core', array( $this, 'empty_core_update_transient' ) );
		add_filter( 'pre_site_transient_update_plugins', array( $this, 'empty_plugin_update_transient' ) );
		add_filter( 'pre_site_transient_update_themes', array( $this, 'empty_theme_update_transient' ) );
		add_filter( 'pre_set_site_transient_update_core', array( $this, 'empty_core_update_transient' ) );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'empty_plugin_update_transient' ) );
		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'empty_theme_update_transient' ) );

		add_filter( 'automatic_updater_disabled', '__return_true' );
		add_filter( 'auto_update_core', '__return_false', 999 );
		add_filter( 'auto_update_plugin', '__return_false', 999 );
		add_filter( 'auto_update_theme', '__return_false', 999 );
		add_filter( 'allow_dev_auto_core_updates', '__return_false' );
		add_filter( 'allow_minor_auto_core_updates', '__return_false' );
		add_filter( 'allow_major_auto_core_updates', '__return_false' );
		add_filter( 'send_core_update_notification_email', '__return_false' );

		// Hide update nags.
		add_action(
			'admin_init',
			static function (): void {
				remove_action( 'admin_notices', 'update_nag', 3 );
				remove_action( 'network_admin_notices', 'update_nag', 3 );
			}
		);

		// Remove the Updates submenu.
		add_action(
			'admin_menu',
			static function (): void {
				remove_submenu_page( 'index.php', 'update-core.php' );
			}
		);

		// Remove updates node from admin bar.
		add_action(
			'wp_before_admin_bar_render',
			static function (): void {
				global $wp_admin_bar;

				if ( $wp_admin_bar instanceof \WP_Admin_Bar ) {
					$wp_admin_bar->remove_node( 'updates' );
				}
			}
		);
	}

	/**
	 * Return an empty core-update transient so WordPress does not trigger checks.
	 *
	 * @param mixed $transient Existing transient value.
	 * @return object
	 */
	public function empty_core_update_transient( $transient ): object {
		$empty                  = is_object( $transient ) ? clone $transient : new \stdClass();
		$empty->updates         = array();
		$empty->last_checked    = time();
		$empty->version_checked = get_bloginfo( 'version' );

		return $empty;
	}

	/**
	 * Return an empty plugin-update transient so WordPress does not trigger checks.
	 *
	 * @param mixed $transient Existing transient value.
	 * @return object
	 */
	public function empty_plugin_update_transient( $transient ): object {
		$empty               = is_object( $transient ) ? clone $transient : new \stdClass();
		$empty->last_checked = time();
		$empty->checked      = array();
		$empty->response     = array();
		$empty->no_update    = array();
		$empty->translations = array();

		return $empty;
	}

	/**
	 * Return an empty theme-update transient so WordPress does not trigger checks.
	 *
	 * @param mixed $transient Existing transient value.
	 * @return object
	 */
	public function empty_theme_update_transient( $transient ): object {
		$empty               = is_object( $transient ) ? clone $transient : new \stdClass();
		$empty->last_checked = time();
		$empty->checked      = array();
		$empty->response     = array();
		$empty->no_update    = array();
		$empty->translations = array();

		return $empty;
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
