{{--
    Product form (#120). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Form.

    Create or edit a product. One card holds underline tabs for General,
    Pricing, Inventory, Shipping, Tax, Organization, Linked products, Media,
    the type's own settings and Activity. Save is the page's one solid button
    and stays in the header; "All products" is the back link beside it.

    After a failed save, an error alert names the tabs with errors, and each
    of those tabs shows a dot. The dot is the tab's count part: it carries
    the words "(has errors)", so the tab's name says it too, and admin.css
    draws it as a dot.

    Changing the stock on hand reveals the reason field under a sentence
    saying how many units the save adds or removes. Gallery images without
    alt text are flagged on their row.

    Unsaved changes: typing marks the form dirty; leaving the page (or a
    wire:navigate link) asks first until the form is saved.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Form;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\RowKeys;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\StoreCurrencies;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\UnsavedChanges;
    use Illuminate\Support\Js;

    $baseCurrency  = StoreCurrencies::base();
    $tabError      = static fn ( string $key ): ?string => in_array( $key, $errorTabs, true ) ? __( '(has errors)' ) : null;
    $basePrices    = array_filter( $prices, static fn ( array $price ): bool => ! $price['scheduled'] );
    $scheduled     = array_filter( $prices, static fn ( array $price ): bool => (bool) $price['scheduled'] );
    $currencyCodes = ControlOptions::from( array_map( static fn ( string $code ): array => [ 'id' => $code, 'name' => $code ], StoreCurrencies::enabled( $product ) ) );
@endphp
<div
    class="ec-stack"
    x-data="{
        {!! UnsavedChanges::members() !!}
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
    {!! UnsavedChanges::attributes( Form::SAVED_EVENT ) !!}
    x-on:{{ Form::INVALID_EVENT }}.window="dirty = true; focusError( $event.detail.field )"
    data-product-form
>
    <x-artisanpack-page-header
        :title="$isCreate ? __( 'New product' ) : __( 'Edit :name', [ 'name' => $product->name ] )"
        :description="null === $savedAt ? null : __( 'Saved :time', [ 'time' => $savedAt ] )"
        :level="1"
    >
        <x-slot:actions>
            @if ( null !== $indexUrl )
                <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="arrow-left" :href="$indexUrl" data-back-link>{{ __( 'All products' ) }}</x-artisanpack-button>
            @endif
            @unless ( $readOnly )
                <x-artisanpack-button size="sm" leading-icon="check" x-on:click="dirty = false" wire:click="save" wire:loading.attr="disabled" wire:target="save" data-save>{{ __( 'Save' ) }}</x-artisanpack-button>
            @endunless
        </x-slot:actions>
    </x-artisanpack-page-header>

    @if ( null !== $product )
        <x-artisanpack-ec-product-type-warning :product="$product" />
    @endif

    @if ( [] !== $errorTabs )
        <x-artisanpack-alert color="error" icon="circle-alert" data-error-tabs="{{ implode( ' ', $errorTabs ) }}">
            {{ __( 'Fix the errors in: :tabs.', [ 'tabs' => implode( ', ', array_map( static fn ( string $tab ): string => $tabLabels[ $tab ] ?? $tab, $errorTabs ) ) ] ) }}
        </x-artisanpack-alert>
    @endif

    <x-artisanpack-card :classes="[ 'body' => 'ec-stack-lg' ]">
        <x-artisanpack-tabs
            variant="underline"
            wire:model="tab"
            :label="__( 'Product details' )"
            class="ec-product-form-tabs"
            :classes="[ 'list' => 'ec-product-form-tablist' ]"
        >
            {{-- General --}}
            <x-artisanpack-tab name="general" :label="$tabLabels['general']" :count="$tabError( 'general' )">
                <fieldset @disabled( $readOnly ) class="ec-product-form-panel">
                    <div class="ec-field-grid">
                        <x-artisanpack-input id="product-name" class="ec-span-2" :label="__( 'Name' )" wire:model.live.debounce.400ms="name" required />
                        <x-artisanpack-input id="product-slug" class="ec-mono-input" :label="__( 'Slug' )" :hint="__( 'Filled from the name until you edit it.' )" wire:model.live.blur="slug" />

                        @if ( $isCreate )
                            <x-artisanpack-select placeholder="" id="product-type" :label="__( 'Type' )" :options="ControlOptions::from( $typeOptions )" wire:model.live="type" />
                        @else
                            <x-artisanpack-input id="product-type" :label="__( 'Type' )" :value="collect( $typeOptions )->firstWhere( 'id', $type )['name'] ?? $type" readonly />
                        @endif

                        <x-artisanpack-select placeholder="" id="product-status" :label="__( 'Status' )" :options="ControlOptions::from( $statusOptions )" wire:model="status" />

                        <x-artisanpack-input
                            id="product-published-at"
                            type="datetime-local"
                            :label="__( 'Publish date' )"
                            :hint="__( 'Leave empty to publish as soon as the status is active.' )"
                            wire:model="publishedAt"
                        />

                        <x-artisanpack-textarea id="product-short-description" class="ec-span-2" :label="__( 'Short description' )" rows="3" wire:model="shortDescription" />
                        <x-artisanpack-rich-text-editor id="product-description" class="ec-span-2" :label="__( 'Description' )" wire:model="description" />
                    </div>
                </fieldset>
            </x-artisanpack-tab>

            {{-- Pricing --}}
            <x-artisanpack-tab name="pricing" :label="$tabLabels['pricing']" :count="$tabError( 'pricing' )">
                <fieldset @disabled( $readOnly ) class="ec-product-form-panel ec-stack-sm">
                    <p class="ec-muted">{{ __( 'Leave a currency empty to convert it from the store currency (:currency) at checkout.', [ 'currency' => $baseCurrency ] ) }}</p>

                    @foreach ( $basePrices as $index => $price )
                        <div
                            wire:key="price-{{ RowKeys::of( $price, $index ) }}"
                            class="ec-item"
                            role="group"
                            aria-label="{{ $price['currency'] }}"
                            data-price-row="{{ $index }}"
                        >
                            <div class="ec-field-grid ec-product-form-price-grid">
                                <x-artisanpack-input id="price-{{ $index }}-code" :label="__( 'Currency' )" :value="$price['currency']" readonly />
                                <x-artisanpack-ec-money-input
                                    id="price-{{ $index }}-amount"
                                    :currency="$price['currency']"
                                    :label="__( 'Price' )"
                                    :placeholder="$price['currency'] === $baseCurrency ? null : __( 'From :currency', [ 'currency' => $baseCurrency ] )"
                                    wire:model="prices.{{ $index }}.price_amount"
                                />
                                <x-artisanpack-ec-money-input id="price-{{ $index }}-compare" :currency="$price['currency']" :label="__( 'Compare-at price' )" wire:model="prices.{{ $index }}.compare_at_amount" />
                                <x-artisanpack-ec-money-input id="price-{{ $index }}-cost" :currency="$price['currency']" :label="__( 'Cost' )" :hint="__( 'Never shown to customers.' )" wire:model="prices.{{ $index }}.cost_amount" />
                            </div>
                        </div>
                    @endforeach

                    <section class="ec-stack-sm" aria-labelledby="product-scheduled-heading" data-scheduled-prices>
                        <h3 id="product-scheduled-heading" class="ec-subheading">{{ __( 'Scheduled prices' ) }}</h3>

                        @foreach ( $scheduled as $index => $price )
                            <div
                                wire:key="price-{{ RowKeys::of( $price, $index ) }}"
                                class="ec-item"
                                role="group"
                                aria-label="{{ __( 'Scheduled price :number', [ 'number' => $loop->iteration ] ) }}"
                                data-price-row="{{ $index }}"
                            >
                                <div class="ec-field-grid ec-product-form-price-grid">
                                    <x-artisanpack-select
                                        placeholder=""
                                        id="price-{{ $index }}-currency"
                                        :label="__( 'Currency' )"
                                        :options="$currencyCodes"
                                        wire:model.live="prices.{{ $index }}.currency"
                                    />
                                    <x-artisanpack-ec-money-input id="price-{{ $index }}-amount" :currency="$price['currency']" :label="__( 'Price' )" wire:model="prices.{{ $index }}.price_amount" />
                                    <x-artisanpack-ec-money-input id="price-{{ $index }}-compare" :currency="$price['currency']" :label="__( 'Compare-at price' )" wire:model="prices.{{ $index }}.compare_at_amount" />
                                    <x-artisanpack-ec-money-input id="price-{{ $index }}-cost" :currency="$price['currency']" :label="__( 'Cost' )" :hint="__( 'Never shown to customers.' )" wire:model="prices.{{ $index }}.cost_amount" />
                                </div>
                                <div class="ec-field-grid ec-product-form-schedule-grid">
                                    <x-artisanpack-input id="price-{{ $index }}-starts" type="datetime-local" :label="__( 'Starts' )" wire:model="prices.{{ $index }}.starts_at" />
                                    <x-artisanpack-input id="price-{{ $index }}-ends" type="datetime-local" :label="__( 'Ends (optional)' )" wire:model="prices.{{ $index }}.ends_at" />
                                    @unless ( $readOnly )
                                        <div>
                                            <x-artisanpack-button
                                                variant="ghost"
                                                color="neutral"
                                                size="sm"
                                                leading-icon="trash"
                                                wire:click="removePrice( {{ $index }} )" wire:loading.attr="disabled"
                                                :aria-label="__( 'Remove scheduled price :number', [ 'number' => $loop->iteration ] )">{{ __( 'Remove' ) }}</x-artisanpack-button>
                                        </div>
                                    @endunless
                                </div>
                            </div>
                        @endforeach

                        @unless ( $readOnly )
                            <div>
                                <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="calendar" wire:click="addScheduledPrice">{{ __( 'Schedule a price' ) }}</x-artisanpack-button>
                            </div>
                        @endunless
                    </section>
                </fieldset>
            </x-artisanpack-tab>

            {{-- Inventory --}}
            <x-artisanpack-tab name="inventory" :label="$tabLabels['inventory']" :count="$tabError( 'inventory' )">
                <fieldset @disabled( $readOnly ) class="ec-product-form-panel">
                    <div class="ec-field-grid">
                        <x-artisanpack-input id="product-sku" class="ec-mono-input" :label="__( 'SKU' )" wire:model="sku" />
                        <x-artisanpack-input id="product-barcode" class="ec-mono-input" :label="__( 'Barcode' )" wire:model="barcode" />

                        @if ( $tracksStock )
                            <div class="ec-span-2">
                                <x-artisanpack-toggle id="product-track-inventory" :label="__( 'Track inventory' )" wire:model.live="trackInventory" />
                            </div>

                            @if ( $trackInventory )
                                <x-artisanpack-input
                                    id="product-quantity"
                                    type="number"
                                    step="1"
                                    :label="$isCreate ? __( 'Opening stock' ) : __( 'Quantity on hand' )"
                                    wire:model.live.debounce.400ms="quantity"
                                />
                                <x-artisanpack-input id="product-low-stock" type="number" min="0" step="1" :label="__( 'Low-stock threshold' )" wire:model="lowStockThreshold" />

                                <div class="ec-span-2">
                                    <x-artisanpack-toggle id="product-backorder" :label="__( 'Allow backorders' )" :description="__( 'Keep selling when stock reaches 0.' )" wire:model="allowBackorder" />
                                </div>

                                @if ( ! $isCreate && 0 !== $quantityDelta )
                                    <div class="ec-span-2 ec-product-form-stock-change" data-stock-change="{{ $quantityDelta }}">
                                        <p class="ec-product-form-stock-change__text" role="status">
                                            <x-artisanpack-icon name="info" aria-hidden="true" />
                                            <span>
                                                <strong>{{ $quantityDelta > 0
                                                    ? trans_choice( 'Saving adds :count unit.|Saving adds :count units.', $quantityDelta, [ 'count' => $quantityDelta ] )
                                                    : trans_choice( 'Saving removes :count unit.|Saving removes :count units.', abs( $quantityDelta ), [ 'count' => abs( $quantityDelta ) ] ) }}</strong>
                                                {{ __( 'The change is recorded in the activity log.' ) }}
                                            </span>
                                        </p>
                                        <x-artisanpack-input
                                            id="product-stock-reason"
                                            :label="__( 'Reason for the stock change' )"
                                            required
                                            maxlength="255"
                                            wire:model="stockReason"
                                            data-stock-reason
                                        />
                                    </div>
                                @endif
                            @endif
                        @else
                            <p class="ec-span-2 ec-muted">{{ __( 'This product type has no stock of its own.' ) }}</p>
                        @endif
                    </div>
                </fieldset>
            </x-artisanpack-tab>

            {{-- Shipping --}}
            <x-artisanpack-tab name="shipping" :label="$tabLabels['shipping']" :count="$tabError( 'shipping' )">
                <fieldset @disabled( $readOnly ) class="ec-product-form-panel ec-stack-lg">
                    <div class="ec-field-grid">
                        <x-artisanpack-input id="product-weight" type="number" min="0" step="0.001" :label="__( 'Weight' )" wire:model="weight" />
                        <x-artisanpack-select id="product-weight-unit" :label="__( 'Weight unit' )" :options="ControlOptions::from( $weightUnits )" :placeholder="__( 'Choose a unit' )" wire:model="weightUnit" />
                    </div>
                    <div class="ec-field-grid ec-field-grid-3">
                        <x-artisanpack-input id="product-length" type="number" min="0" step="0.001" :label="__( 'Length' )" wire:model="length" />
                        <x-artisanpack-input id="product-width" type="number" min="0" step="0.001" :label="__( 'Width' )" wire:model="width" />
                        <x-artisanpack-input id="product-height" type="number" min="0" step="0.001" :label="__( 'Height' )" wire:model="height" />
                        <x-artisanpack-select id="product-dim-unit" :label="__( 'Dimension unit' )" :options="ControlOptions::from( $dimUnits )" :placeholder="__( 'Choose a unit' )" wire:model="dimUnit" />
                    </div>
                </fieldset>
            </x-artisanpack-tab>

            {{-- Tax --}}
            <x-artisanpack-tab name="tax" :label="$tabLabels['tax']" :count="$tabError( 'tax' )">
                <fieldset @disabled( $readOnly ) class="ec-product-form-panel ec-form-stack">
                    <x-artisanpack-toggle id="product-taxable" :label="__( 'Charge tax on this product' )" wire:model.live="isTaxable" />
                    @if ( $isTaxable )
                        <x-artisanpack-select
                            id="product-tax-class"
                            class="ec-field-narrow"
                            :label="__( 'Tax class' )"
                            :options="ControlOptions::from( $taxClassOptions )"
                            :placeholder="__( 'Standard rate' )"
                            wire:model="taxClassKey"
                        />
                    @endif
                </fieldset>
            </x-artisanpack-tab>

            {{-- Organization --}}
            <x-artisanpack-tab name="organization" :label="$tabLabels['organization']" :count="$tabError( 'organization' )">
                <fieldset @disabled( $readOnly ) class="ec-product-form-panel">
                    <div class="ec-field-grid">
                        <div class="ec-span-2">
                            <x-artisanpack-ec-category-picker model="categoryIds" :options="$categoryOptions" :label="__( 'Categories' )" />
                        </div>
                        <x-artisanpack-combobox
                            multiple
                            allow-create
                            id="product-tags"
                            class="ec-span-2"
                            :label="__( 'Tags' )"
                            :placeholder="__( 'Add a tag' )"
                            :hint="__( 'Press Enter after each tag. New tags are created when you save.' )"
                            wire:model="tagNames"
                        />
                        <x-artisanpack-toggle id="product-featured" :label="__( 'Featured' )" :description="__( 'Storefronts can highlight featured products.' )" wire:model="isFeatured" />
                        <x-artisanpack-input id="product-position" type="number" min="0" step="1" :label="__( 'Catalog position' )" :hint="__( 'Manual catalog order: lower numbers come first.' )" wire:model="catalogPosition" />
                    </div>
                </fieldset>
            </x-artisanpack-tab>

            {{-- Linked products --}}
            <x-artisanpack-tab name="linked" :label="$tabLabels['linked']" :count="$tabError( 'linked' )">
                <div class="ec-stack-xl" data-relations>
                    @foreach ( $relationLists as $list )
                        @php
                            $listId = 'relations-' . $list['type'];
                        @endphp
                        <section class="ec-stack-sm" aria-labelledby="{{ $listId }}-heading" data-relation-list="{{ $list['type'] }}">
                            <div>
                                <h3 id="{{ $listId }}-heading" class="ec-subheading">{{ $list['label'] }}</h3>
                                <p id="{{ $listId }}-help" class="ec-muted">
                                    {{ $list['hint'] }}
                                    @if ( $canWrite && count( $list['products'] ) > 1 )
                                        {{ __( 'Drag a row, or use Move up and Move down, to change the order.' ) }}
                                    @endif
                                </p>
                            </div>

                            @error( 'relations.' . $list['type'] )
                                <p class="ec-field-error" role="alert">{{ $message }}</p>
                            @enderror

                            @if ( [] === $list['products'] )
                                <p class="ec-muted" data-relation-empty>{{ __( 'None yet.' ) }}</p>
                            @else
                                <ol
                                    class="ec-list"
                                    aria-labelledby="{{ $listId }}-heading"
                                    aria-describedby="{{ $listId }}-help"
                                    @if ( $canWrite )
                                        x-data
                                        x-drag-context
                                        x-on:drag:end="$wire.reorderRelations( {{ Js::from( $list['type'] ) }}, $event.detail.orderedIds )"
                                    @endif
                                >
                                    @foreach ( $list['products'] as $index => $related )
                                        @php
                                            $rowError = $errors->first( 'relations.' . $list['type'] . '.' . $index );
                                        @endphp
                                        <li
                                            wire:key="relation-{{ $list['type'] }}-{{ $related['id'] }}"
                                            @if ( $canWrite ) x-drag-item="{{ Js::from( (string) $related['id'] ) }}" @endif
                                            class="ec-item ec-product-form-relation"
                                            @if ( '' !== $rowError ) data-invalid @endif
                                            data-relation-row="{{ $related['id'] }}"
                                        >
                                            @if ( $canWrite )
                                                <x-artisanpack-icon name="grip-vertical" class="ec-product-form-grip" aria-hidden="true" />
                                            @endif
                                            <span class="ec-product-form-relation__name">
                                                <span class="ec-strong">{{ $related['name'] }}</span>
                                                @if ( filled( $related['sku'] ) )
                                                    <bdi class="ec-muted ec-mono" dir="ltr">{{ $related['sku'] }}</bdi>
                                                @endif
                                            </span>
                                            @if ( $canWrite )
                                                <div class="ec-row-actions">
                                                    <x-artisanpack-button variant="ghost" color="neutral" size="xs" leading-icon="arrow-up" wire:click="moveRelation( {{ Js::from( $list['type'] ) }}, {{ $index }}, -1 )" wire:loading.attr="disabled" data-reorder="up" data-reorder-list="{{ $listId }}" data-reorder-key="relation-{{ $list['type'] }}-{{ $related['id'] }}" data-reorder-item="{{ $related['name'] }}" :disabled="$loop->first" :aria-label="__( 'Move :name up', [ 'name' => $related['name'] ] )" />
                                                    <x-artisanpack-button variant="ghost" color="neutral" size="xs" leading-icon="arrow-down" wire:click="moveRelation( {{ Js::from( $list['type'] ) }}, {{ $index }}, 1 )" wire:loading.attr="disabled" data-reorder="down" data-reorder-list="{{ $listId }}" data-reorder-key="relation-{{ $list['type'] }}-{{ $related['id'] }}" data-reorder-item="{{ $related['name'] }}" :disabled="$loop->last" :aria-label="__( 'Move :name down', [ 'name' => $related['name'] ] )" />
                                                    <x-artisanpack-button variant="ghost" color="neutral" size="xs" leading-icon="trash" wire:click="removeRelation( {{ Js::from( $list['type'] ) }}, {{ $index }} )" wire:loading.attr="disabled" :aria-label="__( 'Remove :name from :list', [ 'name' => $related['name'], 'list' => $list['label'] ] )" />
                                                </div>
                                            @endif
                                            @if ( '' !== $rowError )
                                                <p class="ec-field-error ec-product-form-relation__error" role="alert">{{ $rowError }}</p>
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
            <x-artisanpack-tab name="media" :label="$tabLabels['media']" :count="$tabError( 'media' )">
                <fieldset @disabled( $readOnly ) class="ec-product-form-panel ec-stack-lg">
                    <section class="ec-stack-sm" aria-labelledby="product-featured-heading">
                        <h3 id="product-featured-heading" class="ec-subheading">{{ __( 'Featured image' ) }}</h3>
                        <div class="ec-product-form-featured" data-featured-image>
                            @if ( null !== $featuredPreview )
                                <img src="{{ $featuredPreview }}" alt="{{ $name }}" class="ec-product-form-featured__image" />
                            @endif

                            @unless ( $mediaLibrary )
                                <x-artisanpack-input id="product-featured-url" class="ec-product-form-url" type="url" :label="__( 'Image URL' )" wire:model.live.blur="featuredImageUrl" />
                            @endunless

                            @unless ( $readOnly )
                                <div class="ec-actions">
                                    @if ( $mediaLibrary )
                                        <x-artisanpack-button
                                            variant="outline"
                                            color="neutral"
                                            size="sm"
                                            leading-icon="image"
                                            x-on:click="Livewire.dispatch( 'open-media-modal', { context: {{ Js::from( Form::MEDIA_FEATURED ) }} } )">{{ null === $featuredMediaId ? __( 'Choose image' ) : __( 'Replace image' ) }}</x-artisanpack-button>
                                    @endif
                                    @if ( null !== $featuredPreview )
                                        <x-artisanpack-button variant="ghost" color="neutral" size="sm" leading-icon="x" wire:click="clearFeaturedImage">{{ __( 'Remove' ) }}</x-artisanpack-button>
                                    @endif
                                </div>
                            @endunless
                        </div>
                    </section>

                    <section class="ec-stack-sm" aria-labelledby="product-gallery-heading">
                        <h3 id="product-gallery-heading" class="ec-subheading">{{ __( 'Gallery' ) }}</h3>

                        @if ( [] === $gallery )
                            <p class="ec-muted">{{ __( 'No gallery images yet.' ) }}</p>
                        @else
                            <ol class="ec-list" aria-label="{{ __( 'Gallery images, in order' ) }}">
                                @foreach ( $gallery as $index => $image )
                                    @php
                                        $missingAlt = '' === trim( (string) ( $image['alt_text'] ?? '' ) );
                                        $imageName  = $missingAlt ? __( 'Image :number', [ 'number' => $index + 1 ] ) : $image['alt_text'];
                                    @endphp
                                    <li
                                        wire:key="gallery-{{ $image['uid'] ?? $index }}"
                                        class="ec-item ec-product-form-gallery-row"
                                        data-gallery-row="{{ $index }}"
                                        @if ( $missingAlt ) data-missing-alt @endif
                                    >
                                        @if ( null !== ( $galleryPreviews[ $index ] ?? null ) )
                                            <img src="{{ $galleryPreviews[ $index ] }}" alt="{{ $missingAlt ? $name : $image['alt_text'] }}" class="ec-product-form-thumb" />
                                        @else
                                            <span class="ec-product-form-thumb" aria-hidden="true"></span>
                                        @endif

                                        <div class="ec-form-stack">
                                            @if ( null === $image['media_id'] )
                                                <x-artisanpack-input id="gallery-{{ $index }}-url" size="sm" type="url" :label="__( 'Image URL' )" wire:model.live.blur="gallery.{{ $index }}.image_url" />
                                            @endif
                                            <x-artisanpack-input
                                                id="gallery-{{ $index }}-alt"
                                                size="sm"
                                                :label="__( 'Alt text' )"
                                                :placeholder="__( 'Describe the image' )"
                                                :hint="$missingAlt ? __( 'No alt text yet. Describe the image for people who can\'t see it.' ) : __( 'Describe the image for people who can\'t see it.' )"
                                                wire:model.live.blur="gallery.{{ $index }}.alt_text"
                                            />
                                            @if ( $missingAlt )
                                                <div>
                                                    <x-artisanpack-badge size="sm" color="warning" leading-icon="triangle-alert" data-alt-missing>{{ __( 'Missing alt text' ) }}</x-artisanpack-badge>
                                                </div>
                                            @endif
                                        </div>

                                        @unless ( $readOnly )
                                            <div class="ec-row-actions">
                                                <x-artisanpack-button variant="ghost" color="neutral" size="xs" leading-icon="arrow-up" wire:click="moveGalleryImage( {{ $index }}, -1 )" wire:loading.attr="disabled" data-reorder="up" data-reorder-list="gallery" data-reorder-key="gallery-{{ $image['uid'] ?? $index }}" data-reorder-item="{{ $imageName }}" :disabled="$loop->first" :aria-label="__( 'Move image :number up', [ 'number' => $index + 1 ] )" />
                                                <x-artisanpack-button variant="ghost" color="neutral" size="xs" leading-icon="arrow-down" wire:click="moveGalleryImage( {{ $index }}, 1 )" wire:loading.attr="disabled" data-reorder="down" data-reorder-list="gallery" data-reorder-key="gallery-{{ $image['uid'] ?? $index }}" data-reorder-item="{{ $imageName }}" :disabled="$loop->last" :aria-label="__( 'Move image :number down', [ 'number' => $index + 1 ] )" />
                                                <x-artisanpack-button variant="ghost" color="neutral" size="xs" leading-icon="trash" wire:click="removeGalleryImage( {{ $index }} )" wire:loading.attr="disabled" :aria-label="__( 'Remove image :number', [ 'number' => $index + 1 ] )" />
                                            </div>
                                        @endunless
                                    </li>
                                @endforeach
                            </ol>
                        @endif

                        @unless ( $readOnly )
                            <div class="ec-actions">
                                @if ( $mediaLibrary )
                                    <x-artisanpack-button
                                        variant="outline"
                                        color="neutral"
                                        size="sm"
                                        leading-icon="plus"
                                        x-on:click="Livewire.dispatch( 'open-media-modal', { context: {{ Js::from( Form::MEDIA_GALLERY ) }} } )">{{ __( 'Add images' ) }}</x-artisanpack-button>
                                @else
                                    <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="plus" wire:click="addGalleryUrl">{{ __( 'Add image URL' ) }}</x-artisanpack-button>
                                @endif
                            </div>
                        @endunless
                    </section>
                </fieldset>
            </x-artisanpack-tab>

            {{-- Type panel --}}
            @if ( null !== $panelComponent )
                <x-artisanpack-tab name="panel" :label="$tabLabels['panel']" :count="$tabError( 'panel' )">
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
                <x-artisanpack-tab name="panel" :label="$tabLabels['panel']">
                    <x-artisanpack-alert color="info" icon="info" data-no-panel>
                        {{ __( 'The ":type" product type has no settings panel in this admin. Its other tabs still work.', [ 'type' => $type ] ) }}
                    </x-artisanpack-alert>
                </x-artisanpack-tab>
            @endif

            {{-- Activity --}}
            @if ( null !== $product )
                <x-artisanpack-tab name="activity" :label="$tabLabels['activity']">
                    @livewire( 'artisanpack-ecommerce-admin-timeline', [ 'subject' => $product ], key( 'product-timeline-' . $product->id ) )
                </x-artisanpack-tab>
            @endif
        </x-artisanpack-tabs>
    </x-artisanpack-card>

    @if ( $mediaLibrary && ! $readOnly )
        {{-- One modal for the page: the featured image, gallery, and type panels open it with their own context. --}}
        <livewire:dynamic-component :is="\ArtisanPackUI\EcommerceAdminLivewire\Support\ProductMedia::modal()" :multi-select="true" key="product-media-modal" />
    @endif
</div>
