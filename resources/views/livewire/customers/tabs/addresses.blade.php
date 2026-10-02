{{--
    A customer's addresses. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Customers\AddressesTab.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
<div class="flex flex-col gap-4">
    @if ( $canUpdate && ! $showForm )
        <div>
            <x-artisanpack-button icon="o-plus" wire:click="startAdd" :label="__( 'Add address' )" data-add-address />
        </div>
    @endif

    @if ( $showForm )
        <x-artisanpack-card :title="null === $editingId ? __( 'New address' ) : __( 'Edit address' )" shadow>
            <form wire:submit="saveAddress" class="flex flex-col gap-4" data-address-form>
                <x-artisanpack-input id="address.label" :label="__( 'Label' )" :hint="__( 'Optional, like Home or Office.' )" wire:model="address.label" />

                <x-artisanpack-ec-address-form model="address" />

                <div class="flex flex-wrap gap-4">
                    <x-artisanpack-toggle id="address.is_default_shipping" :label="__( 'Default shipping address' )" wire:model="address.is_default_shipping" />
                    <x-artisanpack-toggle id="address.is_default_billing" :label="__( 'Default billing address' )" wire:model="address.is_default_billing" />
                </div>

                @error( 'address' )
                    <p class="text-sm text-error" role="alert">{{ $message }}</p>
                @enderror

                <div class="flex gap-2">
                    <x-artisanpack-button type="submit" color="primary" wire:loading.attr="disabled" spinner="saveAddress" :label="__( 'Save address' )" />
                    <x-artisanpack-button variant="ghost" wire:click="cancelForm" :label="__( 'Cancel' )" />
                </div>
            </form>
        </x-artisanpack-card>
    @endif

    @if ( $addresses->isEmpty() )
        <x-artisanpack-ec-empty-state icon="o-map-pin" :title="__( 'No saved addresses' )" :description="__( 'Addresses the customer saves at checkout, or that you add here, appear in this list.' )" />
    @else
        <ul class="grid gap-4 sm:grid-cols-2" aria-label="{{ __( 'Saved addresses' ) }}">
            @foreach ( $addresses as $savedAddress )
                <li wire:key="customer-address-{{ $savedAddress->id }}" class="rounded-box border border-base-content/10 p-4" data-address="{{ $savedAddress->id }}">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <span class="font-semibold">{{ $savedAddress->label ?: __( 'Address' ) }}</span>
                        @if ( $savedAddress->is_default_shipping )
                            <x-artisanpack-badge :value="__( 'Default shipping' )" class="badge-sm" color="primary" />
                        @endif
                        @if ( $savedAddress->is_default_billing )
                            <x-artisanpack-badge :value="__( 'Default billing' )" class="badge-sm" color="primary" />
                        @endif
                    </div>

                    <x-artisanpack-ec-address :address="$savedAddress" />

                    @if ( $canUpdate )
                        <div class="mt-3 flex flex-wrap gap-1">
                            <x-artisanpack-button variant="ghost" size="sm" icon="o-pencil" wire:click="startEdit( {{ $savedAddress->id }} )" :label="__( 'Edit' )" />
                            @unless ( $savedAddress->is_default_shipping )
                                <x-artisanpack-button variant="ghost" size="sm" wire:click="makeDefault( {{ $savedAddress->id }}, 'shipping' )" wire:loading.attr="disabled" :label="__( 'Use for shipping' )" />
                            @endunless
                            @unless ( $savedAddress->is_default_billing )
                                <x-artisanpack-button variant="ghost" size="sm" wire:click="makeDefault( {{ $savedAddress->id }}, 'billing' )" wire:loading.attr="disabled" :label="__( 'Use for billing' )" />
                            @endunless
                            <x-artisanpack-button variant="ghost" size="sm" icon="o-trash" wire:click="confirmDelete( {{ $savedAddress->id }} )" :label="__( 'Delete' )" />
                        </div>
                    @endif

                    @if ( $deletingId === (int) $savedAddress->id )
                        <div role="alertdialog" aria-modal="false" aria-labelledby="address-delete-{{ $savedAddress->id }}" class="mt-3 rounded-box border border-warning p-3" x-data x-init="$nextTick( () => $el.querySelector( '[data-confirm]' )?.focus() )">
                            <p id="address-delete-{{ $savedAddress->id }}" class="mb-2">{{ __( 'Delete this address? Orders that used it keep their own copy.' ) }}</p>
                            <div class="flex gap-2">
                                <x-artisanpack-button
                                    color="error"
                                    size="sm"
                                    data-confirm
                                    wire:click="deleteAddress( {{ \Illuminate\Support\Js::from( $deleteToken ) }} )"
                                    wire:loading.attr="disabled"
                                    :label="__( 'Delete address' )"
                                />
                                <x-artisanpack-button variant="ghost" size="sm" wire:click="cancelDelete" :label="__( 'Cancel' )" />
                            </div>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
