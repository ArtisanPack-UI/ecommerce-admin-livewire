{{--
    Bulk-action bar. See BulkActionBar.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div {{ $attributes->class( [ 'flex flex-wrap items-center gap-2 rounded-box bg-base-200 p-3' ] ) }} role="region" aria-label="{{ __( 'Bulk actions' ) }}" data-bulk-action-bar>
    <p class="font-semibold">
        @if ( $allMatching )
            {{ trans_choice( 'All :count matching row selected.|All :count matching rows selected.', $count, [ 'count' => $count ] ) }}
        @else
            {{ trans_choice( ':count row selected.|:count rows selected.', $count, [ 'count' => $count ] ) }}
        @endif
    </p>

    @if ( $offersSelectAll() )
        <x-artisanpack-button
            variant="ghost"
            size="sm"
            wire:click="selectAllMatchingRows"
            :label="trans_choice( 'Select the :count matching row|Select all :count matching rows', $total, [ 'count' => $total ] )"
        />
    @endif

    {{ $slot }}

    @foreach ( $actions as $action )
        <x-artisanpack-button
            variant="outline"
            size="sm"
            :icon="$action['icon']"
            :label="$action['label']"
            wire:click="runBulkAction( {{ \Illuminate\Support\Js::from( $action['key'] ) }} )"
            data-focus-key="bulk-{{ $action['key'] }}"
            wire:loading.attr="disabled"
            wire:key="bulk-action-{{ $action['key'] }}"
        />
    @endforeach

    <x-artisanpack-button variant="ghost" size="sm" wire:click="clearSelection" :label="__( 'Clear selection' )" />
</div>
