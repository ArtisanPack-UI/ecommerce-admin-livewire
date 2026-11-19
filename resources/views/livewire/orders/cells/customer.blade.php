{{-- Orders list cell: the customer's name (or Guest) over their email. --}}
@php( $customerName = \ArtisanPackUI\EcommerceAdminLivewire\Livewire\Orders\Index::customerName( $row ) )
<span data-scope="table" data-part="primary" @class( [ 'ec-muted-text' => null === $customerName ] )>{{ $customerName ?? __( 'Guest' ) }}</span>
<span data-scope="table" data-part="secondary"><bdi dir="ltr">{{ $row->email }}</bdi></span>
