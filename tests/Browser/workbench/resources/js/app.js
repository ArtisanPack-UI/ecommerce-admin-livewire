/*
 * The scripts livewire-ui-components expects a host to load. Alpine comes
 * with Livewire, so nothing here imports it.
 */
import ApexCharts from 'apexcharts';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import '@artisanpack-ui/livewire-drag-and-drop';
import '../../../../../vendor/artisanpack-ui/livewire-ui-components/resources/js/sparkline.js';
import '../../../../../vendor/artisanpack-ui/livewire-ui-components/resources/js/tinymce-editor.js';

window.ApexCharts = ApexCharts;
window.flatpickr = flatpickr;

// TinyMCE itself comes from the library's published assets; the Alpine
// integration above waits for it to load.
const tinymce = document.createElement( 'script' );
tinymce.src = '/vendor/artisanpack-ui/js/tinymce/tinymce.min.js';
tinymce.referrerPolicy = 'origin';
document.head.append( tinymce );
