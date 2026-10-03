{{--
    A promotion's coupon codes. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\CouponsPanel.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\CouponsPanel;
@endphp
<div class="flex flex-col gap-6" data-promotion-coupons>
    @unless ( $isCoupon )
        <x-artisanpack-alert color="warning" icon="o-exclamation-triangle" role="status" :title="__( 'Codes only work on coupon promotions.' )" :description="__( 'Change the source to Coupon code and save to use these codes.' )" />
    @endunless

    @if ( $canCreate )
        <div class="grid gap-4 lg:grid-cols-2">
            <form wire:submit="addCode" class="flex flex-wrap items-end gap-2 rounded-box border border-base-content/10 p-4" data-add-code>
                <x-artisanpack-input
                    id="coupon-new-code"
                    class="min-w-48"
                    :label="__( 'New code' )"
                    :hint="__( 'Letters are stored in capitals: welcome10 becomes WELCOME10.' )"
                    autocomplete="off"
                    wire:model="newCode"
                />
                <x-artisanpack-button type="submit" icon="o-plus" wire:loading.attr="disabled" :label="__( 'Add code' )" />
            </form>

            <form wire:submit="generateCodes" class="flex flex-col gap-3 rounded-box border border-base-content/10 p-4" data-generate-codes>
                <h3 class="font-semibold">{{ __( 'Generate codes' ) }}</h3>
                <div class="grid gap-3 sm:grid-cols-3">
                    <x-artisanpack-input id="coupon-generate-count" type="number" min="1" max="{{ CouponsPanel::MAX_GENERATE }}" step="1" :label="__( 'How many' )" wire:model="generateCount" />
                    <x-artisanpack-input id="coupon-generate-prefix" :label="__( 'Prefix' )" :hint="__( 'Optional, like FALL-.' )" autocomplete="off" wire:model="generatePrefix" />
                    <x-artisanpack-input id="coupon-generate-length" type="number" min="4" max="32" step="1" :label="__( 'Random characters' )" wire:model="generateLength" />
                </div>
                <p class="text-sm opacity-75">{{ __( 'Generated codes leave out characters that are easy to confuse, like 0 and O or 1 and I.' ) }}</p>
                <div>
                    <x-artisanpack-button type="submit" icon="o-sparkles" wire:loading.attr="disabled" spinner="generateCodes" :label="__( 'Generate' )" />
                </div>
            </form>
        </div>
    @endif

    <div class="flex flex-wrap items-end gap-3">
        <x-artisanpack-input id="coupon-search" class="min-w-64" type="search" icon="o-magnifying-glass" :label="__( 'Find a code' )" wire:model.live.debounce.300ms="search" />
        <p class="text-sm opacity-75">{{ trans_choice( ':count code in all|:count codes in all', $total, [ 'count' => $total ] ) }}</p>
        @if ( $total > 0 )
            <x-artisanpack-button class="ms-auto" variant="outline" size="sm" icon="o-arrow-down-tray" wire:click="exportCodes" wire:loading.attr="disabled" :label="__( 'Export codes (CSV)' )" />
        @endif
    </div>

    @if ( [] === $usage )
        <p class="text-sm opacity-75" data-coupon-usage-note>
            {{ __( 'Uses are counted for the whole promotion (:uses so far), not per code. See the Usage tab for each order.', [ 'uses' => (int) $promotion->times_used ] ) }}
        </p>
    @endif

    @if ( 0 === $codes->total() )
        <x-artisanpack-ec-empty-state
            icon="o-ticket"
            :title="'' === trim( $search ) ? __( 'No codes yet' ) : __( 'No matching codes' )"
            :description="'' === trim( $search ) ? __( 'Add a code, or generate a batch to send out.' ) : __( 'Try another search.' )"
        />
    @else
        <div class="overflow-x-auto">
            <table class="table table-sm" data-coupon-codes>
                <caption class="sr-only">{{ __( 'Coupon codes' ) }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __( 'Code' ) }}</th>
                        @if ( [] !== $usage )
                            <th scope="col" class="text-end">{{ __( 'Uses' ) }}</th>
                        @endif
                        <th scope="col">{{ __( 'Added' ) }}</th>
                        <th scope="col" class="text-end">{{ __( 'Actions' ) }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ( $codes as $coupon )
                        <tr wire:key="coupon-{{ $coupon->id }}">
                            <td>
                                @if ( $editingId === (int) $coupon->id )
                                    <form wire:submit="saveRename" class="flex flex-wrap items-end gap-2" data-rename-code="{{ $coupon->id }}">
                                        <x-artisanpack-input id="coupon-{{ $coupon->id }}-code" class="input-sm" :label="__( 'Code' )" autocomplete="off" wire:model="editingCode" />
                                        <x-artisanpack-button type="submit" size="sm" color="primary" :label="__( 'Save' )" />
                                        <x-artisanpack-button size="sm" variant="ghost" wire:click="cancelRename" :label="__( 'Cancel' )" />
                                    </form>
                                @else
                                    <code class="font-mono font-semibold">{{ $coupon->code }}</code>
                                @endif
                            </td>
                            @if ( [] !== $usage )
                                <td class="text-end tabular-nums">{{ (int) ( $usage[ $coupon->code ] ?? 0 ) }}</td>
                            @endif
                            <td>{{ null === $coupon->created_at ? '—' : LocalizedDate::format( $coupon->created_at ) }}</td>
                            <td class="text-end">
                                <div class="flex justify-end gap-1">
                                    @if ( $canUpdate && $editingId !== (int) $coupon->id )
                                        <x-artisanpack-button variant="ghost" size="sm" icon="o-pencil" wire:click="startRename( {{ $coupon->id }} )" :aria-label="__( 'Rename :code', [ 'code' => $coupon->code ] )" />
                                    @endif
                                    @if ( $canDelete )
                                        <x-artisanpack-button variant="ghost" size="sm" icon="o-trash" wire:click="confirmDelete( {{ $coupon->id }} )" data-focus-key="coupon-delete-{{ $coupon->id }}" :aria-label="__( 'Delete :code', [ 'code' => $coupon->code ] )" />
                                    @endif
                                </div>
                                @if ( $deletingId === (int) $coupon->id )
                                    <div role="alertdialog" aria-modal="false" aria-labelledby="coupon-delete-{{ $coupon->id }}" class="mt-2 rounded-box border border-warning p-3 text-start" x-data x-init="$nextTick( () => $el.querySelector( '[data-confirm]' )?.focus() )">
                                        <p id="coupon-delete-{{ $coupon->id }}" class="mb-2">{{ __( 'Delete :code? It stops working at checkout right away.', [ 'code' => $coupon->code ] ) }}</p>
                                        <div class="flex gap-2">
                                            <x-artisanpack-button color="error" size="sm" data-confirm wire:click="deleteCode( {{ \Illuminate\Support\Js::from( $deleteToken ) }} )" wire:loading.attr="disabled" :label="__( 'Delete code' )" />
                                            <x-artisanpack-button variant="ghost" size="sm" wire:click="cancelDelete" data-focus-return="coupon-delete-{{ $coupon->id }}" :label="__( 'Cancel' )" />
                                        </div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-artisanpack-pagination :rows="$codes" hide-per-page :page-info-template="__( 'Showing {from} to {to} of {total} results' )" />
    @endif
</div>
