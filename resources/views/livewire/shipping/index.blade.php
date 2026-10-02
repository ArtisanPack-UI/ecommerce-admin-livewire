{{--
    Shipping zones and methods. See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Shipping\Index.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Shipping\Index;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\Countries;

    $countryList = static fn ( array $codes ): string => implode( ', ', array_map( static fn ( string $code ): string => Countries::name( $code ), $codes ) );
@endphp
<div class="flex flex-col gap-4">
    <x-artisanpack-header :title="__( 'Shipping' )" :level="1" separator>
        @if ( $canCreate )
            <x-slot:actions>
                <x-artisanpack-button color="primary" icon="o-plus" wire:click="createZone" :label="__( 'New zone' )" />
            </x-slot:actions>
        @endif
    </x-artisanpack-header>

    @if ( $zones->isNotEmpty() && 0 === $coverage['active'] )
        <x-artisanpack-alert
            color="warning"
            icon="o-exclamation-triangle"
            :title="__( 'No active shipping zones' )"
            :description="__( 'Shoppers cannot choose a shipping method until at least one zone is active.' )"
            role="status"
            data-no-active-zones
        />
    @elseif ( [] !== $coverage['uncovered'] || [] !== $coverage['partial'] )
        <div role="status" data-coverage-warning>
            <x-artisanpack-alert color="warning" icon="o-exclamation-triangle">
                <p class="font-bold">{{ __( 'Some countries have no shipping' ) }}</p>
                @if ( [] !== $coverage['uncovered'] )
                    <p data-uncovered-countries>
                        {{ trans_choice( 'No active zone covers :countries.|No active zone covers these countries: :countries.', count( $coverage['uncovered'] ), [ 'countries' => $countryList( $coverage['uncovered'] ) ] ) }}
                    </p>
                @endif
                @if ( [] !== $coverage['partial'] )
                    <p data-partial-countries>
                        {{ trans_choice( 'Only part of :countries is covered (by region or postal code).|Only parts of these countries are covered (by region or postal code): :countries.', count( $coverage['partial'] ), [ 'countries' => $countryList( $coverage['partial'] ) ] ) }}
                    </p>
                @endif
                <p class="text-sm opacity-75">{{ __( 'Countries are checked when they appear in an inactive zone, an active tax rate, or a customer address.' ) }}</p>
            </x-artisanpack-alert>
        </div>
    @endif

    @if ( $zones->isEmpty() )
        <x-artisanpack-ec-empty-state
            icon="o-truck"
            :title="__( 'No shipping zones yet' )"
            :description="__( 'A zone groups the countries you ship to and the shipping methods offered there.' )"
        />
    @else
        <ul class="flex list-none flex-col gap-3" aria-label="{{ __( 'Shipping zones' ) }}" data-shipping-zones>
            @foreach ( $zones as $zone )
                @php
                    $open    = in_array( (int) $zone->id, $expanded, true );
                    $methods = $zone->methods;
                @endphp
                <li wire:key="zone-{{ $zone->id }}" class="rounded-box border border-base-300" data-zone="{{ $zone->id }}">
                    <div class="flex flex-wrap items-center gap-3 p-3">
                        <x-artisanpack-button
                            variant="ghost"
                            size="sm"
                            :icon="$open ? 'o-chevron-down' : 'o-chevron-right'"
                            wire:click="toggleZone( {{ $zone->id }} )"
                            aria-expanded="{{ $open ? 'true' : 'false' }}"
                            aria-controls="zone-{{ $zone->id }}-methods"
                            :aria-label="$open ? __( 'Hide the methods of :name', [ 'name' => $zone->name ] ) : __( 'Show the methods of :name', [ 'name' => $zone->name ] )"
                        />

                        <div class="min-w-0 grow">
                            <span class="font-semibold">{{ $zone->name }}</span>
                            <span class="ms-2 text-sm opacity-75">{{ __( 'Priority :priority', [ 'priority' => (int) $zone->priority ] ) }}</span>
                            <span class="block text-sm">{{ $countryList( (array) $zone->country_codes ) }}</span>
                            @if ( [] !== (array) ( $zone->region_codes ?? [] ) )
                                <span class="block text-xs opacity-75">{{ __( 'Regions: :regions', [ 'regions' => implode( ', ', (array) $zone->region_codes ) ] ) }}</span>
                            @endif
                            @if ( [] !== (array) ( $zone->postal_patterns ?? [] ) )
                                <span class="block text-xs opacity-75">{{ __( 'Postal codes: :patterns', [ 'patterns' => implode( ', ', (array) $zone->postal_patterns ) ] ) }}</span>
                            @endif
                        </div>

                        <x-artisanpack-badge
                            :value="$zone->is_active ? __( 'Active' ) : __( 'Inactive' )"
                            :color="$zone->is_active ? 'success' : 'neutral'"
                            class="badge-sm"
                        />
                        <x-artisanpack-badge
                            :value="trans_choice( ':count method|:count methods', $methods->count(), [ 'count' => $methods->count() ] )"
                            class="badge-ghost badge-sm"
                        />

                        <div class="flex items-center gap-1">
                            @if ( $canUpdate )
                                <x-artisanpack-button variant="ghost" size="sm" icon="o-pencil" wire:click="editZone( {{ $zone->id }} )" :aria-label="__( 'Edit :name', [ 'name' => $zone->name ] )" />
                            @endif
                            @if ( $canDelete )
                                <x-artisanpack-button variant="ghost" size="sm" icon="o-trash" wire:click="confirmDeleteZone( {{ $zone->id }} )" :aria-label="__( 'Delete :name', [ 'name' => $zone->name ] )" />
                            @endif
                        </div>
                    </div>

                    @if ( $open )
                        <div id="zone-{{ $zone->id }}-methods" class="border-t border-base-300 p-3" data-zone-methods="{{ $zone->id }}">
                            @if ( $methods->isEmpty() )
                                <p class="opacity-75">{{ __( 'This zone has no shipping methods yet.' ) }}</p>
                            @else
                                <ol class="flex list-none flex-col divide-y divide-base-300" aria-label="{{ __( 'Methods of :name', [ 'name' => $zone->name ] ) }}">
                                    @foreach ( $methods as $method )
                                        <li wire:key="method-{{ $method->id }}" class="flex flex-wrap items-center gap-3 py-2" data-method="{{ $method->id }}">
                                            <div class="min-w-0 grow">
                                                <span class="font-semibold">{{ $method->label }}</span>
                                                <span class="ms-2 text-sm opacity-75">{{ Index::methodTypeLabel( (string) $method->key ) }}</span>
                                                <span class="block text-xs opacity-75">
                                                    {{ null === $method->tax_class_key
                                                        ? __( 'Tax class: shipping default' )
                                                        : __( 'Tax class: :class', [ 'class' => $taxClassLabels[ $method->tax_class_key ] ?? $method->tax_class_key ] ) }}
                                                </span>
                                            </div>

                                            <x-artisanpack-badge
                                                :value="$method->is_active ? __( 'Active' ) : __( 'Inactive' )"
                                                :color="$method->is_active ? 'success' : 'neutral'"
                                                class="badge-sm"
                                            />

                                            @if ( $canUpdate )
                                                <div class="flex items-center gap-1">
                                                    <x-artisanpack-button
                                                        variant="ghost"
                                                        size="sm"
                                                        icon="o-arrow-up"
                                                        wire:click="moveMethod( {{ $method->id }}, -1 )"
                                                        :disabled="$loop->first"
                                                        :aria-label="__( 'Move :name up', [ 'name' => $method->label ] )"
                                                    />
                                                    <x-artisanpack-button
                                                        variant="ghost"
                                                        size="sm"
                                                        icon="o-arrow-down"
                                                        wire:click="moveMethod( {{ $method->id }}, 1 )"
                                                        :disabled="$loop->last"
                                                        :aria-label="__( 'Move :name down', [ 'name' => $method->label ] )"
                                                    />
                                                    <x-artisanpack-button variant="ghost" size="sm" icon="o-pencil" wire:click="editMethod( {{ $method->id }} )" :aria-label="__( 'Edit :name', [ 'name' => $method->label ] )" />
                                                    <x-artisanpack-button variant="ghost" size="sm" icon="o-trash" wire:click="confirmDeleteMethod( {{ $method->id }} )" :aria-label="__( 'Delete :name', [ 'name' => $method->label ] )" />
                                                </div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ol>
                            @endif

                            @if ( $canUpdate )
                                <x-artisanpack-button class="mt-2" variant="outline" size="sm" icon="o-plus" wire:click="createMethod( {{ $zone->id }} )" :label="__( 'Add method' )" data-add-method />
                            @endif
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <x-artisanpack-drawer
        wire:model="editingZone"
        :title="null === $zoneId ? __( 'New shipping zone' ) : __( 'Edit shipping zone' )"
        right
        separator
        with-close-button
        close-on-escape
        class="w-full max-w-lg"
    >
        <form wire:submit="saveZone" class="flex flex-col gap-4" data-zone-form>
            <x-artisanpack-input id="zone-name" :label="__( 'Name' )" wire:model="zoneForm.name" required />
            <x-artisanpack-choices-offline
                id="zone-countries"
                :label="__( 'Countries' )"
                :options="$countryOptions"
                searchable
                :no-result-text="__( 'No results found.' )"
                wire:model="zoneForm.country_codes"
            />
            @error( 'zoneForm.country_codes.*' )
                <p class="text-sm text-error" role="alert">{{ $message }}</p>
            @enderror
            <x-artisanpack-textarea
                id="zone-regions"
                :label="__( 'Regions' )"
                :hint="__( 'Optional. Region codes such as CA or US-CA, separated by commas or lines. Leave empty for the whole country.' )"
                wire:model="zoneForm.region_codes"
                rows="2"
            />
            <x-artisanpack-textarea
                id="zone-postal-patterns"
                :label="__( 'Postal codes' )"
                :hint="__( 'Optional. One pattern per line: an exact code, a wildcard such as 90*, or a range such as 90000...90999.' )"
                wire:model="zoneForm.postal_patterns"
                rows="3"
            />
            <x-artisanpack-input id="zone-priority" type="number" :label="__( 'Priority' )" :hint="__( 'Zones are matched from the lowest number up; the first match wins.' )" wire:model="zoneForm.priority" />
            <x-artisanpack-toggle id="zone-active" :label="__( 'Active' )" wire:model="zoneForm.is_active" />

            <div class="flex justify-end gap-2">
                <x-artisanpack-button variant="ghost" wire:click="$set( 'editingZone', false )" :label="__( 'Cancel' )" />
                <x-artisanpack-button type="submit" color="primary" wire:loading.attr="disabled" :label="__( 'Save zone' )" />
            </div>
        </form>
    </x-artisanpack-drawer>

    <x-artisanpack-drawer
        wire:model="editingMethod"
        :title="null === $methodId ? __( 'New shipping method' ) : __( 'Edit shipping method' )"
        right
        separator
        with-close-button
        close-on-escape
        class="w-full max-w-lg"
    >
        <form wire:submit="saveMethod" class="flex flex-col gap-4" data-method-form>
            <x-artisanpack-select
                id="method-key"
                :label="__( 'Type' )"
                :options="$typeOptions"
                :placeholder="__( 'Choose a type' )"
                placeholder-value=""
                wire:model.live="methodForm.key"
            />
            <x-artisanpack-input id="method-label" :label="__( 'Label' )" :hint="__( 'Shown to shoppers at checkout.' )" wire:model="methodForm.label" required />
            <x-artisanpack-select
                id="method-tax-class"
                :label="__( 'Tax class' )"
                :options="$taxClasses"
                :placeholder="__( 'Shipping default' )"
                placeholder-value=""
                wire:model="methodForm.tax_class_key"
            />
            <x-artisanpack-toggle id="method-active" :label="__( 'Active' )" wire:model="methodForm.is_active" />

            @if ( '' !== (string) $methodForm['key'] )
                <fieldset class="fieldset" data-method-config>
                    <legend class="fieldset-legend">{{ __( 'Settings' ) }}</legend>
                    <x-artisanpack-ec-config-form registry="shipping-method" :entry="(string) $methodForm['key']" model="methodForm.config" wire:key="method-config-{{ $methodForm['key'] }}" />
                </fieldset>
            @endif

            <div class="flex justify-end gap-2">
                <x-artisanpack-button variant="ghost" wire:click="$set( 'editingMethod', false )" :label="__( 'Cancel' )" />
                <x-artisanpack-button type="submit" color="primary" wire:loading.attr="disabled" :label="__( 'Save method' )" />
            </div>
        </form>
    </x-artisanpack-drawer>

    @if ( null !== $deleting )
        @php
            $isZone      = $deleting instanceof \ArtisanPackUI\Ecommerce\Models\ShippingZone;
            $deleteName  = $isZone ? $deleting->name : $deleting->label;
            $methodCount = $isZone ? $deleting->methods->count() : 0;
            $deleteTitle = __( 'Delete ":name"?', [ 'name' => $deleteName ] );
        @endphp
        <x-artisanpack-modal wire:model="confirmingDelete" :title="$deleteTitle" separator>
            <p data-delete-shipping="{{ $deletingType }}">
                @if ( $isZone )
                    {{ trans_choice( 'The zone and its :count method are deleted. Shoppers in its countries lose these shipping options.|The zone and its :count methods are deleted. Shoppers in its countries lose these shipping options.', $methodCount, [ 'count' => $methodCount ] ) }}
                @else
                    {{ __( 'Shoppers in this zone can no longer choose this method.' ) }}
                @endif
            </p>

            <x-slot:actions>
                <x-artisanpack-button variant="ghost" wire:click="cancelDelete" :label="__( 'Keep it' )" />
                <x-artisanpack-button
                    color="error"
                    wire:click="delete( {{ \Illuminate\Support\Js::from( $deleteToken ) }} )"
                    wire:loading.attr="disabled"
                    :label="$isZone ? __( 'Delete zone' ) : __( 'Delete method' )"
                />
            </x-slot:actions>
        </x-artisanpack-modal>
    @endif
</div>
