<?php

namespace WP_Governance;

defined( 'ABSPATH' ) || exit;

/**
 * Loads, validates, and caches the governance config file.
 *
 * Config is loaded once per request and cached in a static property.
 * If the config file is missing or malformed the plugin fails open
 * (returns an empty array) and logs a warning.
 */
class Config {

	/** @var array|null Cached config. */
	private static ?array $config = null;

	/** @var string Resolved config file path. */
	private static string $path = '';

	/** @var array|null Cached defaults from the shipped sample config. */
	private static ?array $sample_defaults = null;

	/** @var string Resolved environment override file path (empty if none). */
	private static string $environment_path = '';

	/**
	 * Default config structure used for validation.
	 */
	private const DEFAULTS = array(
		'features'                 => array(),
		'restricted_menu_slugs'    => array(),
		'remove_admin_bar_nodes'   => array(),
		'remove_dashboard_widgets' => array(),
		'deny_capabilities'        => array(),
		'allowed_mime_types'       => array(),
		'uploads'                  => array(),
		'login'                    => array(),
		'content'                  => array(),
		'head_cleanup'             => array(),
		'unrestricted_role'        => 'administrator',
		'suppress_admin_notices'   => array(),
		'admin_footer'             => array(),
		'post_types'               => array(),
		'security'                 => array(),
		'custom_rules'             => array(),
		'locked_options'           => array(),
	);

	/**
	 * Get the full governance config array.
	 *
	 * @return array
	 */
	public static function get(): array {
		if ( null !== self::$config ) {
			return self::$config;
		}

		self::$path   = self::resolve_path();
		self::$config = self::load( self::$path );

		/**
		 * Filter the governance config after loading.
		 *
		 * @param array  $config The loaded config array.
		 * @param string $path   The resolved config file path.
		 */
		self::$config = apply_filters( 'wp_governance_config', self::$config, self::$path );

		return self::$config;
	}

	/**
	 * Get a specific config section.
	 *
	 * @param string $key     Top-level config key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public static function section( string $key, $default = array() ) {
		$config = self::get();
		return $config[ $key ] ?? $default;
	}

	/**
	 * Check if a feature toggle is enabled.
	 *
	 * @param string $feature Feature key inside the 'features' section.
	 * @return bool
	 */
	public static function feature_enabled( string $feature ): bool {
		$features = self::section( 'features' );
		$enabled  = ! empty( $features[ $feature ] );

		/**
		 * Override a specific feature toggle.
		 *
		 * @param bool   $enabled Whether the feature is enabled.
		 * @param string $feature The feature key.
		 */
		return (bool) apply_filters( 'wp_governance_feature_enabled', $enabled, $feature );
	}

	/**
	 * Get the resolved config file path.
	 *
	 * @return string
	 */
	public static function path(): string {
		if ( '' === self::$path ) {
			self::$path = self::resolve_path();
		}
		return self::$path;
	}

	/**
	 * Get the current WordPress environment type.
	 *
	 * @return string One of: local, development, staging, production.
	 */
	public static function environment(): string {
		if ( function_exists( 'wp_get_environment_type' ) ) {
			return wp_get_environment_type();
		}

		if ( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
			$type  = (string) WP_ENVIRONMENT_TYPE;
			$valid = array( 'local', 'development', 'staging', 'production' );
			return in_array( $type, $valid, true ) ? $type : 'production';
		}

		return 'production';
	}

	/**
	 * Get the resolved environment override file path (empty if none loaded).
	 *
	 * @return string
	 */
	public static function environment_path(): string {
		// Ensure config has been loaded so the path is resolved.
		self::get();
		return self::$environment_path;
	}

	/**
	 * Get the fail-open config shape used at runtime.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return self::DEFAULTS;
	}

	/**
	 * Get the defaults from the shipped sample config.
	 *
	 * These defaults are intended for human-facing comparisons. Runtime
	 * loading still falls back to DEFAULTS so missing or malformed config
	 * never activates extra governance rules by accident.
	 *
	 * @return array
	 */
	public static function sample_defaults(): array {
		if ( null !== self::$sample_defaults ) {
			return self::$sample_defaults;
		}

		$path = __DIR__ . '/wp-governance-config.php';
		if ( ! is_readable( $path ) ) {
			self::$sample_defaults = self::DEFAULTS;
			return self::$sample_defaults;
		}

		$defaults = include $path;
		if ( ! is_array( $defaults ) ) {
			self::$sample_defaults = self::DEFAULTS;
			return self::$sample_defaults;
		}

		self::$sample_defaults = self::normalize( $defaults );

		return self::$sample_defaults;
	}

	/**
	 * Validate a raw config array and return non-fatal error messages.
	 *
	 * @param array $config Raw config array.
	 * @return string[]
	 */
	public static function validation_errors( array $config ): array {
		$errors  = array();
		$unknown = array_diff( array_keys( $config ), array_keys( self::DEFAULTS ) );

		if ( ! empty( $unknown ) ) {
			$errors[] = 'Unknown config keys: ' . implode( ', ', $unknown );
		}

		self::validate_boolean_section( $config, 'features', self::feature_defaults(), $errors );
		self::validate_string_list_section( $config, 'restricted_menu_slugs', $errors, false );
		self::validate_string_list_section( $config, 'remove_admin_bar_nodes', $errors, true );
		self::validate_string_list_section( $config, 'remove_dashboard_widgets', $errors, true );
		self::validate_deny_capabilities( $config, $errors );
		self::validate_mime_map( $config, $errors );
		self::validate_uploads( $config, $errors );
		self::validate_login( $config, $errors );
		self::validate_content( $config, $errors );
		self::validate_boolean_section( $config, 'head_cleanup', self::head_cleanup_defaults(), $errors );
		self::validate_string_list_section( $config, 'suppress_admin_notices', $errors, true );
		self::validate_admin_footer( $config, $errors );
		self::validate_post_types( $config, $errors );
		self::validate_security( $config, $errors );
		self::validate_custom_rules( $config, $errors );
		self::validate_locked_options( $config, $errors );

		if ( isset( $config['unrestricted_role'] ) && ! is_string( $config['unrestricted_role'] ) ) {
			$errors[] = "'unrestricted_role' should be a string.";
		}

		return $errors;
	}

	/**
	 * Determine where the config file lives.
	 *
	 * Priority:
	 * 1. wp_governance_config_path filter (allows runtime override, useful for tests)
	 * 2. WP_GOVERNANCE_CONFIG constant
	 * 3. Default location beside the mu-plugin loader
	 *
	 * @return string
	 */
	private static function resolve_path(): string {
		$path = '';

		if ( defined( 'WP_GOVERNANCE_CONFIG' ) && WP_GOVERNANCE_CONFIG ) {
			$path = (string) WP_GOVERNANCE_CONFIG;
		} else {
			$path = WP_GOVERNANCE_DIR . 'wp-governance-config.php';
		}

		/**
		 * Filter the resolved config file path.
		 *
		 * @param string $path The resolved config file path.
		 */
		return (string) apply_filters( 'wp_governance_config_path', $path );
	}

	/**
	 * Load and validate the config file, then merge any environment override.
	 *
	 * @param string $path Absolute path to base config file.
	 * @return array Validated config or empty array on failure.
	 */
	private static function load( string $path ): array {
		if ( ! is_readable( $path ) ) {
			self::warn( "WP Governance: config file not found or not readable at {$path}" );
			return self::DEFAULTS;
		}

		$config = include $path;

		if ( ! is_array( $config ) ) {
			self::warn( "WP Governance: config file at {$path} did not return an array." );
			return self::DEFAULTS;
		}

		// Merge environment-specific override (e.g. config.local.php).
		$env_path = self::resolve_environment_path( $path );
		if ( '' !== $env_path ) {
			self::$environment_path = $env_path;
			$override               = include $env_path;

			if ( is_array( $override ) ) {
				$config = self::deep_merge( $config, $override );
			} else {
				self::warn( "WP Governance: environment override at {$env_path} did not return an array." );
			}
		}

		foreach ( self::validation_errors( $config ) as $error ) {
			self::warn( 'WP Governance: ' . $error );
		}

		return self::normalize( $config );
	}

	/**
	 * Normalize the config into a fail-open but schema-aware shape.
	 *
	 * Top-level sections stay empty when omitted so modules do not boot
	 * unnecessarily, but known nested sections are filled with neutral
	 * defaults when they are explicitly configured.
	 *
	 * @param array $config Raw config array.
	 * @return array
	 */
	private static function normalize( array $config ): array {
		$normalized                            = self::DEFAULTS;
		$normalized['features']                = self::normalize_boolean_section( $config['features'] ?? null, self::feature_defaults() );
		$normalized['restricted_menu_slugs']   = self::normalize_string_list( $config['restricted_menu_slugs'] ?? null, false );
		$normalized['remove_admin_bar_nodes']  = self::normalize_string_list( $config['remove_admin_bar_nodes'] ?? null, true );
		$normalized['remove_dashboard_widgets'] = self::normalize_string_list( $config['remove_dashboard_widgets'] ?? null, true );
		$normalized['deny_capabilities']       = self::normalize_deny_capabilities( $config['deny_capabilities'] ?? null );
		$normalized['allowed_mime_types']      = self::normalize_mime_map( $config['allowed_mime_types'] ?? null );
		$normalized['uploads']                 = self::normalize_uploads( $config['uploads'] ?? null );
		$normalized['login']                   = self::normalize_login( $config['login'] ?? null );
		$normalized['content']                 = self::normalize_content( $config['content'] ?? null );
		$normalized['head_cleanup']            = self::normalize_boolean_section( $config['head_cleanup'] ?? null, self::head_cleanup_defaults() );
		$normalized['suppress_admin_notices']  = self::normalize_string_list( $config['suppress_admin_notices'] ?? null, true );
		$normalized['admin_footer']            = self::normalize_admin_footer( $config['admin_footer'] ?? null );
		$normalized['post_types']              = self::normalize_post_types( $config['post_types'] ?? null );
		$normalized['security']                = self::normalize_security( $config['security'] ?? null );
		$normalized['custom_rules']            = self::normalize_custom_rules( $config['custom_rules'] ?? null );
		$normalized['locked_options']           = self::normalize_locked_options( $config['locked_options'] ?? null );

		if ( isset( $config['unrestricted_role'] ) && is_string( $config['unrestricted_role'] ) ) {
			$role = sanitize_key( $config['unrestricted_role'] );
			if ( '' !== $role ) {
				$normalized['unrestricted_role'] = $role;
			}
		}

		return $normalized;
	}

	/**
	 * Normalize a boolean-keyed section and fill neutral defaults.
	 *
	 * @param mixed $value    Raw section value.
	 * @param array $defaults Known boolean defaults.
	 * @return array
	 */
	private static function normalize_boolean_section( $value, array $defaults ): array {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return array();
		}

		$known = array_intersect_key( $value, $defaults );
		if ( empty( $known ) ) {
			return array();
		}

		$normalized = $defaults;

		foreach ( $known as $key => $entry ) {
			$normalized[ $key ] = self::normalize_bool( $entry, $defaults[ $key ] );
		}

		return $normalized;
	}

	/**
	 * Normalize a string list section.
	 *
	 * @param mixed $value             Raw section value.
	 * @param bool  $sanitize_with_key Whether to sanitize entries with sanitize_key().
	 * @return array
	 */
	private static function normalize_string_list( $value, bool $sanitize_with_key ): array {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $value as $entry ) {
			if ( ! is_scalar( $entry ) ) {
				continue;
			}

			$string = $sanitize_with_key ? sanitize_key( (string) $entry ) : trim( (string) $entry );
			if ( '' === $string ) {
				continue;
			}

			$normalized[] = $string;
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Normalize role => denied capability map.
	 *
	 * @param mixed $value Raw section value.
	 * @return array
	 */
	private static function normalize_deny_capabilities( $value ): array {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $value as $role => $caps ) {
			$role = sanitize_key( (string) $role );
			if ( '' === $role || ! is_array( $caps ) ) {
				continue;
			}

			$entries = self::normalize_string_list( $caps, true );
			if ( empty( $entries ) ) {
				continue;
			}

			$normalized[ $role ] = $entries;
		}

		return $normalized;
	}

	/**
	 * Normalize the allowed MIME map.
	 *
	 * @param mixed $value Raw section value.
	 * @return array
	 */
	private static function normalize_mime_map( $value ): array {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $value as $extensions => $mime ) {
			if ( ! is_scalar( $mime ) ) {
				continue;
			}

			$extensions = trim( (string) $extensions );
			$mime       = trim( (string) $mime );

			if ( '' === $extensions || '' === $mime ) {
				continue;
			}

			$normalized[ $extensions ] = $mime;
		}

		return $normalized;
	}

	/**
	 * Normalize upload settings.
	 *
	 * @param mixed $value Raw section value.
	 * @return array
	 */
	private static function normalize_uploads( $value ): array {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return array();
		}

		if ( ! array_key_exists( 'max_upload_size_mb', $value ) ) {
			return array();
		}

		return array(
			'max_upload_size_mb' => self::normalize_positive_number_or_null( $value['max_upload_size_mb'] ),
		);
	}

	/**
	 * Normalize login settings.
	 *
	 * @param mixed $value Raw section value.
	 * @return array
	 */
	private static function normalize_login( $value ): array {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return array();
		}

		$normalized = self::login_defaults();

		if ( ! self::has_known_keys( $value, $normalized ) ) {
			return array();
		}

		if ( array_key_exists( 'disable_password_reset', $value ) ) {
			$normalized['disable_password_reset'] = self::normalize_bool( $value['disable_password_reset'] );
		}

		if ( array_key_exists( 'hide_login_errors', $value ) ) {
			$normalized['hide_login_errors'] = self::normalize_bool( $value['hide_login_errors'] );
		}

		if ( array_key_exists( 'redirect_after_logout', $value ) && is_scalar( $value['redirect_after_logout'] ) ) {
			$normalized['redirect_after_logout'] = trim( (string) $value['redirect_after_logout'] );
		}

		return $normalized;
	}

	/**
	 * Normalize content settings.
	 *
	 * @param mixed $value Raw section value.
	 * @return array
	 */
	private static function normalize_content( $value ): array {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return array();
		}

		$normalized = self::content_defaults();

		if ( ! self::has_known_keys( $value, $normalized ) ) {
			return array();
		}

		foreach ( array( 'disable_revisions', 'disable_autosave', 'disable_embeds', 'disable_emojis' ) as $key ) {
			if ( array_key_exists( $key, $value ) ) {
				$normalized[ $key ] = self::normalize_bool( $value[ $key ] );
			}
		}

		if ( array_key_exists( 'revision_limit', $value ) ) {
			$normalized['revision_limit'] = self::normalize_non_negative_integer_or_null( $value['revision_limit'] );
		}

		if ( array_key_exists( 'autosave_interval', $value ) ) {
			$normalized['autosave_interval'] = self::normalize_positive_integer_or_null( $value['autosave_interval'] );
		}

		return $normalized;
	}

	/**
	 * Normalize admin footer settings.
	 *
	 * Empty strings are not defaulted here because Admin_Footer uses isset()
	 * to decide whether to override core footer text.
	 *
	 * @param mixed $value Raw section value.
	 * @return array
	 */
	private static function normalize_admin_footer( $value ): array {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return array();
		}

		$normalized = array();

		if ( array_key_exists( 'remove_footer', $value ) ) {
			$normalized['remove_footer'] = self::normalize_bool( $value['remove_footer'] );
		}

		if ( array_key_exists( 'left_text', $value ) && is_scalar( $value['left_text'] ) ) {
			$normalized['left_text'] = (string) $value['left_text'];
		}

		if ( array_key_exists( 'right_text', $value ) && is_scalar( $value['right_text'] ) ) {
			$normalized['right_text'] = (string) $value['right_text'];
		}

		return $normalized;
	}

	/**
	 * Normalize post type settings.
	 *
	 * @param mixed $value Raw section value.
	 * @return array
	 */
	private static function normalize_post_types( $value ): array {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return array();
		}

		$defaults = array(
			'hidden'           => array(),
			'disable_supports' => array(),
		);

		if ( ! self::has_known_keys( $value, $defaults ) ) {
			return array();
		}

		$normalized = $defaults;

		if ( array_key_exists( 'hidden', $value ) ) {
			$normalized['hidden'] = self::normalize_string_list( $value['hidden'], true );
		}

		if ( isset( $value['disable_supports'] ) && is_array( $value['disable_supports'] ) ) {
			foreach ( $value['disable_supports'] as $post_type => $supports ) {
				$post_type = sanitize_key( (string) $post_type );

				if ( '' === $post_type || ! is_array( $supports ) ) {
					continue;
				}

				$entries = self::normalize_string_list( $supports, true );
				if ( empty( $entries ) ) {
					continue;
				}

				$normalized['disable_supports'][ $post_type ] = $entries;
			}
		}

		return $normalized;
	}

	/**
	 * Normalize security settings.
	 *
	 * @param mixed $value Raw section value.
	 * @return array
	 */
	private static function normalize_security( $value ): array {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return array();
		}

		$normalized = self::security_defaults();

		if ( ! self::has_known_keys( $value, $normalized ) ) {
			return array();
		}

		if ( isset( $value['headers'] ) && is_array( $value['headers'] ) ) {
			$headers = array();

			foreach ( $value['headers'] as $name => $header_value ) {
				if ( ! is_scalar( $header_value ) ) {
					continue;
				}

				$name         = trim( (string) $name );
				$header_value = trim( (string) $header_value );

				if ( '' === $name || '' === $header_value ) {
					continue;
				}

				$headers[ $name ] = $header_value;
			}

			$normalized['headers'] = $headers;
		}

		foreach ( array( 'disable_author_archives', 'hide_wp_version_from_scripts', 'remove_pingback_header', 'disable_file_editing', 'add_noindex_headers' ) as $key ) {
			if ( array_key_exists( $key, $value ) ) {
				$normalized[ $key ] = self::normalize_bool( $value[ $key ] );
			}
		}

		return $normalized;
	}

	/**
	 * Normalize custom rules into a consistent runtime shape.
	 *
	 * Each rule may be a direct callable or an array with:
	 * - callback   (required)
	 * - hook       (default: init)
	 * - priority   (default: 10)
	 * - admin_only (default: false)
	 * - front_only (default: false)
	 *
	 * @param mixed $value Raw section value.
	 * @return array<string, array{callback:mixed,hook:string,priority:int,admin_only:bool,front_only:bool}>
	 */
	private static function normalize_custom_rules( $value ): array {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $value as $name => $rule ) {
			$name = trim( (string) $name );
			if ( '' === $name ) {
				continue;
			}

			$normalized_rule = self::normalize_custom_rule( $rule );
			if ( empty( $normalized_rule ) ) {
				continue;
			}

			$normalized[ $name ] = $normalized_rule;
		}

		return $normalized;
	}

	/**
	 * Normalize a single custom rule.
	 *
	 * When admin_only and front_only are both true the rule can never
	 * execute, so a 'disabled' flag is set. All consumers should check
	 * this flag instead of re-deriving the conflict.
	 *
	 * @param mixed $value Raw rule definition.
	 * @return array{callback:mixed,hook:string,priority:int,admin_only:bool,front_only:bool,disabled:bool}|array{}
	 */
	private static function normalize_custom_rule( $value ): array {
		$normalized = self::custom_rule_defaults();

		if ( ! is_array( $value ) ) {
			if ( ! self::custom_rule_callback_looks_callable( $value ) ) {
				return array();
			}

			$normalized['callback'] = $value;
			return $normalized;
		}

		$callback = $value['callback'] ?? null;
		if ( ! self::custom_rule_callback_looks_callable( $callback ) ) {
			return array();
		}

		$normalized['callback'] = $callback;

		if ( array_key_exists( 'hook', $value ) && is_scalar( $value['hook'] ) ) {
			$hook = trim( (string) $value['hook'] );
			if ( '' !== $hook ) {
				$normalized['hook'] = $hook;
			}
		}

		if ( array_key_exists( 'priority', $value ) && is_numeric( $value['priority'] ) ) {
			$normalized['priority'] = (int) $value['priority'];
		}

		foreach ( array( 'admin_only', 'front_only' ) as $key ) {
			if ( array_key_exists( $key, $value ) && self::is_bool_like( $value[ $key ] ) ) {
				$normalized[ $key ] = self::normalize_bool( $value[ $key ] );
			}
		}

		// Mark rules with conflicting scope as disabled.
		$normalized['disabled'] = $normalized['admin_only'] && $normalized['front_only'];

		return $normalized;
	}

	/**
	 * Default structured custom rule values.
	 *
	 * @return array{callback:null,hook:string,priority:int,admin_only:bool,front_only:bool,disabled:bool}
	 */
	private static function custom_rule_defaults(): array {
		return array(
			'callback'   => null,
			'hook'       => 'init',
			'priority'   => 10,
			'admin_only' => false,
			'front_only' => false,
			'disabled'   => false,
		);
	}

	/**
	 * Check whether a custom rule callback is callable in the current request.
	 *
	 * @param mixed $value Potential callback.
	 * @return bool
	 */
	private static function custom_rule_callback_looks_callable( $value ): bool {
		return is_callable( $value ) || ( is_string( $value ) && function_exists( $value ) );
	}

	/**
	 * Validate a boolean-keyed nested section.
	 *
	 * @param array  $config   Raw config array.
	 * @param string $section  Section name.
	 * @param array  $defaults Allowed keys.
	 * @param array  $errors   Error accumulator.
	 */
	private static function validate_boolean_section( array $config, string $section, array $defaults, array &$errors ): void {
		if ( ! array_key_exists( $section, $config ) ) {
			return;
		}

		if ( ! is_array( $config[ $section ] ) ) {
			$errors[] = "'{$section}' should be an array.";
			return;
		}

		self::validate_unknown_nested_keys( $section, $config[ $section ], array_keys( $defaults ), $errors );

		foreach ( $config[ $section ] as $key => $value ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				continue;
			}

			if ( ! self::is_bool_like( $value ) ) {
				$errors[] = "'{$section}.{$key}' should be a boolean.";
			}
		}
	}

	/**
	 * Validate a string list section.
	 *
	 * @param array  $config            Raw config array.
	 * @param string $section           Section name.
	 * @param array  $errors            Error accumulator.
	 * @param bool   $sanitize_with_key Whether values are expected to be key-like strings.
	 */
	private static function validate_string_list_section( array $config, string $section, array &$errors, bool $sanitize_with_key ): void {
		if ( ! array_key_exists( $section, $config ) ) {
			return;
		}

		if ( ! is_array( $config[ $section ] ) ) {
			$errors[] = "'{$section}' should be an array.";
			return;
		}

		foreach ( $config[ $section ] as $entry ) {
			if ( ! is_scalar( $entry ) ) {
				$errors[] = "'{$section}' should contain only strings.";
				break;
			}

			$value = $sanitize_with_key ? sanitize_key( (string) $entry ) : trim( (string) $entry );
			if ( '' === $value ) {
				$errors[] = "'{$section}' should not contain empty values.";
				break;
			}
		}
	}

	/**
	 * Validate deny capability map.
	 *
	 * @param array $config Raw config array.
	 * @param array $errors Error accumulator.
	 */
	private static function validate_deny_capabilities( array $config, array &$errors ): void {
		if ( ! array_key_exists( 'deny_capabilities', $config ) ) {
			return;
		}

		if ( ! is_array( $config['deny_capabilities'] ) ) {
			$errors[] = "'deny_capabilities' should be an array.";
			return;
		}

		foreach ( $config['deny_capabilities'] as $role => $caps ) {
			if ( '' === sanitize_key( (string) $role ) ) {
				$errors[] = "'deny_capabilities' contains an invalid role slug.";
				continue;
			}

			if ( ! is_array( $caps ) ) {
				$errors[] = "'deny_capabilities.{$role}' should be an array.";
				continue;
			}

			foreach ( $caps as $cap ) {
				if ( '' === sanitize_key( (string) $cap ) ) {
					$errors[] = "'deny_capabilities.{$role}' should contain only capability strings.";
					break;
				}
			}
		}
	}

	/**
	 * Validate MIME allowlist map.
	 *
	 * @param array $config Raw config array.
	 * @param array $errors Error accumulator.
	 */
	private static function validate_mime_map( array $config, array &$errors ): void {
		if ( ! array_key_exists( 'allowed_mime_types', $config ) ) {
			return;
		}

		if ( ! is_array( $config['allowed_mime_types'] ) ) {
			$errors[] = "'allowed_mime_types' should be an array.";
			return;
		}

		foreach ( $config['allowed_mime_types'] as $extensions => $mime ) {
			if ( '' === trim( (string) $extensions ) || ! is_scalar( $mime ) || '' === trim( (string) $mime ) ) {
				$errors[] = "'allowed_mime_types' should map non-empty extension groups to non-empty MIME strings.";
				break;
			}
		}
	}

	/**
	 * Validate upload settings.
	 *
	 * @param array $config Raw config array.
	 * @param array $errors Error accumulator.
	 */
	private static function validate_uploads( array $config, array &$errors ): void {
		if ( ! array_key_exists( 'uploads', $config ) ) {
			return;
		}

		if ( ! is_array( $config['uploads'] ) ) {
			$errors[] = "'uploads' should be an array.";
			return;
		}

		self::validate_unknown_nested_keys( 'uploads', $config['uploads'], array( 'max_upload_size_mb' ), $errors );

		if (
			array_key_exists( 'max_upload_size_mb', $config['uploads'] ) &&
			null !== $config['uploads']['max_upload_size_mb'] &&
			(
				! is_numeric( $config['uploads']['max_upload_size_mb'] ) ||
				(float) $config['uploads']['max_upload_size_mb'] <= 0
			)
		) {
			$errors[] = "'uploads.max_upload_size_mb' should be a positive number or null.";
		}
	}

	/**
	 * Validate login settings.
	 *
	 * @param array $config Raw config array.
	 * @param array $errors Error accumulator.
	 */
	private static function validate_login( array $config, array &$errors ): void {
		if ( ! array_key_exists( 'login', $config ) ) {
			return;
		}

		if ( ! is_array( $config['login'] ) ) {
			$errors[] = "'login' should be an array.";
			return;
		}

		self::validate_unknown_nested_keys( 'login', $config['login'], array_keys( self::login_defaults() ), $errors );

		foreach ( array( 'disable_password_reset', 'hide_login_errors' ) as $key ) {
			if ( array_key_exists( $key, $config['login'] ) && ! self::is_bool_like( $config['login'][ $key ] ) ) {
				$errors[] = "'login.{$key}' should be a boolean.";
			}
		}

		if ( array_key_exists( 'redirect_after_logout', $config['login'] ) && ! is_scalar( $config['login']['redirect_after_logout'] ) ) {
			$errors[] = "'login.redirect_after_logout' should be a string.";
		}
	}

	/**
	 * Validate content settings.
	 *
	 * @param array $config Raw config array.
	 * @param array $errors Error accumulator.
	 */
	private static function validate_content( array $config, array &$errors ): void {
		if ( ! array_key_exists( 'content', $config ) ) {
			return;
		}

		if ( ! is_array( $config['content'] ) ) {
			$errors[] = "'content' should be an array.";
			return;
		}

		self::validate_unknown_nested_keys( 'content', $config['content'], array_keys( self::content_defaults() ), $errors );

		foreach ( array( 'disable_revisions', 'disable_autosave', 'disable_embeds', 'disable_emojis' ) as $key ) {
			if ( array_key_exists( $key, $config['content'] ) && ! self::is_bool_like( $config['content'][ $key ] ) ) {
				$errors[] = "'content.{$key}' should be a boolean.";
			}
		}

		if (
			array_key_exists( 'revision_limit', $config['content'] ) &&
			null !== $config['content']['revision_limit'] &&
			(
				! is_numeric( $config['content']['revision_limit'] ) ||
				(float) $config['content']['revision_limit'] < 0
			)
		) {
			$errors[] = "'content.revision_limit' should be a non-negative number or null.";
		}

		if (
			array_key_exists( 'autosave_interval', $config['content'] ) &&
			null !== $config['content']['autosave_interval'] &&
			(
				! is_numeric( $config['content']['autosave_interval'] ) ||
				(float) $config['content']['autosave_interval'] <= 0
			)
		) {
			$errors[] = "'content.autosave_interval' should be a positive number or null.";
		}
	}

	/**
	 * Validate admin footer settings.
	 *
	 * @param array $config Raw config array.
	 * @param array $errors Error accumulator.
	 */
	private static function validate_admin_footer( array $config, array &$errors ): void {
		if ( ! array_key_exists( 'admin_footer', $config ) ) {
			return;
		}

		if ( ! is_array( $config['admin_footer'] ) ) {
			$errors[] = "'admin_footer' should be an array.";
			return;
		}

		self::validate_unknown_nested_keys( 'admin_footer', $config['admin_footer'], array( 'left_text', 'right_text', 'remove_footer' ), $errors );

		if ( array_key_exists( 'remove_footer', $config['admin_footer'] ) && ! self::is_bool_like( $config['admin_footer']['remove_footer'] ) ) {
			$errors[] = "'admin_footer.remove_footer' should be a boolean.";
		}

		foreach ( array( 'left_text', 'right_text' ) as $key ) {
			if ( array_key_exists( $key, $config['admin_footer'] ) && ! is_scalar( $config['admin_footer'][ $key ] ) ) {
				$errors[] = "'admin_footer.{$key}' should be a string.";
			}
		}
	}

	/**
	 * Validate post type settings.
	 *
	 * @param array $config Raw config array.
	 * @param array $errors Error accumulator.
	 */
	private static function validate_post_types( array $config, array &$errors ): void {
		if ( ! array_key_exists( 'post_types', $config ) ) {
			return;
		}

		if ( ! is_array( $config['post_types'] ) ) {
			$errors[] = "'post_types' should be an array.";
			return;
		}

		self::validate_unknown_nested_keys( 'post_types', $config['post_types'], array( 'hidden', 'disable_supports' ), $errors );

		if ( array_key_exists( 'hidden', $config['post_types'] ) ) {
			if ( ! is_array( $config['post_types']['hidden'] ) ) {
				$errors[] = "'post_types.hidden' should be an array.";
			} else {
				foreach ( $config['post_types']['hidden'] as $post_type ) {
					if ( '' === sanitize_key( (string) $post_type ) ) {
						$errors[] = "'post_types.hidden' should contain only post type slugs.";
						break;
					}
				}
			}
		}

		if ( array_key_exists( 'disable_supports', $config['post_types'] ) ) {
			if ( ! is_array( $config['post_types']['disable_supports'] ) ) {
				$errors[] = "'post_types.disable_supports' should be an array.";
			} else {
				foreach ( $config['post_types']['disable_supports'] as $post_type => $supports ) {
					if ( '' === sanitize_key( (string) $post_type ) || ! is_array( $supports ) ) {
						$errors[] = "'post_types.disable_supports' should map post type slugs to arrays of support keys.";
						break;
					}

					foreach ( $supports as $support ) {
						if ( '' === sanitize_key( (string) $support ) ) {
							$errors[] = "'post_types.disable_supports.{$post_type}' should contain only support keys.";
							break 2;
						}
					}
				}
			}
		}
	}

	/**
	 * Validate security settings.
	 *
	 * @param array $config Raw config array.
	 * @param array $errors Error accumulator.
	 */
	private static function validate_security( array $config, array &$errors ): void {
		if ( ! array_key_exists( 'security', $config ) ) {
			return;
		}

		if ( ! is_array( $config['security'] ) ) {
			$errors[] = "'security' should be an array.";
			return;
		}

		self::validate_unknown_nested_keys( 'security', $config['security'], array_keys( self::security_defaults() ), $errors );

		if ( array_key_exists( 'headers', $config['security'] ) ) {
			if ( ! is_array( $config['security']['headers'] ) ) {
				$errors[] = "'security.headers' should be an array.";
			} else {
				foreach ( $config['security']['headers'] as $name => $value ) {
					if ( '' === trim( (string) $name ) || ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
						$errors[] = "'security.headers' should map non-empty header names to non-empty values.";
						break;
					}
				}
			}
		}

		foreach ( array( 'disable_author_archives', 'hide_wp_version_from_scripts', 'remove_pingback_header', 'disable_file_editing', 'add_noindex_headers' ) as $key ) {
			if ( array_key_exists( $key, $config['security'] ) && ! self::is_bool_like( $config['security'][ $key ] ) ) {
				$errors[] = "'security.{$key}' should be a boolean.";
			}
		}
	}

	/**
	 * Validate custom rules.
	 *
	 * @param array $config Raw config array.
	 * @param array $errors Error accumulator.
	 */
	private static function validate_custom_rules( array $config, array &$errors ): void {
		if ( ! array_key_exists( 'custom_rules', $config ) ) {
			return;
		}

		if ( ! is_array( $config['custom_rules'] ) ) {
			$errors[] = "'custom_rules' should be an array.";
			return;
		}

		foreach ( $config['custom_rules'] as $name => $rule ) {
			if ( '' === trim( (string) $name ) ) {
				$errors[] = "'custom_rules' contains an invalid rule name.";
				continue;
			}

			if ( ! is_array( $rule ) ) {
				if ( ! self::custom_rule_callback_looks_callable( $rule ) ) {
					$errors[] = "Custom rule '{$name}' does not appear to be callable.";
				}
				continue;
			}

			self::validate_unknown_nested_keys(
				"custom_rules.{$name}",
				$rule,
				array( 'callback', 'hook', 'priority', 'admin_only', 'front_only' ),
				$errors
			);

			if ( ! array_key_exists( 'callback', $rule ) ) {
				$errors[] = "Custom rule '{$name}' should define a callable 'callback'.";
				continue;
			}

			if ( ! self::custom_rule_callback_looks_callable( $rule['callback'] ) ) {
				$errors[] = "Custom rule '{$name}' does not appear to be callable.";
			}

			if (
				array_key_exists( 'hook', $rule ) &&
				( ! is_scalar( $rule['hook'] ) || '' === trim( (string) $rule['hook'] ) )
			) {
				$errors[] = "'custom_rules.{$name}.hook' should be a non-empty string.";
			}

			if ( array_key_exists( 'priority', $rule ) && ! is_numeric( $rule['priority'] ) ) {
				$errors[] = "'custom_rules.{$name}.priority' should be numeric.";
			}

			foreach ( array( 'admin_only', 'front_only' ) as $key ) {
				if ( array_key_exists( $key, $rule ) && ! self::is_bool_like( $rule[ $key ] ) ) {
					$errors[] = "'custom_rules.{$name}.{$key}' should be a boolean.";
				}
			}

			if (
				! empty( $rule['admin_only'] ) &&
				! empty( $rule['front_only'] )
			) {
				$errors[] = "Custom rule '{$name}' cannot be both admin_only and front_only.";
			}
		}
	}

	/**
	 * Normalize locked_options to a clean key => value map.
	 *
	 * @param mixed $value Raw section value.
	 * @return array
	 */
	private static function normalize_locked_options( $value ): array {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return array();
		}

		$clean = array();
		foreach ( $value as $key => $val ) {
			if ( is_string( $key ) && '' !== $key ) {
				$clean[ $key ] = $val;
			}
		}
		return $clean;
	}

	/**
	 * Validate the locked_options section.
	 *
	 * @param array    $config Raw config.
	 * @param string[] $errors Error accumulator.
	 */
	private static function validate_locked_options( array $config, array &$errors ): void {
		if ( ! array_key_exists( 'locked_options', $config ) ) {
			return;
		}

		if ( ! is_array( $config['locked_options'] ) ) {
			$errors[] = "'locked_options' should be an associative array of option_name => value pairs.";
			return;
		}

		foreach ( $config['locked_options'] as $key => $value ) {
			if ( ! is_string( $key ) || '' === $key ) {
				$errors[] = "'locked_options' keys must be non-empty strings (WordPress option names).";
				break;
			}

			if ( false === $value ) {
				$errors[] = "'locked_options.{$key}' is set to false — pre_option_ cannot lock to literal false. Use 0, '', or 'no' instead.";
			}
		}
	}

	/**
	 * Add an error for unknown nested keys in a section.
	 *
	 * @param string   $section      Section name.
	 * @param array    $values       Raw section values.
	 * @param string[] $allowed_keys Allowed nested keys.
	 * @param array    $errors       Error accumulator.
	 */
	private static function validate_unknown_nested_keys( string $section, array $values, array $allowed_keys, array &$errors ): void {
		$unknown = array_diff( array_keys( $values ), $allowed_keys );

		if ( ! empty( $unknown ) ) {
			$errors[] = "Unknown {$section} keys: " . implode( ', ', $unknown );
		}
	}

	/**
	 * Check whether a section includes at least one known key.
	 *
	 * @param array $value    Raw section values.
	 * @param array $defaults Known keys.
	 * @return bool
	 */
	private static function has_known_keys( array $value, array $defaults ): bool {
		return ! empty( array_intersect_key( $value, $defaults ) );
	}

	/**
	 * Normalize a boolean-like value to bool.
	 *
	 * @param mixed $value   Raw value.
	 * @param bool  $default Fallback value.
	 * @return bool
	 */
	private static function normalize_bool( $value, bool $default = false ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( 1 === $value || '1' === $value ) {
			return true;
		}

		if ( 0 === $value || '0' === $value ) {
			return false;
		}

		return $default;
	}

	/**
	 * Check whether a value looks like a boolean config value.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private static function is_bool_like( $value ): bool {
		return is_bool( $value ) || 1 === $value || 0 === $value || '1' === $value || '0' === $value;
	}

	/**
	 * Normalize a positive number or null.
	 *
	 * @param mixed $value Raw value.
	 * @return float|int|null
	 */
	private static function normalize_positive_number_or_null( $value ) {
		if ( null === $value || '' === $value || ! is_numeric( $value ) ) {
			return null;
		}

		$number = (float) $value;

		if ( $number <= 0 ) {
			return null;
		}

		return floor( $number ) === $number ? (int) $number : $number;
	}

	/**
	 * Normalize a non-negative integer or null.
	 *
	 * @param mixed $value Raw value.
	 * @return int|null
	 */
	private static function normalize_non_negative_integer_or_null( $value ): ?int {
		if ( null === $value || '' === $value || ! is_numeric( $value ) ) {
			return null;
		}

		return max( 0, (int) $value );
	}

	/**
	 * Normalize a positive integer or null.
	 *
	 * @param mixed $value Raw value.
	 * @return int|null
	 */
	private static function normalize_positive_integer_or_null( $value ): ?int {
		if ( null === $value || '' === $value || ! is_numeric( $value ) ) {
			return null;
		}

		$number = (int) $value;

		return $number > 0 ? $number : null;
	}

	/**
	 * Neutral defaults for known feature flags.
	 *
	 * @return array
	 */
	private static function feature_defaults(): array {
		return array(
			'disable_file_editor'           => false,
			'disable_xmlrpc'                => false,
			'restrict_rest_api'             => false,
			'remove_wp_version'             => false,
			'disable_self_ping'             => false,
			'disable_admin_email_check'     => false,
			'disable_auto_update_ui'        => false,
			'disable_comments'              => false,
			'disable_block_editor'          => false,
			'disable_application_passwords' => false,
			'disable_dashboard_widgets'     => false,
			'disable_user_registration'     => false,
			'disable_customizer'            => false,
			'disable_widgets'               => false,
			'disable_search'                => false,
			'disable_feeds'                 => false,
			'disable_file_mods'             => false,
			'disable_updates'               => false,
			'force_ssl_admin'               => false,
			'disable_tagline_editing'       => false,
			'lock_permalink_structure'      => false,
			'disable_wp_cron'               => false,
		);
	}

	/**
	 * Neutral defaults for login settings.
	 *
	 * @return array
	 */
	private static function login_defaults(): array {
		return array(
			'disable_password_reset' => false,
			'hide_login_errors'      => false,
			'redirect_after_logout'  => '',
		);
	}

	/**
	 * Neutral defaults for content settings.
	 *
	 * @return array
	 */
	private static function content_defaults(): array {
		return array(
			'disable_revisions' => false,
			'revision_limit'    => null,
			'disable_autosave'  => false,
			'autosave_interval' => null,
			'disable_embeds'    => false,
			'disable_emojis'    => false,
		);
	}

	/**
	 * Neutral defaults for head cleanup settings.
	 *
	 * @return array
	 */
	private static function head_cleanup_defaults(): array {
		return array(
			'remove_rsd_link'      => false,
			'remove_wlwmanifest'   => false,
			'remove_shortlink'     => false,
			'remove_feed_links'    => false,
			'remove_rest_api_link' => false,
		);
	}

	/**
	 * Neutral defaults for security settings.
	 *
	 * @return array
	 */
	private static function security_defaults(): array {
		return array(
			'headers'                      => array(),
			'disable_author_archives'      => false,
			'hide_wp_version_from_scripts' => false,
			'remove_pingback_header'       => false,
			'disable_file_editing'         => false,
			'add_noindex_headers'          => false,
		);
	}

	/**
	 * Log a warning without breaking the site.
	 *
	 * @param string $message Warning message.
	 */
	private static function warn( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Check if the current user bypasses governance restrictions.
	 *
	 * @return bool
	 */
	public static function current_user_is_unrestricted(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		return self::user_is_unrestricted( wp_get_current_user() );
	}

	/**
	 * Check whether a specific user bypasses governance restrictions.
	 *
	 * @param \WP_User $user User object to evaluate.
	 * @return bool
	 */
	public static function user_is_unrestricted( \WP_User $user ): bool {
		$role = self::section( 'unrestricted_role', 'administrator' );

		if ( is_multisite() && is_super_admin( $user->ID ) ) {
			return true;
		}

		if ( in_array( $role, (array) $user->roles, true ) ) {
			return true;
		}

		$role_object = wp_roles()->get_role( $role );
		if ( ! $role_object instanceof \WP_Role ) {
			return false;
		}

		$required_caps = array_keys(
			array_filter(
				(array) $role_object->capabilities
			)
		);

		if ( empty( $required_caps ) ) {
			return false;
		}

		foreach ( $required_caps as $cap ) {
			if ( empty( $user->allcaps[ $cap ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Determine the environment-specific override file path.
	 *
	 * Given a base config path like `/path/to/wp-governance-config.php`,
	 * looks for `/path/to/wp-governance-config.{environment}.php`.
	 *
	 * @param string $base_path Base config file path.
	 * @return string Override file path if readable, empty string otherwise.
	 */
	private static function resolve_environment_path( string $base_path ): string {
		$env  = self::environment();
		$dir  = dirname( $base_path );
		$file = basename( $base_path, '.php' );
		$path = $dir . '/' . $file . '.' . $env . '.php';

		/**
		 * Filter the environment override file path.
		 *
		 * @param string $path        Resolved override path.
		 * @param string $environment Current environment type.
		 * @param string $base_path   Base config file path.
		 */
		$path = (string) apply_filters( 'wp_governance_environment_config_path', $path, $env, $base_path );

		return is_readable( $path ) ? $path : '';
	}

	/**
	 * Deep-merge two config arrays.
	 *
	 * Associative arrays are merged recursively (override keys win).
	 * Lists (sequential integer keys) and scalars are replaced entirely.
	 * Only include keys you want to change in the override file.
	 *
	 * @param array $base     Base config array.
	 * @param array $override Override values.
	 * @return array Merged result.
	 */
	public static function deep_merge( array $base, array $override ): array {
		foreach ( $override as $key => $value ) {
			if (
				is_array( $value ) &&
				! empty( $value ) &&
				isset( $base[ $key ] ) &&
				is_array( $base[ $key ] ) &&
				! array_is_list( $value ) &&
				! array_is_list( $base[ $key ] )
			) {
				// Both sides are non-empty associative arrays — recurse.
				$base[ $key ] = self::deep_merge( $base[ $key ], $value );
			} else {
				// Scalar, list, or type mismatch — override wins.
				$base[ $key ] = $value;
			}
		}

		return $base;
	}

	/**
	 * Reset cached config. Primarily for testing.
	 */
	public static function reset(): void {
		self::$config           = null;
		self::$path             = '';
		self::$sample_defaults  = null;
		self::$environment_path = '';
	}
}
