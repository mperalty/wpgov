<?php

namespace WP_Governance\Modules;

use WP_Governance\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Suppresses specific admin notices.
 */
class Notices {

	private array $notices;

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function __construct( array $notices, array $config ) {
		$this->notices = $notices;
		$this->register();
	}

	private function register(): void {
		if ( empty( $this->notices ) ) {
			return;
		}

		add_action( 'admin_init', array( $this, 'suppress_notices' ) );
	}

	/**
	 * Remove configured admin notices for governed users.
	 */
	public function suppress_notices(): void {
		if ( Config::current_user_is_unrestricted() ) {
			return;
		}

		foreach ( $this->notices as $notice ) {
			switch ( $notice ) {
				case 'update_nag':
					remove_action( 'admin_notices', 'update_nag', 3 );
					remove_action( 'network_admin_notices', 'update_nag', 3 );
					break;

				case 'try_gutenberg_panel':
					remove_action( 'try_gutenberg_panel', 'wp_try_gutenberg_panel' );
					break;

				default:
					remove_action( 'admin_notices', $notice );
					remove_action( 'network_admin_notices', $notice );
					break;
			}
		}
	}
}
