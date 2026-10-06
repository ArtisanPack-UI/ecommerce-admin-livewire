{{--
    Product form. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Form.

    Unsaved changes: typing marks the form dirty; leaving the page (or a
    wire:navigate link) asks first until the form is saved.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Form;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\StoreCurrencies;

    $tabLabel = static fn ( string $key, string $label ): string => in_array( $key, $errorTabs, true ) ? __( ':tab (has errors)', [ 'tab' => $label ] ) : $label;
@endphp
<div
    x-data="{
        dirty: false,
        message: {{ \Illuminate\Support\Js::from( __( 'You have unsaved changes. Leave without saving?' ) ) }},
        warnBeforeUnload( event ) {
            if ( this.dirty ) {
                event.preventDefault();
                event.returnValue = '';
            }
        },
        confirmNavigate( event ) {
            if ( this.dirty && ! window.confirm( this.message ) ) {
                event.preventDefault();
            }
        },
        focusError( field ) {
            // After the tab switch renders, focus the control bound to the
            // first invalid property (the component library marks errors on
            // the wrapper, not the control, so they can't be found by state).
            // The type panel is its own component: its errors arrive as
            // `panelState.*` but its fields bind `state.*`.
            const paths = [ field, String( field ?? '' ).replace( /^panelState\./, 'state.' ) ];

            setTimeout( () => {
                const control = Array.from( this.$root.querySelectorAll( 'input, select, textarea' ) ).find(
                    ( el ) => null !== el.offsetParent && Array.from( el.attributes ).some( ( attribute ) => attribute.name.startsWith( 'wire:model' ) && paths.includes( attribute.value ) ),
                );

                control?.focus();
            }, 50 );
        },
    }"
    x-on:input="dirty = true"
    x-on:change="dirty = true"
    x-on:beforeunload.window="warnBeforeUnload( $event )"
    x-on:livewire:navigate.document="confirmNavigate( $event )"
    x-on:{{ Form::SAVED_EVENT }}.window="dirty = false"
    x-on:{{ Form::INVALID_EVENT }}.window="dirty = true; focusError( $event.detail.field )"
    data-product-form
>
    <x-artisanpack-header
        :title="$isCreate ? __( 'New product' ) : __( 'Edit :name', [ 'name' => $product->name ] )"
        :level="1"
        separator
    >
        <x-slot:actions>
            @if ( null !== $indexUrl )
                <x-artisanpack-button variant="ghost" icon="o-arrow-left" :link="$indexUrl" :label="__( 'All products' )" />
            @endif
            @unless ( $readOnly )
                <x-artisanpack-button color="primary" icon="o-check" x-on:click="dirty = false" wire:click="save" wire:loading.attr="disabled" spinner="save" :label="__( 'Save' )" data-save />
            @endunless
        </x-slot:actions>
    </x-artisanpack-header>

    @if ( null !== $product )
        <x-artisanpack-ec-product-type-warning :product="$product" class="mb-4" />
    @endif

    @if ( [] !== $errorTabs )
        <x-artisanpack-alert color="error" icon="o-exclamation-circle" class="mb-4" role="alert">
            {{ __( 'Fix the errors in: :tabs.', [ 'tabs' => implode( ', ', array_map( static fn ( string $tab ): string => match ( $tab ) {
                'general' => __( 'General' ), 'pricing' => __( 'Pricing' ), 'inventory' => __( 'Inventory' ), 'shipping' => __( 'Shipping' ),
                'tax' => __( 'Tax' ), 'organization' => __( 'Organization' ), 'linked' => __( 'Linked products' ), 'media' => __( 'Media' ), 'panel' => $panelLabel ?? __( 'Type settings' ),
                default => $tab,
            }, $errorTabs ) ) ] ) }}
        </x-artisanpack-alert>
    @endif

    <div class="min-w-0">
        <x-artisanpack-tabs wire:model="tab" aria-label="{{ __( 'Product details' ) }}">
            {{-- General --}}
            <x-artisanpack-tab name="general" :label="$tabLabel( 'general', __( 'General' ) )" icon="o-document-text">
                <fieldset @disabled( $readOnly ) class="min-w-0">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <x-artisanpack-input id="product-name" :label="__( 'Name' )" wire:model.live.debounce.400ms="name" required />
                        <x-artisanpack-input id="product-slug" :label="__( 'Slug' )" :hint="__( 'Filled from the name until you edit it.' )" wire:model.blur="slug" />

                        @if ( $isCreate )
                            <x-artisanpack-select id="product-type" :label="__( 'Type' )" :options="$typeOptions" wire:model.live="type" />
                        @else
                            <x-artisanpack-input id="product-type" :label="__( 'Type' )" :value="collect( $typeOptions )->firstWhere( 'id', $type )['name'] ?? $type" readonly />
                        @endif

                        <x-artisanpack-select id="product-status" :label="__( 'Status' )" :options="$statusOptions" wire:model="status" />

                        <x-artisanpack-input
                            id="product-published-at"
                            type="datetime-local"
                            :label="__( 'Publish date' )"
                            :hint="__( 'Leave empty to publish as soon as the status is active.' )"
                            wire:model="publishedAt"
                        />
                    </div>

                    <div class="mt-4 flex flex-col gap-4">
                        <x-artisanpack-textarea id="product-short-description" :label="__( 'Short description' )" rows="2" wire:model="shortDescription" />
                        <x-artisanpack-editor id="product-description" :label="__( 'Description' )" wire:model="description" />
                    </div>
                </fieldset>
            </x-artisanpack-tab>

            {{-- Pricing --}}
            <x-artisanpack-tab name="pricing" :label="$tabLabel( 'pricing', __( 'Pricing' ) )" icon="o-banknotes">
                <fieldset @disabled( $readOnly ) class="min-w-0">
                    <p class="mb-3 text-sm opacity-75">{{ __( 'Leave a currency empty to convert it from the store currency (:currency) at checkout.', [ 'currency' => StoreCurrencies::base() ] ) }}</p>

                    <div class="flex flex-col gap-4">
                        @foreach ( $prices as $index => $price )
                            <fieldset wire:key="price-{{ $index }}-{{ $price['currency'] }}" class="rounded-box border border-base-300 p-3" data-price-row="{{ $index }}">
                                <legend class="px-1 font-semibold">
                                    {{ $price['scheduled'] ? __( 'Scheduled price' ) : $price['currency'] }}
                                </legend>

                                <div class="grid gap-3 md:grid-cols-3">
                                    @if ( $price['scheduled'] )
                                        <x-artisanpack-select
                                            id="price-{{ $index }}-currency"
                                            :label="__( 'Currency' )"
                                            :options="array_map( static fn ( string $code ): array => [ 'id' => $code, 'name' => $code ], StoreCurrencies::enabled( $product ) )"
                                            wire:model.live="prices.{{ $index }}.currency"
                                        />
                                    @endif
                                    <x-artisanpack-ec-money-input id="price-{{ $index }}-amount" :currency="$price['currency']" :label="__( 'Price' )" wire:model="prices.{{ $index }}.price_amount" />
                                    <x-artisanpack-ec-money-input id="price-{{ $index }}-compare" :currency="$price['currency']" :label="__( 'Compare-at price' )" wire:model="prices.{{ $index }}.compare_at_amount" />
                                    <x-artisanpack-ec-money-input id="price-{{ $index }}-cost" :currency="$price['currency']" :label="__( 'Cost' )" :hint="__( 'Never shown to customers.' )" wire:model="prices.{{ $index }}.cost_amount" />
                                </div>

                                @if ( $price['scheduled'] )
                                    <div class="mt-3 grid gap-3 md:grid-cols-3">
                                        <x-artisanpack-input id="price-{{ $index }}-starts" type="datetime-local" :label="__( 'Starts' )" wire:model="prices.{{ $index }}.starts_at" />
                                        <x-artisanpack-input id="price-{{ $index }}-ends" type="datetime-local" :label="__( 'Ends (optional)' )" wire:model="prices.{{ $index }}.ends_at" />
                                        <div class="flex items-end">
                                            <x-artisanpack-button variant="ghost" size="sm" icon="o-trash" wire:click="removePrice( {{ $index }} )" :label="__( 'Remove' )" :aria-label="__( 'Remove scheduled price :number', [ 'number' => $index + 1 ] )" />
                                        </div>
                                    </div>
                                @endif
                            </fieldset>
                        @endforeach
                    </div>

                    @unless ( $readOnly )
                        <x-artisanpack-button class="mt-3" variant="outline" size="sm" icon="o-calendar" wire:click="addScheduledPrice" :label="__( 'Schedule a price' )" />
                    @endunless
                </fieldset>
            </x-artisanpack-tab>

            {{-- Inventory --}}
            <x-artisanpack-tab name="inventory" :label="$tabLabel( 'inventory', __( 'Inventory' ) )" icon="o-archive-box">
                <fieldset @disabled( $readOnly ) class="min-w-0">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <x-artisanpack-input id="product-sku" :label="__( 'SKU' )" wire:model="sku" />
                        <x-artisanpack-input id="product-barcode" :label="__( 'Barcode' )" wire:model="barcode" />
                    </div>

                    @if ( $tracksStock )
                        <div class="mt-4 flex flex-col gap-4">
                            <x-artisanpack-toggle id="product-track-inventory" :label="__( 'Track inventory' )" wire:model.live="trackInventory" />

                            @if ( $trackInventory )
                                <div class="grid gap-4 lg:grid-cols-3">
                                    <x-artisanpack-input
                                        id="product-quantity"
                                        type="number"
                                        step="1"
                                        :label="$isCreate ? __( 'Opening stock' ) : __( 'Quantity on hand' )"
                                        wire:model.live.debounce.400ms="quantity"
                                    />
                                    <x-artisanpack-input id="product-low-stock" type="number" min="0" step="1" :label="__( 'Low-stock threshold' )" wire:model="lowStockThreshold" />
                                    <div class="flex items-end">
                                        <x-artisanpack-toggle id="product-backorder" :label="__( 'Allow backorders' )" wire:model="allowBackorder" />
                                    </div>
                                </div>

                                @if ( ! $isCreate && 0 !== $quantityDelta )
                                    <x-artisanpack-input
                                        id="product-stock-reason"
                                        :label="__( 'Reason for the stock change' )"
                                        :hint="( $quantityDelta > 0
                                            ? trans_choice( 'Saving adds :count unit.|Saving adds :count units.', $quantityDelta, [ 'count' => $quantityDelta ] )
                                            : trans_choice( 'Saving removes :count unit.|Saving removes :count units.', abs( $quantityDelta ), [ 'count' => abs( $quantityDelta ) ] ) ) . ' ' . __( 'The change is recorded in the activity log.' )"
                                        wire:model="stockReason"
                                        data-stock-reason
                                    />
                                @endif
                            @endif
                        </div>
                    @else
                        <p class="mt-4 text-sm opacity-75">{{ __( 'This product type has no stock of its own.' ) }}</p>
                    @endif
                </fieldset>
            </x-artisanpack-tab>

            {{-- Shipping --}}
            <x-artisanpack-tab name="shipping" :label="$tabLabel( 'shipping', __( 'Shipping' ) )" icon="o-truck">
                <fieldset @disabled( $readOnly ) class="min-w-0">
                    <div class="grid gap-4 md:grid-cols-2">
                        <x-artisanpack-input id="product-weight" type="number" min="0" step="0.001" :label="__( 'Weight' )" wire:model="weight" />
                        <x-artisanpack-select id="product-weight-unit" :label="__( 'Weight unit' )" :options="$weightUnits" :placeholder="__( 'Choose a unit' )" placeholder-value="" wire:model="weightUnit" />
                    </div>
                    <div class="mt-4 grid gap-4 md:grid-cols-4">
                        <x-artisanpack-input id="product-length" type="number" min="0" step="0.001" :label="__( 'Length' )" wire:model="length" />
                        <x-artisanpack-input id="product-width" type="number" min="0" step="0.001" :label="__( 'Width' )" wire:model="width" />
                        <x-artisanpack-input id="product-height" type="number" min="0" step="0.001" :label="__( 'Height' )" wire:model="height" />
                        <x-artisanpack-select id="product-dim-unit" :label="__( 'Dimension unit' )" :options="$dimUnits" :placeholder="__( 'Choose a unit' )" placeholder-value="" wire:model="dimUnit" />
                    </div>
                </fieldset>
            </x-artisanpack-tab>

            {{-- Tax --}}
            <x-artisanpack-tab name="tax" :label="$tabLabel( 'tax', __( 'Tax' ) )" icon="o-receipt-percent">
                <fieldset @disabled( $readOnly ) class="min-w-0">
                    <div class="flex flex-col gap-4">
                        <x-artisanpack-toggle id="product-taxable" :label="__( 'Charge tax on this product' )" wire:model.live="isTaxable" />
                        @if ( $isTaxable )
                            <x-artisanpack-select
                                id="product-tax-class"
                                :label="__( 'Tax class' )"
                                :options="$taxClassOptions"
                                :placeholder="__( 'Standard rate' )"
                                placeholder-value=""
                                wire:model="taxClassKey"
                            />
                        @endif
                    </div>
                </fieldset>
            </x-artisanpack-tab>

            {{-- Organization --}}
            <x-artisanpack-tab name="organization" :label="$tabLabel( 'organization', __( 'Organization' ) )" icon="o-folder">
                <fieldset @disabled( $readOnly ) class="min-w-0">
                    <div class="flex flex-col gap-4">
                        <x-artisanpack-ec-category-picker model="categoryIds" :options="$categoryOptions" :label="__( 'Categories' )" />
                        <x-artisanpack-tags id="product-tags" :label="__( 'Tags' )" :hint="__( 'Press Enter after each tag. New tags are created when you save.' )" wire:model="tagNames" />
                    </div>
                </fieldset>
            </x-artisanpack-tab>

            {{-- Linked products --}}
            <x-artisanpack-tab name="linked" :label="$tabLabel( 'linked', __( 'Linked products' ) )" icon="o-link">
                <div class="flex flex-col gap-6" data-relations>
                    @foreach ( $relationLists as $list )
                        @php( $listId = 'relations-' . $list['type'] )
                        <section aria-labelledby="{{ $listId }}-heading" data-relation-list="{{ $list['type'] }}">
                            <h3 id="{{ $listId }}-heading" class="font-semibold">{{ $list['label'] }}</h3>
                            <p id="{{ $listId }}-help" class="mb-2 text-sm opacity-75">
                                {{ $list['hint'] }}
                                @if ( $canWrite && count( $list['products'] ) > 1 )
                                    {{ __( 'Drag a row, or use Move up and Move down, to change the order.' ) }}
                                @endif
                            </p>

                            @error( 'relations.' . $list['type'] )
                                <p class="mb-2 text-sm text-error" role="alert">{{ $message }}</p>
                            @enderror

                            @if ( [] === $list['products'] )
                                <p class="mb-2 text-sm opacity-75" data-relation-empty>{{ __( 'None yet.' ) }}</p>
                            @else
                                <ol
                                    class="mb-3 flex list-none flex-col gap-2"
                                    aria-labelledby="{{ $listId }}-heading"
                                    aria-describedby="{{ $listId }}-help"
                                    @if ( $canWrite )
                                        x-data
                                        x-drag-context
                                        x-on:drag:end="$wire.reorderRelations( {{ \Illuminate\Support\Js::from( $list['type'] ) }}, $event.detail.orderedIds )"
                                    @endif
                                >
                                    @foreach ( $list['products'] as $index => $related )
                                        @php( $rowError = $errors->first( 'relations.' . $list['type'] . '.' . $index ) )
                                        <li
                                            wire:key="relation-{{ $list['type'] }}-{{ $related['id'] }}"
                                            @if ( $canWrite ) x-drag-item="{{ \Illuminate\Support\Js::from( (string) $related['id'] ) }}" @endif
                                            @class( [ 'flex flex-wrap items-center gap-2 rounded-box border p-2', 'border-error' => '' !== $rowError, 'border-base-300' => '' === $rowError ] )
                                            data-relation-row="{{ $related['id'] }}"
                                        >
                                            @if ( $canWrite )
                                                <x-artisanpack-icon name="o-bars-3" class="h-4 w-4 cursor-grab opacity-50" aria-hidden="true" />
                                            @endif
                                            <span class="font-medium">{{ $related['name'] }}</span>
                                            @if ( filled( $related['sku'] ) )
                                                <span class="text-sm opacity-75">{{ $related['sku'] }}</span>
                                            @endif
                                            @if ( '' !== $rowError )
                                                <span class="w-full text-sm text-error" role="alert">{{ $rowError }}</span>
                                            @endif
                                            @if ( $canWrite )
                                                <div class="ms-auto flex gap-1">
                                                    <x-artisanpack-button variant="ghost" size="xs" icon="o-arrow-up" wire:click="moveRelation( {{ \Illuminate\Support\Js::from( $list['type'] ) }}, {{ $index }}, -1 )" wire:loading.attr="disabled" data-reorder="up" data-reorder-list="{{ $listId }}" data-reorder-key="relation-{{ $list['type'] }}-{{ $related['id'] }}" data-reorder-item="{{ $related['name'] }}" :disabled="$loop->first" :aria-label="__( 'Move :name up', [ 'name' => $related['name'] ] )" />
                                                    <x-artisanpack-button variant="ghost" size="xs" icon="o-arrow-down" wire:click="moveRelation( {{ \Illuminate\Support\Js::from( $list['type'] ) }}, {{ $index }}, 1 )" wire:loading.attr="disabled" data-reorder="down" data-reorder-list="{{ $listId }}" data-reorder-key="relation-{{ $list['type'] }}-{{ $related['id'] }}" data-reorder-item="{{ $related['name'] }}" :disabled="$loop->last" :aria-label="__( 'Move :name down', [ 'name' => $related['name'] ] )" />
                                                    <x-artisanpack-button variant="ghost" size="xs" icon="o-trash" wire:click="removeRelation( {{ \Illuminate\Support\Js::from( $list['type'] ) }}, {{ $index }} )" wire:loading.attr="disabled" :aria-label="__( 'Remove :name from :list', [ 'name' => $related['name'], 'list' => $list['label'] ] )" />
                                                </div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ol>
                            @endif

                            @if ( $canWrite && count( $list['products'] ) < Form::MAX_RELATIONS )
                                <x-artisanpack-ec-product-picker
                                    id="relation-pick-{{ $list['type'] }}"
                                    model="relationPick.{{ $list['type'] }}"
                                    single
                                    live
                                    :label="__( 'Add to :list', [ 'list' => $list['label'] ] )"
                                    :options="$this->optionsForPicker( 'product', 'relationPick.' . $list['type'] )"
                                />
                            @endif
                        </section>
                    @endforeach
                </div>
            </x-artisanpack-tab>

            {{-- Media --}}
            <x-artisanpack-tab name="media" :label="$tabLabel( 'media', __( 'Media' ) )" icon="o-photo">
                <fieldset @disabled( $readOnly ) class="min-w-0">
                    <h3 class="mb-2 font-semibold">{{ __( 'Featured image' ) }}</h3>
                    <div class="mb-6 flex flex-wrap items-end gap-4" data-featured-image>
                        @if ( null !== $featuredPreview )
                            <img src="{{ $featuredPreview }}" alt="{{ $name }}" class="size-24 rounded object-cover" />
                        @endif

                        @if ( $mediaLibrary )
                            @unless ( $readOnly )
                                <x-artisanpack-button
                                    variant="outline"
                                    size="sm"
                                    icon="o-photo"
                                    x-on:click="Livewire.dispatch( 'open-media-modal', { context: {{ \Illuminate\Support\Js::from( Form::MEDIA_FEATURED ) }} } )"
                                    :label="null === $featuredMediaId ? __( 'Choose image' ) : __( 'Replace image' )"
                                />
                            @endunless
                        @else
                            <x-artisanpack-input id="product-featured-url" class="min-w-80" type="url" :label="__( 'Image URL' )" wire:model.blur="featuredImageUrl" />
                        @endif

                        @if ( null !== $featuredPreview && ! $readOnly )
                            <x-artisanpack-button variant="ghost" size="sm" icon="o-x-mark" wire:click="clearFeaturedImage" :label="__( 'Remove' )" />
                        @endif
                    </div>

                    <h3 class="mb-2 font-semibold">{{ __( 'Gallery' ) }}</h3>
                    @if ( [] === $gallery )
                        <p class="mb-3 text-sm opacity-75">{{ __( 'No gallery images yet.' ) }}</p>
                    @else
                        <ol class="mb-3 flex list-none flex-col gap-3" aria-label="{{ __( 'Gallery images, in order' ) }}">
                            @foreach ( $gallery as $index => $image )
                                <li wire:key="gallery-{{ $image['uid'] ?? $index }}" class="flex flex-wrap items-end gap-3 rounded-box border border-base-300 p-3" data-gallery-row="{{ $index }}">
                                    @if ( null !== ( $galleryPreviews[ $index ] ?? null ) )
                                        <img src="{{ $galleryPreviews[ $index ] }}" alt="{{ $image['alt_text'] ?: $name }}" class="size-16 rounded object-cover" />
                                    @endif
                                    @if ( null === $image['media_id'] )
                                        <x-artisanpack-input id="gallery-{{ $index }}-url" class="min-w-64" type="url" :label="__( 'Image URL' )" wire:model.blur="gallery.{{ $index }}.image_url" />
                                    @endif
                                    <x-artisanpack-input id="gallery-{{ $index }}-alt" class="min-w-64" :label="__( 'Alt text' )" :hint="__( 'Describe the image for people who can\'t see it.' )" wire:model="gallery.{{ $index }}.alt_text" />
                                    @unless ( $readOnly )
                                        <div class="flex gap-1">
                                            <x-artisanpack-button variant="ghost" size="sm" icon="o-arrow-up" wire:click="moveGalleryImage( {{ $index }}, -1 )" data-reorder="up" data-reorder-list="gallery" data-reorder-key="gallery-{{ $image['uid'] ?? $index }}" data-reorder-item="{{ '' !== ( $image['alt_text'] ?? '' ) ? $image['alt_text'] : __( 'Image :number', [ 'number' => $index + 1 ] ) }}" :disabled="$loop->first" :aria-label="__( 'Move image :number up', [ 'number' => $index + 1 ] )" />
                                            <x-artisanpack-button variant="ghost" size="sm" icon="o-arrow-down" wire:click="moveGalleryImage( {{ $index }}, 1 )" data-reorder="down" data-reorder-list="gallery" data-reorder-key="gallery-{{ $image['uid'] ?? $index }}" data-reorder-item="{{ '' !== ( $image['alt_text'] ?? '' ) ? $image['alt_text'] : __( 'Image :number', [ 'number' => $index + 1 ] ) }}" :disabled="$loop->last" :aria-label="__( 'Move image :number down', [ 'number' => $index + 1 ] )" />
                                            <x-artisanpack-button variant="ghost" size="sm" icon="o-trash" wire:click="removeGalleryImage( {{ $index }} )" :aria-label="__( 'Remove image :number', [ 'number' => $index + 1 ] )" />
                                        </div>
                                    @endunless
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    @unless ( $readOnly )
                        @if ( $mediaLibrary )
                            <x-artisanpack-button
                                variant="outline"
                                size="sm"
                                icon="o-plus"
                                x-on:click="Livewire.dispatch( 'open-media-modal', { context: {{ \Illuminate\Support\Js::from( Form::MEDIA_GALLERY ) }} } )"
                                :label="__( 'Add images' )"
                            />
                        @else
                            <x-artisanpack-button variant="outline" size="sm" icon="o-plus" wire:click="addGalleryUrl" :label="__( 'Add image URL' )" />
                        @endif
                    @endunless

                </fieldset>
            </x-artisanpack-tab>

            {{-- Type panel --}}
            @if ( null !== $panelComponent )
                <x-artisanpack-tab name="panel" :label="$tabLabel( 'panel', $panelLabel ?? __( 'Type settings' ) )" icon="o-adjustments-horizontal">
                    <livewire:dynamic-component
                        :is="$panelComponent"
                        :product-id="$product?->id"
                        :read-only="$readOnly"
                        :panel-errors="$panelErrors"
                        wire:model="panelState"
                        :key="'product-panel-' . $type"
                    />
                </x-artisanpack-tab>
            @elseif ( ! $panelRegistered )
                <x-artisanpack-tab name="panel" :label="__( 'Type settings' )" icon="o-adjustments-horizontal">
                    <x-artisanpack-alert color="info" icon="o-information-circle" role="status" data-no-panel>
                        {{ __( 'The ":type" product type has no settings panel in this admin. Its other tabs still work.', [ 'type' => $type ] ) }}
                    </x-artisanpack-alert>
                </x-artisanpack-tab>
            @endif

            {{-- Activity --}}
            @if ( null !== $product )
                <x-artisanpack-tab name="activity" :label="__( 'Activity' )" icon="o-clock">
                    @livewire( 'artisanpack-ecommerce-admin-timeline', [ 'subject' => $product ], key( 'product-timeline-' . $product->id ) )
                </x-artisanpack-tab>
            @endif
        </x-artisanpack-tabs>
    </div>

    @if ( $mediaLibrary && ! $readOnly )
        {{-- One modal for the page: the featured image, gallery, and type panels open it with their own context. --}}
        <livewire:dynamic-component :is="\ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia::modal()" :multi-select="true" key="product-media-modal" />
    @endif
</div>
