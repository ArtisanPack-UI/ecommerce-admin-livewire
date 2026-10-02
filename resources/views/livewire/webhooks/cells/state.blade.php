@php( $disabledByEngine = \ArtisanPackUI\EcommerceAdminLivewire\Support\Webhooks::disabledByEngine( $row ) )
@if ( $row->is_active )
    <x-artisanpack-badge :value="__( 'Active' )" class="badge-success" />
@else
    <x-artisanpack-badge :value="__( 'Disabled' )" class="badge-error" />
    @if ( $disabledByEngine )
        <span class="block text-xs opacity-75" data-disabled-by-engine>
            {{ trans_choice( 'Disabled after :count failure|Disabled after :count failures', (int) $row->consecutive_failures, [ 'count' => (int) $row->consecutive_failures ] ) }}
        </span>
    @endif
@endif
