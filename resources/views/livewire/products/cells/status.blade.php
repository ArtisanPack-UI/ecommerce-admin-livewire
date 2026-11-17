<x-artisanpack-badge
    :value="\ArtisanPackUI\EcommerceAdminLivewire\Livewire\Products\Index::statusLabel( (string) $row->status )"
    size="sm"
    :color="match ( (string) $row->status ) { 'active' => 'success', 'archived' => 'warning', default => 'neutral' }"
/>
