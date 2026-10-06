/**
 * Builds the asset bundle the browser tests load (tests/Browser).
 *
 * The package ships no CSS or JS build: a host compiles the admin's views
 * with its own Tailwind setup. The browser suite stands in for that host,
 * so it builds a small Tailwind + daisyUI bundle with the scripts the
 * component library expects on the page, into the Testbench public
 * directory the browser tests point at.
 *
 * It also copies the component library's published assets (TinyMCE and its
 * CSS) the way `php artisan vendor:publish --tag=artisanpack-assets` does.
 *
 * daisyUI is pinned to ~5.0 in package.json: later 5.x releases hide
 * `.tab-content`, which leaves the component library's tab panels empty
 * (ArtisanPack-UI/livewire-ui-components#119).
 */
const library = 'vendor/artisanpack-ui/livewire-ui-components/resources';
const publicDirectory = 'tests/Browser/workbench/public';

const publishLibraryAssets = () => ( {
    name: 'publish-artisanpack-assets',
    closeBundle() {
        cpSync( `${ library }/js`, `${ publicDirectory }/vendor/artisanpack-ui/js`, { recursive: true } );
        cpSync( `${ library }/css`, `${ publicDirectory }/vendor/artisanpack-ui/css`, { recursive: true } );
    },
} );
import { cpSync } from 'node:fs';

import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig( {
    plugins: [
        laravel( {
            input: [
                'tests/Browser/workbench/resources/css/app.css',
                'tests/Browser/workbench/resources/js/app.js',
            ],
            publicDirectory,
            buildDirectory: 'build',
            refresh: false,
        } ),
        tailwindcss(),
        publishLibraryAssets(),
    ],
} );
