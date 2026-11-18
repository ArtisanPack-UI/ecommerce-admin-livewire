<?php

declare( strict_types=1 );

/*
 * Livewire 4 reads wire:model modifiers before `.live` as client-side
 * timing only: `wire:model.blur` updates the browser's copy on blur but
 * never sends a request, where Livewire 3 sent one. The admin supports
 * both, so a field that should reach the server on blur, change or Enter
 * says so with `.live` (`wire:model.live.blur`), which sends exactly one
 * request at that moment in both versions.
 */
it( 'puts .live before every blur, change or enter timing on wire:model', function (): void {
    $root      = dirname( __DIR__, 3 ) . '/resources/views';
    $files     = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
    $offending = [];

    foreach ( $files as $file ) {
        if ( ! str_ends_with( $file->getFilename(), '.blade.php' ) ) {
            continue;
        }

        foreach ( file( $file->getPathname() ) as $number => $line ) {
            if ( 0 === preg_match_all( '/wire:model((?:\.[A-Za-z0-9]+)+)=/', $line, $matches ) ) {
                continue;
            }

            foreach ( $matches[1] as $modifiers ) {
                $parts  = explode( '.', ltrim( $modifiers, '.' ) );
                $live   = array_search( 'live', $parts, true );
                $timing = array_intersect( $parts, [ 'blur', 'change', 'enter' ] );

                // A timing modifier only sends a request when it comes after `.live`.
                foreach ( array_keys( $timing ) as $position ) {
                    if ( false === $live || $position < $live ) {
                        $offending[] = str_replace( $root . '/', '', $file->getPathname() ) . ':' . ( $number + 1 ) . ' wire:model' . $modifiers;

                        break;
                    }
                }
            }
        }
    }

    expect( $offending )->toBe( [] );
} );
