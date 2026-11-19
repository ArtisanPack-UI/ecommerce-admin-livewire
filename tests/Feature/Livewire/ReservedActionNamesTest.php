<?php

declare( strict_types=1 );

/*
 * `$wire` answers some names itself before it calls the component: the file
 * upload calls (`upload`, `uploadMultiple`, `removeUpload`, `cancelUpload`)
 * and, on Livewire 4, aliases such as `get`, `set`, `call`, `refresh`,
 * `watch` and `entangle`. A component action with one of those names never
 * runs: `wire:submit="upload"` started an upload with no file and failed in
 * the browser on `file.name`. Server-side tests call the action directly, so
 * they can't catch it.
 */

const LIVEWIRE_UPLOAD_MAGICS = [ 'upload', 'uploadMultiple', 'removeUpload', 'cancelUpload' ];

const LIVEWIRE_WIRE_ALIASES = [
	'get', 'set', 'call', 'on', 'js', 'el', 'id', 'refs', 'hook', 'watch', 'dirty', 'effect', 'commit',
	'errors', 'island', 'entangle', 'refresh', 'parent', 'toggle', 'intercept', 'dispatch', 'dispatchTo', 'dispatchSelf',
];

it( 'gives no component an action that $wire answers itself', function (): void {
	$root      = dirname( __DIR__, 3 ) . '/src/Livewire';
	$files     = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	$colliding = [];

	foreach ( $files as $file ) {
		if ( ! str_ends_with( $file->getFilename(), '.php' ) ) {
			continue;
		}

		$class = 'ArtisanPackUI\\EcommerceAdminLivewire\\Livewire\\' . str_replace( [ '/', '.php' ], [ '\\', '' ], substr( $file->getPathname(), strlen( $root ) + 1 ) );

		if ( ! class_exists( $class ) ) {
			continue;
		}

		foreach ( [ ...LIVEWIRE_UPLOAD_MAGICS, ...LIVEWIRE_WIRE_ALIASES ] as $name ) {
			if ( ! method_exists( $class, $name ) ) {
				continue;
			}

			$method = new ReflectionMethod( $class, $name );

			// Livewire's own Component declares some of these (dispatch, js); only this package's methods count.
			if ( $method->isPublic() && str_starts_with( $method->getDeclaringClass()->getName(), 'ArtisanPackUI\\EcommerceAdminLivewire\\' ) ) {
				$colliding[] = $class . '::' . $name;
			}
		}
	}

	expect( $colliding )->toBe( [] );
} );

it( 'calls no Livewire upload function as an action from a view', function (): void {
	$root    = dirname( __DIR__, 3 ) . '/resources/views';
	$files   = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	$pattern = '/wire:(?:submit|click)[a-z.]*="(?:' . implode( '|', LIVEWIRE_UPLOAD_MAGICS ) . ')\b/';
	$found   = [];

	foreach ( $files as $file ) {
		if ( ! str_ends_with( $file->getFilename(), '.blade.php' ) ) {
			continue;
		}

		foreach ( file( $file->getPathname() ) as $number => $line ) {
			if ( 1 === preg_match( $pattern, $line ) ) {
				$found[] = str_replace( $root . '/', '', $file->getPathname() ) . ':' . ( $number + 1 );
			}
		}
	}

	expect( $found )->toBe( [] );
} );
