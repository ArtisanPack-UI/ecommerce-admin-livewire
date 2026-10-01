@php( $typeLabel = \ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Index::typeLabel( (string) $row->type ) )
@if ( null === $typeLabel )
    <span class="font-mono text-sm">{{ $row->type }}</span>
    <x-artisanpack-badge :value="__( 'Missing type' )" icon="o-exclamation-triangle" class="badge-sm" color="warning" :title="$row->typeWarning()" data-missing-type />
@else
    {{ $typeLabel }}
@endif
