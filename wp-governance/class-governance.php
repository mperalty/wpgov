<?php

namespace WP_Governance;

defined( 'ABSPATH' ) || exit;

/**
 * Core orchestrator.
 *
 * Loads the config, determines which modules are active,
 * and instantiates them so they can hook into WordPress.
 */
class Governance {

	/** @var self|null Singleton instance. */
	private static ?self $instance = null;

	/** @var array Instantiated module objects. */
	private array $modules = array();

	/**
	 * Module class map: config key => class file + class name.
	 */
	private const MODULE_MAP = array(
		'features'                 => array( 'class-features.php', 'Features' ),
		'restricted_menu_slugs'    => array( 'class-admin-menu.php', 'Admin_Menu' ),
		'remove_admin_bar_nodes'   => array( 'class-admin-bar.php', 'Admin_Bar' ),
		'deny_capabilities'        => array( 'class-capabilities.php', 'Capabilities' ),
		'login'                    => array( 'class-login.php', 'Login' ),
		'content'                  => array( 'class-content.php', 'Content' ),
		'head_cleanup'             => array( 'class-head-cleanup.php', 'Head_Cleanup' ),
		'remove_dashboard_widgets' => array( 'class-dashboard.php', 'Dashboard' ),
		'suppress_admin_notices'   => array( 'class-notices.php', 'Notices' ),
		'admin_footer'             => array( 'class-admin-footer.php', 'Admin_Footer' ),
		'post_types'               => array( 'class-post-types.php', 'Post_Types' ),
		'security'                 => array( 'class-security.php', 'Security' ),
		'locked_options'           => array( 'class-locked-options.php', 'Locked_Options' ),
	);

	private function __construct() {
		$this->boot();
	}

	/**
	 * Get or create the singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Load modules and fire hooks.
	 */
	private function boot(): void {
		$config = Config::get();

		/**
		 * Fires before governance enforcement begins.
		 *
		 * @param array $config The full governance config.
		 */
		do_action( 'wp_governance_before_enforce', $config );

		// Upload rules span two top-level config sections, so they are booted
		// outside MODULE_MAP instead of pretending one section owns them all.
		$this->boot_uploads( $config );

		// Load each module when its config section is non-empty.
		foreach ( self::MODULE_MAP as $key => [$file, $class] ) {
			$section = $config[ $key ] ?? array();

			if ( empty( $section ) ) {
				continue;
			}

			$fqcn = __NAMESPACE__ . '\\Modules\\' . $class;

			require_once WP_GOVERNANCE_DIR . 'modules/' . $file;
			$this->modules[ $key ] = new $fqcn( $section, $config );
		}

		// Custom rules.
		$this->run_custom_rules( $config );

		// Status page (always loaded for unrestricted users).
		if ( is_admin() ) {
			require_once WP_GOVERNANCE_DIR . 'class-status-page.php';
			new Status_Page( $config );
		}

		/**
		 * Fires after all governance modules are initialized.
		 *
		 * @param array $config  The full governance config.
		 * @param array $modules Instantiated module objects keyed by config section.
		 */
		do_action( 'wp_governance_loaded', $config, $this->modules );
	}

	/**
	 * Load upload governance when either MIME restrictions or upload settings exist.
	 */
	private function boot_uploads( array $config ): void {
		$allowed        = $config['allowed_mime_types'] ?? array();
		$uploads        = $config['uploads'] ?? array();
		$has_size_limit = is_array( $uploads ) && array_key_exists( 'max_upload_size_mb', $uploads ) && null !== $uploads['max_upload_size_mb'];

		if ( empty( $allowed ) && ! $has_size_limit ) {
			return;
		}

		require_once WP_GOVERNANCE_DIR . 'modules/class-uploads.php';
		$this->modules['uploads'] = new Modules\Uploads( $allowed, $config );
	}

	/**
	 * Execute user-registered custom rule callables.
	 */
	private function run_custom_rules( array $config ): void {
		$rules = $config['custom_rules'] ?? array();

		if ( empty( $rules ) ) {
			return;
		}

		foreach ( $rules as $rule ) {
			$parsed = $this->parse_custom_rule( $rule );
			if ( empty( $parsed ) || ! empty( $parsed['disabled'] ) ) {
				continue;
			}

			add_action(
				$parsed['hook'],
				static function () use ( $parsed, $config ) {
					if ( $parsed['admin_only'] && ! is_admin() ) {
						return;
					}

					if ( $parsed['front_only'] && is_admin() ) {
						return;
					}

					call_user_func( $parsed['callback'], $config );
				},
				$parsed['priority']
			);
		}
	}

	/**
	 * Normalize a custom rule definition coming from the config or filters.
	 *
	 * @param mixed $rule Rule definition.
	 * @return array{callback:mixed,hook:string,priority:int,admin_only:bool,front_only:bool,disabled:bool}|array{}
	 */
	private function parse_custom_rule( $rule ): array {
		$parsed = array(
			'callback'   => null,
			'hook'       => 'init',
			'priority'   => 10,
			'admin_only' => false,
			'front_only' => false,
			'disabled'   => false,
		);

		if ( ! is_array( $rule ) ) {
			if ( ! is_callable( $rule ) ) {
				return array();
			}

			$parsed['callback'] = $rule;
			return $parsed;
		}

		$callback = $rule['callback'] ?? null;
		if ( ! is_callable( $callback ) ) {
			return array();
		}

		$parsed['callback'] = $callback;

		if ( array_key_exists( 'hook', $rule ) && is_scalar( $rule['hook'] ) ) {
			$hook = trim( (string) $rule['hook'] );
			if ( '' !== $hook ) {
				$parsed['hook'] = $hook;
			}
		}

		if ( array_key_exists( 'priority', $rule ) && is_numeric( $rule['priority'] ) ) {
			$parsed['priority'] = (int) $rule['priority'];
		}

		foreach ( array( 'admin_only', 'front_only' ) as $key ) {
			if ( array_key_exists( $key, $rule ) ) {
				$parsed[ $key ] = (bool) $rule[ $key ];
			}
		}

		// Use the normalized 'disabled' flag if present, otherwise derive it.
		$parsed['disabled'] = ! empty( $rule['disabled'] ) || ( $parsed['admin_only'] && $parsed['front_only'] );

		return $parsed;
	}
}
