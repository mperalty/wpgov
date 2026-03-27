<?php

namespace WP_Governance\Modules;

use WP_Governance\Config;

defined( 'ABSPATH' ) || exit;

/**
 * Removes specified nodes from the WordPress admin bar.
 */
class Admin_Bar {

	private array $nodes;

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function __construct( array $nodes, array $config ) {
		$this->nodes = $nodes;
		$this->register();
	}

	private function register(): void {
		add_action( 'wp_before_admin_bar_render', array( $this, 'remove_nodes' ) );
	}

	public function remove_nodes(): void {
		if ( Config::current_user_is_unrestricted() ) {
			return;
		}

		global $wp_admin_bar;

		if ( ! $wp_admin_bar instanceof \WP_Admin_Bar ) {
			return;
		}

		foreach ( $this->nodes as $node_id ) {
			$wp_admin_bar->remove_node( $node_id );
		}
	}
}
