{{--
    Customer addresses tab (#109). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\AddressesTab.

    Saved addresses as a grid of cards: the label (or the first address line),
    default-shipping and default-billing badges with an icon, the address, and
    quiet actions to edit, make it the default for shipping or billing (only
    on cards that aren't already that default), or delete. Deleting asks in
    the card's footer and reminds staff that orders keep their own copy.
    Adding or editing opens an Address form card in place of the grid.

    The cards' markup is written out because x-artisanpack-card titles are
    always h3, and each address sits under the tab's "Saved addresses" h3.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div class="ec-stack" data-customer-addresses-tab>
    <div class="ec-section-head">
        <x-artisanpack-heading :level="3" :size="6" id="customer-addresses-heading-{{ $this->getId() }}">{{ __( 'Saved addresses' ) }}</x-artisanpack-heading>
        @if ( $canUpdate && ! $showForm )
            <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="plus" wire:click="startAdd" wire:loading.attr="disabled" wire:target="startAdd" data-focus-key="address-add" data-add-address>{{ __( 'Add address' ) }}</x-artisanpack-button>
        @endif
    </div>

    @if ( $showForm )
        @php
            // Where focus goes back to once the form closes: the Edit button of the address being edited, or Add address.
            $formReturnKey = null === $editingId ? 'address-add' : 'address-edit-' . $editingId;
        @endphp
        {{-- The first field takes focus when the form opens. --}}
        <form wire:submit="saveAddress" x-data x-init="$nextTick( () => $el.querySelector( 'input, select' )?.focus() )" data-address-form>
            <x-artisanpack-card :title="null === $editingId ? __( 'New address' ) : __( 'Edit address' )" :classes="[ 'body' => 'ec-stack' ]">
                @include( 'ecommerce-admin::partials.error-summary' )

                <x-artisanpack-input id="address.label" :label="__( 'Label' )" :hint="__( 'Optional, like Home or Office.' )" autocomplete="off" wire:model="address.label" />

                <x-artisanpack-ec-admin-address-form model="address" />

                <div class="ec-toggle-row">
                    <x-artisanpack-toggle id="address.is_default_shipping" :label="__( 'Default shipping' )" wire:model="address.is_default_shipping" />
                    <x-artisanpack-toggle id="address.is_default_billing" :label="__( 'Default billing' )" wire:model="address.is_default_billing" />
                </div>

                @error( 'address' )
                    <p class="ec-field-error" role="alert"><x-artisanpack-icon name="circle-alert" aria-hidden="true" />{{ $message }}</p>
                @enderror

                <x-slot:footer>
                    <x-artisanpack-button variant="ghost" color="neutral" wire:click="cancelForm" data-focus-return="{{ $formReturnKey }}">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                    <x-artisanpack-button type="submit" wire:loading.attr="disabled" wire:target="saveAddress" data-focus-return="{{ $formReturnKey }}">{{ __( 'Save address' ) }}</x-artisanpack-button>
                </x-slot:footer>
            </x-artisanpack-card>
        </form>
    @elseif ( $addresses->isEmpty() )
        <x-artisanpack-ec-admin-empty-state
            size="sm"
            class="ec-empty-sunken"
            :heading-level="4"
            icon="map-pin"
            :title="__( 'No saved addresses' )"
            :description="__( 'Addresses the customer saves at checkout, or that you add here, appear in this list.' )"
        />
    @else
        <ul class="ec-address-grid" aria-labelledby="customer-addresses-heading-{{ $this->getId() }}">
            @foreach ( $addresses as $savedAddress )
                @php
                    $addressId    = (int) $savedAddress->id;
                    $addressTitle = filled( $savedAddress->label ) ? (string) $savedAddress->label : ( filled( $savedAddress->address1 ) ? (string) $savedAddress->address1 : __( 'Address' ) );
                @endphp
                <li wire:key="customer-address-{{ $addressId }}" data-address="{{ $addressId }}">
                    <section class="apui-card ec-address-card" data-surface="default" aria-labelledby="address-title-{{ $addressId }}">
                        <div data-scope="card" data-part="header">
                            <div class="ec-card-title-row">
                                <h4 data-scope="card" data-part="title" id="address-title-{{ $addressId }}" class="ec-address-card-title">{{ $addressTitle }}</h4>
                                @if ( $savedAddress->is_default_shipping )
                                    <x-artisanpack-badge size="sm" color="primary" leading-icon="truck">{{ __( 'Default shipping' ) }}</x-artisanpack-badge>
                                @endif
                                @if ( $savedAddress->is_default_billing )
                                    <x-artisanpack-badge size="sm" color="primary" leading-icon="credit-card">{{ __( 'Default billing' ) }}</x-artisanpack-badge>
                                @endif
                            </div>
                        </div>

                        <div data-scope="card" data-part="body">
                            <x-artisanpack-ec-admin-address :address="$savedAddress" />
                        </div>

                        @if ( $deletingId === $addressId )
                            <div
                                class="ec-confirm ec-confirm-card"
                                role="alertdialog"
                                aria-modal="false"
                                aria-labelledby="address-delete-{{ $addressId }}"
                                x-data
                                x-init="$nextTick( () => $el.querySelector( '[data-confirm]' )?.focus() )"
                                data-address-delete-confirm
                            >
                                <x-artisanpack-icon name="triangle-alert" aria-hidden="true" />
                                <p id="address-delete-{{ $addressId }}">{{ __( 'Delete this address? Orders that used it keep their own copy.' ) }}</p>
                                <div class="ec-confirm-actions">
                                    <x-artisanpack-button
                                        color="error"
                                        size="sm"
                                        data-confirm
                                        wire:click="deleteAddress( {{ \Illuminate\Support\Js::from( $deleteToken ) }} )"
                                        wire:loading.attr="disabled"
                                        wire:target="deleteAddress"
                                    >{{ __( 'Delete address' ) }}</x-artisanpack-button>
                                    <x-artisanpack-button variant="ghost" color="neutral" size="sm" wire:click="cancelDelete" data-focus-return="address-delete-{{ $addressId }}">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                                </div>
                            </div>
                        @elseif ( $canUpdate )
                            <div data-scope="card" data-part="footer" class="ec-address-actions">
                                <x-artisanpack-button variant="ghost" color="neutral" size="xs" leading-icon="pencil" wire:click="startEdit( {{ $addressId }} )" wire:loading.attr="disabled" wire:target="startEdit" data-focus-key="address-edit-{{ $addressId }}">{{ __( 'Edit' ) }}</x-artisanpack-button>
                                @unless ( $savedAddress->is_default_shipping )
                                    <x-artisanpack-button variant="ghost" color="neutral" size="xs" wire:click="makeDefault( {{ $addressId }}, 'shipping' )" wire:loading.attr="disabled" wire:target="makeDefault">{{ __( 'Use for shipping' ) }}</x-artisanpack-button>
                                @endunless
                                @unless ( $savedAddress->is_default_billing )
                                    <x-artisanpack-button variant="ghost" color="neutral" size="xs" wire:click="makeDefault( {{ $addressId }}, 'billing' )" wire:loading.attr="disabled" wire:target="makeDefault">{{ __( 'Use for billing' ) }}</x-artisanpack-button>
                                @endunless
                                <x-artisanpack-button
                                    variant="ghost"
                                    color="neutral"
                                    size="xs"
                                    leading-icon="trash"
                                    icon-only
                                    class="ec-push-end"
                                    wire:click="confirmDelete( {{ $addressId }} )"
                                    wire:loading.attr="disabled"
                                    wire:target="confirmDelete"
                                    :aria-label="__( 'Delete :label', [ 'label' => $addressTitle ] )"
                                    data-focus-key="address-delete-{{ $addressId }}"
                                    data-delete-address
                                />
                            </div>
                        @endif
                    </section>
                </li>
            @endforeach
        </ul>
    @endif
</div>
