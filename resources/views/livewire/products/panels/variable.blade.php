{{--
    Variable product panel (#104). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels\VariablePanel.

    Attributes, their values, and the variants made from them. Attributes
    are raised rows with their values in a sunken well; a dashed panel
    generates the missing combinations after an inline confirm that says
    how many will be added (generating never removes a variant). Variants
    are a table with option badges and an editable name, SKU, a price per
    enabled currency, stock and image. An empty price in a currency other
    than the store's reads "From store currency", a duplicate SKU is
    flagged on the row that clashes, and a stock edit reveals the reason
    field. Every control in the table is named for its variant.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Panels\VariablePanel;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia;
    use Illuminate\Support\Js;

    $attributes = (array) ( $state['attributes'] ?? [] );
    $variants   = (array) ( $state['variants'] ?? [] );
@endphp
<div data-variable-panel>
    <x-artisanpack-card :classes="[ 'body' => 'ec-stack-xl' ]">
        {{-- Attributes --}}
        <section aria-labelledby="variable-attributes-heading" class="ec-stack-sm">
            <x-artisanpack-heading :level="3" :size="5" id="variable-attributes-heading">{{ __( 'Attributes' ) }}</x-artisanpack-heading>

            @error( 'state.attributes' )
                <p class="ec-field-error" role="alert"><x-artisanpack-icon name="circle-alert" aria-hidden="true" />{{ $message }}</p>
            @enderror

            @if ( [] === $attributes )
                <p class="ec-muted">{{ __( 'Add attributes such as size or color, then generate a variant for each combination.' ) }}</p>
            @else
                <ol class="ec-list">
                    @foreach ( $attributes as $a => $attribute )
                        @php
                            $attributeName = filled( $attribute['label'] ?? null ) ? (string) $attribute['label'] : __( 'Attribute :number', [ 'number' => $a + 1 ] );
                        @endphp
                        <li wire:key="attribute-{{ $attribute['uid'] }}" class="ec-item" data-attribute="{{ $a }}">
                            <div class="ec-attr-row">
                                <x-artisanpack-input id="attribute-{{ $a }}-label" :label="__( 'Attribute' )" :placeholder="__( 'Size' )" wire:model.live.blur="state.attributes.{{ $a }}.label" :disabled="$readOnly" />
                                <x-artisanpack-input id="attribute-{{ $a }}-key" class="ec-mono-input" dir="ltr" :label="__( 'Key' )" :hint="__( 'Optional; made from the label.' )" wire:model.live.blur="state.attributes.{{ $a }}.key" :disabled="$readOnly" />
                                <div class="ec-attr-row-end">
                                    <x-artisanpack-toggle id="attribute-{{ $a }}-variation" size="sm" :label="__( 'Used for variations' )" wire:model.live="state.attributes.{{ $a }}.is_variation" :disabled="$readOnly" />
                                </div>
                                @unless ( $readOnly )
                                    <div class="ec-attr-row-end ec-row-actions">
                                        <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="arrow-up" wire:click="moveAttribute( {{ $a }}, -1 )" wire:loading.attr="disabled" data-reorder="up" data-reorder-list="attributes" data-reorder-key="attribute-{{ $attribute['uid'] }}" data-reorder-item="{{ $attributeName }}" :disabled="$loop->first" :aria-label="__( 'Move :name up', [ 'name' => $attributeName ] )" />
                                        <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="arrow-down" wire:click="moveAttribute( {{ $a }}, 1 )" wire:loading.attr="disabled" data-reorder="down" data-reorder-list="attributes" data-reorder-key="attribute-{{ $attribute['uid'] }}" data-reorder-item="{{ $attributeName }}" :disabled="$loop->last" :aria-label="__( 'Move :name down', [ 'name' => $attributeName ] )" />
                                        <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="trash" wire:click="removeAttribute( {{ $a }} )" wire:loading.attr="disabled" :aria-label="__( 'Remove :item', [ 'item' => $attributeName ] )" />
                                    </div>
                                @endunless
                            </div>

                            <fieldset class="ec-values">
                                <legend class="apui-sr-only">{{ __( 'Values · :attribute', [ 'attribute' => $attributeName ] ) }}</legend>
                                <span class="ec-values-title" aria-hidden="true">{{ __( 'Values' ) }}</span>
                                @foreach ( (array) ( $attribute['values'] ?? [] ) as $v => $value )
                                    @php
                                        $valueName = filled( $value['label'] ?? null ) ? (string) $value['label'] : __( 'Value :number', [ 'number' => $v + 1 ] );
                                        $swatch    = (string) ( $value['swatch'] ?? '' );
                                    @endphp
                                    <div wire:key="value-{{ $value['uid'] }}" class="ec-value-row" data-attribute-value="{{ $a }}-{{ $v }}">
                                        <x-artisanpack-input id="value-{{ $a }}-{{ $v }}-label" size="sm" class="ec-value-label" :aria-label="__( 'Value :number of :attribute', [ 'number' => $v + 1, 'attribute' => $attributeName ] )" :placeholder="__( 'Value' )" wire:model.live.blur="state.attributes.{{ $a }}.values.{{ $v }}.label" :disabled="$readOnly" />
                                        <x-artisanpack-input id="value-{{ $a }}-{{ $v }}-swatch" size="sm" class="ec-value-swatch ec-mono-input" dir="ltr" :aria-label="__( 'Swatch for :value', [ 'value' => $valueName ] )" :placeholder="__( 'Swatch' )" wire:model.live.blur="state.attributes.{{ $a }}.values.{{ $v }}.swatch" :disabled="$readOnly" />
                                        @if ( 1 === preg_match( '/^#[0-9a-fA-F]{3,8}$/', $swatch ) )
                                            {{-- An SVG fill, not an inline style, so a nonce-only style CSP keeps the colour. --}}
                                            <svg class="ec-swatch" width="20" height="20" viewBox="0 0 20 20" aria-hidden="true" focusable="false" data-swatch><rect x="0.5" y="0.5" width="19" height="19" rx="3.5" fill="{{ $swatch }}" /></svg>
                                        @endif
                                        @unless ( $readOnly )
                                            <div class="ec-row-actions">
                                                <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="arrow-up" wire:click="moveValue( {{ $a }}, {{ $v }}, -1 )" wire:loading.attr="disabled" data-reorder="up" data-reorder-list="values-{{ $attribute['uid'] }}" data-reorder-key="value-{{ $value['uid'] }}" data-reorder-item="{{ $valueName }}" :disabled="$loop->first" :aria-label="__( 'Move :value up', [ 'value' => $valueName ] )" />
                                                <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="arrow-down" wire:click="moveValue( {{ $a }}, {{ $v }}, 1 )" wire:loading.attr="disabled" data-reorder="down" data-reorder-list="values-{{ $attribute['uid'] }}" data-reorder-key="value-{{ $value['uid'] }}" data-reorder-item="{{ $valueName }}" :disabled="$loop->last" :aria-label="__( 'Move :value down', [ 'value' => $valueName ] )" />
                                                <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="trash" wire:click="removeValue( {{ $a }}, {{ $v }} )" wire:loading.attr="disabled" :aria-label="__( 'Remove :value', [ 'value' => $valueName ] )" />
                                            </div>
                                        @endunless
                                    </div>
                                @endforeach
                                @unless ( $readOnly )
                                    <div>
                                        <x-artisanpack-button variant="ghost" color="neutral" size="xs" leading-icon="plus" wire:click="addValue( {{ $a }} )" :aria-label="__( 'Add value to :attribute', [ 'attribute' => $attributeName ] )">{{ __( 'Add value' ) }}</x-artisanpack-button>
                                    </div>
                                @endunless
                            </fieldset>
                        </li>
                    @endforeach
                </ol>
            @endif

            @unless ( $readOnly )
                <div>
                    <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="plus" wire:click="addAttribute">{{ __( 'Add attribute' ) }}</x-artisanpack-button>
                </div>
            @endunless
        </section>

        {{-- Generator --}}
        @unless ( $readOnly )
            <section class="ec-generate" aria-labelledby="variable-generate-heading">
                <x-artisanpack-heading :level="3" :size="6" id="variable-generate-heading">{{ __( 'Generate variants' ) }}</x-artisanpack-heading>
                <p class="ec-muted">
                    {{ trans_choice( 'The attributes describe :count combination.|The attributes describe :count combinations.', $matrixSize, [ 'count' => $matrixSize ] ) }}
                    {{ __( 'Existing variants are kept; only missing combinations are added.' ) }}
                </p>

                @if ( $pendingGenerate > 0 )
                    <div
                        class="ec-confirm ec-confirm-boxed"
                        role="alertdialog"
                        aria-labelledby="variable-generate-confirm"
                        x-init="$nextTick( () => $el.querySelector( 'button' )?.focus() )"
                        data-generate-confirm
                    >
                        <x-artisanpack-icon name="triangle-alert" aria-hidden="true" />
                        <p id="variable-generate-confirm" class="ec-confirm-title">{{ trans_choice( 'This adds :count variant. Continue?|This adds :count variants. Continue?', $pendingGenerate, [ 'count' => $pendingGenerate ] ) }}</p>
                        <div class="ec-confirm-actions">
                            <x-artisanpack-button size="sm" wire:click="confirmGenerate" data-focus-return="generate-variants" wire:loading.attr="disabled">{{ __( 'Add variants' ) }}</x-artisanpack-button>
                            <x-artisanpack-button variant="ghost" color="neutral" size="sm" wire:click="cancelGenerate" data-focus-return="generate-variants">{{ __( 'Cancel' ) }}</x-artisanpack-button>
                        </div>
                    </div>
                @else
                    <div>
                        <x-artisanpack-button color="neutral" size="sm" leading-icon="layout-grid" wire:click="generateVariants" data-focus-key="generate-variants" wire:loading.attr="disabled">{{ __( 'Generate variants' ) }}</x-artisanpack-button>
                    </div>
                @endif
            </section>
        @endunless

        {{-- Variants --}}
        <section aria-labelledby="variable-variants-heading" class="ec-stack-sm">
            <x-artisanpack-heading :level="3" :size="5" id="variable-variants-heading">{{ trans_choice( ':count variant|:count variants', count( $variants ), [ 'count' => count( $variants ) ] ) }}</x-artisanpack-heading>

            @error( 'state.variants' )
                <p class="ec-field-error" role="alert"><x-artisanpack-icon name="circle-alert" aria-hidden="true" />{{ $message }}</p>
            @enderror

            @if ( [] === $variants )
                <p class="ec-muted">{{ __( 'No variants yet.' ) }}</p>
            @else
                <div class="apui-table ec-table">
                    <div data-scope="table" data-part="scroll" data-density="compact">
                        <table data-scope="table" data-part="table">
                            <caption class="apui-sr-only">{{ __( 'Variants' ) }}</caption>
                            <thead>
                                <tr>
                                    <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Variant' ) }}</th>
                                    <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'SKU' ) }}</th>
                                    @foreach ( $currencies as $currency )
                                        <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Price (:currency)', [ 'currency' => $currency ] ) }}</th>
                                    @endforeach
                                    <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Stock' ) }}</th>
                                    <th data-scope="table" data-part="head-cell" scope="col">{{ __( 'Image' ) }}</th>
                                    <th data-scope="table" data-part="head-cell" scope="col"><span class="apui-sr-only">{{ __( 'Actions' ) }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ( $variants as $i => $variant )
                                    @php
                                        $variantLabel = filled( $variant['name'] ?? null ) ? (string) $variant['name'] : __( 'Variant :number', [ 'number' => $i + 1 ] );
                                        $preview      = ( $previewableMedia[ $i ] ?? false ? ProductMedia::mediaUrl( (int) $variant['image_media_id'] ) : null ) ?? ProductMedia::safeUrl( $variant['image_url'] ?? null );
                                    @endphp
                                    <tr data-scope="table" data-part="row" class="ec-row-top" wire:key="variant-{{ $variant['id'] ?? 'new-' . $i . '-' . md5( json_encode( $variant['options'] ?? [] ) ) }}" data-variant-row="{{ $i }}">
                                        <td data-scope="table" data-part="cell">
                                            <div class="ec-variant-cell">
                                                @if ( [] !== (array) ( $variant['options'] ?? [] ) )
                                                    <div class="ec-badge-row">
                                                        @foreach ( (array) $variant['options'] as $attributeUid => $valueUid )
                                                            <x-artisanpack-badge size="sm" variant="outline" color="neutral">{{ $optionLabels[ $attributeUid ][ $valueUid ] ?? __( 'Removed value' ) }}</x-artisanpack-badge>
                                                        @endforeach
                                                    </div>
                                                @endif
                                                <x-artisanpack-input id="variant-{{ $i }}-name" size="sm" :aria-label="__( 'Name of variant :number', [ 'number' => $i + 1 ] )" wire:model.live.blur="state.variants.{{ $i }}.name" :disabled="$readOnly" />
                                            </div>
                                        </td>
                                        <td data-scope="table" data-part="cell">
                                            <div class="ec-col-sku">
                                                <x-artisanpack-input
                                                    id="variant-{{ $i }}-sku"
                                                    size="sm"
                                                    class="ec-mono-input"
                                                    dir="ltr"
                                                    :aria-label="__( 'SKU for :variant', [ 'variant' => $variantLabel ] )"
                                                    :error="isset( $skuClashes[ $i ] ) ? __( 'Already used by :variant.', [ 'variant' => $skuClashes[ $i ] ] ) : null"
                                                    wire:model.live.blur="state.variants.{{ $i }}.sku"
                                                    :disabled="$readOnly"
                                                    data-variant-sku
                                                />
                                            </div>
                                        </td>
                                        @foreach ( $currencies as $currency )
                                            <td data-scope="table" data-part="cell">
                                                <div class="ec-col-price">
                                                    <x-artisanpack-ec-money-input
                                                        id="variant-{{ $i }}-price-{{ $currency }}"
                                                        size="sm"
                                                        :currency="$currency"
                                                        :aria-label="__( ':currency price for :variant', [ 'currency' => $currency, 'variant' => $variantLabel ] )"
                                                        :placeholder="$currency === $baseCurrency ? null : __( 'From store currency' )"
                                                        wire:model="state.variants.{{ $i }}.prices.{{ $currency }}"
                                                        :disabled="$readOnly"
                                                    />
                                                </div>
                                            </td>
                                        @endforeach
                                        <td data-scope="table" data-part="cell">
                                            <div class="ec-col-stock">
                                                <x-artisanpack-input id="variant-{{ $i }}-stock" type="number" step="1" size="sm" dir="ltr" :aria-label="__( 'Stock for :variant', [ 'variant' => $variantLabel ] )" wire:model.live.debounce.400ms="state.variants.{{ $i }}.stock" :disabled="$readOnly" :readonly="! $canAdjustStock" />
                                            </div>
                                        </td>
                                        <td data-scope="table" data-part="cell">
                                            <div class="ec-variant-image">
                                                @if ( null !== $preview )
                                                    <img src="{{ $preview }}" alt="{{ $variantLabel }}" class="ec-variant-thumb" />
                                                @endif
                                                @if ( $mediaLibrary )
                                                    @unless ( $readOnly )
                                                        <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="pencil" x-on:click="Livewire.dispatch( 'open-media-modal', { context: {{ Js::from( VariablePanel::MEDIA_CONTEXT . $i ) }} } )" :aria-label="__( 'Choose image for :variant', [ 'variant' => $variantLabel ] )" />
                                                    @endunless
                                                @else
                                                    <x-artisanpack-input id="variant-{{ $i }}-image" type="url" size="sm" dir="ltr" class="ec-col-image-url" :aria-label="__( 'Image URL for :variant', [ 'variant' => $variantLabel ] )" wire:model.live.blur="state.variants.{{ $i }}.image_url" :disabled="$readOnly" />
                                                @endif
                                            </div>
                                        </td>
                                        <td data-scope="table" data-part="cell">
                                            @unless ( $readOnly )
                                                <div class="ec-row-actions">
                                                    <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="arrow-up" wire:click="moveVariant( {{ $i }}, -1 )" wire:loading.attr="disabled" data-reorder="up" data-reorder-list="variants" data-reorder-index="{{ $i }}" data-reorder-item="{{ $variantLabel }}" :disabled="$loop->first" :aria-label="__( 'Move :variant up', [ 'variant' => $variantLabel ] )" />
                                                    <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="arrow-down" wire:click="moveVariant( {{ $i }}, 1 )" wire:loading.attr="disabled" data-reorder="down" data-reorder-list="variants" data-reorder-index="{{ $i }}" data-reorder-item="{{ $variantLabel }}" :disabled="$loop->last" :aria-label="__( 'Move :variant down', [ 'variant' => $variantLabel ] )" />
                                                    <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="trash" wire:click="removeVariant( {{ $i }} )" wire:loading.attr="disabled" :aria-label="__( 'Delete :variant', [ 'variant' => $variantLabel ] )" />
                                                </div>
                                            @endunless
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ( $stockChanged )
                    <div class="ec-field-grid">
                        <x-artisanpack-input
                            id="variants-stock-reason"
                            :label="__( 'Reason for the stock changes' )"
                            :hint="__( 'Recorded with every variant stock change in the activity log.' )"
                            required
                            wire:model="state.stock_reason"
                            :disabled="$readOnly"
                            data-variant-stock-reason
                        />
                    </div>
                @endif
            @endif

            @unless ( $readOnly )
                <div>
                    <x-artisanpack-button variant="ghost" color="neutral" size="sm" leading-icon="plus" wire:click="addVariant">{{ __( 'Add a variant by hand' ) }}</x-artisanpack-button>
                </div>
            @endunless
        </section>
    </x-artisanpack-card>
</div>
