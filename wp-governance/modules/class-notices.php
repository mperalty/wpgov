<?php

namespace WP_Governance\Modules;

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
		foreach ( $this->notices as $notice ) {
			switch ( $notice ) {
				case 'update_nag':
					add_action(
						'admin_init',
						static function (): void {
							remove_action( 'admin_notices', 'update_nag', 3 );
							remove_action( 'network_admin_notices', 'update_nag', 3 );
						}
					);
					break;

				case 'try_gutenberg_panel':
					remove_action( 'try_gutenberg_panel', 'wp_try_gutenberg_panel' );
					break;

				default:
					// For custom notice hooks, attempt removal from both notice actions.
					add_action(
						'admin_init',
						static function () use ( $notice ): void {
							remove_action( 'admin_notices', $notice );
							remove_action( 'network_admin_notices', $notice );
						}
					);
					break;
			}
		}
	}
}
