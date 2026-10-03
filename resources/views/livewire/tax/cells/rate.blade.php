{{--
    A tax rate cell: the value, or its input while the row is being edited.
    See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Tax\Index.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\Countries;

    $editing = $context['editingRateId'] === (int) $row->getKey();
    $id      = 'tax-rate-' . $row->getKey() . '-' . $column['key'];
@endphp
@if ( $editing )
    @switch ( $column['key'] )
        @case( 'class' )
            {{-- The first field of the row takes focus when editing starts. --}}
            <div x-data x-init="$nextTick( () => $el.querySelector( 'select, input' )?.focus() )">
                <x-artisanpack-select :id="$id" class="select-sm" :label="__( 'Tax class' )" :options="$context['classOptions']" wire:model="rateForm.tax_class_key" />
            </div>
            @break

        @case( 'country' )
            <x-artisanpack-select :id="$id" class="select-sm" :label="__( 'Country' )" :options="$context['countryOptions']" :placeholder="__( 'Choose a country' )" placeholder-value="" wire:model="rateForm.country_code" />
            @break

        @case( 'region' )
            <x-artisanpack-input :id="$id" class="input-sm w-24" :label="__( 'Region' )" maxlength="10" wire:model="rateForm.region_code" />
            @break

        @case( 'postal' )
            <x-artisanpack-input :id="$id" class="input-sm" :label="__( 'Postal codes' )" maxlength="60" wire:model="rateForm.postal_pattern" />
            @break

        @case( 'rate' )
            <x-artisanpack-ec-percent-input :id="$id" class="w-28" :label="__( 'Rate' )" wire:model="rateForm.rate_ubps" />
            @break

        @case( 'label' )
            <x-artisanpack-input :id="$id" class="input-sm" :label="__( 'Label' )" maxlength="120" wire:model="rateForm.label" />
            @break

        @case( 'compound' )
            <x-artisanpack-toggle :id="$id" :label="__( 'Compound' )" wire:model="rateForm.is_compound" />
            @break

        @case( 'shipping' )
            <x-artisanpack-toggle :id="$id" :label="__( 'Taxes shipping' )" wire:model="rateForm.is_shipping_taxable" />
            @break

        @case( 'priority' )
            <x-artisanpack-input :id="$id" type="number" step="1" class="input-sm w-20" :label="__( 'Priority' )" wire:model="rateForm.priority" />
            @break

        @case( 'active' )
            <x-artisanpack-toggle :id="$id" :label="__( 'Active' )" wire:model="rateForm.is_active" />
            @break

        @case( 'actions' )
            <div class="flex justify-end gap-1" data-editing-rate="{{ $row->getKey() }}">
                <x-artisanpack-button size="sm" color="primary" wire:click="saveRate" wire:loading.attr="disabled" data-focus-return="tax-rate-{{ $row->getKey() }}" :label="__( 'Save' )" />
                <x-artisanpack-button size="sm" variant="ghost" wire:click="cancelRate" data-focus-return="tax-rate-{{ $row->getKey() }}" :label="__( 'Cancel' )" />
            </div>
            @break
    @endswitch
@else
    @switch ( $column['key'] )
        @case( 'class' )
            {{ $row->taxClass?->label ?? $row->tax_class_key }}
            @break

        @case( 'country' )
            <span title="{{ $row->country_code }}">{{ Countries::name( (string) $row->country_code ) }}</span>
            @break

        @case( 'region' )
            {{ '' === (string) $row->region_code ? __( 'All' ) : $row->region_code }}
            @break

        @case( 'postal' )
            {{ '' === (string) $row->postal_pattern ? __( 'All' ) : $row->postal_pattern }}
            @break

        @case( 'rate' )
            <span class="font-mono" data-rate-percent>{{ $row->percent() }}%</span>
            @break

        @case( 'label' )
            {{ $row->label }}
            @break

        @case( 'compound' )
            {{ $row->is_compound ? __( 'Yes' ) : __( 'No' ) }}
            @break

        @case( 'shipping' )
            {{ $row->is_shipping_taxable ? __( 'Yes' ) : __( 'No' ) }}
            @break

        @case( 'priority' )
            {{ $row->priority }}
            @break

        @case( 'active' )
            <x-artisanpack-badge :value="$row->is_active ? __( 'Active' ) : __( 'Off' )" class="badge-sm" :color="$row->is_active ? 'success' : 'neutral'" />
            @break

        @case( 'actions' )
            <div class="flex justify-end gap-1">
                @if ( $context['canUpdate'] )
                    <x-artisanpack-button variant="ghost" size="sm" icon="o-pencil" wire:click="editRate( {{ $row->getKey() }} )" data-focus-key="tax-rate-{{ $row->getKey() }}" :aria-label="__( 'Edit :name', [ 'name' => $row->label ] )" />
                @endif
                @if ( $context['canDelete'] )
                    <x-artisanpack-button variant="ghost" size="sm" icon="o-trash" wire:click="confirmDeleteRate( {{ $row->getKey() }} )" :aria-label="__( 'Delete :name', [ 'name' => $row->label ] )" />
                @endif
            </div>
            @break
    @endswitch
@endif
