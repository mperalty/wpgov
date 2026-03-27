<?php

namespace WP_Governance\Modules;

use WP_Governance\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Denies specific capabilities for specified roles at runtime.
 *
 * Uses the `user_has_cap` filter so no database changes are made.
 * Unrestricted role users bypass all denials.
 */
class Capabilities {

	/** @var array<string, string[]> Role => denied caps. */
	private array $deny_map;

	/** @var array<string, array<string, bool>> Pre-filtered deny map lookups keyed by role. */
	private array $filtered_deny_cache = array();

	/** @var array<string, array<string, bool>> Merged deny lookups keyed by sorted role list. */
	private array $merged_deny_cache = array();

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Consistent module interface.
	public function __construct( array $deny_map, array $config ) {
		$this->deny_map = $deny_map;
		$this->register();
	}

	private function register(): void {
		add_filter( 'user_has_cap', array( $this, 'filter_caps' ), 10, 4 );
	}

	/**
	 * Get a denied-capability lookup for a role, applying the filter once.
	 *
	 * @param string $role Role slug.
	 * @return array<string, bool>
	 */
	private function get_denied_for_role( string $role ): array {
		if ( ! isset( $this->filtered_deny_cache[ $role ] ) ) {
			$denied = $this->deny_map[ $role ] ?? array();

			/**
			 * Filter denied capabilities for a role.
			 *
			 * @param string[] $denied Capabilities to deny.
			 * @param string   $role   The role slug.
			 */
			$lookup = array();

			foreach ( (array) apply_filters( 'wp_governance_deny_caps', $denied, $role ) as $cap ) {
				$cap = sanitize_key( (string) $cap );
				if ( '' !== $cap ) {
					$lookup[ $cap ] = true;
				}
			}

			$this->filtered_deny_cache[ $role ] = $lookup;
		}

		return $this->filtered_deny_cache[ $role ];
	}

	/**
	 * Merge denied capabilities for the user's roles once per unique role set.
	 *
	 * @param string[] $roles Role slugs.
	 * @return array<string, bool>
	 */
	private function get_denied_for_roles( array $roles ): array {
		$roles = array_values(
			array_filter(
				array_map(
					static fn( string $role ): string => sanitize_key( $role ),
					$roles
				)
			)
		);

		if ( empty( $roles ) ) {
			return array();
		}

		sort( $roles );

		$cache_key = implode( '|', $roles );
		if ( ! isset( $this->merged_deny_cache[ $cache_key ] ) ) {
			$merged = array();

			foreach ( $roles as $role ) {
				$merged += $this->get_denied_for_role( $role );
			}

			$this->merged_deny_cache[ $cache_key ] = $merged;
		}

		return $this->merged_deny_cache[ $cache_key ];
	}

	/**
	 * Filter user capabilities at runtime.
	 *
	 * @param bool[]   $allcaps All capabilities for the user.
	 * @param string[] $caps    Required primitive capabilities for the requested check.
	 * @param array    $args    [0] = requested capability, [1] = user ID.
	 * @param \WP_User $user    The user object.
	 * @return bool[]
	 */
	public function filter_caps( array $allcaps, array $caps, array $args, \WP_User $user ): array {
		// Unrestricted users bypass.
		if ( Config::user_is_unrestricted( $user ) ) {
			return $allcaps;
		}

		$denied = $this->get_denied_for_roles( (array) $user->roles );
		if ( empty( $denied ) ) {
			return $allcaps;
		}

		foreach ( $caps as $cap ) {
			$cap = sanitize_key( (string) $cap );
			if ( isset( $denied[ $cap ] ) ) {
				$allcaps[ $cap ] = false;
			}
		}

		$requested_cap = sanitize_key( (string) ( $args[0] ?? '' ) );
		if ( '' !== $requested_cap && isset( $denied[ $requested_cap ] ) ) {
			$allcaps[ $requested_cap ] = false;
		}

		return $allcaps;
	}
}
