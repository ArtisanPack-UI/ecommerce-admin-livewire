{{--
    Shipping (#132). See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Shipping\Index.

    Shipping zones in match order, in one card of expandable rows. Each row
    names the zone with its countries, priority, regions and postal codes on
    one muted line, then its Active badge and method count. Opening a zone
    shows its methods in a sunken list with move up and down. An error alert
    leads when no zone is active (checkout can't ship); a warning lists the
    countries no active zone covers. Zones and methods edit in a drawer (the
    method's settings come from the shared Config form, #87) so the list
    stays in view, and deleting asks first in an alert dialog that names
    what goes with it and who loses shipping.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\Ecommerce\Models\ShippingZone;
    use ArtisanPackUI\EcommerceAdminLivewire\Livewire\Shipping\Index;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\Countries;
    use Illuminate\Support\Js;

    $countryList  = static fn ( array $codes ): string => implode( ', ', array_map( static fn ( string $code ): string => Countries::name( $code ), $codes ) );
    $countryShort = static function ( array $codes ) use ( $countryList ): string {
        if ( count( $codes ) <= Index::COUNTRIES_SHOWN + 1 ) {
            return $countryList( $codes );
        }

        return __( ':countries, +:count more', [
            'countries' => $countryList( array_slice( $codes, 0, Index::COUNTRIES_SHOWN ) ),
            'count'     => count( $codes ) - Index::COUNTRIES_SHOWN,
        ] );
    };
@endphp
<div class="ec-stack" data-shipping-index>
    <x-artisanpack-page-header :title="__( 'Shipping' )" :description="__( 'Where you ship and the methods shoppers can choose there.' )" :level="1">
        @if ( $canCreate )
            <x-slot:actions>
                <x-artisanpack-button size="sm" leading-icon="plus" wire:click="createZone" data-new-zone>{{ __( 'New zone' ) }}</x-artisanpack-button>
            </x-slot:actions>
        @endif
    </x-artisanpack-page-header>

    @if ( 0 === $coverage['active'] )
        <x-artisanpack-alert
            color="error"
            icon="circle-alert"
            :title="__( 'No active shipping zones' )"
            :description="__( 'Shoppers cannot choose a shipping method until at least one zone is active.' )"
            data-no-active-zones
        />
    @elseif ( [] !== $coverage['uncovered'] || [] !== $coverage['partial'] )
        <x-artisanpack-alert color="warning" icon="triangle-alert" :title="__( 'Some countries have no shipping' )" data-coverage-warning>
            @if ( [] !== $coverage['uncovered'] )
                <span data-uncovered-countries>{{ trans_choice( 'No active zone covers :countries.|No active zone covers these countries: :countries.', count( $coverage['uncovered'] ), [ 'countries' => $countryList( $coverage['uncovered'] ) ] ) }}</span>
            @endif
            @if ( [] !== $coverage['partial'] )
                <span data-partial-countries>{{ trans_choice( 'Only part of :countries is covered (by region or postal code).|Only parts of these countries are covered (by region or postal code): :countries.', count( $coverage['partial'] ), [ 'countries' => $countryList( $coverage['partial'] ) ] ) }}</span>
            @endif
            <span>{{ __( 'Countries are checked when they appear in an inactive zone, an active tax rate, or a customer address.' ) }}</span>
        </x-artisanpack-alert>
    @endif

    @if ( $zones->isEmpty() )
        <div class="apui-card">
            <x-artisanpack-ec-admin-empty-state
                icon="truck"
                :heading-level="2"
                :title="__( 'No shipping zones yet' )"
                :description="__( 'A zone groups the countries you ship to and the shipping methods offered there.' )"
            >
                @if ( $canCreate )
                    <x-artisanpack-button leading-icon="plus" wire:click="createZone">{{ __( 'New zone' ) }}</x-artisanpack-button>
                @endif
            </x-artisanpack-ec-admin-empty-state>
        </div>
    @else
        <section class="apui-card" aria-label="{{ __( 'Shipping zones' ) }}">
            <ol class="ec-plain-list ec-shipping-zones" data-shipping-zones>
                @foreach ( $zones as $zone )
                    @php
                        $open      = in_array( (int) $zone->id, $expanded, true );
                        $methods   = $zone->methods;
                        $countries = array_map( 'strval', (array) $zone->country_codes );
                        $regions   = (array) ( $zone->region_codes ?? [] );
                        $patterns  = (array) ( $zone->postal_patterns ?? [] );
                    @endphp
                    <li wire:key="zone-{{ $zone->id }}" class="ec-shipping-zone" data-zone="{{ $zone->id }}">
                        <div class="ec-shipping-zone__head">
                            <x-artisanpack-button
                                variant="ghost"
                                color="neutral"
                                size="xs"
                                icon-only
                                :leading-icon="$open ? 'chevron-down' : 'chevron-right'"
                                wire:click="toggleZone( {{ $zone->id }} )"
                                aria-expanded="{{ $open ? 'true' : 'false' }}"
                                :aria-controls="$open ? 'zone-' . $zone->id . '-methods' : null"
                                :aria-label="$open ? __( 'Hide the methods of :name', [ 'name' => $zone->name ] ) : __( 'Show the methods of :name', [ 'name' => $zone->name ] )"
                                data-toggle-zone="{{ $zone->id }}"
                            />

                            <div class="ec-shipping-zone__text">
                                <span class="ec-strong">{{ $zone->name }}</span>
                                <span class="ec-muted ec-shipping-meta" data-zone-meta>
                                    <span><span aria-hidden="true" title="{{ $countryList( $countries ) }}">{{ $countryShort( $countries ) }}</span><span class="apui-sr-only">{{ $countryList( $countries ) }}</span></span>
                                    <span><bdi dir="ltr">{{ __( 'Priority :priority', [ 'priority' => (int) $zone->priority ] ) }}</bdi></span>
                                    @if ( [] !== $regions )
                                        <span>{{ __( 'Regions:' ) }} <bdi dir="ltr" class="ec-mono">{{ implode( ', ', $regions ) }}</bdi></span>
                                    @endif
                                    @if ( [] !== $patterns )
                                        <span>{{ __( 'Postal codes:' ) }} <bdi dir="ltr" class="ec-mono">{{ implode( ', ', $patterns ) }}</bdi></span>
                                    @endif
                                </span>
                            </div>

                            <div class="ec-badge-row">
                                <x-artisanpack-badge size="sm" dot :color="$zone->is_active ? 'success' : 'neutral'">{{ $zone->is_active ? __( 'Active' ) : __( 'Inactive' ) }}</x-artisanpack-badge>
                                <x-artisanpack-badge size="sm" color="neutral" leading-icon="truck">{{ trans_choice( ':count method|:count methods', $methods->count(), [ 'count' => $methods->count() ] ) }}</x-artisanpack-badge>
                            </div>

                            @if ( $canUpdate || $canDelete )
                                <div class="ec-row-actions">
                                    @if ( $canUpdate )
                                        <x-artisanpack-button variant="ghost" color="neutral" size="xs" leading-icon="pencil" wire:click="editZone( {{ $zone->id }} )" :aria-label="__( 'Edit :name', [ 'name' => $zone->name ] )">{{ __( 'Edit' ) }}</x-artisanpack-button>
                                    @endif
                                    @if ( $canDelete )
                                        <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="trash" wire:click="confirmDeleteZone( {{ $zone->id }} )" :aria-label="__( 'Delete :name', [ 'name' => $zone->name ] )" />
                                    @endif
                                </div>
                            @endif
                        </div>

                        @if ( $open )
                            <div id="zone-{{ $zone->id }}-methods" class="ec-shipping-zone__body" data-zone-methods="{{ $zone->id }}">
                                @if ( $methods->isEmpty() )
                                    <p class="ec-muted ec-shipping-methods-empty">{{ __( 'This zone has no shipping methods yet.' ) }}</p>
                                @else
                                    <ol class="ec-plain-list ec-shipping-methods" aria-label="{{ __( 'Methods of :name', [ 'name' => $zone->name ] ) }}">
                                        @foreach ( $methods as $method )
                                            <li wire:key="method-{{ $method->id }}" class="ec-shipping-method" data-method="{{ $method->id }}">
                                                <div class="ec-shipping-zone__text">
                                                    <span class="ec-shipping-method__name">{{ $method->label }}</span>
                                                    <span class="ec-muted">
                                                        {{ Index::methodTypeLabel( (string) $method->key ) }}
                                                        ·
                                                        {{ null === $method->tax_class_key
                                                            ? __( 'Tax class: shipping default' )
                                                            : __( 'Tax class: :class', [ 'class' => $taxClassLabels[ $method->tax_class_key ] ?? $method->tax_class_key ] ) }}
                                                    </span>
                                                </div>

                                                <x-artisanpack-badge size="sm" dot :color="$method->is_active ? 'success' : 'neutral'">{{ $method->is_active ? __( 'Active' ) : __( 'Inactive' ) }}</x-artisanpack-badge>

                                                @if ( $canUpdate )
                                                    <div class="ec-row-actions">
                                                        <x-artisanpack-button
                                                            variant="ghost"
                                                            color="neutral"
                                                            size="xs"
                                                            icon-only
                                                            leading-icon="arrow-up"
                                                            wire:click="moveMethod( {{ $method->id }}, -1 )" wire:loading.attr="disabled" data-reorder="up" data-reorder-list="methods-{{ $zone->id }}" data-reorder-key="method-{{ $method->id }}" data-reorder-item="{{ $method->label }}"
                                                            :disabled="$loop->first"
                                                            :aria-label="__( 'Move :name up', [ 'name' => $method->label ] )"
                                                        />
                                                        <x-artisanpack-button
                                                            variant="ghost"
                                                            color="neutral"
                                                            size="xs"
                                                            icon-only
                                                            leading-icon="arrow-down"
                                                            wire:click="moveMethod( {{ $method->id }}, 1 )" wire:loading.attr="disabled" data-reorder="down" data-reorder-list="methods-{{ $zone->id }}" data-reorder-key="method-{{ $method->id }}" data-reorder-item="{{ $method->label }}"
                                                            :disabled="$loop->last"
                                                            :aria-label="__( 'Move :name down', [ 'name' => $method->label ] )"
                                                        />
                                                        <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="pencil" wire:click="editMethod( {{ $method->id }} )" :aria-label="__( 'Edit :name', [ 'name' => $method->label ] )" />
                                                        <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="trash" wire:click="confirmDeleteMethod( {{ $method->id }} )" :aria-label="__( 'Delete :name', [ 'name' => $method->label ] )" />
                                                    </div>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ol>
                                @endif

                                @if ( $canUpdate )
                                    <div>
                                        <x-artisanpack-button variant="outline" color="neutral" size="sm" leading-icon="plus" wire:click="createMethod( {{ $zone->id }} )" :aria-label="__( 'Add method to :name', [ 'name' => $zone->name ] )" data-add-method>{{ __( 'Add method' ) }}</x-artisanpack-button>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <x-artisanpack-drawer
        wire:model="editingZone"
        :title="null === $zoneId ? __( 'New zone' ) : __( 'Edit zone' )"
        side="end"
        size="md"
        :close-label="__( 'Close' )"
    >
        @if ( $editingZone )
            <form id="shipping-zone-form" wire:submit="saveZone" class="ec-form-stack ec-stack-lg" data-zone-form>
                @include( 'ecommerce-admin::partials.error-summary' )

                <x-artisanpack-input id="zone-name" :label="__( 'Name' )" wire:model="zoneForm.name" maxlength="255" required />

                <div class="ec-stack-sm">
                    <x-artisanpack-combobox
                        multiple
                        id="zone-countries"
                        :label="__( 'Countries' )"
                        :options="ControlOptions::from( $countryOptions )"
                        :empty-label="__( 'No results found.' )"
                        wire:model="zoneForm.country_codes"
                    />
                    @error( 'zoneForm.country_codes.*' )
                        <p class="ec-field-error" role="alert"><x-artisanpack-icon name="circle-alert" aria-hidden="true" />{{ $message }}</p>
                    @enderror
                </div>

                <x-artisanpack-input
                    id="zone-regions"
                    class="ec-mono-input"
                    dir="ltr"
                    :label="__( 'Regions' )"
                    :hint="__( 'State or province codes, comma-separated. Leave empty for the whole country.' )"
                    wire:model="zoneForm.region_codes"
                />
                <x-artisanpack-input
                    id="zone-postal-patterns"
                    class="ec-mono-input"
                    dir="ltr"
                    :label="__( 'Postal codes' )"
                    :hint="__( 'Comma-separated; * matches anything and 10001...10099 is a range. Leave empty for all.' )"
                    wire:model="zoneForm.postal_patterns"
                />
                <x-artisanpack-input id="zone-priority" type="number" dir="ltr" :label="__( 'Priority' )" :hint="__( 'Zones are matched from the lowest number up; the first match wins.' )" wire:model="zoneForm.priority" />
                <x-artisanpack-toggle id="zone-active" :label="__( 'Active' )" wire:model="zoneForm.is_active" />
            </form>
        @endif

        <x-slot:footer>
            <x-artisanpack-button variant="ghost" color="neutral" wire:click="$set( 'editingZone', false )">{{ __( 'Cancel' ) }}</x-artisanpack-button>
            <x-artisanpack-button type="submit" form="shipping-zone-form" wire:loading.attr="disabled" data-save-zone>{{ __( 'Save zone' ) }}</x-artisanpack-button>
        </x-slot:footer>
    </x-artisanpack-drawer>

    <x-artisanpack-drawer
        wire:model="editingMethod"
        :title="null === $methodId ? __( 'New method' ) : __( 'Edit method' )"
        side="end"
        size="md"
        :close-label="__( 'Close' )"
    >
        @if ( $editingMethod )
            <form id="shipping-method-form" wire:submit="saveMethod" class="ec-form-stack ec-stack-lg" data-method-form>
                @include( 'ecommerce-admin::partials.error-summary' )

                <x-artisanpack-select
                    id="method-key"
                    :label="__( 'Type' )"
                    :options="ControlOptions::from( $typeOptions )"
                    :placeholder="__( 'Choose a type' )"
                    wire:model.live="methodForm.key"
                />
                <x-artisanpack-input id="method-label" :label="__( 'Label' )" :hint="__( 'Shown to shoppers at checkout.' )" wire:model="methodForm.label" maxlength="255" required />
                <x-artisanpack-select
                    id="method-tax-class"
                    :label="__( 'Tax class' )"
                    :options="ControlOptions::from( $taxClasses )"
                    :placeholder="__( 'Shipping default' )"
                    wire:model="methodForm.tax_class_key"
                />
                <x-artisanpack-toggle id="method-active" :label="__( 'Active' )" wire:model="methodForm.is_active" />

                @if ( '' !== (string) $methodForm['key'] )
                    <fieldset class="ec-fieldset ec-sunken" data-method-config>
                        <legend class="ec-fieldset__legend">{{ __( 'Settings' ) }}</legend>
                        <x-artisanpack-ec-config-form registry="shipping-method" :entry="(string) $methodForm['key']" model="methodForm.config" wire:key="method-config-{{ $methodForm['key'] }}" />
                    </fieldset>
                @endif
            </form>
        @endif

        <x-slot:footer>
            <x-artisanpack-button variant="ghost" color="neutral" wire:click="$set( 'editingMethod', false )">{{ __( 'Cancel' ) }}</x-artisanpack-button>
            <x-artisanpack-button type="submit" form="shipping-method-form" wire:loading.attr="disabled" data-save-method>{{ __( 'Save method' ) }}</x-artisanpack-button>
        </x-slot:footer>
    </x-artisanpack-drawer>

    @if ( null !== $deleting )
        @php
            $isZone      = $deleting instanceof ShippingZone;
            $deleteName  = $isZone ? (string) $deleting->name : (string) $deleting->label;
            $deleteTitle = __( 'Delete ":name"?', [ 'name' => $deleteName ] );

            if ( $isZone ) {
                $methodCount  = $deleting->methods->count();
                $zoneCodes    = array_map( 'strval', (array) $deleting->country_codes );
                $zoneRegions  = (array) ( $deleting->region_codes ?? [] );
                $places       = [] === $zoneRegions
                    ? $countryShort( $zoneCodes )
                    : __( ':regions in :countries', [ 'regions' => implode( ', ', $zoneRegions ), 'countries' => $countryShort( $zoneCodes ) ] );
                $deleteBody   = trans_choice( '{0} It has no shipping methods.|{1} Its :count shipping method is deleted with it.|[2,*] Its :count shipping methods are deleted with it.', $methodCount, [ 'count' => $methodCount ] );
                $deleteImpact = $deleting->is_active
                    ? __( 'Shoppers in :places will see no shipping methods unless another zone covers them.', [ 'places' => $places ] )
                    : __( 'The zone is inactive, so no shopper uses it at the moment.' );
            } else {
                $methodZone   = $deleting->zone;
                $deleteBody   = null;
                $deleteImpact = match ( true ) {
                    ! $deleting->is_active             => __( 'The method is inactive, so shoppers don’t see it at checkout now.' ),
                    ! (bool) $methodZone?->is_active   => __( 'Its zone is inactive, so shoppers don’t see it at checkout now.' ),
                    default                            => __( 'Shoppers in :zone can no longer choose :method at checkout.', [ 'zone' => (string) ( $methodZone?->name ?? '' ), 'method' => $deleteName ] ),
                };
            }
        @endphp
        <x-artisanpack-dialog
            wire:model="confirmingDelete"
            role="alertdialog"
            size="sm"
            color="error"
            icon="trash"
            :title="$deleteTitle"
            :close-label="__( 'Close' )"
            :classes="[ 'footer' => 'ec-dialog-footer-divided' ]"
            data-delete-confirm
        >
            <p class="ec-body" data-delete-shipping="{{ $deletingType }}">
                @if ( null !== $deleteBody )
                    {{ $deleteBody }}
                @endif
                {{ $deleteImpact }}
            </p>

            <x-slot:footer>
                <x-artisanpack-button variant="ghost" color="neutral" wire:click="cancelDelete" data-autofocus>{{ __( 'Keep it' ) }}</x-artisanpack-button>
                <x-artisanpack-button
                    color="error"
                    wire:click="delete( {{ Js::from( $deleteToken ) }} )"
                    wire:loading.attr="disabled"
                    data-delete-button>{{ $isZone ? __( 'Delete zone' ) : __( 'Delete method' ) }}</x-artisanpack-button>
            </x-slot:footer>
        </x-artisanpack-dialog>
    @endif
</div>
