{{--
    Variable product panel. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels\VariablePanel.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels\VariablePanel;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia;

    $attributes = (array) ( $state['attributes'] ?? [] );
    $variants   = (array) ( $state['variants'] ?? [] );
@endphp
<div class="flex flex-col gap-6" data-variable-panel>
    {{-- Attributes --}}
    <section aria-labelledby="variable-attributes-heading">
        <h3 id="variable-attributes-heading" class="mb-2 text-lg font-semibold">{{ __( 'Attributes' ) }}</h3>
        @error( 'state.attributes' )
            <p class="mb-2 text-sm text-error" role="alert">{{ $message }}</p>
        @enderror

        @if ( [] === $attributes )
            <p class="mb-3 text-sm opacity-75">{{ __( 'Add attributes such as size or colour, then generate a variant for each combination.' ) }}</p>
        @endif

        <ol class="flex list-none flex-col gap-3">
            @foreach ( $attributes as $a => $attribute )
                <li wire:key="attribute-{{ $attribute['uid'] }}" class="rounded-box border border-base-300 p-3" data-attribute="{{ $a }}">
                    <div class="grid gap-3 md:grid-cols-[1fr_1fr_auto_auto] md:items-end">
                        <x-artisanpack-input id="attribute-{{ $a }}-label" :label="__( 'Attribute' )" :placeholder="__( 'Size' )" wire:model.blur="state.attributes.{{ $a }}.label" :disabled="$readOnly" />
                        <x-artisanpack-input id="attribute-{{ $a }}-key" :label="__( 'Key' )" :hint="__( 'Optional; made from the label.' )" wire:model.blur="state.attributes.{{ $a }}.key" :disabled="$readOnly" />
                        <x-artisanpack-toggle id="attribute-{{ $a }}-variation" :label="__( 'Used for variations' )" wire:model.live="state.attributes.{{ $a }}.is_variation" :disabled="$readOnly" />
                        @unless ( $readOnly )
                            <div class="flex gap-1">
                                <x-artisanpack-button variant="ghost" size="sm" icon="o-arrow-up" wire:click="moveAttribute( {{ $a }}, -1 )" wire:loading.attr="disabled" data-reorder="up" data-reorder-list="attributes" data-reorder-key="attribute-{{ $attribute['uid'] }}" data-reorder-item="{{ '' !== $attribute['label'] ? $attribute['label'] : __( 'Attribute :number', [ 'number' => $a + 1 ] ) }}" :disabled="$loop->first" :aria-label="__( 'Move attribute :number up', [ 'number' => $a + 1 ] )" />
                                <x-artisanpack-button variant="ghost" size="sm" icon="o-arrow-down" wire:click="moveAttribute( {{ $a }}, 1 )" wire:loading.attr="disabled" data-reorder="down" data-reorder-list="attributes" data-reorder-key="attribute-{{ $attribute['uid'] }}" data-reorder-item="{{ '' !== $attribute['label'] ? $attribute['label'] : __( 'Attribute :number', [ 'number' => $a + 1 ] ) }}" :disabled="$loop->last" :aria-label="__( 'Move attribute :number down', [ 'number' => $a + 1 ] )" />
                                <x-artisanpack-button variant="ghost" size="sm" icon="o-trash" wire:click="removeAttribute( {{ $a }} )" wire:loading.attr="disabled" :aria-label="__( 'Remove attribute :number', [ 'number' => $a + 1 ] )" />
                            </div>
                        @endunless
                    </div>

                    <fieldset class="mt-3">
                        <legend class="text-sm font-semibold">{{ __( 'Values' ) }}</legend>
                        <ul class="mt-2 flex list-none flex-col gap-2">
                            @foreach ( (array) ( $attribute['values'] ?? [] ) as $v => $value )
                                <li wire:key="value-{{ $value['uid'] }}" class="flex flex-wrap items-end gap-2" data-attribute-value="{{ $a }}-{{ $v }}">
                                    <x-artisanpack-input id="value-{{ $a }}-{{ $v }}-label" class="min-w-40" :label="__( 'Value' )" wire:model.blur="state.attributes.{{ $a }}.values.{{ $v }}.label" :disabled="$readOnly" />
                                    <x-artisanpack-input id="value-{{ $a }}-{{ $v }}-swatch" class="w-40" :label="__( 'Swatch' )" :hint="__( 'Colour code or image reference.' )" wire:model.blur="state.attributes.{{ $a }}.values.{{ $v }}.swatch" :disabled="$readOnly" />
                                    @if ( 1 === preg_match( '/^#[0-9a-fA-F]{3,8}$/', (string) ( $value['swatch'] ?? '' ) ) )
                                        <span class="mb-3 inline-block size-6 rounded border border-base-300" style="background-color: {{ $value['swatch'] }}" aria-hidden="true"></span>
                                    @endif
                                    @unless ( $readOnly )
                                        <div class="mb-1 flex gap-1">
                                            <x-artisanpack-button variant="ghost" size="xs" icon="o-arrow-up" wire:click="moveValue( {{ $a }}, {{ $v }}, -1 )" wire:loading.attr="disabled" data-reorder="up" data-reorder-list="values-{{ $attribute['uid'] }}" data-reorder-key="value-{{ $value['uid'] }}" data-reorder-item="{{ $value['label'] ?: __( 'value' ) }}" :disabled="$loop->first" :aria-label="__( 'Move :value up', [ 'value' => $value['label'] ?: __( 'value' ) ] )" />
                                            <x-artisanpack-button variant="ghost" size="xs" icon="o-arrow-down" wire:click="moveValue( {{ $a }}, {{ $v }}, 1 )" wire:loading.attr="disabled" data-reorder="down" data-reorder-list="values-{{ $attribute['uid'] }}" data-reorder-key="value-{{ $value['uid'] }}" data-reorder-item="{{ $value['label'] ?: __( 'value' ) }}" :disabled="$loop->last" :aria-label="__( 'Move :value down', [ 'value' => $value['label'] ?: __( 'value' ) ] )" />
                                            <x-artisanpack-button variant="ghost" size="xs" icon="o-x-mark" wire:click="removeValue( {{ $a }}, {{ $v }} )" wire:loading.attr="disabled" :aria-label="__( 'Remove :value', [ 'value' => $value['label'] ?: __( 'value' ) ] )" />
                                        </div>
                                    @endunless
                                </li>
                            @endforeach
                        </ul>
                        @unless ( $readOnly )
                            <x-artisanpack-button class="mt-2" variant="ghost" size="sm" icon="o-plus" wire:click="addValue( {{ $a }} )" :label="__( 'Add value' )" />
                        @endunless
                    </fieldset>
                </li>
            @endforeach
        </ol>

        @unless ( $readOnly )
            <x-artisanpack-button class="mt-3" variant="outline" size="sm" icon="o-plus" wire:click="addAttribute" :label="__( 'Add attribute' )" />
        @endunless
    </section>

    {{-- Generator --}}
    @unless ( $readOnly )
        <section class="rounded-box bg-base-200 p-4" aria-labelledby="variable-generate-heading">
            <h3 id="variable-generate-heading" class="font-semibold">{{ __( 'Generate variants' ) }}</h3>
            <p class="mb-3 text-sm opacity-75">
                {{ trans_choice( 'The attributes describe :count combination.|The attributes describe :count combinations.', $matrixSize, [ 'count' => $matrixSize ] ) }}
                {{ __( 'Existing variants are kept; only missing combinations are added.' ) }}
            </p>

            @if ( $pendingGenerate > 0 )
                <div role="alertdialog" aria-labelledby="variable-generate-confirm" class="rounded-box border border-warning bg-base-100 p-3" x-init="$nextTick( () => $el.querySelector( 'button' )?.focus() )" data-generate-confirm>
                    <p id="variable-generate-confirm" class="mb-2">{{ trans_choice( 'This adds :count variant. Continue?|This adds :count variants. Continue?', $pendingGenerate, [ 'count' => $pendingGenerate ] ) }}</p>
                    <div class="flex gap-2">
                        <x-artisanpack-button color="primary" size="sm" wire:click="confirmGenerate" data-focus-return="generate-variants" wire:loading.attr="disabled" :label="__( 'Generate :count variants', [ 'count' => $pendingGenerate ] )" />
                        <x-artisanpack-button variant="ghost" size="sm" wire:click="cancelGenerate" data-focus-return="generate-variants" :label="__( 'Cancel' )" />
                    </div>
                </div>
            @else
                <x-artisanpack-button size="sm" icon="o-sparkles" wire:click="generateVariants" data-focus-key="generate-variants" wire:loading.attr="disabled" :label="__( 'Generate variants' )" />
            @endif
        </section>
    @endunless

    {{-- Variants --}}
    <section aria-labelledby="variable-variants-heading">
        <h3 id="variable-variants-heading" class="mb-2 text-lg font-semibold">{{ trans_choice( ':count variant|:count variants', count( $variants ), [ 'count' => count( $variants ) ] ) }}</h3>
        @error( 'state.variants' )
            <p class="mb-2 text-sm text-error" role="alert">{{ $message }}</p>
        @enderror

        @if ( [] === $variants )
            <p class="text-sm opacity-75">{{ __( 'No variants yet.' ) }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <caption class="sr-only">{{ __( 'Variants' ) }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __( 'Variant' ) }}</th>
                            <th scope="col">{{ __( 'SKU' ) }}</th>
                            @foreach ( $currencies as $currency )
                                <th scope="col">{{ __( 'Price (:currency)', [ 'currency' => $currency ] ) }}</th>
                            @endforeach
                            <th scope="col">{{ __( 'Stock' ) }}</th>
                            <th scope="col">{{ __( 'Image' ) }}</th>
                            <th scope="col"><span class="sr-only">{{ __( 'Actions' ) }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ( $variants as $i => $variant )
                            @php( $variantLabel = $variant['name'] ?: __( 'Variant :number', [ 'number' => $i + 1 ] ) )
                            <tr wire:key="variant-{{ $variant['id'] ?? 'new-' . $i . '-' . md5( json_encode( $variant['options'] ?? [] ) ) }}" data-variant-row="{{ $i }}">
                                <td class="min-w-48">
                                    <div class="mb-1 flex flex-wrap gap-1">
                                        @foreach ( (array) ( $variant['options'] ?? [] ) as $attributeUid => $valueUid )
                                            <x-artisanpack-badge :value="$optionLabels[ $attributeUid ][ $valueUid ] ?? '?'" class="badge-sm badge-outline" />
                                        @endforeach
                                    </div>
                                    <x-artisanpack-input id="variant-{{ $i }}-name" :label="__( 'Name of variant :number', [ 'number' => $i + 1 ] )" class="input-sm" wire:model.blur="state.variants.{{ $i }}.name" :disabled="$readOnly" />
                                </td>
                                <td class="min-w-36">
                                    <x-artisanpack-input id="variant-{{ $i }}-sku" :label="__( 'SKU for :variant', [ 'variant' => $variantLabel ] )" class="input-sm" wire:model.blur="state.variants.{{ $i }}.sku" :disabled="$readOnly" />
                                </td>
                                @foreach ( $currencies as $currency )
                                    <td class="min-w-36">
                                        <x-artisanpack-ec-money-input
                                            id="variant-{{ $i }}-price-{{ $currency }}"
                                            :currency="$currency"
                                            :label="__( ':currency price for :variant', [ 'currency' => $currency, 'variant' => $variantLabel ] )"
                                            wire:model="state.variants.{{ $i }}.prices.{{ $currency }}"
                                            :disabled="$readOnly"
                                        />
                                    </td>
                                @endforeach
                                <td class="min-w-28">
                                    <x-artisanpack-input id="variant-{{ $i }}-stock" type="number" step="1" class="input-sm" :label="__( 'Stock for :variant', [ 'variant' => $variantLabel ] )" wire:model.live.debounce.400ms="state.variants.{{ $i }}.stock" :disabled="$readOnly" />
                                </td>
                                <td class="min-w-40">
                                    @php( $preview = ProductMedia::mediaUrl( isset( $variant['image_media_id'] ) ? (int) $variant['image_media_id'] : null ) ?? ProductMedia::safeUrl( $variant['image_url'] ?? null ) )
                                    @if ( null !== $preview )
                                        <img src="{{ $preview }}" alt="{{ $variantLabel }}" class="mb-1 size-10 rounded object-cover" />
                                    @endif
                                    @if ( $mediaLibrary )
                                        @unless ( $readOnly )
                                            <x-artisanpack-button variant="ghost" size="xs" icon="o-photo" x-on:click="Livewire.dispatch( 'open-media-modal', { context: {{ \Illuminate\Support\Js::from( VariablePanel::MEDIA_CONTEXT . $i ) }} } )" :label="__( 'Choose image' )" :aria-label="__( 'Choose image for :variant', [ 'variant' => $variantLabel ] )" />
                                        @endunless
                                    @else
                                        <x-artisanpack-input id="variant-{{ $i }}-image" type="url" class="input-sm" :label="__( 'Image URL for :variant', [ 'variant' => $variantLabel ] )" wire:model.blur="state.variants.{{ $i }}.image_url" :disabled="$readOnly" />
                                    @endif
                                </td>
                                <td>
                                    @unless ( $readOnly )
                                        <div class="flex gap-1">
                                            <x-artisanpack-button variant="ghost" size="xs" icon="o-arrow-up" wire:click="moveVariant( {{ $i }}, -1 )" wire:loading.attr="disabled" data-reorder="up" data-reorder-list="variants" data-reorder-index="{{ $i }}" data-reorder-item="{{ $variantLabel }}" :disabled="$loop->first" :aria-label="__( 'Move :variant up', [ 'variant' => $variantLabel ] )" />
                                            <x-artisanpack-button variant="ghost" size="xs" icon="o-arrow-down" wire:click="moveVariant( {{ $i }}, 1 )" wire:loading.attr="disabled" data-reorder="down" data-reorder-list="variants" data-reorder-index="{{ $i }}" data-reorder-item="{{ $variantLabel }}" :disabled="$loop->last" :aria-label="__( 'Move :variant down', [ 'variant' => $variantLabel ] )" />
                                            <x-artisanpack-button variant="ghost" size="xs" icon="o-trash" wire:click="removeVariant( {{ $i }} )" wire:loading.attr="disabled" :aria-label="__( 'Delete :variant', [ 'variant' => $variantLabel ] )" />
                                        </div>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ( $stockChanged )
                <x-artisanpack-input
                    id="variants-stock-reason"
                    class="mt-3"
                    :label="__( 'Reason for the stock changes' )"
                    :hint="__( 'Recorded with every variant stock change in the activity log.' )"
                    wire:model="state.stock_reason"
                    data-variant-stock-reason
                />
            @endif
        @endif

        @unless ( $readOnly )
            <x-artisanpack-button class="mt-3" variant="ghost" size="sm" icon="o-plus" wire:click="addVariant" :label="__( 'Add a variant by hand' )" />
        @endunless
    </section>
</div>
