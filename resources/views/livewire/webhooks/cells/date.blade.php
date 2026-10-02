@php( $value = $row->{ $column['key'] . '_at' } )
@if ( null === $value )
    <span class="opacity-75">{{ __( 'Never' ) }}</span>
@else
    <time datetime="{{ $value->toIso8601String() }}">{{ \ArtisanPackUI\EcommerceAdminLivewire\Support\Webhooks::dateTime( $value ) }}</time>
@endif
