{{--
    Order detail (#118). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders\Show.

    One order, start to finish. The page header carries the number and the
    placed date; the four status badges follow the h1 (the header's meta
    slot would put them before it). Below them, a "changed elsewhere"
    warning banner offers Reload order (it never reloads on its own). The
    main column holds what was bought and what happened to it: items,
    totals, digital deliveries, the main-column panels (fulfillment, refunds,
    edits), notes and the timeline. The side column holds who and where:
    status, customer, addresses, payment, boards and the side-column panels.
    Columns are 2fr / 1fr (side at least 300px); below 1024px they stack
    with the side column first, so status sits under the header. The side
    column is first in the DOM too, so the tab order matches what's seen.
    Each board the order is on shows its column (label and color), how
    long the card has been there, and, with the kanban satellite and
    kanbanBoard.view, an Open board link that focuses the order's card.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPack\LivewireUiComponents\View\Components\Avatar;
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
    use ArtisanPackUI\Ecommerce\Support\TaxLabel;
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders\Show;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
    use Carbon\CarbonInterface;

    $customerLabel = $customerName ?? ( null !== $order->customer_id ? $order->email : __( 'Guest' ) );
@endphp
<div class="ec-stack" data-order-detail>
    <x-artisanpack-page-header
        :title="__( 'Order #:number', [ 'number' => $order->order_number ] )"
        :description="null === $order->placed_at ? null : __( 'Placed :date', [ 'date' => LocalizedDate::format( $order->placed_at ) ] )"
        :level="1"
    />

    <div class="ec-badge-row ec-order-badges" role="group" aria-label="{{ __( 'Order status' ) }}" data-order-badges>
        <x-artisanpack-ec-status-badge type="system" :value="$order->system_status" size="lg" />
        @if ( null !== $order->substatus )
            <x-artisanpack-ec-status-badge :substatus="$order->substatus" size="lg" />
        @endif
        <x-artisanpack-ec-status-badge type="payment" :value="$order->payment_status" size="lg" />
        <x-artisanpack-ec-status-badge type="fulfillment" :value="$order->fulfillment_status" size="lg" />
    </div>

    @include( 'ecommerce-admin::partials.live-region', [ 'message' => $liveAnnouncement ] )

    @if ( $changedElsewhere )
        <div class="ec-banner apui-alert" data-color="warning" data-changed-elsewhere>
            <x-artisanpack-icon name="refresh-cw" aria-hidden="true" />
            <p class="ec-banner__text">{{ __( 'This order was changed elsewhere. Reload it to see the latest details.' ) }}</p>
            <x-artisanpack-button color="warning" size="sm" leading-icon="refresh-cw" wire:click="reloadOrder" wire:loading.attr="disabled" wire:target="reloadOrder">{{ __( 'Reload order' ) }}</x-artisanpack-button>
        </div>
    @endif

    {{-- The cards' titles are h3s (the Card component's level); this keeps the outline from jumping from the page's h1. --}}
    <h2 class="apui-sr-only">{{ __( 'Order details' ) }}</h2>

    <div class="ec-order-detail">
        <div class="ec-order-col ec-order-col-side" data-order-column="side">
            {{-- Status --}}
            @livewire( 'artisanpack-ecommerce-admin-order-status', [ 'order' => $order ], key( 'order-status' ) )

            {{-- Customer --}}
            <x-artisanpack-card :title="__( 'Customer' )" :classes="[ 'body' => 'ec-stack-sm' ]" data-order-customer>
                <div class="ec-order-customer">
                    <x-artisanpack-avatar :initials="Avatar::initialsFor( $customerLabel )" :color="Avatar::colorFor( $customerLabel )" aria-hidden="true" />
                    <div class="ec-order-customer__text">
                        @if ( null !== $customerUrl )
                            <x-artisanpack-link :href="$customerUrl" :navigate="false" standalone class="ec-strong">{{ $customerLabel }}</x-artisanpack-link>
                        @else
                            <span class="ec-strong">{{ $customerLabel }}</span>
                        @endif
                        <x-artisanpack-link :href="'mailto:' . $order->email" :navigate="false" tone="muted" class="ec-order-email">{{ $order->email }}</x-artisanpack-link>
                    </div>
                </div>
                @if ( null !== $order->phone && '' !== $order->phone )
                    <p class="ec-muted"><span class="apui-sr-only">{{ __( 'Phone:' ) }}</span> <bdi dir="ltr">{{ $order->phone }}</bdi></p>
                @endif
                <div class="ec-badge-row">
                    <x-artisanpack-badge size="sm" color="neutral">{{ $isGuest ? __( 'Guest checkout' ) : __( 'Has an account' ) }}</x-artisanpack-badge>
                    <x-artisanpack-badge size="sm" :color="$order->is_claimed ? 'success' : 'warning'">{{ $order->is_claimed ? __( 'Claimed' ) : __( 'Not claimed' ) }}</x-artisanpack-badge>
                </div>
            </x-artisanpack-card>

            {{-- Addresses --}}
            <x-artisanpack-card :title="__( 'Addresses' )" data-order-addresses>
                <section class="ec-order-sub" aria-labelledby="order-shipping-heading">
                    <h4 id="order-shipping-heading" class="ec-subheading">{{ __( 'Shipping' ) }}</h4>
                    <x-artisanpack-ec-admin-address :address="$order->shipping_address" />
                </section>
                <section class="ec-order-sub" aria-labelledby="order-billing-heading">
                    <h4 id="order-billing-heading" class="ec-subheading">{{ __( 'Billing' ) }}</h4>
                    @if ( $billingSameAsShipping )
                        <p class="ec-muted" data-billing-same>{{ __( 'Same as shipping' ) }}</p>
                    @else
                        <x-artisanpack-ec-admin-address :address="$order->billing_address" />
                    @endif
                </section>
            </x-artisanpack-card>

            {{-- Payment --}}
            <x-artisanpack-card :title="__( 'Payment' )" data-order-payment>
                <dl class="ec-dl">
                    <dt>{{ __( 'Gateway' ) }}</dt>
                    <dd>{{ $paymentGateway ?? __( 'None' ) }}</dd>
                    <dt>{{ __( 'Reference' ) }}</dt>
                    <dd class="ec-break">
                        @if ( null !== $order->payment_reference && '' !== $order->payment_reference )
                            <bdi dir="ltr" class="ec-mono">{{ $order->payment_reference }}</bdi>
                        @else
                            {{ __( 'None' ) }}
                        @endif
                    </dd>
                    <dt>{{ __( 'Status' ) }}</dt>
                    <dd><x-artisanpack-ec-status-badge type="payment" :value="$order->payment_status" /></dd>
                </dl>
            </x-artisanpack-card>

            {{-- Boards --}}
            <x-artisanpack-card :title="__( 'Boards' )" :classes="[ 'body' => 'ec-stack-sm' ]" data-order-boards>
                @if ( [] === $assignments )
                    <p class="ec-muted">{{ __( 'This order is not on any board.' ) }}</p>
                @else
                    <ul class="ec-list">
                        @foreach ( $assignments as $assignment )
                            <li wire:key="order-board-{{ $assignment['boardId'] }}" class="ec-order-board" data-order-board="{{ $assignment['boardId'] }}">
                                <div class="ec-item-head">
                                    <span class="ec-strong">{{ $assignment['boardName'] }}</span>
                                    @if ( null !== $assignment['columnLabel'] )
                                        <x-artisanpack-badge variant="soft" color="neutral" size="sm" data-order-board-column>
                                            @if ( null !== $assignment['columnColor'] )
                                                <x-slot:icon><svg class="ec-hex-dot" width="8" height="8" viewBox="0 0 8 8" aria-hidden="true" focusable="false"><circle cx="4" cy="4" r="3.5" fill="{{ $assignment['columnColor'] }}" /></svg></x-slot:icon>
                                            @endif
                                            <span class="apui-sr-only">{{ __( 'Column:' ) }} </span>{{ $assignment['columnLabel'] }}
                                        </x-artisanpack-badge>
                                    @endif
                                    @if ( $canMoveBoards )
                                        <x-artisanpack-button
                                            variant="ghost"
                                            color="neutral"
                                            size="xs"
                                            icon-only
                                            leading-icon="x"
                                            class="ec-push-end"
                                            wire:click="removeFromBoard( {{ $assignment['boardId'] }} )" wire:loading.attr="disabled"
                                            wire:target="removeFromBoard"
                                            :aria-label="__( 'Remove from :board', [ 'board' => $assignment['boardName'] ] )"
                                            data-remove-board
                                        />
                                    @endif
                                </div>
                                @if ( null !== $assignment['since'] || null !== $assignment['url'] )
                                    <div class="ec-item-head">
                                        @if ( null !== $assignment['since'] )
                                            <time class="ec-muted" datetime="{{ $assignment['since']->toIso8601String() }}" data-order-board-since>{{ __( 'In this column for :duration', [ 'duration' => $assignment['since']->diffForHumans( [ 'syntax' => CarbonInterface::DIFF_ABSOLUTE ] ) ] ) }}<span class="apui-sr-only"> ({{ LocalizedDate::format( $assignment['since'] ) }})</span></time>
                                        @endif
                                        @if ( null !== $assignment['url'] )
                                            <x-artisanpack-button
                                                variant="outline"
                                                color="neutral"
                                                size="xs"
                                                leading-icon="columns-3"
                                                class="ec-push-end"
                                                :href="$assignment['url']"
                                                :aria-label="__( 'Open board: :name', [ 'name' => $assignment['boardName'] ] )"
                                                data-open-board
                                            >{{ __( 'Open board' ) }}</x-artisanpack-button>
                                        @endif
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ( $canMoveBoards && [] !== $boardOptions )
                    <form wire:submit="addToBoard" class="ec-order-add-board" data-add-board>
                        <x-artisanpack-select
                            id="order-add-board"
                            class="ec-grow"
                            size="sm"
                            :label="__( 'Add to board' )"
                            :options="ControlOptions::from( $boardOptions )"
                            :placeholder="__( 'Choose a board' )"
                            wire:model="boardToAdd"
                        />
                        <x-artisanpack-button type="submit" variant="outline" color="neutral" size="sm" wire:loading.attr="disabled" wire:target="addToBoard">{{ __( 'Add' ) }}</x-artisanpack-button>
                    </form>
                @endif
            </x-artisanpack-card>

            @foreach ( $sidePanels as $panel )
                <div class="ec-order-panel" data-order-panel="{{ $panel['key'] }}" wire:key="order-panel-wrap-{{ $panel['key'] }}">
                    @livewire( $panel['component'], [ 'order' => $order ], key( 'order-panel-' . $panel['key'] ) )
                </div>
            @endforeach
        </div>

        <div class="ec-order-col" data-order-column="main">
            {{-- Items --}}
            <x-artisanpack-card :title="__( 'Items' )" :class="$items->isNotEmpty() ? 'ec-card-flush' : ''" data-order-items>
                @if ( $items->isEmpty() )
                    <p class="ec-muted">{{ __( 'This order has no items.' ) }}</p>
                @else
                    <div data-scope="table" data-part="scroll" class="ec-table">
                        <table data-scope="table" data-part="table">
                            <caption class="apui-sr-only">{{ __( 'Items in order #:number', [ 'number' => $order->order_number ] ) }}</caption>
                            <thead>
                                <tr>
                                    <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Item' ) }}</th>
                                    <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( 'Qty' ) }}</th>
                                    <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( 'Unit price' ) }}</th>
                                    <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( 'Discount' ) }}</th>
                                    <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ TaxLabel::for() }}</th>
                                    <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( 'Total' ) }}</th>
                                    <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Fulfillment' ) }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ( $items as $item )
                                    @php
                                        $sku      = (string) data_get( $item->product_snapshot, 'sku', '' );
                                        $options  = Show::itemOptions( $item );
                                        $discount = (int) $item->discount_amount;
                                    @endphp
                                    <tr data-scope="table" data-part="row" wire:key="order-item-{{ $item->id }}">
                                        <th data-scope="table" data-part="cell" scope="row" class="ec-cell-wrap">
                                            <span class="ec-strong">{{ Show::itemName( $item ) }}</span>
                                            @if ( [] !== $options || '' !== $sku )
                                                <span class="ec-cell-secondary" data-item-details>
                                                    @foreach ( $options as $option )
                                                        {{ $option }}@if ( ! $loop->last || '' !== $sku )<span aria-hidden="true"> · </span>@endif
                                                    @endforeach
                                                    @if ( '' !== $sku )
                                                        <span class="apui-sr-only">{{ __( 'SKU' ) }}</span>
                                                        <bdi dir="ltr" class="ec-mono ec-order-sku">{{ $sku }}</bdi>
                                                    @endif
                                                </span>
                                            @endif
                                            @if ( null === $item->product_id )
                                                <x-artisanpack-badge size="sm" color="neutral" class="ec-order-gone" data-item-gone>{{ __( 'No longer in the catalog' ) }}</x-artisanpack-badge>
                                            @endif
                                        </th>
                                        <td data-scope="table" data-part="cell" data-numeric class="ec-money">{{ $item->quantity }}</td>
                                        <td data-scope="table" data-part="cell" data-numeric><x-artisanpack-ec-admin-money :amount="$item->unit_price_amount" :currency="$item->unit_price_currency" /></td>
                                        <td data-scope="table" data-part="cell" data-numeric @class( [ 'ec-cell-muted' => 0 === $discount ] )><x-artisanpack-ec-admin-money :amount="$discount > 0 ? -$discount : 0" :currency="$item->discount_currency" /></td>
                                        <td data-scope="table" data-part="cell" data-numeric><x-artisanpack-ec-admin-money :amount="$item->tax_amount" :currency="$item->tax_currency" /></td>
                                        <td data-scope="table" data-part="cell" data-numeric><x-artisanpack-ec-admin-money :amount="$item->total_amount" :currency="$item->total_currency" /></td>
                                        <td data-scope="table" data-part="cell"><x-artisanpack-ec-status-badge type="fulfillment" :value="$item->fulfillment_status" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-artisanpack-card>

            {{-- Totals --}}
            <x-artisanpack-card :title="__( 'Totals' )" class="ec-card-flush" data-order-totals-card>
                <div data-scope="table" data-part="scroll" data-density="compact" class="ec-table ec-order-totals">
                    <table data-scope="table" data-part="table" data-order-totals>
                        <caption class="apui-sr-only">{{ __( 'Totals for order #:number', [ 'number' => $order->order_number ] ) }}</caption>
                        <thead>
                            <tr>
                                <th data-scope="table" data-part="head-cell" scope="col"><span class="apui-sr-only">{{ __( 'Line' ) }}</span></th>
                                <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ $totals['currency'] }}</th>
                                @if ( null !== $totals['baseCurrency'] )
                                    <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( ':currency (store currency)', [ 'currency' => $totals['baseCurrency'] ] ) }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ( $totals['lines'] as $line )
                                <tr data-scope="table" data-part="row" wire:key="order-total-{{ $line['key'] }}" data-total-line="{{ $line['key'] }}" @if ( $line['emphasis'] ) data-emphasis @endif>
                                    <th data-scope="table" data-part="cell" scope="row">{{ $line['label'] }}</th>
                                    <td data-scope="table" data-part="cell" data-numeric><x-artisanpack-ec-admin-money :amount="$line['amount']" :currency="$totals['currency']" /></td>
                                    @if ( null !== $totals['baseCurrency'] )
                                        <td data-scope="table" data-part="cell" data-numeric class="ec-cell-muted"><x-artisanpack-ec-admin-money :amount="$line['base']" :currency="$totals['baseCurrency']" /></td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ( null !== $totals['baseCurrency'] )
                    <p class="ec-muted ec-order-rate-note" data-rate-note>{{ __( 'Store-currency amounts use the exchange rate recorded when the order was placed.' ) }}</p>
                @endif
            </x-artisanpack-card>

            {{-- Digital --}}
            @if ( $downloads->isNotEmpty() || $licenseKeys->isNotEmpty() )
                <x-artisanpack-card :title="__( 'Digital' )" data-order-digital>
                    @if ( $downloads->isNotEmpty() )
                        <section class="ec-order-sub" aria-labelledby="order-downloads-heading">
                            <h4 id="order-downloads-heading" class="ec-subheading">{{ __( 'Downloads' ) }}</h4>
                            <ul class="ec-list">
                                @foreach ( $downloads as $download )
                                    <li wire:key="order-download-{{ $download->id }}" class="ec-item-head">
                                        <span class="ec-strong">{{ $download->file?->label ?? __( 'Deleted file' ) }}</span>
                                        @if ( $download->isExpired() )
                                            <x-artisanpack-badge size="sm" color="neutral">{{ __( 'Expired' ) }}</x-artisanpack-badge>
                                        @endif
                                        <span class="ec-muted ec-order-meta">
                                            {{ trans_choice( ':count download|:count downloads', (int) $download->download_count, [ 'count' => (int) $download->download_count ] ) }}
                                            <span aria-hidden="true">·</span>
                                            @if ( null === $download->downloads_remaining )
                                                {{ __( 'No download limit' ) }}
                                            @else
                                                {{ trans_choice( ':count left|:count left', (int) $download->downloads_remaining, [ 'count' => (int) $download->downloads_remaining ] ) }}
                                            @endif
                                            <span aria-hidden="true">·</span>
                                            {{ null === $download->expires_at ? __( 'Never expires' ) : __( 'Expires :date', [ 'date' => LocalizedDate::format( $download->expires_at ) ] ) }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    @if ( $licenseKeys->isNotEmpty() )
                        <section class="ec-order-sub" aria-labelledby="order-license-keys-heading">
                            <h4 id="order-license-keys-heading" class="ec-subheading">{{ __( 'License keys' ) }}</h4>
                            <ul class="ec-list">
                                @foreach ( $licenseKeys as $licenseKey )
                                    <li wire:key="order-license-{{ $licenseKey->id }}" class="ec-item-head">
                                        <bdi dir="ltr" class="ec-mono">{{ $canViewKeys ? $licenseKey->key : str_repeat( '•', 8 ) . mb_substr( (string) $licenseKey->key, -4 ) }}</bdi>
                                        @if ( $licenseKey->is_revoked )
                                            <x-artisanpack-badge size="sm" color="error">{{ __( 'Revoked' ) }}</x-artisanpack-badge>
                                        @elseif ( $licenseKey->isExpired() )
                                            <x-artisanpack-badge size="sm" color="neutral">{{ __( 'Expired' ) }}</x-artisanpack-badge>
                                        @endif
                                        <span class="ec-muted ec-order-meta">
                                            {{ null === $licenseKey->activations_limit
                                                ? trans_choice( ':count activation|:count activations', (int) $licenseKey->activations_count, [ 'count' => (int) $licenseKey->activations_count ] )
                                                : __( ':used of :limit activations', [ 'used' => (int) $licenseKey->activations_count, 'limit' => (int) $licenseKey->activations_limit ] ) }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                </x-artisanpack-card>
            @endif

            @foreach ( $mainPanels as $panel )
                <div class="ec-order-panel" data-order-panel="{{ $panel['key'] }}" wire:key="order-panel-wrap-{{ $panel['key'] }}">
                    @livewire( $panel['component'], [ 'order' => $order ], key( 'order-panel-' . $panel['key'] ) )
                </div>
            @endforeach

            {{-- Notes (the command palette's "Add note to #…" jumps here) --}}
            <div id="order-notes" tabindex="-1" class="ec-focus-target">
                @livewire( 'artisanpack-ecommerce-admin-notes', [ 'subject' => $order ], key( 'order-notes' ) )
            </div>

            {{-- Timeline --}}
            @livewire( 'artisanpack-ecommerce-admin-timeline', [ 'subject' => $order ], key( 'order-timeline' ) )
        </div>
    </div>
</div>
