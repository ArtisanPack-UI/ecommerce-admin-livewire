{{--
    A tax rate cell (#133): the value, or its control while the row is being
    edited in place. Codes, postal patterns and numbers stay left-to-right;
    an empty region or postal pattern reads as a muted dash ("All" to screen
    readers), Compound and Taxes shipping as a check or a dash, and Active as
    a dotted badge. While editing, each control is named by its column and
    Save and Cancel take the Actions cell.
    See ArtisanPackUI\EcommerceAdminLivewire\Livewire\Tax\Index.

    @package    ArtisanPack_UI
    @subpackage EcommerceAdminLivewire

    @since      1.0.0
--}}
@php
    use ArtisanPackUI\EcommerceAdminLivewire\Support\ControlOptions;
    use ArtisanPackUI\EcommerceAdminLivewire\Support\Countries;

    $editing = $context['editingRateId'] === (int) $row->getKey();
    $id      = 'tax-rate-' . $row->getKey() . '-' . $column['key'];
    $focus   = 'tax-rate-' . $row->getKey();
@endphp
@if ( $editing )
    @switch ( $column['key'] )
        @case( 'class' )
            {{-- The first field of the row takes focus when editing starts. --}}
            <div class="ec-tax-cell-field" x-data x-init="$nextTick( () => $el.querySelector( 'select, input, button' )?.focus() )">
                <x-artisanpack-select placeholder="" :id="$id" size="sm" :aria-label="__( 'Tax class' )" :options="ControlOptions::from( $context['classOptions'] )" wire:model="rateForm.tax_class_key" />
            </div>
            @break

        @case( 'country' )
            <div class="ec-tax-cell-field">
                <x-artisanpack-select :id="$id" size="sm" :aria-label="__( 'Country' )" :options="ControlOptions::from( $context['countryOptions'] )" :placeholder="__( 'Choose a country' )" wire:model="rateForm.country_code" />
            </div>
            @break

        @case( 'region' )
            <div class="ec-tax-cell-field ec-tax-cell-narrow">
                <x-artisanpack-input :id="$id" size="sm" class="ec-mono-input" dir="ltr" :aria-label="__( 'Region' )" maxlength="10" wire:model="rateForm.region_code" />
            </div>
            @break

        @case( 'postal' )
            <div class="ec-tax-cell-field">
                <x-artisanpack-input :id="$id" size="sm" class="ec-mono-input" dir="ltr" :aria-label="__( 'Postal codes' )" maxlength="60" wire:model="rateForm.postal_pattern" />
            </div>
            @break

        @case( 'rate' )
            <div class="ec-tax-cell-field ec-tax-cell-rate">
                <x-artisanpack-ec-percent-input :id="$id" size="sm" dir="ltr" :aria-label="__( 'Rate' )" wire:model="rateForm.rate_ubps" />
            </div>
            @break

        @case( 'label' )
            <div class="ec-tax-cell-field">
                <x-artisanpack-input :id="$id" size="sm" :aria-label="__( 'Label' )" maxlength="120" wire:model="rateForm.label" />
            </div>
            @break

        @case( 'compound' )
            <x-artisanpack-checkbox :id="$id" :aria-label="__( 'Compound' )" wire:model="rateForm.is_compound" />
            @break

        @case( 'shipping' )
            <x-artisanpack-checkbox :id="$id" :aria-label="__( 'Taxes shipping' )" wire:model="rateForm.is_shipping_taxable" />
            @break

        @case( 'priority' )
            <div class="ec-tax-cell-field ec-tax-cell-narrow">
                <x-artisanpack-input :id="$id" type="number" step="1" size="sm" dir="ltr" :aria-label="__( 'Priority' )" wire:model="rateForm.priority" />
            </div>
            @break

        @case( 'active' )
            <x-artisanpack-checkbox :id="$id" :aria-label="__( 'Active' )" wire:model="rateForm.is_active" />
            @break

        @case( 'actions' )
            <div class="ec-row-actions ec-row-actions-end" data-editing-rate="{{ $row->getKey() }}">
                <x-artisanpack-button size="xs" wire:click="saveRate" wire:loading.attr="disabled" wire:target="saveRate" data-focus-return="{{ $focus }}">{{ __( 'Save' ) }}</x-artisanpack-button>
                <x-artisanpack-button variant="ghost" color="neutral" size="xs" wire:click="cancelRate" data-rate-cancel data-focus-return="{{ $focus }}">{{ __( 'Cancel' ) }}</x-artisanpack-button>
            </div>
            @break
    @endswitch
@else
    @switch ( $column['key'] )
        @case( 'class' )
            {{ $row->taxClass?->label ?? $row->tax_class_key }}
            @break

        @case( 'country' )
            <bdi dir="ltr" aria-hidden="true">{{ $row->country_code }}</bdi><span class="apui-sr-only">{{ Countries::name( (string) $row->country_code ) }}</span>
            @break

        @case( 'region' )
            @if ( '' === (string) $row->region_code )
                <span class="ec-cell-muted" aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'All' ) }}</span>
            @else
                <bdi dir="ltr" class="ec-mono">{{ $row->region_code }}</bdi>
            @endif
            @break

        @case( 'postal' )
            @if ( '' === (string) $row->postal_pattern )
                <span class="ec-cell-muted" aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'All' ) }}</span>
            @else
                <bdi dir="ltr" class="ec-mono">{{ $row->postal_pattern }}</bdi>
            @endif
            @break

        @case( 'rate' )
            <bdi dir="ltr" class="ec-money" data-rate-percent>{{ $row->percent() }}%</bdi>
            @break

        @case( 'label' )
            {{ $row->label }}
            @break

        @case( 'compound' )
        @case( 'shipping' )
            @if ( 'compound' === $column['key'] ? $row->is_compound : $row->is_shipping_taxable )
                <span class="ec-tax-yes" data-flag="yes"><x-artisanpack-icon name="check" aria-hidden="true" /><span class="apui-sr-only">{{ __( 'Yes' ) }}</span></span>
            @else
                <span class="ec-cell-muted" data-flag="no"><span aria-hidden="true">&mdash;</span><span class="apui-sr-only">{{ __( 'No' ) }}</span></span>
            @endif
            @break

        @case( 'priority' )
            <bdi dir="ltr" class="ec-money">{{ $row->priority }}</bdi>
            @break

        @case( 'active' )
            <x-artisanpack-badge size="sm" dot :color="$row->is_active ? 'success' : 'neutral'" data-rate-active="{{ $row->is_active ? 'yes' : 'no' }}">{{ $row->is_active ? __( 'Active' ) : __( 'Inactive' ) }}</x-artisanpack-badge>
            @break

        @case( 'actions' )
            <div class="ec-row-actions ec-row-actions-end">
                @if ( $context['canUpdate'] )
                    <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="pencil" wire:click="editRate( {{ $row->getKey() }} )" data-focus-key="{{ $focus }}" :aria-label="__( 'Edit :name', [ 'name' => $row->label ] )" />
                @endif
                @if ( $context['canDelete'] )
                    <x-artisanpack-button variant="ghost" color="neutral" size="xs" icon-only leading-icon="trash" wire:click="confirmDeleteRate( {{ $row->getKey() }} )" :aria-label="__( 'Delete :name', [ 'name' => $row->label ] )" />
                @endif
            </div>
            @break
    @endswitch
@endif
