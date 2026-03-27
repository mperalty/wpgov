<?php

namespace WP_Governance\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Removes specified dashboard widgets.
 */
class Dashboard {

	private array $widgets;

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function __construct( array $widgets, array $config ) {
		$this->widgets = $widgets;
		$this->register();
	}

	private function register(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'remove_widgets' ), 999 );
	}

	/**
	 * Remove listed dashboard widgets.
	 */
	public function remove_widgets(): void {
		// Map of widget ID => [context, priority] for core dashboard widgets.
		$core_map = array(
			'dashboard_quick_press'     => array( 'side', 'core' ),
			'dashboard_primary'         => array( 'side', 'core' ),
			'dashboard_right_now'       => array( 'normal', 'core' ),
			'dashboard_activity'        => array( 'normal', 'core' ),
			'dashboard_site_health'     => array( 'normal', 'core' ),
			'dashboard_incoming_links'  => array( 'normal', 'core' ),
			'dashboard_plugins'         => array( 'normal', 'core' ),
			'dashboard_recent_drafts'   => array( 'side', 'core' ),
			'dashboard_recent_comments' => array( 'normal', 'core' ),
		);

		foreach ( $this->widgets as $widget_id ) {
			if ( isset( $core_map[ $widget_id ] ) ) {
				[$context, $priority] = $core_map[ $widget_id ];
				remove_meta_box( $widget_id, 'dashboard', $context );
			} else {
				// Try both contexts for unknown/third-party widgets.
				remove_meta_box( $widget_id, 'dashboard', 'normal' );
				remove_meta_box( $widget_id, 'dashboard', 'side' );
			}
		}
	}
}
