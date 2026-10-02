<div class="flex flex-wrap gap-1">
    @foreach ( (array) $row->events as $event )
        <x-artisanpack-badge :value="\ArtisanPackUI\EcommerceAdminLivewire\Support\Webhooks::eventLabel( (string) $event )" class="badge-ghost badge-sm" />
    @endforeach
</div>
