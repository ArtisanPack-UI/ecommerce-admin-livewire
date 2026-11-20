/*
 * The scripts the admin expects a host to load: the design system's
 * behavior engine and drag-and-drop. Alpine comes with Livewire, so the
 * engine registers as Alpine starts.
 */
import apuiBehavior from '@artisanpack-ui/behavior/alpine';
import '@artisanpack-ui/livewire-drag-and-drop';

document.addEventListener( 'alpine:init', () => window.Alpine.plugin( apuiBehavior ) );

// TinyMCE comes from the library's published assets; the rich-text editor
// uses it once it has loaded.
const tinymce = document.createElement( 'script' );
tinymce.src = '/vendor/artisanpack-ui/js/tinymce/tinymce.min.js';
tinymce.referrerPolicy = 'origin';
document.head.append( tinymce );
