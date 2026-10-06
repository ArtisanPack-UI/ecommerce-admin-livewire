<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

/*
 * Blade prints a component tag it can't parse (an escaped quote inside an
 * attribute, for example) as literal HTML. Compile every view and make sure
 * no `<x-…>` tag survives outside PHP.
 */
it( 'compiles every component tag in every view', function (): void {
    $root  = dirname( __DIR__, 3 ) . '/resources/views';
    $files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
    $left  = [];

    foreach ( $files as $file ) {
        if ( ! str_ends_with( $file->getFilename(), '.blade.php' ) ) {
            continue;
        }

        $compiled = Blade::compileString( (string) file_get_contents( $file->getPathname() ) );
        $html     = (string) preg_replace( '/<\?php.*?\?>/s', '', $compiled );

        if ( 1 === preg_match( '/<\/?x[-:][\w.\-:]+/', $html, $match ) ) {
            $left[] = str_replace( $root . '/', '', $file->getPathname() ) . ': ' . $match[0];
        }
    }

    expect( $left )->toBe( [] );
} );
