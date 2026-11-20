{{--
    Products list cell (#119): the type as an outline badge, or, when the
    type is no longer registered, a warning badge with its key ("subscription
    · Missing type") whose title and hidden text say why it is read-only.
--}}
@php( $typeLabel = \ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Index::typeLabel( (string) $row->type ) )
@if ( null === $typeLabel )
    <x-artisanpack-badge color="warning" leading-icon="triangle-alert" :title="$row->typeWarning()" data-missing-type>
        <bdi dir="ltr">{{ $row->type }}</bdi> &middot; {{ __( 'Missing type' ) }}<span class="apui-sr-only">. {{ $row->typeWarning() }}</span>
    </x-artisanpack-badge>
@else
    <x-artisanpack-badge variant="outline" color="neutral">{{ $typeLabel }}</x-artisanpack-badge>
@endif
