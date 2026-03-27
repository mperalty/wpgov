<?php

namespace WP_Governance\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Restricts allowed upload MIME types when configured.
 */
class Uploads {

	private array $allowed;

	private array $settings;

	/** @var array<string, string>|null */
	private ?array $allowed_lookup = null;

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	public function __construct( array $allowed, array $config ) {
		$this->allowed  = $allowed;
		$this->settings = $config['uploads'] ?? array();
		$this->register();
	}

	private function register(): void {
		if ( empty( $this->allowed ) && ! $this->has_size_limit() ) {
			return;
		}

		if ( ! empty( $this->allowed ) ) {
			add_filter( 'upload_mimes', array( $this, 'filter_mimes' ), 999 );
			add_filter( 'wp_check_filetype_and_ext', array( $this, 'enforce_file_type' ), 999, 5 );
		}

		if ( $this->has_size_limit() ) {
			add_filter( 'upload_size_limit', array( $this, 'limit_upload_size' ), 999 );
			add_filter( 'wp_handle_upload_prefilter', array( $this, 'validate_upload_size' ), 999 );
			add_filter( 'wp_handle_sideload_prefilter', array( $this, 'validate_upload_size' ), 999 );
		}
	}

	/**
	 * Replace the allowed MIME types list.
	 *
	 * @param array $mimes Default allowed MIME types.
	 * @return array
	 */
	public function filter_mimes( array $mimes ): array {
		/**
		 * Filter allowed MIME types before enforcement.
		 *
		 * @param array $allowed The governance-configured MIME types.
		 * @param array $mimes   The original WordPress MIME types.
		 */
		return apply_filters( 'wp_governance_allowed_mimes', $this->allowed, $mimes );
	}

	/**
	 * Enforce the configured MIME allowlist after WordPress inspects the upload.
	 *
	 * This only narrows what core has already accepted. It never widens the
	 * file types allowed by WordPress.
	 *
	 * @param array       $data     File type data from WordPress.
	 * @param string      $file     Temporary uploaded file path.
	 * @param string      $filename Original filename.
	 * @param array       $mimes    Allowed MIME types.
	 * @param string|bool $real_mime Real MIME type when available.
	 * @return array
	 */
	public function enforce_file_type( array $data, string $file, string $filename, array $mimes, $real_mime ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( empty( $data['ext'] ) || empty( $data['type'] ) ) {
			return $data;
		}

		$allowed = $this->normalized_allowed_mimes();
		if ( empty( $allowed ) ) {
			return $data;
		}

		$detected_ext  = strtolower( (string) $data['ext'] );
		$detected_type = strtolower( (string) $data['type'] );
		$resolved_name = ! empty( $data['proper_filename'] ) ? (string) $data['proper_filename'] : $filename;
		$filename_ext  = strtolower( pathinfo( $resolved_name, PATHINFO_EXTENSION ) );

		if ( ! isset( $allowed[ $detected_ext ] ) || ! isset( $allowed[ $filename_ext ] ) ) {
			return $this->rejected_file_type( $data );
		}

		if ( strtolower( $allowed[ $detected_ext ] ) !== $detected_type ) {
			return $this->rejected_file_type( $data );
		}

		return $data;
	}

	/**
	 * Cap the upload size limit exposed to WordPress.
	 *
	 * @param int $limit Current upload size limit in bytes.
	 * @return int
	 */
	public function limit_upload_size( int $limit ): int {
		$configured = $this->max_upload_size_bytes();

		if ( $configured <= 0 ) {
			return $limit;
		}

		if ( $limit <= 0 ) {
			return $configured;
		}

		return min( $limit, $configured );
	}

	/**
	 * Reject oversized uploads before WordPress moves them into place.
	 *
	 * @param array $file Uploaded file data.
	 * @return array
	 */
	public function validate_upload_size( array $file ): array {
		$limit = $this->max_upload_size_bytes();

		if ( $limit <= 0 || empty( $file['size'] ) || ! empty( $file['error'] ) ) {
			return $file;
		}

		if ( (int) $file['size'] <= $limit ) {
			return $file;
		}

		$file['error'] = sprintf(
			/* translators: %s: upload size limit in MB */
			__( 'This file exceeds the governance upload limit of %s MB.' ),
			rtrim( rtrim( number_format_i18n( $limit / MB_IN_BYTES, 2 ), '0' ), '.' )
		);

		return $file;
	}

	/**
	 * Check whether upload size governance is active.
	 */
	private function has_size_limit(): bool {
		return $this->max_upload_size_bytes() > 0;
	}

	/**
	 * Convert the configured MB limit into bytes.
	 */
	private function max_upload_size_bytes(): int {
		$limit = $this->settings['max_upload_size_mb'] ?? null;

		if ( null === $limit || '' === $limit || ! is_numeric( $limit ) ) {
			return 0;
		}

		return max( 0, (int) round( (float) $limit * MB_IN_BYTES ) );
	}

	/**
	 * Expand extension groups like jpg|jpeg|jpe into direct lookups.
	 *
	 * @return array<string, string>
	 */
	private function normalized_allowed_mimes(): array {
		if ( null !== $this->allowed_lookup ) {
			return $this->allowed_lookup;
		}

		$allowed = apply_filters( 'wp_governance_allowed_mimes', $this->allowed, wp_get_mime_types() );
		$lookup  = array();

		foreach ( $allowed as $extensions => $mime ) {
			foreach ( explode( '|', strtolower( (string) $extensions ) ) as $extension ) {
				if ( '' === $extension ) {
					continue;
				}

				$lookup[ $extension ] = (string) $mime;
			}
		}

		$this->allowed_lookup = $lookup;

		return $this->allowed_lookup;
	}

	/**
	 * Return a rejected file-type payload compatible with WordPress core.
	 *
	 * @param array $data Existing file type data.
	 * @return array
	 */
	private function rejected_file_type( array $data ): array {
		$data['ext']  = false;
		$data['type'] = false;

		return $data;
	}
}
