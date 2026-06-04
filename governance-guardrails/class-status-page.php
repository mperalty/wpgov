<?php

namespace GovGuard;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only admin page that displays active governance rules.
 * Visible only to users with the unrestricted role.
 */
class Status_Page {

	/**
	 * @var array
	 * @phpstan-var array<string, mixed>
	 */
	private array $config;

	/**
	 * @param array $config Governance config.
	 * @phpstan-param array<string, mixed> $config Governance config.
	 * @psalm-param array $config Governance config.
	 */
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
			__( 'Governance Guardrails', 'governance-guardrails' ),
			__( 'Governance Guardrails', 'governance-guardrails' ),
			'read',
			'governance-guardrails',
			array( $this, 'render' )
		);
	}

	public function render(): void {
		if ( ! Config::current_user_is_unrestricted() ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'governance-guardrails' ),
				esc_html__( 'Restricted', 'governance-guardrails' ),
				array( 'response' => 403 )
			);
		}

		$path     = Config::path();
		$mtime    = is_readable( $path ) ? filemtime( $path ) : false;
		$modified = false !== $mtime ? gmdate( 'Y-m-d H:i:s', $mtime ) . ' UTC' : 'N/A';

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Governance Guardrails - Active Rules', 'governance-guardrails' ) . '</h1>';

		// Meta info.
		echo '<table class="widefat fixed striped" style="max-width:600px;margin-bottom:20px;">';
		echo '<tbody>';
		$this->meta_row( __( 'Plugin Version', 'governance-guardrails' ), GOVGUARD_VERSION );
		$this->meta_row( __( 'Config File', 'governance-guardrails' ), $path );
		$this->meta_row( __( 'Last Modified', 'governance-guardrails' ), $modified );
		$this->meta_row( __( 'Unrestricted Role', 'governance-guardrails' ), $this->config['unrestricted_role'] ?? 'administrator' );
		echo '</tbody></table>';

		// Feature toggles.
		$this->render_features();

		// Menu restrictions.
		$this->render_list( __( 'Restricted Menu Slugs', 'governance-guardrails' ), $this->config['restricted_menu_slugs'] ?? array() );

		// Admin bar.
		$this->render_list( __( 'Removed Admin Bar Nodes', 'governance-guardrails' ), $this->config['remove_admin_bar_nodes'] ?? array() );

		// Dashboard widgets.
		$this->render_list( __( 'Removed Dashboard Widgets', 'governance-guardrails' ), $this->config['remove_dashboard_widgets'] ?? array() );

		// Capabilities.
		$this->render_capabilities();

		// Upload MIME types.
		$this->render_mime_types();
		$this->render_key_value( __( 'Upload Restrictions', 'governance-guardrails' ), $this->config['uploads'] ?? array() );

		// Login settings.
		$this->render_key_value( __( 'Login Restrictions', 'governance-guardrails' ), $this->config['login'] ?? array() );

		// Content settings.
		$this->render_key_value( __( 'Content Restrictions', 'governance-guardrails' ), $this->config['content'] ?? array() );

		// Head cleanup.
		$this->render_key_value( __( 'Head Cleanup', 'governance-guardrails' ), $this->config['head_cleanup'] ?? array() );

		// Suppressed notices.
		$this->render_list( __( 'Suppressed Admin Notices', 'governance-guardrails' ), $this->config['suppress_admin_notices'] ?? array() );

		// Admin footer.
		$this->render_key_value( __( 'Admin Footer', 'governance-guardrails' ), $this->config['admin_footer'] ?? array() );

		// Post types.
		$post_types = $this->config['post_types'] ?? array();
		if ( ! empty( $post_types['hidden'] ) ) {
			$this->render_list( __( 'Hidden Post Types', 'governance-guardrails' ), $post_types['hidden'] );
		}
		if ( ! empty( $post_types['disable_supports'] ) ) {
			echo '<h2>' . esc_html__( 'Disabled Post Type Supports', 'governance-guardrails' ) . '</h2>';
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
			__( 'Security Hardening', 'governance-guardrails' ),
			array_filter(
				$this->config['security'] ?? array(),
				static fn( mixed $value ): bool => ! is_array( $value )
			)
		);
		$sec_headers = $this->config['security']['headers'] ?? array();
		if ( is_array( $sec_headers ) && array() !== $sec_headers ) {
			$this->render_key_value( __( 'Security Headers', 'governance-guardrails' ), $sec_headers );
		}

		// Custom rules.
		$rule_rows = $this->custom_rule_rows();
		if ( ! empty( $rule_rows ) ) {
			$this->render_key_value( __( 'Custom Rules', 'governance-guardrails' ), $rule_rows );
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
		if ( ! is_array( $features ) || array() === $features ) {
			return;
		}

		echo '<h2>' . esc_html__( 'Feature Toggles', 'governance-guardrails' ) . '</h2>';
		echo '<table class="widefat fixed striped" style="max-width:600px;margin-bottom:20px;">';
		echo '<thead><tr><th>' . esc_html__( 'Feature', 'governance-guardrails' ) . '</th><th>' . esc_html__( 'Status', 'governance-guardrails' ) . '</th></tr></thead><tbody>';

		foreach ( $features as $key => $enabled ) {
			$status = $enabled
				? '<span style="color:#d63638;font-weight:600;">' . esc_html__( 'Enforced', 'governance-guardrails' ) . '</span>'
				: '<span style="color:#00a32a;">' . esc_html__( 'Off', 'governance-guardrails' ) . '</span>';
			echo '<tr>';
			echo '<td><code>' . esc_html( $key ) . '</code></td>';
			echo '<td>' . wp_kses_post( $status ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * @param array $items List items.
	 * @phpstan-param array<int, string> $items List items.
	 * @psalm-param array $items List items.
	 */
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
		if ( ! is_array( $deny ) || array() === $deny ) {
			return;
		}

		echo '<h2>' . esc_html__( 'Denied Capabilities', 'governance-guardrails' ) . '</h2>';
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
		if ( ! is_array( $mimes ) || array() === $mimes ) {
			return;
		}

		echo '<h2>' . esc_html__( 'Allowed Upload MIME Types', 'governance-guardrails' ) . '</h2>';
		echo '<table class="widefat fixed striped" style="max-width:600px;margin-bottom:20px;">';
		echo '<thead><tr><th>' . esc_html__( 'Extension', 'governance-guardrails' ) . '</th><th>' . esc_html__( 'MIME Type', 'governance-guardrails' ) . '</th></tr></thead><tbody>';

		foreach ( $mimes as $ext => $mime ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( $ext ) . '</code></td>';
			echo '<td><code>' . esc_html( $mime ) . '</code></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * @param array $settings Setting values.
	 * @phpstan-param array<string, mixed> $settings Setting values.
	 * @psalm-param array $settings Setting values.
	 */
	private function render_key_value( string $title, array $settings ): void {
		if ( empty( $settings ) ) {
			return;
		}

		echo '<h2>' . esc_html( $title ) . '</h2>';
		echo '<table class="widefat fixed striped" style="max-width:600px;margin-bottom:20px;">';
		echo '<thead><tr><th>' . esc_html__( 'Setting', 'governance-guardrails' ) . '</th><th>' . esc_html__( 'Value', 'governance-guardrails' ) . '</th></tr></thead><tbody>';

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
	 * @return array
	 * @phpstan-return array<string, string>
	 * @psalm-return array
	 */
	private function custom_rule_rows(): array {
		$rules = $this->config['custom_rules'] ?? array();
		if ( ! is_array( $rules ) || array() === $rules ) {
			return array();
		}

		$rows = array();

		foreach ( $rules as $name => $rule ) {
			$hook     = 'init';
			$priority = 10;
			$scope    = __( 'All requests', 'governance-guardrails' );

			if ( is_array( $rule ) ) {
				if ( ! empty( $rule['hook'] ) && is_scalar( $rule['hook'] ) ) {
					$hook = trim( (string) $rule['hook'] );
				}

				if ( array_key_exists( 'priority', $rule ) && is_numeric( $rule['priority'] ) ) {
					$priority = (int) $rule['priority'];
				}

				if ( ! empty( $rule['disabled'] ) ) {
					$scope = __( 'Disabled (conflicting scope)', 'governance-guardrails' );
				} elseif ( ! empty( $rule['admin_only'] ) ) {
					$scope = __( 'Admin only', 'governance-guardrails' );
				} elseif ( ! empty( $rule['front_only'] ) ) {
					$scope = __( 'Front-end only', 'governance-guardrails' );
				}
			}

			$rows[ (string) $name ] = implode(
				' | ',
				array(
					// translators: %s is the WordPress hook name used by a custom governance rule.
					sprintf( __( 'Hook: %s', 'governance-guardrails' ), $hook ),
					// translators: %d is the priority number used when registering a custom governance rule callback.
					sprintf( __( 'Priority: %d', 'governance-guardrails' ), $priority ),
					$scope,
				)
			);
		}

		return $rows;
	}
}
