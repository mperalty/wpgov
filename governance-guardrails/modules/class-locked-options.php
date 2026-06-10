<?php

namespace GovGuard\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Pins wp_options values from the config file and prevents database overrides.
 *
 * For each declared option, `pre_option_{$name}` returns the config value
 * (bypassing the database) and `pre_update_option_{$name}` silently rejects
 * writes. On admin settings pages, locked fields are visually disabled.
 *
 * Note: WordPress treats a `pre_option_` return of `false` as "not set —
 * fall through to the database." Locking an option to literal `false` is
 * therefore not supported; use `0`, `''`, or `'no'` instead.
 */
class Locked_Options {

	/**
	 * @var array locked value.
	 * @phpstan-var array<string, mixed> Option name => locked value.
	 */
	private array $options;

	/**
	 * @param array $options Option name => locked value.
	 * @phpstan-param array<string, mixed> $options Option name => locked value.
	 * @psalm-param array $options Option name => locked value.
	 * @param array $config Governance config.
	 * @phpstan-param array<string, mixed> $config Governance config.
	 * @psalm-param array $config Governance config.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function __construct( array $options, array $config ) {
		$this->options = $options;
		$this->lock();
	}

	/**
	 * Register filters for each locked option.
	 */
	private function lock(): void {
		foreach ( $this->options as $name => $value ) {
			// Override reads — return the config value instead of the database.
			add_filter( "pre_option_{$name}", static fn(): mixed => $value );

			// Block writes — return the old value so update_option() sees no change.
			add_filter(
				"pre_update_option_{$name}",
				static fn( mixed $new_value, mixed $old_value ): mixed => $old_value,
				10,
				2
			);
		}

		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this, 'show_notice' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_field_lock_script' ) );
		}
	}

	/**
	 * Show a warning on settings pages listing which options are locked.
	 */
	public function show_notice(): void {
		$screen = get_current_screen();
		if ( null === $screen ) {
			return;
		}

		if ( ! str_starts_with( $screen->id, 'options-' ) ) {
			return;
		}

		$locked = $this->locked_on_screen( $screen->id );
		if ( empty( $locked ) ) {
			return;
		}

		$count = count( $locked );
		$names = implode( ', ', $locked );

		echo '<div class="notice notice-warning"><p>';
		echo wp_kses(
			sprintf(
				/* translators: 1: number of locked settings, 2: comma-separated list of option names. */
				_n(
					'%1$s setting is locked by Governance Guardrails: %2$s',
					'%1$s settings are locked by Governance Guardrails: %2$s',
					$count,
					'governance-guardrails'
				),
				number_format_i18n( $count ),
				'<strong>' . esc_html( $names ) . '</strong>'
			),
			array( 'strong' => array() )
		);
		echo '</p></div>';
	}

	/**
	 * Disable form fields for locked options via an inline script on settings screens.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_field_lock_script( string $hook_suffix ): void {
		if ( ! str_starts_with( $hook_suffix, 'options-' ) ) {
			return;
		}

		$names = wp_json_encode( array_keys( $this->options ) );
		$title = wp_json_encode( __( 'Locked by Governance Guardrails', 'governance-guardrails' ) );

		if ( false === $names || false === $title ) {
			return;
		}

		$script = '(function(){var l=' . $names . ';'
			. 'l.forEach(function(n){'
			. 'document.querySelectorAll(\'[name="\'+n+\'"],[name="\'+n+\'[]"]\').forEach(function(e){'
			. 'e.disabled=true;e.style.opacity="0.5";e.title=' . $title . ';'
			. '});'
			// Permalink structure uses radio buttons with name="selection".
			. 'if(n==="permalink_structure"){'
			. 'document.querySelectorAll(\'input[name="selection"]\').forEach(function(e){'
			. 'e.disabled=true;e.style.opacity="0.5";'
			. '});}'
			. '});})();';

		wp_register_script( 'govguard-locked-options', false, array(), GOVGUARD_VERSION, true );
		wp_enqueue_script( 'govguard-locked-options' );
		wp_add_inline_script( 'govguard-locked-options', $script );
	}

	/**
	 * Return the locked option names that appear on a given settings screen.
	 *
	 * @param string $screen_id Admin screen ID.
	 * @return string[]
	 */
	private function locked_on_screen( string $screen_id ): array {
		$screen_options = array(
			'options-general'    => array(
				'blogname', 'blogdescription', 'siteurl', 'home', 'admin_email',
				'users_can_register', 'default_role', 'timezone_string',
				'date_format', 'time_format', 'start_of_week', 'WPLANG',
			),
			'options-writing'    => array( 'default_category', 'default_post_format' ),
			'options-reading'    => array(
				'posts_per_page', 'posts_per_rss', 'rss_use_excerpt',
				'blog_public', 'show_on_front', 'page_on_front', 'page_for_posts',
			),
			'options-discussion' => array(
				'default_pingback_flag', 'default_ping_status', 'default_comment_status',
				'require_name_email', 'comment_registration', 'close_comments_for_old_posts',
				'close_comments_days_old', 'thread_comments', 'thread_comments_depth',
				'page_comments', 'comments_per_page', 'default_comments_page',
				'comment_order', 'comment_moderation', 'moderation_notify',
				'comment_previously_approved', 'comment_max_links',
				'show_avatars', 'avatar_rating', 'avatar_default',
			),
			'options-media'      => array(
				'thumbnail_size_w', 'thumbnail_size_h', 'thumbnail_crop',
				'medium_size_w', 'medium_size_h', 'large_size_w', 'large_size_h',
			),
			'options-permalink'  => array( 'permalink_structure', 'category_base', 'tag_base' ),
		);

		$known = $screen_options[ $screen_id ] ?? array();

		return array_values( array_intersect( $known, array_keys( $this->options ) ) );
	}
}
