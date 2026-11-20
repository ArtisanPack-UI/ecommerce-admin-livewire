{{--
    Grouped and bundled product panel (#106). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels\ChildrenPanel.

    The products a grouped product shows together, or a bundle sells as
    one. Each is a raised row with a product picker, an optional variant
    ("Shopper chooses" leaves it to the storefront; a product without
    variants says so instead of showing a dead select), a quantity, and
    move and remove buttons. The order is the order shoppers see, and the
    move buttons are the keyboard path for it.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;

    $children = array_values( (array) ( $state['children'] ?? [] ) );
@endphp
<div data-children-panel>
    <x-artisanpack-card :classes="[ 'body' => 'ec-stack-sm' ]">
        <p class="ec-muted ec-measure">{{ __( 'A grouped product lists these products together for shoppers to buy separately; a bundle sells them as one item at its own price.' ) }}</p>

        @error( 'state.children' )
            <p class="ec-field-error" role="alert"><x-artisanpack-icon name="circle-alert" />{{ $message }}</p>
        @enderror

        @if ( [] === $children )
            <p class="ec-muted">{{ __( 'No products added yet.' ) }}</p>
        @else
            <ol class="ec-list" aria-label="{{ __( 'Products included, in order' ) }}">
                @foreach ( $children as $i => $child )
                    @php
                        $position = __( 'Product :number', [ 'number' => $i + 1 ] );
                        $chosen   = collect( $childPickerOptions[ $i ] ?? [] )->first( static fn ( array $option ): bool => (string) ( $option['id'] ?? '' ) === (string) ( $child['product_id'] ?? '' ) );
                        $rowName  = filled( $chosen['name'] ?? null ) ? (string) $chosen['name'] : $position;
                    @endphp
                    <li wire:key="child-{{ $child['uid'] ?? $i }}" class="ec-item" data-child-row="{{ $i }}">
                        <div class="ec-child-row">
                            <x-artisanpack-ec-product-picker
                                :id="'child-' . $i . '-product'"
                                :model="'state.children.' . $i . '.product_id'"
                                :options="$childPickerOptions[ $i ] ?? []"
                                :label="$position"
                                single
                                live
                                :disabled="$readOnly"
                            />
                            @if ( [] === ( $variantOptions[ $i ] ?? [] ) )
                                <p class="ec-muted ec-child-no-variants" data-no-variants>{{ __( 'No variants' ) }}</p>
                            @else
                                <x-artisanpack-select
                                    id="child-{{ $i }}-variant"
                                    :label="__( 'Variant' )"
                                    :aria-label="__( 'Variant of :product', [ 'product' => $rowName ] )"
                                    :options="ControlOptions::from( $variantOptions[ $i ] )"
                                    :placeholder="__( 'Shopper chooses' )"
                                    wire:model="state.children.{{ $i }}.variant_id"
                                    :disabled="$readOnly"
                                />
                            @endif
                            <x-artisanpack-input
                                id="child-{{ $i }}-quantity"
                                type="number"
                                min="1"
                                step="1"
                                :label="__( 'Quantity' )"
                                :aria-label="__( 'Quantity of :product', [ 'product' => $rowName ] )"
                                wire:model="state.children.{{ $i }}.quantity"
                                :disabled="$readOnly"
                            />
                            @unless ( $readOnly )
                                <div class="ec-row-actions">
                                    <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="arrow-up" wire:click="moveChild( {{ $i }}, -1 )" wire:loading.attr="disabled" data-reorder="up" data-reorder-list="children" data-reorder-key="child-{{ $child['uid'] ?? $i }}" data-reorder-item="{{ $rowName }}" :disabled="$loop->first" :aria-label="__( 'Move :name up', [ 'name' => $rowName ] )" />
                                    <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="arrow-down" wire:click="moveChild( {{ $i }}, 1 )" wire:loading.attr="disabled" data-reorder="down" data-reorder-list="children" data-reorder-key="child-{{ $child['uid'] ?? $i }}" data-reorder-item="{{ $rowName }}" :disabled="$loop->last" :aria-label="__( 'Move :name down', [ 'name' => $rowName ] )" />
                                    <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="trash" wire:click="removeChild( {{ $i }} )" wire:loading.attr="disabled" :aria-label="__( 'Remove :item', [ 'item' => $rowName ] )" />
                                </div>
                            @endunless
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif

        @unless ( $readOnly )
            <div>
                <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="plus" wire:click="addChild">{{ __( 'Add product' ) }}</x-artisanpack-button>
            </div>
        @endunless
    </x-artisanpack-card>
</div>
