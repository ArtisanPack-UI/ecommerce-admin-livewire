{{--
    Grouped / bundled product panel. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels\ChildrenPanel.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php( $children = array_values( (array) ( $state['children'] ?? [] ) ) )
<div class="flex flex-col gap-4" data-children-panel>
    <p class="text-sm opacity-75">{{ __( 'A grouped product lists these products together for shoppers to buy separately; a bundle sells them as one item at its own price.' ) }}</p>

    @error( 'state.children' )
        <p class="text-sm text-error" role="alert">{{ $message }}</p>
    @enderror

    @if ( [] === $children )
        <p class="text-sm opacity-75">{{ __( 'No products added yet.' ) }}</p>
    @else
        <ol class="flex list-none flex-col gap-3" aria-label="{{ __( 'Products included, in order' ) }}">
            @foreach ( $children as $i => $child )
                <li wire:key="child-{{ $i }}-{{ $child['product_id'] ?? 'new' }}" class="grid gap-3 rounded-box border border-base-300 p-3 md:grid-cols-[2fr_1fr_8rem_auto] md:items-end" data-child-row="{{ $i }}">
                    <x-artisanpack-ec-product-picker
                        :id="'child-' . $i . '-product'"
                        :model="'state.children.' . $i . '.product_id'"
                        :options="$pickerOptions[ $i ] ?? []"
                        :label="__( 'Product :number', [ 'number' => $i + 1 ] )"
                        single
                        live
                        :disabled="$readOnly"
                    />
                    @if ( [] === ( $variantOptions[ $i ] ?? [] ) )
                        <p class="pb-3 text-sm opacity-75">{{ __( 'No variants' ) }}</p>
                    @else
                        <x-artisanpack-select
                            id="child-{{ $i }}-variant"
                            :label="__( 'Variant' )"
                            :options="$variantOptions[ $i ]"
                            :placeholder="__( 'Shopper chooses' )"
                            placeholder-value=""
                            wire:model="state.children.{{ $i }}.variant_id"
                            :disabled="$readOnly"
                        />
                    @endif
                    <x-artisanpack-input id="child-{{ $i }}-quantity" type="number" min="1" step="1" :label="__( 'Quantity' )" wire:model="state.children.{{ $i }}.quantity" :disabled="$readOnly" />
                    @unless ( $readOnly )
                        <div class="flex gap-1">
                            <x-artisanpack-button variant="ghost" size="sm" icon="o-arrow-up" wire:click="moveChild( {{ $i }}, -1 )" :disabled="$loop->first" :aria-label="__( 'Move product :number up', [ 'number' => $i + 1 ] )" />
                            <x-artisanpack-button variant="ghost" size="sm" icon="o-arrow-down" wire:click="moveChild( {{ $i }}, 1 )" :disabled="$loop->last" :aria-label="__( 'Move product :number down', [ 'number' => $i + 1 ] )" />
                            <x-artisanpack-button variant="ghost" size="sm" icon="o-trash" wire:click="removeChild( {{ $i }} )" :aria-label="__( 'Remove product :number', [ 'number' => $i + 1 ] )" />
                        </div>
                    @endunless
                </li>
            @endforeach
        </ol>
    @endif

    @unless ( $readOnly )
        <x-artisanpack-button class="self-start" variant="outline" size="sm" icon="o-plus" wire:click="addChild" :label="__( 'Add product' )" />
    @endunless
</div>
