<?php

namespace {

if ( ! class_exists( 'WP_CLI_Command', false ) ) {
	abstract class WP_CLI_Command {}
}

if ( ! class_exists( 'WP_CLI', false ) ) {
	class WP_CLI {
		public static array $logs = [];
		public static array $warnings = [];
		public static array $successes = [];
		public static array $printed_values = [];

		public static function reset(): void {
			self::$logs = [];
			self::$warnings = [];
			self::$successes = [];
			self::$printed_values = [];
		}

		public static function log( string $message ): void {
			self::$logs[] = $message;
		}

		public static function warning( string $message ): void {
			self::$warnings[] = $message;
		}

		public static function success( string $message ): void {
			self::$successes[] = $message;
		}

		public static function error( string $message ): void {
			throw new \RuntimeException( $message );
		}

		public static function print_value( $value, array $args = [] ): void {
			self::$printed_values[] = [
				'value' => $value,
				'args'  => $args,
			];
		}

		public static function colorize( string $message ): string {
			return $message;
		}
	}
}
}

namespace WP_CLI {

if ( ! class_exists( Formatter::class, false ) ) {
	class Formatter {
		public static array $display_calls = [];

		public function __construct( array $assoc_args, array $fields ) {}

		public static function reset(): void {
			self::$display_calls = [];
		}

		public function display_items( array $items ): void {
			self::$display_calls[] = $items;
		}
	}
}
}

namespace {

use WP_Governance\Config;

require_once WP_GOVERNANCE_DIR . 'class-cli.php';

/**
 * Tests for the WP-CLI surface.
 */
class CLITest extends WP_UnitTestCase {

	/**
	 * Tracks filter callbacks added during each test so they can be cleaned up.
	 *
	 * @var array<array{string, callable, int}>
	 */
	private array $filters_to_remove = [];

	public function setUp(): void {
		parent::setUp();

		if ( ! method_exists( 'WP_CLI', 'reset' ) || ! method_exists( '\WP_CLI\Formatter', 'reset' ) ) {
			$this->markTestSkipped( 'WP-CLI test stubs are not available in this environment.' );
		}

		Config::reset();
		\WP_CLI::reset();
		\WP_CLI\Formatter::reset();
	}

	public function tearDown(): void {
		foreach ( $this->filters_to_remove as [ $tag, $callback, $priority ] ) {
			remove_filter( $tag, $callback, $priority );
		}
		$this->filters_to_remove = [];

		Config::reset();
		\WP_CLI::reset();
		\WP_CLI\Formatter::reset();
		parent::tearDown();
	}

	private function set_config_path( string $path ): void {
		$callback = static function () use ( $path ): string {
			return $path;
		};
		add_filter( 'wp_governance_config_path', $callback, 1 );
		$this->filters_to_remove[] = [ 'wp_governance_config_path', $callback, 1 ];
	}

	private function set_environment_config_path( string $path ): void {
		$callback = static function () use ( $path ): string {
			return $path;
		};
		add_filter( 'wp_governance_environment_config_path', $callback, 1 );
		$this->filters_to_remove[] = [ 'wp_governance_environment_config_path', $callback, 1 ];
	}

	public function test_diff_uses_sample_defaults_as_the_clean_baseline(): void {
		$this->set_config_path( WP_GOVERNANCE_DIR . 'wp-governance-config.php' );
		Config::reset();

		$cli = new \WP_Governance\CLI();
		$cli->diff( [], [] );

		$this->assertContains(
			'Config matches the shipped sample defaults; no overrides detected.',
			\WP_CLI::$successes
		);
		$this->assertSame( [], \WP_CLI\Formatter::$display_calls );
	}

	public function test_check_reports_nested_schema_warnings(): void {
		$this->set_config_path( __DIR__ . '/fixtures/invalid-nested-config.php' );
		Config::reset();

		$cli = new \WP_Governance\CLI();
		$cli->check( [], [] );

		$warnings = implode( "\n", \WP_CLI::$warnings );

		$this->assertStringContainsString( 'Unknown features keys: unknown_flag', $warnings );
		$this->assertStringContainsString( "'features.disable_xmlrpc' should be a boolean.", $warnings );
		$this->assertStringContainsString( "'uploads.max_upload_size_mb' should be a positive number or null.", $warnings );
		$this->assertStringContainsString( "'security.headers' should be an array.", $warnings );
	}

	// ── status ──────────────────────────────────────────────────

	public function test_status_shows_module_rows(): void {
		$this->set_config_path( __DIR__ . '/fixtures/valid-config.php' );
		Config::reset();

		$cli = new \WP_Governance\CLI();
		$cli->status( [], [] );

		$this->assertNotEmpty( \WP_CLI\Formatter::$display_calls );

		$rows    = \WP_CLI\Formatter::$display_calls[0];
		$modules = array_column( $rows, 'module' );

		$this->assertContains( 'Features', $modules );
		$this->assertContains( 'Admin Bar', $modules );
		$this->assertContains( 'Capabilities', $modules );
		$this->assertContains( 'Uploads', $modules );
		$this->assertContains( 'Security', $modules );
		$this->assertContains( 'Custom Rules', $modules );
	}

	public function test_status_marks_active_modules_as_enforcing(): void {
		$this->set_config_path( __DIR__ . '/fixtures/valid-config.php' );
		Config::reset();

		$cli = new \WP_Governance\CLI();
		$cli->status( [], [] );

		$rows    = \WP_CLI\Formatter::$display_calls[0];
		$indexed = array_column( $rows, null, 'module' );

		$this->assertSame( 'enforcing', $indexed['Admin Bar']['status'] );
		$this->assertSame( 'enforcing', $indexed['Features']['status'] );
		$this->assertSame( 'enforcing', $indexed['Security']['status'] );
	}

	public function test_status_marks_empty_modules_as_off(): void {
		$this->set_config_path( __DIR__ . '/fixtures/empty-config.php' );
		Config::reset();

		$cli = new \WP_Governance\CLI();
		$cli->status( [], [] );

		$rows = \WP_CLI\Formatter::$display_calls[0];

		foreach ( $rows as $row ) {
			$this->assertSame( 'off', $row['status'] );
		}
	}

	// ── features ────────────────────────────────────────────────

	public function test_features_lists_all_toggles(): void {
		$this->set_config_path( __DIR__ . '/fixtures/valid-config.php' );
		Config::reset();

		$cli = new \WP_Governance\CLI();
		$cli->features( [], [] );

		$rows = \WP_CLI\Formatter::$display_calls[0];
		$map  = array_column( $rows, 'status', 'feature' );

		$this->assertSame( 'enforced', $map['disable_xmlrpc'] );
		$this->assertSame( 'off', $map['disable_auto_update_ui'] );
	}

	public function test_features_filter_by_enforced(): void {
		$this->set_config_path( __DIR__ . '/fixtures/valid-config.php' );
		Config::reset();

		$cli = new \WP_Governance\CLI();
		$cli->features( [], [ 'status' => 'enforced' ] );

		$rows = \WP_CLI\Formatter::$display_calls[0];

		foreach ( $rows as $row ) {
			$this->assertSame( 'enforced', $row['status'] );
		}
	}

	public function test_features_filter_by_off(): void {
		$this->set_config_path( __DIR__ . '/fixtures/valid-config.php' );
		Config::reset();

		$cli = new \WP_Governance\CLI();
		$cli->features( [], [ 'status' => 'off' ] );

		$rows = \WP_CLI\Formatter::$display_calls[0];

		foreach ( $rows as $row ) {
			$this->assertSame( 'off', $row['status'] );
		}
	}

	public function test_features_empty_config_logs_message(): void {
		$this->set_config_path( __DIR__ . '/fixtures/empty-config.php' );
		Config::reset();

		$cli = new \WP_Governance\CLI();
		$cli->features( [], [] );

		$this->assertContains( 'No features configured.', \WP_CLI::$logs );
		$this->assertEmpty( \WP_CLI\Formatter::$display_calls );
	}

	// ── caps ────────────────────────────────────────────────────

	public function test_caps_lists_denied_capabilities(): void {
		$this->set_config_path( __DIR__ . '/fixtures/valid-config.php' );
		Config::reset();

		$cli = new \WP_Governance\CLI();
		$cli->caps( [], [] );

		$rows = \WP_CLI\Formatter::$display_calls[0];

		$this->assertNotEmpty( $rows );
		$this->assertArrayHasKey( 'role', $rows[0] );
		$this->assertArrayHasKey( 'capability', $rows[0] );
		$this->assertSame( 'denied', $rows[0]['status'] );
	}

	public function test_caps_filters_by_role(): void {
		$this->set_config_path( __DIR__ . '/fixtures/valid-config.php' );
		Config::reset();

		$cli = new \WP_Governance\CLI();
		$cli->caps( [], [ 'role' => 'editor' ] );

		$rows = \WP_CLI\Formatter::$display_calls[0];

		foreach ( $rows as $row ) {
			$this->assertSame( 'editor', $row['role'] );
		}
	}

	public function test_caps_empty_config_logs_message(): void {
		$this->set_config_path( __DIR__ . '/fixtures/empty-config.php' );
		Config::reset();

		$cli = new \WP_Governance\CLI();
		$cli->caps( [], [] );

		$this->assertContains( 'No capability denials configured.', \WP_CLI::$logs );
	}

	// ── menus ───────────────────────────────────────────────────

	public function test_menus_lists_restricted_slugs(): void {
		$this->set_config_path( __DIR__ . '/fixtures/valid-config.php' );
		Config::reset();

		$cli = new \WP_Governance\CLI();
		$cli->menus( [], [] );

		$rows  = \WP_CLI\Formatter::$display_calls[0];
		$slugs = array_column( $rows, 'slug' );

		$this->assertContains( 'tools.php', $slugs );
		$this->assertContains( 'options-general.php', $slugs );
	}

	public function test_menus_empty_config_logs_message(): void {
		$this->set_config_path( __DIR__ . '/fixtures/empty-config.php' );
		Config::reset();

		$cli = new \WP_Governance\CLI();
		$cli->menus( [], [] );

		$this->assertContains( 'No menu restrictions configured.', \WP_CLI::$logs );
	}

	// ── mimes ───────────────────────────────────────────────────

	public function test_mimes_lists_allowed_types(): void {
		$this->set_config_path( __DIR__ . '/fixtures/valid-config.php' );
		Config::reset();

		$cli = new \WP_Governance\CLI();
		$cli->mimes( [], [] );

		$rows = \WP_CLI\Formatter::$display_calls[0];
		$map  = array_column( $rows, 'mime_type', 'extension' );

		$this->assertSame( 'image/jpeg', $map['jpg|jpeg|jpe'] );
		$this->assertSame( 'image/png', $map['png'] );
	}

	public function test_mimes_empty_config_logs_message(): void {
		$this->set_config_path( __DIR__ . '/fixtures/empty-config.php' );
		Config::reset();

		$cli = new \WP_Governance\CLI();
		$cli->mimes( [], [] );

		$this->assertContains( 'No MIME type restrictions configured (using WordPress defaults).', \WP_CLI::$logs );
	}

	// ── get ─────────────────────────────────────────────────────

	public function test_get_outputs_section_data(): void {
		$this->set_config_path( __DIR__ . '/fixtures/valid-config.php' );
		Config::reset();

		$cli = new \WP_Governance\CLI();
		$cli->get( [ 'features' ], [ 'format' => 'json' ] );

		$this->assertNotEmpty( \WP_CLI::$printed_values );
		$entry = \WP_CLI::$printed_values[0];
		$this->assertIsArray( $entry['value'] );
		$this->assertTrue( $entry['value']['disable_xmlrpc'] );
	}

	public function test_get_errors_on_unknown_section(): void {
		$this->set_config_path( __DIR__ . '/fixtures/valid-config.php' );
		Config::reset();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Unknown config section: nonexistent' );

		$cli = new \WP_Governance\CLI();
		$cli->get( [ 'nonexistent' ], [] );
	}

	// ── export ──────────────────────────────────────────────────

	public function test_export_outputs_valid_json(): void {
		$this->set_config_path( __DIR__ . '/fixtures/valid-config.php' );
		Config::reset();

		$cli = new \WP_Governance\CLI();
		$cli->export( [], [] );

		$json = implode( '', \WP_CLI::$logs );
		$data = json_decode( $json, true );

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'features', $data );
		$this->assertArrayHasKey( 'unrestricted_role', $data );
	}

	public function test_export_pretty_includes_newlines(): void {
		$this->set_config_path( __DIR__ . '/fixtures/valid-config.php' );
		Config::reset();

		$cli = new \WP_Governance\CLI();
		$cli->export( [], [ 'pretty' => true ] );

		$json = implode( '', \WP_CLI::$logs );
		$this->assertStringContainsString( "\n", $json );
	}

	// ── check ───────────────────────────────────────────────────

	public function test_check_succeeds_for_valid_config(): void {
		$this->set_config_path( __DIR__ . '/fixtures/valid-config.php' );
		Config::reset();

		$cli = new \WP_Governance\CLI();
		$cli->check( [], [] );

		$successes = implode( "\n", \WP_CLI::$successes );
		$this->assertStringContainsString( 'File exists and returns a valid array', $successes );
		$this->assertStringContainsString( 'Config validation complete', $successes );
	}

	public function test_check_errors_for_missing_config(): void {
		$this->set_config_path( __DIR__ . '/fixtures/nonexistent.php' );
		Config::reset();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Config file not found.' );

		$cli = new \WP_Governance\CLI();
		$cli->check( [], [] );
	}

	public function test_check_errors_for_malformed_config(): void {
		$this->set_config_path( __DIR__ . '/fixtures/malformed-config.php' );
		Config::reset();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Config file does not return a PHP array.' );

		$cli = new \WP_Governance\CLI();
		$cli->check( [], [] );
	}

	public function test_check_errors_for_syntax_error_config(): void {
		$this->set_config_path( __DIR__ . '/fixtures/syntax-error-config.php' );
		Config::reset();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Config file could not be loaded.' );

		$cli = new \WP_Governance\CLI();
		$cli->check( [], [] );
	}

	public function test_check_errors_for_broken_environment_override(): void {
		$this->set_config_path( __DIR__ . '/fixtures/minimal-config.php' );
		$this->set_environment_config_path( __DIR__ . '/fixtures/syntax-error-override.php' );
		Config::reset();

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Environment override could not be loaded.' );

		$cli = new \WP_Governance\CLI();
		$cli->check( [], [] );
	}
}
}
