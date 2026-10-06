<?php

declare( strict_types=1 );

/*
 * A link that opens a new tab says so to screen reader users (WCAG 3.2.5).
 */
it( 'announces every link that opens a new tab', function (): void {
    $root    = dirname( __DIR__, 3 ) . '/resources/views';
    $files   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
    $missing = [];

    foreach ( $files as $file ) {
        if ( ! str_ends_with( $file->getFilename(), '.blade.php' ) ) {
            continue;
        }

        preg_match_all( '/<a\b[^>]*target="_blank"[^>]*>(.*?)<\/a>/s', (string) file_get_contents( $file->getPathname() ), $links, PREG_SET_ORDER );

        foreach ( $links as $link ) {
            if ( ! str_contains( $link[1], "__( '(opens in a new tab)' )" ) ) {
                $missing[] = str_replace( $root . '/', '', $file->getPathname() ) . ': ' . trim( mb_substr( $link[0], 0, 80 ) );
            }
        }
    }

    expect( $missing )->toBe( [] );
} );
