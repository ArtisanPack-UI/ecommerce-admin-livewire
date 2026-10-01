@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders\Show;
@endphp
<div class="flex flex-col gap-4">
    <x-artisanpack-header
        :title="__( 'Order #:number', [ 'number' => $order->order_number ] )"
        :subtitle="null === $order->placed_at ? null : __( 'Placed :date', [ 'date' => LocalizedDate::format( $order->placed_at ) ] )"
        :level="1"
        separator
    />

    <div class="flex flex-wrap gap-2" aria-label="{{ __( 'Order status' ) }}" role="group">
        <x-artisanpack-ec-status-badge type="system" :value="$order->system_status" />
        @if ( null !== $order->substatus )
            <x-artisanpack-ec-status-badge :substatus="$order->substatus" />
        @endif
        <x-artisanpack-ec-status-badge type="payment" :value="$order->payment_status" />
        <x-artisanpack-ec-status-badge type="fulfillment" :value="$order->fulfillment_status" />
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="flex flex-col gap-4 lg:col-span-2">
            {{-- Items --}}
            <x-artisanpack-card :title="__( 'Items' )" shadow>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <caption class="sr-only">{{ __( 'Items in order #:number', [ 'number' => $order->order_number ] ) }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __( 'Item' ) }}</th>
                                <th scope="col">{{ __( 'SKU' ) }}</th>
                                <th scope="col" class="text-end">{{ __( 'Qty' ) }}</th>
                                <th scope="col" class="text-end">{{ __( 'Unit price' ) }}</th>
                                <th scope="col" class="text-end">{{ __( 'Discount' ) }}</th>
                                <th scope="col" class="text-end">{{ __( 'Tax' ) }}</th>
                                <th scope="col" class="text-end">{{ __( 'Total' ) }}</th>
                                <th scope="col">{{ __( 'Fulfillment' ) }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ( $items as $item )
                                <tr wire:key="order-item-{{ $item->id }}">
                                    <td>
                                        <span class="font-semibold">{{ Show::itemName( $item ) }}</span>
                                        @foreach ( Show::itemOptions( $item ) as $option )
                                            <span class="block text-xs opacity-75">{{ $option }}</span>
                                        @endforeach
                                        @if ( null === $item->product_id )
                                            <x-artisanpack-badge :value="__( 'No longer in the catalog' )" class="badge-ghost badge-sm mt-1" />
                                        @endif
                                    </td>
                                    <td>{{ data_get( $item->product_snapshot, 'sku' ) ?: '—' }}</td>
                                    <td class="text-end tabular-nums">{{ $item->quantity }}</td>
                                    <td class="text-end"><x-artisanpack-ec-money :amount="$item->unit_price_amount" :currency="$item->unit_price_currency" /></td>
                                    <td class="text-end"><x-artisanpack-ec-money :amount="$item->discount_amount" :currency="$item->discount_currency" /></td>
                                    <td class="text-end"><x-artisanpack-ec-money :amount="$item->tax_amount" :currency="$item->tax_currency" /></td>
                                    <td class="text-end"><x-artisanpack-ec-money :amount="$item->total_amount" :currency="$item->total_currency" /></td>
                                    <td><x-artisanpack-ec-status-badge type="fulfillment" :value="$item->fulfillment_status" /></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">{{ __( 'This order has no items.' ) }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-artisanpack-card>

            {{-- Totals --}}
            <x-artisanpack-card :title="__( 'Totals' )" shadow>
                <div class="overflow-x-auto">
                    <table class="table table-sm" data-order-totals>
                        <caption class="sr-only">{{ __( 'Totals for order #:number', [ 'number' => $order->order_number ] ) }}</caption>
                        <thead>
                            <tr>
                                <th scope="col"><span class="sr-only">{{ __( 'Line' ) }}</span></th>
                                <th scope="col" class="text-end">{{ $totals['currency'] }}</th>
                                @if ( null !== $totals['baseCurrency'] )
                                    <th scope="col" class="text-end">
                                        {{ __( ':currency (store currency)', [ 'currency' => $totals['baseCurrency'] ] ) }}
                                    </th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ( $totals['lines'] as $line )
                                <tr wire:key="order-total-{{ $line['key'] }}" @class( [ 'font-semibold' => $line['emphasis'] ] ) data-total-line="{{ $line['key'] }}">
                                    <th scope="row" class="font-normal">{{ $line['label'] }}</th>
                                    <td class="text-end"><x-artisanpack-ec-money :amount="$line['amount']" :currency="$totals['currency']" /></td>
                                    @if ( null !== $totals['baseCurrency'] )
                                        <td class="text-end"><x-artisanpack-ec-money :amount="$line['base']" :currency="$totals['baseCurrency']" /></td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ( null !== $totals['baseCurrency'] )
                    <p class="mt-2 text-sm opacity-75">{{ __( 'Store-currency amounts use the exchange rate recorded when the order was placed.' ) }}</p>
                @endif
            </x-artisanpack-card>

            {{-- Digital --}}
            @if ( $downloads->isNotEmpty() || $licenseKeys->isNotEmpty() )
                <x-artisanpack-card :title="__( 'Digital' )" shadow>
                    @if ( $downloads->isNotEmpty() )
                        <h3 class="font-semibold mb-2">{{ __( 'Downloads' ) }}</h3>
                        <ul class="flex flex-col gap-2 mb-4">
                            @foreach ( $downloads as $download )
                                <li wire:key="order-download-{{ $download->id }}" class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span class="font-semibold">{{ $download->file?->label ?? __( 'Deleted file' ) }}</span>
                                    <span class="text-sm opacity-75">
                                        {{ trans_choice( ':count download|:count downloads', (int) $download->download_count, [ 'count' => (int) $download->download_count ] ) }}
                                        &middot;
                                        @if ( null === $download->downloads_remaining )
                                            {{ __( 'No download limit' ) }}
                                        @else
                                            {{ trans_choice( ':count left|:count left', (int) $download->downloads_remaining, [ 'count' => (int) $download->downloads_remaining ] ) }}
                                        @endif
                                        &middot;
                                        {{ null === $download->expires_at ? __( 'Never expires' ) : __( 'Expires :date', [ 'date' => LocalizedDate::format( $download->expires_at ) ] ) }}
                                    </span>
                                    @if ( $download->isExpired() )
                                        <x-artisanpack-badge :value="__( 'Expired' )" class="badge-sm" color="neutral" />
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ( $licenseKeys->isNotEmpty() )
                        <h3 class="font-semibold mb-2">{{ __( 'License keys' ) }}</h3>
                        <ul class="flex flex-col gap-2">
                            @foreach ( $licenseKeys as $licenseKey )
                                <li wire:key="order-license-{{ $licenseKey->id }}" class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <code class="font-mono">{{ $canViewKeys ? $licenseKey->key : str_repeat( '•', 8 ) . mb_substr( (string) $licenseKey->key, -4 ) }}</code>
                                    <span class="text-sm opacity-75">
                                        {{ null === $licenseKey->activations_limit
                                            ? trans_choice( ':count activation|:count activations', (int) $licenseKey->activations_count, [ 'count' => (int) $licenseKey->activations_count ] )
                                            : __( ':used of :limit activations', [ 'used' => (int) $licenseKey->activations_count, 'limit' => (int) $licenseKey->activations_limit ] ) }}
                                    </span>
                                    @if ( $licenseKey->is_revoked )
                                        <x-artisanpack-badge :value="__( 'Revoked' )" class="badge-sm" color="error" />
                                    @elseif ( $licenseKey->isExpired() )
                                        <x-artisanpack-badge :value="__( 'Expired' )" class="badge-sm" color="neutral" />
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-artisanpack-card>
            @endif

            @foreach ( $mainPanels as $panel )
                <div data-order-panel="{{ $panel['key'] }}">
                    @livewire( $panel['component'], [ 'order' => $order ], key( 'order-panel-' . $panel['key'] ) )
                </div>
            @endforeach
        </div>

        <div class="flex flex-col gap-4">
            {{-- Customer --}}
            <x-artisanpack-card :title="__( 'Customer' )" shadow>
                <p class="font-semibold">
                    @if ( null !== $customerUrl )
                        <a href="{{ $customerUrl }}" class="link link-hover">{{ $customerName ?? $order->email }}</a>
                    @else
                        {{ $customerName ?? __( 'Guest' ) }}
                    @endif
                </p>
                <p><a href="mailto:{{ $order->email }}" class="link link-hover">{{ $order->email }}</a></p>
                @if ( null !== $order->phone && '' !== $order->phone )
                    <p><span class="sr-only">{{ __( 'Phone:' ) }}</span> {{ $order->phone }}</p>
                @endif
                <div class="mt-2 flex flex-wrap gap-2">
                    <x-artisanpack-badge :value="$isGuest ? __( 'Guest checkout' ) : __( 'Has an account' )" class="badge-sm" color="neutral" />
                    <x-artisanpack-badge :value="$order->is_claimed ? __( 'Claimed' ) : __( 'Not claimed' )" class="badge-sm" :color="$order->is_claimed ? 'success' : 'warning'" />
                </div>
            </x-artisanpack-card>

            {{-- Addresses --}}
            <x-artisanpack-card :title="__( 'Addresses' )" shadow>
                <h3 class="font-semibold">{{ __( 'Shipping' ) }}</h3>
                <x-artisanpack-ec-address :address="$order->shipping_address" class="mb-3" />
                <h3 class="font-semibold">{{ __( 'Billing' ) }}</h3>
                <x-artisanpack-ec-address :address="$order->billing_address" />
            </x-artisanpack-card>

            {{-- Payment --}}
            <x-artisanpack-card :title="__( 'Payment' )" shadow>
                <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
                    <dt class="opacity-75">{{ __( 'Gateway' ) }}</dt>
                    <dd>{{ $paymentGateway ?? __( 'None' ) }}</dd>
                    <dt class="opacity-75">{{ __( 'Reference' ) }}</dt>
                    <dd class="break-all font-mono text-sm">{{ $order->payment_reference ?: __( 'None' ) }}</dd>
                    <dt class="opacity-75">{{ __( 'Status' ) }}</dt>
                    <dd><x-artisanpack-ec-status-badge type="payment" :value="$order->payment_status" /></dd>
                </dl>
            </x-artisanpack-card>

            {{-- Boards --}}
            <x-artisanpack-card :title="__( 'Boards' )" shadow>
                @if ( $assignments->isEmpty() )
                    <p class="opacity-75">{{ __( 'This order is not on any board.' ) }}</p>
                @else
                    <ul class="flex flex-col gap-2">
                        @foreach ( $assignments as $assignment )
                            <li wire:key="order-board-{{ $assignment->board_id }}" class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold">{{ $assignment->board?->name ?? __( 'Deleted board' ) }}</span>
                                @if ( null !== $assignment->substatus )
                                    <x-artisanpack-ec-status-badge :substatus="$assignment->substatus" />
                                @endif
                                @if ( $canMoveBoards )
                                    <x-artisanpack-button
                                        variant="ghost"
                                        size="xs"
                                        icon="o-x-mark"
                                        class="ms-auto"
                                        wire:click="removeFromBoard( {{ (int) $assignment->board_id }} )"
                                        wire:loading.attr="disabled"
                                        :label="__( 'Remove' )"
                                        :aria-label="__( 'Remove from :board', [ 'board' => $assignment->board?->name ?? '' ] )"
                                    />
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ( $canMoveBoards && [] !== $boardOptions )
                    <form wire:submit="addToBoard" class="mt-3 flex flex-wrap items-end gap-2">
                        <x-artisanpack-select
                            id="order-add-board"
                            :label="__( 'Add to board' )"
                            :options="$boardOptions"
                            :placeholder="__( 'Choose a board' )"
                            placeholder-value=""
                            wire:model="boardToAdd"
                        />
                        <x-artisanpack-button type="submit" size="sm" wire:loading.attr="disabled" :label="__( 'Add' )" />
                    </form>
                @endif
            </x-artisanpack-card>

            @foreach ( $sidePanels as $panel )
                <div data-order-panel="{{ $panel['key'] }}">
                    @livewire( $panel['component'], [ 'order' => $order ], key( 'order-panel-' . $panel['key'] ) )
                </div>
            @endforeach
        </div>
    </div>
</div>
