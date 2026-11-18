{{--
    Fulfillment panel (#100): the order's shipments.

    The header carries the fulfillment status and the one action, Create
    shipment. Each shipment is a raised row with its status, carrier,
    tracking, dates and items, plus Update tracking (edited in place) and
    Buy label when a label provider is registered; both buttons are named
    for their shipment. Tracking links open in a new tab and say so.
    Creating a shipment opens a dialog with the units left to ship; lines
    with nothing left are disabled, and errors from the gateway or label
    provider show as an error Alert at the top of the dialog.

    The Card's markup is written out so the status badge can sit beside the
    title without becoming part of the heading.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
    use Illuminate\Support\Js;

    $titleId = 'order-fulfillment-title-' . $order->getKey();
@endphp
<div>
    <section class="apui-card" data-surface="default" aria-labelledby="{{ $titleId }}" data-fulfillment-panel>
        <div data-scope="card" data-part="header">
            <div class="ec-card-title-row">
                <h3 data-scope="card" data-part="title" id="{{ $titleId }}">{{ __( 'Fulfillment' ) }}</h3>
                <x-artisanpack-ec-status-badge type="fulfillment" :value="$order->fulfillment_status" />
            </div>
            @if ( $canShip )
                <div data-scope="card" data-part="actions">
                    <x-artisanpack-button size="sm" leading-icon="truck" wire:click="startShipment" wire:loading.attr="disabled" wire:target="startShipment" data-create-shipment>{{ __( 'Create shipment' ) }}</x-artisanpack-button>
                </div>
            @endif
        </div>

        <div data-scope="card" data-part="body" class="ec-stack-sm">
            @if ( null !== $unshippableReason )
                <p class="ec-muted" role="status" data-unshippable>{{ $unshippableReason }}</p>
            @elseif ( [] === $remaining )
                <p class="ec-muted" role="status">{{ __( 'Nothing on this order needs shipping.' ) }}</p>
            @elseif ( 0 === array_sum( $remaining ) )
                <p class="ec-muted" role="status">{{ __( 'Every item has shipped.' ) }}</p>
            @endif

            @if ( $shipments->isEmpty() )
                <x-artisanpack-ec-admin-empty-state size="sm" :heading-level="4" icon="truck" class="ec-empty-sunken" :title="__( 'No shipments yet' )" :description="__( 'Create a shipment when items leave the warehouse.' )" />
            @else
                <ul class="ec-list" data-shipments>
                    @foreach ( $shipments as $shipment )
                        @php
                            $shipmentName = __( 'Shipment #:id', [ 'id' => $shipment->id ] );
                        @endphp
                        <li wire:key="shipment-{{ $shipment->id }}" class="ec-item" data-shipment="{{ $shipment->id }}">
                            <div class="ec-item-head">
                                <span class="ec-strong">{{ $shipmentName }}</span>
                                <x-artisanpack-ec-status-badge type="shipment" :value="$shipment->status" />
                                @if ( null !== $shipment->label_id )
                                    <x-artisanpack-badge color="neutral" leading-icon="check" data-label-printed>{{ __( 'Label printed' ) }}</x-artisanpack-badge>
                                @endif
                            </div>

                            <dl class="ec-dl">
                                <dt>{{ __( 'Carrier' ) }}</dt>
                                <dd>
                                    @if ( filled( $shipment->carrier ) || filled( $shipment->service ) )
                                        {{ implode( ' · ', array_filter( [ (string) $shipment->carrier, (string) $shipment->service ], 'filled' ) ) }}
                                    @else
                                        <span aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'None' ) }}</span>
                                    @endif
                                </dd>
                                <dt>{{ __( 'Tracking' ) }}</dt>
                                <dd>
                                    @if ( $shipment->tracking_url && preg_match( '#^https?://#i', (string) $shipment->tracking_url ) )
                                        <x-artisanpack-link :href="$shipment->tracking_url" external :external-label="__( '(opens in a new tab)' )" data-tracking-link>
                                            @if ( filled( $shipment->tracking_number ) )
                                                <bdi dir="ltr" class="ec-mono">{{ $shipment->tracking_number }}</bdi>
                                            @else
                                                {{ __( 'Track shipment' ) }}
                                            @endif
                                        </x-artisanpack-link>
                                    @elseif ( filled( $shipment->tracking_number ) )
                                        <bdi dir="ltr" class="ec-mono">{{ $shipment->tracking_number }}</bdi>
                                    @else
                                        <span aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'None' ) }}</span>
                                    @endif
                                </dd>
                                <dt>{{ __( 'Shipped' ) }}</dt>
                                <dd>
                                    @if ( null !== $shipment->shipped_at )
                                        {{ LocalizedDate::format( $shipment->shipped_at ) }}
                                    @else
                                        <span aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'None' ) }}</span>
                                    @endif
                                </dd>
                                <dt>{{ __( 'Delivered' ) }}</dt>
                                <dd>
                                    @if ( null !== $shipment->delivered_at )
                                        {{ LocalizedDate::format( $shipment->delivered_at ) }}
                                    @else
                                        <span aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'None' ) }}</span>
                                    @endif
                                </dd>
                                <dt>{{ __( 'Items' ) }}</dt>
                                <dd>
                                    <ul class="ec-plain-list">
                                        @foreach ( $shipment->items as $line )
                                            <li wire:key="shipment-{{ $shipment->id }}-item-{{ $line->id }}">{{ __( ':item × :quantity', [ 'item' => $itemNames[ (int) $line->order_item_id ] ?? __( 'Item #:id', [ 'id' => $line->order_item_id ] ), 'quantity' => $line->quantity ] ) }}</li>
                                        @endforeach
                                    </ul>
                                </dd>
                            </dl>

                            @if ( $canUpdate )
                                @if ( $editingShipmentId === (int) $shipment->id )
                                    <form wire:submit="updateTracking" class="ec-sunken" data-tracking-form>
                                        <div class="ec-field-grid ec-field-grid-3">
                                            <x-artisanpack-select id="shipment-{{ $shipment->id }}-status" :label="__( 'Status' )" :options="ControlOptions::from( $statusOptions )" wire:model="editStatus" />
                                            <x-artisanpack-input id="shipment-{{ $shipment->id }}-tracking-number" :label="__( 'Tracking number' )" dir="ltr" wire:model="editTrackingNumber" />
                                            <x-artisanpack-input id="shipment-{{ $shipment->id }}-tracking-url" type="url" :label="__( 'Tracking link' )" dir="ltr" wire:model="editTrackingUrl" />
                                        </div>
                                        <div class="ec-actions">
                                            <x-artisanpack-button type="submit" size="sm" wire:loading.attr="disabled" wire:target="updateTracking">{{ __( 'Save tracking' ) }}</x-artisanpack-button>
                                            <x-artisanpack-button variant="ghost" color="neutral" size="sm" wire:click="$set( 'editingShipmentId', null )">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                                        </div>
                                    </form>
                                @else
                                    <div class="ec-actions">
                                        <x-artisanpack-button
                                            variant="ghost"
                                            color="neutral"
                                            size="sm"
                                            leading-icon="pencil"
                                            wire:click="editTracking( {{ (int) $shipment->id }} )"
                                            :aria-label="__( 'Update tracking for shipment #:id', [ 'id' => $shipment->id ] )">{{ __( 'Update tracking' ) }}</x-artisanpack-button>
                                        @if ( isset( $labelTokens[ (int) $shipment->id ] ) )
                                            @if ( count( $labelProviders ) > 1 )
                                                <x-artisanpack-select
                                                    id="shipment-{{ $shipment->id }}-label-provider"
                                                    size="sm"
                                                    :label="__( 'Label provider' )"
                                                    :options="ControlOptions::from( collect( $labelProviders )->map( fn ( $label, $key ) => [ 'id' => $key, 'name' => $label ] )->values()->all() )"
                                                    :placeholder="__( 'Choose a provider' )"
                                                    wire:model="labelProvider"
                                                />
                                            @endif
                                            <x-artisanpack-button
                                                variant="outline"
                                                color="neutral"
                                                size="sm"
                                                leading-icon="file-down"
                                                wire:click="buyLabel( {{ (int) $shipment->id }}, {{ Js::from( $labelTokens[ (int) $shipment->id ] ) }} )"
                                                wire:loading.attr="disabled"
                                                wire:target="buyLabel"
                                                :aria-label="__( 'Buy label for shipment #:id', [ 'id' => $shipment->id ] )">{{ __( 'Buy label' ) }}</x-artisanpack-button>
                                        @endif
                                    </div>
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    @if ( null !== $shipmentToken )
        <x-artisanpack-dialog
            wire:model="creating"
            size="lg"
            :title="__( 'Create shipment' )"
            :description="__( 'Choose how many units of each item are in this parcel.' )"
            :close-label="__( 'Close' )"
            :classes="[ 'footer' => 'ec-dialog-footer-divided', 'body' => 'ec-stack' ]"
            data-shipment-form
        >
            @error( 'shipment' )
                <x-artisanpack-alert color="error" icon="circle-alert" role="alert" data-shipment-error>{{ $message }}</x-artisanpack-alert>
            @enderror
            @error( 'quantities' )
                <x-artisanpack-alert color="error" icon="circle-alert" role="alert">{{ $message }}</x-artisanpack-alert>
            @enderror

            <div class="apui-table ec-table">
                <div data-scope="table" data-part="scroll" data-density="compact">
                    <table data-scope="table" data-part="table">
                        <caption class="apui-sr-only">{{ __( 'Items to ship' ) }}</caption>
                        <thead>
                            <tr>
                                <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Item' ) }}</th>
                                <th data-scope="table" data-part="head-cell" scope="col" data-numeric>{{ __( 'Left to ship' ) }}</th>
                                <th data-scope="table" data-part="head-cell" scope="col" class="ec-col-qty">{{ __( 'Ship now' ) }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ( $remaining as $itemId => $left )
                                <tr data-scope="table" data-part="row" wire:key="ship-item-{{ $itemId }}">
                                    <th data-scope="table" data-part="cell" scope="row">{{ $itemNames[ $itemId ] ?? __( 'Item #:id', [ 'id' => $itemId ] ) }}</th>
                                    <td data-scope="table" data-part="cell" data-numeric>{{ $left }}</td>
                                    <td data-scope="table" data-part="cell">
                                        <x-artisanpack-input
                                            id="ship-item-{{ $itemId }}"
                                            type="number"
                                            size="sm"
                                            min="0"
                                            :max="$left"
                                            :disabled="0 === $left"
                                            :aria-label="__( 'Units of :item to ship', [ 'item' => $itemNames[ $itemId ] ?? $itemId ] )"
                                            wire:model="quantities.{{ $itemId }}"
                                        />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="ec-field-grid">
                <x-artisanpack-select
                    id="shipment-method"
                    :label="__( 'Shipping method' )"
                    :options="ControlOptions::from( $methodOptions )"
                    :placeholder="__( 'Choose a method' )"
                    wire:model="method"
                />
                <x-artisanpack-input id="shipment-carrier" :label="__( 'Carrier' )" wire:model="carrier" />
                <x-artisanpack-input id="shipment-service" :label="__( 'Service' )" wire:model="service" />
                <x-artisanpack-input id="shipment-tracking-number" :label="__( 'Tracking number' )" :placeholder="__( 'Add it now or later' )" dir="ltr" wire:model="trackingNumber" />
                <div class="ec-span-2">
                    <x-artisanpack-input id="shipment-tracking-url" type="url" :label="__( 'Tracking link' )" dir="ltr" wire:model="trackingUrl" />
                </div>
            </div>

            <x-slot:footer>
                <x-artisanpack-button variant="ghost" color="neutral" wire:click="$set( 'creating', false )">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                <x-artisanpack-button
                    wire:click="createShipment( {{ Js::from( $shipmentToken ) }} )"
                    wire:loading.attr="disabled"
                    wire:target="createShipment">{{ __( 'Create shipment' ) }}</x-artisanpack-button>
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif
</div>
