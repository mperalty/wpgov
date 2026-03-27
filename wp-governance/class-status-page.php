<?php

namespace WP_Governance;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only admin page that displays active governance rules.
 * Visible only to users with the unrestricted role.
 */
class Status_Page {

	private array $config;

	public function __construct( array $config ) {
		$this->config = $config;
		add_action( 'admin_menu', array( $this, 'register_page' ) );
	}

	public function register_page(): void {
		if ( ! Config::current_user_is_unrestricted() ) {
			return;
		}

		// Use 'read' as the capability since access is already gated by
		// current_user_is_unrestricted() above. Using 'manage_options'
		// would make the page inaccessible when unrestricted_role is set
		// to a role that lacks that capability (e.g. 'editor').
		add_management_page(
			__( 'WP Governance' ),
			__( 'WP Governance' ),
			'read',
			'wp-governance',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		$path     = Config::path();
		$modified = is_readable( $path ) ? gmdate( 'Y-m-d H:i:s', filemtime( $path ) ) . ' UTC' : 'N/A';

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'WP Governance - Active Rules' ) . '</h1>';

		// Meta info.
		echo '<table class="widefat fixed striped" style="max-width:600px;margin-bottom:20px;">';
		echo '<tbody>';
		$this->meta_row( __( 'Plugin Version' ), WP_GOVERNANCE_VERSION );
		$this->meta_row( __( 'Config File' ), $path );
		$this->meta_row( __( 'Last Modified' ), $modified );
		$this->meta_row( __( 'Unrestricted Role' ), $this->config['unrestricted_role'] ?? 'administrator' );
		echo '</tbody></table>';

		// Feature toggles.
		$this->render_features();

		// Menu restrictions.
		$this->render_list( __( 'Restricted Menu Slugs' ), $this->config['restricted_menu_slugs'] ?? array() );

		// Admin bar.
		$this->render_list( __( 'Removed Admin Bar Nodes' ), $this->config['remove_admin_bar_nodes'] ?? array() );

		// Dashboard widgets.
		$this->render_list( __( 'Removed Dashboard Widgets' ), $this->config['remove_dashboard_widgets'] ?? array() );

		// Capabilities.
		$this->render_capabilities();

		// Upload MIME types.
		$this->render_mime_types();
		$this->render_key_value( __( 'Upload Restrictions' ), $this->config['uploads'] ?? array() );

		// Login settings.
		$this->render_key_value( __( 'Login Restrictions' ), $this->config['login'] ?? array() );

		// Content settings.
		$this->render_key_value( __( 'Content Restrictions' ), $this->config['content'] ?? array() );

		// Head cleanup.
		$this->render_key_value( __( 'Head Cleanup' ), $this->config['head_cleanup'] ?? array() );

		// Suppressed notices.
		$this->render_list( __( 'Suppressed Admin Notices' ), $this->config['suppress_admin_notices'] ?? array() );

		// Admin footer.
		$this->render_key_value( __( 'Admin Footer' ), $this->config['admin_footer'] ?? array() );

		// Post types.
		$post_types = $this->config['post_types'] ?? array();
		if ( ! empty( $post_types['hidden'] ) ) {
			$this->render_list( __( 'Hidden Post Types' ), $post_types['hidden'] );
		}
		if ( ! empty( $post_types['disable_supports'] ) ) {
			echo '<h2>' . esc_html__( 'Disabled Post Type Supports' ) . '</h2>';
			foreach ( $post_types['disable_supports'] as $pt => $supports ) {
				echo '<h3 style="margin-bottom:5px;"><code>' . esc_html( $pt ) . '</code></h3>';
				echo '<ul style="margin-left:20px;margin-top:0;">';
				foreach ( (array) $supports as $support ) {
					echo '<li><code>' . esc_html( $support ) . '</code></li>';
				}
				echo '</ul>';
			}
		}

		// Security.
		$this->render_key_value(
			__( 'Security Hardening' ),
			array_filter(
				$this->config['security'] ?? array(),
				fn( $value ) => ! is_array( $value )
			)
		);
		$sec_headers = $this->config['security']['headers'] ?? array();
		if ( ! empty( $sec_headers ) ) {
			$this->render_key_value( __( 'Security Headers' ), $sec_headers );
		}

		// Custom rules.
		$rule_rows = $this->custom_rule_rows();
		if ( ! empty( $rule_rows ) ) {
			$this->render_key_value( __( 'Custom Rules' ), $rule_rows );
		}

		echo '</div>';
	}

	private function meta_row( string $label, string $value ): void {
		echo '<tr>';
		echo '<td><strong>' . esc_html( $label ) . '</strong></td>';
		echo '<td><code>' . esc_html( $value ) . '</code></td>';
		echo '</tr>';
	}

	private function render_features(): void {
		$features = $this->config['features'] ?? array();
		if ( empty( $features ) ) {
			return;
		}

		echo '<h2>' . esc_html__( 'Feature Toggles' ) . '</h2>';
		echo '<table class="widefat fixed striped" style="max-width:600px;margin-bottom:20px;">';
		echo '<thead><tr><th>' . esc_html__( 'Feature' ) . '</th><th>' . esc_html__( 'Status' ) . '</th></tr></thead><tbody>';

		foreach ( $features as $key => $enabled ) {
			$status = $enabled
				? '<span style="color:#d63638;font-weight:600;">' . esc_html__( 'Enforced' ) . '</span>'
				: '<span style="color:#00a32a;">' . esc_html__( 'Off' ) . '</span>';
			echo '<tr>';
			echo '<td><code>' . esc_html( $key ) . '</code></td>';
			echo '<td>' . wp_kses_post( $status ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	private function render_list( string $title, array $items ): void {
		if ( empty( $items ) ) {
			return;
		}

		echo '<h2>' . esc_html( $title ) . '</h2>';
		echo '<ul style="margin-left:20px;">';
		foreach ( $items as $item ) {
			echo '<li><code>' . esc_html( $item ) . '</code></li>';
		}
		echo '</ul>';
	}

	private function render_capabilities(): void {
		$deny = $this->config['deny_capabilities'] ?? array();
		if ( empty( $deny ) ) {
			return;
		}

		echo '<h2>' . esc_html__( 'Denied Capabilities' ) . '</h2>';
		foreach ( $deny as $role => $caps ) {
			echo '<h3 style="margin-bottom:5px;"><code>' . esc_html( $role ) . '</code></h3>';
			echo '<ul style="margin-left:20px;margin-top:0;">';
			foreach ( $caps as $cap ) {
				echo '<li><code>' . esc_html( $cap ) . '</code></li>';
			}
			echo '</ul>';
		}
	}

	private function render_mime_types(): void {
		$mimes = $this->config['allowed_mime_types'] ?? array();
		if ( empty( $mimes ) ) {
			return;
		}

		echo '<h2>' . esc_html__( 'Allowed Upload MIME Types' ) . '</h2>';
		echo '<table class="widefat fixed striped" style="max-width:600px;margin-bottom:20px;">';
		echo '<thead><tr><th>' . esc_html__( 'Extension' ) . '</th><th>' . esc_html__( 'MIME Type' ) . '</th></tr></thead><tbody>';

		foreach ( $mimes as $ext => $mime ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( $ext ) . '</code></td>';
			echo '<td><code>' . esc_html( $mime ) . '</code></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	private function render_key_value( string $title, array $settings ): void {
		if ( empty( $settings ) ) {
			return;
		}

		echo '<h2>' . esc_html( $title ) . '</h2>';
		echo '<table class="widefat fixed striped" style="max-width:600px;margin-bottom:20px;">';
		echo '<thead><tr><th>' . esc_html__( 'Setting' ) . '</th><th>' . esc_html__( 'Value' ) . '</th></tr></thead><tbody>';

		foreach ( $settings as $key => $value ) {
			$display = is_bool( $value ) ? ( $value ? 'true' : 'false' ) : (string) $value;
			echo '<tr>';
			echo '<td><code>' . esc_html( $key ) . '</code></td>';
			echo '<td><code>' . esc_html( $display ) . '</code></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Build display rows for custom rules.
	 *
	 * @return array<string, string>
	 */
	private function custom_rule_rows(): array {
		$rules = $this->config['custom_rules'] ?? array();
		if ( empty( $rules ) ) {
			return array();
		}

		$rows = array();

		foreach ( $rules as $name => $rule ) {
			$hook     = 'init';
			$priority = 10;
			$scope    = __( 'All requests' );

			if ( is_array( $rule ) ) {
				if ( ! empty( $rule['hook'] ) && is_scalar( $rule['hook'] ) ) {
					$hook = trim( (string) $rule['hook'] );
				}

				if ( array_key_exists( 'priority', $rule ) && is_numeric( $rule['priority'] ) ) {
					$priority = (int) $rule['priority'];
				}

				if ( ! empty( $rule['disabled'] ) ) {
					$scope = __( 'Disabled (conflicting scope)' );
				} elseif ( ! empty( $rule['admin_only'] ) ) {
					$scope = __( 'Admin only' );
				} elseif ( ! empty( $rule['front_only'] ) ) {
					$scope = __( 'Front-end only' );
				}
			}

			$rows[ (string) $name ] = implode(
				' | ',
				array(
					sprintf( __( 'Hook: %s' ), $hook ),
					sprintf( __( 'Priority: %d' ), $priority ),
					$scope,
				)
			);
		}

		return $rows;
	}
}
