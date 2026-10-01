@php
    use ArtisanPackUI\Ecommerce\Support\LocalizedDate;
    use Illuminate\Support\Js;
@endphp
<div>
    <x-artisanpack-card :title="__( 'Fulfillment' )" shadow>
        <div class="flex flex-wrap items-center gap-3">
            <x-artisanpack-ec-status-badge type="fulfillment" :value="$order->fulfillment_status" />
            @if ( $canShip )
                <x-artisanpack-button size="sm" icon="o-truck" class="ms-auto" wire:click="startShipment" wire:loading.attr="disabled" :label="__( 'Create shipment' )" />
            @endif
        </div>

        @if ( null !== $unshippableReason )
            <p class="mt-2 text-sm opacity-75" role="status" data-unshippable>{{ $unshippableReason }}</p>
        @elseif ( [] === $remaining )
            <p class="mt-2 text-sm opacity-75">{{ __( 'Nothing on this order needs shipping.' ) }}</p>
        @elseif ( 0 === array_sum( $remaining ) )
            <p class="mt-2 text-sm opacity-75">{{ __( 'Every item has shipped.' ) }}</p>
        @endif

        @if ( $shipments->isEmpty() )
            <p class="mt-3 opacity-75">{{ __( 'No shipments yet.' ) }}</p>
        @else
            <ul class="mt-3 flex flex-col gap-3" data-shipments>
                @foreach ( $shipments as $shipment )
                    <li wire:key="shipment-{{ $shipment->id }}" class="rounded-box border border-base-300 p-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">{{ __( 'Shipment #:id', [ 'id' => $shipment->id ] ) }}</span>
                            <x-artisanpack-ec-status-badge type="shipment" :value="$shipment->status" />
                            @if ( null !== $shipment->label_id )
                                <x-artisanpack-badge :value="__( 'Label printed' )" class="badge-sm" color="neutral" />
                            @endif
                        </div>

                        <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                            <dt class="opacity-75">{{ __( 'Carrier' ) }}</dt>
                            <dd>{{ $shipment->carrier ?: '—' }}{{ $shipment->service ? ' · ' . $shipment->service : '' }}</dd>
                            <dt class="opacity-75">{{ __( 'Tracking' ) }}</dt>
                            <dd class="break-all">
                                @if ( $shipment->tracking_url && preg_match( '#^https?://#i', (string) $shipment->tracking_url ) )
                                    <a href="{{ $shipment->tracking_url }}" class="link" target="_blank" rel="noopener noreferrer">
                                        {{ $shipment->tracking_number ?: __( 'Track shipment' ) }}<span class="sr-only"> {{ __( '(opens in a new tab)' ) }}</span>
                                    </a>
                                @else
                                    {{ $shipment->tracking_number ?: '—' }}
                                @endif
                            </dd>
                            <dt class="opacity-75">{{ __( 'Shipped' ) }}</dt>
                            <dd>{{ null === $shipment->shipped_at ? '—' : LocalizedDate::format( $shipment->shipped_at ) }}</dd>
                            <dt class="opacity-75">{{ __( 'Delivered' ) }}</dt>
                            <dd>{{ null === $shipment->delivered_at ? '—' : LocalizedDate::format( $shipment->delivered_at ) }}</dd>
                            <dt class="opacity-75">{{ __( 'Items' ) }}</dt>
                            <dd>
                                <ul>
                                    @foreach ( $shipment->items as $line )
                                        <li wire:key="shipment-{{ $shipment->id }}-item-{{ $line->id }}">{{ __( ':item × :quantity', [ 'item' => $itemNames[ (int) $line->order_item_id ] ?? __( 'Item #:id', [ 'id' => $line->order_item_id ] ), 'quantity' => $line->quantity ] ) }}</li>
                                    @endforeach
                                </ul>
                            </dd>
                        </dl>

                        @if ( $canUpdate )
                            @if ( $editingShipmentId === (int) $shipment->id )
                                <form wire:submit="updateTracking" class="mt-3 grid gap-2 sm:grid-cols-3" data-tracking-form>
                                    <x-artisanpack-select id="shipment-{{ $shipment->id }}-status" :label="__( 'Status' )" :options="$statusOptions" wire:model="editStatus" />
                                    <x-artisanpack-input id="shipment-{{ $shipment->id }}-tracking-number" :label="__( 'Tracking number' )" wire:model="editTrackingNumber" />
                                    <x-artisanpack-input id="shipment-{{ $shipment->id }}-tracking-url" type="url" :label="__( 'Tracking link' )" wire:model="editTrackingUrl" />
                                    <div class="flex gap-2 sm:col-span-3">
                                        <x-artisanpack-button type="submit" size="sm" wire:loading.attr="disabled" :label="__( 'Save tracking' )" />
                                        <x-artisanpack-button variant="ghost" size="sm" wire:click="$set( 'editingShipmentId', null )" :label="__( 'Cancel' )" />
                                    </div>
                                </form>
                            @else
                                <div class="mt-3 flex flex-wrap items-end gap-2">
                                    <x-artisanpack-button
                                        variant="ghost"
                                        size="sm"
                                        icon="o-pencil-square"
                                        wire:click="editTracking( {{ (int) $shipment->id }} )"
                                        :label="__( 'Update tracking' )"
                                        :aria-label="__( 'Update tracking for shipment #:id', [ 'id' => $shipment->id ] )"
                                    />
                                    @if ( isset( $labelTokens[ (int) $shipment->id ] ) )
                                        @if ( count( $labelProviders ) > 1 )
                                            <x-artisanpack-select
                                                id="shipment-{{ $shipment->id }}-label-provider"
                                                :label="__( 'Label provider' )"
                                                :options="collect( $labelProviders )->map( fn ( $label, $key ) => [ 'id' => $key, 'name' => $label ] )->values()->all()"
                                                :placeholder="__( 'Choose a provider' )"
                                                placeholder-value=""
                                                wire:model="labelProvider"
                                            />
                                        @endif
                                        <x-artisanpack-button
                                            size="sm"
                                            icon="o-printer"
                                            wire:click="buyLabel( {{ (int) $shipment->id }}, {{ Js::from( $labelTokens[ (int) $shipment->id ] ) }} )"
                                            wire:loading.attr="disabled"
                                            :label="__( 'Buy label' )"
                                            :aria-label="__( 'Buy a label for shipment #:id', [ 'id' => $shipment->id ] )"
                                        />
                                    @endif
                                </div>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-artisanpack-card>

    @if ( null !== $shipmentToken )
        <x-artisanpack-modal wire:model="creating" :title="__( 'Create shipment' )" box-class="max-w-2xl" separator>
            <div class="flex flex-col gap-4" data-shipment-form>
                @error( 'shipment' )
                    <x-artisanpack-alert color="error" icon="o-exclamation-circle" role="alert">{{ $message }}</x-artisanpack-alert>
                @enderror
                @error( 'quantities' )
                    <p class="text-sm text-error" role="alert">{{ $message }}</p>
                @enderror

                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <caption class="sr-only">{{ __( 'Items to ship' ) }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __( 'Item' ) }}</th>
                                <th scope="col">{{ __( 'Left to ship' ) }}</th>
                                <th scope="col">{{ __( 'Ship now' ) }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ( $remaining as $itemId => $left )
                                <tr wire:key="ship-item-{{ $itemId }}">
                                    <th scope="row" class="font-normal">{{ $itemNames[ $itemId ] ?? __( 'Item #:id', [ 'id' => $itemId ] ) }}</th>
                                    <td class="tabular-nums">{{ $left }}</td>
                                    <td class="w-28">
                                        <x-artisanpack-input
                                            id="ship-item-{{ $itemId }}"
                                            type="number"
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

                <div class="grid gap-3 sm:grid-cols-2">
                    <x-artisanpack-select
                        id="shipment-method"
                        :label="__( 'Shipping method' )"
                        :options="$methodOptions"
                        :placeholder="__( 'Choose a method' )"
                        placeholder-value=""
                        wire:model="method"
                    />
                    <x-artisanpack-input id="shipment-carrier" :label="__( 'Carrier' )" wire:model="carrier" />
                    <x-artisanpack-input id="shipment-service" :label="__( 'Service' )" wire:model="service" />
                    <x-artisanpack-input id="shipment-tracking-number" :label="__( 'Tracking number' )" wire:model="trackingNumber" />
                    <x-artisanpack-input id="shipment-tracking-url" type="url" :label="__( 'Tracking link' )" class="sm:col-span-2" wire:model="trackingUrl" />
                </div>
            </div>

            <x-slot:actions>
                <x-artisanpack-button variant="ghost" wire:click="$set( 'creating', false )" :label="__( 'Cancel' )" />
                <x-artisanpack-button
                    color="primary"
                    wire:click="createShipment( {{ Js::from( $shipmentToken ) }} )"
                    wire:loading.attr="disabled"
                    :label="__( 'Create shipment' )"
                />
            </x-slot:actions>
        </x-artisanpack-modal>
    @endif
</div>
