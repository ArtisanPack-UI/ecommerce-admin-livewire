<?php

declare( strict_types=1 );

/*
 * Move and remove buttons act on a row's position, so a double click that
 * lands after the first round trip moves or removes the wrong row. Every one
 * is disabled while its request is in flight.
 */
it( 'disables every move and remove button while its request runs', function (): void {
    $root    = dirname( __DIR__, 3 ) . '/resources/views';
    $files   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
    $missing = [];

    foreach ( $files as $file ) {
        if ( ! str_ends_with( $file->getFilename(), '.blade.php' ) ) {
            continue;
        }

        foreach ( file( $file->getPathname() ) as $number => $line ) {
            if ( 1 === preg_match( '/wire:click="(move|remove)[A-Za-z]*\(/', $line ) && ! str_contains( $line, 'wire:loading.attr="disabled"' ) ) {
                $missing[] = str_replace( $root . '/', '', $file->getPathname() ) . ':' . ( $number + 1 );
            }
        }
    }

    expect( $missing )->toBe( [] );
} );
