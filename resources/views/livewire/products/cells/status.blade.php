{{-- Products list cell (#119): the status as a dotted badge (active success, archived warning, draft neutral). --}}
<x-artisanpack-badge
    dot
    :color="match ( (string) $row->status ) { 'active' => 'success', 'archived' => 'warning', default => 'neutral' }"
    data-product-status="{{ $row->status }}"
>{{ \ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Index::statusLabel( (string) $row->status ) }}</x-artisanpack-badge>
