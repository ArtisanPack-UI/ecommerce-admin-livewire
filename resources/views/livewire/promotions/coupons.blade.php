{{--
    Coupons panel (#111). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\CouponsPanel.

    The codes of a coupon promotion. A warning leads when the promotion isn't
    set to coupons, because its codes won't work until it is. A sunken card
    holds "New code" (one code) and "Generate codes" (a batch with a prefix);
    below it a search, the code count and the CSV export sit above the
    codes table. Codes are mono and shown in capitals, and the hint says
    lowercase input becomes uppercase. Rename happens in the row; Delete
    confirms in place of the row and warns that the code stops working
    right away. The engine counts uses per promotion, so unless a satellite
    supplies per-code counts (and the Uses column), a note points to the
    Usage tab.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Promotions\CouponsPanel;
    use Illuminate\Support\Js;

    $isSearching = '' !== trim( $search );
    $columnCount = [] === $usage ? 3 : 4;
@endphp
<div class="ec-stack" data-promotion-coupons>
    @unless ( $isCoupon )
        <x-artisanpack-alert color="warning" icon="triangle-alert" role="status" :title="__( 'Codes only work on coupon promotions.' )" :description="__( 'Change the source to Coupon code and save to use these codes.' )" data-wrong-source />
    @endunless

    @if ( $canCreate )
        <x-artisanpack-card surface="sunken" :classes="[ 'body' => 'ec-coupon-forms' ]" data-coupon-tools>
            <form wire:submit="addCode" class="ec-stack-sm ec-align-start" aria-labelledby="coupon-new-code-heading" data-add-code>
                <x-artisanpack-heading :level="3" :size="6" id="coupon-new-code-heading">{{ __( 'New code' ) }}</x-artisanpack-heading>
                <x-artisanpack-input
                    id="coupon-new-code"
                    class="ec-mono-input"
                    dir="ltr"
                    aria-labelledby="coupon-new-code-heading"
                    :hint="__( 'Letters are stored in capitals: welcome10 becomes WELCOME10.' )"
                    autocomplete="off"
                    spellcheck="false"
                    wire:model="newCode"
                />
                <div>
                    <x-artisanpack-button type="submit" variant="outline" color="neutral" size="sm" leading-icon="plus" wire:loading.attr="disabled" wire:target="addCode">{{ __( 'Add code' ) }}</x-artisanpack-button>
                </div>
            </form>

            <form wire:submit="generateCodes" class="ec-stack-sm ec-align-start" aria-labelledby="coupon-generate-heading" data-generate-codes>
                <x-artisanpack-heading :level="3" :size="6" id="coupon-generate-heading">{{ __( 'Generate codes' ) }}</x-artisanpack-heading>
                <div class="ec-generate-fields">
                    <x-artisanpack-input id="coupon-generate-count" type="number" min="1" max="{{ CouponsPanel::MAX_GENERATE }}" step="1" dir="ltr" :label="__( 'How many' )" wire:model="generateCount" />
                    <x-artisanpack-input id="coupon-generate-prefix" class="ec-mono-input" dir="ltr" :label="__( 'Prefix' )" :hint="__( 'Optional, like FALL-.' )" autocomplete="off" spellcheck="false" wire:model="generatePrefix" />
                    <x-artisanpack-input id="coupon-generate-length" type="number" min="4" max="32" step="1" dir="ltr" :label="__( 'Random characters' )" wire:model="generateLength" />
                </div>
                <p class="ec-muted">{{ __( 'Generated codes leave out characters that are easy to confuse, like 0 and O or 1 and I.' ) }}</p>
                <div>
                    <x-artisanpack-button type="submit" variant="outline" color="neutral" size="sm" leading-icon="zap" wire:loading.attr="disabled" wire:target="generateCodes">{{ __( 'Generate' ) }}</x-artisanpack-button>
                </div>
            </form>
        </x-artisanpack-card>
    @endif

    <div class="ec-table-toolbar" data-coupon-toolbar>
        <div class="ec-table-search">
            <x-artisanpack-input
                id="coupon-search"
                size="sm"
                type="search"
                leading-icon="search"
                :aria-label="__( 'Find a code' )"
                :placeholder="__( 'Find a code' )"
                autocomplete="off"
                spellcheck="false"
                wire:model.live.debounce.300ms="search"
            />
        </div>
        <p class="ec-muted" data-coupon-total>{{ trans_choice( ':count code in all|:count codes in all', $total, [ 'count' => $total ] ) }}</p>
        @if ( $canExport )
            @if ( $total > 0 )
                <x-artisanpack-button class="ec-push-end" variant="outline" color="neutral" size="sm" leading-icon="download" wire:click="exportCodes" wire:loading.attr="disabled" wire:target="exportCodes" data-export-codes>{{ __( 'Export codes (CSV)' ) }}</x-artisanpack-button>
            @else
                <x-artisanpack-button class="ec-push-end" variant="outline" color="neutral" size="sm" leading-icon="download" aria-disabled="true" data-export-codes>{{ __( 'Export codes (CSV)' ) }}</x-artisanpack-button>
            @endif
        @endif
    </div>

    @if ( [] === $usage && $codes->total() > 0 )
        <p class="ec-muted ec-icon-line" data-coupon-usage-note>
            <x-artisanpack-icon name="info" aria-hidden="true" />
            {{ __( 'Uses are counted for the whole promotion (:uses so far), not per code. See the Usage tab for each order.', [ 'uses' => (int) $promotion->times_used ] ) }}
        </p>
    @endif

    @if ( 0 === $codes->total() )
        <x-artisanpack-ec-admin-empty-state
            size="sm"
            class="ec-empty-sunken"
            :heading-level="3"
            :icon="$isSearching ? 'search' : 'tag'"
            :title="$isSearching ? __( 'No matching codes' ) : __( 'No codes yet' )"
            :description="$isSearching ? __( 'Try another search.' ) : __( 'Add a code, or generate a batch to send out.' )"
        />
    @else
        <div class="apui-table ec-table" data-surface="default">
            <div data-scope="table" data-part="scroll">
                <table data-scope="table" data-part="table" data-coupon-codes>
                    <caption class="apui-sr-only">{{ __( 'Coupon codes' ) }}</caption>
                    <thead>
                        <tr>
                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Code' ) }}</th>
                            @if ( [] !== $usage )
                                <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( 'Uses' ) }}</th>
                            @endif
                            <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Added' ) }}</th>
                            <th data-scope="table" data-part="head-cell" scope="col" data-numeric><span class="apui-sr-only">{{ __( 'Actions' ) }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ( $codes as $coupon )
                            @php( $couponId = (int) $coupon->id )
                            @if ( $deletingId === $couponId )
                                <tr data-scope="table" data-part="row" wire:key="coupon-{{ $couponId }}-delete">
                                    <td data-scope="table" data-part="cell" colspan="{{ $columnCount }}" class="ec-cell-flush">
                                        <div
                                            class="ec-confirm"
                                            role="alertdialog"
                                            aria-modal="false"
                                            aria-labelledby="coupon-delete-{{ $couponId }}"
                                            x-data
                                            x-init="$nextTick( () => $el.querySelector( '[data-confirm]' )?.focus() )"
                                            data-coupon-delete-confirm
                                        >
                                            <x-artisanpack-icon name="triangle-alert" aria-hidden="true" />
                                            <p id="coupon-delete-{{ $couponId }}">{{ __( 'Delete :code? It stops working at checkout right away.', [ 'code' => $coupon->code ] ) }}</p>
                                            <div class="ec-confirm-actions">
                                                <x-artisanpack-button color="error" size="sm" data-confirm wire:click="deleteCode( {{ Js::from( $deleteToken ) }} )" wire:loading.attr="disabled" wire:target="deleteCode">{{ __( 'Delete code' ) }}</x-artisanpack-button>
                                                <x-artisanpack-button variant="ghost" color="neutral" size="sm" wire:click="cancelDelete" data-focus-return="coupon-delete-{{ $couponId }}">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                @continue
                            @endif
                            <tr data-scope="table" data-part="row" wire:key="coupon-{{ $couponId }}">
                                <td data-scope="table" data-part="cell">
                                    @if ( $editingId === $couponId )
                                        <form
                                            wire:submit="saveRename"
                                            class="ec-rename-row"
                                            x-on:keydown.escape.prevent="$wire.cancelRename()"
                                            data-rename-code="{{ $couponId }}"
                                        >
                                            <x-artisanpack-input
                                                id="coupon-{{ $couponId }}-code"
                                                size="sm"
                                                class="ec-mono-input"
                                                dir="ltr"
                                                :aria-label="__( 'Code' )"
                                                autocomplete="off"
                                                spellcheck="false"
                                                x-init="$nextTick( () => $el.focus() )"
                                                wire:model="editingCode"
                                            />
                                            <x-artisanpack-button type="submit" size="xs" data-focus-return="coupon-rename-{{ $couponId }}" wire:loading.attr="disabled" wire:target="saveRename">{{ __( 'Save' ) }}</x-artisanpack-button>
                                            <x-artisanpack-button variant="ghost" color="neutral" size="xs" wire:click="cancelRename" data-focus-return="coupon-rename-{{ $couponId }}">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                                        </form>
                                    @else
                                        <bdi dir="ltr" class="ec-mono ec-strong">{{ $coupon->code }}</bdi>
                                    @endif
                                </td>
                                @if ( [] !== $usage )
                                    <td data-scope="table" data-part="cell" data-numeric><bdi dir="ltr">{{ (int) ( $usage[ $coupon->code ] ?? 0 ) }}</bdi></td>
                                @endif
                                <td data-scope="table" data-part="cell" class="ec-cell-muted ec-nowrap">
                                    @if ( null !== $coupon->created_at )
                                        <time datetime="{{ $coupon->created_at->toIso8601String() }}">{{ LocalizedDate::format( $coupon->created_at ) }}</time>
                                    @else
                                        <span aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'None' ) }}</span>
                                    @endif
                                </td>
                                <td data-scope="table" data-part="cell" data-numeric>
                                    @if ( $editingId !== $couponId )
                                        <div class="ec-row-actions ec-row-actions-end">
                                            @if ( $canUpdate )
                                                <x-artisanpack-button
                                                    variant="ghost"
                                                    color="neutral"
                                                    size="xs"
                                                    leading-icon="pencil"
                                                    wire:click="startRename( {{ $couponId }} )"
                                                    wire:loading.attr="disabled"
                                                    wire:target="startRename"
                                                    :aria-label="__( 'Rename :code', [ 'code' => $coupon->code ] )"
                                                    data-focus-key="coupon-rename-{{ $couponId }}"
                                                />
                                            @endif
                                            @if ( $canDelete )
                                                <x-artisanpack-button
                                                    variant="ghost"
                                                    color="neutral"
                                                    size="xs"
                                                    leading-icon="trash"
                                                    wire:click="confirmDelete( {{ $couponId }} )"
                                                    wire:loading.attr="disabled"
                                                    wire:target="confirmDelete"
                                                    :aria-label="__( 'Delete :code', [ 'code' => $coupon->code ] )"
                                                    data-focus-key="coupon-delete-{{ $couponId }}"
                                                />
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div data-scope="table" data-part="footer">
                <x-artisanpack-pagination :rows="$codes" summary :summary-label="__( 'Showing {from} to {to} of {total} results' )" />
            </div>
        </div>
    @endif
</div>
