{{--
    Bulk-action bar (#79). See BulkActionBar.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div {{ $attributes->class( [ 'ec-bulkbar' ] ) }} role="region" aria-label="{{ __( 'Bulk actions' ) }}" data-bulk-action-bar>
    <p>
        @if ( $allMatching )
            {{ trans_choice( 'All :count matching row selected.|All :count matching rows selected.', $count, [ 'count' => $count ] ) }}
        @else
            {{ trans_choice( ':count row selected.|:count rows selected.', $count, [ 'count' => $count ] ) }}
        @endif
    </p>

    @if ( $offersSelectAll() )
        <x-artisanpack-button variant="link" size="sm" wire:click="selectAllMatchingRows">
            {{ trans_choice( 'Select the :count matching row|Select all :count matching rows', $total, [ 'count' => $total ] ) }}
        </x-artisanpack-button>
    @endif

    <span class="ec-spacer"></span>

    {{ $slot }}

    @foreach ( $actions as $action )
        <x-artisanpack-button
            variant="outline"
            :color="null !== ( $action['confirm'] ?? null ) ? 'error' : 'neutral'"
            size="sm"
            :leading-icon="$action['icon']"
            wire:click="runBulkAction( {{ \Illuminate\Support\Js::from( $action['key'] ) }} )"
            data-focus-key="bulk-{{ $action['key'] }}"
            wire:loading.attr="disabled"
            wire:key="bulk-action-{{ $action['key'] }}"
        >{{ $action['label'] }}</x-artisanpack-button>
    @endforeach

    <x-artisanpack-button variant="ghost" color="neutral" size="sm" wire:click="clearSelection">{{ __( 'Clear selection' ) }}</x-artisanpack-button>
</div>
