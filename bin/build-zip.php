<?php
/**
 * Build the WordPress.org submission zip.
 *
 * Usage: php bin/build-zip.php
 *
 * Produces governance-guardrails-{version}.zip in the repository root with
 * the same layout the plugin review team receives:
 *
 *   governance-guardrails/
 *     governance-guardrails.php
 *     readme.txt
 *     LICENSE
 *     languages/index.php
 *     governance-guardrails/   (runtime classes + sample config)
 */

$root = dirname( __DIR__ );

// Read the version from the main plugin file header.
$main = file_get_contents( $root . '/governance-guardrails.php' );
if ( false === $main || 1 !== preg_match( '/^\s*\*\s*Version:\s*(.+)$/m', $main, $matches ) ) {
	fwrite( STDERR, "Could not read the plugin version from governance-guardrails.php\n" );
	exit( 1 );
}
$version  = trim( $matches[1] );
$zip_path = $root . "/governance-guardrails-{$version}.zip";

$files = array(
	'governance-guardrails.php' => 'governance-guardrails/governance-guardrails.php',
	'readme.txt'                => 'governance-guardrails/readme.txt',
	'LICENSE'                   => 'governance-guardrails/LICENSE',
	'languages/index.php'       => 'governance-guardrails/languages/index.php',
);

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root . '/governance-guardrails', FilesystemIterator::SKIP_DOTS )
);
foreach ( $iterator as $file ) {
	if ( $file->isFile() ) {
		$relative           = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
		$files[ $relative ] = 'governance-guardrails/' . $relative;
	}
}

if ( file_exists( $zip_path ) && ! unlink( $zip_path ) ) {
	fwrite( STDERR, "Could not remove the existing {$zip_path}\n" );
	exit( 1 );
}

$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path, ZipArchive::CREATE ) ) {
	fwrite( STDERR, "Could not create {$zip_path}\n" );
	exit( 1 );
}

ksort( $files );

foreach ( $files as $source => $target ) {
	if ( ! $zip->addFile( $root . '/' . $source, $target ) ) {
		fwrite( STDERR, "Could not add {$source} to the zip\n" );
		exit( 1 );
	}
}

$zip->close();

echo 'Built ' . basename( $zip_path ) . ' (' . count( $files ) . " files)\n";
