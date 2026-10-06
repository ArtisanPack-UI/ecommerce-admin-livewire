@php( $customerName = \ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders\Index::customerName( $row ) )
<div class="flex flex-col">
    <span>{{ $customerName ?? __( 'Guest' ) }}</span>
    <span class="text-xs opacity-75">{{ $row->email }}</span>
</div>
