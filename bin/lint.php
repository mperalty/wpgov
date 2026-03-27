<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = array(
	$root . DIRECTORY_SEPARATOR . 'wp-governance.php',
	$root . DIRECTORY_SEPARATOR . 'wp-governance',
	$root . DIRECTORY_SEPARATOR . 'tests',
	$root . DIRECTORY_SEPARATOR . 'bin',
);

$files = array();

foreach ( $paths as $path ) {
	if ( is_file( $path ) ) {
		$files[] = $path;
		continue;
	}

	if ( ! is_dir( $path ) ) {
		continue;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file ) {
		if ( 'php' !== strtolower( (string) $file->getExtension() ) ) {
			continue;
		}

		$files[] = $file->getPathname();
	}
}

sort( $files );

$failures = array();

foreach ( $files as $file ) {
	$command = sprintf(
		'"%s" -l %s 2>&1',
		PHP_BINARY,
		escapeshellarg( $file )
	);

	$output = array();
	$code   = 0;
	exec( $command, $output, $code );

	if ( 0 === $code ) {
		continue;
	}

	$failures[] = $file;
	fwrite( STDERR, implode( PHP_EOL, $output ) . PHP_EOL );
}

if ( ! empty( $failures ) ) {
	fwrite( STDERR, sprintf( 'Syntax errors found in %d file(s).', count( $failures ) ) . PHP_EOL );
	exit( 1 );
}

fwrite( STDOUT, sprintf( 'Syntax OK for %d file(s).', count( $files ) ) . PHP_EOL );
